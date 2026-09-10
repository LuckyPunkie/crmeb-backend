<?php

namespace app\controller\api\taoke;

use crmeb\basic\BaseController;
use crmeb\services\taoke\TaobaoOfficialService;
use think\App;
use think\facade\Log;

/**
 * 淘宝开放平台 OAuth（推广者授权 → TAOBAO_SESSION）
 */
class Oauth extends BaseController
{
    protected TaobaoOfficialService $taobaoOfficial;

    public function __construct(App $app, TaobaoOfficialService $taobaoOfficial)
    {
        parent::__construct($app);
        $this->taobaoOfficial = $taobaoOfficial;
    }

    /**
     * 跳转淘宝授权页（经理浏览器打开此链接）
     * GET /api/taoke/oauth/taobao/authorize
     */
    public function taobaoAuthorize()
    {
        $url = $this->taobaoOfficial->buildAuthorizeUrl('taoke');
        return redirect($url);
    }

    /**
     * OAuth 回调：用 code 换 access_token 并展示给运营复制
     * GET /api/taoke/oauth/taobao/callback
     */
    public function taobaoCallback()
    {
        $error = (string) $this->request->get('error', '');
        if ($error !== '') {
            return $this->renderOAuthPage('授权被拒绝', ['error' => $error], false);
        }

        $code = (string) $this->request->get('code', '');
        if ($code === '') {
            return $this->renderOAuthPage('缺少授权码 code', $this->request->get(), false);
        }

        $token = $this->taobaoOfficial->exchangeAuthorizationCode($code);
        if (!empty($token['error']) || empty($token['access_token'])) {
            Log::warning('淘宝 OAuth 回调换 token 失败', ['token' => $token]);
            return $this->renderOAuthPage('换取 access_token 失败', $token, false);
        }

        Log::info('淘宝 OAuth 授权成功', [
            'taobao_user_nick' => $token['taobao_user_nick'] ?? '',
            'expires_in'       => $token['expires_in'] ?? '',
        ]);

        return $this->renderOAuthPage('授权成功', $token, true);
    }

    protected function renderOAuthPage(string $title, array $data, bool $success)
    {
        $accessToken = htmlspecialchars((string) ($data['access_token'] ?? ''), ENT_QUOTES, 'UTF-8');
        $nick = htmlspecialchars((string) ($data['taobao_user_nick'] ?? ''), ENT_QUOTES, 'UTF-8');
        $expires = htmlspecialchars((string) ($data['expires_in'] ?? ''), ENT_QUOTES, 'UTF-8');
        $refresh = htmlspecialchars((string) ($data['refresh_token'] ?? ''), ENT_QUOTES, 'UTF-8');
        $json = htmlspecialchars(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8');
        $color = $success ? '#16a34a' : '#dc2626';

        $body = $success
            ? "<p>授权账号：<b>{$nick}</b></p>
               <p>有效期（秒）：<b>{$expires}</b></p>
               <p>请将下面整串 <b>access_token</b> 发给开发，写入服务器 <code>TAOBAO_SESSION</code>：</p>
               <textarea id=\"tok\" style=\"width:100%;height:88px\">{$accessToken}</textarea>
               <p><button onclick=\"navigator.clipboard.writeText(document.getElementById('tok').value)\">复制 access_token</button></p>
               <details><summary>完整返回 JSON</summary><pre>{$json}</pre></details>"
            : "<pre>{$json}</pre>";

        $html = <<<HTML
<!DOCTYPE html><html><head><meta charset="utf-8"><title>{$title}</title></head>
<body style="font-family:sans-serif;max-width:720px;margin:40px auto;padding:0 16px">
<h2 style="color:{$color}">{$title}</h2>
{$body}
</body></html>
HTML;

        return response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }
}
