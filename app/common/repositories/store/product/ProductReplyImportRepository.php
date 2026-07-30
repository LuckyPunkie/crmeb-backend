<?php

namespace app\common\repositories\store\product;

use app\common\repositories\BaseRepository;
use crmeb\jobs\UpdateProductReplyJob;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Queue;

/**
 * 商品评价批量导入
 * 固定规则：评分全 5 星，规格随机，时间随机取近一个月
 */
class ProductReplyImportRepository extends BaseRepository
{
    public const TEMPLATE_HEADERS = [
        '商品ID*'              => 'product_id',
        '评论内容*'             => 'comment',
        '用户昵称'              => 'nickname',
        '头像URL'              => 'avatar',
        '评论配图URL(多图逗号分隔)' => 'pics',
    ];

    /** 示例评论池 */
    private const COMMENT_POOL = [
        '质量非常好，收到实物和图片描述一致，非常满意，下次还会继续购买！',
        '发货速度很快，包装完好，商品质量不错，物超所值，强烈推荐！',
        '东西很好，和描述的一样，客服态度也很好，快递很快，好评！',
        '收到货了，质量很好，比想象中的还要好，非常满意，值得购买！',
        '商品质量很好，包装精美，发货快，服务态度也很好，好评！',
        '物品收到了，和卖家描述的一样，质量很好，非常满意，五星好评！',
        '收货很快，东西很好，包装完好无损，和图片一样，很满意！',
        '商品很好，质量过关，包装完整，发货速度也很快，值得推荐！',
        '东西很好用，质量不错，和图片上的一样，客服也很热情，好评！',
        '发货快，包装好，产品质量不错，性价比很高，下次还会来购买！',
        '收到货了非常满意，质量很好，包装完好，物流速度也很快，五星推荐！',
        '商品性价比很高，质量很好，和描述一致，快递也很快，满意！',
        '产品很好，做工精细，使用效果很好，客服回复也很及时，好评！',
        '包装很用心，商品质量很好，和图片一样，非常满意，会回购！',
        '质量很棒，实物比图片还要好看，发货速度超快，非常满意！',
        '商品很好，价格实惠，物流很快，包装完好，给五星好评！',
        '收到货很满意，商品质量不错，和图片描述一致，物流速度也很快！',
        '东西很不错，质量好，用着很顺手，服务也很贴心，会继续购买！',
        '产品质量很好，包装严实，快递小哥也很负责，非常满意！',
        '收货比预期快，商品完好无损，质量超出预期，非常推荐！',
    ];

    public function buildTemplateFile(): string
    {
        // 取 4 个在售商品
        $products = Db::name('store_product')
            ->field('product_id,store_name')
            ->where('is_del', 0)
            ->where('status', 1)
            ->order('product_id', 'asc')
            ->limit(4)
            ->select()
            ->toArray();

        // 取有头像的机器人用户（仅保留 http 开头的图片链接，排除 base64/本地路径）
        $botUsers = array_values(array_filter(
            Db::name('user')
                ->field('nickname,avatar')
                ->where('user_type', 'import')
                ->whereNull('cancel_time')
                ->where('status', 1)
                ->where('avatar', '<>', '')
                ->whereRaw("avatar LIKE 'http%'")
                ->limit(100)
                ->select()
                ->toArray(),
            static function ($u) {
                return filter_var(trim($u['avatar']), FILTER_VALIDATE_URL) !== false;
            }
        ));

        // 所有商品（含更多）用于商品对照表
        $allProducts = Db::name('store_product')
            ->field('product_id,store_name')
            ->where('is_del', 0)
            ->where('status', 1)
            ->order('product_id', 'asc')
            ->limit(200)
            ->select()
            ->toArray();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('导入数据');

        // 表头
        $headers = array_keys(self::TEMPLATE_HEADERS);
        foreach ($headers as $i => $h) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue($col . '1', $h);
            $sheet->getColumnDimension($col)->setWidth(min(55, max(14, mb_strlen($h) + 4)));
        }

        // 生成示例数据行：每个商品 5-6 条，不同用户
        $dataRow = 2;
        $commentPool = self::COMMENT_POOL;
        shuffle($commentPool);
        $commentIdx = 0;
        foreach ($products as $product) {
            $rowCount = mt_rand(5, 6);
            // 从机器人用户里挑不重复的
            $usedBots = $botUsers ? array_splice($botUsers, 0, $rowCount) : [];
            for ($n = 0; $n < $rowCount; $n++) {
                $bot = $usedBots[$n] ?? null;
                $nickname = $bot['nickname'] ?? ('买家' . mt_rand(1000, 9999));
                $avatar   = $bot['avatar'] ?? '';
                $comment  = $commentPool[$commentIdx % count($commentPool)];
                $commentIdx++;

                $row = [
                    (string)$product['product_id'],
                    $this->sanitize($comment),
                    $this->sanitize($nickname),
                    $this->sanitize($avatar),
                    '',
                ];
                foreach ($row as $i => $v) {
                    $col = Coordinate::stringFromColumnIndex($i + 1);
                    $sheet->setCellValueExplicit($col . $dataRow, $v, DataType::TYPE_STRING);
                }
                $dataRow++;
            }
        }

        // 填写说明页
        $help = $spreadsheet->createSheet();
        $help->setTitle('填写说明');
        $lines = [
            '1. 带 * 的列为必填；请勿修改第一行表头。',
            '2. 商品ID 请填数字，参考「商品对照表」工作表。',
            '3. 评分自动打满（5星），规格自动随机匹配，评价时间自动随机取近一个月内的时间点。',
            '4. 用户昵称、头像URL 可留空，系统自动从机器人用户中随机取；若无机器人用户则自动生成昵称。',
            '5. 评论配图URL 可填图片直链，多张图片之间用英文逗号分隔，最多6张，也可留空。',
            '6. 示例数据已用真实商品ID和机器人昵称填好，可直接导入，也可自行修改内容。',
        ];
        foreach ($lines as $i => $line) {
            $help->setCellValue('A' . ($i + 1), $line);
        }
        $help->getColumnDimension('A')->setWidth(90);

        // 商品对照表
        $ref = $spreadsheet->createSheet();
        $ref->setTitle('商品对照表');
        $ref->setCellValue('A1', '商品ID');
        $ref->setCellValue('B1', '商品名称');
        foreach ($allProducts as $i => $p) {
            $ref->setCellValue('A' . ($i + 2), (int)$p['product_id']);
            $ref->setCellValue('B' . ($i + 2), $p['store_name']);
        }
        $ref->getColumnDimension('A')->setWidth(12);
        $ref->getColumnDimension('B')->setWidth(50);

        $spreadsheet->setActiveSheetIndex(0);

        $dir = runtime_path() . 'phpExcel' . DIRECTORY_SEPARATOR . date('Ym') . DIRECTORY_SEPARATOR . date('d');
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new ValidateException('无法创建临时目录');
        }
        $full = $dir . DIRECTORY_SEPARATOR . 'product_reply_import_template_' . date('His') . '.xlsx';
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

        $headerRow = array_map(static function ($v) { return trim((string)$v); }, $rows[0]);
        $fieldIndex = [];
        foreach ($headerRow as $idx => $title) {
            if ($title !== '' && isset(self::TEMPLATE_HEADERS[$title])) {
                $fieldIndex[$idx] = self::TEMPLATE_HEADERS[$title];
            }
        }
        $mapped = array_flip($fieldIndex);
        if (!isset($mapped['product_id']) || !isset($mapped['comment'])) {
            throw new ValidateException('无法解析表头，请下载官方模版填写');
        }

        // 预加载机器人用户（昵称+头像备用）
        $botUsers = Db::name('user')
            ->field('nickname,avatar')
            ->where('user_type', 'import')
            ->whereNull('cancel_time')
            ->where('status', 1)
            ->limit(500)
            ->select()
            ->toArray();

        $created = 0;
        $failed = [];
        $touchedProducts = [];

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
                $productId = $this->insertReply($data, $botUsers);
                $created++;
                $touchedProducts[$productId] = true;
            } catch (\Throwable $e) {
                $failed[] = ['row' => $r + 1, 'msg' => $e->getMessage()];
            }
        }

        // 触发评分重算
        foreach (array_keys($touchedProducts) as $productId) {
            Queue::push(UpdateProductReplyJob::class, $productId);
        }

        return compact('created', 'failed');
    }

    /** 写入单条评价，返回 product_id */
    private function insertReply(array $data, array $botUsers): int
    {
        $productId = (int)($data['product_id'] ?? 0);
        $comment   = $data['comment'] ?? '';
        if (!$productId) {
            throw new ValidateException('商品ID不能为空');
        }
        if ($comment === '') {
            throw new ValidateException('评论内容不能为空');
        }

        $product = Db::name('store_product')
            ->field('product_id,mer_id,product_type')
            ->where('product_id', $productId)
            ->where('is_del', 0)
            ->find();
        if (!$product) {
            throw new ValidateException('商品不存在：' . $productId);
        }

        // 随机取一个规格 unique
        $skuList = Db::name('store_product_attr_value')
            ->field('unique')
            ->where('product_id', $productId)
            ->column('unique');
        $unique = $skuList ? $skuList[array_rand($skuList)] : '';

        // 昵称 & 头像：有填用填的，否则从机器人用户随机取
        $nickname = $data['nickname'] ?? '';
        $avatar   = $data['avatar'] ?? '';
        if (($nickname === '' || $avatar === '') && $botUsers) {
            $bot = $botUsers[array_rand($botUsers)];
            if ($nickname === '') $nickname = $bot['nickname'];
            if ($avatar === '')   $avatar   = $bot['avatar'];
        }
        if ($nickname === '') {
            $nickname = '买家' . mt_rand(1000, 9999);
        }

        // 配图：多个 URL 逗号分隔，最多保留 6 张
        $picsRaw = $data['pics'] ?? '';
        $picsList = [];
        if ($picsRaw !== '') {
            $picsList = array_filter(array_map('trim', explode(',', $picsRaw)));
            $picsList = array_slice(array_values($picsList), 0, 6);
        }

        // 近一个月内随机时间
        $now        = time();
        $createTime = date('Y-m-d H:i:s', mt_rand($now - 30 * 86400, $now));

        Db::name('store_product_reply')->insert([
            'uid'                    => 0,
            'mer_id'                 => (int)$product['mer_id'],
            'order_product_id'       => 0,
            'unique'                 => $unique,
            'product_id'             => $productId,
            'product_type'           => (int)($product['product_type'] ?? 0),
            'product_score'          => 5,
            'service_score'          => 5,
            'postage_score'          => 5,
            'rate'                   => 5,
            'comment'                => $comment,
            'pics'                   => implode(',', $picsList),
            'nickname'               => mb_substr($nickname, 0, 32),
            'avatar'                 => $avatar,
            'is_virtual'             => 1,
            'is_del'                 => 0,
            'is_reply'               => 0,
            'sort'                   => 0,
            'merchant_reply_content' => '',
            'merchant_reply_time'    => null,
            'create_time'            => $createTime,
        ]);

        return $productId;
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

    /** 去掉 XML 1.0 非法字符，防止 PhpSpreadsheet 生成损坏的 xlsx */
    private function sanitize(string $v): string
    {
        // 先确保是合法 UTF-8
        $v = mb_convert_encoding($v, 'UTF-8', 'UTF-8');
        // 去除 XML 1.0 禁止的控制字符（保留 \t=0x09 \n=0x0A \r=0x0D）
        $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string)$v);
        // 去除 UTF-16 代理对（U+D800–U+DFFF），在 XML 中非法
        $v = preg_replace('/[\xED\xA0\x80-\xED\xBF\xBF]/u', '', (string)$v);
        // 统一换行为空格
        $v = str_replace(["\r\n", "\r", "\n"], ' ', (string)$v);
        return trim($v);
    }
}
