<?php

namespace crmeb\jobs;

use app\common\repositories\taoke\ServiceGoodsRepository;
use crmeb\interfaces\JobInterface;
use think\facade\Log;

/**
 * 平台直连服务页 Tab 探活（后台刷新，service_tabs 只读缓存结果）
 */
class ServiceTabProbeJob implements JobInterface
{
    public function fire($job, $tab)
    {
        try {
            if (is_array($tab)) {
                app()->make(ServiceGoodsRepository::class)
                    ->withDriverChannel('official')
                    ->refreshOfficialTabProbe($tab);
            }
        } catch (\Throwable $e) {
            Log::warning('服务页 Tab 后台探活异常', ['tab_key' => $tab['tab_key'] ?? '', 'error' => $e->getMessage()]);
        }
        $job->delete();
    }

    public function failed($data)
    {
    }
}
