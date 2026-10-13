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


use app\common\dao\user\LabelRuleDao;
use app\common\dao\user\UserOrderDao;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\system\groupData\GroupDataRepository;
use app\common\repositories\system\merchant\FinancialRecordRepository;
use app\common\repositories\wechat\WechatUserRepository;
use crmeb\jobs\SendSmsJob;
use crmeb\services\pay\Pay;
use think\exception\ValidateException;
use FormBuilder\Factory\Elm;
use think\facade\Db;
use think\facade\Log;
use think\facade\Queue;

/**
 * Class LabelRuleRepository
 * @package app\common\repositories\user
 * @author xaboy
 * @day 2020/10/20
 * @mixin UserOrderDao
 */
class UserOrderRepository extends BaseRepository
{

    //付费会员
    const TYPE_SVIP = 'S-';

    /**
     * LabelRuleRepository constructor.
     * @param UserOrderDao $dao
     */
    public function __construct(UserOrderDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 根据条件获取列表数据
     *
     * 本函数用于根据给定的条件数组$where，从数据库中检索满足条件的数据列表。
     * 它支持分页查询，每页的数据数量由$limit参数指定，页码由$page参数指定。
     * 查询结果包括两部分：总数据量$count和当前页的数据列表$list。
     * 数据列表中的每个项目都包含了用户信息，这些信息是通过关联查询获取的，只包含指定的字段。
     *
     * @param array $where 查询条件数组
     * @param int $page 当前页码
     * @param int $limit 每页的数据数量
     * @return array 包含总数据量和当前页数据列表的数组
     */
    public function getList(array $where, $page, $limit)
    {
        // 根据条件查询数据
        $query = $this->dao->search($where);

        // 计算满足条件的总数据量
        $count = $query->count();

        // 查询数据列表，同时加载每个项目的用户信息，只包含指定的用户字段
        $list = $query->with([
            'user' => function($query){
                $query->field('uid,nickname,avatar,phone,is_svip,svip_endtime');
            }
        ])
        ->order('create_time DESC') // 按照创建时间降序排序
        ->page($page, $limit) // 进行分页查询
        ->select()->toArray(); // 将查询结果转换为数组形式

        // 返回包含总数据量和数据列表的数组
        return compact('count', 'list');
    }

    /**
     * 添加会员订单
     *
     * @param object $res 订单资源信息
     * @param object $user 用户信息
     * @param array $params 额外参数，包括支付类型和是否为APP支付等
     * @return mixed 返回支付配置信息或订单ID
     */
    public function add($res, $user, $params)
    {
        $order_sn = app()->make(StoreOrderRepository::class)->getNewOrderId(StoreOrderRepository::TYPE_SN_USER_ORDER);
        $data = [
            'title'     => $res['value']['svip_name'],
            'link_id'   => $res->group_data_id,
            'order_sn'  => $order_sn,
            'pay_price' => $res['value']['price'],
            'order_info' => json_encode($res['value'],JSON_UNESCAPED_UNICODE),
            'uid'        => $user->uid,
            'order_type' => self::TYPE_SVIP.$res['value']['svip_type'],
            'pay_type'   => $res['value']['price'] == 0 ? 'free' : $params['pay_type'],
            'status'     => 1,
            'other'     => $user->is_svip == -1 ? 'first' : '',
        ];
        $body = [
            'order_sn' => $order_sn,
            'pay_price' => $data['pay_price'],
            'attach' => 'user_order',
            'body' =>'付费会员'
        ];
        $type = $params['pay_type'];
        if (in_array($type, ['weixin', 'alipay'], true) && $params['is_app']) {
            $type .= 'App';
        }
        if ($params['return_url'] && $type === 'alipay') $body['return_url'] = $params['return_url'];
        $info = $this->dao->create($data);
        if ($data['pay_price']){
            try {
                $pay = app()->make(Pay::class);
                switch ($type) {
                    case 'weixin':
                        $wechatUserRepo = app()->make(WechatUserRepository::class);
                        $openId = $wechatUserRepo->idByOpenId($user['wechat_user_id']);
                        if (!$openId) throw new ValidateException('请关联微信公众号!');
                        $config = $pay->pay($type, $body, $openId);
                        break;
                    case 'weixinApp':
                        $config = $pay->pay($type, $body, '');
                        break;
                    case 'routine':
                        $wechatUserRepo = app()->make(WechatUserRepository::class);
                        $openId = $wechatUserRepo->idByRoutineId($user['wechat_user_id']);
                        if (!$openId) throw new ValidateException('请关联微信小程序!');
                        $config = $pay->pay($type, $body, $openId);
                        break;
                    case 'alipay':
                        $body['attach'] = 'user_order';
                        $config = (new Pay('alipay'))->pay($type, $body, '');
                        break;
                    case 'alipayApp':
                        $body['attach'] = 'user_order';
                        $config = (new Pay('alipay'))->pay($type, $body, '');
                        break;
                    default:
                        $config = $pay->pay($type, $body, '');
                }
                return app('json')->status($type, $config + ['order_id' => $info->order_id]);
            } catch (\Exception $e) {
                return app('json')->status('error', $e->getMessage(), ['order_id' => $info->order_id]);
            }

        } else {
            $res = $this->paySuccess($data);
            return app('json')->status('success', ['order_id' => $info->order_id]);
        }
    }

    /**
     * 处理支付成功的逻辑。
     *
     * 当支付成功时，此函数将被调用以处理后续操作，例如更新订单状态，
     * 对于不同类型的订单，可能需要执行不同的操作，比如更新会员状态等。
     *
     * @param array $data 支付相关的数据，包含订单号等信息。
     * @return mixed 根据订单类型的不同，可能返回不同的结果。
     */
    public function paySuccess($data)
    {
        $res = $this->dao->getWhere(['order_sn' => $data['order_sn']]);
        $type = explode('-',$res['order_type'])[0].'-';

        Log::info('付费会员支付回调执行' . var_export([$data,$res->toArray(),$type,'----------------------------'],1));
        // 付费会员充值
        if ($res->pay_time && $res->paid) return true;
        if ($type == self::TYPE_SVIP) {
            return Db::transaction(function () use($data, $res) {
                $res->paid = 1;
                $res->pay_time = date('y_m-d H:i:s', time());
                $res->transaction_id = $data['data']['transaction_id'] ?? ($data['data']['trade_no'] ?? '');
                $res->save();
                return $this->payAfter($res, $res);
            });
        }
    }

    /**
     * 后付费处理函数
     * 该函数用于处理用户购买会员后的支付流程，包括更新用户会员状态、记录支付信息、生成财务记录和用户账单。
     *
     * @param array $data 包含订单信息的数据数组
     * @param object $ret 订单返回对象，包含订单相关数据
     */
    public function payAfter($data, $ret)
    {
        $financialRecordRepository = app()->make(FinancialRecordRepository::class);
        $userBillRepository = app()->make(UserBillRepository::class);
        $bill = $userBillRepository->getWhere(['type' => 'svip_pay','link_id' => $ret->order_id]);
        if ($bill) return true;
        $info = json_decode($data['order_info']);
        $user = app()->make(UserRepository::class)->get($ret['uid']);
        // 获取会员的增加天数
        $day = $info->svip_type == 3 ? 0 : $info->svip_number;
        //如果是过期了的会员，则更新结束时间以当前时间增加,否则是根据结束时间增加
        $endtime = ($user['svip_endtime'] && $user['is_svip'] != 0) ? $user['svip_endtime'] : date('Y-m-d H:i:s',time());
        $svip_endtime =  date('Y-m-d H:i:s',strtotime("$endtime  +$day day" ));
        $user->is_svip = $info->svip_type;
        $user->svip_endtime = $svip_endtime;
        $user->save();
        $ret->status = 1;
        $ret->pay_time = date('Y-m-d H:i:s',time());
        $ret->end_time = $svip_endtime;
        $ret->save();
        $title = '支付会员';
        if ($info->svip_type == 3) {
            $date = '终身会员';
            $mark = '终身会员';
        } else {
            $date = $svip_endtime;
            $mark = '到期时间'.$svip_endtime;
        }

        $financialRecordRepository->inc([
            'order_id' => $ret->order_id,
            'order_sn' => $ret->order_sn,
            'user_info'=> $user->nickname,
            'user_id'  => $user->uid,
            'financial_type' => $financialRecordRepository::FINANCIA_TYPE_SVIP,
            'number' => $ret->pay_price,
            'type'  => 2,
            'pay_type' => 0
        ],0);
        $userBillRepository->incBill($ret['uid'],UserBillRepository::CATEGORY_SVIP_PAY,'svip_pay',[
            'link_id' => $ret->order_id,
            'title' => $title,
            'number'=> $ret->pay_price,
            'status'=> 1,
            'mark' => $mark,
        ]);
        if ($user->phone) Queue::push(SendSmsJob::class,['tempId' => 'SVIP_PAY_SUCCESS','id' => ['phone' => $user->phone, 'date' => $date]]);

        //小程序发货管理
        event('mini_order_shipping', ['member', $ret, 3, '', '']);
        return true;
    }


    /**
     * 统计会员信息
     * @return array
     */
    public function countMemberInfo(array $where = [])
    {
        return [
            [
                'className' => 'el-icon-s-goods',
                'count' => $this->dao->search(['paid' => 1] + $where)->group('uid')->count(),
                'field' => 'member_nums',
                'name' => '累计付费会员人数'
            ],
            [
                'className' => 'el-icon-s-goods',
                'count' => $this->dao->search(['paid' => 1, 'pay_price' => 0] + $where)->sum('UserOrder.pay_price'),
                'field' => 'total_membership_fee',
                'name' => '累计支付会员费'
            ],
            [
                'className' => 'el-icon-s-goods',
                'count' => app()->make(UserRepository::class)->search(['svip_type' => 0])->count(),
                'field' => 'member_expire_nums',
                'name' => '累计已过期人数'
            ],
        ];
    }

}
