<?php

namespace app\common\repositories\system\merchant;

use app\common\repositories\BaseRepository;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 商家批量导入
 */
class MerchantImportRepository extends BaseRepository
{
    public const SOURCE_ADMIN = 1;     // 后台添加
    public const SOURCE_USER = 2;      // 用户注册
    public const SOURCE_IMPORT = 3;    // 批量导入

    public const TEMPLATE_HEADERS = [
        '商户名称*' => 'mer_name',
        '联系人*' => 'real_name',
        '联系电话*' => 'mer_phone',
        '商户地址' => 'mer_address',
        '店铺分类ID*(见对照表)' => 'category_id',
        '店铺类型ID*(见对照表)' => 'type_id',
        '店铺账号*' => 'mer_account',
        '登录密码*' => 'mer_password',
        '商户头像URL' => 'mer_avatar',
        '商户简介' => 'mer_info',
        '客服电话' => 'service_phone',
        '关键词' => 'mer_keyword',
        '是否自营(0否1是)' => 'is_trader',
        '排序' => 'sort',
        '备注' => 'mark',
    ];

    /**
     * 生成商家导入模版，返回绝对路径
     */
    public function buildTemplateFile(): string
    {
        $categories = Db::name('merchant_category')
            ->field('merchant_category_id,category_name')
            ->order('merchant_category_id', 'asc')
            ->select()
            ->toArray();
        $types = Db::name('merchant_type')
            ->field('mer_type_id,type_name')
            ->order('mer_type_id', 'asc')
            ->select()
            ->toArray();

        $catHint = [];
        foreach ($categories as $c) {
            $catHint[] = ((int)$c['merchant_category_id']) . '=' . $c['category_name'];
        }
        $typeHint = [];
        foreach ($types as $t) {
            $typeHint[] = ((int)$t['mer_type_id']) . '=' . $t['type_name'];
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('导入数据');
        $headers = array_keys(self::TEMPLATE_HEADERS);
        foreach ($headers as $i => $h) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue($col . '1', $h);
            $sheet->getColumnDimension($col)->setWidth(min(32, max(12, mb_strlen($h) + 2)));
        }
        $firstCat = $categories[0]['merchant_category_id'] ?? 1;
        $firstType = $types[0]['mer_type_id'] ?? 1;
        $example = [
            '示例店铺', '张三', '13800138000', '北京市朝阳区', (string)$firstCat, (string)$firstType,
            'shop_demo01', '123456', '', '店铺简介', '13800138000', '', '0', '0', '',
        ];
        foreach ($example as $i => $v) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValueExplicit($col . '2', $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        }

        // 填写说明
        $help = $spreadsheet->createSheet();
        $help->setTitle('填写说明');
        $helpLines = [
            '1. 带 * 的列为必填；请勿修改第一行表头。',
            '2. 「店铺账号 / 登录密码」= 后台添加店铺「账号信息」里的店铺登录账号，用于登录商家后台。',
            '3. 店铺分类ID、店铺类型ID请填数字，对照见下方和下个工作表「对照表」。',
            '4. 店铺分类：' . ($catHint ? implode('  ', $catHint) : '（后台暂无分类，请先添加）'),
            '5. 店铺类型：' . ($typeHint ? implode('  ', $typeHint) : '（后台暂无类型，请先添加）'),
            '6. 同名商户已存在时将覆盖更新基础信息。',
            '7. 第2行为示例，导入前请删除或改成真实数据。',
        ];
        foreach ($helpLines as $i => $line) {
            $help->setCellValue('A' . ($i + 1), $line);
        }
        $help->getColumnDimension('A')->setWidth(100);

        // 对照表（动态读取后台配置）
        $map = $spreadsheet->createSheet();
        $map->setTitle('对照表');
        $map->setCellValue('A1', '店铺分类ID');
        $map->setCellValue('B1', '分类名称');
        $map->setCellValue('D1', '店铺类型ID');
        $map->setCellValue('E1', '类型名称');
        foreach ($categories as $i => $c) {
            $map->setCellValue('A' . ($i + 2), (int)$c['merchant_category_id']);
            $map->setCellValue('B' . ($i + 2), $c['category_name']);
        }
        foreach ($types as $i => $t) {
            $map->setCellValue('D' . ($i + 2), (int)$t['mer_type_id']);
            $map->setCellValue('E' . ($i + 2), $t['type_name']);
        }
        $map->getColumnDimension('A')->setWidth(14);
        $map->getColumnDimension('B')->setWidth(20);
        $map->getColumnDimension('D')->setWidth(14);
        $map->getColumnDimension('E')->setWidth(20);

        $spreadsheet->setActiveSheetIndex(0);

        $dir = runtime_path() . 'phpExcel' . DIRECTORY_SEPARATOR . date('Ym') . DIRECTORY_SEPARATOR . date('d');
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new ValidateException('无法创建临时目录');
        }
        $full = $dir . DIRECTORY_SEPARATOR . 'merchant_import_template_' . date('His') . '.xlsx';
        (new Xlsx($spreadsheet))->save($full);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        if (!is_file($full) || filesize($full) < 100) {
            throw new ValidateException('模版生成失败');
        }
        return $full;
    }

    public function downloadTemplate(): void
    {
        $path = $this->buildTemplateFile();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="merchant_import_template.xlsx"');
        header('Cache-Control: max-age=0');
        readfile($path);
        exit;
    }

    public function import(string $filePath, array $adminInfo = []): array
    {
        if (!is_file($filePath)) {
            throw new ValidateException('上传文件不存在');
        }
        $spreadsheet = IOFactory::load($filePath);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        if (count($rows) < 2) {
            throw new ValidateException('Excel 无数据行');
        }

        $headerRow = array_map(static fn($v) => trim((string)$v), $rows[0]);
        // 兼容旧表头别名
        $aliases = [
            '店铺分类ID*' => 'category_id',
            '店铺类型ID*' => 'type_id',
            '管理员账号*' => 'mer_account',
            '管理员密码*' => 'mer_password',
        ];
        $fieldIndex = [];
        foreach ($headerRow as $idx => $title) {
            if ($title === '') {
                continue;
            }
            if (isset(self::TEMPLATE_HEADERS[$title])) {
                $fieldIndex[$idx] = self::TEMPLATE_HEADERS[$title];
            } elseif (isset($aliases[$title])) {
                $fieldIndex[$idx] = $aliases[$title];
            }
        }
        if (!$fieldIndex) {
            throw new ValidateException('无法解析表头，请下载官方模版填写');
        }

        $catNameMap = Db::name('merchant_category')->column('merchant_category_id', 'category_name');
        $typeNameMap = Db::name('merchant_type')->column('mer_type_id', 'type_name');

        $merchantRepo = app()->make(MerchantRepository::class);
        $created = 0;
        $updated = 0;
        $failed = [];

        for ($r = 1; $r < count($rows); $r++) {
            $row = $rows[$r];
            $empty = true;
            foreach ($row as $cell) {
                if (trim((string)$cell) !== '') {
                    $empty = false;
                    break;
                }
            }
            if ($empty) {
                continue;
            }

            $data = [];
            foreach ($fieldIndex as $idx => $field) {
                $data[$field] = isset($row[$idx]) ? trim((string)$row[$idx]) : '';
            }

            try {
                $data['category_id'] = $this->resolveCategoryId($data['category_id'] ?? '', $catNameMap);
                $data['type_id'] = $this->resolveTypeId($data['type_id'] ?? '', $typeNameMap);
                $result = $this->upsertMerchant($merchantRepo, $data, $adminInfo);
                if ($result === 'created') {
                    $created++;
                } else {
                    $updated++;
                }
            } catch (\Throwable $e) {
                $failed[] = ['row' => $r + 1, 'msg' => $e->getMessage()];
            }
        }

        return compact('created', 'updated', 'failed');
    }

    private function resolveCategoryId(string $raw, array $nameMap): int
    {
        $raw = trim($raw);
        if ($raw === '') {
            throw new ValidateException('店铺分类ID不能为空');
        }
        if (ctype_digit($raw)) {
            return (int)$raw;
        }
        if (isset($nameMap[$raw])) {
            return (int)$nameMap[$raw];
        }
        throw new ValidateException('店铺分类无效：' . $raw . '（请填对照表中的 ID）');
    }

    private function resolveTypeId(string $raw, array $nameMap): int
    {
        $raw = trim($raw);
        if ($raw === '') {
            throw new ValidateException('店铺类型ID不能为空');
        }
        if (ctype_digit($raw)) {
            return (int)$raw;
        }
        if (isset($nameMap[$raw])) {
            return (int)$nameMap[$raw];
        }
        throw new ValidateException('店铺类型无效：' . $raw . '（请填对照表中的 ID）');
    }

    private function upsertMerchant(MerchantRepository $merchantRepo, array $data, array $adminInfo): string
    {
        foreach (['mer_name', 'real_name', 'mer_phone', 'category_id', 'type_id', 'mer_account', 'mer_password'] as $req) {
            if ($data[$req] === '' || $data[$req] === null) {
                throw new ValidateException("缺少必填字段: {$req}");
            }
        }

        $merAvatar = strip_image_watermark_url((string)($data['mer_avatar'] ?? ''));

        $exist = Db::name('merchant')->where('mer_name', $data['mer_name'])->where('is_del', 0)->find();
        if ($exist) {
            // 覆盖更新基础信息（不重置管理员密码账号冲突时跳过密码）
            $update = [
                'real_name' => $data['real_name'],
                'mer_phone' => $data['mer_phone'],
                'mer_address' => (string)($data['mer_address'] ?? ''),
                'category_id' => (int)$data['category_id'],
                'type_id' => (int)$data['type_id'],
                'mer_avatar' => $merAvatar,
                'mer_info' => (string)($data['mer_info'] ?? ''),
                'service_phone' => (string)($data['service_phone'] ?? ''),
                'mer_keyword' => (string)($data['mer_keyword'] ?? ''),
                'is_trader' => (int)($data['is_trader'] ?? 0),
                'sort' => (int)($data['sort'] ?? 0),
                'mark' => (string)($data['mark'] ?? ''),
                'create_source' => self::SOURCE_IMPORT,
            ];
            Db::name('merchant')->where('mer_id', $exist['mer_id'])->update($update);
            return 'updated';
        }

        $payload = [
            'mer_name' => $data['mer_name'],
            'real_name' => $data['real_name'],
            'mer_phone' => $data['mer_phone'],
            'mer_address' => (string)($data['mer_address'] ?? ''),
            'category_id' => (int)$data['category_id'],
            'type_id' => (int)$data['type_id'],
            'mer_account' => $data['mer_account'],
            'mer_password' => $data['mer_password'],
            'mer_avatar' => $merAvatar,
            'mer_info' => (string)($data['mer_info'] ?? ''),
            'service_phone' => (string)($data['service_phone'] ?? $data['mer_phone']),
            'mer_keyword' => (string)($data['mer_keyword'] ?? ''),
            'is_trader' => (int)($data['is_trader'] ?? 0),
            'sort' => (int)($data['sort'] ?? 0),
            'mark' => (string)($data['mark'] ?? ''),
            'status' => 1,
            'mer_state' => 1,
            'create_source' => self::SOURCE_IMPORT,
            'admin_info' => $adminInfo,
        ];

        $merchant = $merchantRepo->createMerchant($payload);
        // createMerchant 可能未写入 create_source，强制补写
        if ($merchant && isset($merchant->mer_id)) {
            Db::name('merchant')->where('mer_id', $merchant->mer_id)->update([
                'create_source' => self::SOURCE_IMPORT,
            ]);
        }
        return 'created';
    }
}
