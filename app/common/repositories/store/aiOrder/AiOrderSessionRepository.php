<?php
// +----------------------------------------------------------------------
// | AI 点餐 - 会话
// +----------------------------------------------------------------------

namespace app\common\repositories\store\aiOrder;

use app\common\model\store\aiOrder\AiOrderSession;
use app\common\model\store\order\StoreCart;
use app\common\model\store\product\Product;
use app\common\model\store\product\ProductAttrValue;
use app\common\model\store\scanOrder\ScanOrderTable;
use app\common\model\system\merchant\Merchant;
use app\common\repositories\store\order\StoreCartRepository;
use app\common\services\aiOrder\DoubaoRealtimeClient;
use think\exception\ValidateException;

class AiOrderSessionRepository
{
    protected $billing;
    protected $configRepo;
    protected $doubao;

    public function __construct(
        AiOrderBillingRepository $billing,
        AiOrderConfigRepository $configRepo,
        DoubaoRealtimeClient $doubao
    ) {
        $this->billing = $billing;
        $this->configRepo = $configRepo;
        $this->doubao = $doubao;
    }

    public function create(int $merId, int $uid, int $tableId = 0): array
    {
        if (!$this->configRepo->isEnabled($merId)) {
            throw new ValidateException('本店未开启AI点餐');
        }
        $this->billing->assertCanStart($merId);

        $cfg = $this->configRepo->getConfig($merId);
        $mer = Merchant::getDB()->where('mer_id', $merId)->field('mer_id,mer_name')->find();
        $merName = $mer ? (string)$mer['mer_name'] : '本店';

        $tableLabel = '';
        if ($tableId > 0) {
            $table = ScanOrderTable::getDB()
                ->where('mer_id', $merId)
                ->where('id', $tableId)
                ->where('is_del', 0)
                ->find();
            $tableLabel = $table ? (string)$table['table_label'] : '';
        }

        $menuLines = $this->buildMenuLines($merId);
        $dialects = config('ai_order.dialects') ?: [];
        $styles = config('ai_order.styles') ?: [];
        $dialectLabel = $dialects[$cfg['dialect']] ?? '普通话';
        $styleLabel = $styles[$cfg['style']] ?? '热情亲切';
        $systemPrompt = $this->doubao->buildSystemPrompt($merName, $menuLines, $dialectLabel, $styleLabel);

        $sessionNo = 'AI' . date('YmdHis') . sprintf('%04d', random_int(0, 9999)) . $merId;
        $now = time();
        $id = AiOrderSession::getDB()->insertGetId([
            'session_no' => $sessionNo,
            'mer_id' => $merId,
            'uid' => $uid,
            'table_id' => $tableId,
            'table_label' => $tableLabel,
            'status' => AiOrderSession::STATUS_ACTIVE,
            'usage_tokens' => 0,
            'usage_seconds' => 0,
            'fee' => 0,
            'rate' => $this->billing->ratePer1k(),
            'summary' => '',
            'provider_request_id' => '',
            'deducted' => 0,
            'start_time' => $now,
            'end_time' => 0,
            'create_time' => date('Y-m-d H:i:s'),
        ]);

        $client = $this->doubao->buildClientSessionPayload($sessionNo, [
            'dialect' => $cfg['dialect'],
            'style' => $cfg['style'],
            'menu_hint' => implode('；', array_slice($menuLines, 0, 10)),
            'system_prompt' => $systemPrompt,
        ]);

        try {
            app()->make(AiOrderDialogRepository::class)->bindSystemPrompt($sessionNo, $systemPrompt);
        } catch (\Throwable $e) {
        }

        return [
            'session_id' => $id,
            'session_no' => $sessionNo,
            'avatar' => $cfg['avatar'],
            'mer_name' => $merName,
            'table_label' => $tableLabel,
            'ai_balance' => $this->billing->getBalance($merId),
            'client' => $client,
        ];
    }

    /**
     * 结束会话：写总结、扣费
     */
    public function end(string $sessionNo, int $uid, array $payload = []): array
    {
        $session = AiOrderSession::getDB()->where('session_no', $sessionNo)->find();
        if (!$session) {
            throw new ValidateException('会话不存在');
        }
        if ((int)$session['uid'] !== $uid && $uid > 0) {
            // 允许同会话结束；严格校验用户
            if ((int)$session['uid'] > 0) {
                throw new ValidateException('无权操作该会话');
            }
        }

        if ((int)$session['status'] === AiOrderSession::STATUS_ENDED && (int)$session['deducted'] === 1) {
            $out = $this->formatEnded($session);
            $transcript = trim((string)($payload['transcript'] ?? '') . "\n" . (string)($session['summary'] ?? ''));
            $out['suggest_items'] = $this->resolveSuggestFromTranscript(
                (int)$session['mer_id'],
                $transcript,
                true
            );
            return $out;
        }

        $usageTokens = max(0, (int)($payload['usage_tokens'] ?? 0));
        $usageSeconds = max(0, (int)($payload['usage_seconds'] ?? 0));
        if ($usageTokens <= 0 && $usageSeconds > 0) {
            // 无 token 回执时按秒粗估（约 20 token/秒，可后续按官方字段替换）
            $usageTokens = $usageSeconds * 20;
        }
        if ($usageTokens <= 0 && (int)$session['start_time'] > 0) {
            $usageSeconds = max($usageSeconds, time() - (int)$session['start_time']);
            $usageTokens = max(1, $usageSeconds * 20);
        }

        $summary = trim((string)($payload['summary'] ?? ''));
        if ($summary === '' && !empty($payload['transcript'])) {
            $summary = $this->summarizeTranscript((string)$payload['transcript']);
        }
        if (mb_strlen($summary) > 2000) {
            $summary = mb_substr($summary, 0, 2000);
        }

        $bill = $this->billing->deductForSession($session, $usageTokens, $usageSeconds);

        AiOrderSession::getDB()->where('id', $session['id'])->update([
            'status' => AiOrderSession::STATUS_ENDED,
            'summary' => $summary,
            'provider_request_id' => (string)($payload['provider_request_id'] ?? ''),
            'end_time' => time(),
        ]);

        $session = AiOrderSession::getDB()->where('id', $session['id'])->find();
        $out = $this->formatEnded($session);
        $out['bill'] = $bill;
        // AI 建议加入购物车：只认顾客明确要点的菜，忽略服务员推荐且尊重「不要」
        $transcript = (string)($payload['transcript'] ?? '');
        $structured = is_array($payload['items'] ?? null) ? $payload['items'] : [];
        $fromIntent = $this->resolveSuggestFromTranscript((int)$session['mer_id'], $transcript, false);
        if ($fromIntent) {
            $out['suggest_items'] = $fromIntent;
        } else {
            $onlyGuess = $structured && !array_filter($structured, function ($it) {
                return (($it['source'] ?? '') !== 'guess');
            });
            $intent = $this->parseDialogIntent($transcript . "\n" . $summary);
            $out['suggest_items'] = $this->filterRejectedSuggest(
                $this->resolveSuggestItems(
                    (int)$session['mer_id'],
                    $intent['positive'],
                    $onlyGuess ? [] : $structured,
                    true
                ),
                $intent['reject']
            );
        }
        return $out;
    }

    public function getByNo(string $sessionNo): ?array
    {
        $row = AiOrderSession::getDB()->where('session_no', $sessionNo)->find();
        return $row ? $row->toArray() : null;
    }

    /**
     * 用户确认后，把 AI 建议菜品加入扫码购物车
     */
    public function addSuggestToCart(string $sessionNo, int $uid, array $items, string $touristKey = ''): array
    {
        $session = AiOrderSession::getDB()->where('session_no', $sessionNo)->find();
        if (!$session) {
            throw new ValidateException('会话不存在');
        }
        if ((int)$session['uid'] > 0 && $uid > 0 && (int)$session['uid'] !== $uid) {
            throw new ValidateException('无权操作该会话');
        }
        $merId = (int)$session['mer_id'];
        if (!$items) {
            throw new ValidateException('请选择要加入的菜品');
        }

        $cartRepo = app()->make(StoreCartRepository::class);
        $added = [];
        foreach ($items as $row) {
            $productId = (int)($row['product_id'] ?? 0);
            $unique = trim((string)($row['product_attr_unique'] ?? ''));
            $num = max(1, (int)($row['cart_num'] ?? 1));
            if ($productId <= 0) {
                continue;
            }
            if ($unique === '') {
                $unique = $this->defaultSkuUnique($productId);
            }
            if ($unique === '') {
                continue;
            }
            $product = Product::getDB()
                ->where('product_id', $productId)
                ->where('mer_id', $merId)
                ->where('is_del', 0)
                ->where('is_show', 1)
                ->where('status', 1)
                ->find();
            if (!$product) {
                continue;
            }
            $sku = ProductAttrValue::getDB()->where('unique', $unique)->where('product_id', $productId)->find();
            if (!$sku || (int)$sku['stock'] < $num) {
                continue;
            }

            $existQ = StoreCart::getDB()->where([
                'mer_id' => $merId,
                'cart_scene' => 'scan_order',
                'product_attr_unique' => $unique,
                'is_del' => 0,
                'is_new' => 0,
                'is_pay' => 0,
            ]);
            if ($uid > 0) {
                $existQ->where('uid', $uid);
            } else {
                $existQ->where('uid', 0)->where('tourist_unique_key', $touristKey);
            }
            $exist = $existQ->find();
            if ($exist) {
                $newNum = (int)$exist['cart_num'] + $num;
                if ((int)$sku['stock'] < $newNum) {
                    continue;
                }
                $cartRepo->update((int)$exist['cart_id'], ['cart_num' => $newNum]);
                $added[] = ['cart_id' => (int)$exist['cart_id'], 'product_id' => $productId, 'cart_num' => $newNum];
            } else {
                $cart = $cartRepo->create([
                    'uid' => $uid,
                    'mer_id' => $merId,
                    'cart_scene' => 'scan_order',
                    'tourist_unique_key' => $uid > 0 ? '' : $touristKey,
                    'product_id' => $productId,
                    'product_attr_unique' => $unique,
                    'cart_num' => $num,
                    'product_type' => 0,
                    'is_new' => 0,
                    'is_pay' => 0,
                    'is_del' => 0,
                    'is_fail' => 0,
                    'source' => 0,
                    'source_id' => $productId,
                ]);
                $added[] = ['cart_id' => (int)$cart['cart_id'], 'product_id' => $productId, 'cart_num' => $num];
            }
        }
        if (!$added) {
            throw new ValidateException('没有可加入的菜品');
        }
        return ['added' => $added, 'count' => count($added)];
    }

    /** 从回合数组匹配建议菜（仅顾客话） */
    public function resolveSuggestFromTurns(int $merId, array $turns, bool $allowGuess = false): array
    {
        $lines = [];
        foreach ($turns as $t) {
            $role = (($t['role'] ?? '') === 'user') ? '顾客' : '服务员';
            $lines[] = $role . '：' . trim((string)($t['text'] ?? ''));
        }
        return $this->resolveSuggestFromTranscript($merId, implode("\n", $lines), $allowGuess);
    }

    /**
     * 从对话稿匹配建议菜（只返回要点要加的，不展示「未勾选」项）：
     * - 顾客明确要点：显示并勾选
     * - 顾客口头接受推荐（也要/推荐的也要）：推荐一并显示并勾选
     * - 顾客说不需要 / 未接受的推荐：不出现
     */
    public function resolveSuggestFromTranscript(int $merId, string $transcript, bool $allowGuess = false): array
    {
        $intent = $this->parseDialogIntent($transcript);
        $wanted = [];
        if ($intent['positive'] !== '') {
            $wanted = $this->resolveSuggestItems($merId, $intent['positive'], [], false);
            foreach ($wanted as &$w) {
                $w['checked'] = 1;
                $w['source'] = 'match';
            }
            unset($w);
            $wanted = $this->filterRejectedSuggest($wanted, $intent['reject']);
        }

        // 顾客侧 ASR 弱/空时：从服务员「已确认点单」话术里找回菜名（用餐愉快前通常会复述）
        if (!$wanted && $intent['assistant'] !== '') {
            $confirmedText = $this->extractConfirmedAssistantText((string)$intent['assistant']);
            if ($confirmedText !== '') {
                $wanted = $this->resolveSuggestItems($merId, $confirmedText, [], false);
                foreach ($wanted as &$w) {
                    $w['checked'] = 1;
                    $w['source'] = 'match';
                }
                unset($w);
                $wanted = $this->filterRejectedSuggest($wanted, $intent['reject']);
            }
        }

        $recommended = [];
        // 仅当顾客明确接受推荐，且未说不需要时，才加入推荐菜
        if (
            $intent['assistant'] !== ''
            && !empty($intent['accept_recommend'])
            && empty($intent['reject_recommend'])
        ) {
            $recommended = $this->resolveSuggestItems($merId, $intent['assistant'], [], false);
            foreach ($recommended as &$r) {
                $r['checked'] = 1;
                $r['source'] = 'recommend';
            }
            unset($r);
            $recommended = $this->filterRejectedSuggest($recommended, $intent['reject']);
        }

        // 合并：顾客要点优先
        $out = [];
        $seen = [];
        foreach (array_merge($wanted, $recommended) as $it) {
            $pid = (int)$it['product_id'];
            if ($pid <= 0 || isset($seen[$pid])) {
                continue;
            }
            // 未勾选的不返回（前端也不展示）
            if (empty($it['checked'])) {
                continue;
            }
            $seen[$pid] = 1;
            $out[] = $it;
        }

        // 热销兜底也不自动勾选展示；只有完全没命中时才给未勾选兜底（前端会过滤掉）
        if (!$out && $allowGuess) {
            return [];
        }
        return $out;
    }

    /**
     * 从服务员话术中抽出「已确认点单」句子，跳过纯推荐问句
     */
    protected function extractConfirmedAssistantText(string $assistant): string
    {
        $parts = preg_split('/[。！？!?；;\n]/u', $assistant) ?: [];
        $keep = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }
            $isRecommendQ = (bool)preg_match('/(要不要|需不需要|推荐|喝点|来杯|加个|顺便)/u', $p);
            $isConfirm = (bool)preg_match(
                '/(安排|给您|帮您|记下|已记|来一份|一份|好的|收到|就点|就这些|已点|点好|用餐愉快)/u',
                $p
            );
            if ($isRecommendQ && !$isConfirm) {
                continue;
            }
            if ($isConfirm) {
                $keep[] = $p;
            }
        }
        return trim(implode(' ', $keep));
    }

    /**
     * 解析对话意图
     * @return array{positive:string,reject:string[],assistant:string,reject_recommend:bool,accept_recommend:bool}
     */
    public function parseDialogIntent(string $transcript): array
    {
        $userChunks = [];
        $assistantChunks = [];
        $raw = trim($transcript);
        if ($raw === '') {
            return [
                'positive' => '',
                'reject' => [],
                'assistant' => '',
                'reject_recommend' => false,
                'accept_recommend' => false,
            ];
        }
        $lines = preg_split('/\r\n|\n|\r/u', $raw) ?: [];
        $hasRole = false;
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^(顾客|用户|我)\s*[：:]\s*(.+)$/u', $line, $m)) {
                $hasRole = true;
                $userChunks[] = trim($m[2]);
            } elseif (preg_match('/^(服务员|助手|AI)\s*[：:]\s*(.+)$/u', $line, $m)) {
                $hasRole = true;
                $assistantChunks[] = trim($m[2]);
            }
        }
        if (!$hasRole) {
            // 无角色前缀时，整段当作用户侧（前端本地字幕可能如此）
            $userChunks[] = preg_replace('/服务员[：:].*/u', '', $raw) ?: $raw;
        }

        $reject = [];
        $pushReject = function ($frag) use (&$reject) {
            $frag = trim((string)$frag, " 的了啊呀哦呢吧～~,，");
            if ($frag === '') {
                return;
            }
            foreach (preg_split('/[和与及、,，\/]+/u', $frag) ?: [] as $p) {
                $p = trim($p, " 的了啊呀哦呢吧～~");
                // 过滤纯语气
                if ($p === '' || in_array($p, ['了', '啊', '吧', '呀', '呢', '哦'], true)) {
                    continue;
                }
                $reject[] = $p;
            }
        };
        $positiveParts = [];
        foreach ($userChunks as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }
            // 1) 前缀否定：不要凉茶 / 不需要冰粉（宾语至少1字，避免吞掉句尾「不需要」）
            $cleaned = preg_replace_callback(
                '/(不要|不需要|不用|别要|不点|不喝|不吃|没要|不想要|先不要)([^，。！？；,.!\n]{1,30})/u',
                function ($m) use ($pushReject) {
                    $pushReject($m[2]);
                    return ' ';
                },
                $chunk
            );
            // 2) 后缀否定：凉茶和冰粉不需要 / 饮料不要
            $cleaned = preg_replace_callback(
                '/([^，。！？；,.!\n]{1,30}?)(不需要|不要了|不用了|不要|别要)(?=[，。！？；,.!\n]|$)/u',
                function ($m) use ($pushReject) {
                    // 避免误伤「只要…」：只要xxx 不含否定
                    $frag = $m[1];
                    if (mb_strpos($frag, '只要') !== false || mb_strpos($frag, '需要') === 0) {
                        return $m[0];
                    }
                    $pushReject($frag);
                    return ' ';
                },
                (string)$cleaned
            );
            // 整句拒绝（不需要了 / 不用了）
            if (preg_match('/^(不需要|不用了|不要了|算了|不用|不要)[了啊呀吧]*$/u', trim((string)$cleaned))) {
                continue;
            }
            $cleaned = trim(preg_replace('/\s+/u', '', (string)$cleaned) ?: '');
            // 前端占位字幕不算点餐意图
            if ($cleaned !== '' && preg_match('/^(识别中|已收到语音|语音已发送|服务员处理中|语音回复中)/u', $cleaned)) {
                continue;
            }
            if ($cleaned !== '') {
                $positiveParts[] = $cleaned;
            }
        }
        $reject = array_values(array_unique($reject));
        $userAll = implode(' ', $userChunks);
        // 明确接受推荐（避免「可以/好的」误触发）
        $acceptRecommend = (bool)preg_match(
            '/(都要|也要|要一份推荐|推荐的也要|你推荐的也要|按你说的|那就按推荐|推荐来一份|加推荐)/u',
            $userAll
        );
        // 拒绝服务员推荐：整句「不需要」或明确说不要推荐/饮料
        $rejectRecommend = false;
        foreach ($userChunks as $chunk) {
            $c = trim($chunk);
            if ($c === '') {
                continue;
            }
            if (preg_match('/^(不需要|不用了|不要了|算了|不用|不要|先不要)[了啊呀吧哦呢]*$/u', $c)) {
                $rejectRecommend = true;
                break;
            }
            if (preg_match('/(不需要|不要|不用).{0,8}(推荐|这些|那些|别的|其他|饮料|喝的)/u', $c)) {
                $rejectRecommend = true;
                break;
            }
            if (preg_match('/(推荐|这些|那些|饮料|喝的).{0,8}(不需要|不要|不用)/u', $c)) {
                $rejectRecommend = true;
                break;
            }
        }
        if ($rejectRecommend) {
            $acceptRecommend = false;
        }

        return [
            'positive' => implode(' ', $positiveParts),
            'reject' => $reject,
            'assistant' => implode(' ', $assistantChunks),
            'reject_recommend' => $rejectRecommend,
            'accept_recommend' => $acceptRecommend,
        ];
    }

    protected function filterRejectedSuggest(array $items, array $rejectFrags): array
    {
        if (!$items) {
            return [];
        }
        if (!$rejectFrags) {
            return array_values($items);
        }
        $out = [];
        foreach ($items as $it) {
            $name = (string)($it['store_name'] ?? '');
            if ($name !== '' && $this->isRejectedDish($name, $rejectFrags)) {
                continue;
            }
            $out[] = $it;
        }
        return $out;
    }

    protected function isRejectedDish(string $name, array $rejectFrags): bool
    {
        foreach ($rejectFrags as $frag) {
            $frag = trim((string)$frag);
            if ($frag === '') {
                continue;
            }
            // 「饮料」这类品类词：名字里含常见饮品也拒
            if ($frag === '饮料' || $frag === '喝的') {
                if (preg_match('/(茶|饮|奶|可乐|雪碧|汽水|果汁|咖啡|冰粉)/u', $name)) {
                    return true;
                }
            }
            if ($this->scoreMenuMatch($frag, $name) >= 20) {
                return true;
            }
            if (mb_strpos($name, $frag) !== false || mb_strpos($frag, $name) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * 从 AI 结构化 items 或对话文本匹配本店菜品
     * @param bool $allowGuess 是否允许热销兜底
     */
    public function resolveSuggestItems(int $merId, string $text, array $items = [], bool $allowGuess = true): array
    {
        $out = [];
        if ($items) {
            foreach ($items as $row) {
                $productId = (int)($row['product_id'] ?? 0);
                $num = max(1, (int)($row['cart_num'] ?? 1));
                if ($productId <= 0) {
                    continue;
                }
                // 已是完整匹配结果时直接沿用（避免再包一层 source=ai）
                if (!empty($row['store_name']) && !empty($row['product_attr_unique']) && isset($row['source'])) {
                    $src = (string)$row['source'];
                    if ($src === 'guess' && !$allowGuess) {
                        continue;
                    }
                    $out[] = [
                        'product_id' => $productId,
                        'store_name' => (string)$row['store_name'],
                        'image' => (string)($row['image'] ?? ''),
                        'price' => $row['price'] ?? 0,
                        'product_attr_unique' => (string)$row['product_attr_unique'],
                        'cart_num' => $num,
                        'source' => $src ?: 'ai',
                        'checked' => (int)($row['checked'] ?? ($src === 'guess' ? 0 : 1)),
                    ];
                    continue;
                }
                $p = Product::getDB()
                    ->where('product_id', $productId)
                    ->where('mer_id', $merId)
                    ->where('is_del', 0)
                    ->where('is_show', 1)
                    ->where('status', 1)
                    ->field('product_id,store_name,image,price,sales')
                    ->find();
                if (!$p) {
                    continue;
                }
                $unique = trim((string)($row['product_attr_unique'] ?? ''));
                if ($unique === '') {
                    $unique = $this->defaultSkuUnique($productId);
                }
                if ($unique === '') {
                    continue;
                }
                $out[] = [
                    'product_id' => $productId,
                    'store_name' => (string)$p['store_name'],
                    'image' => (string)$p['image'],
                    'price' => $p['price'],
                    'product_attr_unique' => $unique,
                    'cart_num' => $num,
                    'source' => 'ai',
                    'checked' => 1,
                ];
            }
            if ($out) {
                return $out;
            }
        }

        $menu = Product::getDB()
            ->where('mer_id', $merId)
            ->where('is_del', 0)
            ->where('is_show', 1)
            ->where('status', 1)
            ->field('product_id,store_name,image,price,sales')
            ->order('sales DESC, product_id DESC')
            ->limit(80)
            ->select()
            ->toArray();

        $text = trim($text);
        if ($text !== '') {
            $scored = [];
            foreach ($menu as $p) {
                $name = (string)$p['store_name'];
                if ($name === '') {
                    continue;
                }
                $score = $this->scoreMenuMatch($text, $name);
                if ($score <= 0) {
                    continue;
                }
                $unique = $this->defaultSkuUnique((int)$p['product_id']);
                if ($unique === '') {
                    continue;
                }
                $scored[] = [
                    'score' => $score,
                    'item' => [
                        'product_id' => (int)$p['product_id'],
                        'store_name' => $name,
                        'image' => (string)$p['image'],
                        'price' => $p['price'],
                        'product_attr_unique' => $unique,
                        'cart_num' => 1,
                        'source' => 'match',
                        'checked' => 1,
                    ],
                ];
            }
            usort($scored, function ($a, $b) {
                return $b['score'] <=> $a['score'];
            });
            // 只要明确命中的前几道，避免把顺带提到的推荐全勾上
            foreach (array_slice($scored, 0, 4) as $row) {
                // 低分弱匹配默认不勾选，减少误加
                $item = $row['item'];
                if ($row['score'] < 40) {
                    $item['checked'] = 0;
                }
                $out[] = $item;
            }
        }

        // 未识别到具体菜名时，才给热销兜底
        if (!$out && $allowGuess && $menu) {
            foreach (array_slice($menu, 0, 2) as $p) {
                $unique = $this->defaultSkuUnique((int)$p['product_id']);
                if ($unique === '') {
                    continue;
                }
                $out[] = [
                    'product_id' => (int)$p['product_id'],
                    'store_name' => (string)$p['store_name'],
                    'image' => (string)$p['image'],
                    'price' => $p['price'],
                    'product_attr_unique' => $unique,
                    'cart_num' => 1,
                    'source' => 'guess',
                    'checked' => 0,
                ];
            }
        }
        return $out;
    }

    /** 对话文本与菜名模糊匹配得分 */
    protected function scoreMenuMatch(string $text, string $name): int
    {
        $text = mb_strtolower(preg_replace('/\s+/u', '', $text) ?: '');
        $name = mb_strtolower(preg_replace('/\s+/u', '', $name) ?: '');
        if ($text === '' || $name === '') {
            return 0;
        }
        if (mb_strpos($text, $name) !== false) {
            return 100 + mb_strlen($name);
        }
        // 去常见后缀后再比
        $nameCore = preg_replace('/(套餐|单人餐|双人餐|份|招牌)/u', '', $name) ?: $name;
        if ($nameCore !== '' && mb_strpos($text, $nameCore) !== false) {
            return 80 + mb_strlen($nameCore);
        }
        $score = 0;
        $len = mb_strlen($nameCore);
        // 2~4 字滑动窗口：用户说「麻辣烫」能命中「招牌麻辣烫套餐」
        for ($n = min(4, $len); $n >= 2; $n--) {
            for ($i = 0; $i <= $len - $n; $i++) {
                $part = mb_substr($nameCore, $i, $n);
                // 过短通用字不参与（「牛肉」OK，「料」太短）
                if (mb_strlen($part) < 2) {
                    continue;
                }
                if ($part !== '' && mb_strpos($text, $part) !== false) {
                    $score = max($score, $n * 10);
                }
            }
        }
        // 过滤过短噪点命中
        $noise = ['一份', '两个', '不要', '可以', '我们', '用餐', '几位', '忌口', '谢谢', '好的', '请问', '推荐'];
        foreach ($noise as $w) {
            if (mb_strpos($name, $w) !== false) {
                $score = max(0, $score - 5);
            }
        }
        return $score >= 20 ? $score : 0;
    }

    protected function defaultSkuUnique(int $productId): string
    {
        $sku = ProductAttrValue::getDB()
            ->where('product_id', $productId)
            ->where('stock', '>', 0)
            ->order('price ASC, unique ASC')
            ->find();
        return $sku ? (string)$sku['unique'] : '';
    }

    protected function formatEnded($session): array
    {
        return [
            'session_no' => (string)$session['session_no'],
            'mer_id' => (int)$session['mer_id'],
            'summary' => (string)$session['summary'],
            'usage_tokens' => (int)$session['usage_tokens'],
            'usage_seconds' => (int)$session['usage_seconds'],
            'fee' => (float)$session['fee'],
            'status' => (int)$session['status'],
        ];
    }

    public function buildMenuLines(int $merId): array
    {
        try {
            $query = Product::getDB()
                ->where('mer_id', $merId)
                ->where('is_del', 0)
                ->where('is_show', 1)
                ->where('status', 1)
                ->field('store_name,price')
                ->order('sort DESC, product_id DESC')
                ->limit(60);
            // 有扫码渠道字段时优先扫码可见商品
            try {
                $query->where('is_scan_order', 1);
            } catch (\Throwable $e) {
            }
            $list = $query->select()->toArray();
            $lines = [];
            foreach ($list as $item) {
                $lines[] = ((string)$item['store_name']) . ' ¥' . $item['price'];
            }
            return $lines;
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function summarizeTranscript(string $transcript): string
    {
        $transcript = trim($transcript);
        if ($transcript === '') {
            return '';
        }
        // 1.0：截取对话要点；后续可接文本模型精炼
        if (mb_strlen($transcript) <= 500) {
            return '顾客沟通摘要：' . $transcript;
        }
        return '顾客沟通摘要：' . mb_substr($transcript, 0, 500) . '…';
    }
}
