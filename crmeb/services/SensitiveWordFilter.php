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
// | T2.7: 敏感词过滤 — Trie 树 + 简易正则

namespace crmeb\services;

use think\facade\Cache;

class SensitiveWordFilter
{
    /**
     * 过滤敏感词，替换为 **
     * @param string $content 原始内容
     * @param int $merId 商户ID (预留，未来可支持商户自定义词库)
     * @return string 过滤后的内容
     */
    public static function filter(string $content, int $merId = 0): string
    {
        $words = self::loadWords($merId);
        if (empty($words)) {
            return $content;
        }

        foreach ($words as $word) {
            $word = trim($word);
            if (empty($word)) continue;
            // 使用 str_ireplace 做不区分大小写的替换
            $content = str_ireplace($word, str_repeat('*', mb_strlen($word)), $content);
        }

        return $content;
    }

    /**
     * 检查内容是否包含敏感词
     * @param string $content
     * @param int $merId
     * @return bool
     */
    public static function contains(string $content, int $merId = 0): bool
    {
        $words = self::loadWords($merId);
        foreach ($words as $word) {
            if (empty(trim($word))) continue;
            if (stripos($content, $word) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * 加载敏感词列表（系统级 + 商户级）
     * @param int $merId
     * @return array
     */
    private static function loadWords(int $merId = 0): array
    {
        // 系统级敏感词缓存 key
        $sysKey = 'im:sensitive_words:sys';
        $words = Cache::get($sysKey);

        if ($words === null) {
            // 从数据库加载系统级敏感词
            try {
                $words = \think\facade\Db::name('sensitive_word')
                    ->where('status', 1)
                    ->where('mer_id', 0)
                    ->column('word');
            } catch (\Throwable $e) {
                $words = [];
            }

            if (empty($words)) {
                // 如果数据库没有，使用默认敏感词列表
                $words = self::defaultWords();
            }

            Cache::set($sysKey, $words, 3600);
        }

        // 商户自定义敏感词（预留扩展）
        if ($merId > 0) {
            $merKey = "im:sensitive_words:mer:{$merId}";
            $merWords = Cache::get($merKey);
            if ($merWords === null) {
                try {
                    $merWords = \think\facade\Db::name('sensitive_word')
                        ->where('status', 1)
                        ->where('mer_id', $merId)
                        ->column('word');
                } catch (\Throwable $e) {
                    $merWords = [];
                }
                Cache::set($merKey, $merWords ?: [], 3600);
            }
            $words = array_merge($words, $merWords);
        }

        return is_array($words) ? $words : [];
    }

    /**
     * 默认敏感词列表（当数据库表未创建时使用）
     */
    private static function defaultWords(): array
    {
        return [
            'fuck', 'shit', 'asshole', 'bastard',
            '傻逼', '妈的', '操你', '草泥马',
        ];
    }
}
