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
// | T3.3: 客服转接处理

namespace app\webscoket\handler;

use app\common\repositories\store\service\StoreServiceLogRepository;
use app\common\repositories\store\service\StoreServiceRepository;
use app\common\repositories\store\service\StoreServiceUserRepository;
use crmeb\services\CrossWorkerRouter;
use think\exception\ValidateException;

class TransferHandler
{
    /**
     * 客服发起转接请求
     * @param array $result
     * @return \think\response\Json
     */
    public function transfer_request(array $result)
    {
        $data = $result['data'] ?? [];
        $payload = $result['payload'] ?? [];
        $fromServiceId = $payload[0] ?? 0;
        $toServiceId = $data['to_service_id'] ?? 0;
        $toUid = $data['to_uid'] ?? 0;

        if (!$toServiceId || !$toUid) {
            return app('json')->message('err_tip', '参数错误');
        }

        // 验证目标客服在线
        $toService = app()->make(StoreServiceRepository::class)->getValidServiceInfo($toServiceId);
        if (!$toService) {
            return app('json')->message('err_tip', '目标客服不在线');
        }

        // 更新 store_service_user 关系：转接给新客服
        $serviceUserRepo = app()->make(StoreServiceUserRepository::class);
        $serviceUserRepo->transferService($fromServiceId, $toServiceId, $toUid, $toService->mer_id);

        // 添加系统转接消息
        $logRepo = app()->make(StoreServiceLogRepository::class);
        $merId = $toService->mer_id;

        $systemMsg = $logRepo->create([
            'mer_id'     => $merId,
            'uid'        => $toUid,
            'service_id' => $toServiceId,
            'msn_type'   => 1,
            'msn'        => '[系统] 对话已由客服转接',
            'send_type'  => 1,
        ]);

        // 推送给新客服
        CrossWorkerRouter::routeToUser($toService->uid, 'chat', [
            'type' => 'transfer_notify',
            'data' => $systemMsg->toArray() + ['transfer' => true, 'from_service_id' => $fromServiceId],
        ]);

        // 推送给用户
        CrossWorkerRouter::routeToUser($toUid, 'chat', [
            'type' => 'transfer_notify',
            'data' => [
                'msn_type'  => 1,
                'msn'       => '[系统] 对话已转接给新客服',
                'send_type' => 1,
                'transfer'  => true,
            ],
        ]);

        return app('json')->success(['message' => '转接成功', 'new_service_id' => $toServiceId]);
    }

    /**
     * 获取可转接的目标客服列表
     * @param array $result
     * @return \think\response\Json
     */
    public function transfer_targets(array $result)
    {
        $payload = $result['payload'] ?? [];
        $fromServiceId = $payload[0] ?? 0;

        $repo = app()->make(StoreServiceRepository::class);
        $services = $repo->search([
            'status'  => 1,
            'is_open' => 1,
            'is_del'  => 0,
        ])->where('service_id', '<>', $fromServiceId)
          ->field('service_id,nickname,avatar,status,mer_id')
          ->select();

        return app('json')->success(['list' => $services->toArray()]);
    }
}
