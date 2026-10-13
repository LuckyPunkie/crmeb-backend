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
use app\common\dao\store\product\ProductGroupUserDao;
use app\common\repositories\store\order\StoreOrderProductRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use crmeb\services\LockService;
use think\exception\ValidateException;

class ProductGroupUserRepository extends BaseRepository
{
    protected $dao;

    /**
     * ProductGroupRepository constructor.
     * @param ProductGroupUserDao $dao
     */
    public function __construct(ProductGroupUserDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 创建团购订单
     *
     * 本函数用于处理用户参加团购的逻辑。首先，它会检查用户是否已经参加过相同的团购，如果是，则抛出异常提示用户。
     * 接着，它会准备数据并调用创建订单的逻辑。这个过程是原子性的，通过锁定服务来保证在并发环境下的数据一致性。
     *
     * @param object $userInfo 用户信息对象，包含用户ID、昵称和头像等信息。
     * @param array $data 团购相关数据，包括产品组ID、团购ID等。
     * @return mixed 返回创建的团购订单结果。
     * @throws ValidateException 如果用户已经参加过相同的团购，则抛出此异常。
     */
    public function create($userInfo, $data)
    {
        // 根据传入的数据和用户信息，构造查询条件
        $_where = [
            'product_group_id' => $data['product_group_id'],
            'group_buying_id' => $data['group_buying_id'],
            'uid' => $userInfo->uid,
        ];

        // 根据查询条件获取已存在的用户团购记录
        $user = $this->getWhere($_where);

        // 如果查询结果存在，表示用户已经参加过此团购，抛出异常
        if ($user) {
            throw new ValidateException('您已经参加过此团');
        }

        // 将用户信息加入到订单数据中
        $data['uid'] = $userInfo->uid;
        $data['nickname'] = $userInfo->nickname;
        $data['avatar'] = $userInfo->avatar;

        // 使用锁定服务执行创建团购订单的逻辑，确保并发下的数据一致性
        return app()->make(LockService::class)->exec('order.group_buying', function () use ($data) {
            $this->dao->create($data);
        });
    }

    /**
     * 团员列表
     * @param $id
     * @return array
     * @author Qinii
     * @day 1/12/21
     */
    public function getAdminList($where,$page,$limit)
    {
        $query = $this->dao->getSearch($where)->where('uid','<>',0)->where('is_del',0)->with([
            'orderInfo' => function($query){
                $query->field('order_id,order_sn,pay_price,status,extension_one,status');
            },
        ])->order('create_time DESC');
        $count = $query->count();
        $list = $query->page($page,$limit)->select()->toArray();
        $is_leader = array_unique(array_column($list,'is_leader'));
        if (count($is_leader) > 1) {
            $extension_one = array_column($list,'extension_one');
            //数组值相加
            $extension_one = array_sum($extension_one);
            foreach ($list as $item) {
                if ($item['is_leader']) {
                    $item['extension_one'] = $extension_one;
                    break;
                }
            }
        }
        return compact('count','list');
    }

    /**
     * 团员列表
     * @param $where
     * @param $page
     * @param $limit
     * @return array
     * @author Qinii
     * @day 1/12/21
     */
    public function getApiList($where,$page,$limit)
    {
        $query = $this->dao->getSearch($where)->where('uid','<>',0)->where('is_del',0)->order('create_time DESC');
        $count = $query->count();
        $list = $query->page($page,$limit)->hidden(['uid','order_id','is_del'])->select();
        return compact('count','list');
    }


    /**
     * 转移团长
     * @param $groupId
     * @return bool
     * @author Qinii
     * @day 1/13/21
     */
    public function changeInitator(int $groupId,$uid)
    {
        $user = $this->dao->getSearch(['group_buying_id' => $groupId])
            ->where('uid','<>',0)
            ->where('is_del',0)
            ->where('uid','<>',$uid)
            ->order('create_time ASC')->find();
        if($user) {
            $user->is_initiator = 1;
            $user->save();
        }
        $this->cancelGroupEx($groupId);
    }

    /**
     *  如果更换团长，则取消创建者的分佣
     * @param $groupId
     * @param $uid
     * @return void
     * @author Qinii
     */
    public function cancelGroupEx($groupId)
    {
        $storeOrderProductRepository = app()->make(StoreOrderProductRepository::class);
        $order_id = $storeOrderProductRepository->getSearch(['product_type' => 4,'activity_id' => $groupId])->column('order_id');
        if ($order_id) app()->make(StoreOrderRepository::class)->updates($order_id,['spread_uid' => 0]);
    }

}
