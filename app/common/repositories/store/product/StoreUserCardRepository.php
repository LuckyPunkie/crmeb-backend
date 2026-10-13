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

use app\common\dao\store\product\StoreUserCardDao;
use app\common\model\store\order\StoreOrder;
use app\common\model\store\order\StoreRefundOrder;
use app\common\model\store\product\StoreProductCardRule;
use app\common\model\store\product\StoreUserCard;
use app\common\model\store\product\StoreUserCardVerifyLog;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\order\OrderStatus;
use app\common\repositories\store\order\StoreOrderRepository;
use think\exception\ValidateException;
use think\facade\Db;

class StoreUserCardRepository extends BaseRepository
{
    public function __construct(StoreUserCardDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 支付成功后为次卡/年卡订单发放用户卡。
     *
     * 一条订单商品可能购买多份，因此这里按 order_product.product_num 补齐对应数量的用户卡。
     * 已发放过的订单商品会通过 order_product_id 计数跳过，避免支付回调或补偿任务重复执行时重复发卡。
     * 用户卡快照会保存商品、SKU、规则、有效期等信息，后续商品规则变更不会影响已购买的卡。
     *
     * @param StoreOrder $order 已支付的订单模型，必须已加载 orderProduct 关联。
     */
    public function issueByOrder(StoreOrder $order): void
    {
        $cards = [];
        foreach ($order->orderProduct as $orderProduct) {
            if (!$this->isServiceCardOrderProduct($orderProduct)) {
                continue;
            }

            $orderProductId = (int)$orderProduct['order_product_id'];
            $productNum = (int)$orderProduct['product_num'];
            $issuedCount = $this->dao->search(['order_product_id' => $orderProductId])->count();
            if ($issuedCount >= $productNum) {
                continue;
            }

            $rule = $this->getOrderProductRule($order, $orderProduct);
            $issueNum = $productNum - $issuedCount;
            for ($i = 0; $i < $issueNum; $i++) {
                $cards[] = $this->buildIssueData($order, $orderProduct, $rule);
            }
        }

        if ($cards) {
            $this->dao->insertAll($cards);
        }
    }

    /**
     * 根据核销码核销次卡/年卡。
     *
     * 核销过程在事务内完成，并通过 lockByVerifyCode 锁定用户卡，防止同一个核销码并发扣减。
     * 固定次数卡会扣减 remaining_count、增加 used_count，剩余次数为 0 时状态改为已用完；
     * 不限次数年卡保持 remaining_count = -1，只记录核销日志，不会因为单次核销变成已用完。
     * 每次核销都会写入 store_user_card_verify_log，记录核销前后次数、操作者、服务方式和备注。
     * 固定次数订单下所有卡都用完后，会将原订单 status 更新为已完成。
     *
     * @param string $verifyCode 用户卡核销码。
     * @param int $merId 商户 ID，用于限制核销码归属。
     * @param int $verifyCount 本次核销次数，固定次数卡会按该值扣减。
     * @param int $serviceId 客服/服务人员/员工 ID，移动端核销时记录到日志。
     * @param int $adminId 商户后台管理员 ID，商户后台核销时记录到日志。
     * @param int $verifyType 核销来源类型，见 StoreUserCardVerifyLog::VERIFY_TYPE_*。
     * @param string $remark 核销备注。
     * @param int $serviceType 本次实际服务方式，到店/上门；传 0 时按卡规则自动取默认值。
     * @return StoreUserCard
     */
    public function verifyByCode(
        string $verifyCode,
        int $merId,
        int $verifyCount = 1,
        int $serviceId = 0,
        int $adminId = 0,
        int $verifyType = StoreUserCardVerifyLog::VERIFY_TYPE_MERCHANT,
        string $remark = '',
        int $serviceType = 0
    ) {
        if ($verifyCode === '') {
            throw new ValidateException('核销码不能为空');
        }
        if ($verifyCount <= 0) {
            throw new ValidateException('核销次数必须大于0');
        }
        if (!in_array($verifyType, StoreUserCardVerifyLog::VERIFY_TYPES, true)) {
            throw new ValidateException('核销来源类型错误');
        }

        return Db::transaction(function () use ($verifyCode, $merId, $verifyCount, $serviceId, $adminId, $verifyType, $remark, $serviceType) {
            $card = $this->dao->lockByVerifyCode($verifyCode, $merId);
            if (!$card) {
                throw new ValidateException('次卡/年卡不存在');
            }

            $this->assertCardCanVerify($card, $verifyCount);
            $serviceType = $this->resolveVerifyServiceType($card, $serviceType);

            $beforeCount = (int)$card->remaining_count;
            $afterCount = $card->use_limit_type == StoreProductCardRuleRepository::USE_LIMIT_TYPE_UNLIMITED
                ? StoreUserCard::UNLIMITED_REMAINING_COUNT
                : $beforeCount - $verifyCount;

            $card->used_count = (int)$card->used_count + $verifyCount;
            $card->remaining_count = $afterCount;
            $card->status = $afterCount === StoreUserCard::REMAINING_COUNT_EMPTY
                ? StoreUserCard::STATUS_USED
                : StoreUserCard::STATUS_AVAILABLE;
            $card->update_time = date('Y-m-d H:i:s');
            $card->save();

            app()->make(StoreUserCardVerifyLogRepository::class)->create([
                'user_card_id' => $card->user_card_id,
                'card_no' => $card->card_no,
                'uid' => $card->uid,
                'mer_id' => $card->mer_id,
                'order_id' => $card->order_id,
                'order_product_id' => $card->order_product_id,
                'verify_count' => $verifyCount,
                'before_count' => $beforeCount,
                'after_count' => $afterCount,
                'service_id' => $serviceId,
                'admin_id' => $adminId,
                'verify_type' => $verifyType,
                'service_type' => $serviceType,
                'remark' => $remark,
                'create_time' => date('Y-m-d H:i:s'),
            ]);

            $this->completeOrderWhenCardsUsed($card, $afterCount);

            return $card;
        });
    }

    /**
     * 退款前校验订单商品对应的用户卡是否允许退。
     *
     * 次卡/年卡退款不能只看订单商品数量，还要看已经发出的用户卡状态：
     * - 规则配置为不支持退款时直接拒绝。
     * - 只要有非退款状态的卡已核销过，就拒绝退款。
     * - 可退款数量必须不小于本次申请退款数量。
     *
     * @param array $refundNums key 为 order_product_id，value 为申请退款数量。
     */
    public function assertOrderProductsCanRefund(array $refundNums): void
    {
        foreach ($refundNums as $orderProductId => $refundNum) {
            $orderProductId = (int)$orderProductId;
            $refundNum = (int)$refundNum;
            if ($orderProductId <= 0 || $refundNum <= 0) {
                continue;
            }

            $cards = $this->dao->search(['order_product_id' => $orderProductId])
                ->with(['rule'])
                ->select();
            if (!count($cards)) {
                continue;
            }
            foreach ($cards as $card) {
                if ($card->rule && (int)$card->rule->refund_rule === StoreProductCardRuleRepository::REFUND_RULE_DISABLED) {
                    throw new ValidateException('当前次卡/年卡不支持退款');
                }
            }

            $used = $this->dao->search(['order_product_id' => $orderProductId])
                ->where('used_count', '>', StoreUserCard::USED_COUNT_DEFAULT)
                ->where('status', '<>', StoreUserCard::STATUS_REFUNDED)
                ->count();
            //if ($used) {
            //    throw new ValidateException('次卡/年卡已核销，不支持退款');
            //}

            $canRefund = $this->dao->search(['order_product_id' => $orderProductId])
                ->where('status', StoreUserCard::STATUS_AVAILABLE)
                ->where('used_count', StoreUserCard::USED_COUNT_DEFAULT)
                ->count();
            if ($canRefund < $refundNum) {
                throw new ValidateException('可退次卡/年卡数量不足');
            }
        }
    }

    /**
     * 退款单退款成功后，将对应未使用用户卡标记为已退款作废。
     *
     * 只处理仍可用且 used_count = 0 的卡，已经核销过的卡在退款校验阶段会被拦截。
     * 每个退款商品按 refund_num 作废同等数量的卡，避免用户继续使用已退款权益。
     *
     * @param StoreRefundOrder $refundOrder 已处理成功的退款单模型。
     */
    public function markRefundedByRefundOrder(StoreRefundOrder $refundOrder): void
    {
        $refundOrder->append(['refundProduct']);
        foreach ($refundOrder->refundProduct as $refundProduct) {
            $refundNum = (int)$refundProduct['refund_num'];
            if ($refundNum <= 0) {
                continue;
            }

            $cards = $this->dao->search(['order_product_id' => (int)$refundProduct['order_product_id']])
                ->where('status', StoreUserCard::STATUS_AVAILABLE)
                ->where('used_count', StoreUserCard::USED_COUNT_DEFAULT)
                ->limit($refundNum)
                ->select()
                ->toArray();
            $ids = array_column($cards, 'user_card_id');
            if ($ids) {
                $this->dao->updates($ids, [
                    'status' => StoreUserCard::STATUS_REFUNDED,
                    'update_time' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    /**
     * 通过核销码查询用户卡详情。
     *
     * 主要用于核销前展示和判断当前验证码是否为次卡/年卡验证码。
     * 查询会限制商户 ID，避免跨商户拿到其他门店卡信息。
     *
     * @param string $verifyCode 用户卡核销码。
     * @param int $merId 商户 ID。
     */
    public function detailByVerifyCode(string $verifyCode, int $merId)
    {
        return $this->dao->search([
            'verify_code' => $verifyCode,
            'mer_id' => $merId,
        ])->with(['rule', 'product', 'user'])->find();
    }

    /**
     * 查询订单下所有已发放的次卡/年卡。
     *
     * 用于订单详情展示用户可核销权益，同时补充是否不限次数、状态文案和服务方式文案。
     * 这里读取的是用户卡快照表，不依赖当前商品规则，保证历史订单展示稳定。
     *
     * @param int $orderId 订单 ID。
     * @return array
     */
    public function listByOrderId(int $orderId): array
    {
        if ($orderId <= 0) {
            return [];
        }

        $cards = $this->dao->search(['order_id' => $orderId])
            ->field('user_card_id,card_no,verify_code,uid,mer_id,card_rule_id,product_id,value_id,unique,order_id,order_product_id,order_sn,product_name,sku_name,image,use_limit_type,total_count,used_count,remaining_count,validity_type,valid_start_time,valid_end_time,service_type,status,create_time')
            ->order('user_card_id ASC')
            ->select()
            ->toArray();

        foreach ($cards as &$card) {
            $card['last_verify_time'] = null;
            $card['is_unlimited'] = (int)$card['remaining_count'] === StoreUserCard::UNLIMITED_REMAINING_COUNT;
            $card['status_text'] = $this->cardStatusText((int)$card['status']);
            $card['service_type_text'] = StoreProductCardRuleRepository::SERVICE_TYPE_LABELS[(int)$card['service_type']] ?? '';
        }
        unset($card);

        return $cards;
    }

    /**
     * 判断订单商品是否为次卡/年卡商品。
     *
     * 优先从下单时写入的 cart_info.product.type 判断，避免商品后续改类型后影响历史订单。
     * cart_info 缺失时才回退到关联商品模型。
     */
    protected function isServiceCardOrderProduct($orderProduct): bool
    {
        $cartInfo = $orderProduct['cart_info'] ?: [];
        $productType = (int)($cartInfo['product']['type'] ?? ($orderProduct->product->type ?? ProductRepository::DEFINE_TYPE_ENTITY));

        return $productType === ProductRepository::DEFINE_TYPE_SERVICE_CARD;
    }

    /**
     * 获取订单商品对应的启用规则。
     *
     * 次卡/年卡规则绑定到 SKU value_id/unique。发卡时必须使用下单时的 SKU 信息，
     * 否则商品重新编辑后可能拿错规则。找不到规则说明商品或规格配置异常，需要中断发卡。
     *
     * @param StoreOrder $order 订单模型。
     * @param mixed $orderProduct 订单商品模型。
     * @return StoreProductCardRule
     */
    protected function getOrderProductRule(StoreOrder $order, $orderProduct)
    {
        $cartInfo = $orderProduct['cart_info'] ?: [];
        $productId = (int)$orderProduct['product_id'];
        $valueId = (int)($cartInfo['productAttr']['value_id'] ?? 0);
        $unique = (string)($cartInfo['productAttr']['unique'] ?? $orderProduct['product_sku'] ?? '');
        $rule = app()->make(StoreProductCardRuleRepository::class)->getEnabledRule(
            (int)$order['mer_id'],
            $productId,
            $valueId,
            $unique
        );

        if (!$rule) {
            throw new ValidateException('次卡/年卡规则不存在，订单号：' . $order['order_sn']);
        }

        return $rule;
    }

    /**
     * 组装用户卡发放数据。
     *
     * 用户卡保存一份购买当时的权益快照，包括规则类型、总次数、有效期、服务方式和商品展示信息。
     * 固定次数卡 remaining_count 初始化为规则总次数；不限次数年卡使用 -1 表示有效期内不限次数。
     *
     * @param StoreOrder $order 订单模型。
     * @param mixed $orderProduct 订单商品模型。
     * @param StoreProductCardRule $rule 下单 SKU 对应的次卡/年卡规则。
     * @return array
     */
    protected function buildIssueData(StoreOrder $order, $orderProduct, StoreProductCardRule $rule): array
    {
        $cartInfo = $orderProduct['cart_info'] ?: [];
        $baseTime = $this->normalizeTime($order['pay_time'] ?? $order['create_time'] ?? null);
        [$validStartTime, $validEndTime] = $this->resolveValidity($rule, $baseTime);
        $isUnlimited = (int)$rule['use_limit_type'] === StoreProductCardRuleRepository::USE_LIMIT_TYPE_UNLIMITED;

        return [
            'card_no' => $this->makeCardNo(),
            'verify_code' => $this->makeVerifyCode(),
            'uid' => (int)$order['uid'],
            'mer_id' => (int)$order['mer_id'],
            'card_rule_id' => (int)$rule['card_rule_id'],
            'product_id' => (int)$orderProduct['product_id'],
            'value_id' => (int)$rule['value_id'],
            'unique' => (string)$rule['unique'],
            'order_id' => (int)$order['order_id'],
            'order_product_id' => (int)$orderProduct['order_product_id'],
            'order_sn' => (string)$order['order_sn'],
            'product_name' => (string)($cartInfo['product']['store_name'] ?? ''),
            'sku_name' => (string)($cartInfo['productAttr']['sku'] ?? ''),
            'image' => (string)($cartInfo['productAttr']['image'] ?? $cartInfo['product']['image'] ?? ''),
            'use_limit_type' => (int)$rule['use_limit_type'],
            'total_count' => (int)$rule['total_count'],
            'used_count' => StoreUserCard::USED_COUNT_DEFAULT,
            'remaining_count' => $isUnlimited ? StoreUserCard::UNLIMITED_REMAINING_COUNT : (int)$rule['total_count'],
            'validity_type' => (int)$rule['validity_type'],
            'valid_start_time' => $validStartTime,
            'valid_end_time' => $validEndTime,
            'service_type' => (int)($rule['service_type'] ?? StoreProductCardRuleRepository::SERVICE_TYPE_STORE),
            'status' => StoreUserCard::STATUS_AVAILABLE,
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * 校验用户卡是否可以核销。
     *
     * 这里统一处理状态、有效期和剩余次数：
     * - 只有可用状态可以核销。
     * - 未到开始时间或已过有效期不能核销；过期时会同步把卡状态改为已过期。
     * - 固定次数卡必须保证剩余次数足够本次核销数量。
     *
     * @param StoreUserCard $card 被锁定的用户卡模型。
     * @param int $verifyCount 本次核销次数。
     */
    protected function assertCardCanVerify(StoreUserCard $card, int $verifyCount): void
    {
        if ((int)$card->status !== StoreUserCard::STATUS_AVAILABLE) {
            throw new ValidateException('当前次卡/年卡不可核销');
        }

        $today = date('Y-m-d');
        $validStartDate = $this->normalizeNullableDate($card->valid_start_time);
        $validEndDate = $this->normalizeNullableDate($card->valid_end_time);
        if ($validStartDate && $validStartDate > $today) {
            throw new ValidateException('当前次卡/年卡尚未到可核销时间');
        }
        if ($validEndDate && $validEndDate < $today) {
            $card->status = StoreUserCard::STATUS_EXPIRED;
            $card->update_time = date('Y-m-d H:i:s');
            $card->save();
            throw new ValidateException('当前次卡/年卡已过期');
        }

        if ((int)$card->use_limit_type === StoreProductCardRuleRepository::USE_LIMIT_TYPE_FIXED && (int)$card->remaining_count < $verifyCount) {
            throw new ValidateException('剩余核销次数不足');
        }
    }

    /**
     * 解析并校验本次核销使用的服务方式。
     *
     * 规则只支持到店、上门、二者都可。前端不传 service_type 时使用规则默认方式；
     * 如果规则是“上门+到店”，默认按到店核销。传入的方式必须被规则允许。
     *
     * @param StoreUserCard $card 用户卡模型。
     * @param int $serviceType 请求传入的服务方式。
     * @return int 最终写入核销日志的服务方式。
     */
    protected function resolveVerifyServiceType(StoreUserCard $card, int $serviceType): int
    {
        $cardServiceType = (int)($card->service_type ?: StoreProductCardRuleRepository::SERVICE_TYPE_STORE);
        if (!in_array($cardServiceType, StoreProductCardRuleRepository::SERVICE_TYPES, true)) {
            $cardServiceType = StoreProductCardRuleRepository::SERVICE_TYPE_STORE;
        }

        if (!$serviceType) {
            return $cardServiceType === StoreProductCardRuleRepository::SERVICE_TYPE_BOTH
                ? StoreProductCardRuleRepository::SERVICE_TYPE_STORE
                : $cardServiceType;
        }

        if (!in_array($serviceType, [StoreProductCardRuleRepository::SERVICE_TYPE_STORE, StoreProductCardRuleRepository::SERVICE_TYPE_HOME], true)) {
            throw new ValidateException('请选择正确的核销服务方式');
        }

        if ($cardServiceType !== StoreProductCardRuleRepository::SERVICE_TYPE_BOTH && $serviceType !== $cardServiceType) {
            $serviceTypeText = StoreProductCardRuleRepository::SERVICE_TYPE_LABELS[$serviceType] ?? '';
            throw new ValidateException('当前次卡/年卡不支持' . $serviceTypeText . '核销');
        }

        return $serviceType;
    }

    /**
     * 固定次数卡全部核销后完成原订单。
     *
     * 次卡/年卡核销不走普通订单 splitOrder 拆单流程，所以订单完成状态需要在用户卡核销后单独推进。
     * 只有固定次数卡剩余次数归零才表示“核销完”；不限次数年卡不会在单次核销后自动完成订单。
     * 同一个订单购买多张卡时，必须等订单下所有卡都不再是可用状态，才把订单 status 更新为已完成。
     *
     * @param StoreUserCard $card 当前刚完成核销的用户卡。
     * @param int $afterCount 当前卡核销后的剩余次数。
     */
    protected function completeOrderWhenCardsUsed(StoreUserCard $card, int $afterCount): void
    {
        if ((int)$card->use_limit_type !== StoreProductCardRuleRepository::USE_LIMIT_TYPE_FIXED) {
            return;
        }
        if ($afterCount !== StoreUserCard::REMAINING_COUNT_EMPTY || (int)$card->order_id <= 0) {
            return;
        }
        if ($this->hasAvailableCardByOrder((int)$card->order_id)) {
            return;
        }

        app()->make(StoreOrderRepository::class)->update((int)$card->order_id, [
            'status' => OrderStatus::ORDER_STATUS_PENDING_REVIEW,
        ]);
    }

    /**
     * 判断订单下是否仍存在可核销用户卡。
     *
     * 用于决定原订单是否可以从待使用推进到已完成。只要还有 STATUS_AVAILABLE 的卡，
     * 说明用户仍有未用完权益，订单不能完成。
     *
     * @param int $orderId 订单 ID。
     */
    protected function hasAvailableCardByOrder(int $orderId): bool
    {
        return $this->dao->search(['order_id' => $orderId])
            ->where('status', StoreUserCard::STATUS_AVAILABLE)
            ->count() > 0;
    }

    /**
     * 根据规则计算用户卡有效期。
     *
     * 永久有效：开始日期为支付当天，结束日期为空。
     * 购买后 N 天有效：开始、结束值都只保存 Y-m-d。
     * 固定有效期：直接保存规则的开始、结束日期。
     * 核销时只比较 Y-m-d，结束日期等于当天仍允许核销。
     *
     * @param StoreProductCardRule $rule 次卡/年卡规则。
     * @param string $baseTime 订单创建/支付时间。
     * @return array [valid_start_time, valid_end_time]
     */
    protected function resolveValidity(StoreProductCardRule $rule, string $baseTime): array
    {
        if ((int)$rule['validity_type'] === StoreProductCardRuleRepository::VALIDITY_TYPE_AFTER_PAY_DAYS) {
            return [
                $this->normalizeDate($baseTime),
                $this->normalizeDate(
                    strtotime('+' . (int)$rule['valid_days'] . ' day', strtotime($baseTime))
                ),
            ];
        }

        if ((int)$rule['validity_type'] === StoreProductCardRuleRepository::VALIDITY_TYPE_DATE_RANGE) {
            return [
                $this->normalizeDate($rule['valid_start_time']),
                $this->normalizeDate($rule['valid_end_time']),
            ];
        }

        return [$this->normalizeDate($baseTime), null];
    }

    /**
     * 将时间字段统一为数据库保存的日期时间字符串。
     *
     * 支持时间戳和可被 strtotime 解析的字符串；空值使用当前时间兜底。
     */
    protected function normalizeTime($value): string
    {
        if (!$value) {
            return date('Y-m-d H:i:s');
        }
        $time = is_numeric($value) ? (int)$value : strtotime((string)$value);

        return date('Y-m-d H:i:s', $time ?: time());
    }

    /**
     * 将次卡有效期收敛为日期。
     *
     * 数据库字段为 DATE，不保存时分秒。
     */
    protected function normalizeDate($value): string
    {
        $time = is_numeric($value) ? (int)$value : strtotime((string)$value);

        return date('Y-m-d', $time ?: time());
    }

    /**
     * 规范化可空有效期日期，用于核销时的自然日比较。
     */
    protected function normalizeNullableDate($value): ?string
    {
        if (!$value) {
            return null;
        }

        $time = is_numeric($value) ? (int)$value : strtotime((string)$value);

        return $time ? date('Y-m-d', $time) : null;
    }

    /**
     * 生成用户卡卡号。
     *
     * 卡号用于展示和人工识别，和核销码分离；生成后会查重，极小概率重复时递归重试。
     */
    protected function makeCardNo(): string
    {
        $cardNo = 'SC' . date('YmdHis') . random_int(100000, 999999);
        if ($this->dao->search(['card_no' => $cardNo])->count()) {
            return $this->makeCardNo();
        }

        return $cardNo;
    }

    /**
     * 生成用户卡核销码。
     *
     * 核销码是实际核销入口使用的凭证，需要全表唯一；生成后会查重，重复时递归重试。
     */
    protected function makeVerifyCode(): string
    {
        $verifyCode = strtoupper(substr(md5(uniqid((string)random_int(1000, 9999), true)), 8, 16));
        if ($this->dao->search(['verify_code' => $verifyCode])->count()) {
            return $this->makeVerifyCode();
        }

        return $verifyCode;
    }

    /**
     * 将用户卡状态转换为接口展示文案。
     */
    protected function cardStatusText(int $status): string
    {
        $texts = [
            StoreUserCard::STATUS_DISABLED => '已禁用',
            StoreUserCard::STATUS_AVAILABLE => '可核销',
            StoreUserCard::STATUS_USED => '已用完',
            StoreUserCard::STATUS_EXPIRED => '已过期',
            StoreUserCard::STATUS_REFUNDED => '已退款',
        ];

        return $texts[$status] ?? '未知';
    }

    /**
     * 检测所有状态为STATUS_AVAILABLE的,结束时间到了就设置为已过期
     * @return void
     * @author Qinii
     */
    public function validateEndTime()
    {
        $this->dao->search([])
            ->where('status',StoreUserCard::STATUS_AVAILABLE)
            ->whereNotNull('valid_end_time')
            ->whereTime('valid_end_time','<',strtotime(date('Y-m-d',time())))
            ->update(['status' => StoreUserCard::STATUS_EXPIRED])
        ;
    }
}
