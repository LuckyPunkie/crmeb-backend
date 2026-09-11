<?php

namespace app\controller\api\taoke;

use crmeb\basic\BaseController;
use crmeb\services\taoke\KuaishouOfficialService;
use crmeb\services\taoke\TaobaoOfficialService;
use think\App;
use think\facade\Log;

/**
 * 联盟平台 OAuth（淘宝 TAOBAO_SESSION / 快手 KUAISHOU_ACCESS_TOKEN）
 */
class Oauth extends BaseController
{
    protected TaobaoOfficialService $taobaoOfficial;
    protected KuaishouOfficialService $kuaishouOfficial;

    public function __construct(
        App $app,
        TaobaoOfficialService $taobaoOfficial,
        KuaishouOfficialService $kuaishouOfficial
    ) {
        parent::__construct($app);
        $this->taobaoOfficial = $taobaoOfficial;
        $this->kuaishouOfficial = $kuaishouOfficial;
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

        $saved = $this->taobaoOfficial->applyOAuthTokenResponse($token);
        $token['_env_saved'] = $saved;

        Log::info('淘宝 OAuth 授权成功', [
            'taobao_user_nick' => $token['taobao_user_nick'] ?? '',
            'expires_in'       => $token['expires_in'] ?? '',
            'env_saved'        => $saved,
        ]);

        return $this->renderOAuthPage('授权成功', $token, true);
    }

    /**
     * 手动 refresh（可挂 cron：curl 此 URL）
     * GET /api/taoke/oauth/taobao/refresh
     */
    public function taobaoRefresh()
    {
        $result = $this->taobaoOfficial->refreshAccessToken(true);
        $ok = !empty($result['access_token']);
        if ($ok) {
            Log::info('淘宝 OAuth refresh 成功', [
                'cached'     => !empty($result['cached']),
                'expires_in' => $result['expires_in'] ?? '',
            ]);
        } else {
            Log::warning('淘宝 OAuth refresh 失败', ['result' => $result]);
        }

        return app('json')->success([
            'ok'      => $ok,
            'cached'  => !empty($result['cached']),
            'expires' => $result['expires_in'] ?? null,
            'error'   => $ok ? null : ($result['error'] ?? ($result['error_description'] ?? 'refresh_failed')),
        ]);
    }

    /**
     * 跳转快手授权页
     * GET /api/taoke/oauth/kuaishou/authorize
     */
    public function kuaishouAuthorize()
    {
        return redirect($this->kuaishouOfficial->buildAuthorizeUrl('taoke'));
    }

    /**
     * 快手 OAuth 回调
     * GET /api/taoke/oauth/kuaishou/callback
     */
    public function kuaishouCallback()
    {
        $error = (string) $this->request->get('error', '');
        if ($error !== '') {
            return $this->renderOAuthPage('快手授权被拒绝', ['error' => $error], false, 'kuaishou');
        }

        $code = (string) $this->request->get('code', '');
        if ($code === '') {
            return $this->renderOAuthPage('缺少授权码 code', $this->request->get(), false, 'kuaishou');
        }

        $token = $this->kuaishouOfficial->exchangeAuthorizationCode($code);
        if ((int) ($token['result'] ?? 0) !== 1 || empty($token['access_token'])) {
            Log::warning('快手 OAuth 回调换 token 失败', ['token' => $token]);
            return $this->renderOAuthPage('换取 access_token 失败', $token, false, 'kuaishou');
        }

        $saved = $this->kuaishouOfficial->applyOAuthTokenResponse($token);
        $token['_env_saved'] = $saved;

        Log::info('快手 OAuth 授权成功', [
            'open_id' => $token['open_id'] ?? '',
            'expires_in' => $token['expires_in'] ?? '',
            'env_saved' => $saved,
        ]);

        return $this->renderOAuthPage('快手授权成功', $token, true, 'kuaishou');
    }

    /**
     * 快手 refresh（可挂 cron）
     * GET /api/taoke/oauth/kuaishou/refresh
     */
    public function kuaishouRefresh()
    {
        $result = $this->kuaishouOfficial->refreshAccessToken(true);
        $ok = !empty($result['access_token']) && (int) ($result['result'] ?? 0) === 1;
        if ($ok) {
            Log::info('快手 OAuth refresh 成功', [
                'cached' => !empty($result['cached']),
                'expires_in' => $result['expires_in'] ?? '',
            ]);
        } else {
            Log::warning('快手 OAuth refresh 失败', ['result' => $result]);
        }

        $errMsg = '';
        if (!$ok) {
            $errMsg = (string) ($result['error'] ?? $result['sub_msg'] ?? $result['msg'] ?? 'refresh_failed');
        }

        return app('json')->success([
            'ok' => $ok,
            'cached' => !empty($result['cached']),
            'expires' => isset($result['expires_in']) ? (int) $result['expires_in'] : null,
            'error' => $ok ? '' : $errMsg,
        ]);
    }

    protected function renderOAuthPage(string $title, array $data, bool $success, string $platform = 'taobao')
    {
        $accessToken = htmlspecialchars((string) ($data['access_token'] ?? ''), ENT_QUOTES, 'UTF-8');
        $nick = htmlspecialchars((string) ($data['taobao_user_nick'] ?? ($data['open_id'] ?? '')), ENT_QUOTES, 'UTF-8');
        $expires = htmlspecialchars((string) ($data['expires_in'] ?? ''), ENT_QUOTES, 'UTF-8');
        $refresh = htmlspecialchars((string) ($data['refresh_token'] ?? ''), ENT_QUOTES, 'UTF-8');
        $json = htmlspecialchars(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8');
        $color = $success ? '#16a34a' : '#dc2626';

        $saved = !empty($data['_env_saved']);
        if ($platform === 'kuaishou') {
            $envTip = 'KUAISHOU_ACCESS_TOKEN / KUAISHOU_REFRESH_TOKEN / KUAISHOU_ACCESS_TOKEN_EXPIRE_AT';
            $accessLabel = 'KUAISHOU_ACCESS_TOKEN';
            $refreshLabel = 'KUAISHOU_REFRESH_TOKEN';
        } else {
            $envTip = 'TAOBAO_SESSION / TAOBAO_REFRESH_TOKEN / TAOBAO_SESSION_EXPIRE_AT';
            $accessLabel = 'TAOBAO_SESSION';
            $refreshLabel = 'TAOBAO_REFRESH_TOKEN';
        }

        $savedTip = $saved
            ? '<p style="color:#16a34a">已自动写入服务器 <code>.env</code>（' . $envTip . '），无需手工复制。</p>'
            : '<p style="color:#dc2626">未能自动写入 .env，请将下方 token 手工配置到服务器。</p>';

        $body = $success
            ? "<p>授权账号：<b>{$nick}</b></p>
               <p>有效期（秒）：<b>{$expires}</b></p>
               {$savedTip}
               <p>access_token（{$accessLabel}）：</p>
               <textarea id=\"tok\" style=\"width:100%;height:88px\">{$accessToken}</textarea>
               <p>refresh_token（{$refreshLabel}）：</p>
               <textarea id=\"ref\" style=\"width:100%;height:88px\">{$refresh}</textarea>
               <p><button onclick=\"navigator.clipboard.writeText(document.getElementById('tok').value)\">复制 access_token</button>
               <button onclick=\"navigator.clipboard.writeText(document.getElementById('ref').value)\">复制 refresh_token</button></p>
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
