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

// 事件定义文件
return [
    'bind' => [],

    'listen' => [
        'AppInit' => [],
        'HttpRun' => [],
        'HttpEnd' => [],
        'LogLevel' => [],
        'LogWrite' => [],
        'swoole.task' => [\crmeb\listens\SwooleTaskListen::class],
        'swoole.init' => [
            \crmeb\listens\InitSwooleLockListen::class,
            \crmeb\listens\ClearSchemaCacheListen::class,
        ],
        'swoole.workerStart' => [
            \app\webscoket\SwooleWorkerStart::class,
            \crmeb\listens\CreateTimerListen::class,
            [\crmeb\services\CrossWorkerRouter::class, 'subscribe'],
        ],
        'swoole.workerExit' => [\crmeb\listens\SwooleWorkerExitListen::class],
        'swoole.workerError' => [\crmeb\listens\SwooleWorkerExitListen::class],
        'swoole.workerStop' => [\crmeb\listens\SwooleWorkerExitListen::class],
        'create_timer' => env('INSTALLED', false) ? [
            /**
             * 这里存在的事件是有执行的，可根据需求适当关闭调整等；
             * 如果这里不存在，但是代码逻辑中存在的定义事件，为：预留事件，就是没有实际的事件方法；是为了给需要二开，但是又不想修改源代码预留的；不用可以忽略。
             */

            //自动分账
             \crmeb\listens\AutoOrderProfitsharingListen::class,
             \crmeb\listens\AutoOrderProfitsharingStatusListen::class,
            //自动收货，用户未确认收货，超过15天未确认收货，自动确认收货
             \crmeb\listens\AuthTakeOrderListen::class,
            //取消订单，未支付的订单自动取消
             \crmeb\listens\AutoCancelGroupOrderListen::class,
            // 取消预售订单，自动取消
             \crmeb\listens\AuthCancelPresellOrderListen::class,
            // 自动解冻佣金
             \crmeb\listens\AutoUnLockBrokerageListen::class,
            // 自动发送短信提醒用户支付 10分钟未支付提醒
             \crmeb\listens\AutoSendPayOrderSmsListen::class,
            //自动同步短信状态
             \crmeb\listens\SyncSmsResultCodeListen::class,
            // 自动同步直播间状态，直播间同步监听，未开启可删除次行
            // \crmeb\listens\SyncBroadcastStatusListen::class,
            //商户长时间未处理退款订单，自动退款
             \crmeb\listens\RefundOrderAgreeListen::class,
            // 自动检测秒杀商品状态
             \crmeb\listens\SeckillTImeCheckListen::class,
            // 自动评价商品
             \crmeb\listens\AutoOrderReplyListen::class,
            // 预售商品状态检测
             \crmeb\listens\ProductPresellStatusListen::class,
            //检测拼团状态，团队是否成功和超时等
             \crmeb\listens\ProductGroupStatusCheckListen::class,
            //同步分销员状态
             \crmeb\listens\SyncSpreadStatusListen::class,
            // 保障服务使用数量统计
             \crmeb\listens\GuaranteeCountListen::class,
            // 自动解冻积分
             \crmeb\listens\AutoUnLockIntegralListen::class,
            // 自动清空积分
             \crmeb\listens\AutoClearIntegralListen::class,
            // 自动分账，商户入驻申请状态同步
             \crmeb\listens\MerchantApplyMentsCheckListen::class,
             //\crmeb\listens\MerchantApplyMentsRatekListen::class,
            // 自动解冻商户金额
             \crmeb\listens\AutoUnlockMerchantMoneyListen::class,
            // 自动同步社区话题热度
             \crmeb\listens\SumCountListen::class,
            // 同步热卖排行商品
             \crmeb\listens\SyncHotRankingListen::class,
            // 检测活动状态（氛围图/活动边框/系统表单）
             \crmeb\listens\AuthCancelActivityListen::class,
            // 自动关闭用户到期付费会员
             \crmeb\listens\CloseUserSvipListen::class,
             // 自动发送付费会员优惠券
             \crmeb\listens\SendSvipCouponListen::class,
             // 自动同步商户保证金状态
             \crmeb\listens\SyncMerchantMarginStatusListen::class,
            // 检查队列状态
             \crmeb\listens\SyncQueueStatusListen::class,
            // 定时上下架监听
             \crmeb\listens\AutoUpDownShelvesListen::class,
             // 代客下单扫码枪支付订单结果查询
             \crmeb\listens\pay\BarCodePayStatusListen::class,
        ] : [],
        // 支付相关回调 处理逻辑
        'pay_success_user_recharge' => [\crmeb\listens\pay\UserRechargeSuccessListen::class],
        'pay_success_user_order' => [\crmeb\listens\pay\UserOrderSuccessListen::class],
        'pay_success_order' => [\crmeb\listens\pay\OrderPaySuccessListen::class],
        'pay_success_presell' => [\crmeb\listens\pay\PresellPaySuccessListen::class],
        'pay_success_meal' => [\crmeb\listens\pay\MealSuccessListen::class],
        // 附近好店买单支付回调
        'pay.notify' => [\crmeb\listens\pay\NearbyBillPayNotifyListen::class],
        'pay_success_bill' => [\crmeb\listens\pay\NearbyBillPayNotifyListen::class],
        'pay_success_bill_pay' => [\crmeb\listens\pay\NearbyBillPayNotifyListen::class],
        'pay_success_order' => [
            \crmeb\listens\pay\OrderPaySuccessListen::class,
            \crmeb\listens\pay\NearbyBillPayNotifyListen::class,
            \crmeb\listens\pay\BlindBoxPaySuccessListen::class,
        ],
        // 本地/模拟支付走 paySuccess 后抛出，盲盒入柜依赖此事件
        'order.paySuccess' => [
            \crmeb\listens\pay\BlindBoxPaySuccessListen::class,
        ],
        //数据大屏
        'data.screen.send' =>[\crmeb\listens\DataScreenListen::class],
        //操作日志
        'create_operate_log' => [\crmeb\listens\CreateOperateLogListen::class],  // 操作日志事件
        'mini_order_shipping' => [\crmeb\listens\MiniOrderShippingListen::class],  // 小程序发货管理事件
        // 商家转帐到零钱状态处理事件
        'company_extract_status' => [\crmeb\listens\CompanyExtractStatusListen::class]
    ],

    'subscribe' => [],
];
