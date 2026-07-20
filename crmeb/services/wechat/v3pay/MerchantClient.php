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
namespace crmeb\services\wechat\v3pay;

use crmeb\services\wechat\config\V3PaymentConfig;
use crmeb\services\wechat\config\ServicePaymentConfig;
use think\exception\ValidateException;

/**
 * 二级商户进件 / 分账接收方管理 / 媒体文件上传
 * 对应旧版 easywechat/merchant/Client
 * Class MerchantClient
 */
class MerchantClient extends BaseClient
{

    public function __construct(V3PaymentConfig $config, ServicePaymentConfig $serviceConfig)
    {
        parent::__construct($config, $serviceConfig);
        $this->isServicePay = true;
    }

    /**
     * 二级商户进件成为微信支付商户
     * POST /v3/ecommerce/applyments/
     * @param array $params
     * @return mixed
     */
    public function submitApplication(array $params)
    {
        $params = $this->processParams($params);
        $res = $this->request('v3/ecommerce/applyments/', 'POST', [
            'body' => json_encode($params, JSON_UNESCAPED_UNICODE)
        ], true);
        if (isset($res['code'])) {
            throw new ValidateException('[微信接口返回]:' . $res['message']);
        }
        return $res;
    }

    /**
     * 申请单ID查询申请状态
     * GET /v3/ecommerce/applyments/{applyment_id}
     * @param string|int $id
     * @return mixed
     */
    public function getApplicationById($id)
    {
        $res = $this->request('v3/ecommerce/applyments/' . $id, 'GET');
        if (isset($res['code'])) {
            throw new ValidateException('[微信接口返回]:' . $res['message']);
        }
        return $res;
    }

    /**
     * 业务申请编号查询申请状态
     * GET /v3/ecommerce/applyments/out-request-no/{out_request_no}
     * @param string $no
     * @return mixed
     */
    public function getApplicationByNo(string $no)
    {
        $res = $this->request('v3/ecommerce/applyments/out-request-no/' . $no, 'GET');
        if (isset($res['code'])) {
            throw new ValidateException('[微信接口返回]:' . $res['message']);
        }
        return $res;
    }

    /**
     * 修改结算账号
     * POST /v3/apply4sub/sub_merchants/{sub_mchid}/modify-settlement
     * @param string $mchid
     * @param array $params
     * @return mixed
     */
    public function updateSubMerchant(string $mchid, array $params)
    {
        $res = $this->request(
            "v3/apply4sub/sub_merchants/{$mchid}/modify-settlement",
            'POST',
            ['body' => json_encode($params, JSON_UNESCAPED_UNICODE)],
            true
        );
        if (isset($res['code'])) {
            throw new ValidateException('[微信接口返回]:' . $res['message']);
        }
        return $res;
    }

    /**
     * 查询结算账户
     * GET /v3/apply4sub/sub_merchants/{sub_mchid}/settlement
     * @param string $mchid
     * @return mixed
     */
    public function getSubMerchant(string $mchid)
    {
        $res = $this->request("v3/apply4sub/sub_merchants/{$mchid}/settlement", 'GET');
        if (isset($res['code'])) {
            throw new ValidateException('[微信接口返回]:' . $res['message']);
        }
        return $res;
    }

    /**
     * 添加分账接收方
     * POST /v3/ecommerce/profitsharing/receivers/add
     * @param array $params
     * @return mixed
     */
    public function profitsharingAdd(array $params)
    {
        $params['appid'] = $this->serviceConfig->wechatAppid ?: $this->serviceConfig->routineAppid;
        $res = $this->request('v3/ecommerce/profitsharing/receivers/add', 'POST', [
            'body' => json_encode($params, JSON_UNESCAPED_UNICODE)
        ], true);
        if (isset($res['code'])) {
            throw new ValidateException('[微信接口返回]:' . $res['message']);
        }
        return $res;
    }

    /**
     * 删除分账接收方
     * POST /v3/ecommerce/profitsharing/receivers/delete
     * @param array $params
     * @return mixed
     */
    public function profitsharingDel(array $params)
    {
        $params['appid'] = $this->serviceConfig->wechatAppid ?: $this->serviceConfig->routineAppid;
        $res = $this->request('v3/ecommerce/profitsharing/receivers/delete', 'POST', [
            'body' => json_encode($params, JSON_UNESCAPED_UNICODE)
        ], true);
        if (isset($res['code'])) {
            throw new ValidateException('[微信接口返回]:' . $res['message']);
        }
        return $res;
    }

    /**
     * 上传图片
     * POST /v3/merchant/media/upload
     * @param string $filepath  本地文件路径
     * @param string $filename  文件名（含扩展名）
     * @return mixed
     */
    public function upload(string $filepath, string $filename)
    {
        if (!is_file($filepath) || !is_readable($filepath)) {
            throw new ValidateException('上传文件不存在或不可读');
        }

        $boundary = uniqid();
        $file = file_get_contents($filepath);
        if ($file === false) {
            throw new ValidateException('上传文件读取失败');
        }

        $signBody = json_encode(['filename' => $filename, 'sha256' => hash_file('sha256', $filepath)]);
        $boundaryStr = "--{$boundary}\r\n";
        $fileMime = $this->resolveFileMimeType($filepath, 'image/jpeg');

        $body  = $boundaryStr;
        $body .= 'Content-Disposition: form-data; name="meta"' . "\r\n";
        $body .= 'Content-Type: application/json' . "\r\n";
        $body .= "\r\n";
        $body .= $signBody . "\r\n";
        $body .= $boundaryStr;
        $body .= 'Content-Disposition: form-data; name="file"; filename="' . $filename . '"' . "\r\n";
        $body .= 'Content-Type: ' . $fileMime . "\r\n";
        $body .= "\r\n";
        $body .= $file . "\r\n";
        $body .= "--{$boundary}--";

        $options = [
            'headers' => ['Content-Type' => 'multipart/form-data;boundary=' . $boundary],
            'sign_body' => $signBody,
            'data' => $body,
        ];

        try {
            $res = $this->request('v3/merchant/media/upload', 'POST', $options, true);
        } catch (\Exception $exception) {
            throw new ValidateException($exception->getMessage());
        }

        if (isset($res['code'])) {
            throw new ValidateException('[微信接口返回]:' . $res['message']);
        }
        return $res;
    }

    /**
     * 上传视频
     * POST /v3/merchant/media/video_upload
     * @param string $filepath  本地文件路径
     * @param string $filename  文件名（含扩展名）
     * @return mixed
     */
    public function videoUpload(string $filepath, string $filename)
    {
        if (!is_file($filepath) || !is_readable($filepath)) {
            throw new ValidateException('上传文件不存在或不可读');
        }

        $boundary = uniqid();
        $file = file_get_contents($filepath);
        if ($file === false) {
            throw new ValidateException('上传文件读取失败');
        }

        $signBody = json_encode(['filename' => $filename, 'sha256' => hash_file('sha256', $filepath)]);
        $boundaryStr = "--{$boundary}\r\n";
        $fileMime = $this->resolveFileMimeType($filepath, 'video/mp4');

        $body  = $boundaryStr;
        $body .= 'Content-Disposition: form-data; name="meta"' . "\r\n";
        $body .= 'Content-Type: application/json' . "\r\n";
        $body .= "\r\n";
        $body .= $signBody . "\r\n";
        $body .= $boundaryStr;
        $body .= 'Content-Disposition: form-data; name="file"; filename="' . $filename . '"' . "\r\n";
        $body .= 'Content-Type: ' . $fileMime . "\r\n";
        $body .= "\r\n";
        $body .= $file . "\r\n";
        $body .= "--{$boundary}--";

        $options = [
            'headers' => ['Content-Type' => 'multipart/form-data;boundary=' . $boundary],
            'sign_body' => $signBody,
            'data' => $body,
        ];

        try {
            $res = $this->request('v3/merchant/media/video_upload', 'POST', $options, true);
        } catch (\Exception $exception) {
            throw new ValidateException($exception->getMessage());
        }

        if (isset($res['code'])) {
            throw new ValidateException('[微信接口返回]:' . $res['message']);
        }
        return $res;
    }

    /**
     * 获取文件 MIME 类型
     * @param string $filepath
     * @param string $default
     * @return string
     */
    protected function resolveFileMimeType(string $filepath, string $default = 'application/octet-stream'): string
    {
        $mime = function_exists('mime_content_type') ? mime_content_type($filepath) : false;
        return $mime ?: $default;
    }
}
