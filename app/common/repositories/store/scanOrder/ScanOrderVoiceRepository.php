<?php
// +----------------------------------------------------------------------
// | 扫码下单 - 语音播报（复用扫码买单 APP 队列 / UniPush）
// +----------------------------------------------------------------------

namespace app\common\repositories\store\scanOrder;

use app\common\model\store\service\StoreService;
use app\common\repositories\system\merchant\MerchantRepository;
use crmeb\services\SwooleTaskService;
use crmeb\services\UniPushService;
use think\facade\Cache;
use think\facade\Log;

class ScanOrderVoiceRepository
{
    public function notify(int $merId, string $orderSn, int $orderId = 0, string $tableLabel = ''): void
    {
        $msg = '新订单来啦，请注意查收';
        if ($tableLabel !== '') {
            $msg = '新订单来啦，' . $tableLabel . '，请注意查收';
        }
        $payload = [
            'title' => '扫码下单提醒',
            'message' => $msg,
            'voice_text' => $msg,
            'order_sn' => $orderSn,
            'id' => $orderId,
            'order_id' => $orderId,
            'mer_id' => $merId,
            'table_label' => $tableLabel,
            'type' => 'scan_order',
            'amount' => 0,
        ];

        try {
            SwooleTaskService::merchant('notice', [
                'type' => 'scan_order',
                'data' => $payload,
            ], $merId);
            // 同时发 new_order，保证订单列表角标/通用提醒也收到
            if ($orderId > 0) {
                SwooleTaskService::merchant('notice', [
                    'type' => 'new_order',
                    'data' => [
                        'title' => '您有新的扫码订单',
                        'message' => $msg,
                        'id' => $orderId,
                        'type' => 'new_order',
                    ],
                ], $merId);
            }
        } catch (\Throwable $e) {
            Log::error('ScanOrder merchant voice failed: ' . $e->getMessage());
        }

        try {
            $uids = $this->resolveNotifyUids($merId);
            $item = json_encode($payload, JSON_UNESCAPED_UNICODE);
            $redis = Cache::store('redis')->handler();

            $merKey = 'scan_pay_voice_mer:' . $merId;
            $redis->lPush($merKey, $item);
            $redis->lTrim($merKey, 0, 49);
            $redis->expire($merKey, 3600);

            foreach ($uids as $uid) {
                $key = 'scan_pay_voice:' . $uid;
                $redis->lPush($key, $item);
                $redis->lTrim($key, 0, 49);
                $redis->expire($key, 3600);
                try {
                    SwooleTaskService::user($uid, [
                        'type' => 'scan_order',
                        'data' => $payload,
                    ]);
                } catch (\Throwable $e) {
                }
            }

            try {
                UniPushService::pushScanPayToUids($uids, $payload);
            } catch (\Throwable $e) {
                Log::error('ScanOrder UniPush failed: ' . $e->getMessage());
            }
        } catch (\Throwable $e) {
            Log::error('ScanOrder app voice queue failed: ' . $e->getMessage());
        }
    }

    protected function resolveNotifyUids(int $merId): array
    {
        $uids = [];
        try {
            $serviceUids = StoreService::getDB()
                ->where('mer_id', $merId)
                ->where('is_del', 0)
                ->where('is_open', 1)
                ->column('uid');
            foreach ((array)$serviceUids as $uid) {
                $uids[] = (int)$uid;
            }
        } catch (\Throwable $e) {
        }

        $phones = [];
        try {
            $mer = app()->make(MerchantRepository::class)->get($merId);
            if ($mer && !empty($mer['mer_phone'])) {
                $phones[] = (string)$mer['mer_phone'];
            }
        } catch (\Throwable $e) {
        }
        try {
            $adminPhones = \app\common\model\system\merchant\MerchantAdmin::getDB()
                ->where('mer_id', $merId)
                ->where('is_del', 0)
                ->column('phone');
            foreach ((array)$adminPhones as $p) {
                if ($p) $phones[] = (string)$p;
            }
        } catch (\Throwable $e) {
        }

        $phones = array_unique(array_filter($phones));
        if ($phones) {
            try {
                $userUids = \app\common\model\user\User::getDB()
                    ->whereIn('phone', $phones)
                    ->column('uid');
                foreach ((array)$userUids as $uid) {
                    $uids[] = (int)$uid;
                }
            } catch (\Throwable $e) {
            }
        }

        return array_values(array_unique(array_filter($uids)));
    }
}
