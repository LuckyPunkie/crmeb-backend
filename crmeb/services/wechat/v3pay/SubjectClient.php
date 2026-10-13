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
 * 特约商户申请 / 银行列表查询
 * 对应旧版 easywechat/subject/Client
 * Class SubjectClient
 */
class SubjectClient extends BaseClient
{

    const CAPITALLHH_BANKS  = 'v3/capital/capitallhh/banks/';
    const AREAS_PROVINCES   = 'v3/capital/capitallhh/areas/provinces';
    const SUBJECT_APPLYMENT = 'v3/applyment4sub/applyment/';

    public function __construct(V3PaymentConfig $config, ServicePaymentConfig $serviceConfig)
    {
        parent::__construct($config, $serviceConfig);
        $this->isServicePay = true;
    }

    /**
     * 特约商户申请
     * POST /v3/applyment4sub/applyment/
     * @param array $params
     * @return mixed
     */
    public function submitApplication(array $params)
    {
        $params = $this->processParams($params);
        $res = $this->request(self::SUBJECT_APPLYMENT, 'POST', [
            'body' => json_encode($params, JSON_UNESCAPED_UNICODE)
        ], true);
        if (isset($res['code'])) {
            throw new ValidateException('[微信接口返回]:' . $res['message']);
        }
        return $res;
    }

    /**
     * 申请单ID查询申请状态
     * GET /v3/applyment4sub/applyment/applyment_id/{applyment_id}
     * @param string|int $id
     * @return mixed
     */
    public function getApplicationById($id)
    {
        $res = $this->request('/v3/applyment4sub/applyment/applyment_id/' . $id, 'GET');
        if (isset($res['code'])) {
            throw new ValidateException('[微信接口返回]:' . $res['message']);
        }
        return $res;
    }

    /**
     * 业务申请编号查询申请状态
     * GET /v3/applyment4sub/applyment/business_code/{business_code}
     * @param string $no
     * @return mixed
     */
    public function getApplicationByNo(string $no)
    {
        $res = $this->request('v3/applyment4sub/applyment/business_code/' . $no, 'GET');
        if (isset($res['code'])) {
            throw new ValidateException('[微信接口返回]:' . $res['message']);
        }
        return $res;
    }

    /**
     * 查询支持个人业务/企业业务的银行列表
     * GET /v3/capital/capitallhh/banks/{bank_type}?offset=&limit=
     * @param int $type  0=personal-banking  1=corporate-banking
     * @param int $offset
     * @param int $limit
     * @return mixed
     */
    public function getBanks(int $type, int $offset, int $limit = 100)
    {
        $typeMap = ['personal-banking', 'corporate-banking'];
        $url = self::CAPITALLHH_BANKS . $typeMap[$type] . '?offset=' . $offset . '&limit=' . $limit;
        $res = $this->request($url, 'GET', [], true);
        if (isset($res['code'])) {
            throw new ValidateException('[微信接口返回]:' . $res['message']);
        }
        return $res;
    }

    /**
     * 查询支行列表
     * GET /v3/capital/capitallhh/banks/{bank_alias_code}/branches?city_code=&offset=&limit=
     * @param string $bankAliasCode
     * @param string $cityCode
     * @param int $offset
     * @param int $limit
     * @return mixed
     */
    public function branches(string $bankAliasCode, string $cityCode, int $offset = 0, int $limit = 100)
    {
        $url = 'v3/capital/capitallhh/banks/' . $bankAliasCode
            . '/branches?city_code=' . $cityCode
            . '&offset=' . $offset
            . '&limit=' . $limit;
        $res = $this->request($url, 'GET', [], true);
        if (isset($res['code'])) {
            throw new ValidateException('[微信接口返回]:' . $res['message']);
        }
        return $res;
    }

    /**
     * 查询省份列表或城市列表
     * GET /v3/capital/capitallhh/areas/provinces[/{province_code}/cities]
     * @param string $provinceCode  为空则查询省份列表，否则查询该省下的城市列表
     * @return mixed
     */
    public function areas(string $provinceCode = '')
    {
        if ($provinceCode) {
            $url = self::AREAS_PROVINCES . '/' . $provinceCode . '/cities';
        } else {
            $url = self::AREAS_PROVINCES;
        }
        $res = $this->request($url, 'GET', [], true);
        if (isset($res['code'])) {
            throw new ValidateException('[微信接口返回]:' . $res['message']);
        }
        return $res;
    }
}
