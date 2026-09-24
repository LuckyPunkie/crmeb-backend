<?php

namespace crmeb\services\identity;

use crmeb\services\HttpService;

/**
 * 阿里云云市场 APPCODE 方式：姓名 + 身份证号 + 手机号 运营商三要素核验。
 */
class AliyunMobileThreeElementService
{
    /**
     * @return array{ok:bool, message:string, raw?:array}
     */
    public function verify(string $name, string $idCard, string $mobile): array
    {
        $appcode = trim((string)config('identity.appcode'));
        $url = trim((string)config('identity.verify_url'));
        if ($appcode === '' || $url === '') {
            return ['ok' => false, 'message' => '实名核验服务未配置，请联系管理员'];
        }

        $params = [
            (string)config('identity.param_name')   => $name,
            (string)config('identity.param_idcard') => $idCard,
            (string)config('identity.param_mobile') => $mobile,
        ];

        $method = (string)config('identity.method');
        $headers = [
            'Authorization: APPCODE ' . $appcode,
        ];

        if ($method === 'POST') {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded; charset=UTF-8';
            $body = HttpService::request($url, 'post', http_build_query($params), $headers, 15);
        } else {
            $body = HttpService::getRequest($url, $params, $headers, 15);
        }

        if ($body === false) {
            $status = HttpService::getStatus();
            $code = is_array($status) ? (int)($status['http_code'] ?? 0) : 0;
            $err = HttpService::getCurlError();
            if ($code === 403) {
                return ['ok' => false, 'message' => '实名核验次数已用尽或 AppCode 无效'];
            }
            return ['ok' => false, 'message' => $err ?: ('核验请求失败' . ($code ? "（HTTP {$code}）" : ''))];
        }

        $json = json_decode($body, true);
        if (!is_array($json)) {
            return ['ok' => false, 'message' => '核验返回格式异常'];
        }

        return $this->parseResult($json);
    }

    /**
     * @param array $json
     * @return array{ok:bool, message:string, raw?:array}
     */
    private function parseResult(array $json): array
    {
        // swphone3 operator3_precision：{"charge":1|2,"result_code":"...","result_msg":"...","request_id":"..."}
        if (isset($json['result_code'])) {
            $rc = (string)$json['result_code'];
            $msg = trim((string)($json['result_msg'] ?? $json['resultMessage'] ?? $json['message'] ?? ''));
            $data = $json['data'] ?? [];

            if (is_array($data) && isset($data['result'])) {
                $result = (int)$data['result'];
                if ($result === 1) {
                    return ['ok' => true, 'message' => $msg ?: '核验通过', 'raw' => $json];
                }
                return [
                    'ok'      => false,
                    'message' => $msg ?: $this->operatorDetailMessage($result),
                    'raw'     => $json,
                ];
            }

            $successCodes = ['0000', '000000', '10000', '200000', '1000', '1001', '01'];
            if (in_array($rc, $successCodes, true)) {
                return ['ok' => true, 'message' => $msg ?: '核验通过', 'raw' => $json];
            }

            if ($msg !== '') {
                return ['ok' => false, 'message' => $msg, 'raw' => $json];
            }

            return ['ok' => false, 'message' => '核验未通过', 'raw' => $json];
        }

        // 常见：{"respCode":"0000","respMessage":"信息匹配"}
        if (isset($json['respCode'])) {
            $code = (string)$json['respCode'];
            $msg = (string)($json['respMessage'] ?? $json['message'] ?? '');
            if ($code === '0000') {
                return ['ok' => true, 'message' => $msg ?: '核验通过', 'raw' => $json];
            }
            return ['ok' => false, 'message' => $msg ?: '姓名、身份证号与手机号不一致', 'raw' => $json];
        }

        // {"code":"0000","msg":"调用成功","data":{"result":1}}（swphone3 operator3_precision 等详版）
        if (array_key_exists('code', $json)) {
            $codeStr = (string)$json['code'];
            $msg = (string)($json['msg'] ?? $json['message'] ?? '');
            $data = $json['data'] ?? [];
            if ($codeStr === '0000' && is_array($data) && isset($data['result'])) {
                $result = (int)$data['result'];
                if ($result === 1) {
                    return ['ok' => true, 'message' => $msg ?: '核验通过', 'raw' => $json];
                }
                return [
                    'ok'      => false,
                    'message' => $msg ?: $this->operatorDetailMessage($result),
                    'raw'     => $json,
                ];
            }
            if ($codeStr === '0000') {
                return ['ok' => true, 'message' => $msg ?: '核验通过', 'raw' => $json];
            }
            if ($codeStr !== '' && $codeStr !== '0') {
                return ['ok' => false, 'message' => $msg ?: '核验未通过', 'raw' => $json];
            }
            // code 为 0 / "0"：{"code":0,"data":{"result":1,"remark":"一致"}}
            $top = (int)$json['code'];
            if (is_array($data) && isset($data['result'])) {
                $result = (int)$data['result'];
                $remark = (string)($data['remark'] ?? $json['message'] ?? '');
                if ($top === 0 && $result === 1) {
                    return ['ok' => true, 'message' => $remark ?: '核验通过', 'raw' => $json];
                }
                return ['ok' => false, 'message' => $remark ?: '姓名、身份证号与手机号不一致', 'raw' => $json];
            }
            if ($top === 0) {
                return ['ok' => true, 'message' => (string)($json['message'] ?? '核验通过'), 'raw' => $json];
            }
            return ['ok' => false, 'message' => (string)($json['message'] ?? '核验未通过'), 'raw' => $json];
        }

        // 号码百科风格
        if (isset($json['Data']['IsConsistent'])) {
            $consistent = (int)$json['Data']['IsConsistent'];
            if ((string)($json['Code'] ?? '') === 'OK' && $consistent === 1) {
                return ['ok' => true, 'message' => '核验通过', 'raw' => $json];
            }
            return ['ok' => false, 'message' => (string)($json['Message'] ?? '姓名、身份证号与手机号不一致'), 'raw' => $json];
        }

        return ['ok' => false, 'message' => '无法解析核验结果', 'raw' => $json];
    }

    private function operatorDetailMessage(int $result): string
    {
        $map = [
            2 => '手机号与实名信息不一致',
            3 => '姓名与手机号实名信息不一致',
            4 => '身份证号与手机号实名信息不一致',
            5 => '未查得实名信息',
        ];
        return $map[$result] ?? '姓名、身份证号与手机号不一致';
    }
}
