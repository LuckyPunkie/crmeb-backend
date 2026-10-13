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

use app\common\repositories\BaseRepository;
use app\common\dao\user\UserExtractDao as dao;
use app\common\repositories\wechat\WechatUserRepository;
use crmeb\jobs\SendSmsJob;
use crmeb\services\SwooleTaskService;
use crmeb\services\wechat\Payment;
use FormBuilder\Factory\Elm;
use think\exception\ValidateException;
use think\facade\{Db, Cache, Queue};
use think\facade\Route;
use think\facade\Log;

/**
 * Class UserExtractRepository
 *
 * @mixin dao
 */
class UserExtractRepository extends BaseRepository
{

    /**
     * @var dao
     */
    protected $dao;

    //0 银行卡 1微信 2支付宝  3零钱 4 余额
    const EXTRACT_TYPE_BANK = 0;
    const EXTRACT_TYPE_WECHAT = 1;
    const EXTRACT_TYPE_ALIPAY = 2;
    const EXTRACT_TYPE_WEXIN = 3;
    const EXTRACT_TYPE_YUE = 4;


    /**
     * UserExtractRepository constructor.
     * @param dao $dao
     */
    public function __construct(dao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 检查给定ID的相关记录是否存在且状态为0。
     *
     * 此方法用于通过指定的ID和状态查询数据库中是否有相关记录。
     * 它封装了对数据库操作的调用，以便外部代码可以通过一个简单的调用，
     * 确定是否有必要进一步处理或显示数据。
     *
     * @param int $id 需要查询的记录的ID。
     * @return bool 如果找到至少一条状态为0的记录，则返回true；否则返回false。
     */
    public function getWhereCount($id)
    {
        // 定义查询条件，指定ID和状态
        $where['extract_id'] = $id;
        $where['status'] = 0;

        // 调用DAO层方法查询满足条件的记录数，并检查是否大于0
        return $this->dao->getWhereCount($where) > 0;
    }

    /**
     * 根据条件获取分页列表数据
     *
     * 本函数用于根据给定的条件数组 $where，从数据库中检索满足条件的数据列表。
     * 它支持分页查询，并返回当前页码的数据显示以及总数据条数。
     * 这样可以在前端实现数据的分页显示。
     *
     * @param array $where 查询条件数组，用于指定数据库查询的条件。
     * @param int $page 当前页码，用于指定要返回的数据页码。
     * @param int $limit 每页数据的数量，用于指定每页显示的数据条数。
     * @return array 返回包含 'count' 和 'list' 两个元素的数组，'count' 表示总数据条数，'list' 表示当前页的数据列表。
     */
    public function getList(array $where, $page, $limit)
    {
        // 初始化查询，根据 $where 条件搜索，并加载关联的用户信息，但只获取指定的用户字段
        $query = $this->dao->search($where)->with(['user' => function ($query) {
            // 关联查询用户信息，只获取 uid, avatar, nickname 三个字段
            $query->field('uid,avatar,nickname');
        }]);

        // 计算满足条件的总数据条数
        $count = $query->count();

        // 根据当前页码和每页数据数量，获取满足条件的数据列表
        $list = $query->page($page, $limit)->select();

        // 将总数据条数和当前页的数据列表一起返回
        return compact('count', 'list');
    }

    /**
     * 计算总提取价格
     * 该方法通过查询数据表中满足条件的记录，计算出所有记录的提取价格之和。
     * 主要用于统计已提取金额的总数，以便于财务结算或数据分析。
     *
     * @param array $where 查询条件
     *                   默认情况下，查询条件包括状态为1的记录，表示有效或激活的状态。
     *                   用户可以通过传递自定义条件来修改查询逻辑。
     * @return float 返回查询结果中所有记录的提取价格之和
     */
    public function getTotalExtractPrice($where = [])
    {
        // 将默认状态为1的条件与用户自定义条件合并，并执行查询
        // 查询结果为满足条件的所有记录的提取价格之和
        return $this->dao->search($where + ['status' => 1])->sum('extract_price');
    }

    /**
     * 计算用户提取总额
     *
     * 本函数用于查询指定用户的所有提取记录，并计算这些记录的提取金额总和。
     * 提取记录是通过搜索数据表中满足条件的条目来获取的，只包括状态为1（即有效）的记录。
     *
     * @param int $uid 用户ID
     * @return float 用户提取金额的总和
     */
    public function userTotalExtract($uid)
    {
        // 通过DAO层查询满足条件的提取记录，并计算提取金额总和
        return $this->dao->search(['status' => 1, 'uid' => $uid])->sum('extract_price');
    }

    /**
     * 提现申请创建函数
     * @param User $user 用户对象
     * @param array $data 提现数据
     * @return Extract 提现记录对象
     *
     * 该函数用于处理用户的提现申请，包括验证提现条件、更新用户佣金余额、
     * 创建提现记录等操作。在执行过程中，会触发相应的事件，以便于其他系统组件监听和处理相关逻辑。
     */
    public function create($user,$data)
    {
        $key = 'user_extract_create_' . $user['uid'];
        if(Cache::get($key)) {
            throw new ValidateException('提现频率过快，请稍后重试');
        }
        Cache::set($key, $data['extract_price'], 30);
        // 触发提现前的提取事件，允许其他系统组件在这个阶段进行干预
        event('user.extract.before',compact('user','data'));
        $config = systemConfig(['extract_switch','sys_extension_type','withdraw_type','extract_type']);
        if (empty($config['withdraw_type'])) $config['withdraw_type'] = [self::EXTRACT_TYPE_YUE];
        $config['withdraw_type'][] = 3;
        if (!in_array($data['extract_type'],$config['withdraw_type']))
            throw new ValidateException('未开启该提现功能');
        //if ($data['extract_type'] == self::EXTRACT_TYPE_WEXIN && !$config['sys_extension_type'])
        //    throw new ValidateException('未开启微信自动转账');
        if ($data['extract_type'] == self::EXTRACT_TYPE_WECHAT && $config['sys_extension_type'])
            $data['extract_type'] = self::EXTRACT_TYPE_WEXIN;
            //throw new ValidateException('仅支持微信自动转账');
        // 校验用户可提现金额是否满足最小值要求
        if($user['brokerage_price'] < (systemConfig('user_extract_min')))
            throw new ValidateException('可提现金额不足');
        // 校验提现金额是否满足最小值要求
        if($data['extract_price'] < (systemConfig('user_extract_min')))
            throw new ValidateException('提现金额不得小于最低额度');
        // 校验提现金额是否超过用户可提现余额
        if($user['brokerage_price'] < $data['extract_price'])
            throw new ValidateException('提现金额不足');
        if($data['extract_price'] >= 2000 && empty($data['real_name']) && $data['extract_type'] == self::EXTRACT_TYPE_WEXIN) {
            throw new ValidateException('提现金额大于等于2000时,收款人姓名必须填写');
        }

        // 如果提现类型为微信，验证用户的微信OpenID是否存在
        if($data['extract_type'] == self::EXTRACT_TYPE_WEXIN) {
            $make = app()->make(WechatUserRepository::class);
            $openid = $make->idByOpenId((int)$user['wechat_user_id']);
            if (!$openid){
                $openid = $make->idByRoutineId((int)$user['wechat_user_id']);
                if(!$openid) throw new ValidateException('openID获取失败,请确认是微信用户');
            }
        }
        // 使用数据库事务来确保操作的原子性
        $userExtract = Db::transaction(function()use($user,$data){
            // 计算用户更新后的佣金余额
            $brokerage_price = bcsub($user['brokerage_price'],$data['extract_price'],2);
            // 更新用户佣金余额
            $user->brokerage_price = $brokerage_price;
            $user->save();
            // 生成提现单号
            $data['extract_sn'] = $this->createSn();
            // 设置提现单的用户ID和余额
            $data['uid'] = $user['uid'];
            $data['balance'] = $brokerage_price;
            // 创建提现记录
            $res = $this->dao->create($data);
            if ($data['extract_type'] == self::EXTRACT_TYPE_YUE) {
                $this->switchStatus($res->extract_id, ['status' => 1]);
            }
            return $res;
        });

        if ($data['extract_type'] != self::EXTRACT_TYPE_YUE) {
            // 发送管理员通知，提醒有新的提现申请
            SwooleTaskService::admin('notice', [
                'type' => 'extract',
                'title' => '您有一条新的提醒申请',
                'id' => $userExtract->extract_id
            ]);
        }
        // 触发提现完成事件，允许其他系统组件进行后续处理
        event('user.extract',compact('userExtract'));
        return  $userExtract;
    }

    /**
     * 创建提现审核状态切换的表单
     *
     * 该方法用于生成一个包含审核状态切换选项的表单，特别是用于处理用户提现申请的审核状态。
     * 表单中包含一个单选按钮组，用于选择通过或拒绝提现申请，如果选择拒绝，还需要提供拒绝的原因。
     *
     * @param int $id 用户提现申请的ID，用于构建表单提交的URL，确保表单提交到正确的处理程序。
     * @return \Encore\Admin\Widgets\Form|\FormBuilder\Form
     */
    public function switchStatusForm($id)
    {
        // 构建表单URL，该URL将指向处理提现审核状态切换的控制器方法。
        $url = Route::buildUrl('systemUserExtractSwitchStatus', compact('id'))->build();

        // 创建表单对象，并设置表单标题。
        return Elm::createForm($url, [
            // 添加单选按钮组，用于选择提现申请的审核状态：通过或拒绝。
            Elm::radio('status', '审核状态：', 1)->options([['value' => -1, 'label' => '拒绝'], ['value' => 1, 'label' => '通过']])->control([
                // 当选择拒绝时，显示文本区域，用于输入拒绝的原因。
                ['value' => -1, 'rule' => [
                    Elm::textarea('fail_msg', '拒绝原因：', '信息有误,请完善')->placeholder('请输入拒绝理由')->required('请输入拒绝理由')
                ]]
            ]),
        ])->setTitle('提现审核');
    }

    /**
     * 切换提取状态
     * 该方法用于处理提取记录的状态切换，涉及到资金操作和状态通知。
     *
     * @param int $id 提取记录ID
     * @param array $data 提交的数据，包含状态等信息
     * @throws ValidateException 如果用户不存在
     */
    public function switchStatus($id,$data)
    {
        // 根据提取ID获取提取记录
        $extract = $this->dao->getWhere(['extract_id' => $id]);
        // 如果提取记录不存在，抛出异常
        if(!$extract) {
            throw new ValidateException('提取记录不存在');
        }
        // 如果提取记录状态已被处理，抛出异常
        if($extract['status'] !== 0) {
            throw new ValidateException('提取记录状态已被处理');
        }
        // 根据提取记录中的用户ID获取用户信息
        $user = app()->make(UserRepository::class)->get($extract['uid']);
        // 如果用户不存在，抛出异常
        if(!$user) throw new ValidateException('用户不存在');
        // 获取系统配置的扩展类型
        $type = systemConfig('sys_extension_type');
        // 初始化返回数组和支付服务变量
        $ret = [];
        $service = null;
        $func = null;
        $brokerage = $user->brokerage_price;
        // 初始化佣金价格变量
        $brokerage_price = 0;
        $out = [];
        //同意
        if ($data['status'] == 1) {
            $out = [
                'type' => 'extract',
                'data' => [
                    'link_id' => $id,
                    'status' => 1,
                    'title' => '佣金提现',
                    'number' => $extract['extract_price'],
                    'mark' => '成功佣金提现' . floatval($extract['extract_price']) . '元',
                    'balance' => $brokerage
                ]
            ];
            switch ($extract['extract_type']) {
                case self::EXTRACT_TYPE_WEXIN:
                    if (in_array($type, [1, 2])) {
                        // 判断渠道：优先公众号 openid，其次小程序 openid
                        $wechatUserRepo = app()->make(WechatUserRepository::class);
                        $openid = $wechatUserRepo->idByOpenId((int)$user['wechat_user_id']);
                        $channel_type = $openid ? 'wechat' : 'routine';
                        if (!$openid) {
                            if (!$wechatUserRepo->idByRoutineId((int)$user['wechat_user_id'])) {
                                throw new ValidateException('非微信用户不支持付款到零钱');
                            }
                        }
                        $ret = [
                            'channel_type' => $channel_type,
                            'uid'          => $user->uid,
                            'sn'           => $extract['extract_sn'],
                            'price'        => $extract['extract_price'],
                            'mark'         => '企业付款给用户:' . $user->nickname,
                            'realName'     => $extract['real_name'] ?? '',
                        ];
                        $service = Payment::instance();
                        $func = 'doMerchantPay';
                    }
                    break;
                case self::EXTRACT_TYPE_YUE:
                    $ret = ['extract' => $extract['extract_price'], 'extract_id' => $id];
                    $service = app()->make(UserExtractRepository::class);
                    $func = 'toBalance';
                    $out = [
                        'type' => 'now_money',
                        'data' => [
                            'link_id' => $id,
                            'status' => 1,
                            'title' => '佣金转入余额',
                            'number' => $extract['extract_price'],
                            'mark' => '成功转入余额' . floatval($extract['extract_price']) . '元',
                            'balance' => $brokerage
                        ]
                    ];
                    break;
                default:
                    break;
            }
        } else {
            // 如果数据中的状态为-1，计算新的佣金价格
            $brokerage_price = bcadd($user['brokerage_price'] ,$extract['extract_price'],2);
        }
        $userBillRepository = app()->make(UserBillRepository::class);
        // 使用事务处理以下操作，确保数据的一致性
        Db::transaction(function()use($id,$data,$user,$brokerage_price,$ret,$service,$func,$userBillRepository,$out){
            // 触发状态切换前的事件
            event('user.extractStatus.before',compact('id','data'));

            if ($data['status'] == 1 && $func) {
                if ($func === 'doMerchantPay') {
                    // 微信转账：走 Payment::merchantPay() 静态方法
                    $res = Payment::merchantPay(
                        $ret['channel_type'],
                        $ret['uid'],
                        $ret['sn'],
                        $ret['price'],
                        $ret['mark'],
                        $ret['realName']
                    );
                    if ($res) {
                        $updateData = [];
                        if (isset($res['transfer_bill_no'])) $updateData['transfer_bill_no'] = $res['transfer_bill_no'];
                        if (isset($res['package_info']))    $updateData['package_info']     = $res['package_info'];
                        if (isset($res['state']))           $updateData['wechat_status']    = $res['state'];
                        if (isset($res['app_id']))          $updateData['wechat_app_id']    = $res['app_id'];
                        if (isset($res['mch_id']))          $updateData['wechat_mch_id']    = $res['mch_id'];
                        if ($updateData) $this->dao->update($id, $updateData);
                    }
                } else {
                    // 余额转账：走原有 $service->toBalance()
                    $service->{$func}($ret, $user);
                }
            }
            // 如果有计算出的佣金价格，更新用户佣金信息
            if($brokerage_price){
                $user->brokerage_price = $brokerage_price;
                $user->save();
            }
            // 更新提取记录状态
            $userExtract = $this->dao->update($id,$data);
            if($out) $userBillRepository->decBill($user->uid, 'brokerage', $out['type'], $out['data']);
            // 触发状态切换后的事件
            event('user.extractStatus',compact('id','userExtract'));
        });

        // 推送发送短信的任务到队列
        Queue::push(SendSmsJob::class,['tempId' => 'PAYMENT_RECEIVED', 'id' =>$id]);
    }

    /**
     *  佣金提现到余额
     * @param $data
     * @param $user
     * @return void
     * @author Qinii
     */
    public function toBalance($data, $user)
    {
        $now_money = bcadd($user->now_money, $data['extract'], 2);
        $user->now_money = $now_money;
        $user->save();
        // 创建用户账单记录，佣金增加到余额
        app()->make(UserBillRepository::class)->incBill($user->uid, 'now_money', 'brokerage', [
            'link_id' => 0,
            'status' => 1,
            'title' => '佣金转入余额',
            'number' => $data['extract'],
            'mark' => '成功转入余额' . floatval($data['extract']) . '元',
            'balance' => $now_money
        ]);
    }

    /**
     * 创建一个唯一序列号
     *
     * 本函数旨在生成一个包含时间戳和随机数的唯一序列号，用于标识或唯一标记某个事物。
     * 序列号以"ue"开头，后面跟随毫秒级时间戳和一个随机数。这样可以确保在短时间内生成的序列号是唯一的。
     *
     * @return string 生成的唯一序列号
     */
    public function createSn()
    {
        // 获取当前时间的微秒和秒部分
        list($msec, $sec) = explode(' ', microtime());

        // 将微秒和秒转换为毫秒，并去掉小数点，确保序列号是整数
        $msectime = number_format((floatval($msec) + floatval($sec)) * 1000, 0, '', '');

        // 生成序列号：'ue' + 毫秒时间戳 + 随机数
        // 随机数范围确保在特定范围内，以避免生成的序列号在短时间内重复
        $sn = 'ue' . $msectime . mt_rand(10000, max(intval($msec * 10000) + 10000, 98369));

        return $sn;
    }


    /**
     * 获取用户的历史银行卡信息
     *
     * 本函数用于查询指定用户的历史银行卡记录。它通过用户的UID来检索数据，
     * 仅返回提取类型为0的记录，这通常表示用户的存款记录。返回的数据包括
     * 实名、银行代码、银行地址和银行名称，这些信息对于后续的银行相关操作
     * 或用户查询非常有用。
     *
     * @param int $uid 用户ID。这是查询用户历史银行卡记录的关键标识。
     * @return array 返回包含用户历史银行卡信息的数组。如果找不到相关信息，则返回空数组。
     */
    public function getHistoryBank($uid)
    {
        // 使用DAO对象进行查询，指定查询条件为UID和提取类型为0，按创建时间降序排序，并指定返回的字段。
        return $this->dao->getSearch(['uid' => $uid,'extract_type' => 0])->order('create_time DESC')->field('real_name,bank_code,bank_address,bank_name')->find();
    }

    /**
     * 根据ID获取详细信息
     * 此方法通过ID从数据库中获取特定记录的详细信息，包括用户信息，使用懒加载模式来加载用户信息，
     * 仅当需要时才查询用户相关数据，以提高查询效率。
     *
     * @param int $id 主键ID，用于查询特定记录
     * @return array 返回查询到的详细信息数组
     * @throws ValidateException 如果未查询到任何信息，则抛出异常
     */
    public function detail(int $id)
    {
        // 使用懒加载方式获取数据，这里只查询基本信息，并通过回调函数加载用户详细信息
        $info = $this->dao->getWith($id, ['user' => function ($query) {
            // 精确查询用户信息，只获取必要的字段，以减少数据库查询负载
            $query->field('uid,avatar,nickname');
        }]);

        // 检查查询结果，如果为空，则抛出异常提示数据异常
        if(empty($info)){
            throw new ValidateException('数据异常');
        }

        // 将查询结果转换为数组格式并返回
        return $info->toArray();
    }
    /**
     * 回调事件更新提现记录状态
     *
     * @param array $params
     * @return boolean
     */
    public function updateStatus(array $params) : bool
    {
        $where = [];
        $where['transfer_bill_no'] = $params['data']['transfer_bill_no'];
        $where['extract_sn'] = $params['data']['out_bill_no'];

        $info = $this->dao->getWhere($where);
        if(!$info) {
            Log::info('商家提现记录变更：提现记录不存在。params：'.json_encode($params));
            return false;
        };
        $user = app()->make(UserRepository::class)->get($info['uid']);
        // 初始化佣金价格变量
        $brokerage_price = 0;
        $extractData = [];
        $extractData['wechat_status'] = $params['data']['state'];
        $extractData['status'] = 2; // 提现成功
        if($extractData['wechat_status'] !== 'SUCCESS') {
            $extractData['status'] = -2; // 提现失败
            // 失败则回退佣金
            $brokerage_price = bcadd($user['brokerage_price'] ,$info['extract_price'],2);
        }

        // 使用事务处理以下操作，确保数据的一致性
        Db::transaction(function()use($info, $extractData, $user, $brokerage_price, $params){
            // 如果有佣金，更新用户佣金信息
            if($brokerage_price){
                $user->brokerage_price = $brokerage_price;
                $user->save();
            }
            $res = $this->dao->update($info['extract_id'], $extractData);
            if(!$res) {
                Log::info('商家提现记录变更：提现记录状态变更失败。params：'.json_encode($params),'，res：'.json_encode($res));
                return false;
            };
        });


        Log::info('商家提现记录变更：提现记录状态变更成功,id：'.$info['extract_id'].'，status：'.$params['data']['state']);
        return true;
    }
}
