<?php
// +----------------------------------------------------------------------
// | App 端文本降级：京东 JoySafety 内容安全服务（《内容安全阈值确定版》3.1）
// | 接口：POST {base}/llmsec/api/defense/v2/{accessKey}
// | 签名：HMAC-SHA1(accessKey=&accessTarget=defenseV2&requestId=&timestamp=&plainText=, secret) 再 base64
// | 后台 社区配置 填 joysafety_url / joysafety_access_key / joysafety_secret 后启用，任一为空则不启用
// +----------------------------------------------------------------------

namespace crmeb\services\security\driver;

class JoySafetyDriver
{
    const TIMEOUT = 30;
    const RETRIES = 3;

    public static function enabled(): bool
    {
        [$url, $ak, $secret] = self::config();
        return $url !== '' && $ak !== '' && $secret !== '';
    }

    /**
     * @return array{pass:bool,suggest:string,label:int,trace_id:string}
     * @throws \RuntimeException 服务不可用或返回错误
     */
    public static function checkText(string $content, int $uid): array
    {
        [$url, $ak, $secret] = self::config();
        return self::request($url, $ak, $secret, $content, $uid);
    }

    /**
     * @return array{pass:bool,suggest:string,label:int,trace_id:string}
     */
    public static function request(string $baseUrl, string $accessKey, string $secret, string $content, int $uid): array
    {
        $requestId = bin2hex(random_bytes(16));
        $timestamp = (int)round(microtime(true) * 1000);
        $body = [
            'requestId' => $requestId,
            'timestamp' => $timestamp,
            'accessKey' => $accessKey,
            'accessTarget' => 'defenseV2',
            'plainText' => $content,
            'businessType' => 'toC',
            'responseMode' => 'sync',
            'contentType' => 'text',
            'content' => $content,
            'messageInfo' => ['fromRole' => 'user', 'fromId' => (string)$uid],
        ];
        $signText = "accessKey={$accessKey}&accessTarget=defenseV2&requestId={$requestId}&timestamp={$timestamp}&plainText={$content}";
        $body['signature'] = base64_encode(hash_hmac('sha1', $signText, $secret, true));
        $url = rtrim($baseUrl, '/') . '/llmsec/api/defense/v2/' . rawurlencode($accessKey);

        $lastError = null;
        for ($attempt = 1; $attempt <= self::RETRIES; $attempt++) {
            try {
                $res = self::post($url, $body);
                if ((int)($res['code'] ?? -1) !== 0) {
                    throw new \RuntimeException('JoySafety 返回错误: ' . ($res['message'] ?? json_encode($res, JSON_UNESCAPED_UNICODE)));
                }
                $riskCode = 0;
                foreach ((array)($res['data'] ?? []) as $item) {
                    $code = (int)($item['riskCode'] ?? 0);
                    if ($code !== 0) {
                        $riskCode = $code;
                        break;
                    }
                }
                return [
                    'pass' => $riskCode === 0,
                    'suggest' => $riskCode === 0 ? 'pass' : 'risky',
                    'label' => $riskCode,
                    'trace_id' => $requestId,
                ];
            } catch (\Throwable $e) {
                $lastError = $e;
            }
        }
        throw $lastError instanceof \RuntimeException ? $lastError : new \RuntimeException($lastError->getMessage());
    }

    protected static function post(string $url, array $body): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => self::TIMEOUT,
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false || $status !== 200) {
            throw new \RuntimeException("JoySafety 请求失败 HTTP {$status} {$err}");
        }
        $data = json_decode((string)$raw, true);
        if (!is_array($data)) {
            throw new \RuntimeException('JoySafety 响应不是 JSON');
        }
        return $data;
    }

    /**
     * @return array{0:string,1:string,2:string}
     */
    protected static function config(): array
    {
        try {
            $cfg = systemConfig(['joysafety_url', 'joysafety_access_key', 'joysafety_secret']);
        } catch (\Throwable $e) {
            $cfg = [];
        }
        return [
            trim((string)($cfg['joysafety_url'] ?? '')),
            trim((string)($cfg['joysafety_access_key'] ?? '')),
            trim((string)($cfg['joysafety_secret'] ?? '')),
        ];
    }
}
