<?php
// +----------------------------------------------------------------------
// | 轻量消息队列（原 MNS）HTTP 推送验签
// | 待签串：METHOD\nContent-MD5\nContent-Type\nDate\n<x-mns-* 头按名排序 key:value\n><路径>
// | 公钥证书地址在 x-mns-signing-cert-url（base64），只信任 aliyuncs.com 下的证书
// | 验签只是第一道：收到通知后仍以 IMM GetTask 查到的状态为准
// +----------------------------------------------------------------------

namespace crmeb\services\oss;

use think\facade\Cache;
use think\facade\Log;

class MnsSignature
{
    /**
     * @param array $headers 小写头名 => 值
     */
    public static function verify(string $method, string $path, array $headers): bool
    {
        $certUrl = (string)base64_decode((string)($headers['x-mns-signing-cert-url'] ?? ''));
        $host = (string)parse_url($certUrl, PHP_URL_HOST);
        if ($host === '' || !preg_match('/(^|\.)aliyuncs\.com$/', $host)) {
            return false;
        }
        $signature = (string)base64_decode((string)($headers['authorization'] ?? ''));
        if ($signature === '') {
            return false;
        }
        $mnsHeaders = [];
        foreach ($headers as $name => $value) {
            if (strpos($name, 'x-mns-') === 0) {
                $mnsHeaders[$name] = $name . ':' . $value;
            }
        }
        ksort($mnsHeaders, SORT_STRING);
        $stringToSign = strtoupper($method) . "\n"
            . ($headers['content-md5'] ?? '') . "\n"
            . ($headers['content-type'] ?? '') . "\n"
            . ($headers['date'] ?? '') . "\n"
            . ($mnsHeaders ? implode("\n", $mnsHeaders) . "\n" : '')
            . $path;
        $cert = self::cert($certUrl);
        if ($cert === '') {
            return false;
        }
        return openssl_verify($stringToSign, $signature, $cert, OPENSSL_ALGO_SHA1) === 1;
    }

    protected static function cert(string $url): string
    {
        $cacheKey = 'mns_signing_cert:' . md5($url);
        $cert = (string)Cache::get($cacheKey, '');
        if ($cert !== '') {
            return $cert;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
        $cert = (string)curl_exec($ch);
        curl_close($ch);
        if (strpos($cert, 'BEGIN CERTIFICATE') === false) {
            Log::error('MNS 签名证书下载失败: ' . $url);
            return '';
        }
        Cache::set($cacheKey, $cert, 86400);
        return $cert;
    }
}
