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


namespace app\controller;

use crmeb\basic\BaseController;
use crmeb\services\security\MediaCheckNotifyHandler;
use crmeb\services\wechat\config\MiniProgramConfig;
use crmeb\services\wechat\MiniProgram;
use crmeb\services\wechat\OfficialAccount;
use EasyWeChat\Core\Exceptions\InvalidArgumentException;
use EasyWeChat\Server\BadRequestException;
use think\Response;
use crmeb\services\wechat\OfficialAccountMessageHandler;

/**
 * Class WechatNotice
 * @package app\controller
 * @author xaboy
 * @day 2020-04-26
 */
class WechatNotice extends BaseController
{
    /**
     * @return Response
     * @throws InvalidArgumentException
     * @throws BadRequestException
     * @author xaboy
     * @day 2020-04-26
     */
    public function serve()
    {
        ob_clean();
        return OfficialAccount::serve([app()->make(OfficialAccountMessageHandler::class), 'handle']);
    }

    /**
     * 小程序消息推送（微信公众平台 → 开发管理 → 消息推送 填本地址）
     */
    public function routineServe()
    {
        // EasyWeChat 6 明文模式不校验 signature（GET 直接回显 echostr，POST 也不校验），必须自己校验，
        // 否则任何人都能伪造审核结果推送；Token 未配置时一律拒绝
        $token = (string)app()->make(MiniProgramConfig::class)->token;
        $signature = (string)$this->request->get('signature', '');
        $timestamp = (string)$this->request->get('timestamp', '');
        $nonce = (string)$this->request->get('nonce', '');
        $parts = [$token, $timestamp, $nonce];
        sort($parts, SORT_STRING);
        $valid = $token !== '' && $signature !== '' && hash_equals(sha1(implode('', $parts)), $signature)
            && abs(time() - (int)$timestamp) <= 300;
        \think\facade\Log::info('routine msg_push: ' . json_encode([
            'method' => $this->request->method(),
            'encrypt_type' => (string)$this->request->get('encrypt_type', ''),
            'has_echostr' => $this->request->get('echostr', '') !== '',
            'sig_ok' => $valid,
        ]));
        if (!$valid) {
            return response('invalid signature', 403)->contentType('text/plain');
        }
        // 非 html 类型，避免 app_debug 下 think-trace 往响应里追加调试脚本，导致微信校验 echostr 失败
        return MiniProgram::serve(MediaCheckNotifyHandler::class)->contentType('text/plain');
    }
}
