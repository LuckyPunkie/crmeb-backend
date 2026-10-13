<?php

// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016-2026 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\command;

use think\facade\Log;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;

class ImportSql extends Command
{
    protected function configure()
    {
        $this->setName('sql:import')
            ->addArgument('file', Argument::OPTIONAL, 'SQL文件路径', root_path('backup').'update_4_0.sql')
            ->addOption('force', 'f', Option::VALUE_NONE, 'SQL执行报错时继续执行后续SQL')
            ->setDescription('按升级SQL方式导入指定文件，默认导入 backup/update_4_0.sql');
    }

    protected function execute(Input $input, Output $output)
    {
        $file = $input->getArgument('file');
        if (!is_file($file)) {
            $output->error('SQL文件不存在：' . $file);
            return 1;
        }

        $sqldata = file_get_contents($file);
        if ($sqldata === false || trim($sqldata) === '') {
            $output->error('SQL文件为空或读取失败：' . $file);
            return 1;
        }

        $installHost = systemConfig('site_url');
        $hostArr = explode('//', $installHost);
        $_url = $hostArr[1] ?? $installHost;
        $sqldata = str_replace('mer1.crmeb.net', $_url, $sqldata);
        $sqldata = str_replace('mer.crmeb.net', $_url, $sqldata);

        $str = preg_replace('/--.*/i', '', $sqldata);
        $str = preg_replace('/\/\*.*\*\/(\;)?/i', '', $str);
        $sqlList = explode(";\n", $str);
        $count = count($sqlList);
        $pre = env('database.prefix');
        $force = (bool)$input->getOption('force');
        $sqlError = '';

        $output->writeln('开始导入：' . $file);
        Db::execute('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($sqlList as $idx => $sql) {
                $sql = trim($sql, " \xEF\xBB\xBF\r\n");
                if (!$sql) continue;
                if ($pre && $pre !== 'eb_') {
                    $sql = str_replace('eb_', $pre, $sql);
                }
                try {
                    Db::execute($sql . ';');
                } catch (\Throwable $e) {
                    $sqlError .= $e->getMessage() . PHP_EOL;
                    if (!$force) {
                        throw $e;
                    }
                }
                if (!($idx % 50)) {
                    $output->info("导入中($idx/$count)");
                }
            }
        } catch (\Throwable $e) {
            Db::execute('SET FOREIGN_KEY_CHECKS=1');
            $output->error('SQL导入失败：' . $e->getMessage());
            return 1;
        }
        Db::execute('SET FOREIGN_KEY_CHECKS=1');

        if ($sqlError) {
            Log::info("SQL导入失败的信息，请自行判断处理:".var_export([
                    ['---------------------',$sqlError,'---------------------'],
                    true
                ])
            );
            $output->warning('SQL导入失败的信息，请自行判断处理:' . $sqlError);
        }
        $output->info('SQL导入完成');
        return 0;
    }
}
