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
// | T4.7: Prometheus 指标采集器

namespace crmeb\services\metrics;

use think\facade\Cache;

class ImMetricsCollector
{
    private static string $metricsKey = 'im:metrics:data';

    /**
     * 记录连接
     */
    public static function recordConnection(string $type): void
    {
        self::incCounter('im_connections_total', 1, ['type' => $type]);
        self::incGauge('im_connections_active', ['type' => $type]);
    }

    /**
     * 记录断开
     */
    public static function recordDisconnection(string $type): void
    {
        self::decGauge('im_connections_active', ['type' => $type]);
    }

    /**
     * 记录消息
     */
    public static function recordMessage(string $type, int $msnType, float $latencyMs): void
    {
        self::incCounter('im_messages_total', 1, ['type' => $type, 'msn_type' => (string)$msnType]);
        self::addLatency('im_message_latency', $latencyMs, ['type' => $type]);
    }

    /**
     * 记录离线队列深度
     */
    public static function recordOfflineQueueDepth(int $depth): void
    {
        self::setGaugeRaw('im_offline_queue_depth', $depth);
    }

    // ---- 内部辅助方法 ----

    private static function incCounter(string $metric, int $by = 1, array $labels = []): void
    {
        $k = self::labelKey($metric, $labels);
        Cache::store()->handler()->hIncrBy(self::$metricsKey, "counter:{$k}", $by);
    }

    private static function incGauge(string $metric, array $labels = []): void
    {
        $k = self::labelKey($metric, $labels);
        Cache::store()->handler()->hIncrBy(self::$metricsKey, "gauge:{$k}", 1);
    }

    private static function decGauge(string $metric, array $labels = []): void
    {
        $k = self::labelKey($metric, $labels);
        $val = Cache::store()->handler()->hIncrBy(self::$metricsKey, "gauge:{$k}", -1);
        if ($val < 0) {
            Cache::store()->handler()->hSet(self::$metricsKey, "gauge:{$k}", 0);
        }
    }

    private static function setGaugeRaw(string $metric, int $value): void
    {
        Cache::store()->handler()->hSet(self::$metricsKey, "gauge:{$metric}", $value);
    }

    private static function addLatency(string $metric, float $ms, array $labels = []): void
    {
        $k = self::labelKey($metric, $labels);
        // 简易 P50/P95/P99 存储：存储值用于 Grafana 概览
        Cache::store()->handler()->hSet(self::$metricsKey, "latency:{$k}:last", $ms);

        // 维护最近 100 条延迟数据用于分位数计算
        $listKey = "{$k}:samples";
        Cache::store()->handler()->lPush($listKey, $ms);
        Cache::store()->handler()->lTrim($listKey, 0, 99);
    }

    private static function labelKey(string $metric, array $labels): string
    {
        if (empty($labels)) return $metric;
        $parts = [$metric];
        foreach ($labels as $k => $v) {
            $parts[] = "{$k}={$v}";
        }
        return implode(',', $parts);
    }
}
