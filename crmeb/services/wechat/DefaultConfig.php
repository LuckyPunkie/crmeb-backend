<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2020 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

namespace crmeb\services\wechat;

use think\facade\Config;

/**
 * 默认配置
 * Class DefaultConfig
 * @package crmeb\services\wechat
 */
class DefaultConfig
{
    //小程序appid
    const MINI_APPID = 'mini.appid';
    //公众号appid
    const OFFICIAL_APPID = 'official.appid';
    //开放平台appid
    const APP_APPID = 'app.appid';
    //开放平台网页端appid
    const WEB_APPID = 'web.appid';
    //企业微信id
    const WORK_CORP_ID = 'work.corp_id';
    //公众号商户id
    const WEIXIN_PAY_MCHID = 'pay.weixin.mchid';
    //routine商户id
    const MINI_PAY_MCHID = 'pay.mini.mchid';
    //系统配置域名地址,携带,格式:http://www.a.com
    const COMMENT_URL = 'comment.url';

    /**
     *
     */
    const WECHAT_CONFIG = [
        //请求响应日志
        'logger' => true,
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
            'token' => [
                'key' => 'wechat_token'
            ],
            'key' => [
                'key' => 'wechat_encodingaeskey'
            ],
            'notifyUrl' => [
                //必须携带斜杠开头
                'value' => '/api/pay/notify/routine'
            ],
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
                'key' => 'wechat_app_appid'
            ],
            'secret' => [
                'key' => 'wechat_app_appsecret'
            ],
            'token' => [
                'key' => 'wechat_openapp_app_token'
            ],
            'key' => [
                'key' => 'wechat_openapp_app_aes_key'
            ],
            'notifyUrl' => [
                //必须携带斜杠开头
                'value' => '/api/pay/notify/app'
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
        //企业微信
        'work' => [
            'corp_id' => [
                'key' => 'wechat_work_corpid'
            ],
            'token' => [
                'key' => 'wechat_work_token'
            ],
            'key' => [
                'key' => 'wechat_work_aes_key'
            ],
        ],
        //支付
        'pay' => [
            //商户号
            'mchid' => [
                'key' => 'pay_weixin_mchid'
            ],
            //小程序商户号
            'routine_mchid' => [
                'key' => 'pay_routine_mchid'
            ],
            //支付key
            'key' => [
                'key' => 'pay_weixin_key'
            ],
            //证书
            'client_cert' => [
                'key' => 'pay_weixin_client_cert'
            ],
            //证书
            'client_key' => [
                'key' => 'pay_weixin_client_key'
            ],
            'notifyUrl' => [
                //支付回调,必须携带斜杠开头
                'value' => '/api/pay/notify/wechat'
            ],
            'refundUrl' => [
                //退款回调,必须携带斜杠开头
                'value' => '/api/pay/refund/wechat'
            ],
        ],
        //v3支付新增配置，证书和商户号使用v2支付配置的证书
        'v3_pay' => [
            'key' => [
                //默认使用value值，没有值使用eb_system_config配置中的key的值
                'key' => 'v3_pay_weixin_key',
                //配置值
                'value' => '',
            ],
            'serial_no' => [
                //默认使用value值，没有值使用eb_system_config配置中的key的值
                'key' => 'pay_weixin_serial_no',
                //配置值
                'value' => '',
            ],
            'pay_type' => [
                //默认使用value值，没有值使用eb_system_config配置中的key的值
                'key' => 'pay_wechat_type',
                //配置值
                'value' => '',
            ],
            'public_key'=>[
                //默认使用value值，没有值使用eb_system_config配置中的key的值
                'key' => 'v3_pay_public_key',
                //配置值
                'value' => '',
            ],
            'public_pem'=>[
                //默认使用value值，没有值使用eb_system_config配置中的key的值
                'key' => 'v3_pay_public_pem',
                //配置值
                'value' => '',
            ],
        ],
		//服务商支付
		'service_pay' => [
			'pay_status' => [
				'key' => 'pay_weixin_sp_status',
				'value' => ''
			],
			//商户号
			'mchid' => [
				'key' => 'pay_weixin_sp_mchid'
			],
			'appid' => [
				'key' => 'pay_weixin_sp_appid'
			],
			//支付key
			'v2_key' => [
				'key' => 'v2_pay_weixin_sp_key'
			],
			'key' => [
				//默认使用value值，没有值使用eb_system_config配置中的key的值
				'key' => 'v3_pay_weixin_sp_key',
				//配置值
				'value' => '',
			],
			'serial_no' => [
				//默认使用value值，没有值使用eb_system_config配置中的key的值
				'key' => 'pay_weixin_sp_serial_no',
				//配置值
				'value' => '',
			],
			'public_key'=>[
				//默认使用value值，没有值使用eb_system_config配置中的key的值
				'key' => 'v3_pay_sp_public_key',
				//配置值
				'value' => '',
			],
			'public_pem'=>[
				//默认使用value值，没有值使用eb_system_config配置中的key的值
				'key' => 'v3_pay_sp_public_pem',
				//配置值
				'value' => '',
			],
			//证书
			'client_cert' => [
				'key' => 'pay_weixin_sp_client_cert'
			],
			//证书
			'client_key' => [
				'key' => 'pay_weixin_sp_client_key'
			],
		],
    ];

    /**
     * 获取配置,如果配置为数组则使用value的值，如果没有值返回key
     * @param string $key
     * @return array|mixed|string[]|null|bool
     */
    public static function value(string $key)
    {
        $config = [];
        if (Config::has('wechat')) {
            $config = Config::get('wechat', []);
        }
        $config = array_merge(self::WECHAT_CONFIG, $config);

        $key = explode('.', $key);
        $value = null;
        foreach ($key as $k) {
            if ($value) {
                $value = $value[$k] ?? null;
            } else {
                $value = $config[$k] ?? null;
            }
        }

        if (is_array($value)) {
            return $value['value'] ?? null;
        } else {
            return $value;
        }
    }

    /**
     * @param string $key
     * @return mixed|null
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/18
     */
    public static function key(string $key)
    {
        $config = [];
        if (Config::has('wechat')) {
            $config = Config::get('wechat', []);
        }
        $config = array_merge(self::WECHAT_CONFIG, $config);
        $key = explode('.', $key);
        $value = null;
        foreach ($key as $k) {
            if ($value) {
                $value = $value[$k] ?? null;
            } else {
                $value = $config[$k] ?? null;
            }
        }

        if (is_array($value)) {
            $value = $value['key'] ?? null;
        }

        return $value;
    }
}
