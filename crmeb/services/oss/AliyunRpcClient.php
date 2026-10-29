<?php
// +----------------------------------------------------------------------
// | 阿里云 RPC 风格 OpenAPI 调用（签名 V1，HMAC-SHA1）
// | STS AssumeRole、IMM 2020-09-30 都是 RPC 风格；项目 vendor 不进 git，
// | 不为这两个接口引入整套 SDK
// +----------------------------------------------------------------------

namespace crmeb\services\oss;

class AliyunRpcClient
{
    /**
     * @param string $endpoint 如 sts.cn-shanghai.aliyuncs.com
     * @param array $params 业务参数；复杂结构（数组）会被 json_encode
     * @return array 解析后的 JSON
     * @throws \RuntimeException 网络错误或接口返回 Code
     */
    public static function call(string $endpoint, string $version, string $action, array $params, string $ak, string $sk, int $timeout = 10): array
    {
        $query = [
            'Format' => 'JSON',
            'Version' => $version,
            'AccessKeyId' => $ak,
            'SignatureMethod' => 'HMAC-SHA1',
            'Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'SignatureVersion' => '1.0',
            'SignatureNonce' => bin2hex(random_bytes(16)),
            'Action' => $action,
        ];
        foreach ($params as $k => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            $query[$k] = is_array($v) ? json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (string)$v;
        }
        $query['Signature'] = self::sign($query, $sk, 'POST');

        $ch = curl_init('https://' . $endpoint . '/');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => self::canonicalize($query),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
        ]);
        $resp = curl_exec($ch);
        if ($resp === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException($action . ' curl: ' . ($err ?: 'failed'));
        }
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $json = json_decode((string)$resp, true);
        if (!is_array($json)) {
            throw new \RuntimeException($action . ' http_' . $code . ': ' . mb_substr((string)$resp, 0, 200));
        }
        if ($code >= 400 || isset($json['Code']) && !in_array((string)$json['Code'], ['', '200', 'OK', 'Success'], true)) {
            throw new \RuntimeException($action . ' ' . ($json['Code'] ?? $code) . ': ' . ($json['Message'] ?? ''));
        }
        return $json;
    }

    public static function sign(array $params, string $sk, string $method = 'POST'): string
    {
        unset($params['Signature']);
        $stringToSign = $method . '&' . self::encode('/') . '&' . self::encode(self::canonicalize($params));
        return base64_encode(hash_hmac('sha1', $stringToSign, $sk . '&', true));
    }

    protected static function canonicalize(array $params): string
    {
        ksort($params, SORT_STRING);
        $pairs = [];
        foreach ($params as $k => $v) {
            $pairs[] = self::encode((string)$k) . '=' . self::encode((string)$v);
        }
        return implode('&', $pairs);
    }

    /** RFC3986：PHP 的 rawurlencode 已把空格编为 %20、* 编为 %2A、保留 ~ */
    protected static function encode(string $value): string
    {
        return rawurlencode($value);
    }
}
