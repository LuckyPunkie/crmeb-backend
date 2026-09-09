<?php

namespace crmeb\services\taoke;

use crmeb\basic\BaseServices;
use GuzzleHttp\Client;
use think\facade\Log;

/**
 * 淘宝联盟（淘宝客）官方直连服务
 * 网关: https://eco.taobao.com/router/rest
 * 签名: MD5( appSecret + ksort(拼k v k v ...) + appSecret ) 大写
 * 文档: https://open.taobao.com/api.htm
 */
class TaobaoOfficialService extends BaseServices
{
    protected $apiUrl;
    protected $appKey;
    protected $appSecret;
    protected $adzoneId;
    protected $pid;

    public function __construct()
    {
        parent::__construct('taobao', [], 'taoke');
        $this->setHttpClient(new Client([
            'timeout' => 30,
            'connect_timeout' => 8,
            'verify' => false,
        ]));
        $this->apiUrl    = 'https://eco.taobao.com/router/rest';
        $this->appKey    = (string) config('taoke.taobao.appkey');
        $this->appSecret = (string) config('taoke.taobao.appsecret');
        $this->pid       = (string) config('taoke.taobao.pid');
        $this->adzoneId  = (string) config('taoke.taobao.adzone_id');

        // pid 格式 mm_uid_siteid_adzoneid，若 adzone 未单独配则从 pid 拆出
        if ($this->adzoneId === '' && $this->pid !== '') {
            $parts = explode('_', $this->pid);
            if (count($parts) >= 4) {
                $this->adzoneId = $parts[3];
            }
        }
    }

    /**
     * 商品简版详情 (SDK 里存在: TbkItemInfoGetRequest)
     * method: taobao.tbk.item.info.get
     */
    public function itemInfo(string $numIids): array
    {
        return $this->call('taobao.tbk.item.info.get', [
            'num_iids'  => $numIids,
            'platform'  => '2',
        ]);
    }

    /**
     * 淘口令 (SDK 里存在: TbkTpwdCreateRequest)
     * method: taobao.tbk.tpwd.create
     */
    public function tpwdCreate(string $text, string $url, string $logo = ''): array
    {
        $params = [
            'text' => $text,
            'url'  => $url,
        ];
        if ($logo !== '') $params['logo'] = $logo;
        return $this->call('taobao.tbk.tpwd.create', $params);
    }

    /**
     * 物料库/官方推荐 (SDK 里存在: TbkDgOptimusMaterialRequest)
     * method: taobao.tbk.dg.optimus.material
     * material_id 常用值: 6708 猜你喜欢 / 3756 精选 / 6707 淘抢购
     */
    public function optimusMaterial(int $materialId = 3756, int $page = 1, int $pageSize = 20): array
    {
        return $this->call('taobao.tbk.dg.optimus.material', [
            'material_id' => $materialId,
            'page_no'     => $page,
            'page_size'   => $pageSize,
            'adzone_id'   => $this->adzoneId,
        ]);
    }

    /**
     * 关键词搜索 (SDK 里疑似缺失: TbkDgMaterialOptionalRequest)
     * method: taobao.tbk.dg.material.optional
     * 用来验证：如果这个能通，说明只是 SDK 生成旧了；不通就是淘客权限没申请
     */
    public function materialOptional(string $q, int $page = 1, int $pageSize = 20): array
    {
        return $this->call('taobao.tbk.dg.material.optional', [
            'q'          => $q,
            'page_no'    => $page,
            'page_size'  => $pageSize,
            'adzone_id'  => $this->adzoneId,
            'platform'   => '2',
        ]);
    }

    /**
     * 高佣转链 (SDK 里疑似缺失: TbkDgMaterialOptionalRequest 也能返回但独立方法更明确)
     * method: taobao.tbk.privilege.get
     */
    public function privilegeGet(string $numIid, string $adzoneId = ''): array
    {
        return $this->call('taobao.tbk.privilege.get', [
            'item_id'   => $numIid,
            'adzone_id' => $adzoneId ?: $this->adzoneId,
            'platform'  => '2',
        ]);
    }

    /**
     * 订单查询
     * method: taobao.tbk.order.details.get
     */
    public function orderDetailsGet(string $startTime, string $endTime, int $span = 600, int $page = 1, int $pageSize = 100, int $queryType = 1): array
    {
        return $this->call('taobao.tbk.order.details.get', [
            'start_time' => $startTime,
            'end_time'   => $endTime,
            'span'       => $span,
            'page_no'    => $page,
            'page_size'  => $pageSize,
            'query_type' => $queryType,
        ]);
    }

    // ==================== 底层 ====================

    protected function call(string $method, array $bizData): array
    {
        if ($this->appKey === '' || $this->appSecret === '') {
            throw new \think\exception\ValidateException('淘宝联盟 appKey/appSecret 未配置');
        }

        $sysParams = [
            'method'      => $method,
            'app_key'     => $this->appKey,
            'timestamp'   => date('Y-m-d H:i:s'),
            'format'      => 'json',
            'v'           => '2.0',
            'sign_method' => 'md5',
        ];
        $allParams = array_merge($sysParams, $bizData);
        $allParams['sign'] = $this->sign($allParams);

        try {
            $response = $this->httpClient->post($this->apiUrl, [
                'form_params' => $allParams,
            ]);
            $raw = (string) $response->getBody();
            $result = json_decode($raw, true);
            if (!is_array($result)) {
                Log::error('淘宝联盟API响应解析失败', ['method' => $method, 'raw' => $raw]);
                return [];
            }
            if (isset($result['error_response'])) {
                Log::warning('淘宝联盟API业务错误', [
                    'method' => $method,
                    'error'  => $result['error_response'],
                ]);
            }
            return $result;
        } catch (\Throwable $e) {
            Log::error('淘宝联盟API请求异常', [
                'method' => $method,
                'msg'    => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * 淘宝 TOP 签名: MD5( appSecret + ksort(拼k v k v ...) + appSecret ), 大写
     * 排除 sign 本身和 @开头的文件参数
     */
    protected function sign(array $params): string
    {
        ksort($params);
        $str = $this->appSecret;
        foreach ($params as $k => $v) {
            if ($k === 'sign') continue;
            if (is_array($v)) continue;
            if (strlen((string)$v) > 0 && substr((string)$v, 0, 1) === '@') continue;
            $str .= $k . $v;
        }
        $str .= $this->appSecret;
        return strtoupper(md5($str));
    }
}
