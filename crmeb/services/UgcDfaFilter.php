<?php

namespace crmeb\services;

class UgcDfaFilter
{
    protected static $root = [];

    protected static $wordCount = 0;

    protected static $inited = false;

    protected static function dfaFile(): string
    {
        return root_path() . 'crmeb' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'ugc_sensitive_dfa.php';
    }

    public static function init(): void
    {
        if (self::$inited) {
            return;
        }
        self::$inited = true;
        $file = self::dfaFile();
        if (!is_file($file)) {
            self::$root = [];
            self::$wordCount = 0;
            return;
        }
        $data = include $file;
        if (!is_array($data)) {
            self::$root = [];
            self::$wordCount = 0;
            return;
        }
        self::$wordCount = (int)($data['__word_count'] ?? 0);
        self::$root = is_array($data['nodes'] ?? null) ? $data['nodes'] : [];
    }

    public static function wordCount(): int
    {
        self::init();
        return self::$wordCount;
    }

    public static function contains(string $text): bool
    {
        $text = (string)$text;
        if ($text === '') {
            return false;
        }
        self::init();
        if (!self::$root) {
            return false;
        }
        $len = mb_strlen($text, 'UTF-8');
        for ($i = 0; $i < $len; $i++) {
            if (self::matchAt($text, $i) > 0) {
                return true;
            }
        }
        return false;
    }

    public static function filter(string $text): string
    {
        $text = (string)$text;
        if ($text === '') {
            return '';
        }
        self::init();
        if (!self::$root) {
            return $text;
        }
        $len = mb_strlen($text, 'UTF-8');
        $out = '';
        for ($i = 0; $i < $len;) {
            $hitLen = self::matchAt($text, $i);
            if ($hitLen > 0) {
                $out .= str_repeat('*', $hitLen);
                $i += $hitLen;
                continue;
            }
            $out .= mb_substr($text, $i, 1, 'UTF-8');
            $i++;
        }
        return $out;
    }

    /**
     * 从 $start 起匹配敏感词，匹配过程中可跳过空白（防「硝酸甘油[换行]炸弹」类拆分绕过）
     *
     * @return int 命中时在原文中占用的字符数（含中间被跳过的空白），0 表示未命中
     */
    protected static function matchAt(string $text, int $start): int
    {
        $node = self::$root;
        $len = mb_strlen($text, 'UTF-8');
        $lastSpan = 0;
        $matchedAny = false;
        for ($i = $start; $i < $len; $i++) {
            $ch = mb_substr($text, $i, 1, 'UTF-8');
            if (self::isIgnorableGap($ch)) {
                continue;
            }
            if (!isset($node[$ch])) {
                break;
            }
            $node = $node[$ch];
            $matchedAny = true;
            if (!empty($node['__end'])) {
                $lastSpan = $i - $start + 1;
            }
        }
        return $matchedAny ? $lastSpan : 0;
    }

    /** 匹配时可跳过的分隔（换行、空格等），不参与 DFA 状态转移 */
    protected static function isIgnorableGap(string $ch): bool
    {
        if ($ch === '') {
            return false;
        }
        return (bool)preg_match('/[\s\p{Z}\p{Cf}]/u', $ch);
    }
}
