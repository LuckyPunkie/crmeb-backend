<?php
declare(strict_types=1);

namespace app\command;

use crmeb\services\SchemaCacheService;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;

/**
 * 清理 ThinkORM 表结构缓存
 * 用法：
 *   php think schema:clear
 *   php think schema:clear user
 *   php think schema:clear eb_user
 */
class ClearSchemaCache extends Command
{
    protected function configure()
    {
        $this->setName('schema:clear')
            ->addArgument('table', Argument::OPTIONAL, '表名，可带或不带 eb_ 前缀；空则清全部')
            ->setDescription('清理 ThinkORM Redis/文件 表结构缓存（ALTER 加字段后必跑）');
    }

    protected function execute(Input $input, Output $output)
    {
        $table = (string)$input->getArgument('table');
        if ($table !== '') {
            $n = SchemaCacheService::clearTable($table);
            $output->writeln("cleared table schema cache: {$table}, deleted={$n}");
            return 0;
        }
        $result = SchemaCacheService::clearAll();
        $output->writeln(sprintf(
            'cleared all schema cache: redis=%d files=%d pattern=%s',
            $result['redis'],
            $result['files'],
            SchemaCacheService::redisPattern()
        ));
        return 0;
    }
}
