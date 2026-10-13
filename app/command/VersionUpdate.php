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


namespace app\command;


use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\Exception;
use think\facade\Db;

class VersionUpdate extends Command
{
    protected function configure()
    {
        $this->setName('version:update')
            ->setDescription('crmeb_merchant 版本更新命令')
            ->addOption('package', 'p', Option::VALUE_REQUIRED, '指定更新包的路径');
    }

    /**
     * 版本升级
     * @param Input $input
     * @param Output $output
     * @return int|void|null
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/12
     */
    protected function execute(Input $input, Output $output)
    {
        $flag = $output->confirm($input, '更新之前请务必做好数据库和代码的备份,防止数据或代码在更新中被覆盖 !!!', false);
        if (!$flag) return;
        $flag = $output->confirm($input, '请确保swoole服务和队列服务已关闭,防止意外报错', false);
        if (!$flag) return;

        $version = get_crmeb_version_code();
        ini_set('memory_limit', '-1');
        set_time_limit(0);

        $packagePath = $input->getOption('package') ?: 'auto_update.zip';
        $updateFilePath = app()->getRootPath() . ltrim($packagePath, '/= ');
        $updatePath = dirname($updateFilePath);
        $unzipPath = $updatePath . '/_update_runtime_' . str_replace('.', '_', $version);
        $removeDirectory = function ($path) use (&$removeDirectory) {
            if (!is_dir($path) || is_link($path)) {
                if (is_file($path) || is_link($path)) @unlink($path);
                return;
            }
            $iterator = new \FilesystemIterator($path, \FilesystemIterator::SKIP_DOTS);
            foreach ($iterator as $item) {
                $itemPath = $item->getPathname();
                if ($item->isDir() && !$item->isLink()) {
                    $removeDirectory($itemPath);
                } else {
                    @unlink($itemPath);
                }
            }
            @rmdir($path);
        };
        $unlink = function () use ($unzipPath, $removeDirectory) {
            $removeDirectory($unzipPath);
        };
        if (!is_file($updateFilePath)) {
            $output->warning($updateFilePath . ' 更新包不存在');
            return;
        }
        if (is_dir($unzipPath)) {
            $removeDirectory($unzipPath);
        }
        if (!mkdir($unzipPath, 0777, true) && !is_dir($unzipPath)) {
            $output->warning($unzipPath . ' 临时目录创建失败');
            return;
        }
        $zip = new \ZipArchive();
        if ($zip->open($updateFilePath) === true) {
            if (!$zip->extractTo($unzipPath)) {
                $zip->close();
                $output->warning($updateFilePath . ' 更新包解压失败');
                $unlink();
                return;
            }
            $zip->close();
        } else {
            $output->warning($updateFilePath . ' 更新包打开失败');
            $unlink();
            return;
        }
        if (!is_file($unzipPath . '/.env') && !is_file($unzipPath . '/.config')) {
            $output->warning($unzipPath.'文件不完整');
            $unlink();
            return;
        }
        if (is_file($unzipPath . '/.env')) {
            $env = parse_ini_file($unzipPath . '/.env', true) ?: [];
        }
        if (is_file($unzipPath . '/.config')) {
            $env = parse_ini_file($unzipPath . '/.config', true) ?: [];
        }
        $output->info(var_export($env, true));
        if (($env['NAME'] ?? '') !== 'CRMEB-MERCHANT') {
            $output->warning('（1）版本号对比失败,请检查当前版本号(.version/更新包)是否被修改');
            $unlink();
            return;
        }
        if (($env['TYPE'] ?? '') !== 'MODEL') {
            if (!($env['OLD_VERSION'] ?? '')) {
                $output->warning('（2）版本号对比失败,请检查当前版本号(.version/更新包)是否被修改');
                $unlink();
                return;
            }
            if ($env['OLD_VERSION'] !== $version) {
                if (($env['UPDATE_VERSION']?? '') == $version) {
                    $r = $output->confirm($input, '当前版本号和升级包版本号一致，是否重新覆盖操作???', false);
                    if (!$r) {
                        $unlink();
                        return;
                    }
                } else {
                    $output->warning('（2）版本号对比失败,请检查当前版本号(.version/更新包)是否被修改:'.$env['OLD_VERSION']);
                    $unlink();
                    return;
                }
            }
        }
        $r = true;
        $confirm = [];
        if (isset($env['confirm'])) {
            $confirm = is_array($env['confirm']) ? $env['confirm'] : [$env['confirm']];
        }
        foreach ($confirm as $item) {
            if (!$output->confirm($input, $item, false)) {
                $unlink();
                return;
            }
        }
        $installHost = systemConfig('site_url');
        $appRoot = rtrim(app()->getRootPath(), '/\\') . DIRECTORY_SEPARATOR;
        $projectRoot = dirname(rtrim($appRoot, '/\\')) . DIRECTORY_SEPARATOR;
        $hostArr = explode('//', $installHost);
        $_url = $hostArr[1];

        if (is_file($unzipPath . '/update.sql')) {
            $sqldata = file_get_contents($unzipPath . '/update.sql');
            $sqldata = str_replace('mer1.crmeb.net', $_url, $sqldata);
            $sqldata = str_replace('mer.crmeb.net', $_url, $sqldata);
            $str = preg_replace('/--.*/i', '', $sqldata);
            $str = preg_replace('/\/\*.*\*\/(\;)?/i', '', $str);
            $sqlList = explode(";\n", $str);
        } else {
            $sqlList = [];
        }
        $autoUpdateData = null;
        if (is_file($unzipPath . '/AutoUpdate.php')) {
            try {
                require_once $unzipPath . '/AutoUpdate.php';
                $autoUpdateData = new \crmeb\update\AutoUpdate($input, $output);
            } catch (\Throwable $e) {}
        }

        if ($autoUpdateData) $autoUpdateData->autoUpdateStart();
        $output->info('开始更新');
        $pre = env('database.prefix');
        $sql_error = '';
        try {
            Db::transaction(function () use ($pre, $output, $unzipPath, $sqlList, $autoUpdateData, $r, $sql_error, $appRoot, $projectRoot) {
                if ($autoUpdateData) $autoUpdateData->autoUpdateBefore();
                $count = count($sqlList);
                if ($count && $autoUpdateData) {
                    $autoUpdateData->autoSqlBefore();
                }
                foreach ($sqlList as $idx => $sql) {
                    $sql = trim($sql, " \xEF\xBB\xBF\r\n");
                    if (!$sql) continue;
                    if ($pre && $pre !== 'eb_') {
                        $sql = str_replace('eb_', $pre, $sql);
                    }
                    try{
                        Db::query($sql . ';');
                    }catch (\Exception $e) {
                        $sql_error .=  $e->getMessage().PHP_EOL;
                        if (!$r) throw new Exception($e->getMessage());
                    }
                    if (!($idx % 50)) {
                        $output->info("导入中($idx/$count)");
                    }
                }
                if ($count) {
                    if ($autoUpdateData) $autoUpdateData->autoSqlAfter();
                    $output->info('数据库更新成功');
                }
                $zip = new \ZipArchive();
                if ($zip->open($unzipPath . '/update.zip') === true) {
                    if ($autoUpdateData) $autoUpdateData->autoCopyBefore();
                    $updateCodePath = $unzipPath . '/update_code';
                    if (!mkdir($updateCodePath, 0777, true) && !is_dir($updateCodePath)) {
                        $zip->close();
                        throw new Exception('更新包临时目录创建失败');
                    }
                    if (!$zip->extractTo($updateCodePath)) {
                        $zip->close();
                        throw new Exception('更新文件解压失败');
                    }
                    $zip->close();
                    $this->installUpdateFiles($updateCodePath, $appRoot, $projectRoot, $output);
                    if ($autoUpdateData) $autoUpdateData->autoCopyAfter();
                } else {
                    throw new Exception('更新文件覆盖失败');
                }
            });
            if ($autoUpdateData) $autoUpdateData->autoUpdateAfter();
        } catch (\Throwable $e) {
            $output->warning('更新失败:' . $e->getMessage());
            $unlink();
            if ($autoUpdateData) $autoUpdateData->autoUpdateFail($e);
            return;
        }
        $unlink();
        if ($autoUpdateData) $autoUpdateData->autoUpdateEnd();
        if ($sql_error) {
            $output->warning('数据库更新失败的信息，请自行判断处理:' . $sql_error);
        }
        $output->info('版本更新成功, 请重启swoole服务和队列服务');
        update_crmeb_compiled();
    }

    /**
     * 将升级包内容合并到当前部署目录。
     * 新包使用 backend/frontend 两层目录，旧包则直接放置后端文件。
     *
     * @param string $sourceRoot
     * @param string $appRoot
     * @param string $projectRoot
     * @param Output $output
     * @return void
     * @throws Exception
     */
    protected function installUpdateFiles($sourceRoot, $appRoot, $projectRoot, Output $output)
    {
        $backendPath = $sourceRoot . DIRECTORY_SEPARATOR . 'backend';
        if (!is_dir($backendPath)) {
            $output->info('未检测到 backend/ 目录，按旧版扁平升级包合并到：' . $appRoot);
            $this->copyDirectoryContents($sourceRoot, $appRoot);
            return;
        }

        $output->info('检测到 backend/ 目录，后端文件合并到：' . $appRoot);
        $this->copyDirectoryContents($backendPath, $appRoot);

        $frontendPath = $sourceRoot . DIRECTORY_SEPARATOR . 'frontend';
        if (!is_dir($frontendPath)) {
            $output->info('升级包未包含 frontend/，跳过前端源码合并');
            return;
        }

        $targetFrontendPath = rtrim($projectRoot, '/\\') . DIRECTORY_SEPARATOR . 'frontend';
        if (!is_dir($targetFrontendPath)) {
            $output->warning('服务器上未找到 frontend/ 目录，已跳过：' . $targetFrontendPath);
            return;
        }

        $output->info('检测到服务器存在 frontend/，前端文件合并到：' . $targetFrontendPath);
        $this->copyDirectoryContents($frontendPath, $targetFrontendPath);
    }

    /**
     * 递归复制目录内容，不额外增加源目录这一层。
     *
     * @param string $source
     * @param string $destination
     * @return void
     * @throws Exception
     */
    protected function copyDirectoryContents($source, $destination)
    {
        if (!is_dir($source)) {
            throw new Exception('升级目录不存在：' . $source);
        }
        if (!is_dir($destination) && !mkdir($destination, 0777, true) && !is_dir($destination)) {
            throw new Exception('目标目录创建失败：' . $destination);
        }

        $source = rtrim($source, '/\\');
        $destination = rtrim($destination, '/\\');
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $sourcePath = $item->getPathname();
            $relativePath = substr($sourcePath, strlen($source) + 1);
            $targetPath = $destination . DIRECTORY_SEPARATOR . $relativePath;

            if ($item->isLink()) {
                $targetDirectory = dirname($targetPath);
                if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0777, true) && !is_dir($targetDirectory)) {
                    throw new Exception('升级目录创建失败：' . $targetDirectory);
                }
                if (file_exists($targetPath) || is_link($targetPath)) @unlink($targetPath);
                if (!symlink(readlink($sourcePath), $targetPath)) {
                    throw new Exception('升级文件复制失败：' . $relativePath);
                }
                continue;
            }

            if ($item->isDir()) {
                if (!is_dir($targetPath) && !mkdir($targetPath, 0777, true) && !is_dir($targetPath)) {
                    throw new Exception('升级目录创建失败：' . $targetPath);
                }
                continue;
            }

            $targetDirectory = dirname($targetPath);
            if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0777, true) && !is_dir($targetDirectory)) {
                throw new Exception('升级目录创建失败：' . $targetDirectory);
            }
            if (is_dir($targetPath)) {
                throw new Exception('升级文件复制失败，目标路径是目录：' . $targetPath);
            }
            if (!copy($sourcePath, $targetPath)) {
                throw new Exception('升级文件复制失败：' . $relativePath);
            }
            $permissions = fileperms($sourcePath);
            if ($permissions !== false) {
                @chmod($targetPath, $permissions & 0777);
            }
        }
    }
}
