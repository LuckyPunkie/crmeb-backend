<?php
// +----------------------------------------------------------------------
// | 扫码下单 - 走系统 v2/order/create 时的扫码专属处理（台号校验/备注/打标/打印/语音）
// +----------------------------------------------------------------------

namespace app\common\repositories\store\scanOrder;

use app\common\model\store\order\StoreOrder;
use app\common\repositories\store\order\StoreOrderRepository;
use think\facade\Db;
use think\facade\Log;

class ScanOrderOrderRepository
{
    /**
     * 解析扫码下单参数；未带 scan_mer_id 视为普通下单，返回 null
     */
    public function resolveContext(int $merId, int $tableId, string $sign): ?array
    {
        if ($merId <= 0) {
            return null;
        }
        $table = ['table_label' => '', 'id' => 0];
        if ($tableId > 0) {
            $table = app()->make(ScanOrderTableRepository::class)->assertTableAccess($merId, $tableId, $sign, true);
        }
        $config = app()->make(ScanOrderConfigRepository::class)->getConfig($merId);

        return [
            'mer_id' => $merId,
            'table_id' => $tableId,
            'table_label' => (string)($table['table_label'] ?? ''),
            'need_pay' => (int)$config['need_pay'] === 1,
            'voice_enable' => (int)$config['voice_enable'] === 1,
            'auto_print' => (int)$config['auto_print'] === 1,
        ];
    }

    /**
     * 到店自提校验需要收货人姓名/手机号，扫码点餐不收，用账号信息，无效手机号用合规占位号
     */
    public function normalizePost(array $post, $user): array
    {
        $realName = trim((string)($post['real_name'] ?? ''));
        if ($realName === '') {
            $realName = trim((string)($user['nickname'] ?? ''));
        }
        $phone = preg_replace('/\D+/', '', (string)($post['phone'] ?? ''));
        if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
            $phone = preg_replace('/\D+/', '', (string)($user['phone'] ?? ''));
        }
        if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
            $phone = '13000000000';
        }

        return ['real_name' => $realName ?: '顾客', 'phone' => $phone];
    }

    /**
     * 返回 [商户备注(mark) 数组, 用户原始备注, AI 摘要]
     */
    public function buildMark(array $ctx, array $mark, string $aiSessionNo, string $aiSummary): array
    {
        $merId = $ctx['mer_id'];
        $userRemark = trim((string)($mark[$merId] ?? ''));
        if (mb_strlen($userRemark) > 200) {
            $userRemark = mb_substr($userRemark, 0, 200);
        }
        if ($aiSessionNo !== '' && $aiSummary === '') {
            try {
                $aiSession = app()->make(\app\common\repositories\store\aiOrder\AiOrderSessionRepository::class)
                    ->getByNo($aiSessionNo);
                if ($aiSession && (int)$aiSession['mer_id'] === $merId) {
                    $aiSummary = (string)($aiSession['summary'] ?? '');
                }
            } catch (\Throwable $e) {
            }
        }
        if (mb_strlen($aiSummary) > 2000) {
            $aiSummary = mb_substr($aiSummary, 0, 2000);
        }

        // 无台号 = 商家详情页「线上下单」，复用扫码点餐流程但不是扫码
        $markText = $ctx['table_id'] > 0
            ? '扫码下单:' . $ctx['table_label'] . '(台号ID:' . $ctx['table_id'] . ')'
            : '线上下单';
        if ($userRemark !== '') {
            $markText .= '；备注:' . $userRemark;
        }
        if ($aiSummary !== '') {
            $markText .= '；AI点餐:' . $aiSummary;
        }
        $mark[$merId] = $markText;

        return [$mark, $userRemark, $aiSummary];
    }

    /**
     * 订单创建后：打标、免支付直接置已支付、自动打印、语音播报
     *
     * @return bool 是否免支付订单（前端据此直接进成功页）
     */
    public function afterCreate($groupOrder, array $ctx, string $userRemark, string $aiSessionNo, string $aiSummary): bool
    {
        $merId = $ctx['mer_id'];
        $orders = StoreOrder::getDB()
            ->where('group_order_id', $groupOrder->group_order_id)
            ->where('mer_id', $merId)
            ->select();

        // is_scan_order：1=扫码下单（有台号） 2=线上下单（无台号）
        $isScan = $ctx['table_id'] > 0;
        $sourceText = $isScan ? '扫码下单' : '线上下单';
        $orderRemark = $userRemark !== '' ? ($sourceText . '；' . $userRemark) : $sourceText;
        foreach ($orders as $order) {
            $update = [
                'is_scan_order' => $isScan ? 1 : 2,
                'order_source' => $isScan ? StoreOrderRepository::ORDER_SOURCE_SCAN : StoreOrderRepository::ORDER_SOURCE_ONLINE,
                'order_source' => $isScan ? StoreOrderRepository::ORDER_SOURCE_SCAN : StoreOrderRepository::ORDER_SOURCE_ONLINE,
                'scan_table_id' => $ctx['table_id'],
                'scan_table_label' => $ctx['table_label'],
                'remark' => $orderRemark,
            ];
            if ($aiSessionNo !== '' || $aiSummary !== '') {
                $update['ai_session_id'] = $aiSessionNo;
                $update['ai_order_summary'] = $aiSummary;
            }
            try {
                StoreOrder::getDB()->where('order_id', $order['order_id'])->update($update);
            } catch (\Throwable $e) {
                // 未执行迁移时降级：至少写扫码字段
                unset($update['ai_session_id'], $update['ai_order_summary']);
                StoreOrder::getDB()->where('order_id', $order['order_id'])->update($update);
            }
        }

        $orderRepository = app()->make(StoreOrderRepository::class);
        $noPay = !$ctx['need_pay'];
        if ($noPay) {
            try {
                $orderRepository->paySuccess($groupOrder);
            } catch (\Throwable $e) {
                StoreOrder::getDB()->where('group_order_id', $groupOrder->group_order_id)->update([
                    'paid' => 1,
                    'pay_time' => date('Y-m-d H:i:s'),
                    'status' => 0,
                ]);
                Db::name('store_group_order')->where('group_order_id', $groupOrder->group_order_id)->update([
                    'paid' => 1,
                    'pay_time' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        if ($ctx['auto_print']) {
            foreach ($orders as $order) {
                try {
                    $orderRepository->autoPrinter((int)$order['order_id'], $merId, 2);
                } catch (\Throwable $e) {
                }
            }
        }

        if ($ctx['voice_enable']) {
            try {
                $firstOrderId = 0;
                foreach ($orders as $o) {
                    $firstOrderId = (int)$o['order_id'];
                    break;
                }
                app()->make(ScanOrderVoiceRepository::class)->notify(
                    $merId,
                    (string)($groupOrder['group_order_sn'] ?? $groupOrder->group_order_id),
                    $firstOrderId,
                    $ctx['table_label']
                );
            } catch (\Throwable $e) {
                Log::error('ScanOrder voice notify: ' . $e->getMessage());
            }
        }

        return $noPay;
    }
}
