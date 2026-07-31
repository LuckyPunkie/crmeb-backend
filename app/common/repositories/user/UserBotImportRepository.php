<?php

namespace app\common\repositories\user;

use app\common\repositories\BaseRepository;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 机器人用户批量导入
 */
class UserBotImportRepository extends BaseRepository
{
    public const BOT_TYPE_USER = 1;      // 用户机器人
    public const BOT_TYPE_CREATOR = 2;   // 创作机器人

    /** Excel 表头 => 字段映射（枚举请填数字，表头已标注含义；机器人类型由上传按钮决定） */
    public const TEMPLATE_HEADERS = [
        '账号(可选,留空自动生成)' => 'account',
        '昵称' => 'nickname',
        '头像URL' => 'avatar',
        '真实姓名' => 'real_name',
        '性别(0未知 1男 2女)' => 'sex',
        '生日(YYYY-MM-DD)' => 'birthday',
        '身高cm' => 'height',
        '体重kg' => 'weight',
        '出生年月(YYYY-MM)' => 'birth_month',
        '星座(1白羊 2金牛 3双子 4巨蟹 5狮子 6处女 7天秤 8天蝎 9射手 10摩羯 11水瓶 12双鱼)' => 'zodiac',
        '学历(1高中 2大专 3本科 4硕士 5博士 6中专 7小学 8初中)' => 'education',
        '学历类型(1全日制 2非全日制)' => 'education_type',
        '工作岗位' => 'job_title',
        '家乡省' => 'hometown_province',
        '家乡市' => 'hometown_city',
        '现居省' => 'current_province',
        '现居市' => 'current_city',
        '年收入(1=10万以下 2=10-20万 3=20-50万 4=50万以上)' => 'annual_income',
        '车辆数' => 'car_count',
        '房产数' => 'house_count',
        '总资产(1=100万以下 2=100-300万 3=300万以上)' => 'total_assets',
        '感情状态(1单身 2恋爱中 3已婚 4已育 5离异 6丧偶)' => 'relationship_status',
        '交友目的(1找对象 2普通交友 3不确定)' => 'dating_purpose',
        '备注' => 'mark',
    ];

    /** 兼容旧模版中文；新模版请填数字 */
    private const ENUM_MAPS = [
        'sex' => [
            '未知' => 0, '保密' => 0, '0' => 0,
            '男' => 1, '1' => 1,
            '女' => 2, '2' => 2,
        ],
        'zodiac' => [
            '白羊座' => 1, '金牛座' => 2, '双子座' => 3, '巨蟹座' => 4,
            '狮子座' => 5, '处女座' => 6, '天秤座' => 7, '天蝎座' => 8,
            '射手座' => 9, '摩羯座' => 10, '水瓶座' => 11, '双鱼座' => 12,
            '1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6,
            '7' => 7, '8' => 8, '9' => 9, '10' => 10, '11' => 11, '12' => 12,
        ],
        'education' => [
            '高中' => 1, '大专' => 2, '本科' => 3, '硕士' => 4,
            '博士' => 5, '博士及以上' => 5, '中专' => 6, '小学' => 7, '初中' => 8,
            '1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6, '7' => 7, '8' => 8,
        ],
        'education_type' => [
            '全日制' => 1, '非全日制' => 2, '1' => 1, '2' => 2,
        ],
        'annual_income' => [
            '10万以下' => 1, '年入10万以下' => 1, '<10万' => 1,
            '10-20万' => 2, '年入10-20万' => 2,
            '20-50万' => 3, '年入20-50万' => 3,
            '50万以上' => 4, '年入50万以上' => 4, '>50万' => 4,
            '1' => 1, '2' => 2, '3' => 3, '4' => 4,
        ],
        'total_assets' => [
            '100万以下' => 1, '100-300万' => 2, '300万以上' => 3,
            '1' => 1, '2' => 2, '3' => 3,
        ],
        'relationship_status' => [
            '单身' => 1, '恋爱中' => 2, '恋爱' => 2, '已婚' => 3,
            '已育' => 4, '离异' => 5, '丧偶' => 6,
            '1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6,
        ],
        'dating_purpose' => [
            '找对象' => 1, '普通交友' => 2, '不确定' => 3,
            '1' => 1, '2' => 2, '3' => 3,
        ],
    ];

    /**
     * 生成导入模版文件，返回绝对路径（两类机器人共用同一模版；类型由上传按钮决定）
     */
    public function buildTemplateFile(): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('导入数据');

        $headers = array_keys(self::TEMPLATE_HEADERS);
        foreach ($headers as $i => $h) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue($col . '1', $h);
            $sheet->getColumnDimension($col)->setWidth(min(36, max(14, mb_strlen($h) + 2)));
        }

        // 示例行：枚举一律填数字（无机器人类型列）
        $example = [
            '', '示例昵称', 'https://example.com/avatar.jpg', '', 1, '1995-01-15',
            172, 65, '1995-01', 8, 3, 1, '工程师',
            '湖北', '襄阳', '北京', '北京', 2, 0, 0, 1, 1, 1, '',
        ];
        foreach ($example as $i => $v) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValueExplicit($col . '2', $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        }
        $example2 = [
            '', '资讯小编示例', 'https://example.com/avatar2.jpg', '', 2, '1992-06-01',
            165, 50, '1992-06', 3, 3, 1, '编辑',
            '浙江', '杭州', '上海', '上海', 2, 0, 0, 1, 1, 2, '',
        ];
        foreach ($example2 as $i => $v) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValueExplicit($col . '3', $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        }

        // 说明页：数值对照表
        $help = $spreadsheet->createSheet();
        $help->setTitle('填写说明');
        $helpLines = [
            '【重要】带选项的列请直接填数字，含义写在表头括号里；请勿改第一行表头。',
            '第2、3行为示例，导入前请删除或改成真实数据。',
            '机器人类型不在表里填：点「导入用户机器人」→整批用户机器人；点「导入创作机器人」→整批创作机器人。',
            '',
            '性别：0=未知  1=男  2=女',
            '星座：1白羊 2金牛 3双子 4巨蟹 5狮子 6处女 7天秤 8天蝎 9射手 10摩羯 11水瓶 12双鱼',
            '学历：1高中 2大专 3本科 4硕士 5博士 6中专 7小学 8初中',
            '学历类型：1=全日制  2=非全日制',
            '年收入：1=10万以下  2=10-20万  3=20-50万  4=50万以上',
            '总资产：1=100万以下  2=100-300万  3=300万以上',
            '感情状态：1单身  2恋爱中  3已婚  4已育  5离异  6丧偶',
            '交友目的：1找对象  2普通交友  3不确定',
            '',
            '账号列可留空，系统自动生成 bot_ 开头账号（非手机号，不可登录）。',
            '填写已有账号则覆盖更新。头像请填可访问的图片 URL。',
        ];
        foreach ($helpLines as $i => $line) {
            $help->setCellValue('A' . ($i + 1), $line);
        }
        $help->getColumnDimension('A')->setWidth(100);

        $spreadsheet->setActiveSheetIndex(0);

        $dir = runtime_path() . 'phpExcel' . DIRECTORY_SEPARATOR . date('Ym') . DIRECTORY_SEPARATOR . date('d');
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new ValidateException('无法创建临时目录');
        }
        $filename = 'bot_user_import_template_' . date('His') . '.xlsx';
        $full = $dir . DIRECTORY_SEPARATOR . $filename;
        (new Xlsx($spreadsheet))->save($full);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        if (!is_file($full) || filesize($full) < 100) {
            throw new ValidateException('模版生成失败');
        }
        return $full;
    }

    /** @deprecated 请用 buildTemplateFile + download() */
    public function downloadTemplate(): void
    {
        $path = $this->buildTemplateFile();
        // 兼容旧调用：直接输出文件内容（非 Swoole 环境）
        $filename = 'bot_user_import_template.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        readfile($path);
        exit;
    }

    /**
     * @param string $filePath 本地临时文件
     * @param int $forceBotType 1用户机器人 2创作机器人（按钮指定，整批统一；优先于 Excel 列）
     */
    public function import(string $filePath, int $forceBotType): array
    {
        if (!in_array($forceBotType, [self::BOT_TYPE_USER, self::BOT_TYPE_CREATOR], true)) {
            throw new ValidateException('请通过「导入用户机器人」或「导入创作机器人」按钮上传');
        }
        if (!is_file($filePath)) {
            throw new ValidateException('上传文件不存在');
        }

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);
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
        if (!$fieldIndex) {
            throw new ValidateException('无法解析表头，请下载官方模版填写');
        }

        $userRepo = app()->make(UserRepository::class);
        $profileRepo = app()->make(UserProfileRepository::class);

        $created = 0;
        $updated = 0;
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
                // 以按钮选择的类型为准，整批统一
                $result = $this->upsertBotUser($userRepo, $profileRepo, $data, $forceBotType);
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

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string)$cell) !== '') {
                return false;
            }
        }
        return true;
    }

    private function upsertBotUser(UserRepository $userRepo, UserProfileRepository $profileRepo, array $data, int $botType): string
    {
        $account = trim((string)($data['account'] ?? ''));
        $nickname = trim((string)($data['nickname'] ?? ''));
        $avatar = strip_image_watermark_url(trim((string)($data['avatar'] ?? '')));

        $exist = null;
        if ($account !== '') {
            $exist = Db::name('user')->where('account', $account)->whereNull('cancel_time')->find();
        }

        if (!$exist) {
            if ($account === '') {
                $account = 'bot_' . date('ymdHis') . '_' . mt_rand(1000, 9999);
                // 极小概率冲突时再拼一段
                while (Db::name('user')->where('account', $account)->count()) {
                    $account = 'bot_' . date('ymdHis') . '_' . mt_rand(10000, 99999);
                }
            }
            if ($nickname === '') {
                $nickname = '用户' . substr($account, -5);
            }
            if ($avatar === '') {
                $avatar = systemConfig('user_default_avatar') ?: '';
            }

            $pwd = $userRepo->encodePassword(bin2hex(random_bytes(16)));
            $user = $userRepo->create('import', [
                'account' => $account,
                'pwd' => $pwd,
                'nickname' => mb_substr($nickname, 0, 16),
                'avatar' => $avatar,
                'real_name' => (string)($data['real_name'] ?? ''),
                'sex' => $this->parseEnum('sex', (string)($data['sex'] ?? '')) ?? 0,
                'birthday' => $this->nullableDate($data['birthday'] ?? ''),
                'phone' => null,
                'status' => 1,
                'mark' => (string)($data['mark'] ?? ''),
                'bot_type' => $botType,
                'is_promoter' => 0,
                'promoter_switch' => 0,
            ]);
            $uid = (int)$user['uid'];
            // create() 会把 user_type 设为 import；补写 bot_type（防止 create 过滤）
            Db::name('user')->where('uid', $uid)->update([
                'user_type' => 'import',
                'bot_type' => $botType,
                'phone' => null,
            ]);
            $this->saveProfile($profileRepo, $uid, $data);
            return 'created';
        }

        // 覆盖更新
        $uid = (int)$exist['uid'];
        $update = [
            'user_type' => 'import',
            'bot_type' => $botType,
            'phone' => null,
        ];
        if ($nickname !== '') {
            $update['nickname'] = mb_substr($nickname, 0, 16);
        }
        if ($avatar !== '') {
            $update['avatar'] = $avatar;
        }
        if (isset($data['real_name']) && $data['real_name'] !== '') {
            $update['real_name'] = $data['real_name'];
        }
        if (isset($data['sex']) && $data['sex'] !== '') {
            $sex = $this->parseEnum('sex', (string)$data['sex']);
            if ($sex !== null) {
                $update['sex'] = $sex;
            }
        }
        if (!empty($data['birthday'])) {
            $update['birthday'] = $this->nullableDate($data['birthday']);
        }
        if (isset($data['mark'])) {
            $update['mark'] = (string)$data['mark'];
        }
        Db::name('user')->where('uid', $uid)->update($update);
        $this->saveProfile($profileRepo, $uid, $data);
        return 'updated';
    }

    private function saveProfile(UserProfileRepository $profileRepo, int $uid, array $data): void
    {
        $profile = [];
        $keys = [
            'height', 'weight', 'birth_month', 'zodiac', 'education', 'education_type',
            'job_title', 'hometown_province', 'hometown_city', 'current_province', 'current_city',
            'annual_income', 'car_count', 'house_count', 'total_assets',
            'relationship_status', 'dating_purpose',
        ];
        $textKeys = ['job_title', 'hometown_province', 'hometown_city', 'current_province', 'current_city', 'birth_month'];
        $enumKeys = array_keys(self::ENUM_MAPS);

        foreach ($keys as $k) {
            if (!isset($data[$k]) || $data[$k] === '') {
                continue;
            }
            if (in_array($k, $textKeys, true)) {
                $profile[$k] = (string)$data[$k];
                continue;
            }
            if (in_array($k, $enumKeys, true)) {
                $parsed = $this->parseEnum($k, (string)$data[$k]);
                if ($parsed !== null) {
                    $profile[$k] = $parsed;
                }
                continue;
            }
            // 身高体重车辆房产等数字
            $profile[$k] = (int)preg_replace('/[^\d]/', '', (string)$data[$k]);
        }
        // 用户表性别也走枚举
        if (isset($data['sex']) && $data['sex'] !== '') {
            $sex = $this->parseEnum('sex', (string)$data['sex']);
            if ($sex !== null) {
                // sex 写在 user 表，这里仅保证 profile 不吞掉；upsertBotUser 里已处理
            }
        }
        if ($profile) {
            $profileRepo->save($uid, $profile);
        }
    }

    private function parseEnum(string $field, string $raw): ?int
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        $map = self::ENUM_MAPS[$field] ?? [];
        if (isset($map[$raw])) {
            return (int)$map[$raw];
        }
        // 去掉空格再试
        $compact = preg_replace('/\s+/', '', $raw);
        if (isset($map[$compact])) {
            return (int)$map[$compact];
        }
        if (ctype_digit($raw)) {
            return (int)$raw;
        }
        return null;
    }

    private function nullableDate(string $v): ?string
    {
        $v = trim($v);
        if ($v === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $v)) {
            return substr($v, 0, 10);
        }
        return null;
    }
}
