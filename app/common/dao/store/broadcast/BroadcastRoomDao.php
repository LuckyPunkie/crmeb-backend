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
use app\common\model\store\broadcast\BroadcastRoom;
use app\common\repositories\system\merchant\MerchantRepository;
use think\db\BaseQuery;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Model;

/**
 * Class BroadcastRoomDao
 * @package app\common\dao\store\broadcast
 * @author xaboy
 * @day 2020/7/29
 */
class BroadcastRoomDao extends BaseDao
{

    /**
     * @return string
     * @author xaboy
     * @day 2020/7/29
     */
    protected function getModel(): string
    {
        return BroadcastRoom::class;
    }

    /**
     * 删除直播间
     * @param int $id
     * @return int
     * @throws DbException
     * @author xaboy
     * @day 2020/7/30
     */
    public function delete(int $id)
    {
        return $this->update($id, ['is_del' => 1]);
    }

    /**
     * 直播间是否存在
     * @param int $id
     * @return bool
     * @author xaboy
     * @day 2020/7/30
     */
    public function exists(int $id)
    {
        return $this->existsWhere(['broadcast_room_id' => $id, 'is_del' => 0]);
    }

    /**
     * 商户直播间是否存在
     * @param int $id
     * @param int $merId
     * @return bool
     * @author xaboy
     * @day 2020/7/30
     */
    public function merExists(int $id, int $merId)
    {
        return $this->existsWhere(['broadcast_room_id' => $id, 'is_del' => 0, 'is_mer_del' => 0, 'mer_id' => $merId]);
    }

    /**
     * 删除直播间
     * @param int $id
     * @return int
     * @throws DbException
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/13
     */
    public function merDelete(int $id)
    {
        return $this->update($id, ['is_mer_del' => 1]);
    }

    /**
     * 查询直播间
     * @param array $where
     * @return BaseQuery
     * @author xaboy
     * @day 2020/7/30
     */
    public function search(array $where)
    {
        if(isset($where['is_trader']) && $where['is_trader'] !== ''){
            $query = BroadcastRoom::hasWhere('merchant',function($query)use($where){
                $query->where('Merchant.is_trader',$where['is_trader']);
            });
        }else{
            $query = BroadcastRoom::getDB()->alias('BroadcastRoom');
        }
        $query->when(isset($where['keyword']) && $where['keyword'] !== '', function ($query) use ($where) {
            $query->whereLike('room_id|name|anchor_name|anchor_wechat|broadcast_room_id', "%{$where['keyword']}%");
        })->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
            $query->where('mer_id', $where['mer_id']);
        })->when(isset($where['live_status']) && $where['live_status'] !== '', function ($query) use ($where) {
            $query->where('live_status', $where['live_status']);
        })->when(isset($where['star']) && $where['star'] !== '', function ($query) use ($where) {
            $query->where('star', $where['star']);
        })->when(isset($where['show_tag']) && $where['show_tag'] !== '', function ($query) use ($where) {
            $query->where('is_show', 1)->where('is_mer_show', 1)->where('status', 2);
        })->when(isset($where['hot']) && $where['hot'] !== '', function ($query) use ($where) {
            $query->order('live_status ASC,star DESC,sort DESC');
        })->when(isset($where['broadcast_room_id']) && $where['broadcast_room_id'] !== '', function ($query) use ($where) {
            $query->where('broadcast_room_id', $where['broadcast_room_id']);
        })->when(isset($where['status_tag']) && $where['status_tag'] !== '', function ($query) use ($where) {
            if ($where['status_tag'] == 1) {
                $query->where('status', 2);
            } else if ($where['status_tag'] == -1) {
                $query->where('status', -1);
            } else if ($where['status_tag'] == 0) {
                $query->whereIn('status', [0, 1]);
            }
        })->when(isset($where['show_type']) && $where['show_type'] !== '', function ($query) use ($where) {
            if ($where['show_type'] == 3) {
                $query->where('is_mer_show', 1)->where('is_show', 1);
            } else if ($where['show_type'] == 2) {
                $query->where('is_mer_show', 0)->where('is_show', 1);
            } else if ($where['show_type'] == 1) {
                $query->where('is_mer_show', 1)->where('is_show', 0);
            } else if ($where['show_type'] == 0) {
                $query->where('is_mer_show', 0)->where('is_show', 0);
            }
        })->where('is_del', 0)->where('is_mer_del', 0);

        return $query;
    }

    /**
     * 验证直播间
     * @param $roomId
     * @param $merId
     * @return array|Model|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020/7/31
     */
    public function validRoom($roomId, $merId)
    {
        return BroadcastRoom::getDB()->where('broadcast_room_id', $roomId)->where('mer_id', $merId)->where('status', 2)->where('is_show', 1)->find();
    }

    /**
     * 获取直播间状态
     * @param array $roomIds
     * @return array
     * @author xaboy
     * @day 2020/7/31
     */
    public function getRooms(array $roomIds)
    {
        return BroadcastRoom::getDB()->whereIn('room_id', $roomIds)->column('live_status,broadcast_room_id', 'room_id');
    }
}
