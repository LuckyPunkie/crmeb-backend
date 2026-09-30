<?php
// +----------------------------------------------------------------------
// | uni-sec-check Sprint 1: 微信文本内容安全检测 driver
// +----------------------------------------------------------------------

namespace crmeb\services\security\driver;

use crmeb\services\wechat\client\miniprogram\MediaCheckClient;
use crmeb\services\wechat\MiniProgram;
use crmeb\services\wechat\WechatResponse;

class WechatDriver
{
    /**
     * 小程序端文本内容安全检测（msgSecCheck V2，同步返回）
     *
     * 注意：V2 接口违规内容不是通过 errcode 抛错传达的——errcode=0 的正常响应里
     * result.suggest / result.label 才是真正的审核结论，调用方必须读取这两个字段，
     * 不能像旧代码那样直接忽略返回值。
     *
     * @param string $content
     * @param int $scene 微信场景值：1资料 2评论 3论坛 4社交日志
     * @param string $openid
     * @return array{pass:bool,suggest:string,label:int,label_text:string,trace_id:string}
     */
    public static function checkTextV2(string $content, int $scene, string $openid): array
    {
        $response = MiniProgram::msgSecCheck($content, $scene, $openid, 0);

        if (!$response instanceof WechatResponse) {
            // msgSecCheck 在 $openid 为空时会直接 return true，正常不会走到这里
            // （facade 层已经保证只有拿到 openid 才会调用本方法），这里只是防御
            return [
                'pass' => true,
                'suggest' => 'pass',
                'label' => 100,
                'label_text' => '正常',
                'trace_id' => '',
            ];
        }

        $result = is_array($response->result) ? $response->result : [];
        $suggest = $result['suggest'] ?? 'pass';
        $label = (int)($result['label'] ?? 100);

        return [
            'pass' => $suggest === 'pass',
            'suggest' => $suggest,
            'label' => $label,
            'label_text' => MediaCheckClient::LABEL[$label] ?? '未知',
            'trace_id' => (string)($response->trace_id ?? ''),
        ];
    }

    /**
     * 提交图片/音频异步检测（media_check_async V2），结果由微信推送到小程序消息推送地址
     *
     * @param int $mediaType 1 音频 / 2 图片（微信不支持视频，视频需先抽帧）
     * @return string trace_id
     * @throws \Throwable 3 次均失败时抛出最后一次异常
     */
    public static function submitMediaAsync(string $mediaUrl, int $mediaType, int $scene, string $openid): string
    {
        $lastError = null;
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $response = MiniProgram::msgSecCheck($mediaUrl, $scene, $openid, $mediaType);
                $traceId = $response instanceof WechatResponse ? (string)($response->trace_id ?? '') : '';
                if ($traceId !== '') {
                    return $traceId;
                }
                $lastError = new \RuntimeException('media_check_async 未返回 trace_id');
            } catch (\Throwable $e) {
                $lastError = $e;
            }
        }
        throw $lastError;
    }
}
