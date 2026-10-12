<?php

namespace crmeb\services\security\driver;

use crmeb\services\security\AliyunBaselinePolicy;
use think\facade\Log;

class AliyunBaselineDriver
{
    public static function enabled(): bool
    {
        $id = (string)systemConfig('aliyun_AccessKeyId');
        $secret = (string)systemConfig('aliyun_AccessKeySecret');
        return $id !== '' && $secret !== '';
    }

    /** @return array{verdict:string,results:array,error:string} */
    public static function moderateImageUrl(string $imageUrl): array
    {
        $imageUrl = trim($imageUrl);
        if ($imageUrl === '') {
            return ['verdict' => AliyunBaselinePolicy::ACTION_MANUAL, 'results' => [], 'error' => 'empty_url'];
        }
        if (!preg_match('#^https?://#i', $imageUrl)) {
            $imageUrl = rtrim((string)systemConfig('site_url'), '/') . '/' . ltrim($imageUrl, '/');
        }
        if (!self::enabled()) {
            return ['verdict' => AliyunBaselinePolicy::ACTION_MANUAL, 'results' => [], 'error' => 'aliyun_disabled'];
        }
        try {
            $results = self::callApi($imageUrl);
            $verdict = AliyunBaselinePolicy::verdictForResults($results);
            return ['verdict' => $verdict, 'results' => $results, 'error' => ''];
        } catch (\Throwable $e) {
            Log::error('Aliyun baselineCheck 失败: ' . $e->getMessage());
            return ['verdict' => AliyunBaselinePolicy::ACTION_MANUAL, 'results' => [], 'error' => $e->getMessage()];
        }
    }

    protected static function callApi(string $imageUrl): array
    {
        $ak = (string)systemConfig('aliyun_AccessKeyId');
        $sk = (string)systemConfig('aliyun_AccessKeySecret');
        $host = (string)(systemConfig('ugc_aliyun_green_endpoint') ?: 'green-cip.cn-shanghai.aliyuncs.com');
        $body = json_encode([
            'Service' => 'baselineCheck',
            'ServiceParameters' => json_encode(['imageUrl' => $imageUrl], JSON_UNESCAPED_SLASHES),
        ], JSON_UNESCAPED_UNICODE);
        $response = self::acs3Post($host, 'ImageModeration', '2022-03-02', $body, $ak, $sk);
        if ($response === '') {
            throw new \RuntimeException('empty_response');
        }
        $json = json_decode($response, true);
        if (!is_array($json)) {
            throw new \RuntimeException('invalid_json');
        }
        if (isset($json['Code']) && (string)$json['Code'] !== '200') {
            $msg = (string)($json['Message'] ?? $json['Msg'] ?? 'api_error');
            throw new \RuntimeException($msg);
        }
        $data = $json['Data'] ?? [];
        $results = $data['Result'] ?? $data['result'] ?? [];
        if (!is_array($results)) {
            return [];
        }
        $out = [];
        foreach ($results as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = [
                'Label' => (string)($row['Label'] ?? $row['label'] ?? ''),
                'Confidence' => isset($row['Confidence']) ? (float)$row['Confidence'] : (isset($row['confidence']) ? (float)$row['confidence'] : null),
            ];
        }
        return $out;
    }

    protected static function acs3Post(string $host, string $action, string $version, string $body, string $ak, string $sk): string
    {
        $date = gmdate('Y-m-d\TH:i:s\Z');
        $nonce = bin2hex(random_bytes(8));
        $contentHash = hash('sha256', $body);
        $signedHeaders = 'host;x-acs-action;x-acs-content-sha256;x-acs-date;x-acs-signature-nonce;x-acs-version';
        $canonicalHeaders = 'host:' . $host . "\n"
            . 'x-acs-action:' . $action . "\n"
            . 'x-acs-content-sha256:' . $contentHash . "\n"
            . 'x-acs-date:' . $date . "\n"
            . 'x-acs-signature-nonce:' . $nonce . "\n"
            . 'x-acs-version:' . $version . "\n";
        $canonicalRequest = "POST\n/\n\n" . $canonicalHeaders . "\n" . $signedHeaders . "\n" . $contentHash;
        $stringToSign = "ACS3-HMAC-SHA256\n" . hash('sha256', $canonicalRequest);
        $signature = bin2hex(hash_hmac('sha256', $stringToSign, $sk, true));
        $authorization = 'ACS3-HMAC-SHA256 Credential=' . $ak . ',SignedHeaders=' . $signedHeaders . ',Signature=' . $signature;
        $url = 'https://' . $host . '/';
        $headers = [
            'Content-Type: application/json',
            'Host: ' . $host,
            'x-acs-action: ' . $action,
            'x-acs-version: ' . $version,
            'x-acs-date: ' . $date,
            'x-acs-signature-nonce: ' . $nonce,
            'x-acs-content-sha256: ' . $contentHash,
            'Authorization: ' . $authorization,
        ];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        $resp = curl_exec($ch);
        if ($resp === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException($err ?: 'curl_failed');
        }
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 400) {
            throw new \RuntimeException('http_' . $code . ':' . mb_substr((string)$resp, 0, 200));
        }
        return (string)$resp;
    }
}