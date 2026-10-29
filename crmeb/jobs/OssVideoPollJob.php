<?php
// +----------------------------------------------------------------------
// | 确认 IMM 转码结果：MNS 推送到达时立即执行一次，另有延时轮询兜底
// | 成功 → play_key 换成 720P 产物；失败 → 仍播原文件
// +----------------------------------------------------------------------

namespace crmeb\jobs;

use app\common\repositories\system\UploadMediaRepository;
use crmeb\interfaces\JobInterface;
use crmeb\services\oss\ImmService;
use crmeb\services\oss\OssDirectService;
use think\facade\Log;
use think\facade\Queue;

class OssVideoPollJob implements JobInterface
{
    const FIRST_DELAY = 30;
    /** 30 秒一次查 20 次，之后每 5 分钟查一次，总共约 2 小时 */
    const FAST_TRIES = 20;
    const MAX_TRIES = 40;

    public function fire($job, $data)
    {
        $job->delete();
        $id = (int)($data['id'] ?? 0);
        $try = (int)($data['try'] ?? 0);
        $repository = app()->make(UploadMediaRepository::class);
        $media = $repository->getMedia($id);
        if (!$media || (int)$media['status'] !== UploadMediaRepository::STATUS_TRANSCODING || (string)$media['imm_task_id'] === '') {
            return;
        }
        try {
            $task = ImmService::taskStatus((string)$media['imm_task_id']);
        } catch (\Throwable $e) {
            Log::error('查询转码任务失败 #' . $id . ': ' . $e->getMessage());
            $task = ['status' => '', 'message' => ''];
        }
        if ($task['status'] === 'Succeeded') {
            $repository->updateMedia($id, [
                'status' => UploadMediaRepository::STATUS_READY,
                'play_key' => OssDirectService::transcodedKey((string)$media['object_key']),
            ]);
            return;
        }
        if (in_array($task['status'], ['Failed', 'Cancelled'], true)) {
            Log::error('转码失败 #' . $id . ': ' . $task['message']);
            $repository->updateMedia($id, ['status' => UploadMediaRepository::STATUS_FAILED, 'meta' => mb_substr($task['message'], 0, 1000)]);
            return;
        }
        if ($try >= self::MAX_TRIES) {
            // MNS 推送触发的单次确认，不续排
            return;
        }
        if ($try + 1 >= self::MAX_TRIES) {
            Log::error('转码超时未完成 #' . $id);
            return;
        }
        Queue::later($try < self::FAST_TRIES ? 30 : 300, self::class, ['id' => $id, 'try' => $try + 1]);
    }

    public function failed($data)
    {
    }
}
