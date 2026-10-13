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

use think\facade\Log;
use app\common\repositories\BaseRepository;
use app\common\dao\user\UserHistoryDao;
use app\common\repositories\store\product\SpuRepository;

class UserHistoryRepository extends BaseRepository
{

    protected $dao;

    /**
     * UserHistoryRepository constructor.
     * @param UserHistoryDao $dao
     */
    public function __construct(UserHistoryDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取API列表
     * 根据用户请求的页面、每页数量、用户ID和类型，查询特定用户的API调用历史。
     * 如果类型为1，还会额外加载SPU信息，并计算该用户的总历史记录数。
     *
     * @param int $page 当前页码
     * @param int $limit 每页数量
     * @param int $uid 用户ID
     * @param int $type 查询类型，1表示包含SPU信息
     * @return array 返回包含总数和列表数据的数组
     */
    public function getApiList($page,$limit,$uid,$type)
    {
        // 初始化包含关系数组
        $with = [];
        // 如果类型为1，查询时包含SPU信息，并计算用户的历史记录总数
        if($type == 1){
            $with = ['spu'];
            $count = app()->make(UserHistoryRepository::class)->userTotalHistory($uid);
        }
        // 根据用户ID和类型进行查询
        $query = $this->dao->search($uid,$type);
        // 设置查询的包含关系和排序方式
        $query->with($with)->order('update_time DESC');
        // 如果类型不为1，则计算查询结果的总数
        $count = $count ?? $query->count();
        // 分页查询数据
        $data = $query->page($page,$limit)->select();
        // 初始化最终返回的结果数组和列表数组
        $res = $list = [];
        // 遍历查询结果，按更新时间分组
        foreach ($data as $item) {
            if ($item['spu']) {
                $time = date('m月d日',strtotime($item['update_time']));
                $res[$time][] = $item;
            }
        }
        // 将分组后的数据转换为日期-列表的形式
        foreach ($res as $k => $v) {
            $list[] = ['date' => $k, 'list' => $v];
        }
        // 返回总数和列表数据
        return compact('count','list');
    }


    /**
     * 获取列表数据
     * 根据给定的页码和每页数量，以及用户ID和类型，获取对应的数据列表。
     * 如果类型为1，则同时获取SPU数据。
     *
     * @param int $page 当前页码
     * @param int $limit 每页数据数量
     * @param int $uid 用户ID
     * @param int $type 数据类型
     * @return array 包含数据总数和数据列表的数组
     */
    public function getList($page,$limit,$uid,$type)
    {
        // 初始化with数组，用于指定关联查询
        $with = [];
        // 如果类型为1，加载SPU关联数据
        if($type == 1)$with = ['spu'];

        // 初始化查询对象
        $query = $this->dao->search($uid,$type);
        // 设置查询条件：关联查询和按更新时间降序排序
        $query->with($with)->order('update_time DESC');
        // 计算总数据量
        $count = $query->count();
        // 分页查询数据
        $list = $query->page($page,$limit)->select();
        // 返回包含总数和列表的数据数组
        return compact('count','list');
    }


    /**
     * 根据传入的数据创建或更新SPU。
     *
     * 此方法用于处理产品类型为0、1或其它情况下的SPU创建或更新逻辑。
     * 它首先根据产品类型组装查询条件，然后尝试从数据库中检索相应的SPU信息。
     * 如果找到了SPU，并且SPU有有效的ID，则将相关信息存储到浏览记录表中。
     * 如果捕获到异常，则记录日志信息。
     *
     * @param array $data 包含产品类型、ID和其它必要信息的数据数组。
     * @return mixed 返回数据库查询结果，如果未找到则返回null。
     */
    public function createOrUpdate(array $data)
    {
        // 实例化SPURepository类，用于后续的SPU数据操作
        $make = app()->make(SpuRepository::class);

        // 初始化查询条件中的产品类型
        $where['product_type'] = $data['product_type'];

        // 根据产品类型组装查询条件
        switch ($data['product_type']) {
            case 0:
            case 1:
                // 产品类型为0或1时，使用产品ID作为查询条件
                $where['product_id'] = $data['id'];
                break;
            default:
                // 其他产品类型使用活动ID作为查询条件
                $where['activity_id'] = $data['id'];
                break;
        }

        try {
            // 根据组装的查询条件尝试获取SPU信息
            $ret = $make->getSearch($where)->find();

            // 如果找到了SPU，并且SPU有有效的ID，则更新浏览记录
            if ($ret && $ret['spu_id']) {
                $arr = [
                    'res_type' => $data['res_type'],
                    'res_id' => $ret['spu_id'],
                    'uid' => $data['uid']
                ];
                $this->dao->createOrUpdate($arr);
            }

            // 返回查询结果
            return $ret;
        } catch (\Exception $exception) {
            // 捕获异常并记录日志
            Log::info('浏览记录添加失败，ID：' . $data['id'] . '类型：' . $data['product_type']);
        }
    }

    /**
     * 商品推荐列表
     * @param int|null $uid
     * @return array
     * @author Qinii
     * @day 4/9/21
     */
    public function getRecommend(?int $uid)
    {
        $ret = $this->dao->search($uid,1)->with(['spu.product'])->limit(10)->select();
        if(!$ret) return [];
        $i = [];
        foreach ($ret as $item){
            if(isset($item['spu']['product']['cate_id'])) $i[] = $item['spu']['product']['cate_id'];
        }
        if($i) $i = array_unique($i);
        return $i;
    }

    /**
     * 获取历史记录列表
     *
     * 本函数用于根据给定的条件查询SPU相关的历史记录，并分页返回结果。
     * 主要包括查询条件、分页参数，以及返回数据的格式化。
     *
     * @param string|array $where 查询条件，可以是字符串或者数组形式的SQL条件。
     * @param int $page 当前页码，用于分页查询。
     * @param int $limit 每页显示的记录数，用于分页查询。
     * @return array 返回包含记录总数和历史记录列表的数组。
     */
    public function historyLst($where,$page,$limit)
    {
        // 根据查询条件进行SPU的连接查询
        $query = $this->dao->joinSpu($where);
        // 声明要包含的关联数据，并按更新时间降序排序
        $query->with([
            'spu'
        ])->order('update_time DESC');
        // 计算满足条件的总记录数
        $count = $query->count();
        // 进行分页查询，并指定查询字段，返回历史记录列表
        $list = $query->page($page, $limit)
            ->setOption('field',[])->field('uid,product_id,product_type,spu_id,image,store_name,price')
            ->select();
        // 返回包含记录总数和历史记录列表的数组
        return compact('count','list');
    }
}
