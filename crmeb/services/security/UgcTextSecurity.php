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
        if (!empty($item['topic']) && is_array($item['topic']) && isset($item['topic']['topic_name'])) {
            $item['topic']['topic_name'] = self::maskForDisplay((string)$item['topic']['topic_name']);
        }
        if (!empty($item['topics']) && is_array($item['topics'])) {
            foreach ($item['topics'] as $i => $topic) {
                if (!is_array($topic) || !isset($topic['topic_name'])) {
                    continue;
                }
                $item['topics'][$i]['topic_name'] = self::maskForDisplay((string)$topic['topic_name']);
            }
        }
        if (!empty($item['tags']) && is_array($item['tags'])) {
            foreach ($item['tags'] as $i => $tag) {
                $item['tags'][$i] = self::maskForDisplay((string)$tag);
            }
        }
        return $item;
    }
}
