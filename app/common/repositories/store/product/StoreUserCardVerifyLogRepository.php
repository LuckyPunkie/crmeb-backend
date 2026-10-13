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

namespace app\common\repositories\store\product;

use app\common\repositories\BaseRepository;
use app\common\dao\store\product\StoreUserCardVerifyLogDao;

class StoreUserCardVerifyLogRepository extends BaseRepository
{
    public function __construct(StoreUserCardVerifyLogDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取次卡/年卡核销流水列表。
     *
     * 核销记录来自 store_user_card_verify_log，每次核销都会记录本次核销次数、
     * 核销前剩余次数、核销后剩余次数、核销人和服务方式。
     * 这里保留简单分页结构，供订单维度或用户卡维度查看核销历史。
     *
     * @param array $where 支持 order_id、user_card_id、uid、mer_id、verify_type 等过滤条件。
     * @param int $page 页码。
     * @param int $limit 每页数量。
     * @return array{count:int,list:mixed}
     */
    public function getList(array $where, int $page, int $limit)
    {
        $query = $this->dao->search($where)->with([
            'orderInfo.orderProduct',
            'service' => function($query){
                $query->field('service_id,avatar,nickname,account');
            },
            'adminInfo' => function($query){
                $query->field('merchant_admin_id,account,real_name');
            },
            'merchant' => function($query){
                $query->field('mer_id,mer_name,mer_avatar,mer_address');
            }
        ]);
        $count = $query->count();
        $list = $query->page($page, $limit)->order('L.create_time DESC')->select();
        return compact('count', 'list');
    }
}
