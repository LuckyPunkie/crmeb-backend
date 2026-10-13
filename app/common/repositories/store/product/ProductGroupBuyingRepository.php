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

use app\common\model\store\order\StoreOrder;
use app\common\model\store\product\ProductGroupBuying;
use app\common\repositories\BaseRepository;
use app\common\dao\store\product\ProductGroupBuyingDao;
use app\common\repositories\store\order\StoreOrderProductRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\store\order\StoreOrderStatusRepository;
use app\common\repositories\store\order\StoreRefundOrderRepository;
use app\common\repositories\user\UserRepository;
use crmeb\jobs\CancelGroupBuyingJob;
use crmeb\jobs\SendSmsJob;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Queue;

/**
 * 拼团活动
 */
class ProductGroupBuyingRepository extends BaseRepository
{
    protected $dao;

    /**
     * @param ProductGroupBuyingDao $dao
     */
    public function __construct(ProductGroupBuyingDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 检查团状态
     * 本函数用于确认给定的团是否处于有效状态，以及用户是否已加入该团。
     *
     * @param int $groupId 团ID，用于查询团的信息。
     * @param object $userInfo 用户信息，包含用户ID，用于检查用户是否已加入该团。
     * @return bool 返回true表示团状态正常且用户未加入，返回false表示团状态异常或用户已加入。
     */
    public function checkGroupStatus(int $groupId, $userInfo)
    {
        // 检查团的状态是否正常
        $this->checkStatus($groupId);

        // 查询数据库中团的基本信息
        $data = $this->dao->getSearch([$this->dao->getPk() => $groupId, 'is_del' => 0])->find();

        // 如果提供了用户信息，则检查该用户是否已加入当前团
        if ($userInfo) {
            // 构建查询条件，检查用户是否已加入当前团
            $_where = [
                'group_buying_id' => $groupId,
                'uid' => $userInfo->uid,
                'is_del' => 0
            ];
            // 执行查询，确认用户是否已加入团
            $has = app()->make(ProductGroupUserRepository::class)->getWhere($_where);
        }

        // 如果团信息不存在、团状态不为0，或者用户已加入，则返回false
        if (!$data || ($data['status'] !== 0) || $has) {
            return false;
        }

        // 如果所有条件检查都通过，则返回true
        return true;
    }


    /**
     * 参团操作
     * @param $userInfo
     * @param int $activeId
     * @param int $groupId
     * @param int $orderId
     * @author Qinii
     * @day 1/11/21
     */
    public function create($userInfo, int $activeId, int $groupId, int $orderId)
    {
        /**
         * 1. 活动商品是否在售，
         * 2. 团是否存在，
         *      2.1 存在是否可加入
         *      2.2 不存在，创建团，标记为团长
         * 3. 记录参团人信息
         */
        $active_make = app()->make(ProductGroupRepository::class);

        $where = $active_make->actionShow();
        $where['product_group_id'] = $activeId;
        $active = $active_make->search($where)->find();
        if (!$active) throw new ValidateException('活动商品已下架');
        if ($groupId && !$this->checkGroupStatus($groupId, $userInfo)) $groupId = 0;

        return Db::transaction(function () use ($userInfo, $active, $groupId, $activeId, $orderId) {
            if (!$groupId) {
                $ficti_status = systemConfig('ficti_status') ? $active['ficti_status'] : 0;
                $time = time() + $active['time'] * 3600;
                $_group = [
                    'product_group_id' => $activeId,
                    'ficti_status' => $ficti_status,
                    'ficti_num' => $ficti_status ? $active['ficti_num'] : 0,
                    'buying_count_num' => $active['buying_count_num'],
                    'buying_num' => $active['buying_num'],
                    'end_time' => $time,
                    'mer_id' => $active['mer_id'],
                    'is_hidde' => $active['leader_extension']
                ];
                $group = $this->dao->create($_group);
                $groupId = $group->group_buying_id;
                $is_initiator = 1;
            }
            $_where = [
                'product_group_id' => $activeId,
                'group_buying_id' => $groupId,
                'is_initiator' => $is_initiator ?? 0,
                'order_id' => $orderId,
                'is_leader' => 1,
            ];
            $user_make = app()->make(ProductGroupUserRepository::class);
            $user_make->create($userInfo, $_where);
            $this->dao->incField($groupId, 'yet_buying_num');
            if (!isset($is_initiator)) $this->checkStatus($groupId);
            return $groupId;
        });
    }

    /**
     *  成功后检测并修改状态
     * @param $groupId
     * @author Qinii
     * @day 1/11/21
     */
    public function checkStatus(?int $groupId)
    {
        $where = ['status' => 0, 'end_time' => time()];
        if ($groupId) $where = ['group_buying_id' => $groupId];
        $result = $this->dao->getSearch($where)->with([
            'groupUser' => function ($query) {
                $query->where('uid', '>', 0)->where('is_del', 0);
            },
        ])->select();
        if (!$result) return true;
        foreach ($result as $res) {
            if ($res['yet_buying_num'] >= $res['buying_count_num']) {
                $this->successChange($res);
            } else {
                if ($res['end_time'] <= time()) {
                    if ($res['ficti_status'] && $res['yet_buying_num'] >= $res['buying_num']) {
                        $this->setFictiGroup($res);
                    } else {
                        $res->status = -1;
                        $res->save();
                        foreach ($res->groupUser as $item) {
                            Queue::push(CancelGroupBuyingJob::class, ['order_id' => $item['order_id'], 'message' => '拼团失败，自动退款']);
                        }
                    }
                }
            }
        }
    }

    /**
     * 虚拟成团 操作
     * @param ProductGroupBuying $res
     * @author Qinii
     * @day 1/11/21
     */
    public function setFictiGroup(ProductGroupBuying $res)
    {
        $j = $res['buying_count_num'] - $res['yet_buying_num'];
        $user_make = app()->make(UserRepository::class);
        $query = $user_make->search([]);
        $count = $query->count();
        $id = rand(1, ($count - $j));
        $arr = $query->where('uid', '>', $id)->limit($j)->column('avatar');
        $data = [];
        $_count = count($arr);
        for ($i = 1; $i <= $j; $i++) {
            $data[] = [
                'group_buying_id' => $res['group_buying_id'],
                'product_group_id' => $res['product_group_id'],
                'uid' => 0,
                'order_id' => 0,
                'nickname' => '匿名',
                'avatar' => $arr[rand(0, $_count - 1)],
                'status' => 0
            ];
        }
        Db::transaction(function () use ($res, $data) {
            app()->make(ProductGroupUserRepository::class)->insertAll($data);
            $this->successChange($res);
        });

    }

    /**
     * 成功后更改团状态
     * @param ProductGroupBuying $res
     * @author Qinii
     * @day 1/14/21
     */
    public function successChange(ProductGroupBuying $res)
    {
        $res->status = 10;
        $res->save();
        app()->make(ProductGroupRepository::class)->incField($res['product_group_id'], 'success_num', 1);
        $productGroupUserRepository = app()->make(ProductGroupUserRepository::class);
        $productGroupUserRepository->updateStatus($res['group_buying_id']);
        $user = $productGroupUserRepository->groupOrderIds($res['group_buying_id']);
        $storeOrderStatusRepository = app()->make(storeOrderStatusRepository::class);
        $data = $orderIds = [];
        foreach ($user as $item) {
            $data[] = [
                'order_id' => $item['order_id'],
                'order_sn' => $item['orderInfo']['order_sn'],
                'type' => $storeOrderStatusRepository::TYPE_ORDER,
                'change_message' => '拼团成功',
                'change_type' => $storeOrderStatusRepository::ORDER_STATUS_GROUP_SUCCESS,
                'uid' => 0,
                'nickname' => '系统',
                'user_type' => $storeOrderStatusRepository::U_TYPE_SYSTEM,
            ];
            $orderIds[] = $item['order_id'];
        }
        if ($data && $orderIds) {
            Db::transaction(function () use ($storeOrderStatusRepository, $orderIds, $data, $res) {
                $storeOrderStatusRepository->batchCreateLog($data);
                app()->make(StoreOrderRepository::class)
                    ->getSearch([])
                    ->whereIn('order_id', $orderIds)
                    ->update(['status' => 0]);
                Queue::push(SendSmsJob::class, ['tempId' => 'GROUP_BUYING_SUCCESS', 'id' => $res->group_buying_id]);
            });
        }

    }


    /**
     * 平台团列表
     * @param $where
     * @param $page
     * @param $limit
     * @return array
     * @author Qinii
     * @day 1/12/21
     */
    public function getAdminList($where, $page, $limit)
    {
        $query = $this->dao->search($where)->with([
            'productGroup' => [
                'product' => function ($query) {
                    $query->field('product_id,store_name,image');
                }
            ],
            'merchant' => function ($query) {
                $query->field('mer_id,mer_name,mer_avatar,is_trader');
            },
            'initiator'
        ])->order('B.create_time DESC');
        $count = $query->count();
        $list = $query->page($page, $limit)->setOption('field', [])->field('B.*')->select()->append(['stop_time']);
        return compact('count', 'list');
    }


    /**
     * 商户团列表
     * @param $where
     * @param $page
     * @param $limit
     * @return array
     * @author Qinii
     * @day 1/12/21
     */
    public function getMerchantList($where, $page, $limit)
    {
        $query = $this->dao->search($where)->with([
            'productGroup' => [
                'product' => function ($query) {
                    $query->field('product_id,store_name,image');
                }
            ],
            'initiator'
        ])->order('B.create_time DESC');
        $count = $query->count();
        $list = $query->page($page, $limit)->setOption('field', [])->field('B.*')->select()->append(['stop_time']);

        return compact('count', 'list');
    }

    /**
     * 详情
     * @param int $id
     * @return array|\think\Model|null
     * @author Qinii
     * @day 1/13/21
     */
    public function detail(int $id, $userInfo)
    {
        $where = ['group_buying_id' => $id];
        $data = $this->dao->getSearch($where)->where('is_del', 0)->with([
            'groupUser' => function ($query) {
                $query->where('is_del', 0)->field('group_buying_id,product_group_id,is_initiator,nickname,avatar')->order('is_initiator DESC');
            },
        ])->hidden(['ficti_status', 'ficti_num'])->find();
        if (!$data) throw new ValidateException('无此团信息');
        $make = app()->make(ProductRepository::class);
        $product = $make->apiProductDetail(['product_id' => $data->productGroup['product_id']], 4, $data->productGroup['product_group_id']);
        $product['ot_price'] = $product['price'];
        $product['price'] = $data->productGroup['price'];
        $data['product'] = $product;

        if ($userInfo) {
            $make = app()->make(ProductGroupUserRepository::class);
            $data['self'] = $make->getSearch(['group_buying_id' => $id, 'uid' => $userInfo->uid, 'is_del' => 0])->find();
            $count = $data['self'] ? 1 : 0;
        }
        $data['create_status'] = $count ?? 0;
        unset($data['productGroup']);
        return $data;
    }

    /**
     * 取消参团
     * @param int $groupId
     * @param $userInfo
     * @author Qinii
     * @day 1/13/21
     */
    public function cancelGroup(int $groupId, $userInfo)
    {
        $this->checkStatus($groupId);
        $res = $this->dao->get($groupId);
        if (!$res) throw new ValidateException('数据丢失');
        if ($res['status'] == 10) throw new ValidateException('已拼团成功，不可取消，请在订单详情中申请退款');
        if ($res['status'] == -1) throw new ValidateException('已拼团失败，不可取消，订单将会自动退款');
        $make = app()->make(ProductGroupUserRepository::class);
        $where = [
            'group_buying_id' => $groupId,
            'uid' => $userInfo->uid,
            'is_del' => 0
        ];
        $user = $make->getSearch($where)->find();
        if (!$user) throw new ValidateException('您没有参加此团');
        Db::transaction(function () use ($res, $user, $groupId, $make) {
            // 如果团成员少于两人 直接关闭此团
            if ($res['yet_buying_num'] < 2) {
                $res->is_del = 1;
                $res->status = -1;
                $res->save();
            } else {
                //返回团对数量
                $this->dao->decField($groupId, 'yet_buying_num', 1);
                //如果是团长，转移团长
                if ($user['is_initiator']) $make->changeInitator($groupId, $user->uid);
                $user->is_initiator = 0;
                $user->is_del = 1;
                $user->save();
            }
            app()->make(StoreRefundOrderRepository::class)->autoRefundOrder($user['order_id'], 1, '取消拼团，自动退款');
        });
    }
}
