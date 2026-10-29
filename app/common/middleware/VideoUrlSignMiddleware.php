<?php

namespace app\common\middleware;

use app\common\repositories\system\UploadMediaRepository;
use app\Request;
use crmeb\services\oss\OssDirectService;
use think\Response;
use think\response\Json;

/**
 * 视频 CDN 地址统一在输出时签名（全局中间件）
 * 库里存的是不带签名的视频 CDN 地址；所有 JSON 响应在这里：
 *   1. 源文件已转码的换成 720P 产物路径
 *   2. 加上 A 类鉴权 auth_key（已有的旧 auth_key 会被替换）
 * 这样商品、社区、素材库、DIY 等各处的视频字段都不用逐个改
 */
class VideoUrlSignMiddleware extends BaseMiddleware
{
    public function before(Request $request)
    {
    }

    public function after(Response $response)
    {
        if (!$response instanceof Json) {
            return;
        }
        $videoCdn = rtrim(trim((string)systemConfig('oss_direct_video_cdn')), '/');
        $host = $videoCdn !== '' ? (string)parse_url($videoCdn, PHP_URL_HOST) : '';
        if ($host === '') {
            return;
        }
        $content = $response->getContent();
        if (strpos($content, $host) === false) {
            return;
        }
        $cfg = OssDirectService::config();
        if (!OssDirectService::videoAuthEnabled($cfg)) {
            return;
        }
        $response->content($this->rewrite($content, $host, $cfg));
    }

    protected function rewrite(string $content, string $host, array $cfg): string
    {
        // JSON 里 / 可能被转义成 \/；占有量词防止回溯截断路径；后面跟别的 query 的不处理
        $pattern = '#https?:\\\\?/\\\\?/' . preg_quote($host, '#')
            . '((?:\\\\?/[A-Za-z0-9._~%\-]++)++)(?:\?auth_key=[\w\-]*+)?+(?!\?)#';
        if (!preg_match_all($pattern, $content, $matches)) {
            return $content;
        }
        $keys = [];
        foreach ($matches[1] as $path) {
            $key = ltrim(str_replace('\\', '', $path), '/');
            if (strpos($key, 'video/') === 0) {
                $keys[] = $key;
            }
        }
        $playKeys = $keys ? app()->make(UploadMediaRepository::class)->playKeys($keys) : [];

        return preg_replace_callback($pattern, function ($m) use ($playKeys, $cfg) {
            $key = ltrim(str_replace('\\', '', $m[1]), '/');
            if (strpos($key, 'video/') !== 0) {
                return $m[0];
            }
            $signed = OssDirectService::signVideoPath($playKeys[$key] ?? $key, $cfg);
            return strpos($m[1], '\\/') !== false ? str_replace('/', '\\/', $signed) : $signed;
        }, $content);
    }
}
