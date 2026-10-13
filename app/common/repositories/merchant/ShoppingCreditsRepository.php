<?php

namespace app\common\repositories\merchant;

use think\facade\Route;
use FormBuilder\Factory\Elm;
use app\common\dao\merchant\MerchantMemberDao;
use app\common\dao\merchant\ShoppingCreditsMerchantMoneyRecordDao;
use app\common\dao\merchant\ShoppingCreditsPackageDao;
use app\common\dao\system\merchant\MerchantDao;
use app\common\dao\user\UserShoppingCreditsDao;
use app\common\dao\user\UserShoppingCreditsRecordDao;
use app\common\repositories\BaseRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 购物金账户仓储：负责充值套餐、账户余额、资金流水及商户资金记账。
 */
class ShoppingCreditsRepository extends BaseRepository
{
    public const PAY_TYPE = 'shoppingCredits';

    private ShoppingCreditsPackageDao $packageDao;
    private ShoppingCreditsMerchantMoneyRecordDao $moneyRecordDao;
    private UserShoppingCreditsDao $shoppingCreditsDao;
    private UserShoppingCreditsRecordDao $shoppingCreditsRecordDao;
    private MerchantMemberDao $memberDao;
    private MerchantDao $merchantDao;

    public function __construct(
        ShoppingCreditsPackageDao $packageDao,
        ShoppingCreditsMerchantMoneyRecordDao $moneyRecordDao,
        UserShoppingCreditsDao $shoppingCreditsDao,
        UserShoppingCreditsRecordDao $shoppingCreditsRecordDao,
        MerchantMemberDao $memberDao,
        MerchantDao $merchantDao
    ) {
        $this->packageDao = $packageDao;
        $this->moneyRecordDao = $moneyRecordDao;
        $this->shoppingCreditsDao = $shoppingCreditsDao;
        $this->shoppingCreditsRecordDao = $shoppingCreditsRecordDao;
        $this->memberDao = $memberDao;
        $this->merchantDao = $merchantDao;
    }

    /**
     * 获取商户购物金充值套餐列表，前台模式校验店铺可用并仅返回上架套餐
     * @param int $merId 商户ID
     * @param bool $front 是否前台查询（校验店铺可用性并过滤下架套餐）
     * @return array 套餐列表
     */
    public function packageList(int $merId, bool $front = false): array
    {
        if ($front && !$this->merchantIsAvailable($merId)) {
            return [];
        }
        return $this->packageDao->search()
            ->where('mer_id', $merId)
            ->when($front, fn($query) => $query->where('show_status', 1))
            ->order('sort DESC,id ASC')
            ->select()
            ->toArray();
    }

    /**
     * 按ID批量获取上架的购物金套餐，结果按传入ID顺序返回
     * @param array $ids 套餐ID列表
     * @param int|null $merId 商户ID，为空时不校验店铺可用性
     * @return array 套餐列表
     */
    public function packageListByIds(array $ids, ?int $merId = null): array
    {
        if ($merId !== null && !$this->merchantIsAvailable($merId)) {
            return [];
        }
        $rows = $this->packageDao->search()
            ->whereIn('id', $ids)
            ->when($merId !== null, fn($query) => $query->where('mer_id', $merId))
            ->where('show_status', 1)
            ->select()
            ->toArray();
        $sort = array_flip(array_map('intval', $ids));
        usort($rows, static fn($left, $right) => ($sort[(int)$left['id']] ?? PHP_INT_MAX)
            <=> ($sort[(int)$right['id']] ?? PHP_INT_MAX));
        return $rows;
    }

    /**
     * 校验店铺是否可用（未删除、启用、营业中）
     * @param int $merId 商户ID
     * @return bool 店铺是否可用
     */
    protected function merchantIsAvailable(int $merId): bool
    {
        return $this->merchantDao->query([])
            ->where('mer_id', $merId)
            ->where('is_del', 0)
            ->where('status', 1)
            ->where('mer_state', 1)
            ->count() > 0;
    }

    /**
     * 获取购物金套餐详情，套餐不存在时抛出异常
     * @param int $merId 商户ID
     * @param int $id 套餐ID
     * @return array 套餐详情
     */
    public function packageDetail(int $merId, int $id): array
    {
        $package = $this->packageDao->search()->where('mer_id', $merId)->where('id', $id)->find();
        if (!$package) {
            throw new ValidateException('购物金套餐不存在');
        }
        return $package->toArray();
    }

    /**
     * 新增或编辑购物金充值套餐，校验充值金额与赠送金额后保存，返回最新套餐详情
     * @param int $merId 商户ID
     * @param array $data 套餐数据（recharge_amount/gift_amount/sort/show_status）
     * @param int $id 套餐ID，0表示新增
     * @return array 保存后的套餐详情
     */
    public function savePackage(int $merId, array $data, int $id = 0): array
    {
        $recharge = round((float)($data['recharge_amount'] ?? $data['price'] ?? 0), 2);
        $gift = round((float)($data['gift_amount'] ?? $data['give_price'] ?? 0), 2);
        if ($recharge <= 0) {
            throw new ValidateException('充值金额必须大于0');
        }
        if ($gift < 0) {
            throw new ValidateException('赠送金额不能小于0');
        }
        $save = [
            'mer_id' => $merId,
            'recharge_amount' => $recharge,
            'gift_amount' => $gift,
            'sort' => (int)($data['sort'] ?? 0),
            'show_status' => (int)($data['show_status'] ?? $data['status'] ?? 1) ? 1 : 0,
        ];
        if ($id) {
            $this->packageDetail($merId, $id);
            $this->packageDao->search()->where('id', $id)->where('mer_id', $merId)->update($save);
        } else {
            $id = $this->packageDao->create($save)->id;
        }
        return $this->packageDetail($merId, $id);
    }

    /**
     * 删除购物金套餐，先校验套餐存在再物理删除
     * @param int $merId 商户ID
     * @param int $id 套餐ID
     */
    public function deletePackage(int $merId, int $id): void
    {
        $this->packageDetail($merId, $id);
        $this->packageDao->search()->where('id', $id)->where('mer_id', $merId)->delete();
    }

    /**
     * 切换购物金套餐上架状态，未指定目标状态时取反当前状态
     * @param int $merId 商户ID
     * @param int $id 套餐ID
     * @param int|null $status 目标状态，为空时自动取反
     * @return int 切换后的状态
     */
    public function togglePackage(int $merId, int $id, ?int $status = null): int
    {
        $package = $this->packageDetail($merId, $id);
        $status = $status === null ? !(int)$package['show_status'] : ($status ? 1 : 0);
        $this->packageDao->search()->where('id', $id)->where('mer_id', $merId)->update(['show_status' => $status]);
        return $status;
    }

    /**
     * 获取用户在某店铺的购物金资产信息（冻结状态、本金、赠送金、余额及累计充值/消费）
     * @param int $uid 用户ID
     * @param int $merId 商户ID
     * @return array 购物金资产数组
     */
    public function asset(int $uid, int $merId): array
    {
        $credits = $this->shoppingCreditsDao->search()->where('uid', $uid)->where('mer_id', $merId)->find();
        $member = $this->memberDao->search()
            ->where('uid', $uid)
            ->where('mer_id', $merId)
            ->where('is_logoff', 0)
            ->field('financial_status')
            ->find();
        $recharge = (string)($credits->recharge_amount ?? '0.00');
        $gift = (string)($credits->gift_amount ?? '0.00');
        $recordQuery = $this->shoppingCreditsRecordDao->search()->where('uid', $uid)->where('mer_id', $merId);
        $rechargeRecordQuery = (clone $recordQuery)
            ->where('type', 1)
            ->whereIn('link_type', ['recharge', 'merchant_adjust']);
        return [
            'financial_status' => (int)($member->financial_status ?? 0),
            'recharge_amount' => $recharge,
            'gift_amount' => $gift,
            'shopping_credits_balance' => bcadd($recharge, $gift, 2),
            // 累计充值包含实际充值与商户后台增加，扣减及退款返还不计入累计值。
            'recharge_total_amount' => (string)((clone $rechargeRecordQuery)->sum('recharge_amount') ?: '0.00'),
            'gift_total_amount' => (string)((clone $rechargeRecordQuery)->sum('gift_amount') ?: '0.00'),
            'consume_total_amount' => (string)((clone $recordQuery)->where('type', 2)->where('link_type', 'order')
                ->sum(Db::raw('recharge_amount + gift_amount')) ?: '0.00'),
        ];
    }

    /**
     * 初始化用户在指定店铺的购物金账户，已存在时不重复创建。
     */
    public function ensureAccount(int $uid, int $merId): void
    {
        $credits = $this->shoppingCreditsDao->search()
            ->where('uid', $uid)
            ->where('mer_id', $merId)
            ->lock(true)
            ->find();
        if ($credits) {
            return;
        }

        try {
            $this->shoppingCreditsDao->create([
                'uid' => $uid,
                'mer_id' => $merId,
                'recharge_amount' => 0,
                'gift_amount' => 0,
            ]);
        } catch (\Throwable $e) {
            if (!$this->isDuplicateKeyException($e)) {
                throw $e;
            }
        }
    }

    /**
     * 获取购物金基础余额。
     */
    public function balance(int $uid, int $merId, bool $lock = false): array
    {
        $query = $this->shoppingCreditsDao->search()
            ->where('uid', $uid)
            ->where('mer_id', $merId);
        if ($lock) {
            $query->lock(true);
        }
        $credits = $query->find();
        $recharge = (string)($credits->recharge_amount ?? '0.00');
        $gift = (string)($credits->gift_amount ?? '0.00');

        return [
            'recharge_amount' => $recharge,
            'gift_amount' => $gift,
            'shopping_credits_balance' => bcadd($recharge, $gift, 2),
        ];
    }

    /**
     * 注销会员前校验购物金余额已经清零。
     */
    public function assertCanCloseAccount(int $uid, int $merId): void
    {
        $balance = $this->balance($uid, $merId, true);
        if (bccomp($balance['shopping_credits_balance'], '0.00', 2) > 0) {
            throw new ValidateException('购物金余额未清零，暂不能注销店铺会员');
        }
    }

    /**
     * 分页查询用户购物金收支流水，可按收入/支出类型过滤
     * @param int $uid 用户ID
     * @param int $merId 商户ID
     * @param int $page 页码
     * @param int $limit 每页数量
     * @param string $type 流水类型（income收入/expense支出/refund退款，空为全部）
     * @param string $date 流水时间范围
     * @return array 含count与list的分页结果
     */
    public function userRecords(int $uid, int $merId, int $page, int $limit, string $type = '', string $date = ''): array
    {
        $query = $this->shoppingCreditsRecordDao->search()->where('uid', $uid)->where('mer_id', $merId)
            ->when($type === 'income', fn($q) => $q->where('type', 1))
            ->when($type === 'expense', fn($q) => $q->where('type', 2)->where('link_type', '<>', 'refund'))
            ->when($type === 'refund', fn($q) => $q->where('link_type', 'refund'))
            ->where('link_type', '<>', 'refund_reject')
            ->when($date !== '', function ($query) use ($date) {
                getModelTime($query, $date, 'create_time');
            })
            ->order('id DESC');
        $count = $query->count();
        $list = $query->page($page, $limit)->select()->toArray();
        return compact('count', 'list');
    }

    /**
     * 商户调整用户购物金（本金/赠送金增加或扣减），返回调整后的资产信息
     * @param int $uid 用户ID
     * @param int $merId 商户ID
     * @param string $recharge 调整本金金额
     * @param string $gift 调整赠送金金额
     * @param int $type 调整类型（1增加、2扣减）
     * @param string $remark 调整备注
     * @return array 调整后的购物金资产
     */
    public function adjust(int $uid, int $merId, string $recharge, string $gift, int $type, string $remark): array
    {
        if (bccomp($recharge, '0.00', 2) < 0 || bccomp($gift, '0.00', 2) < 0) {
            throw new ValidateException('调整金额不能小于0');
        }
        $member = $this->memberDao->search()
            ->where('uid', $uid)
            ->where('mer_id', $merId)
            ->where('is_logoff', 0)
            ->find();
        if (!$member) {
            throw new ValidateException('该用户不是店铺会员');
        }
        $linkNo = $this->makeOrderNo('SCA', $uid);
        $this->changeBalance($uid, $merId, $recharge, $gift, $type, $linkNo, 'merchant_adjust', $remark ?: '商户调整购物金');
        return $this->asset($uid, $merId);
    }

    /**
     * 变更用户购物金余额（增加或扣减）并写入流水记录，事务内对账户行锁防止并发，账户缺失时自动初始化
     * @param int $uid 用户ID
     * @param int $merId 商户ID
     * @param string $recharge 本金变动金额
     * @param string $gift 赠送金变动金额
     * @param int $type 变动类型（1增加、2扣减）
     * @param string $linkNo 关联业务单号
     * @param string $linkType 关联业务类型（recharge/refund/order/merchant_adjust等）
     * @param string $title 流水标题
     * @return array 变动后的购物金账户数据
     */
    public function changeBalance(
        int $uid,
        int $merId,
        string $recharge,
        string $gift,
        int $type,
        string $linkNo,
        string $linkType,
        string $title
    ): array {
        $result = [];
        Db::transaction(function () use ($uid, $merId, $recharge, $gift, $type, $linkNo, $linkType, $title, &$result) {
            $this->ensureAccount($uid, $merId);
            $credits = $this->shoppingCreditsDao->search()
                ->where('uid', $uid)
                ->where('mer_id', $merId)
                ->lock(true)
                ->find();
            if (!$credits) {
                throw new ValidateException('购物金账户初始化失败');
            }
            if ($type === 2) {
                if (bccomp((string)$credits->recharge_amount, $recharge, 2) < 0 ||
                    bccomp((string)$credits->gift_amount, $gift, 2) < 0) {
                    throw new ValidateException('购物金余额不足');
                }
                $credits->recharge_amount = bcsub((string)$credits->recharge_amount, $recharge, 2);
                $credits->gift_amount = bcsub((string)$credits->gift_amount, $gift, 2);
            } else {
                $credits->recharge_amount = bcadd((string)$credits->recharge_amount, $recharge, 2);
                $credits->gift_amount = bcadd((string)$credits->gift_amount, $gift, 2);
            }
            $credits->save();
            $this->shoppingCreditsRecordDao->create([
                'uid' => $uid,
                'mer_id' => $merId,
                'link_no' => $linkNo,
                'link_type' => $linkType,
                'type' => $type,
                'title' => $title,
                'recharge_amount' => $recharge,
                'gift_amount' => $gift,
                'balance_recharge_amount' => $credits->recharge_amount,
                'balance_gift_amount' => $credits->gift_amount,
                'remark' => $title,
            ]);
            $result = $credits->toArray();
        });
        return $result;
    }

    /**
     * 按业务单号幂等调整商户余额，正数增加、负数扣减。
     */
    public function changeMerchantMoney(
        int $merId,
        string $amount,
        string $linkNo,
        string $linkType,
        string $title
    ): void {
        Db::transaction(function () use ($merId, $amount, $linkNo, $linkType, $title) {
            $merchant = $this->merchantDao->query([])->where('mer_id', $merId)->lock(true)->find();
            if (!$merchant) {
                throw new ValidateException('商户不存在');
            }
            $exists = $this->moneyRecordDao->search()
                ->where('mer_id', $merId)
                ->where('link_no', $linkNo)
                ->where('link_type', $linkType)
                ->find();
            if ($exists) {
                return;
            }

            $compare = bccomp($amount, '0.00', 2);
            $merchantRepository = app()->make(MerchantRepository::class);
            if ($compare > 0) {
                $merchantRepository->addMoney($merId, (float)$amount);
            } elseif ($compare < 0) {
                $merchantRepository->subMoney($merId, (float)bcsub('0.00', $amount, 2));
            }
            $balance = (string)$this->merchantDao->query([])->where('mer_id', $merId)->value('mer_money');
            $this->moneyRecordDao->create([
                'mer_id' => $merId,
                'link_no' => $linkNo,
                'link_type' => $linkType,
                'amount' => $amount,
                'balance' => $balance,
                'title' => $title,
            ]);
        });
    }

    /**
     * 生成业务单号（前缀+时间+用户ID+随机数）
     * @param string $prefix 单号前缀（SC充值/SCR退款/SCA调整）
     * @param int $uid 用户ID
     * @return string 生成的业务单号
     */
    private function makeOrderNo(string $prefix, int $uid): string
    {
        return $prefix . date('YmdHis') . $uid . random_int(1000, 9999);
    }

    /**
     * 判断异常是否为数据库唯一键冲突（duplicate entry等）
     * @param \Throwable $e 捕获的异常
     * @return bool 是否为唯一键冲突
     */
    private function isDuplicateKeyException(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());
        return str_contains($message, 'duplicate entry')
            || str_contains($message, 'sqlstate[23000]')
            || str_contains($message, 'integrity constraint violation');
    }

    public function switchStatusForm($id)
    {
        // 构建表单URL，该URL将指向处理提现审核状态切换的控制器方法。
        $url = Route::buildUrl('merchantShoppingCreditsRefundAudit', compact('id'))->build();

        // 创建表单对象，并设置表单标题。
        return Elm::createForm($url, [
            // 添加单选按钮组，用于选择提现申请的审核状态：通过或拒绝。
            Elm::radio('audit_type', '审核状态：', 1)->options([
                ['value' => 1, 'label' => '通过'],
                ['value' => -1, 'label' => '拒绝']])->control([
                // 当选择拒绝时，显示文本区域，用于输入拒绝的原因。
                ['value' => -1, 'rule' => [
                    Elm::textarea('reason', '拒绝原因：', '信息有误,请完善')->placeholder('请输入拒绝理由')->required('请输入拒绝理由')
                ]]
            ]),
        ])->setTitle('审核');
    }
}
