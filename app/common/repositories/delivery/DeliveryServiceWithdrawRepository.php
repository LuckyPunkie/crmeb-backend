<?php

namespace app\common\repositories\delivery;

use app\common\dao\delivery\DeliveryServiceWithdrawDao;
use app\common\model\delivery\DeliveryService;
use app\common\model\delivery\DeliveryServiceWithdraw;
use app\common\repositories\BaseRepository;
use app\common\repositories\user\UserBillRepository;
use app\common\repositories\user\UserRepository;
use app\common\repositories\wechat\WechatUserRepository;
use crmeb\exceptions\PayException;
use crmeb\services\wechat\Payment;
use FormBuilder\Factory\Elm;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Route;

class DeliveryServiceWithdrawRepository extends BaseRepository
{
    public function __construct(DeliveryServiceWithdrawDao $dao)
    {
        $this->dao = $dao;
    }

    public function create(int $serviceId, int $uid, int $merId, array $data): DeliveryServiceWithdraw
    {
        return Db::transaction(function () use ($serviceId, $uid, $merId, $data) {
            $service = DeliveryService::getDB()
                ->where('service_id', $serviceId)
                ->where('uid', $uid)
                ->where('is_del', 0)
                ->lock(true)
                ->find();
            if (!$service) {
                throw new ValidateException('配送员身份不存在');
            }

            $serviceType = $merId === 0 ? DeliveryService::TYPE_PLATFORM : DeliveryService::TYPE_MERCHANT;
            if ((int)$service->type !== $serviceType || (int)$service->mer_id !== $merId) {
                throw new ValidateException('配送员身份与商户不匹配');
            }

            $paymentMethod = (int)$data['payment_method'];
            $this->checkPaymentMethod($serviceType, $paymentMethod);
            $paymentInfo = $paymentMethod === DeliveryServiceWithdraw::PAYMENT_METHOD_USER_BALANCE
                ? ''
                : $this->normalizePaymentInfo($data['payment_info'] ?? '');

            $amount = bcadd((string)$data['amount'], '0', 2);
            if (bccomp($amount, '0.00', 2) <= 0) {
                throw new ValidateException('提现金额必须大于0');
            }
            $availableAmount = bcadd((string)($service->available_amount ?? '0'), '0', 2);
            if (bccomp($availableAmount, $amount, 2) < 0) {
                throw new ValidateException('可提现余额不足');
            }

            $service->available_amount = bcsub($availableAmount, $amount, 2);
            $service->frozen_amount = bcadd((string)($service->frozen_amount ?? '0'), $amount, 2);
            $service->save();

            return $this->dao->create([
                'withdraw_sn' => $this->getSn(),
                'service_id' => $serviceId,
                'uid' => $uid,
                'mer_id' => $merId,
                'service_type' => $serviceType,
                'amount' => $amount,
                'status' => DeliveryServiceWithdraw::STATUS_PENDING,
                'payment_method' => $paymentMethod,
                'payment_info' => $paymentInfo,
                'remark' => $data['remark'] ?? '',
            ]);
        });
    }

    public function getList(array $where, int $page, int $limit): array
    {
        $query = $this->dao->search($where)
            ->order('w.create_time DESC,w.delivery_service_withdraw_id DESC');
        $count = (clone $query)->count();
        $list = $query->page($page, $limit)->select();
        return compact('count', 'list');
    }

    /**
     * 获取符合列表筛选条件的全部提现记录，用于导出。
     */
    public function getExportList(array $where): array
    {
        $query = $this->dao->search($where)
            ->order('w.create_time DESC,w.delivery_service_withdraw_id DESC');

        return [
            'count' => (clone $query)->count(),
            'list' => $query->select(),
        ];
    }

    public function getStatusStatistics(array $where): array
    {
        unset($where['status']);
        $rows = $this->dao->search($where)
            ->field('w.status,COUNT(w.delivery_service_withdraw_id) AS count')
            ->group('w.status')
            ->select()
            ->toArray();
        $counts = [];
        foreach ($rows as $row) {
            $counts[(int)$row['status']] = (int)$row['count'];
        }

        $statistics = [];
        foreach (DeliveryServiceWithdraw::STATUS_TEXT as $status => $title) {
            $statistics[] = [
                'status' => $status,
                'count' => $counts[$status] ?? 0,
                'title' => $title,
            ];
        }
        return $statistics;
    }

    public function getDetail(int $id, ?int $serviceType = null, ?int $merId = null, ?int $uid = null)
    {
        $where = [];
        if ($serviceType !== null) {
            $where['service_type'] = $serviceType;
        }
        if ($merId !== null) {
            $where['mer_id'] = $merId;
        }
        if ($uid !== null) {
            $where['uid'] = $uid;
        }
        return $this->dao->search($where)
            ->with(['user'])
            ->where('w.delivery_service_withdraw_id', $id)
            ->find();
    }

    public function updatePending(int $id, int $serviceType, int $merId, array $data): bool
    {
        return Db::transaction(function () use ($id, $serviceType, $merId, $data) {
            $withdraw = $this->getLockedWithdraw($id, $serviceType, $merId);
            $this->checkPending($withdraw);
            $paymentMethod = (int)$data['payment_method'];
            $this->checkPaymentMethod($serviceType, $paymentMethod);

            $amount = bcadd((string)$data['amount'], '0', 2);
            if (bccomp($amount, '0.00', 2) <= 0) {
                throw new ValidateException('提现金额必须大于0');
            }

            $service = $this->getLockedService((int)$withdraw->service_id, (int)$withdraw->uid, $serviceType, $merId);
            $availableAmount = bcadd((string)($service->available_amount ?? '0'), '0', 2);
            $frozenAmount = bcadd((string)($service->frozen_amount ?? '0'), '0', 2);
            $difference = bcsub($amount, (string)$withdraw->amount, 2);
            if (bccomp($difference, '0.00', 2) > 0) {
                if (bccomp($availableAmount, $difference, 2) < 0) {
                    throw new ValidateException('可提现余额不足');
                }
                $service->available_amount = bcsub($availableAmount, $difference, 2);
                $service->frozen_amount = bcadd($frozenAmount, $difference, 2);
            } elseif (bccomp($difference, '0.00', 2) < 0) {
                $refundAmount = bcmul($difference, '-1', 2);
                if (bccomp($frozenAmount, $refundAmount, 2) < 0) {
                    throw new ValidateException('冻结金额不足，无法修改提现申请');
                }
                $service->available_amount = bcadd($availableAmount, $refundAmount, 2);
                $service->frozen_amount = bcsub($frozenAmount, $refundAmount, 2);
            }
            $service->save();

            $this->dao->update($id, [
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'payment_info' => $paymentMethod === DeliveryServiceWithdraw::PAYMENT_METHOD_USER_BALANCE
                    ? ''
                    : $this->normalizePaymentInfo($data['payment_info'] ?? ''),
                'remark' => $data['remark'] ?? '',
                'update_time' => date('Y-m-d H:i:s'),
            ]);
            return true;
        });
    }

    public function cancel(int $id, int $serviceType, int $merId): bool
    {
        return Db::transaction(function () use ($id, $serviceType, $merId) {
            $withdraw = $this->getLockedWithdraw($id, $serviceType, $merId);
            $this->checkPending($withdraw);
            $service = $this->getLockedService((int)$withdraw->service_id, (int)$withdraw->uid, $serviceType, $merId);
            $this->refundFrozenAmount($service, (string)$withdraw->amount);
            $this->dao->update($id, [
                'status' => DeliveryServiceWithdraw::STATUS_CANCELLED,
                'update_time' => date('Y-m-d H:i:s'),
            ]);
            return true;
        });
    }

    public function audit(int $id, int $serviceType, int $merId, int $adminId, array $data): bool
    {
        return Db::transaction(function () use ($id, $serviceType, $merId, $adminId, $data) {
            $withdraw = $this->getLockedWithdraw($id, $serviceType, $merId);
            $this->checkPending($withdraw);
            $service = $this->getLockedService((int)$withdraw->service_id, (int)$withdraw->uid, $serviceType, $merId);
            $auditTime = date('Y-m-d H:i:s');
            $auditStatus = (int)$data['status'];
            if (!in_array($auditStatus, [
                DeliveryServiceWithdraw::STATUS_REJECTED,
                DeliveryServiceWithdraw::STATUS_TO_PAY,
            ], true)) {
                throw new ValidateException('审核状态错误');
            }

            if ($auditStatus === DeliveryServiceWithdraw::STATUS_REJECTED) {
                $this->refundFrozenAmount($service, (string)$withdraw->amount);
                $this->dao->update($id, [
                    'status' => DeliveryServiceWithdraw::STATUS_REJECTED,
                    'audit_admin_id' => $adminId,
                    'audit_reason' => $data['audit_reason'],
                    'audit_time' => $auditTime,
                    'update_time' => $auditTime,
                ]);
                return true;
            }

            if (
                $serviceType === DeliveryService::TYPE_PLATFORM
                && $merId === 0
                && (int)$withdraw->payment_method === DeliveryServiceWithdraw::PAYMENT_METHOD_USER_BALANCE
            ) {
                $frozenAmount = bcadd((string)($service->frozen_amount ?? '0'), '0', 2);
                if (bccomp($frozenAmount, (string)$withdraw->amount, 2) < 0) {
                    throw new ValidateException('冻结金额不足，无法完成提现');
                }

                $user = app()->make(UserRepository::class)
                    ->query(['uid' => (int)$withdraw->uid])
                    ->lock(true)
                    ->find();
                if (!$user) {
                    throw new ValidateException('配送员用户不存在，无法转入余额');
                }
                $balance = bcadd((string)($user->now_money ?? '0'), (string)$withdraw->amount, 2);
                $user->save(['now_money' => $balance]);
                app()->make(UserBillRepository::class)->incBill(
                    (int)$withdraw->uid,
                    UserBillRepository::CATEGORY_NOW_MONEY,
                    'delivery_service',
                    [
                        'link_id' => (int)$withdraw->delivery_service_withdraw_id,
                        'status' => 1,
                        'title' => '配送费转入余额',
                        'number' => $withdraw->amount,
                        'mark' => '平台配送费提现转入余额' . floatval($withdraw->amount) . '元',
                        'balance' => $balance,
                    ]
                );

                $service->frozen_amount = bcsub($frozenAmount, (string)$withdraw->amount, 2);
                $service->withdrawn_amount = bcadd(
                    (string)($service->withdrawn_amount ?? '0'),
                    (string)$withdraw->amount,
                    2
                );
                $service->save();

                $bill = app()->make(DeliveryServiceBillRepository::class)->createWithdrawExpend($withdraw);
                $this->dao->update($id, [
                    'status' => DeliveryServiceWithdraw::STATUS_PAID,
                    'payment_sn' => (string)$withdraw->withdraw_sn,
                    'delivery_service_bill_id' => $bill->delivery_service_bill_id,
                    'audit_admin_id' => $adminId,
                    'audit_reason' => $data['audit_reason'] ?? '',
                    'audit_time' => $auditTime,
                    'pay_time' => $auditTime,
                    'update_time' => $auditTime,
                ]);
                return true;
            }

            $this->dao->update($id, [
                'status' => DeliveryServiceWithdraw::STATUS_TO_PAY,
                'audit_admin_id' => $adminId,
                'audit_reason' => $data['audit_reason'] ?? '',
                'audit_time' => $auditTime,
                'update_time' => $auditTime,
            ]);
            return true;
        });
    }

    public function form(?int $id, int $serviceType, int $merId)
    {
        $withdraw = $id ? $this->getDetail($id, $serviceType, $merId) : null;
        if ($id && !$withdraw) {
            throw new ValidateException('提现申请不存在');
        }
        if ($withdraw && (int)$withdraw->status !== DeliveryServiceWithdraw::STATUS_PENDING) {
            throw new ValidateException('仅待审核申请可以编辑');
        }

        $isPlatform = $serviceType === DeliveryService::TYPE_PLATFORM;
        $createRoute = $isPlatform ? 'systemDeliveryServiceWithdrawCreate' : 'merchantDeliveryServiceWithdrawCreate';
        $updateRoute = $isPlatform ? 'systemDeliveryServiceWithdrawUpdate' : 'merchantDeliveryServiceWithdrawUpdate';
        $url = $id
            ? Route::buildUrl($updateRoute, ['id' => $id])->build()
            : Route::buildUrl($createRoute)->build();

        $services = DeliveryService::getDB()
            ->where('type', $serviceType)
            ->where('mer_id', $merId)
            ->where('is_del', 0)
            ->field('service_id,name,phone,available_amount')
            ->order('service_id DESC')
            ->select()
            ->toArray();
        $serviceOptions = array_map(function ($service) {
            return [
                'value' => $service['service_id'],
                'label' => $service['name'] . ' ' . $service['phone'] . '（可提现：' . ($service['available_amount'] ?? '0.00') . '）',
            ];
        }, $services);
        $paymentMethods = [
            ['value' => DeliveryServiceWithdraw::PAYMENT_METHOD_BANK, 'label' => '银行卡'],
            ['value' => DeliveryServiceWithdraw::PAYMENT_METHOD_WECHAT, 'label' => '微信'],
            ['value' => DeliveryServiceWithdraw::PAYMENT_METHOD_ALIPAY, 'label' => '支付宝'],
        ];
        if ($isPlatform) {
            $paymentMethods[] = [
                'value' => DeliveryServiceWithdraw::PAYMENT_METHOD_WECHAT_BALANCE,
                'label' => '微信转账到零钱',
            ];
            $paymentMethods[] = [
                'value' => DeliveryServiceWithdraw::PAYMENT_METHOD_USER_BALANCE,
                'label' => '转入用户余额',
            ];
        }

        $paymentInfo = $withdraw->payment_info ?? '';
        if (is_array($paymentInfo)) {
            $paymentInfo = json_encode($paymentInfo, JSON_UNESCAPED_UNICODE);
        }
        $rules = [];
        if (!$id) {
            $rules[] = Elm::select('service_id', '配送员：')->options($serviceOptions)->required('请选择配送员');
        }
        $rules[] = Elm::input('amount', '提现金额：', $withdraw->amount ?? '')->required('请输入提现金额');
        $paymentMethodRule = Elm::select('payment_method', '收款方式：', $withdraw->payment_method ?? 0)
            ->options($paymentMethods)
            ->required('请选择收款方式');
        if ($isPlatform) {
            $paymentInfoRule = function () use ($paymentInfo) {
                return Elm::textarea('payment_info', '收款信息：', $paymentInfo)->required('请填写收款信息');
            };
            $paymentMethodRule->control([
                ['value' => DeliveryServiceWithdraw::PAYMENT_METHOD_BANK, 'rule' => [$paymentInfoRule()]],
                ['value' => DeliveryServiceWithdraw::PAYMENT_METHOD_WECHAT, 'rule' => [$paymentInfoRule()]],
                ['value' => DeliveryServiceWithdraw::PAYMENT_METHOD_ALIPAY, 'rule' => [$paymentInfoRule()]],
                ['value' => DeliveryServiceWithdraw::PAYMENT_METHOD_WECHAT_BALANCE, 'rule' => [$paymentInfoRule()]],
            ]);
            $rules[] = $paymentMethodRule;
        } else {
            $rules[] = $paymentMethodRule;
            $rules[] = Elm::textarea('payment_info', '收款信息：', $paymentInfo)->required('请填写收款信息');
        }
        $rules[] = Elm::textarea('remark', '备注：', $withdraw->remark ?? '');

        return Elm::createForm($url, $rules)->setTitle($id ? '编辑提现申请' : '添加提现申请');
    }

    public function auditForm(int $id, int $serviceType, int $merId)
    {
        $withdraw = $this->getDetail($id, $serviceType, $merId);
        if (!$withdraw) {
            throw new ValidateException('提现申请不存在');
        }
        if ((int)$withdraw->status !== DeliveryServiceWithdraw::STATUS_PENDING) {
            throw new ValidateException('该提现申请已处理');
        }
        $route = $serviceType === DeliveryService::TYPE_PLATFORM
            ? 'systemDeliveryServiceWithdrawAudit'
            : 'merchantDeliveryServiceWithdrawAudit';

        return Elm::createForm(Route::buildUrl($route, ['id' => $id])->build(), [
            // 添加单选按钮组，用于选择提现申请的审核状态：通过或拒绝。
            Elm::radio('status', '审核状态：', 1)->options([
                ['value' => 1, 'label' => '通过'],
                ['value' => -1, 'label' => '拒绝']])->control([
                // 当选择拒绝时，显示文本区域，用于输入拒绝的原因。
                ['value' => -1, 'rule' => [
                    Elm::textarea('audit_reason', '拒绝原因：', '信息有误,请完善')->placeholder('请输入拒绝理由')->required('请输入拒绝理由')
                ]]
            ]),
        ])->setTitle('审核');

    }

    public function remarkForm(int $id, int $serviceType, int $merId)
    {
        $withdraw = $this->getDetail($id, $serviceType, $merId);
        if (!$withdraw) {
            throw new ValidateException('提现申请不存在');
        }
        $route = $serviceType === DeliveryService::TYPE_PLATFORM
            ? 'systemDeliveryServiceWithdrawRemark'
            : 'merchantDeliveryServiceWithdrawRemark';

        return Elm::createForm(Route::buildUrl($route, ['id' => $id])->build(), [
            Elm::textarea('remark', '备注：', $withdraw->remark ?? '')->placeholder('请输入备注'),
        ])->setTitle('修改备注');
    }

    public function updateRemark(int $id, int $serviceType, int $merId, string $remark): bool
    {
        $withdraw = $this->getDetail($id, $serviceType, $merId);
        if (!$withdraw) {
            throw new ValidateException('提现申请不存在');
        }
        $this->dao->update($id, [
            'remark' => $remark,
            'update_time' => date('Y-m-d H:i:s'),
        ]);
        return true;
    }

    public function transferForm(int $id, int $serviceType, int $merId)
    {
        $withdraw = $this->getDetail($id, $serviceType, $merId);
        if (!$withdraw) {
            throw new ValidateException('提现申请不存在');
        }
        if ((int)$withdraw->status !== DeliveryServiceWithdraw::STATUS_TO_PAY) {
            throw new ValidateException('仅待打款申请可以记录转账');
        }

        $route = $serviceType === DeliveryService::TYPE_PLATFORM
            ? 'systemDeliveryServiceWithdrawTransfer'
            : 'merchantDeliveryServiceWithdrawTransfer';
        $prefix = $serviceType === DeliveryService::TYPE_PLATFORM
            ? config('admin.admin_prefix')
            : config('admin.merchant_prefix');

        return Elm::createForm(Route::buildUrl($route, ['id' => $id])->build(), [
            Elm::input('payment_sn', '转账流水号：', $withdraw->payment_sn ?? '')->required(true)
                ->placeholder('微信转账到零钱由系统自动填写'),
            Elm::frameImages(
                'payment_vouchers',
                '转账凭证：',
                '/' . $prefix . '/setting/uploadPicture?field=payment_vouchers&type=2',
                $withdraw->payment_vouchers ?? []
            )->required(true)->maxLength(10)->width('1000px')->height('600px')->props(['footer' => false])
                ->icon('el-icon-camera')->modal(['modal' => false]),
        ])->setTitle('记录转账');
    }

    public function transfer(int $id, int $serviceType, int $merId, array $data): bool
    {
        return Db::transaction(function () use ($id, $serviceType, $merId, $data) {
            $withdraw = $this->getLockedWithdraw($id, $serviceType, $merId);
            if ((int)$withdraw->status !== DeliveryServiceWithdraw::STATUS_TO_PAY) {
                throw new ValidateException('仅待打款申请可以记录转账');
            }

            $paymentVouchers = $data['payment_vouchers'] ?? [];
            if (!is_array($paymentVouchers) || !$paymentVouchers || count($paymentVouchers) > 10) {
                throw new ValidateException('请上传1至10张转账凭证');
            }
            foreach ($paymentVouchers as $voucher) {
                if (!is_string($voucher) || trim($voucher) === '') {
                    throw new ValidateException('转账凭证格式错误');
                }
            }

            $service = $this->getLockedService(
                (int)$withdraw->service_id,
                (int)$withdraw->uid,
                $serviceType,
                $merId
            );
            $frozenAmount = bcadd((string)($service->frozen_amount ?? '0'), '0', 2);
            if (bccomp($frozenAmount, (string)$withdraw->amount, 2) < 0) {
                throw new ValidateException('冻结金额不足，无法完成提现');
            }

            $paymentSn = trim((string)($data['payment_sn'] ?? ''));
            if ((int)$withdraw->payment_method === DeliveryServiceWithdraw::PAYMENT_METHOD_WECHAT_BALANCE) {
                if ($serviceType !== DeliveryService::TYPE_PLATFORM || $merId !== 0) {
                    throw new ValidateException('商户配送员不支持微信转账到零钱');
                }
                $paymentResult = $this->merchantPay($withdraw);
                $paymentSn = is_array($paymentResult)
                    ? (string)($paymentResult['transfer_bill_no'] ?? $withdraw->withdraw_sn)
                    : (string)$withdraw->withdraw_sn;
            } elseif ($paymentSn === '') {
                throw new ValidateException('请填写转账流水号');
            }

            $service->frozen_amount = bcsub($frozenAmount, (string)$withdraw->amount, 2);
            $service->withdrawn_amount = bcadd(
                (string)($service->withdrawn_amount ?? '0'),
                (string)$withdraw->amount,
                2
            );
            $service->save();

            $bill = app()->make(DeliveryServiceBillRepository::class)->createWithdrawExpend($withdraw);
            $payTime = date('Y-m-d H:i:s');
            $this->dao->update($id, [
                'status' => DeliveryServiceWithdraw::STATUS_PAID,
                'payment_sn' => $paymentSn,
                'payment_vouchers' => json_encode(array_values($paymentVouchers), JSON_UNESCAPED_UNICODE),
                'delivery_service_bill_id' => $bill->delivery_service_bill_id,
                'pay_time' => $payTime,
                'update_time' => $payTime,
            ]);
            return true;
        });
    }

    protected function getLockedWithdraw(int $id, int $serviceType, int $merId): DeliveryServiceWithdraw
    {
        $withdraw = DeliveryServiceWithdraw::getDB()
            ->where('delivery_service_withdraw_id', $id)
            ->where('service_type', $serviceType)
            ->where('mer_id', $merId)
            ->lock(true)
            ->find();
        if (!$withdraw) {
            throw new ValidateException('提现申请不存在或无权操作');
        }
        return $withdraw;
    }

    protected function getLockedService(int $serviceId, int $uid, int $serviceType, int $merId): DeliveryService
    {
        $service = DeliveryService::getDB()
            ->where('service_id', $serviceId)
            ->where('uid', $uid)
            ->where('type', $serviceType)
            ->where('mer_id', $merId)
            ->where('is_del', 0)
            ->lock(true)
            ->find();
        if (!$service) {
            throw new ValidateException('配送员身份不存在');
        }
        return $service;
    }

    protected function checkPending(DeliveryServiceWithdraw $withdraw): void
    {
        if ((int)$withdraw->status !== DeliveryServiceWithdraw::STATUS_PENDING) {
            throw new ValidateException('仅待审核申请可以操作');
        }
    }

    protected function checkPaymentMethod(int $serviceType, int $paymentMethod): void
    {
        $allowedMethods = $serviceType === DeliveryService::TYPE_PLATFORM
            ? [
                DeliveryServiceWithdraw::PAYMENT_METHOD_BANK,
                DeliveryServiceWithdraw::PAYMENT_METHOD_WECHAT,
                DeliveryServiceWithdraw::PAYMENT_METHOD_ALIPAY,
                DeliveryServiceWithdraw::PAYMENT_METHOD_WECHAT_BALANCE,
                DeliveryServiceWithdraw::PAYMENT_METHOD_USER_BALANCE,
            ]
            : [
                DeliveryServiceWithdraw::PAYMENT_METHOD_BANK,
                DeliveryServiceWithdraw::PAYMENT_METHOD_WECHAT,
                DeliveryServiceWithdraw::PAYMENT_METHOD_ALIPAY,
            ];
        if (!in_array($paymentMethod, $allowedMethods, true)) {
            throw new ValidateException('当前配送身份不支持该收款方式');
        }
    }

    protected function normalizePaymentInfo($paymentInfo): string
    {
        if (is_array($paymentInfo)) {
            $paymentInfo = json_encode($paymentInfo, JSON_UNESCAPED_UNICODE);
        }
        if (!is_string($paymentInfo) || trim($paymentInfo) === '') {
            throw new ValidateException('请填写收款信息');
        }
        return $paymentInfo;
    }

    protected function refundFrozenAmount(DeliveryService $service, string $amount): void
    {
        $frozenAmount = bcadd((string)($service->frozen_amount ?? '0'), '0', 2);
        if (bccomp($frozenAmount, $amount, 2) < 0) {
            throw new ValidateException('冻结金额不足，无法退回提现金额');
        }
        $service->frozen_amount = bcsub($frozenAmount, $amount, 2);
        $service->available_amount = bcadd((string)($service->available_amount ?? '0'), $amount, 2);
        $service->save();
    }

    protected function merchantPay(DeliveryServiceWithdraw $withdraw)
    {
        $user = app()->make(UserRepository::class)->get((int)$withdraw->uid);
        if (!$user) {
            throw new ValidateException('配送员用户不存在');
        }
        $wechatUserRepository = app()->make(WechatUserRepository::class);
        $openid = $wechatUserRepository->idByOpenId((int)$user->wechat_user_id);
        $channelType = $openid ? 'wechat' : 'routine';
        if (!$openid) {
            $openid = $wechatUserRepository->idByRoutineId((int)$user->wechat_user_id);
        }
        if (!$openid) {
            throw new ValidateException('配送员未绑定微信，无法转账到零钱');
        }

        $paymentInfo = $withdraw->payment_info;
        $realName = is_array($paymentInfo) ? (string)($paymentInfo['real_name'] ?? '') : '';
        try {
            return Payment::merchantPay(
                $channelType,
                $openid,
                (string)$withdraw->withdraw_sn,
                (string)$withdraw->amount,
                '平台配送费提现:' . $user->nickname,
                $realName
            );
        } catch (PayException $e) {
            throw new ValidateException($e->getMessage());
        }
    }

    protected function getSn(): string
    {
        return 'dsw' . date('YmdHis') . random_int(100000, 999999);
    }
}
