<?php

/**
 * 淘宝客系统配置文件
 */

return [
    // ==================== 聚推客配置 ====================
    'jutuike' => [
        'appkey' => env('taoke.jutuike_appkey', ''),
        'pub_id' => env('taoke.jutuike_pub_id', ''),
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
        'refresh_token' => env('taoke.taobao_refresh_token', ''),
        // access_token 过期 Unix 时间戳（OAuth 回调或 refresh 时自动写入 .env）
        'session_expire_at' => (int) env('taoke.taobao_session_expire_at', 0),
        'pid' => env('taoke.taobao_pid', ''),
        'adzone_id' => env('taoke.taobao_adzone_id', ''),
        // OAuth 回调（须与开放平台「回调URL」完全一致，且不能为阿里系域名）
        'oauth_callback' => env('taoke.taobao_oauth_callback', 'https://0626tbcs.ohlegend.com/api/taoke/oauth/taobao/callback'),
        // 订单查询API配置
        'order_query_span' => 330, // 订单查询时间跨度（秒），默认5分30秒
    ],

    // ==================== 京东联盟配置 ====================
    'jd' => [
        'appkey' => env('taoke.jd_appkey', ''),
        'secret' => env('taoke.jd_secret', ''),
        'unionid' => env('taoke.jd_unionid', ''),
        'site_id' => env('taoke.jd_site_id', ''),         // 媒体ID第2段, promotion.common.get 的 siteId
        'pid' => env('taoke.jd_pid', ''),                 // 推广位ID第3段
        'full_pid' => env('taoke.jd_full_pid', ''),       // 完整 unionId_siteId_positionId, 商品发现类接口的 pid 参数
        'access_token' => env('taoke.jd_access_token', ''),
        'api_url' => 'https://api.jd.com/routerjson',
    ],

    // ==================== 拼多多联盟配置 ====================
    'pdd' => [
        'client_id' => env('taoke.pdd_client_id', ''),
        'client_secret' => env('taoke.pdd_client_secret', ''),
        'pid' => env('taoke.pdd_pid', ''),
        'media_id' => env('taoke.pdd_media_id', ''),
        // 平台默认备案 custom_parameters（列表/转链）
        'default_custom_parameters' => env('taoke.pdd_default_custom_parameters', 'naimeng01'),
        // 无关键词推荐流默认 activity_tags（多多进宝活动标签 id）
        'default_activity_tag' => (int) env('taoke.pdd_default_activity_tag', 4),
        'api_url' => 'https://gw-api.pinduoduo.com/api/router',
    ],

    // ==================== 快手联盟配置 ====================
    'kuaishou' => [
        'appkey' => env('taoke.kuaishou_appkey', ''),
        'secret' => env('taoke.kuaishou_secret', ''),
        'sign_secret' => env('taoke.kuaishou_sign_secret', ''),
        'message_secret' => env('taoke.kuaishou_message_secret', ''),
        'access_token' => env('taoke.kuaishou_access_token', ''),
        'refresh_token' => env('taoke.kuaishou_refresh_token', ''),
        'access_token_expire_at' => (int) env('taoke.kuaishou_access_token_expire_at', 0),
        'pid' => env('taoke.kuaishou_pid', ''),
        'api_url' => 'https://openapi.kwaixiaodian.com',
        'oauth_callback' => env('taoke.kuaishou_oauth_callback', 'https://0626tbcs.ohlegend.com/api/taoke/oauth/kuaishou/callback'),
        'oauth_scope' => env('taoke.kuaishou_oauth_scope', 'merchant_distribution'),
    ],

    // ==================== 抖音穿山甲 CPS 配置 ====================
    'pangle' => [
        'app_id' => env('taoke.pangle_app_id', ''),
        'secure_key' => env('taoke.pangle_secure_key', ''),
        'role_id' => env('taoke.pangle_role_id', ''),
        'api_url' => env('taoke.pangle_api_url', 'https://ecom.pangolin-sdk-toutiao.com'),
    ],

    // ==================== 平台数据源开关 ====================
    // official / legacy 由 API 路径决定：/taoke/goods=legacy，/taoke/official/goods=official
    // 下列 env 仅兼容旧脚本/CLI，用户端请用双路由而非改 .env 切换现网
    'driver' => [
        'taobao'   => env('taoke.driver_taobao', 'legacy'),
        'jd'       => env('taoke.driver_jd', 'legacy'),
        'pdd'      => env('taoke.driver_pdd', 'legacy'),
        'douyin'   => env('taoke.driver_douyin', 'pangle'),
        'kuaishou' => env('taoke.driver_kuaishou', 'legacy'),
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
