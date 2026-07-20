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
// | T3.8: 智能自动回复引擎 — 多级匹配策略

namespace crmeb\services;

use app\common\repositories\store\service\StoreServiceReplyRepository;
use think\facade\Cache;

class AutoReplyEngine
{
    /**
     * 多级匹配策略
     * @param string $input 用户输入
     * @param int $merId 商户ID
     * @return array|null ['content', 'match_level', 'match_keyword']
     */
    public function match(string $input, int $merId): ?array
    {
        $input = mb_strtolower(trim($input));
        if (empty($input)) return null;

        // 预热缓存
        $this->warmupCache($merId);

        // Level 1: 精确关键词匹配
        $result = $this->exactMatch($input, $merId);
        if ($result) {
            $result['match_level'] = 'exact';
            return $result;
        }

        // Level 2: 分词 + 包含匹配
        $result = $this->segmentedMatch($input, $merId);
        if ($result) {
            $result['match_level'] = 'segmented';
            return $result;
        }

        // Level 3: 模糊匹配（编辑距离 ≤ 2）
        $result = $this->fuzzyMatch($input, $merId);
        if ($result) {
            $result['match_level'] = 'fuzzy';
            return $result;
        }

        // Level 4: 默认回复
        return $this->defaultReply($merId);
    }

    /**
     * 精确关键词匹配
     */
    private function exactMatch(string $input, int $merId): ?array
    {
        $replyKey = "im:auto_reply:{$merId}";
        $redis = Cache::store()->handler();

        // 精确匹配
        $content = $redis->hGet($replyKey, $input);
        if ($content) {
            return ['content' => $content, 'match_keyword' => $input];
        }

        // 包含匹配（用户输入中包含了关键词）
        $keywords = $redis->hKeys($replyKey);
        foreach ($keywords as $kw) {
            if ($kw === '__default__') continue;
            if (mb_strpos($input, $kw) !== false) {
                return ['content' => $redis->hGet($replyKey, $kw), 'match_keyword' => $kw];
            }
        }

        return null;
    }

    /**
     * 分词匹配
     */
    private function segmentedMatch(string $input, int $merId): ?array
    {
        // 简易分词：按常见标点/空格切分
        $words = preg_split('/[，,。\s]+/u', $input);
        if (empty($words)) return null;

        $replyKey = "im:auto_reply:{$merId}";
        $redis = Cache::store()->handler();
        $keywords = $redis->hKeys($replyKey);

        foreach ($words as $word) {
            $word = trim($word);
            if (mb_strlen($word) < 2) continue;

            foreach ($keywords as $kw) {
                if ($kw === '__default__') continue;
                if (mb_strpos($word, $kw) !== false) {
                    return ['content' => $redis->hGet($replyKey, $kw), 'match_keyword' => $kw];
                }
            }
        }

        return null;
    }

    /**
     * 模糊匹配（编辑距离）
     */
    private function fuzzyMatch(string $input, int $merId, int $maxDistance = 2): ?array
    {
        $replyKey = "im:auto_reply:{$merId}";
        $redis = Cache::store()->handler();
        $keywords = $redis->hKeys($replyKey);

        foreach ($keywords as $kw) {
            if ($kw === '__default__') continue;
            if (mb_strlen($kw) < 3) continue;
            if (levenshtein($input, $kw) <= $maxDistance) {
                return ['content' => $redis->hGet($replyKey, $kw), 'match_keyword' => $kw];
            }
        }

        return null;
    }

    /**
     * 默认回复
     */
    private function defaultReply(int $merId): ?array
    {
        $replyKey = "im:auto_reply:{$merId}";
        $content = Cache::store()->handler()->hGet($replyKey, '__default__');
        return $content ? ['content' => $content, 'match_level' => 'default'] : null;
    }

    /**
     * 预热 Redis 缓存 (N1 修复: SETNX 分布式锁防缓存击穿)
     */
    private function warmupCache(int $merId): void
    {
        $replyKey = "im:auto_reply:{$merId}";
        $lockKey = "{$replyKey}:lock";

        if (Cache::store()->handler()->exists($replyKey)) {
            return;
        }

        // 使用 SETNX 避免多 Worker 竞态重复预热
        if (!Cache::store()->handler()->setnx($lockKey, time())) {
            return;
        }
        Cache::store()->handler()->expire($lockKey, 10);

        try {
            $replies = app()->make(StoreServiceReplyRepository::class)
                ->search(['mer_id' => $merId, 'status' => 1])
                ->select();

            $redis = Cache::store()->handler();
            foreach ($replies as $reply) {
                $redis->hSet($replyKey, mb_strtolower($reply['keyword']), $reply['content']);
            }
            $redis->expire($replyKey, 3600);
        } catch (\Throwable $e) {
            // 静默处理：数据库表可能不存在
        }
    }
}
