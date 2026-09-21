<?php
declare (strict_types=1);

namespace app\command;

use app\common\repositories\user\UserRepository;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

/**
 * 为存量用户批量生成 user_code
 * 用法：php think user:backfill_code
 * 幂等：只处理 user_code 为 NULL 或空串的行；已有值的用户跳过。
 */
class BackfillUserCode extends Command
{
    protected function configure()
    {
        $this->setName('user:backfill_code')
            ->setDescription('为存量用户批量生成 7 位 user_code');
    }

    protected function execute(Input $input, Output $output)
    {
        $repo = app()->make(UserRepository::class);

        // 空值 + 不合混排规则（全数字 / 全字母 / 长度不对）的都需要重发
        // MySQL 里 REGEXP 大小写敏感需靠字符集，这里直接分两条：全数字 or 全字母 or 空
        $badWhere = "user_code IS NULL "
            . "OR user_code = '' "
            . "OR CHAR_LENGTH(user_code) <> 7 "
            . "OR user_code REGEXP '^[0-9]+$' "
            . "OR user_code REGEXP '^[A-Za-z]+$'";

        $total = Db::name('user')->whereRaw($badWhere)->count();
        if ($total === 0) {
            $output->info('无需回填，所有用户 user_code 均符合混排规则');
            return 0;
        }
        $output->info("待回填/重发 {$total} 个用户");

        $done = 0;
        $failed = 0;
        Db::name('user')
            ->whereRaw($badWhere)
            ->field('uid,user_code')
            ->chunk(200, function ($rows) use ($repo, &$done, &$failed, $output) {
                foreach ($rows as $row) {
                    try {
                        $code = $repo->generateUserCode();
                        Db::name('user')->where('uid', $row['uid'])->update(['user_code' => $code]);
                        $done++;
                    } catch (\Throwable $e) {
                        $failed++;
                        $output->warning("uid={$row['uid']} 生成失败: " . $e->getMessage());
                    }
                }
            });

        $output->info("完成，成功 {$done}，失败 {$failed}");
        return 0;
    }
}
