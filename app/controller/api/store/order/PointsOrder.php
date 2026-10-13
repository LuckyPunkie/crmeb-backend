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


namespace app\controller\api\store\order;


use app\common\repositories\delivery\DeliveryOrderRepository;
use app\common\repositories\store\order\PointsOrderCreateRepository;
use app\common\repositories\store\order\StoreOrderCreateRepository;
use app\common\repositories\store\order\StoreOrderReceiptRepository;
use app\validate\api\UserReceiptValidate;
use crmeb\basic\BaseController;
use app\common\repositories\store\order\StoreCartRepository;
use app\common\repositories\store\order\StoreGroupOrderRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use crmeb\services\ExpressService;
use crmeb\services\LockService;
use think\App;
use think\exception\ValidateException;
use think\facade\Log;

/**
 * Class StoreOrder
 * @package app\controller\api\store\order
 * @author xaboy
 * @day 2020/6/10
 */
class PointsOrder extends BaseController
{
    /**
     * @var StoreOrderRepository
     */
    protected $repository;

    /**
     * StoreOrder constructor.
     * @param App $app
     * @param StoreOrderRepository $repository
     */
    public function __construct(App $app, StoreOrderRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 在确认订单前进行购物车检查
     * 该方法主要用于在用户提交订单前，验证购物车中的商品是否有效，以及处理相关的优惠券和积分使用等逻辑。
     *
     * @param StoreCartRepository $cartRepository 购物车仓库对象，用于操作购物车数据。
     *
     * @return json 返回一个包含订单信息的JSON对象，如果数据无效，则返回错误信息。
     */
    public function beforCheck(StoreCartRepository $cartRepository)
    {
        // 获取请求中的购物车ID、收货地址ID、是否使用积分以及优惠券ID和代金券ID
        $cartId = (array)$this->request->param('cart_id', []);
        $addressId = (int)$this->request->param('address_id');
        $useIntegral = (bool)$this->request->param('use_integral', true);
        $params['couponIds'] = (array)$this->request->param('use_coupon', []);
        $params['takes'] = (array)$this->request->param('takes', []);

        // 获取当前请求的用户信息
        $user = $this->request->userInfo();

        // 验证购物车ID的数量是否有效，并检查购物车ID是否与用户ID匹配
        if (!($count = count($cartId)) || $count != count($cartRepository->validIntersection($cartId, $user->uid)))
            return app('json')->fail('数据无效');

        // 调用订单创建仓库的check方法，检查订单是否可以创建，并返回订单信息
        $orderInfo = app()->make(PointsOrderCreateRepository::class)->check($user, $cartId, $addressId, $useIntegral, $params);

        // 返回订单信息的JSON对象
        return app('json')->success($orderInfo);
    }

    /**
     * 创建订单
     *
     * 本函数负责根据用户购物车信息、收货地址、支付方式等创建订单。
     * 它处理了PC端和移动端的不同支付方式，并对订单创建过程进行了事务处理，确保数据一致性。
     *
     * @param StoreCartRepository $cartRepository 购物车仓库，用于获取和验证购物车项。
     * @return mixed 返回创建的订单信息，可能是一个支付成功的提示，或者是支付接口的跳转信息。
     */
    public function createOrder(StoreCartRepository $cartRepository)
    {
        // 获取购物车ID、收货地址ID、是否使用积分、标记和支付方式
        $cartId = (array)$this->request->param('cart_id', []);
        $addressId = (int)$this->request->param('address_id');
        $useIntegral = (bool)$this->request->param('use_integral', true);
        $mark = $this->request->param('mark', '');
        $payType = $this->request->param('pay_type');

        // 判断是否为PC端支付，PC端默认支付方式为余额支付
        $isPc = $payType === 'pc';
        if ($isPc) $payType = 'balance';

        // 检查支付方式是否有效
        if (!in_array($payType, StoreOrderRepository::PAY_TYPE, true))
            return app('json')->fail('请选择正确的支付方式');

        // 获取用户ID
        $uid = $this->request->uid();

        // 验证购物车ID的有效性
        if (!($count = count($cartId)) || $count != count($cartRepository->validIntersection($cartId, $uid)))
            return app('json')->fail('数据无效');

        // 使用锁服务，确保并发下的数据一致性
        $make = app()->make(LockService::class);

        // 通过积分创建订单，使用事务处理确保数据一致性
        $groupOrder = $make->exec('points.order.create', function () use ($mark, $cartId, $payType, $useIntegral, $addressId) {
            return app()->make(PointsOrderCreateRepository::class)->createOrder($this->request->userInfo(),$cartId,$addressId,$useIntegral,$mark,array_search($payType, StoreOrderRepository::PAY_TYPE));
        });

        // 如果订单支付价格为0，表示订单已支付成功
        if ($groupOrder['pay_price'] == 0) {
            $this->repository->paySuccess($groupOrder);
            return app('json')->status('success', '支付成功', ['order_id' => $groupOrder['group_order_id']]);
        }

        // PC端直接返回订单ID
        if ($isPc) {
            return app('json')->success(['order_id' => $groupOrder->group_order_id]);
        }

        // 移动端调用支付接口进行支付
        try {
            return $this->repository->pay($payType, $this->request->userInfo(), $groupOrder, $this->request->param('return_url'), $this->request->isApp(), false);
        } catch (\Exception $e) {
            // 支付过程中出现异常，返回错误信息和订单ID
            return app('json')->status('error', $e->getMessage(), ['order_id' => $groupOrder->group_order_id]);
        }
    }

    /**
     * 积分商品订单
     * @param StoreOrderRepository $storeOrderRepository
     * @return \think\response\Json
     * @author Qinii
     * @day 2023/4/23
     */
    public function lst(StoreOrderRepository $storeOrderRepository)
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['pay_type','paid','status']);
        $where['activity_type'] = 20;
        $where['uid'] = $this->request->uid();
        return app('json')->success($storeOrderRepository->pointsOrderList($where,$page, $limit));
    }

    /**
     * 查询积分订单详情
     *
     * 本函数用于根据订单ID查询积分订单的详细信息。它首先尝试根据提供的ID和当前用户的UID来获取订单。
     * 如果订单不存在，则返回一个失败的JSON响应；如果订单存在，则返回订单的详细信息。
     *
     * @param int $id 积分订单的ID
     * @param StoreOrderRepository $storeOrderRepository 积分订单仓库对象，用于查询订单
     * @return mixed 返回一个失败的JSON响应或者包含订单详情的JSON响应
     */
    public function detail($id, StoreOrderRepository $storeOrderRepository)
    {
        // 根据订单ID和当前用户UID查询积分订单详情
        $order = $storeOrderRepository->pointsDetail((int)$id, $this->request->uid());

        // 如果订单不存在，则返回一个失败的JSON响应
        if (!$order)
            return app('json')->fail('订单不存在');

        // 如果订单存在，则返回包含订单详情的JSON响应
        return app('json')->success($order->toArray());
    }

    /**
     * 确认订单收货
     *
     * 本函数用于处理订单的收货确认操作。当用户确认收货时，此函数将被调用。
     * 它通过调用仓库接口的takeOrder方法，标记订单为已收货，并传递订单ID和用户信息。
     *
     * @param int $id 订单ID，用于标识待确认收货的订单。
     * @return \Illuminate\Http\JsonResponse 返回一个JSON响应，指示确认收货操作成功。
     */
    public function take($id)
    {
        // 调用仓库 repository 的 takeOrder 方法，处理订单收货确认
        $this->repository->takeOrder($id, $this->request->userInfo());

        // 返回一个成功的JSON响应，告知用户收货确认已成功
        return app('json')->success('确认收货成功');
    }

    /**
     * 删除用户
     *
     * 本函数用于执行用户删除操作。它接收一个用户ID作为参数，
     * 并调用仓库层的用户删除方法来执行实际的删除操作。删除操作
     * 是基于当前请求的用户ID执行的，确保了操作的安全性和审计
     * 能力。函数在成功执行删除操作后，会返回一个表示删除成功
     * 的JSON响应。
     *
     * @param int $id 用户ID，指定要删除的用户
     * @return \Illuminate\Http\JsonResponse 删除成功的JSON响应
     */
    public function del($id)
    {
        // 调用仓库层的方法，删除指定ID的用户，同时传入当前请求的用户ID作为安全校验
        $this->repository->userDel($id, $this->request->uid());

        // 返回一个表示删除成功的JSON响应
        return app('json')->success('删除成功');
    }



}
