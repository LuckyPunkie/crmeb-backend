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


namespace app\common\dao\store\broadcast;


use app\common\dao\BaseDao;
use app\common\model\BaseModel;
use app\common\model\store\broadcast\BroadcastRoomGoods;
use app\common\repositories\store\order\StoreCartRepository;

class BroadcastRoomGoodsDao extends BaseDao
{

    protected function getModel(): string
    {
        return BroadcastRoomGoods::class;
    }

    /**
     * 清除直播间商品
     * @param $id
     * @return int
     * @throws \think\db\exception\DbException
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/13
     */
    public function clear($id)
    {
        return BroadcastRoomGoods::getDB()->where('broadcast_room_id', $id)->delete();
    }

    /**
     * 获取直播间的所有商品id
     * @param $id
     * @return array
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/13
     */
    public function goodsId($id)
    {
        return BroadcastRoomGoods::getDB()->where('broadcast_room_id', $id)->column('broadcast_goods_id');
    }

    /**
     * 删除单个商品
     * @param $goodsId
     * @param $roomId
     * @return int
     * @throws \think\db\exception\DbException
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/13
     */
    public function rmGoods($goodsId, $roomId)
    {
        return BroadcastRoomGoods::getDB()->where('broadcast_room_id', $roomId)->where('broadcast_goods_id', $goodsId)->delete();
    }

    /**
     * 获取直播间商品列表
     * @param $roomId
     * @param $page
     * @param $limit
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/13
     */
    public function getGoodsList($roomId, $page, $limit)
    {
        $query = BroadcastRoomGoods::getDB()->where('broadcast_room_id', $roomId);
        $count = $query->count();
        $list = $query->page($page, $limit)->with('goods.product')->select()->toArray();
        $ids = array_column($list, 'broadcast_goods_id');
        if (count($ids)) {
            $sourcePayInfo = app()->make(StoreCartRepository::class)->getSourcePayInfo(1, $ids);
            $data = [];
            foreach ($sourcePayInfo as $item) {
                $data[$item['source_id']] = $item;
            }
            foreach ($list as $k => $goods) {
                $list[$k]['goods']['pay_num'] = $data[$goods['broadcast_goods_id']]['pay_num'] ?? 0;
                $list[$k]['goods']['pay_price'] = $data[$goods['broadcast_goods_id']]['pay_price'] ?? 0;
            }
        }
        return compact('list', 'count');
    }

    /**
     * 删除商品
     * @param $goodsId
     * @return int
     * @throws \think\db\exception\DbException
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/13
     */
    public function deleteGoods($goodsId)
    {
        return BroadcastRoomGoods::getDB()->where('broadcast_goods_id', $goodsId)->delete();
    }
}
