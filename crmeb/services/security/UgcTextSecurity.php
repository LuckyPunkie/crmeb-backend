<?php

namespace crmeb\services\security;

use crmeb\services\UgcDfaFilter;

class UgcTextSecurity
{
    public static function maskForDisplay(string $text): string
    {
        $text = trim($text);
        return $text === '' ? '' : UgcDfaFilter::filter($text);
    }

    /**
     * 社区评论：人工审核通过（status=1）原文展示；待审/未通过且命中敏感词则打星
     */
    public static function maskReplyContentForDisplay(string $text, int $status): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        if ((int)$status === 1) {
            return $text;
        }
        return UgcDfaFilter::contains($text) ? UgcDfaFilter::filter($text) : $text;
    }

    public static function applyMaskToMessage(array $item): array
    {
        if ((int)($item['msn_type'] ?? 0) === 1 && isset($item['msn'])) {
            $item['msn'] = self::maskForDisplay((string)$item['msn']);
        }
        return $item;
    }

    public static function applyMaskToCommunity(array $item): array
    {
        if (isset($item['content'])) {
            $item['content'] = self::maskForDisplay((string)$item['content']);
        }
        if (isset($item['title'])) {
            $item['title'] = self::maskForDisplay((string)$item['title']);
        }
        // 话题 / 标签命中敏感词：直接不展示（不打星）
        if (!empty($item['topic']) && is_array($item['topic']) && isset($item['topic']['topic_name'])
            && self::hitSensitive((string)$item['topic']['topic_name'])) {
            $item['topic'] = null;
        }
        if (!empty($item['topics']) && is_array($item['topics'])) {
            $item['topics'] = array_values(array_filter($item['topics'], function ($topic) {
                return !(is_array($topic) && isset($topic['topic_name']) && self::hitSensitive((string)$topic['topic_name']));
            }));
        }
        if (!empty($item['tags']) && is_array($item['tags'])) {
            $item['tags'] = array_values(array_filter($item['tags'], function ($tag) {
                return !self::hitSensitive((string)$tag);
            }));
        }
        return $item;
    }

    private static function hitSensitive(string $text): bool
    {
        $text = trim($text);
        return $text !== '' && UgcDfaFilter::contains($text);
    }
}
