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

namespace crmeb\services\product\storage;

use crmeb\services\BaseProduct;

/**
 * Class Copy
 * @package crmeb\services\product\storage
 */
class Copy extends BaseProduct
{

    /**
     * 是否开通
     */
    const PRODUCT_OPEN = 'copy/open';
    /**
     * 获取详情
     */
    const PRODUCT_GOODS = 'copy/goods';

    const GET_USER_INFO = 'user/info';

    /** 初始化
     * @param array $config
     */
    protected function initialize(array $config = [])
    {
        parent::initialize($config);
    }

    /** 是否开通复制
     * @return mixed
     */
    public function open()
    {
        return $this->accessToken->httpRequest(self::PRODUCT_OPEN, []);
    }

    /** 复制商品
     * @return mixed
     */
    public function goods(string $url)
    {
        $param['url'] = $url;
        return $this->accessToken->httpRequest(self::PRODUCT_GOODS, $param);
    }

    /**
     *  获取一号通信息
     * @return array|mixed
     * @author Qinii
     */
    public function getUserInfo()
    {
        return $this->accessToken->httpRequest(self::GET_USER_INFO);
    }

}
