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
use app\common\model\community\Community;
use app\common\model\system\Relevance;
use app\common\repositories\system\RelevanceRepository;

class CommunityDao extends BaseDao
{

    /**
     * @return Community
     *
     * @date 2023/10/21
     * @author yyw
     */
    protected function getModel(): string
    {
        return Community::class;
    }

    /**
     * 搜索社区帖子
     * @param array $where
     * @return \think\db\BaseQuery
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/12
     */
    public function search(array $where)
    {
        $query = Community::hasWhere('author', function($query) use ($where){
            $query->when(isset($where['username']) && $where['username'] !==  '', function ($query) use($where) {
                $query->whereLike('real_name|phone|nickname',"%{$where['username']}%");
            })
                ->when(isset($where['phone']) && $where['phone'] !==  '', function ($query) use($where) {
                    $query->whereLike('phone',"%{$where['phone']}%");
                })
                ->when(isset($where['real_name']) && $where['real_name'] !==  '', function ($query) use($where) {
                    $query->whereLike('real_name',"%{$where['real_name']}%");
                })
                ->when(isset($where['nickname']) && $where['nickname'] !==  '', function ($query) use($where) {
                    $query->whereLike('nickname',"%{$where['nickname']}%");
                });
            $query->where(true);
        });
        $query->when(isset($where['search_type']) && $where['search_type'] !== '', function ($query) use ($where) {
                if(isset($where['keyword']) && $where['keyword'] !==  ''){
                    if($where['search_type'] == 'all'){
                        $query->whereLike('title|content|User.nickname',"%{$where['keyword']}%");
                    }
                    if($where['search_type'] == 'content'){
                        $query->whereLike('title|content',"%{$where['keyword']}%");
                    }
                    if($where['search_type'] == 'user'){
                        $query->whereLike('User.nickname',"%{$where['keyword']}%");
                    }
                }
            },function ($query) use ($where) {   // 兼容之前逻辑
                if(isset($where['keyword']) && $where['keyword'] !==  ''){
                    $query->whereLike('title',"%{$where['keyword']}%");
                }
            })
//            ->when(isset($where['keyword']) && $where['keyword'] !==  '', function ($query) use($where) {
//                $query->whereLike('title',"%{$where['keyword']}%");
//            })
            ->when(isset($where['uid']) && $where['uid'] !==  '', function ($query) use($where) {
                $query->where('uid',$where['uid']);
            })
            ->when(isset($where['uids']) && $where['uids'] !==  '', function ($query) use($where) {
                $query->whereIn('uid',$where['uids']);
            })
            ->when(isset($where['topic_id']) && $where['topic_id'] !==  '', function ($query) use($where) {
                $query->where('topic_id',$where['topic_id']);
            })
            ->when(isset($where['mer_id']) && $where['mer_id'] !==  '', function ($query) use($where) {
                $query->where('mer_id',$where['mer_id']);
            })
            ->when(isset($where['community_id']) && $where['community_id'] !==  '', function ($query) use($where) {
                $query->where('community_id',$where['community_id']);
            })
            ->when(isset($where['not_id']) && $where['not_id'] !==  '', function ($query) use($where) {
                $query->whereNotIn('community_id',$where['not_id']);
            })
            ->when(isset($where['in_id']) && $where['in_id'] !==  '', function ($query) use($where) {
                $query->whereOr(function($query) use($where){
                    $query->whereIn('community_id',$where['in_id']);
                });
            })
            ->when(isset($where['community_ids']) && $where['community_ids'] !==  '', function ($query) use($where) {
                $query->whereIn('community_id',$where['community_ids']);
            })
            ->when(isset($where['is_type']) && $where['is_type'] !==  '', function ($query) use($where) {
                $query->whereIn('is_type',$where['is_type']);
            })
            ->when(isset($where['community_type']) && $where['community_type'] !==  '', function ($query) use($where) {
                if (is_array($where['community_type'])) {
                    $query->whereIn('community_type', $where['community_type']);
                } else {
                    $query->where('community_type', $where['community_type']);
                }
            })
            ->when(isset($where['is_show']) && $where['is_show'] !==  '', function ($query) use($where) {
                $query->where('is_show',$where['is_show']);
            })
            ->when(isset($where['status']) && $where['status'] !==  '', function ($query) use($where) {
                $query->where('status',$where['status']);
            })
            ->when(isset($where['start']) && $where['start'] !==  '', function ($query) use($where) {
                $query->where('start',$where['start']);
            })
            ->when(isset($where['is_del']) && $where['is_del'] !==  '', function ($query) use($where) {
                $query->where('is_del',$where['is_del']);
            })
            ->when(isset($where['category_id']) && $where['category_id'] !==  '', function ($query) use($where) {
                $query->where('category_id',$where['category_id']);
            })
            ->when(isset($where['spu_id']) && $where['spu_id'] !==  '', function ($query) use($where) {
                $id = Relevance::where('right_id', $where['spu_id'])
                    ->where('type',RelevanceRepository::TYPE_COMMUNITY_PRODUCT)
                    ->column('left_id');
                $query->where('community_id','in', $id);
            });

        $order = 'create_time DESC';

        if (isset($where['order']) && $where['order'] == 'start') {
            $order = 'start DESC,Community.create_time DESC';
        }

        $query->order($order);
        return $query;
    }

    /**
     * 查询用户是否发过帖子
     * @param int $id
     * @param int $uid
     * @return bool
     * @throws \think\db\exception\DbException
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/12
     */
    public function uidExists(int $id, int $uid)
    {
        return $this->getModel()::getDb()->where('uid',$uid)->where($this->getPk(),$id)->count() > 0;
    }

    /**
     * id查询帖子是否存在
     * @param int $id
     * @return bool
     * @throws \think\db\exception\DbException
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/12
     */
    public function exists(int $id)
    {
        return $this->getModel()::getDb()->where('is_del',0)->where($this->getPk(),$id)->count() > 0;
    }

    /**
     * 删除某个用户的帖子
     * @param $uid
     * @return int
     * @throws \think\db\exception\DbException
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/12
     */
    public function destoryByUid($uid)
    {
        return $this->getModel()::getDb()->where('uid' ,$uid)->update(['is_del' =>  1]);
    }

    /**
     * 关联用户
     * @param $where
     * @return \think\db\BaseQuery
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/12
     */
    public function joinUser($where)
    {
        return Community::hasWhere('relevanceRight',function($query) use($where){
            $query->where('type',RelevanceRepository::TYPE_COMMUNITY_START)->where('left_id',$where['uid']);
        })
            ->when(isset($where['is_type']) && $where['is_type'] !==  '', function ($query) use($where) {
                $query->whereIn('Community.is_type',$where['is_type']);
            })
            ->when(isset($where['is_show']) && $where['is_show'] !==  '', function ($query) use($where) {
                $query->where('Community.is_show',$where['is_show']);
            })
            ->when(isset($where['status']) && $where['status'] !==  '', function ($query) use($where) {
                $query->where('Community.status',$where['status']);
            })
            ->when(isset($where['is_del']) && $where['is_del'] !==  '', function ($query) use($where) {
                $query->where('Community.is_del',$where['is_del']);
            });
    }

    /**
     * 统计每个用户的帖子数量
     * @return mixed
     *
     * @date 2023/10/21
     * @author yyw
     */
    public function getCountByGroupUid()
    {
        return $this->getModel()::getDb()->where('is_del', 0)->field('uid,count(community_id) as count')->group('uid')->select()->toArray();
    }

    public function isApprove(int $id)
    {
        return $this->getModel()::getDb()->where('is_del',0)->where('status',1)->where($this->getPk(),$id)->count() > 0;
    }
}
