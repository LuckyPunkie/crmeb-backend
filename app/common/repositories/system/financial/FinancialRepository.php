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
namespace app\common\repositories\system\financial;


use crmeb\services\wechat\Payment;
use app\common\dao\system\financial\FinancialDao;
use app\common\repositories\BaseRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\system\serve\ServeOrderRepository;
use app\common\repositories\user\UserBillRepository;
use crmeb\jobs\ChangeMerchantStatusJob;
use crmeb\jobs\SendSmsJob;
use crmeb\services\merchant\MerchantCoreService;
use FormBuilder\Factory\Elm;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Queue;
use think\facade\Route;


/**
 * 商户财务申请提现
 */
class FinancialRepository extends BaseRepository
{
    public function __construct(FinancialDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 商户财务账号表单生成方法
     *
     * 本方法用于根据给定的商户ID生成一个财务账号编辑表单。
     * 表单中包括银行卡、微信、支付宝三种转账方式的选项，根据商户当前设置的转账类型
     * 显示相应的编辑字段。商户可以填写或修改收款人的姓名、账号信息，并上传收款二维码。
     *
     * @param int $id 商户ID，用于查询商户当前的财务账号信息。
     * @return \FormBuilder\Form|\LaravelAdminPanel\Form
     */
    public function financialAccountForm($id)
    {
        // 根据商户ID查询商户及其当前财务账号信息
        $merchant = app()->make(MerchantRepository::class)->search(['mer_id' => $id])->find();

        // 创建表单对象，并设置表单提交的URL
        $form = Elm::createForm(Route::buildUrl('merchantFinancialAccountSave', ['id' => $id])->build());

        // 设置表单验证规则，根据转账类型动态显示相应的输入字段
        $form->setRule([
            // 创建选择器，用于选择转账类型：银行卡、微信、支付宝
            Elm::radio('financial_type', '转账类型：', $merchant->financial_type)
                ->setOptions([
                    ['value' => 1, 'label' => '银行卡'],
                    ['value' => 2, 'label' => '微信'],
                    ['value' => 3, 'label' => '支付宝'],
                ])
                // 根据选择的转账类型，显示不同的输入字段
                ->control([
                    [
                        'value' => 1,
                        'rule' => [
                            // 银行卡收款人的姓名
                            Elm::input('name', '姓名：')->value($merchant->financial_bank->name ?? '')->placeholder('请输入姓名')->required('请输入姓名'),
                            // 银行开户名称
                            Elm::input('bank', '开户银行：')->value($merchant->financial_bank->bank ?? '')->placeholder('请输入开户银行')->required('请输入开户银行'),
                            // 银行卡号
                            Elm::input('bank_code', '银行卡号：')->value($merchant->financial_bank->bank_code ?? '')->placeholder('请输入银行卡号')->required('请输入银行卡号'),
                        ]
                    ],
                    [
                        'value' => 2,
                        'rule' => [
                            // 微信收款人的姓名
                            Elm::input('name', '姓名：')->value($merchant->financial_wechat->name ?? '')->placeholder('请输入姓名')->required('请输入姓名'),
                            // 微信号
                            Elm::input('wechat', '微信号：')->value($merchant->financial_wechat->wechat ?? '')->placeholder('请输入微信号')->required('请输入微信号'),
                            // 微信收款二维码
                            Elm::frameImage('wechat_code', '收款二维码：', '/' . config('admin.merchant_prefix') . '/setting/uploadPicture?field=wechat_code&type=1')->value($merchant->financial_wechat->wechat_code ?? '')->icon('el-icon-camera')->modal(['modal' => false])->width('1000px')->height('600px'),
                        ]
                    ],
                    [
                        'value' => 3,
                        'rule' => [
                            // 支付宝收款人的姓名
                            Elm::input('name', '姓名：')->value($merchant->financial_alipay->name ?? '')->placeholder('请输入姓名')->required('请输入姓名'),
                            // 支付宝账号
                            Elm::input('alipay', '支付宝账号：')->value($merchant->financial_alipay->alipay ?? '')->placeholder('请输入支付宝账号')->required('请输入支付宝账号'),
                            // 支付宝收款二维码
                            Elm::frameImage('alipay_code', '收款二维码：', '/' . config('admin.merchant_prefix') . '/setting/uploadPicture?field=alipay_code&type=1')->value($merchant->financial_alipay->alipay_code ?? '')->icon('el-icon-camera')->modal(['modal' => false])->width('1000px')->height('600px'),
                        ]
                    ],
                ]),
        ]);

        // 设置表单标题
        return $form->setTitle('转账信息');
    }

    /**
     * 保存转账信息
     * @param int $merId
     * @param array $data
     * @author Qinii
     * @day 3/18/21
     */
    public function saveAccount(int $merId, array $data)
    {
        switch ($data['financial_type']) {
            case 1:
                $key = 'financial_bank';
                $update = [
                    'name' => $data['name'],
                    'bank' => $data['bank'],
                    'bank_code' => $data['bank_code'],
                ];
                break;
            case 2:
                $key = 'financial_wechat';
                $update = [
                    'name' => $data['name'],
                    //'idcard' => $data['idcard'],
                    'wechat' => $data['wechat'],
                    'wechat_code' => $data['wechat_code'],
                ];
                break;
            case 3:
                $key = 'financial_alipay';
                $update = [
                    'name' => $data['name'],
                    //'idcard' => $data['idcard'],
                    'alipay' => $data['alipay'],
                    'alipay_code' => $data['alipay_code'],
                ];
                break;
        }
        return app()->make(MerchantRepository::class)->update($merId, [$key => json_encode($update), 'financial_type' => $data['financial_type']]);
    }

    /**
     * 商户申请提现表单的生成方法
     *
     * 本方法用于根据商户ID生成商户提现申请的表单。表单中包含了商户的基本信息、可提现金额、转账类型选择
     * 以及必要的转账信息展示（如银行卡、微信、支付宝信息）。商户在填写提现金额后，可提交表单进行提现申请。
     *
     * @param int $merId 商户ID，用于查询商户信息和提现配置。
     * @return \FormBuilder\Form|\think\response\View
     */
    public function applyForm(int $merId)
    {
        $merchant = app()->make(MerchantRepository::class)->search(['mer_id' => $merId])->field('mer_id,mer_name,mer_money,financial_bank,financial_wechat,financial_alipay,financial_type')->find();
        $extract_minimum_line = systemConfig('extract_minimum_line') ?: 0;
        $extract_minimum_num = systemConfig('extract_minimum_num');
        $_line = bcsub($merchant->mer_money, $extract_minimum_line, 2);
        $_extract = ($_line < 0) ? 0 : $_line;
        $form = Elm::createForm(Route::buildUrl('merchantFinancialCreateSave')->build());
        $form->setRule([
            [
                'type' => 'span',
                'title' => '商户名称：',
                'native' => false,
                'children' => ["$merchant->mer_name"]
            ],
            [
                'type' => 'span',
                'title' => '商户ID：',
                'native' => false,
                'children' => ["$merId"]
            ],
//            [
//                'type' => 'span',
//                'title' => '',
//                'children' => []
//            ],
            [
                'type' => 'span',
                'title' => '提示：',
                'native' => false,
                'children' => ['商户余额押金:' . $extract_minimum_line . '元;最低提现金额：' . $extract_minimum_num . '元']
            ],
            [
                'type' => 'span',
                'title' => '商户余额：',
                'native' => false,
                'children' => ["$merchant->mer_money"]
            ],
            [
                'type' => 'span',
                'native' => false,
                'title' => '商户可提现金额：',
                'children' => ["$_extract"]
            ],

            Elm::radio('financial_type', '转账类型：', $merchant->financial_type)
                ->setOptions([
                    ['value' => 1, 'label' => '银行卡'],
                    ['value' => 2, 'label' => '微信'],
                    ['value' => 3, 'label' => '支付宝'],
                ])->control([
                    [
                        'value' => 1,
                        'rule' => [
                            [
                                'type' => 'span',
                                'title' => '姓名：',
                                'native' => false,
                                'children' => [$merchant->financial_bank->name ?? '未填写']
                            ],
                            [
                                'type' => 'span',
                                'title' => '开户银行：',
                                'native' => false,
                                'children' => [$merchant->financial_bank->bank ?? '未填写']
                            ],
                            [
                                'type' => 'span',
                                'title' => '银行卡号：',
                                'native' => false,
                                'children' => [$merchant->financial_bank->bank_code ?? '未填写']
                            ],
                        ]
                    ],
                    [
                        'value' => 2,
                        'rule' => [
                            [
                                'type' => 'span',
                                'title' => '姓名：',
                                'native' => false,
                                'children' => [$merchant->financial_wechat->name ?? '未填写']
                            ],
                            [
                                'type' => 'span',
                                'title' => '微信号：',
                                'native' => false,
                                'children' => [$merchant->financial_wechat->wechat ?? '未填写']
                            ],
                            [
                                'type' => 'img',
                                'title' => '收款二维码：',
                                'native' => false,
                                'attrs' => ['src' => $merchant->financial_wechat->wechat_code ?? ''],
                                'style' => ['width' => '86px', 'height' => '48px']
                            ],
                        ]
                    ],
                    [
                        'value' => 3,
                        'rule' => [
                            [
                                'type' => 'span',
                                'title' => '姓名：',
                                'native' => false,
                                'children' => [$merchant->financial_alipay->name ?? '未填写']
                            ],
                            [
                                'type' => 'span',
                                'title' => '支付宝账号：',
                                'native' => false,
                                'children' => [$merchant->financial_alipay->alipay ?? '未填写']
                            ],
                            [
                                'type' => 'img',
                                'title' => '收款二维码：',
                                'native' => false,
                                'attrs' => ['src' => $merchant->financial_alipay->alipay_code ?? ''],
                                'style' => ['width' => '86px', 'height' => '48px']
                            ],
                        ]
                    ],

                ]),
            Elm::number('extract_money', '申请金额:')->value($extract_minimum_num)->required('请输入申请金额'),
        ]);
        return $form->setTitle('申请转账');
    }

    /**
     * 保存申请
     * @param int $merId
     * @param array $data
     * @author Qinii
     * @day 3/19/21
     */
    public function saveApply(int $merId, array $data)
    {
        $make = app()->make(MerchantRepository::class);
        $merchant = $make->search(['mer_id' => $merId])->field('mer_id,mer_name,mer_money,financial_bank,financial_wechat,financial_alipay')->find();

        if ($merchant['mer_money'] <= 0) throw new ValidateException('余额不足');

        if ($data['financial_type'] == 1) {
            $financial_account = $merchant->financial_bank;
        } elseif ($data['financial_type'] == 2) {
            $financial_account = $merchant->financial_wechat;
        } elseif ($data['financial_type'] == 3) {
            $financial_account = $merchant->financial_alipay;
        }
        if (empty($financial_account)) throw new ValidateException('未填写转账信息');

        $extract_maxmum_num = systemConfig('extract_maxmum_num');
        if ($extract_maxmum_num > 0 && $data['extract_money'] > $extract_maxmum_num) throw new ValidateException('单次申请金额不得大于' . $extract_maxmum_num . '元');
        //最低提现额度
        $extract_minimum_line = systemConfig('extract_minimum_line') ? systemConfig('extract_minimum_line') : 0;
        $_line = bcsub($merchant->mer_money, $extract_minimum_line, 2);
        if ($_line < $extract_minimum_line) throw new ValidateException('余额大于' . $extract_minimum_line . '才可提现');
        if ($data['extract_money'] > $_line) throw new ValidateException('提现金额大于可提现金额');

        //最低提现金额
        $extract_minimum_num = systemConfig('extract_minimum_num');
        if ($data['extract_money'] < $extract_minimum_num) throw new ValidateException('最低提现金额' . $extract_minimum_num);

        //可提现金额
        $_line = bcsub($merchant->mer_money, $extract_minimum_line, 2);
        if ($_line < 0) throw new ValidateException('余额大于' . $extract_minimum_line . '才可提现');

        //最低提现金额
        if ($data['extract_money'] < $extract_minimum_num) throw new ValidateException('最低提现金额' . $extract_minimum_num);

        //不足提现最低金额
        if ($_line < $extract_minimum_num) throw new ValidateException('提现金额不足');

        $_money = bcsub($merchant['mer_money'], $data['extract_money'], 2);

        $sn = date('YmdHis' . $merId);
        $ret = [
            'status' => 0,
            'mer_id' => $merId,
            'mer_money' => $_money,
            'financial_sn' => $sn,
            'extract_money' => $data['extract_money'],
            'financial_type' => $data['financial_type'],
            'financial_account' => json_encode($financial_account, JSON_UNESCAPED_UNICODE),
            'financial_status' => 0,
            'mer_admin_id' => $data['mer_admin_id'],
            'mark' => $datap['mark'] ?? '',
            'refusal' => '',
        ];
        Db::transaction(function () use ($merId, $ret, $data, $make) {
            $this->dao->create($ret);
            $make->subMoney($merId, (float)$data['extract_money']);
        });
    }


    /**
     * 申请退保证金
     *
     * @param int $merId 商户 ID。
     * @param int $adminId 商户管理员 ID。
     * @param array $account 线下收款账号信息。
     * @return mixed 返回事务执行结果。
     * @throws ValidateException 退款条件不满足、线下收款信息缺失或核心字段写入未授权时抛出。
     * @author Qinii
     * @day 2023/5/10
     */
    public function refundMargin($merId, $adminId, $account)
    {
        $merchant = app()->make(MerchantRepository::class)->get($merId);
        $res = $this->checkRefundMargin($merId, $adminId, true, $account);
        if ($res['offline'] && (!$account['type'] || !$account['name'] || !$account['code'] || !$account['pic'])) {
            throw new ValidateException('请填写线下收款信息');
        }
        $financial = $res['financial'];
        $bill = [
            'title' => '申请退保证金',
            'number' => $merchant->margin,
            'balance' => 0,
            'mark' => '【 操作者：' . request()->adminId() . '|' . request()->adminInfo()->real_name . '】',
            'mer_id' => $merchant->mer_id,
        ];
        $userBillRepository = app()->make(UserBillRepository::class);
        return Db::transaction(function () use ($financial, $merchant, $bill, $userBillRepository) {
            $this->dao->insertAll($financial);
            $merchant->margin = 0;
            $merchant->is_margin = -1;
            app()->make(MerchantCoreService::class)->writeMerchant((int)$merchant->mer_id, ['margin', 'is_margin'], function () use ($merchant) {
                $merchant->save();
            }, 'financial_refund_margin_apply');
            $userBillRepository->bill(0, 'mer_margin', 'margin_status', 0, $bill);
        });
    }

    /**
     * 检查并处理退款保证金
     *
     * 该方法用于检查商家是否有足够的保证金可以退款，并根据情况处理线上和线下的退款。
     * 它首先查询商家的信息以及相关的保证金订单，然后计算可退金额并分别处理线上和线下的退款操作。
     *
     * @param string $merId 商家ID
     * @param string $adminId 管理员ID
     * @param bool $crate 是否创建退款订单，默认为false
     * @param array $account 如果需要线下退款，传入退款账户信息
     * @return array 包含线上退款金额、线下退款金额、退款订单信息和退款类型信息的数组
     * @throws ValidateException 如果商家无法退款或重复申请，则抛出验证异常
     */
    public function checkRefundMargin($merId, $adminId, $crate = false, $account = [])
    {
        /**
         * 获取线上支付的订单
         * 检查线上支付的订单是否够退当前的保证金
         * 优先线上退款，剩余的金额都走线下退款
         */
        $merchant = app()->make(MerchantRepository::class)->getWhere(['mer_id' => $merId], '*', [
                'marginOrder' => function ($query) {
                    $query->where('status', 1)->where('pay_type', '<>', ServeOrderRepository::PAY_TYPE_SYS)->field('order_id,order_sn,pay_price,pay_type,mer_id');
                }]
        );

        if ($merchant['is_margin'] == -1) throw new ValidateException('请勿重复申请');
        if (!in_array($merchant['is_margin'], [10, -10]) || $merchant['margin'] <= 0)
            throw new ValidateException('无可退保证金');
        $orderList = $merchant->marginOrder;
        //需退金额
        $extract_money = $merchant->margin;
        $financial = $info = [];
        $online = $offline = 0;
        if ($orderList) {
            foreach ($orderList as $order) {
                $refund_price = bcsub($order->pay_price, $extract_money, 2) < 0 ? $order->pay_price : $merchant->margin;
                $extract_money = bcsub($extract_money, $refund_price, 2);
                $online = bcadd($refund_price, $online, 2);
                if ($crate) {
                    $financial[] = $this->payOrderRefund($merchant, $adminId, $refund_price, $order);
                }
                if (bccomp($extract_money, 0, 2) == 0) break;
            }
        }

        if (bccomp($extract_money, 0, 2) == 1) {
            $offline = $extract_money;
            if ($crate) {
                $financial[] = $this->payOrderRefund($merchant, $adminId, $extract_money, null, $account);
            }
            $info = $this->getType($merchant);
        }

        return compact('online', 'offline', 'financial', 'info');

    }

    /**
     * 根据商家的财务类型获取相应的财务信息。
     *
     * 本函数通过商家的财务类型来确定商家的收款方式，并返回相应的收款信息。
     * 支持的收款方式包括银行、微信和支付宝。每种收款方式返回的信息包括名称、代码和图片标识。
     * 如果商家未设置财务类型，则默认返回空的收款信息。
     *
     * @param object $merchant 商家对象，包含财务类型及相关信息。
     * @return array 返回一个包含收款方式名称、代码和图片的数组。
     */
    public function getType($merchant)
    {
        switch ($merchant->financial_type) {
            case 1:
                $arr = [
                    'name' => $merchant->financial_bank->name ?? '',
                    'code' => $merchant->financial_bank->bank ?? '',
                    'pic' => $merchant->financial_bank->bank_code ?? '',
                ];
                break;
            case 2:
                $arr = [
                    'name' => $merchant->financial_wechat->name ?? '',
                    'code' => $merchant->financial_wechat->wechat ?? '',
                    'pic' => $merchant->financial_wechat->wechat_code ?? '',
                ];
                break;
            case 3:
                $arr = [
                    'name' => $merchant->financial_alipay->name ?? '',
                    'code' => $merchant->financial_alipay->alipay ?? '',
                    'pic' => $merchant->financial_alipay->alipay_code ?? '',
                ];
                break;
            default:
                $arr = ['name' => '', 'code' => '', 'pic' => '',];
                break;
        }
        $arr['type'] = $merchant->financial_type ?: 1;
        return $arr;
    }

    /**
     * 处理订单退款逻辑
     *
     * 本函数用于生成订单退款的相关信息。如果未提供订单信息或账户信息，则会根据退款金额和商家信息生成默认的订单和账户数据。
     * 主要用于在退款操作时，生成必要的财务记录和订单记录，以便后续的退款处理和审计。
     *
     * @param object $merchant 商家信息对象，包含商家ID等关键信息。
     * @param int $adminId 管理员ID，用于标识进行退款操作的管理员。
     * @param float $refund_price 退款金额，指定本次退款的金额。
     * @param array $order 可选的订单信息数组，包含订单ID、订单号、支付金额、支付类型等信息。
     * @param array $account 可选的账户信息数组，用于指定退款账户的相关信息。
     * @return array 返回一个包含退款相关信息的数组，包括状态、商家ID、商家金额、财务流水号、提取金额、财务类型、财务账户信息、财务状态、管理员ID、备注和类型等字段。
     */
    public function payOrderRefund($merchant, $adminId, $refund_price, $order = [], $account = [])
    {
        // 如果订单信息为空，则生成默认的订单信息
        if (!$order) {
            $order = [
                'order_id' => 0,
                'order_sn' => '',
                'pay_price' => $refund_price,
                'pay_type' => ServeOrderRepository::PAY_TYPE_SYS,
                'mer_id' => $merchant->mer_id,
                'account' => $account
            ];
        }

        // 生成并返回退款相关信息数组
        return [
            'status' => 0,
            'mer_id' => $merchant->mer_id,
            'mer_money' => 0,
            'financial_sn' => 'mm' . date('YmdHis' . $merchant->mer_id),
            'extract_money' => $refund_price,
            'financial_type' => $order['pay_type'],
            'financial_account' => json_encode($order, JSON_UNESCAPED_UNICODE),
            'financial_status' => 0,
            'mer_admin_id' => $adminId,
            'mark' => '',
            'type' => 1
        ];
    }


    /**
     *  商户列表
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     * @author Qinii
     * @day 3/19/21
     */
    public function getAdminList(array $where, int $page, int $limit)
    {
        $where['is_del'] = 0;
        $query = $this->dao->search($where)->with([
            'merchant' => function ($query) {
                $query->field('mer_id,mer_name,is_trader,mer_avatar,type_id,mer_phone,mer_address,is_margin,margin,real_name,ot_margin');
                $query->with([
                    'merchantType',
                    'marginOrder' => function ($query) {
                        $query->field('order_id,order_sn,pay_price')->where('status', 10);
                    }
                ]);
            }
        ]);
        $count = $query->count();
        $list = $query->page($page, $limit)->select();

        return compact('count', 'list');
    }


    /**
     * 取消/拒绝 变更状态返还余额
     * @param $merId
     * @param $id
     * @param $data
     * @author Qinii
     * @day 3/19/21
     */
    public function cancel(?int $merId, int $id, array $data)
    {
        $where = [
            'financial_id' => $id,
            'is_del' => 0,
            'status' => 0
        ];
        if ($merId) $where['mer_id'] = $merId;
        $res = $this->dao->getWhere($where);
        if (!$res) throw new ValidateException('数据不存在');
        if ($res['financial_status'] == 1) throw new ValidateException('当前状态无法完成此操作');
        $merId = $merId ?? $res['mer_id'];
        Db::transaction(function () use ($merId, $res, $id, $data) {
            $this->dao->update($id, $data);
            app()->make(MerchantRepository::class)->addMoney($merId, (float)$res['extract_money']);
        });
    }

    /**
     * 标记商家财务状态的表单生成方法
     *
     * 本方法用于生成一个用于修改商家财务状态备注的表单。通过给定的商家ID，
     * 获取当前备注信息，并构建一个表单以允许用户输入新的备注。
     * 表单提交的URL是根据当前商家ID动态生成的，确保了表单提交的目标地址与商家ID的一致性。
     *
     * @param int $id 商家的唯一标识ID，用于获取商家的当前备注信息。
     * @return object 返回一个包含表单HTML代码的对象，该对象还包括表单的标题和验证规则。
     */
    public function markForm($id)
    {
        // 通过商家ID获取当前的备注信息
        $data = $this->dao->get($id);

        // 构建表单提交的URL，确保表单提交时会带上正确的商家ID
        $form = Elm::createForm(Route::buildUrl('merchantFinancialMark', ['id' => $id])->build());

        // 设置表单的验证规则，这里仅包括一个文本输入框用于输入备注信息
        $form->setRule([
            Elm::text('mark', '备注：', $data['mark'])->placeholder('请输入备注')->required('请输入备注'),
        ]);

        // 设置表单的标题为“修改备注”，明确表单的功能
        return $form->setTitle('修改备注');
    }

    /**
     * 创建管理员标记表单
     *
     * 该方法用于生成一个用于修改管理员标记的表单。通过给定的ID获取相关数据，
     * 并使用这些数据来预填充表单字段。表单提交的URL是根据当前ID动态生成的，
     * 保证了表单提交的目标地址与当前处理的数据ID一致。
     *
     * @param int $id 数据ID，用于获取特定数据并预填充表单
     * @return \FormBuilder\Form|\Phper6\Elm\Form
     */
    public function adminMarkForm($id)
    {
        // 根据ID获取数据，用于预填充表单
        $data = $this->dao->get($id);

        // 创建表单，并设置表单提交的URL
        $form = Elm::createForm(Route::buildUrl('systemFinancialMark', ['id' => $id])->build());

        // 设置表单的验证规则，包括一个文本字段用于输入管理员标记
        $form->setRule([
            Elm::text('admin_mark', '备注：', $data['admin_mark'])->placeholder('请输入备注')->required('请输入备注'),
        ]);

        // 设置表单的标题
        return $form->setTitle('修改备注');
    }

    /**
     * 创建管理员标记表单
     *
     * 该方法用于生成一个用于管理员标记的表单，表单的主要目的是允许管理员对特定记录添加备注。
     * 通过传入的ID获取相关数据，并基于这些数据构建表单，表单提交的目标是系统设定的备注修改路由。
     *
     * @param int $id 记录的唯一标识符，用于获取该记录的当前备注信息。
     * @return \FormBuilder\Form|string
     */
    public function adminMarginMarkForm($id)
    {
        // 根据ID获取记录的信息，主要用于获取当前的管理员备注。
        $data = $this->dao->get($id);

        // 构建表单的URL，指向系统中处理备注修改的路由，其中ID作为参数进行传递。
        $form = Elm::createForm(Route::buildUrl('systemMarginRefundMark', ['id' => $id])->build());

        // 设置表单的验证规则，这里主要是一个文本输入框用于管理员输入备注信息。
        // 表单字段的名称、标签和默认值分别设置，同时输入框设置为必填。
        $form->setRule([
            Elm::text('admin_mark', '备注：', $data['admin_mark'])->placeholder('请输入备注')->required('请输入备注'),
        ]);

        // 设置表单的标题为“修改备注”，明确表单的操作目的。
        return $form->setTitle('修改备注');
    }

    /**
     * 构建保证金退还审核表单
     *
     * @param int $id 保证金订单ID
     * @return mixed 返回表单元素
     *
     * 此函数用于生成特定保证金订单的审核表单，表单中包含商户信息、保证金相关金额信息
     * 以及审核选项。通过此表单，审核人员可以查看相关详情并进行同意或拒绝的审核操作。
     */
    public function statusForm($id)
    {
        $data = $this->dao->get($id);
        if (!$data['merchant']->marginOrder) throw new ValidateException('未查询到缴费记录');
        if ($data['status'] !== 0) throw new ValidateException('请勿重复审核');
        $form = Elm::createForm(Route::buildUrl('systemMarginRefundSwitchStatus', ['id' => $id])->build());
        $rule = [
            [
                'type' => 'span',
                'title' => '商户名称：',
                'native' => false,
                'children' => [(string)$data['merchant']->mer_name]
            ],
            [
                'type' => 'span',
                'title' => '商户ID：',
                'native' => false,
                'children' => [(string)$data['mer_id']]
            ],
            [
                'type' => 'span',
                'title' => '店铺类型：',
                'native' => false,
                'children' => [(string)$data['merchant']->merchantType->type_name]
            ],
            [
                'type' => 'span',
                'title' => '保证金额度：',
                'native' => false,
                'children' => [(string)$data['financial_account']->pay_price]
            ],
            [
                'type' => 'span',
                'title' => '扣费金额：',
                'native' => false,
                'children' => [(string)bcsub($data['financial_account']->pay_price, $data['extract_money'], 2)]
            ],
            [
                'type' => 'span',
                'title' => '退回金额：',
                'native' => false,
                'children' => [(string)$data['extract_money']]
            ],
            [
                'type' => 'div',
                'title' => '退回方式：',
                'native' => false,
                'style' => 'white-space:pre-wrap',
                'children' => [(string)$data['financial_type'] == ServeOrderRepository::PAY_TYPE_SYS ? '线下转账' : '线上退回',]
            ],
        ];
        if ($data['financial_type'] == ServeOrderRepository::PAY_TYPE_SYS && isset($data['financial_account']->account) && $data['financial_account']->account) {
            $type = $data['financial_account']->account->type;
            $rule[] = [
                'type' => 'span',
                'title' => '收款人姓名：',
                'native' => false,
                'style' => 'white-space:pre-wrap',
                'children' => [(string)$data['financial_account']->account->name ?? '']
            ];
            $rule[] = [
                'type' => 'span',
                'title' => ($type == 1) ? '收款银行：' : (($type == 2) ? '微信号：' : '支付宝账号：'),
                'native' => false,
                'children' => [(string)$data['financial_account']->account->code ?? '']
            ];

            if ($type == 1) {
                $rule[] = [
                    'type' => 'span',
                    'title' => '收款银行卡：',
                    'native' => false,
                    'children' => [(string)$data['financial_account']->account->pic ?? '']
                ];
            } else {
                $rule[] = [
                    'type' => 'img',
                    'title' => '收款二维码：',
                    'native' => false,
                    'attrs' => ['src' => $data['merchant']->financial_wechat->wechat_code ?? ''],
                    'style' => ['width' => '100px', 'height' => '100px']
                ];
            }
        }

        $rule[] = Elm::radio('status', '审核：', -1)->setOptions([
            ['value' => 1, 'label' => '同意'],
            ['value' => -1, 'label' => '拒绝'],
        ])->control([
            [
                'value' => -1,
                'rule' => [
                    Elm::input('refusal', '拒绝原因：')->required('请输入拒绝原因'),
                ]
            ],]);
        $form->setRule($rule);

        return $form->setTitle('退保证金审核');
    }

    /**
     * 详情
     * @param $id
     * @return array|\think\Model|null
     * @author Qinii
     * @day 4/22/21
     */
    public function detail($id, $merId = 0)
    {
        $where[$this->dao->getPk()] = $id;
        if ($merId) $where['mer_id'] = $merId;
        $data = $this->dao->getSearch($where)->with(['merchant' => function ($query) {
            $query->field('mer_id,mer_name,mer_avatar');
        }])->find();
        if (!$data) throw new ValidateException('数据不存在');
        return $data;
    }

    /**
     * 头部统计
     * @return array
     * @author Qinii
     * @day 4/22/21
     */
    public function getTitle($where)
    {
        $make = app()->make(MerchantRepository::class);
        //应付商户金额 = 所有商户的余额之和
        $all = $make->search(['is_del' => 0])->sum('mer_money');
        //商户可提现金额 = （每个商户的余额 - 平台设置的最低提现额度） 之和
        $extract_minimum_line = systemConfig('extract_minimum_line') ?: 0;
        $ret = $make->search(['is_del' => 0])->where('mer_money', '>', $extract_minimum_line)
            ->field("sum(mer_money - $extract_minimum_line) as  money")
            ->find();
        $money = $ret->money;
        //申请转账的商户数 = 申请提现且未转账的商户数量
        $where['financial_status'] = 0;
        $query = $this->dao->getSearch($where)->where('status', '>', -1);
        $count = $query->group('mer_id')->count();
        //申请转账的总金额 = 申请提现已通过审核，且未转账的申请金额 之和
        $where['status'] = 1;
        $all_ = $this->dao->search($where)->sum('extract_money');
        $where['status'] = 0;
        $all_0 = $this->dao->search($where)->sum('extract_money');
        $merLockMoney = app()->make(UserBillRepository::class)->merchantLickMoney();
        $stat = [
            [
                'className' => 'el-icon-s-goods',
                'count' => $all,
                'field' => '元',
                'name' => '应付商户金额'
            ],
            [
                'className' => 'el-icon-s-goods',
                'count' => $money,
                'field' => '元',
                'name' => '商户可提现金额'
            ],
            [
                'className' => 'el-icon-s-goods',
                'count' => $count,
                'field' => '个',
                'name' => '申请转账的商户数'
            ],
            [
                'className' => 'el-icon-s-goods',
                'count' => $all_,
                'field' => '元',
                'name' => '申请转账的总金额'
            ],
            [
                'className' => 'el-icon-s-goods',
                'count' => $all_0,
                'field' => '元',
                'name' => '待审核的总金额'
            ],
            [
                'className' => 'el-icon-s-goods',
                'count' => $merLockMoney,
                'field' => '元',
                'name' => '商户冻结金额'
            ],
        ];

        return $stat;
    }


    /**
     * 修改状态
     *
     * @param int $id 财务申请 ID。
     * @param int $type 财务申请类型。
     * @param array $data 状态审核数据。
     * @return int
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws ValidateException 审核数据不存在、业务校验失败或核心字段写入未授权时抛出。
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/23
     */
    public function switchStatus($id, $type, $data)
    {
        $where = [
            'financial_id' => $id,
            'is_del' => 0,
            'status' => 0,
            'type' => $type
        ];

        $res = $this->dao->getWhere($where);
        if (!$res) throw new ValidateException('数据不存在');
        switch ($type) {
            case 0:
                if ($data['status'] == -1) $this->cancel(null, $id, $data);
                break;
            case 1:
                $bill['number'] = $res['extract_money'];
                $bill['mer_id'] = $res->merchant->mer_id;
                if ($data['status'] == 1) {
                    $this->agree($res);
                    $data['financial_status'] = 1;
                    $tempId = 'REFUND_MARGIN_SUCCESS';
                    $bill['title'] = '审核通过';
                    $bill['balance'] = 0;
                    $bill['mark'] = '【 操作者：' . request()->adminId() . '|' . request()->adminInfo()->real_name . '】';
                    $pm = 0;
                } else if ($data['status'] == -1) {
                    $number = bcadd($res->merchant->margin, $res->extract_money, 2);
                    $res->merchant->is_margin = 10;
                    $res->merchant->margin = $number;
                    app()->make(MerchantCoreService::class)->writeMerchant((int)$res->merchant->mer_id, ['is_margin', 'margin'], function () use ($res) {
                        $res->merchant->save();
                    }, 'financial_refund_margin_reject');
                    $tempId = 'REFUND_MARGIN_FAIL';
                    $bill['title'] = '审核拒绝';
                    $bill['balance'] = $number;
                    $bill['mark'] = $data['refusal'] . '【 操作者：' . request()->adminId() . '|' . request()->adminInfo()->real_name . '】';
                    $pm = 1;
                }
                $userBillRepository = app()->make(UserBillRepository::class);
                $userBillRepository->bill(0, 'mer_margin', 'margin_status', $pm, $bill);
                Queue::push(SendSmsJob::class, [
                    'tempId' => $tempId,
                    'id' => [
                        'name' => $res->merchant->mer_name,
                        'time' => $res->create_time,
                        'phone' => $res->merchant->mer_phone
                    ]]);
                break;
        }
//        Queue::push(SendSmsJob::class, ['tempId' => 'TRANSFER_ACCOUNTS_SUCCESS', 'id' => $id]);
        return $this->dao->update($id, $data);
    }

    /**
     * 根据条件查询退款信息
     *
     * 本函数用于查询特定财务id、类型和状态下的退款信息。通过传入的id，精确查询满足条件的退款记录。
     * 主要用于前端展示退款详情，或相关业务逻辑中对特定退款记录的查询操作。
     *
     * @param int $id 财务id，用于查询特定的退款记录
     * @return array 查询到的退款信息，包含所有字段及关联的merchant信息
     * @throws ValidateException 如果查询不到满足条件的记录，则抛出异常
     */
    public function refundShow($id)
    {
        // 定义查询条件，包括财务id、类型和状态
        $where = [
            'financial_id' => $id,
            'type' => 1,
            'status' => 1
        ];

        // 根据查询条件获取满足条件的所有字段，并包含merchant的merchantType字段
        $res = $this->dao->getWhere($where, '*', ['merchant' => ['merchantType']]);

        // 如果查询结果为空，则抛出异常提示数据不存在
        if (!$res) throw new ValidateException('数据不存在');

        // 返回查询结果
        return $res;
    }

    /**
     * 同意退保证金
     *
     * @param mixed $res 财务申请模型，需包含 merchant、financial_account 等关联数据。
     * @return void
     * @throws ValidateException 原路退款金额校验失败或核心字段写入未授权时抛出。
     * @author Qinii
     * @day 1/27/22
     */
    public function agree($res)
    {
        $data = [];
        $order = null;
        //线上原路退回
        if ($res['financial_type'] !== ServeOrderRepository::PAY_TYPE_SYS) {
            //验证
            $comp = bccomp($res['financial_account']->pay_price, $res['extract_money'], 2);
            if ($comp == -1)
                throw new ValidateException('申请退款金额：' . $res['extract_money'] . ',大于支付保证金：' . $res['financial_account']->pay_price);
            //退款
            $data = [
                'refund_id' => $res['financial_account']->order_sn,
                'pay_price' => $res['financial_account']->pay_price,
                'refund_price' => $res['extract_money']
            ];
            $order = app()->make(ServeOrderRepository::class)->get($res['financial_account']->order_id);

        }
        $has = $this->dao->getSearch([])
            ->where('status', 0)
            ->where('mer_id', $res->mer_id)
            ->where('type', 1)
            ->where('financial_id', '<>', $res->financial_id)
            ->count();

        Db::transaction(function () use ($res, $data, $order, $has) {
            if ($data) {
                Payment::instance()->payOrderRefund($res['financial_account']->order_sn, $data);
                //改订单
                $order->status = 20;
                $order->save();
            }
            /*
            * 如果存在，则有其他正在申请退款的订单；
            * 保证金状态不变；
            * 否则
            * 变更保证金状态&关闭店铺
            *
            */
            if (!$has) {
                $is_margin = $res['merchant']['merchantType']['is_margin'];
                $res->merchant->is_margin = $is_margin;
                $res->merchant->margin = $res['merchant']['merchantType']['margin'];
                $res->merchant->ot_margin = $res['merchant']['merchantType']['margin'];
                if ($is_margin == 1) {
                    $res->merchant->mer_state = 0;
                    Queue::push(ChangeMerchantStatusJob::class, $res['mer_id']);
                }
                app()->make(MerchantCoreService::class)->writeMerchant((int)$res->merchant->mer_id, ['is_margin', 'margin', 'ot_margin', 'mer_state'], function () use ($res) {
                    $res->merchant->save();
                }, 'financial_refund_margin_agree');
            }
        });

    }
}
