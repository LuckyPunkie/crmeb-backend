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

use think\exception\ValidateException;
use app\common\repositories\BaseRepository;
use app\common\model\store\order\StoreOrder;
use app\common\repositories\store\order\StoreOrderProductRepository;
use app\common\dao\store\product\ProductAttrValueReservationDao as dao;

class ProductAttrValueReservationRepository extends BaseRepository
{

    protected $dao;

    public function __construct(dao $dao)
    {
        $this->dao = $dao;
    }
    /**
     * 修改预约商品库存
     *
     * @param array $data
     * @return void
     */
    public function updateAttrReservations(array $data)
    {
        foreach ($data as $item) {
            $reservation = $this->dao->get($item['attr_reservation_id']);
            if (!$reservation) {
                throw new ValidateException('预约库存信息不存在,请检查');
            }

            $reservation->update(['stock' => $item['stock']], ['attr_reservation_id' => $item['attr_reservation_id']]);
        }
    }

    /**
     *  检测预约商品某个日期的某个时间段库存是否足够
     * @param $product_id
     * @param $reservation_id
     * @param $date
     * @param $num
     * @return bool
     * @author Qinii
     */
    public function validateStock($product_id ,$reservation_id, $date, $num)
    {
        $product = app()->make(ProductReservationRepository::class)->getSearch(['product_id' =>  $product_id])->find();
        if (!$product) throw new ValidateException('数据不存在');
        $day = date('Y-m-d',time());
        if ($date < date('Y-m-d',time() || $product['show_reservation_days'] > ($date - $day)))
            throw new ValidateException('超出可预约时间');

        $reservation = $this->dao->get($reservation_id);
        if (!$reservation) throw new ValidateException('预约库存信息不存在,请检查');

        //如果需提前，计算时间与当前时间差
        if ($product['is_advance'] == ProductReservationRepository::ADVANCE_TYPE_ALLOW) {
            $advance_time = $product['advance_time'] * 60 * 60;
            $time = strtotime($date . ' ' . $reservation['start_time']);
            if ($time - time() < $advance_time)
                throw new ValidateException('超出提前可预约时间，请刷新重试');
        }

        $orderProductRepository = app()->make(StoreOrderProductRepository::class);
        $_stock = (int)$orderProductRepository->getReservationSum($product_id, $date, $reservation_id);
        $stock = $reservation['stock'] - $_stock;
        return  $stock >= $num ? $stock : false;
    }
}
