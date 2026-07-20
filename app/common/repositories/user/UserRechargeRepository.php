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

use app\common\dao\user\UserRechargeDao;
use app\common\model\user\User;
use app\common\model\user\UserRecharge;
use app\common\repositories\BaseRepository;
use app\common\repositories\wechat\WechatUserRepository;
use crmeb\jobs\SendSmsJob;
use crmeb\services\pay\Pay;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Queue;

/**
 * Class UserRechargeRepository
 * @package app\common\repositories\user
 * @author xaboy
 * @day 2020/6/2
 * @mixin UserRechargeDao
 */
class UserRechargeRepository extends BaseRepository
{
    const TYPE_WECHAT = 'weixin';
    const TYPE_ROUTINE = 'routine';
    /**
     * UserRechargeRepository constructor.
     * @param UserRechargeDao $dao
     */
    public function __construct(UserRechargeDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 创建充值订单
     *
     * 本函数用于生成一个新的充值订单。它根据传入的用户ID、充值金额、赠送金额和充值类型，
     * 创建一个订单并返回订单数据。订单初始状态为未支付。
     *
     * @param int $uid 用户ID，用于关联订单和用户。
     * @param float $price 充值金额，单位为元，精确到小数点后两位。
     * @param float $givePrice 赠送金额，单位为元，精确到小数点后两位。
     * @param string $type 充值类型，用于区分不同的充值方式，如：支付宝、微信等。
     * @return array 返回新创建的订单数据。
     */
    public function create($uid, float $price, float $givePrice, string $type)
    {
        // 生成订单数据数组，包含用户ID、充值金额、赠送金额、充值类型、初始支付状态和订单ID。
        $orderData = [
            'uid' => $uid,
            'price' => $price,
            'give_price' => $givePrice,
            'recharge_type' => $type,
            'paid' => 0, // 订单初始支付状态为未支付。
            'order_id' => $this->dao->createOrderId($uid) // 生成唯一的订单ID。
        ];

        // 调用DAO层的方法创建订单，并返回创建的订单数据。
        return $this->dao->create($orderData);
    }

    /**
     * 获取列表数据
     * 通过此方法可以从数据库中检索满足特定条件的列表数据。它支持分页查询，以优化大型数据集的处理。
     *
     * @param string $where 查询条件，用于筛选数据。这是一个字符串，可能包含SQL的WHERE子句条件。
     * @param int $page 当前页码，用于分页查询。
     * @param int $limit 每页的数据条数，用于分页查询。
     * @return array 返回一个包含两个元素的数组，'count'表示数据总数，'list'表示当前页的数据列表。
     */
    public function getList($where, $page, $limit)
    {
        // 构建查询语句，包括JOIN操作和排序规则
        $query = $this->dao->searchJoinQuery($where)->order('a.pay_time DESC,a.create_time DESC');

        // 计算满足条件的数据总数
        $count = $query->count();

        // 执行分页查询，并获取当前页的数据列表
        $list = $query->page($page, $limit)->select();

        // 将数据总数和数据列表打包成数组返回
        return compact('count', 'list');
    }



    /**
     * 根据充值金额获取赠送金额。
     *
     * 本函数用于根据用户充值的金额，确定相应的赠送金额。系统预设了不同充值金额区间的赠送规则，
     * 本函数通过查询这些规则，找到适用的赠送金额。
     *
     * @param float $price 充值金额。
     * @return float 返回应赠送的金额。
     */
    public function priceByGive($price)
    {
        // 获取系统设置的充值额度和赠送额度规则
        $quota = systemGroupData('user_recharge_quota');
        $give = 0.0; // 初始化赠送金额为0

        // 遍历规则数组，找出适用的赠送规则
        foreach ($quota as $item) {
            $min = floatval($item['price']); // 当前规则的最低充值金额
            $_give = floatval($item['give']); // 当前规则的赠送金额

            // 如果当前充值金额大于等于当前规则的最低充值金额，更新赠送金额
            if ($price >= $min) {
                $give = $_give;
            }
        }

        return $give; // 返回计算出的赠送金额
    }

    /**
     * 处理用户充值支付逻辑。
     *
     * 根据支付类型和是否为APP环境，生成相应的支付配置。支持微信和支付宝两种支付方式，
     * 当在APP环境中支付时，支付类型会相应地加上App后缀。在支付前，会触发一个before充值事件，
     * 允许其他功能模块在充值前进行额外的操作或校验。最终，返回包含支付配置和充值ID等信息的数组。
     *
     * @param string $type 支付方式，支持'weixin'和'alipay'。
     * @param User $user 进行充值的用户对象。
     * @param UserRecharge $recharge 用户充值记录对象。
     * @param string $return_url 支付宝充值成功后的回调URL，可选。
     * @param bool $isApp 是否在APP环境中支付，默认为false。
     * @return array 包含支付配置和充值ID等信息的数组。
     */
    public function pay(string $type, User $user, UserRecharge $recharge, $return_url = '', $isApp = false)
    {
        // 根据支付方式和是否为APP环境，调整支付类型
        if (in_array($type, ['weixin', 'alipay'], true) && $isApp) {
            $type .= 'App';
        }

        // 触发用户充值前的事件，允许其他功能模块介入
        event('user.recharge.before', compact('user', 'recharge', 'type', 'isApp'));

        // 直接调用 Pay 驱动
        $pay = app()->make(Pay::class);
        $orderParams = $recharge->getPayParams($type === 'alipay' ? $return_url : '');

        switch ($type) {
            case 'weixin':
                $wechatUserRepo = app()->make(WechatUserRepository::class);
                $openId = $wechatUserRepo->idByOpenId($user['wechat_user_id']);
                if (!$openId) throw new ValidateException('请关联微信公众号!');
                $config = $pay->pay($type, $orderParams, $openId);
                break;
            case 'weixinApp':
                $config = $pay->pay($type, $orderParams, '');
                break;
            case 'routine':
                $wechatUserRepo = app()->make(WechatUserRepository::class);
                $openId = $wechatUserRepo->idByRoutineId($user['wechat_user_id']);
                if (!$openId) throw new ValidateException('请关联微信小程序!');
                $config = $pay->pay($type, $orderParams, $openId);
                break;
            case 'alipay':
                $orderParams['attach'] = 'user_recharge';
                $orderParams['return_url'] = $return_url;
                $config = (new Pay('alipay'))->pay($type, $orderParams, '');
                break;
            case 'alipayApp':
                $orderParams['attach'] = 'user_recharge';
                $config = (new Pay('alipay'))->pay($type, $orderParams, '');
                break;
            default:
                $config = $pay->pay($type, $orderParams, '');
        }

        // 返回支付配置，加上充值ID和支付类型，供前端进行支付操作
        return $config + ['recharge_id' => $recharge['recharge_id'], 'type' => $type];
    }

    /**
     * //TODO 余额充值成功
     *
     * @param $orderId
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author xaboy
     * @day 2020/6/19
     */
    public function paySuccess($orderId)
    {
        $recharge = $this->dao->getWhere(['order_id' => $orderId]);
        if ($recharge->paid == 1) return;
        $recharge->paid = 1;
        $recharge->pay_time = date('Y-m-d H:i:s');

        Db::transaction(function () use ($recharge) {
            $price = bcadd($recharge->price, $recharge->give_price, 2);
            $mark = '成功充值余额' . floatval($recharge->price) . '元' . ($recharge->give_price > 0 ? ',赠送' . $recharge->give_price . '元' : '');
            app()->make(UserBillRepository::class)->incBill($recharge->user->uid, 'now_money', 'recharge', [
                'link_id' => $recharge->recharge_id,
                'status' => 1,
                'title' => '余额充值',
                'number' => $price,
                'mark' => $mark,
                'balance' => bcadd($recharge->user->now_money, $price, 2)
            ]);
            $recharge->user->now_money = bcadd($recharge->user->now_money, $price, 2);
            $recharge->user->save();
            $recharge->save();
        });
        Queue::push(SendSmsJob::class,['tempId' => 'USER_BALANCE_CHANGE', 'id' =>$orderId]);

        //小程序发货管理
        event('mini_order_shipping', ['recharge', $recharge, 3, '', '']);

        event('user.recharge',compact('recharge'));
    }
}
