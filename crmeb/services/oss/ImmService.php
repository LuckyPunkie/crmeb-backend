<?php
// +----------------------------------------------------------------------
// | IMM 2020-09-30：读视频编码信息、提交 720P 转码、查询任务状态
// +----------------------------------------------------------------------

namespace crmeb\services\oss;

class ImmService
{
    const VERSION = '2020-09-30';
    /** 输出短边 720（横屏 1280×720，竖屏 720×1280），不放大 */
    const TARGET_SHORT_SIDE = 720;

    protected static function call(string $action, array $params, int $timeout = 15): array
    {
        $cfg = OssDirectService::config();
        if ($cfg['imm_project'] === '') {
            throw new \RuntimeException('未配置 IMM 项目');
        }
        $params = ['ProjectName' => $cfg['imm_project']] + $params;
        return AliyunRpcClient::call('imm.' . $cfg['region'] . '.aliyuncs.com', self::VERSION, $action, $params,
            $cfg['access_key_id'], $cfg['access_key_secret'], $timeout);
    }

    protected static function uri(string $key): string
    {
        return 'oss://' . OssDirectService::config()['bucket'] . '/' . ltrim($key, '/');
    }

    /**
     * @return array{video_codec:string,audio_codec:string,format:string,width:int,height:int,duration:float}
     */
    public static function detect(string $key): array
    {
        $res = self::call('DetectMediaMeta', ['SourceURI' => self::uri($key)], 30);
        $video = $res['VideoStreams'][0] ?? [];
        $audio = $res['AudioStreams'][0] ?? [];
        $width = (int)($video['Width'] ?? 0);
        $height = (int)($video['Height'] ?? 0);
        // 手机竖拍常见编码宽高 + rotate 90，按显示方向算
        if (in_array(abs((int)($video['Rotate'] ?? 0)), [90, 270], true)) {
            [$width, $height] = [$height, $width];
        }
        return [
            'video_codec' => strtolower((string)($video['CodecName'] ?? '')),
            'audio_codec' => strtolower((string)($audio['CodecName'] ?? '')),
            'format' => strtolower((string)($res['FormatName'] ?? '')),
            'width' => $width,
            'height' => $height,
            'duration' => (float)($res['Duration'] ?? $video['Duration'] ?? 0),
        ];
    }

    /** 已是 H.264 +（AAC 或无音轨）+ mp4/mov 容器的直接播原文件 */
    public static function needsTranscode(array $meta): bool
    {
        if ($meta['video_codec'] !== 'h264') {
            return true;
        }
        if ($meta['audio_codec'] !== '' && $meta['audio_codec'] !== 'aac') {
            return true;
        }
        return strpos($meta['format'], 'mp4') === false && strpos($meta['format'], 'mov') === false;
    }

    /** 等比缩到短边 720，取偶数；源比 720 小就保持原尺寸 */
    public static function targetResolution(int $width, int $height): string
    {
        if ($width <= 0 || $height <= 0) {
            return '1280x720';
        }
        $short = min($width, $height);
        $scale = $short > self::TARGET_SHORT_SIDE ? self::TARGET_SHORT_SIDE / $short : 1;
        $w = (int)(round($width * $scale / 2) * 2);
        $h = (int)(round($height * $scale / 2) * 2);
        return $w . 'x' . $h;
    }

    /** @return string TaskId */
    public static function convert(string $srcKey, string $dstKey, array $meta, string $userData = ''): string
    {
        $cfg = OssDirectService::config();
        $params = [
            'Sources' => [['URI' => self::uri($srcKey)]],
            'Targets' => [[
                'URI' => self::uri($dstKey),
                'Container' => 'mp4',
                'Video' => ['TranscodeVideo' => [
                    'Codec' => 'h264',
                    'Resolution' => self::targetResolution((int)$meta['width'], (int)$meta['height']),
                    'ScaleType' => 'fit',
                ]],
                'Audio' => ['TranscodeAudio' => ['Codec' => 'aac']],
            ]],
            'UserData' => $userData,
        ];
        if ($cfg['mns_topic'] !== '') {
            $params['Notification'] = ['MNS' => ['TopicName' => $cfg['mns_topic']]];
        }
        $res = self::call('CreateMediaConvertTask', $params);
        $taskId = (string)($res['TaskId'] ?? '');
        if ($taskId === '') {
            throw new \RuntimeException('CreateMediaConvertTask 未返回 TaskId');
        }
        return $taskId;
    }

    /** @return array{status:string,message:string} status：Running / Succeeded / Failed 等 */
    public static function taskStatus(string $taskId): array
    {
        $res = self::call('GetTask', ['TaskType' => 'MediaConvert', 'TaskId' => $taskId]);
        return [
            'status' => (string)($res['Status'] ?? ''),
            'message' => (string)($res['Message'] ?? $res['Code'] ?? ''),
        ];
    }
}
