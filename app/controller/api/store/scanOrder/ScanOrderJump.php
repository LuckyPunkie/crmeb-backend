<?php
// +----------------------------------------------------------------------
// | 扫码下单中转页（一码多端）
// | HTTPS: /scanjump/:mer_id/:table_id?sign=xxx
// +----------------------------------------------------------------------

namespace app\controller\api\store\scanOrder;

use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\store\scanOrder\ScanOrderSign;
use app\common\repositories\store\scanOrder\ScanOrderTableRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use crmeb\services\wechat\MiniProgram;

class ScanOrderJump extends BaseController
{
    protected $tableRepository;

    public function __construct(App $app, ScanOrderTableRepository $tableRepository)
    {
        parent::__construct($app);
        $this->tableRepository = $tableRepository;
    }

    /**
     * GET /scanjump/:mer_id/:table_id
     * GET /api/scan_order/jump/:mer_id/:table_id
     */
    public function jump($merId, $tableId)
    {
        $merId = (int)$merId;
        $tableId = (int)$tableId;
        $sign = (string)$this->request->param('sign', '');

        try {
            $table = $this->tableRepository->assertTableAccess($merId, $tableId, $sign, true);
        } catch (\Throwable $e) {
            return response($this->renderHtml([
                'ok' => false,
                'title' => '二维码无效',
                'message' => $e->getMessage() ?: '台号无效，请联系商家重新张贴二维码',
                'mer_id' => $merId,
                'table_id' => $tableId,
            ]), 200, ['Content-Type' => 'text/html; charset=utf-8']);
        }

        $mer = app()->make(MerchantRepository::class)->get($merId);
        $merName = $mer ? (string)$mer['mer_name'] : '';
        $ua = (string)$this->request->header('user-agent', '');
        $env = $this->detectEnv($ua);

        $query = 'mer_id=' . $merId . '&table_id=' . $tableId . '&sign=' . urlencode($sign);
        $mpPage = 'pages/scan_order/index';
        $miniLinkInfo = $this->tryWechatUrlLink($mpPage, $query);
        $miniLink = (string)($miniLinkInfo['url_link'] ?? '');

        if ($env === 'wechat' && $miniLink) {
            return redirect($miniLink);
        }

        $siteUrl = rtrim((string)systemConfig('site_url'), '/');
        $appScheme = trim((string)systemConfig('app_launch_scheme'));
        if ($appScheme !== '') {
            $sep = (strpos($appScheme, '?') !== false) ? '&' : '?';
            $appScheme = rtrim($appScheme, '?&') . $sep . $query;
        }
        $downloadUrl = (string)(systemConfig('app_download_url') ?: ($siteUrl . '/'));

        return response($this->renderHtml([
            'ok' => true,
            'env' => $env,
            'mer_id' => $merId,
            'table_id' => $tableId,
            'table_label' => $table['table_label'] ?? '',
            'mer_name' => $merName,
            'sign' => $sign,
            'mini_link' => $miniLink,
            'mp_page' => $mpPage,
            'mp_query' => $query,
            'app_scheme' => $appScheme,
            'download_url' => $downloadUrl,
            'jump_url' => ScanOrderSign::jumpUrl($merId, $tableId),
            'hint' => '微信需配置「扫普通链接二维码打开小程序」，前缀建议 ' . $siteUrl . '/scanjump/',
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
        return 'other';
    }

    protected function tryWechatUrlLink(string $path, string $query): array
    {
        try {
            $res = MiniProgram::generateUrlLink($path, $query);
            if (is_array($res) && !empty($res['url_link'])) {
                return ['url_link' => $res['url_link'], 'error' => ''];
            }
            if (is_string($res) && strpos($res, 'http') === 0) {
                return ['url_link' => $res, 'error' => ''];
            }
            return ['url_link' => '', 'error' => is_string($res) ? $res : json_encode($res, JSON_UNESCAPED_UNICODE)];
        } catch (\Throwable $e) {
            return ['url_link' => '', 'error' => $e->getMessage()];
        }
    }

    protected function renderHtml(array $data): string
    {
        $ok = !empty($data['ok']);
        $title = htmlspecialchars((string)($data['title'] ?? ($ok ? '扫码下单' : '提示')), ENT_QUOTES, 'UTF-8');
        $merName = htmlspecialchars((string)($data['mer_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $tableLabel = htmlspecialchars((string)($data['table_label'] ?? ''), ENT_QUOTES, 'UTF-8');
        $message = htmlspecialchars((string)($data['message'] ?? ''), ENT_QUOTES, 'UTF-8');
        $hint = htmlspecialchars((string)($data['hint'] ?? ''), ENT_QUOTES, 'UTF-8');
        $download = htmlspecialchars((string)($data['download_url'] ?? ''), ENT_QUOTES, 'UTF-8');
        $appScheme = htmlspecialchars((string)($data['app_scheme'] ?? ''), ENT_QUOTES, 'UTF-8');

        $body = $ok
            ? '<div class="card">'
            . '<div class="name">' . ($merName ?: '商家') . '</div>'
            . ($tableLabel ? '<div class="pos">位置：' . $tableLabel . '</div>' : '')
            . '<div class="tip">请使用微信扫一扫打开小程序下单；或打开瓜几 APP 扫码。</div>'
            . ($appScheme ? '<a class="btn" href="' . $appScheme . '">打开 APP 下单</a>' : '')
            . ($download ? '<a class="link" href="' . $download . '">下载 APP</a>' : '')
            . ($hint ? '<div class="hint">' . $hint . '</div>' : '')
            . '</div>'
            : '<div class="card"><div class="name">' . $title . '</div><div class="tip">' . $message . '</div></div>';

        return '<!doctype html><html><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">'
            . '<title>' . $title . '</title>'
            . '<style>'
            . 'body{margin:0;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#f6f7fb;color:#222}'
            . '.card{margin:48px 20px;padding:24px;background:#fff;border-radius:12px;box-shadow:0 6px 24px rgba(0,0,0,.06)}'
            . '.name{font-size:20px;font-weight:600;margin-bottom:8px}'
            . '.pos{font-size:15px;color:#e6a23c;margin-bottom:12px}'
            . '.tip{font-size:14px;line-height:1.6;color:#666}'
            . '.btn{display:block;margin-top:18px;text-align:center;background:#07c160;color:#fff;text-decoration:none;padding:12px;border-radius:8px}'
            . '.link{display:block;margin-top:12px;text-align:center;color:#409eff;text-decoration:none;font-size:14px}'
            . '.hint{margin-top:16px;font-size:12px;color:#999;line-height:1.5}'
            . '</style></head><body>' . $body . '</body></html>';
    }
}
