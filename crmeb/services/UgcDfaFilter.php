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

    protected static function matchAt(string $text, int $start): int
    {
        $node = self::$root;
        $len = mb_strlen($text, 'UTF-8');
        $lastEnd = 0;
        for ($i = $start; $i < $len; $i++) {
            $ch = mb_substr($text, $i, 1, 'UTF-8');
            if (!isset($node[$ch])) {
                break;
            }
            $node = $node[$ch];
            if (!empty($node['__end'])) {
                $lastEnd = $i - $start + 1;
            }
        }
        return $lastEnd;
    }
}
