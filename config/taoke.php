<?php

/**
 * 淘宝客系统配置文件
 */

return [
    // ==================== 聚推客配置 ====================
    'jutuike' => [
        'appkey' => env('taoke.jutuike_appkey', ''),
        'api_url' => 'http://api.jutuike.com/',
    ],

    // ==================== 订单侠配置 ====================
    'dingdanxia' => [
        'appkey' => env('taoke.dingdanxia_appkey', ''),
        'api_url' => 'http://api.tbk.dingdanxia.com/',
    ],

    // ==================== 淘宝联盟配置 ====================
    'taobao' => [
        'appkey' => env('taoke.taobao_appkey', ''),
        'appsecret' => env('taoke.taobao_appsecret', ''),
        'session' => env('taoke.taobao_session', ''),
        'pid' => env('taoke.taobao_pid', ''),
        'adzone_id' => env('taoke.taobao_adzone_id', ''),
        // 订单查询API配置
        'order_query_span' => 330, // 订单查询时间跨度（秒），默认5分30秒
    ],

    // ==================== 京东联盟配置 ====================
    'jd' => [
        'appkey' => env('taoke.jd_appkey', ''),
        'secret' => env('taoke.jd_secret', ''),
        'unionid' => env('taoke.jd_unionid', ''),
        'pid' => env('taoke.jd_pid', ''),
        'access_token' => env('taoke.jd_access_token', ''),
    ],

    // ==================== 拼多多联盟配置 ====================
    'pdd' => [
        'client_id' => env('taoke.pdd_client_id', ''),
        'client_secret' => env('taoke.pdd_client_secret', ''),
        'pid' => env('taoke.pdd_pid', ''),
    ],

    // ==================== 分佣配置 ====================
    'commission' => [
        // 自购返佣比例（%）
        'self_rate' => 50,
        // 分享返佣比例（%）
        'share_rate' => 30,
        // 一级分佣比例（%）
        'level1_rate' => 20,
        // 二级分佣比例（%）
        'level2_rate' => 10,
        // 坑位费（%）
        'kengdie_fee' => 0,
        // 最高分佣层级（0=自己，1=一级，2=二级）
        'max_level' => 2,
    ],

    // ==================== 结算配置 ====================
    'settle' => [
        // 结算方式：0=每月固定日期，1=订单结算后N天
        'type' => 1,
        // 结算天数（当type=1时生效）
        'days' => 7,
        // 结算日期（当type=0时生效，每月几号）
        'date' => 15,
    ],

    // ==================== 订单同步配置 ====================
    'sync' => [
        // 同步间隔（分钟）
        'interval' => 5,
        // 是否启用Webhook
        'webhook_enable' => true,
        // Webhook密钥
        'webhook_key' => env('taoke.taoke_webhook_key', ''),
        // 订单导入批量大小
        'batch_size' => 100,
        // API失败重试次数
        'retry_times' => 3,
        // API重试间隔（秒）
        'retry_interval' => 5,
    ],

    // ==================== 缓存配置 ====================
    'cache' => [
        // 商品信息缓存时间（秒）
        'goods_ttl' => 3600,
        // 推广链接缓存时间（秒）
        'link_ttl' => 86400,
        // 用户配置缓存时间（秒）
        'user_config_ttl' => 1800,
    ],

    // ==================== 队列配置 ====================
    'queue' => [
        // 订单导入队列名称
        'order_import' => 'taoke_order_import',
        // 分佣计算队列名称
        'commission_calc' => 'taoke_commission_calc',
        // 佣金结算队列名称
        'commission_settle' => 'taoke_commission_settle',
        // 队列连接名称
        'connection' => 'default',
    ],

    // ==================== 用户等级配置 ====================
    'user_level' => [
        // 默认用户等级
        'default_level' => 1,
        // 等级配置（可从数据库覆盖）
        'levels' => [
            1 => [
                'name' => '普通会员',
                'self_rate' => 50,      // 自购返佣比例
                'share_rate' => 30,     // 分享返佣比例
            ],
            2 => [
                'name' => '黄金会员',
                'self_rate' => 55,
                'share_rate' => 35,
            ],
            3 => [
                'name' => '铂金会员',
                'self_rate' => 60,
                'share_rate' => 40,
            ],
        ],
    ],
];
