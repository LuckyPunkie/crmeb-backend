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


namespace app\common\repositories\system;


use app\common\dao\system\RelevanceDao;
use app\common\repositories\BaseRepository;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * @mixin RelevanceDao
 */
class RelevanceRepository extends BaseRepository
{

    //文章关联商品
    const TYPE_COMMUNITY_PRODUCT  =  'community_product';
    //社区关注
    const TYPE_COMMUNITY_FANS  =  'fans';
    //社区文章点赞
    const TYPE_COMMUNITY_START  =  'community_start';
    //社区评论点赞
    const TYPE_COMMUNITY_REPLY_START  =  'community_reply_start';
    //商户权限
    const TYPE_MERCHANT_AUTH = 'mer_auth';

    //指定范围类型
    //0全部商品
    const TYPE_ALL = 'scope_type';
    //指定商品
    const SCOPE_TYPE_PRODUCT = 'scope_type_product';
    //指定分类
    const SCOPE_TYPE_CATEGORY = 'scope_type_category';
    //指定商户
    const SCOPE_TYPE_STORE = 'scope_type_store';
    //指定商户标签
    const SCOPE_TYPE_PRODUCT_LABEL = 'scope_type_product_label';

    //价格说明关联分类
    const PRICE_RULE_CATEGORY = 'price_rule_category';

    //商品参数关联
    const PRODUCT_PARAMES_CATE = 'product_params_cate';
    //店铺关联分组
    const STORE_GROUP = 'store_group';


    //DIY默认模板适用范围 0. 指定店铺、1. 指定商户分类、2. 指定店铺类型、3. 指定商户类别
    const  MER_DIY_SCOPE = [
        'mer_diy_scope_0',
        'mer_diy_scope_1',
        'mer_diy_scope_2',
        'mer_diy_scope_3',
    ];

    protected $dao;
    /**
     * RelevanceRepository constructor.
     * @param RelevanceDao $dao
     */
    public function __construct(RelevanceDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 添加
     * @param int $leftId
     * @param int $rightId
     * @param string $type
     * @param $check
     * @return bool
     * @author Qinii
     * @day 10/28/21
     */
    public function create(int $leftId, int $rightId, string $type, bool $check = false)
    {
        if ($check && $this->checkHas($leftId, $rightId, $type))  {
            return false;
        }
        $data = [
            'left_id' => $leftId,
            'right_id'=> $rightId,
            'type'    => $type,
        ];
        try{
            $this->dao->create($data);
            return true;
        } catch (\Exception  $exception) {
            throw new ValidateException('创建失败');
        }
    }

    /**
     * 删除
     * @param int $leftId
     * @param string $type
     * @param int $rightId
     * @return bool
     * @author Qinii
     * @day 10/28/21
     */
    public function destory(int $leftId, int $rightId, string $type)
    {
        return $this->dao->getSearch([
            'left_id' => $leftId,
            'right_id'=> $rightId,
            'type'    => $type,
        ])->delete();
    }

    /**
     * 检测是否存在
     * @param int $leftId
     * @param int $rightId
     * @param string $type
     * @return int
     * @author Qinii
     * @day 10/28/21
     */
    public function checkHas(int $leftId, $rightId, string $type)
    {
        return $this->dao->getSearch([
            'left_id' => $leftId,
            'right_id'=> $rightId,
            'type'    => $type,
        ])->count();
    }

    /**
     * 根据左键批量删除
     * @param int $leftId
     * @param $type
     * @return bool
     * @author Qinii
     * @day 10/28/21
     */
    public function batchDelete(int $leftId, $type)
    {
        return $this->dao->getSearch([
            'left_id' => $leftId,
            'type'    => $type,
        ])->delete();
    }

    /**
     * 关注我的人
     * @param int $uid
     * @return array
     * @author Qinii
     * @day 10/28/21
     */
    public function getUserFans(int $uid, int $page, int $limit)
    {
        $query = $this->dao->getSearch([
            'right_id' => $uid,
            'type'    => self::TYPE_COMMUNITY_FANS,
        ])->with([
            'fans' => function($query) {
                $query->field('uid,avatar,nickname,count_fans,count_content');
            }
        ]);
        $count = $query->count();
        $list = $query->page($page, $limit)->select()->append(['is_start']);
        $data = [];
        foreach ($list as $item) {
            if (!$item['fans']) {
                $item->delete();
                $count -= 1;
            }
            $data[] = $item;
        }
        $list = $data;
        return compact('count','list');
    }

    /**
     * 我关注的人
     * @param $uid
     * @return array
     * @author Qinii
     * @day 10/28/21
     */
    public function getUserFocus(int $uid, int $page, int $limit)
    {
        $query = $this->dao->getSearch([
            'left_id' => $uid,
            'type'    => self::TYPE_COMMUNITY_FANS,
        ])->with([
            'focus' => function($query) {
                $query->field('uid,avatar,nickname,count_fans,count_content');
            }
        ]);
        $count = $query->count();
        $list = $query->page($page, $limit)->select()->append(['is_fans']);
        $data = [];
        foreach ($list as $item) {
            if (!$item['focus']) {
                $item->delete();
                $count -= 1;
            }
            $data[] = $item;
        }
        $list = $data;
        return compact('count','list');
    }


    /**
     * 我点赞过的文章
     * @param int $uid
     * @return \think\Collection
     * @author Qinii
     * @day 10/28/21
     */
    public function getUserStartCommunity(array $where, int $page, int $limit)
    {
        $query = $this->dao->joinUser($where)->with([
            'community'=> function($query) use($where){
                $query->with([
                    'author' => function($query){
                        $query->field('uid,real_name,status,avatar,nickname,count_start');
                    },
                    'is_start' => function($query) use ($where) {
                        $query->where('left_id',$where['uid']);
                    },
                    'topic' => function($query) {
                        $query->where('status', 1)->where('is_del',0);
                        $query->field('topic_id,topic_name,status,category_id,pic,is_del');
                    },
                    'relevance'  => [
                        'spu' => function($query) {
                            $query->field('spu_id,store_name,image,price,product_type,activity_id,product_id');
                        }
                    ],
                    'isFanss' => function($query) use($where){
                        $query->where('left_id',$where['uid']);
                    }]);
            },
        ]);
        $count = $query->count();
        $list = $query->page($page, $limit)->select()->each(function ($item){
            $item['time'] = date('m月d日', strtotime($item['create_time']));
            $item['isFans'] = $item['isFanss'];
            return $item;
        });

        return compact('count','list');
    }

    /**
     * 我点赞过的文章
     * @param int $uid
     * @return \think\Collection
     * @author Qinii
     * @day 10/28/21
     */
    public function getUserStartCommunityByVideos(array $where, int $page, int $limit)
    {
        $query = $this->dao->joinUser($where)->with([
            'community'=> function($query) {
                $query->with(['author'=> function($query) {
                    $query->field('uid,avatar,nickname');
                }]);
            },
        ]);
        $count = $query->count();
        $list = $query->page($page, $limit)->select()->each(function ($item){
            $item['time'] = date('m月d日', strtotime($item['create_time']));
            return $item;
        });

        return compact('count','list');
    }

    /**
     * 获取指定字段在特定类型中符合条件的记录数量
     *
     * 此方法通过调用DAO层的搜索功能，统计符合指定字段值和类型条件的记录数。
     * 主要用于在数据检索和分析场景中，快速获取特定字段在特定类型中的数据量。
     *
     * @param string $field 需要查询的字段名
     * @param int $value 字段的指定值
     * @param string $type 数据的类型
     * @return int 符合条件的记录数量
     */
    public function getFieldCount(string $field, int $value, string $type)
    {
        // 通过DAO层的搜索方法查询符合条件的记录，并返回其数量
        return $this->dao->getSearch([$field => $value, 'type'    => $type,])->count();
    }


    /**
     * 批量创建关联数据。
     * 该方法用于通过给定的左ID、一系列右ID和类型，创建多个关联数据记录。
     * 主要用于在具有多对多关系的数据库表中插入关联数据。
     *
     * @param int $leftId 左侧实体的ID，用于建立关联。
     * @param array $rightId 一个包含多个右侧实体ID的数组，这些ID将与左ID建立关联。
     * @param string $type 关联的类型或标签，用于区分不同类型的关联。
     */
    public function createMany(int $leftId, array $rightId, string $type)
    {
        // 当右ID不为空时，才进行关联数据的创建
        if (!empty($rightId)) {
            // 遍历右ID数组，为每个右ID创建关联数据数组
            foreach ($rightId as $value) {
                $res[] = [
                    'left_id' => $leftId,
                    'right_id' => $value,
                    'type' => $type,
                ];
            }
            // 将所有关联数据一次性插入到数据库中
            $this->dao->insertAll($res);
        }
    }
    /**
     * 获取指定左ID下所有关联的右ID。
     * 该方法用于根据给定的左ID和类型，查询所有与之关联的右ID。
     * 主要用于在多对多关系中，根据左侧实体ID获取所有相关的右侧实体ID。
     *
     * @param integer $leftId 左侧实体的ID，用于查询其关联的右ID。
     * @param string $type 关联的类型或标签，用于区分不同类型的关联。
     * @return array 一个包含所有关联右ID的数组。
     */
    public function getRightIds(int $leftId, string $type)
    {
        $where = [
            'left_id' => $leftId,
            'type' => $type,
        ];

        return $this->dao->getSearch($where)->column('right_id');
    }


    /**
     * 删除
     * @param int $leftId
     * @param string $type
     * @param int $rightId
     * @return bool
     * @author Qinii
     * @day 10/28/21
     */
    public function batchDeleteByRightId(int $rightId, string $type)
    {
        return $this->dao->getSearch([
            'right_id' => $rightId,
            'type'    => $type,
        ])->delete();
    }
}
