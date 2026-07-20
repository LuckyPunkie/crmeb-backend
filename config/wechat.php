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

// +----------------------------------------------------------------------
// | 微信服务相关配置
// +----------------------------------------------------------------------


return [
    //请求响应日志
    'logger' => env('APP_DEBUG', false),
    //公用
    'comment' => [
        'url' => [
            'key' => 'site_url'
        ],
    ],
    //小程序配置
    'mini' => [
        'appid' => [
            'key' => 'routine_appId'
        ],
        'secret' => [
            'key' => 'routine_appsecret'
        ],
        // 'token' => [
        //     'key' => 'wechat_token'
        // ],
        // 'key' => [
        //     'key' => 'wechat_encodingaeskey'
        // ],
        // 'notifyUrl' => [
        //     //必须携带斜杠开头
        //     'value' => '/api/pay/notify/routine'
        // ],
    ],
    //公众号配置
    'official' => [
        'appid' => [
            'key' => 'wechat_appid'
        ],
        'secret' => [
            'key' => 'wechat_appsecret'
        ],
        'token' => [
            'key' => 'wechat_token'
        ],
        'key' => [
            'key' => 'wechat_encodingaeskey'
        ],
        'encode' => [
            'key' => 'wechat_encode'
        ],
    ],
    //开放平台APP
    'app' => [
        'appid' => [
            'key' => 'wecaht_app_appid'
        ],
        'secret' => [
            'key' => 'wechat_app_appsecret'
        ],
    ],
    //开放平台网页应用
    'web' => [
        'appid' => [
            'key' => 'wechat_open_app_id'
        ],
        'secret' => [
            'key' => 'wechat_open_app_secret'
        ],
        'token' => [
            'key' => 'wechat_open_app_token'
        ],
        'key' => [
            'key' => 'wechat_open_app_aes_key'
        ],
    ],
    //支付
    'pay' => [
        'weixin' => [
            //商户号
            'mchid' => [
                'key' => 'pay_weixin_mchid'
            ],
             //支付key
            'key' => [
                'key' => 'pay_weixin_key'
            ],
            //证书
            'client_cert' => [
                'key' => 'pay_weixin_client_cert'
            ],
            //密钥
            'client_key' => [
                'key' => 'pay_weixin_client_key'
            ],
            'notifyUrl' => [
                //支付回调,必须携带斜杠开头
                'value' => '/api/notice/wechat'
            ],
            'refundUrl' => [
                //退款回调,必须携带斜杠开头
                'value' => '/api/pay/refund/wechat'
            ],
            //是否开启v3支付
            'isV3PAy' => [
                'key' => 'pay_wechat_type',
            ],
            //v3支付key
            'v3_key' => [
                'key' => 'pay_weixin_v3_key',
            ],
            //v3支付serial_no
            'v3_serial_no' => [
                'key' => 'pay_wechat_serial_no_v3',
            ],
            //v3支付public_id
            'v3_public_id' => [
                'key' => 'pay_weixin_public_id',
            ],
            //v3支付public_pem
            'v3_public_pem' => [
                'key' => 'pay_weixin_public_key',
            ],
        ],
        'mini' => [
            //小程序商户号
            'routine_mchid' => [
                'key' => 'pay_routine_mchid'
            ],
            //商户号
            'mchid' => [
                'key' => 'pay_routine_mchid'
            ],
             //支付key
            'key' => [
                'key' => 'pay_routine_key'
            ],
            //证书
            'client_cert' => [
                'key' => 'pay_routine_client_cert'
            ],
            //密钥
            'client_key' => [
                'key' => 'pay_routine_client_key'
            ],
             'notifyUrl' => [
                //支付回调,必须携带斜杠开头
                'value' => '/api/notice/routine'
            ],
            'refundUrl' => [
                //退款回调,必须携带斜杠开头
                'value' => '/api/pay/refund/routine'
            ],
            //是否开启v3支付
            'isV3PAy' => [
                'key' => 'pay_routine_type',
            ],
            //v3支付key
            'v3_key' => [
                'key' => 'pay_routine_v3_key',
            ],
            //v3支付serial_no
            'v3_serial_no' => [
                'key' => 'pay_routine_serial_no_v3',
            ],
            //v3支付public_id
            'v3_public_id' => [
                'key' => 'pay_routine_public_id',
            ],
            //v3支付public_pem
            'v3_public_pem' => [
                'key' => 'pay_routine_public_key',
            ],
        ],

    ],
	//自动分账,服务商支付等配置
	'service_pay' => [
        'pay_status' => [
			'key' => 'pay_weixin_sp_status',
			'value' => ''
		],
        //服务商名称
        'service_name' => [
            'key' => 'wechat_service_name'
        ],
        //服务商工具: 平台收付通 || 服务商
        'drive' =>  [
            'key' => 'wechat_service_tool'
        ],
		//商户号
		'mchid' => [
			'key' => 'wechat_service_merid'
		],
        'serial_no' => [
            'key' => 'wechat_service_serial_no'
        ],
        'key' => [
            'key' => 'wechat_service_key'
		],
		'v3_key' => [
			//默认使用value值，没有值使用eb_system_config配置中的key的值
			'key' => 'wechat_service_v3key',
			//配置值
			'value' => '',
		],
        //支付公钥ID
		'v3_public_id' => [
			//默认使用value值，没有值使用eb_system_config配置中的key的值
			'key' => 'wechat_service_public_id',
			//配置值
			'value' => '',
		],
        //支付公钥证书
		'v3_public_pem'=>[
			//默认使用value值，没有值使用eb_system_config配置中的key的值
			'key' => 'wechat_service_public_key',
			//配置值
			'value' => '',
		],
		//证书
		'client_cert' => [
			'key' => 'wechat_service_client_cert'
		],
		//证书
		'client_key' => [
			'key' => 'wechat_service_client_key'
		],
        'notifyUrl' => [
            'value' => '/api/notice/partner'
        ],
        'refundUrl' => [
            'value' => '/api/notice/partner'
        ],

	],
];
