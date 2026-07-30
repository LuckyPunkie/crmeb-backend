<?php
// +----------------------------------------------------------------------
// | 扫码买单中转页（收钱吧同款：一码多端）
// | 二维码 = HTTPS 短链 → 本页按 UA 分流 → 微信/支付宝小程序 / App / 下载引导
// +----------------------------------------------------------------------

namespace app\controller\api\store\nearby;

use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\store\nearby\NearbyShopRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use crmeb\services\wechat\MiniProgram;

class ScanPay extends BaseController
{
    protected $repository;

    public function __construct(App $app, NearbyShopRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 统一收款中转
     * GET /api/scan_pay/:mer_id
     * GET /payjump/:mer_id  （别名，便于微信「普通链接二维码」规则配置）
     */
    public function jump($merId)
    {
        $merId = (int)$merId;
        $detail = $merId > 0 ? $this->repository->getDetail($merId) : null;
        if (!$detail) {
            return response($this->renderHtml([
                'ok' => false,
                'env' => 'other',
                'title' => '商家不存在',
                'message' => '商家不存在或未在附近好店展示，请联系商家完善资料。',
                'mer_id' => $merId,
            ]), 200, ['Content-Type' => 'text/html; charset=utf-8']);
        }

        $ua = (string)$this->request->header('user-agent', '');
        $env = $this->detectEnv($ua);

        $query = 'mer_id=' . $merId . '&action=pay';
        $payPath = '/pages/nearby/detail?mer_id=' . $merId . '&action=pay';
        $miniLinkInfo = $this->tryWechatUrlLinkDetail('pages/scan_pay/index', 'sp=' . $merId);
        $miniLink = (string)($miniLinkInfo['url_link'] ?? '');
        $routineQrcode = '';
        $routineErr = '';
        try {
            $routineQrcode = (string)app()->make(MerchantRepository::class)->scanPayRoutineQrcode($merId, false);
        } catch (\Throwable $e) {
            $routineQrcode = '';
            $routineErr = $e->getMessage();
        }

        // 微信：若已能生成 URL Link（正式发布后），直接 302
        if ($env === 'wechat' && $miniLink) {
            return redirect($miniLink);
        }

        // 支付宝：暂未接支付宝小程序，先展示商家信息 + 说明
        // App / 浏览器：展示商家信息，尝试 scheme（未配置则仅引导）

        $siteUrl = rtrim((string)systemConfig('site_url'), '/');
        $appScheme = trim((string)systemConfig('app_launch_scheme'));
        if ($appScheme === '') {
            $appScheme = '';
        } else {
            $sep = (strpos($appScheme, '?') !== false) ? '&' : '?';
            $appScheme = rtrim($appScheme, '?&') . $sep . 'mer_id=' . $merId . '&action=pay';
        }
        $downloadUrl = (string)(systemConfig('app_download_url') ?: ($siteUrl . '/'));

        $debugShow = (int)$this->request->param('debug', 0) === 1
            || (string)$this->request->param('debug', '') === '1'
            || $env === 'wechat';

        $headers = [
            'host' => (string)$this->request->header('host', ''),
            'referer' => (string)$this->request->header('referer', ''),
            'x-forwarded-for' => (string)$this->request->header('x-forwarded-for', ''),
            'x-real-ip' => (string)$this->request->header('x-real-ip', ''),
            'accept-language' => (string)$this->request->header('accept-language', ''),
        ];

        return response($this->renderHtml([
            'ok' => true,
            'env' => $env,
            'mer_id' => $merId,
            'mer_name' => $detail['mer_name'] ?? '',
            'mer_avatar' => $detail['mer_avatar'] ?? '',
            'mer_address' => $detail['mer_address'] ?? '',
            'mer_phone' => $detail['mer_phone'] ?? '',
            'star' => $detail['star'] ?? 0,
            'avg_price' => $detail['avg_price'] ?? 0,
            'mini_link' => $miniLink,
            'routine_qrcode' => $routineQrcode,
            'pay_path' => $payPath,
            'app_scheme' => $appScheme,
            'download_url' => $downloadUrl,
            'site_url' => $siteUrl,
            'debug_show' => $debugShow,
            'debug' => [
                'hint' => '看到本 H5 = 微信未命中「普通链接打开小程序」规则（链接被当网页打开）',
                'checklist' => [
                    '1. 规则前缀是否为 ' . $siteUrl . '/payjump/',
                    '2. 功能页面：普通链接规则建议 pages/scan_pay/index；长按太阳码现指向 pages/nearby/detail',
                    '3. 测试范围开发版/体验版是否与手机上的小程序版本一致',
                    '4. 规则是否已点「发布」（非仅保存）',
                    '5. 是否用微信「扫一扫」扫二维码（点链接不会走该规则）',
                    '6. 域名是否 ICP 备案；小程序是否企业主体',
                ],
                'env' => $env,
                'ua' => $ua,
                'headers' => $headers,
                'request_url' => (string)$this->request->url(true),
                'pathinfo' => (string)$this->request->pathinfo(),
                'query' => $this->request->get(),
                'mer_id' => $merId,
                'mer_name' => $detail['mer_name'] ?? '',
                'pay_path' => $payPath,
                'url_link' => [
                    'ok' => $miniLink !== '',
                    'value' => $miniLink,
                    'page' => 'pages/scan_pay/index',
                    'query' => 'sp=' . $merId,
                    'error' => $miniLinkInfo['error'] ?? '',
                    'note' => '未正式发布时通常为 false（errcode 85079），与普通链接规则无关',
                ],
                'routine_qrcode' => [
                    'ok' => $routineQrcode !== '',
                    'value' => $routineQrcode,
                    'page' => 'pages/nearby/detail',
                    'scene' => 'sp=' . $merId,
                    'env_version' => 'develop',
                    'error' => $routineErr,
                    'note' => 'H5 长按识别用；需手机已有对应开发版/体验版',
                ],
                'expected_rule' => [
                    'prefix' => $siteUrl . '/payjump/',
                    'mp_page_for_link_rule' => 'pages/scan_pay/index',
                    'mp_page_for_longpress' => 'pages/nearby/detail? via scene sp=' . $merId,
                    'test_url' => $siteUrl . '/payjump/' . $merId,
                ],
                'server_time' => date('Y-m-d H:i:s'),
            ],
        ]), 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    protected function detectEnv(string $ua): string
    {
        $u = strtolower($ua);
        if (strpos($u, 'micromessenger') !== false) {
            return 'wechat';
        }
        if (strpos($u, 'alipayclient') !== false || strpos($u, 'alipay') !== false) {
            return 'alipay';
        }
        // App WebView 可自行注入标识，例如 GuajiApp / CRMEBApp
        if (strpos($u, 'guajiapp') !== false || strpos($u, 'crmebapp') !== false) {
            return 'app';
        }
        return 'other';
    }

    protected function tryWechatUrlLink(string $page, string $query): string
    {
        $info = $this->tryWechatUrlLinkDetail($page, $query);
        return (string)($info['url_link'] ?? '');
    }

    /**
     * @return array{url_link:string,error:string}
     */
    protected function tryWechatUrlLinkDetail(string $page, string $query): array
    {
        try {
            $res = MiniProgram::generateUrlLink($page, $query);
            $link = '';
            if (is_array($res)) {
                $link = (string)($res['url_link'] ?? $res['link'] ?? '');
                if ($link === '' && !empty($res['errmsg'])) {
                    return ['url_link' => '', 'error' => (string)$res['errmsg']];
                }
            } elseif (is_object($res) && method_exists($res, 'toArray')) {
                $arr = $res->toArray();
                $link = (string)($arr['url_link'] ?? $arr['link'] ?? '');
                if ($link === '' && !empty($arr['errmsg'])) {
                    return ['url_link' => '', 'error' => (string)$arr['errmsg']];
                }
            } elseif (is_string($res) && strpos($res, 'http') === 0) {
                $link = $res;
            }
            return ['url_link' => $link, 'error' => $link === '' ? 'empty url_link' : ''];
        } catch (\Throwable $e) {
            return ['url_link' => '', 'error' => $e->getMessage()];
        }
    }

    protected function renderHtml(array $data): string
    {
        $ok = !empty($data['ok']);
        $env = htmlspecialchars((string)($data['env'] ?? 'other'), ENT_QUOTES, 'UTF-8');
        $name = htmlspecialchars((string)($data['mer_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $avatar = htmlspecialchars((string)($data['mer_avatar'] ?? ''), ENT_QUOTES, 'UTF-8');
        $address = htmlspecialchars((string)($data['mer_address'] ?? ''), ENT_QUOTES, 'UTF-8');
        $phone = htmlspecialchars((string)($data['mer_phone'] ?? ''), ENT_QUOTES, 'UTF-8');
        $message = htmlspecialchars((string)($data['message'] ?? ''), ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars((string)($data['title'] ?? '到店买单'), ENT_QUOTES, 'UTF-8');
        $miniLink = htmlspecialchars((string)($data['mini_link'] ?? ''), ENT_QUOTES, 'UTF-8');
        $routine = htmlspecialchars((string)($data['routine_qrcode'] ?? ''), ENT_QUOTES, 'UTF-8');
        $star = htmlspecialchars((string)($data['star'] ?? '0'), ENT_QUOTES, 'UTF-8');
        $avg = htmlspecialchars((string)($data['avg_price'] ?? '0'), ENT_QUOTES, 'UTF-8');
        $merId = (int)($data['mer_id'] ?? 0);
        $appScheme = htmlspecialchars((string)($data['app_scheme'] ?? ''), ENT_QUOTES, 'UTF-8');
        $downloadUrl = htmlspecialchars((string)($data['download_url'] ?? ''), ENT_QUOTES, 'UTF-8');
        $siteUrl = htmlspecialchars((string)($data['site_url'] ?? ''), ENT_QUOTES, 'UTF-8');
        $debug = is_array($data['debug'] ?? null) ? $data['debug'] : [];
        $debugJson = json_encode($debug, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($debugJson === false) {
            $debugJson = '{}';
        }
        $debugShow = !empty($data['debug_show']);

        $action = '';
        if ($ok && $env === 'wechat') {
            if ($miniLink) {
                $action = '<a class="btn" href="' . $miniLink . '">打开小程序买单</a>';
            } elseif ($routine) {
                $action = '<div class="mp-box">'
                    . '<div class="mp-title">长按识别小程序码 · 进入付款</div>'
                    . '<img class="mp-qr" src="' . $routine . '" alt="小程序码"/>'
                    . '<div class="tip">请<strong>长按上方小程序码</strong>进入到店买单页付款。<br/>'
                    . '开发期若普通链接未命中规则，会先打开本 H5（属兜底页）。</div>'
                    . '</div>';
            } else {
                $action = '<div class="tip">请配置微信「扫普通链接二维码打开小程序」，或稍后重试。</div>';
            }
        } elseif ($ok && $env === 'alipay') {
            $action = '<div class="tip">支付宝收款小程序尚未开通。请使用微信扫码或打开瓜几 APP 扫码买单。</div>';
        } elseif ($ok && ($env === 'app' || $env === 'other')) {
            if ($appScheme) {
                $action = '<a class="btn" id="openApp" href="' . $appScheme . '">打开 APP 买单</a>'
                    . '<div class="tip">若未自动打开，请安装瓜几 APP 后重试。</div>';
            } else {
                $action = '<div class="tip">请使用瓜几 APP「附近好店 → 扫码」识别本收款码；或使用微信扫一扫。</div>';
            }
            if ($downloadUrl) {
                $action .= '<a class="btn ghost" href="' . $downloadUrl . '">下载 APP</a>';
            }
        }

        $card = $ok
            ? '<div class="card">'
                . ($avatar ? '<img class="avatar" src="' . $avatar . '" alt=""/>' : '<div class="avatar placeholder"></div>')
                . '<div class="info"><div class="name">' . $name . '</div>'
                . '<div class="meta">评分 ' . $star . ' · ¥' . $avg . '/人</div>'
                . '<div class="addr">' . ($address ?: '地址待完善') . '</div>'
                . ($phone ? '<div class="phone">' . $phone . '</div>' : '')
                . '</div></div>'
            : '<div class="card empty"><div class="name">' . $title . '</div><div class="meta">' . $message . '</div></div>';

        $autoScript = '';
        if ($ok && $env === 'other' && $appScheme) {
            $autoScript = '<script>(function(){var s=' . json_encode($data['app_scheme'] ?? '', JSON_UNESCAPED_UNICODE)
                . ';var d=' . json_encode($data['download_url'] ?? '', JSON_UNESCAPED_UNICODE)
                . ';if(!s)return;location.href=s;setTimeout(function(){if(d)location.href=d;},1800);})();</script>';
        }

        $debugHtml = '<div class="dbg">'
            . '<div class="dbg-toggle" id="dbgToggle">调试信息（点开/收起）</div>'
            . '<pre class="dbg-body" id="dbgBody" style="display:' . ($debugShow ? 'block' : 'none') . '"></pre>'
            . '</div>';

        $debugScript = '<script>(function(){'
            . 'var info=' . $debugJson . ';'
            . 'info.client={'
            . 'href:location.href,'
            . 'search:location.search,'
            . 'hash:location.hash,'
            . 'referrer:document.referrer||"",'
            . 'title:document.title||"",'
            . 'visibility:document.visibilityState||"",'
            . 'lang:navigator.language||"",'
            . 'platform:navigator.platform||"",'
            . 'cookieEnabled:!!navigator.cookieEnabled,'
            . 'online:!!navigator.onLine,'
            . 'screen:(window.screen? (screen.width+"x"+screen.height):""),'
            . 'dpr:window.devicePixelRatio||1,'
            . 'isWechat:/MicroMessenger/i.test(navigator.userAgent),'
            . 'isWechatWork:/wxwork/i.test(navigator.userAgent),'
            . 'ts:new Date().toISOString()'
            . '};'
            . 'try{console.group("[scan_pay H5 debug]");console.log(info);console.groupEnd();}catch(e){}'
            . 'var body=document.getElementById("dbgBody");'
            . 'var btn=document.getElementById("dbgToggle");'
            . 'function render(){if(body){body.textContent=JSON.stringify(info,null,2);}}'
            . 'render();'
            . 'if(btn&&body){btn.onclick=function(){var on=body.style.display!=="none";body.style.display=on?"none":"block";};}'
            . '})();</script>';

        return '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"/>'
            . '<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no"/>'
            . '<title>' . ($ok ? $name . ' · 到店买单' : $title) . '</title>'
            . '<style>'
            . 'body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"PingFang SC","Microsoft YaHei",sans-serif;background:#f5f6f8;color:#1a1a1a}'
            . '.wrap{max-width:480px;margin:0 auto;padding:24px 16px 40px}'
            . '.badge{display:inline-block;background:#e8f3ff;color:#1677ff;font-size:12px;padding:4px 10px;border-radius:999px;margin-bottom:16px}'
            . '.card{background:#fff;border-radius:16px;padding:20px;display:flex;gap:14px;box-shadow:0 8px 24px rgba(0,0,0,.04)}'
            . '.avatar{width:72px;height:72px;border-radius:12px;object-fit:cover;background:#3b82f6;flex-shrink:0}'
            . '.name{font-size:20px;font-weight:700;line-height:1.3}'
            . '.meta,.addr,.phone,.tip{font-size:13px;color:#666;margin-top:6px;line-height:1.55}'
            . '.btn{display:block;margin-top:16px;text-align:center;background:#1677ff;color:#fff;text-decoration:none;padding:14px 16px;border-radius:12px;font-size:16px;font-weight:600}'
            . '.btn.ghost{background:#fff;color:#1677ff;border:1px solid #1677ff}'
            . '.mp-box{margin-top:20px;background:#fff;border-radius:16px;padding:20px;text-align:center;box-shadow:0 8px 24px rgba(0,0,0,.04)}'
            . '.mp-title{font-size:16px;font-weight:600;margin-bottom:14px}'
            . '.mp-qr{width:220px;height:220px;object-fit:contain}'
            . '.tip{margin-top:14px;text-align:left;background:#f7f8fa;border-radius:12px;padding:12px 14px}'
            . '.tip code{font-size:12px;word-break:break-all;color:#1677ff}'
            . '.foot{margin-top:18px;text-align:center;font-size:12px;color:#999}'
            . '.dbg{margin-top:16px;background:#111;color:#9fe870;border-radius:12px;overflow:hidden}'
            . '.dbg-toggle{padding:10px 12px;font-size:12px;color:#fff;background:#333}'
            . '.dbg-body{margin:0;padding:12px;font-size:11px;line-height:1.45;white-space:pre-wrap;word-break:break-all;max-height:60vh;overflow:auto}'
            . '</style></head><body><div class="wrap">'
            . '<div class="badge">统一收款码 · ' . $env . '</div>'
            . $card
            . $action
            . '<div class="foot">商户ID：' . $merId . '</div>'
            . $debugHtml
            . '</div>' . $autoScript . $debugScript . '</body></html>';
    }
}
