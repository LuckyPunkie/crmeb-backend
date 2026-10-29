<?php
// +----------------------------------------------------------------------
// | 直传视频落库后：IMM 读编码信息 → 已是 H.264+AAC 的直接播原文件，
// | 其余提交 720P 单档转码，完成由 MNS 推送或 OssVideoPollJob 轮询确认
// +----------------------------------------------------------------------

namespace crmeb\jobs;

use app\common\repositories\system\UploadMediaRepository;
use crmeb\interfaces\JobInterface;
use crmeb\services\oss\ImmService;
use crmeb\services\oss\OssDirectService;
use think\facade\Log;
use think\facade\Queue;

class OssVideoProcessJob implements JobInterface
{
    public function fire($job, $data)
    {
        $id = (int)($data['id'] ?? 0);
        $repository = app()->make(UploadMediaRepository::class);
        $media = $repository->getMedia($id);
        if (!$media || (int)$media['status'] !== UploadMediaRepository::STATUS_DETECTING) {
            $job->delete();
            return;
        }
        $key = (string)$media['object_key'];
        try {
            $meta = ImmService::detect($key);
            $update = [
                'width' => $meta['width'],
                'height' => $meta['height'],
                'duration' => $meta['duration'],
                'meta' => json_encode($meta, JSON_UNESCAPED_SLASHES),
            ];
            if (!ImmService::needsTranscode($meta)) {
                $repository->updateMedia($id, $update + ['status' => UploadMediaRepository::STATUS_READY, 'play_key' => $key]);
                $job->delete();
                return;
            }
            $taskId = ImmService::convert($key, OssDirectService::transcodedKey($key), $meta, (string)$id);
            $repository->updateMedia($id, $update + ['status' => UploadMediaRepository::STATUS_TRANSCODING, 'imm_task_id' => $taskId]);
            // MNS 推送是主通道，轮询兜底（没配 MNS 也能跑）
            Queue::later(OssVideoPollJob::FIRST_DELAY, OssVideoPollJob::class, ['id' => $id, 'try' => 0]);
        } catch (\Throwable $e) {
            Log::error('直传视频处理失败 #' . $id . ' ' . $key . ': ' . $e->getMessage());
            if ($job->attempts() < 3) {
                $job->release(60);
                return;
            }
            // 读不到编码信息 / 提交失败：仍播原文件，后台可从 meta 看原因
            $repository->updateMedia($id, ['status' => UploadMediaRepository::STATUS_FAILED, 'meta' => mb_substr($e->getMessage(), 0, 1000)]);
        }
        $job->delete();
    }

    public function failed($data)
    {
    }
}
