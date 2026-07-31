<?php

namespace app\common\repositories\community;

use app\common\repositories\BaseRepository;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 图文帖子批量导入
 * 固定规则：图文类型、审核通过并显示；作者/评论者从机器人用户库取，评论内容随机对应机器人
 */
class CommunityImportRepository extends BaseRepository
{
    public const TEMPLATE_HEADERS = [
        '正文内容*'                    => 'content',
        '图片URL(多图逗号分隔)*'         => 'image',
        '标题'                         => 'title',
        '话题名称(多个逗号分隔)'          => 'topic_names',
        '作者账号(可空随机机器人)'        => 'author_account',
        '评论内容(多条用|分隔可空用内置池)' => 'comments',
        '评论条数(评论为空时生效默认3)'    => 'comment_count',
    ];

    /** 示例正文池 */
    private const CONTENT_POOL = [
        "今天天气真好，带毛孩子出去晒太阳了！\n阳光下它特别乖，拍了几张照片分享给大家～",
        "周末探店打卡，这家咖啡店环境超棒！\n店里的小食也很推荐，下次还会再来。",
        "新入手的好物开箱，质感超出预期！\n细节做得很用心，已经种草给身边朋友了。",
        "下班后的小确幸，就是一杯热饮配晚霞。\n生活需要仪式感，记录这一刻。",
        "运动打卡 Day 12，坚持就是胜利！\n今天状态不错，继续加油～",
    ];

    /** 示例评论池 */
    private const COMMENT_POOL = [
        '写得真好，拍的也很漂亮！',
        '羡慕了，下次也要去打卡～',
        '太有生活气息了，点赞！',
        '看起来好舒服啊，求地点！',
        '同感，周末就该这样放松。',
        '拍得很有感觉，收藏了！',
        '哈哈哈说得太对了',
        '支持一下，继续更新哦',
        '看完心情都变好了',
        '好物推荐靠谱，感谢分享',
        '种草了种草了！',
        '内容很真实，喜欢这种分享',
        '学习了，下次试试看',
        '颜值很高啊，赞一个',
        '评论区排队打卡中～',
    ];

    public function buildTemplateFile(): string
    {
        $bots = $this->loadBotUsers(80);
        $topics = Db::name('community_topic')
            ->where('status', 1)
            ->where('is_del', 0)
            ->field('topic_id,topic_name')
            ->order('topic_id', 'asc')
            ->limit(50)
            ->select()
            ->toArray();

        // 尝试找几张可用图（用户头像 http）作为示例图
        $sampleImages = [];
        foreach ($bots as $b) {
            $av = trim((string)($b['avatar'] ?? ''));
            if ($av !== '' && filter_var($av, FILTER_VALIDATE_URL)) {
                $sampleImages[] = $av;
            }
            if (count($sampleImages) >= 6) {
                break;
            }
        }
        if (!$sampleImages) {
            $sampleImages = ['https://via.placeholder.com/400x400.png?text=demo'];
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('导入数据');

        $headers = array_keys(self::TEMPLATE_HEADERS);
        foreach ($headers as $i => $h) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue($col . '1', $h);
            $sheet->getColumnDimension($col)->setWidth(min(42, max(16, mb_strlen($h) + 4)));
        }

        $commentPool = self::COMMENT_POOL;
        shuffle($commentPool);
        $contentPool = self::CONTENT_POOL;
        $topicName = $topics[0]['topic_name'] ?? '';

        for ($r = 0; $r < 3; $r++) {
            $author = $bots[$r % max(1, count($bots))] ?? null;
            $imgs = array_slice($sampleImages, $r % max(1, count($sampleImages)), 2);
            if (count($imgs) < 1) {
                $imgs = [$sampleImages[0]];
            }
            $comments = array_slice($commentPool, $r * 3, 3);
            $row = [
                $this->sanitize($contentPool[$r % count($contentPool)]),
                $this->sanitize(implode(',', $imgs)),
                '',
                $this->sanitize($topicName),
                $this->sanitize((string)($author['account'] ?? '')),
                $this->sanitize(implode('|', $comments)),
                '3',
            ];
            foreach ($row as $i => $v) {
                $col = Coordinate::stringFromColumnIndex($i + 1);
                $sheet->setCellValueExplicit($col . ($r + 2), $v, DataType::TYPE_STRING);
            }
        }

        $help = $spreadsheet->createSheet();
        $help->setTitle('填写说明');
        $lines = [
            '1. 带 * 的列为必填；请勿修改第一行表头。',
            '2. 仅支持导入图文帖子（非短视频），导入后默认审核通过并显示。',
            '3. 图片请填可访问的图片直链，多张用英文逗号分隔，合计长度勿超过 1000 字符。',
            '4. 标题可留空：默认取正文第一行，超长自动截断。',
            '5. 话题名称可填多个（逗号分隔），不存在会自动创建；也可留空。',
            '6. 作者账号可留空：系统从机器人用户库（user_type=import）随机取一位作为作者。',
            '7. 评论内容多条用英文竖线 | 分隔；留空则按「评论条数」从内置评论池随机生成。',
            '8. 每条评论会随机绑定一位机器人用户（尽量不与作者重复）。',
            '9. 正文最长 1000 字，单条评论最长 255 字；发布时间随机取近一个月内。',
            '10. 请先在「用户列表」导入足够数量的机器人用户，再导入帖子。',
        ];
        foreach ($lines as $i => $line) {
            $help->setCellValue('A' . ($i + 1), $line);
        }
        $help->getColumnDimension('A')->setWidth(95);

        $ref = $spreadsheet->createSheet();
        $ref->setTitle('话题对照表');
        $ref->setCellValue('A1', '话题ID');
        $ref->setCellValue('B1', '话题名称');
        foreach ($topics as $i => $t) {
            $ref->setCellValue('A' . ($i + 2), (int)$t['topic_id']);
            $ref->setCellValue('B' . ($i + 2), $this->sanitize((string)$t['topic_name']));
        }
        $ref->getColumnDimension('A')->setWidth(12);
        $ref->getColumnDimension('B')->setWidth(30);

        $botSheet = $spreadsheet->createSheet();
        $botSheet->setTitle('机器人对照表');
        $botSheet->setCellValue('A1', 'UID');
        $botSheet->setCellValue('B1', '账号');
        $botSheet->setCellValue('C1', '昵称');
        $botSheet->setCellValue('D1', 'bot_type(1用户2创作)');
        foreach ($bots as $i => $b) {
            $botSheet->setCellValue('A' . ($i + 2), (int)$b['uid']);
            $botSheet->setCellValueExplicit('B' . ($i + 2), (string)$b['account'], DataType::TYPE_STRING);
            $botSheet->setCellValue('C' . ($i + 2), $this->sanitize((string)$b['nickname']));
            $botSheet->setCellValue('D' . ($i + 2), (int)($b['bot_type'] ?? 0));
        }
        $botSheet->getColumnDimension('A')->setWidth(10);
        $botSheet->getColumnDimension('B')->setWidth(22);
        $botSheet->getColumnDimension('C')->setWidth(18);
        $botSheet->getColumnDimension('D')->setWidth(20);

        $spreadsheet->setActiveSheetIndex(0);

        $dir = runtime_path() . 'phpExcel' . DIRECTORY_SEPARATOR . date('Ym') . DIRECTORY_SEPARATOR . date('d');
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new ValidateException('无法创建临时目录');
        }
        $full = $dir . DIRECTORY_SEPARATOR . 'community_import_template_' . date('His') . '.xlsx';
        (new Xlsx($spreadsheet))->save($full);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        if (!is_file($full) || filesize($full) < 100) {
            throw new ValidateException('模版生成失败');
        }
        return $full;
    }

    public function import(string $filePath): array
    {
        if (!is_file($filePath)) {
            throw new ValidateException('上传文件不存在');
        }
        $spreadsheet = IOFactory::load($filePath);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        if (count($rows) < 2) {
            throw new ValidateException('Excel 无数据行');
        }

        $headerRow = array_map(static function ($v) {
            return trim((string)$v);
        }, $rows[0]);
        $fieldIndex = [];
        foreach ($headerRow as $idx => $title) {
            if ($title !== '' && isset(self::TEMPLATE_HEADERS[$title])) {
                $fieldIndex[$idx] = self::TEMPLATE_HEADERS[$title];
            }
        }
        $mapped = array_flip($fieldIndex);
        if (!isset($mapped['content']) || !isset($mapped['image'])) {
            throw new ValidateException('无法解析表头，请下载官方模版填写');
        }

        $bots = $this->loadBotUsers(500);
        if (!$bots) {
            throw new ValidateException('暂无可用机器人用户，请先导入机器人');
        }

        $created = 0;
        $replyCreated = 0;
        $failed = [];

        for ($r = 1; $r < count($rows); $r++) {
            $row = $rows[$r];
            if ($this->isEmptyRow($row)) {
                continue;
            }
            $data = [];
            foreach ($fieldIndex as $idx => $field) {
                $data[$field] = isset($row[$idx]) ? trim((string)$row[$idx]) : '';
            }
            try {
                $result = $this->insertPost($data, $bots);
                $created++;
                $replyCreated += $result['reply_count'];
            } catch (\Throwable $e) {
                $failed[] = ['row' => $r + 1, 'msg' => $e->getMessage()];
            }
        }

        return compact('created', 'replyCreated', 'failed');
    }

    /**
     * @return array{community_id:int,reply_count:int}
     */
    private function insertPost(array $data, array $bots): array
    {
        $content = $this->sanitize($data['content'] ?? '');
        $imageRaw = strip_image_watermark_url($this->sanitize($data['image'] ?? ''));
        if ($content === '') {
            throw new ValidateException('正文内容不能为空');
        }
        if ($imageRaw === '') {
            throw new ValidateException('图片URL不能为空');
        }
        $content = mb_substr($content, 0, 1000);

        $images = array_values(array_filter(array_map('trim', explode(',', $imageRaw))));
        if (!$images) {
            throw new ValidateException('图片URL无效');
        }
        $imageStr = '';
        foreach ($images as $img) {
            $img = strip_image_watermark_url($img);
            if ($img === '') {
                continue;
            }
            $next = $imageStr === '' ? $img : ($imageStr . ',' . $img);
            if (mb_strlen($next) > 1000) {
                break;
            }
            $imageStr = $next;
        }
        if ($imageStr === '') {
            throw new ValidateException('图片URL过长或无效');
        }

        $title = $this->sanitize($data['title'] ?? '');
        if ($title === '') {
            $firstLine = preg_split('/\r\n|\r|\n/', $content)[0] ?? $content;
            $title = trim($firstLine);
        }
        if (mb_strlen($title) > 40) {
            $title = mb_substr($title, 0, 30);
        }
        if ($title === '') {
            $title = '图文分享';
        }

        $author = $this->resolveAuthor($data['author_account'] ?? '', $bots);
        $createTime = date('Y-m-d H:i:s', mt_rand(time() - 30 * 86400, time()));

        $topicRepo = app()->make(CommunityTopicRepository::class);
        $topicPayload = ['content' => $content];
        $topicNames = $this->sanitize($data['topic_names'] ?? '');
        if ($topicNames !== '') {
            $topicPayload['topic_names'] = $topicNames;
        }
        $topics = $topicRepo->resolveTopicsFromPayload($topicPayload);
        $topicId = 0;
        $categoryId = 0;
        $topicIds = [];
        foreach ($topics as $topic) {
            $topicIds[] = (int)$topic['topic_id'];
        }
        if ($topics) {
            $first = $topics[0];
            $topicId = (int)$first['topic_id'];
            $categoryId = (int)($first['category_id'] ?? 0);
        }

        $communityId = (int)Db::transaction(function () use (
            $author, $title, $content, $imageStr, $createTime, $topicId, $categoryId, $topicIds, $topicRepo
        ) {
            $id = (int)Db::name('community')->insertGetId([
                'uid'            => (int)$author['uid'],
                'title'          => $title,
                'content'        => $content,
                'image'          => $imageStr,
                'topic_id'       => $topicId,
                'category_id'    => $categoryId,
                'is_type'        => (int)CommunityRepository::COMMUNIT_TYPE_FONT,
                'community_type' => 0,
                'status'         => 1,
                'is_show'        => 1,
                'status_time'    => $createTime,
                'create_time'    => $createTime,
                'is_del'         => 0,
                'start'          => 1,
                'mer_id'         => 0,
                'video_link'     => '',
                'order_id'       => 0,
                'count_start'    => 0,
                'count_reply'    => 0,
                'count_share'    => 0,
                'pv'             => mt_rand(10, 200),
                'is_hot'         => 0,
                'refusal'        => '',
            ]);
            $topicRepo->syncCommunityTopics($id, $topicIds);
            app()->make(\app\common\repositories\user\UserRepository::class)
                ->incField((int)$author['uid'], 'count_content');
            return $id;
        });

        $comments = $this->resolveComments($data);
        $replyCount = 0;
        $usedCommentUids = [(int)$author['uid']];
        foreach ($comments as $i => $comment) {
            $comment = mb_substr($this->sanitize($comment), 0, 255);
            if ($comment === '') {
                continue;
            }
            $bot = $this->pickBot($bots, $usedCommentUids);
            $usedCommentUids[] = (int)$bot['uid'];
            $replyTime = date('Y-m-d H:i:s', min(time(), strtotime($createTime) + 60 * ($i + 1) + mt_rand(0, 3600)));
            Db::name('community_reply')->insert([
                'parent_id'    => 0,
                'content'      => $comment,
                'pid'          => 0,
                'uid'          => (int)$bot['uid'],
                're_uid'       => 0,
                'count_start'  => 0,
                'count_reply'  => 0,
                'status'       => 1,
                'community_id' => $communityId,
                'create_time'  => $replyTime,
                'is_del'       => 0,
                'refusal'      => '',
            ]);
            $replyCount++;
        }
        if ($replyCount > 0) {
            Db::name('community')->where('community_id', $communityId)->update([
                'count_reply' => $replyCount,
            ]);
        }

        return ['community_id' => $communityId, 'reply_count' => $replyCount];
    }

    private function resolveAuthor(string $account, array $bots): array
    {
        $account = trim($account);
        if ($account !== '') {
            foreach ($bots as $b) {
                if ((string)$b['account'] === $account || (string)$b['uid'] === $account) {
                    return $b;
                }
            }
            $user = Db::name('user')
                ->where('account', $account)
                ->whereNull('cancel_time')
                ->where('status', 1)
                ->field('uid,account,nickname,avatar,bot_type,user_type')
                ->find();
            if (!$user && ctype_digit($account)) {
                $user = Db::name('user')
                    ->where('uid', (int)$account)
                    ->whereNull('cancel_time')
                    ->where('status', 1)
                    ->field('uid,account,nickname,avatar,bot_type,user_type')
                    ->find();
            }
            if ($user) {
                return $user;
            }
            throw new ValidateException('作者账号不存在：' . $account);
        }
        // 优先创作机器人
        $creators = array_values(array_filter($bots, static function ($b) {
            return (int)($b['bot_type'] ?? 0) === 2;
        }));
        $pool = $creators ?: $bots;
        return $pool[array_rand($pool)];
    }

    /** @return string[] */
    private function resolveComments(array $data): array
    {
        $raw = trim((string)($data['comments'] ?? ''));
        if ($raw !== '') {
            $list = array_values(array_filter(array_map('trim', explode('|', $raw)), static function ($v) {
                return $v !== '';
            }));
            return array_slice($list, 0, 20);
        }
        $n = (int)($data['comment_count'] ?? 3);
        if ($n <= 0) {
            return [];
        }
        $n = min(20, max(1, $n));
        $pool = self::COMMENT_POOL;
        shuffle($pool);
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $out[] = $pool[$i % count($pool)];
        }
        return $out;
    }

    private function pickBot(array $bots, array $excludeUids): array
    {
        $exclude = array_flip(array_map('intval', $excludeUids));
        $candidates = array_values(array_filter($bots, static function ($b) use ($exclude) {
            return !isset($exclude[(int)$b['uid']]);
        }));
        if (!$candidates) {
            $candidates = $bots;
        }
        // 评论优先用户机器人
        $users = array_values(array_filter($candidates, static function ($b) {
            return (int)($b['bot_type'] ?? 0) === 1;
        }));
        $pool = $users ?: $candidates;
        return $pool[array_rand($pool)];
    }

    private function loadBotUsers(int $limit): array
    {
        return Db::name('user')
            ->field('uid,account,nickname,avatar,bot_type,user_type')
            ->where('user_type', 'import')
            ->whereNull('cancel_time')
            ->where('status', 1)
            ->order('uid', 'asc')
            ->limit($limit)
            ->select()
            ->toArray();
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string)$cell) !== '') {
                return false;
            }
        }
        return true;
    }

    private function sanitize(string $v): string
    {
        $v = mb_convert_encoding($v, 'UTF-8', 'UTF-8');
        $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string)$v);
        $v = preg_replace('/[\xED\xA0\x80-\xED\xBF\xBF]/u', '', (string)$v);
        return trim((string)$v);
    }
}
