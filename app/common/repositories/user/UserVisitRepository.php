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


namespace app\common\repositories\user;


use app\common\dao\user\UserVisitDao;
use app\common\repositories\BaseRepository;

/**
 * Class UserVisitRepository
 * @package app\common\repositories\user
 * @author xaboy
 * @day 2020/5/27
 * @mixin UserVisitDao
 */
class UserVisitRepository extends BaseRepository
{
    /**
     * @var UserVisitDao
     */
    protected $dao;

    /**
     * UserVisitRepository constructor.
     * @param UserVisitDao $dao
     */
    public function __construct(UserVisitDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取用户推荐产品的类别ID列表
     *
     * 本函数旨在查询并返回与指定用户相关的产品推荐类别ID列表。通过查询推荐表中与用户UID相关且类型为产品的推荐项，
     * 限制返回结果的数量为7条，并进一步获取每项产品的类别ID。此列表可用于显示用户个性化的推荐产品类别。
     *
     * @param int|null $uid 用户ID。可选参数，如果未指定，则默认查询所有推荐产品的类别ID列表。
     * @return array 返回包含产品类别ID的数组。如果查询结果为空或非数组，则返回空数组。
     */
    public function getRecommend(?int $uid)
    {
        // 根据用户ID和类型为'product'的条件查询推荐数据，并关联产品信息，限制返回结果数量为7
        $data = $this->dao->search(['uid' => $uid, 'type' => 'product'])->with(['product' => function ($query) {
            // 选择产品ID和类别ID字段
            $query->field('product_id,cate_id');
        }])->limit(7)->select();

        // 初始化用于存储类别ID的数组
        $i = [];
        // 如果查询结果是数组，则遍历结果，提取每条推荐产品的类别ID
        if (is_array($data)) {
            foreach ($data as $item) {
                // 将产品类别ID添加到类别ID数组中
                $i[] = $item['product']['cate_id'];
            }
        }
        // 返回包含产品类别ID的数组
        return $i;
    }

    /**
     * 获取用户产品浏览历史记录
     *
     * 本函数用于根据用户ID和分页信息，从数据库中检索该用户的产品浏览历史记录。
     * 它首先构造一个查询条件为用户ID和类型为产品的查询，然后计算符合条件的记录总数，
     * 最后根据指定的页码和每页记录数来获取记录列表。
     *
     * @param int $uid 用户ID，用于指定要查询哪个用户的浏览历史。
     * @param int $page 当前页码，用于分页查询。
     * @param int $limit 每页的记录数，用于分页查询。
     * @return array 返回一个包含浏览历史记录总数和列表的数组。
     */
    public function getHistory($uid,$page, $limit)
    {
        // 根据用户ID和类型为产品搜索浏览历史记录
        $query = $this->dao->search(['uid' => $uid, 'type' => 'product']);

        // 伴随查询产品信息，只获取指定的字段以减少数据量
        $query->with(['product'=>function($query){
            $query->field('product_id,image,store_name,slider_image,price,is_show,status,sales');
        }]);

        // 计算符合条件的浏览历史记录总数
        $count = $query->count();

        // 根据当前页码和每页记录数获取浏览历史记录列表
        $list = $query->page($page,$limit)->select();

        // 将记录总数和列表一起返回
        return compact('count','list');
    }

    /**
     * 获取搜索日志列表
     *
     * 根据给定的条件数组 $where，分页获取搜索日志，并包含用户信息。
     * 这个方法主要用于支持对搜索行为的数据统计和查询。
     *
     * @param array $where 搜索条件数组，用于精确匹配搜索日志。
     * @param int $page 当前页码，用于分页查询。
     * @param int $limit 每页数据条数，用于分页查询。
     * @return array 返回包含搜索日志总数和列表的数据数组。
     */
    public function getSearchLog(array $where, $page, $limit)
    {
        // 初始化搜索查询
        $query = $this->dao->search($where);

        // 关联加载用户信息，只获取指定字段以减少数据量
        $query->with(['user' => function ($query) {
            $query->field('uid,nickname,avatar,user_type');
        }]);

        // 计算满足条件的搜索日志总数
        $count = $query->count();

        // 分页查询搜索日志，并按创建时间降序排序
        $list = $query->page($page, $limit)->order('create_time DESC')->select();

        // 返回搜索日志总数和列表的组合
        return compact('count', 'list');
    }
    /**
     * 清除搜索日志
     *
     * @return void
     */
    public function clearSearchLog(array $where)
    {
        return $this->dao->clearSearchLog($where);
    }

    /**
     * 获取热门列表
     * 本函数旨在根据用户访问记录，统计前一天访问量最高的前10个产品或内容。
     * 如果前一天的访问数据不足，将返回最近访问量排名前100的内容。
     *
     * @return array 热门内容列表，包含前10个访问量最高的内容。
     */
    public function getHotList()
    {
        // 定义查询条件，特定类型为'searchProduct'
        $where['type'] = ['searchProduct'];

        // 查询前一天访问量最高的内容
        $data = app()->make(UserVisitRepository::class)
            ->search($where)
            ->whereDay('UserVisit.create_time', date('Y-m-d', strtotime("-1 day")))->column('content');

        // 如果前一天的数据为空，则查询历史访问量最高的100个内容
        if (empty($data)) {
            $data = app()->make(UserVisitRepository::class)
                ->search($where)->limit(100)->column('content');
        }

        // 统计每个内容出现的次数，按访问量降序排列
        $data = array_count_values($data);
        arsort($data);

        // 取出前10个访问量最高的内容
        $data = array_keys($data);
        $data = array_slice($data, 0, 10);

        return $data;
    }


}
