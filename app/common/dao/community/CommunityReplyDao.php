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


namespace app\common\dao\community;


use app\common\dao\BaseDao;
use app\common\model\community\CommunityReply;

class CommunityReplyDao extends BaseDao
{

    protected function getModel(): string
    {
        return CommunityReply::class;
    }

    /**
     * 查询用户的帖子回复
     * @param int $id
     * @param int $uid
     * @return mixed
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/12
     */
    public function uidExists(int $id, int $uid)
    {
        return $this->getModel()::getDb()->where($this->getPk(), $id)->where('uid', $uid)->where('is_del', 0)->find();
    }

    /**
     * 查询帖子回复
     * @param array $where
     * @return \think\db\BaseQuery
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/12
     */
    public function search(array $where)
    {
        $query = CommunityReply::hasWhere('author',function($query) use($where) {
            $query->when(isset($where['username']) && $where['username'] !== '', function ($query) use($where) {
                $query->whereLike('nickname',"%{$where['username']}%");
            });
            $query->where(true);
        });

        $query->when(isset($where['keyword']) && $where['keyword'] !== '', function ($query) use($where) {
            $query->whereLike('CommunityReply.content',"%{$where['keyword']}%");
        });
        $query->when(isset($where['date']) && $where['date'] !== '', function ($query) use($where) {
            getModelTime($query, $where['date'], 'CommunityReply.create_time');
        });
        $query->when(isset($where['is_del']) && $where['is_del'] !== '', function ($query) use($where) {
           $query->where('CommunityReply.is_del',$where['is_del']);
        });
        $query->when(isset($where['status']) && $where['status'] !== '', function ($query) use($where) {
           $query->where('CommunityReply.status',$where['status']);
        });

        $query->when(isset($where['pid']) && $where['pid'] !== '', function ($query) use($where) {
            $query->where('CommunityReply.pid',$where['pid']);
        });

        $query->when(isset($where['community_id']) && $where['community_id'] !== '', function ($query) use($where) {
            $query->where('CommunityReply.community_id',$where['community_id']);
        });
        return $query->order('create_time DESC');
    }
}
