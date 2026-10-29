<?php

namespace app\controller\api;

use app\common\repositories\system\UploadMediaRepository;
use crmeb\basic\BaseController;
use crmeb\jobs\OssVideoPollJob;
use crmeb\services\oss\MnsSignature;
use crmeb\services\oss\OssDirectService;
use think\exception\ValidateException;
use think\facade\Log;
use think\facade\Queue;

/**
 * 全站图片/视频直传 OSS：签名、OSS 回调、IMM 转码完成通知
 * 后台 / 商家后台 / 客服的签名接口各在自己的控制器里，回调统一走这里
 */
class OssUpload extends BaseController
{
    /** 各端启动时读一次：关闭时继续走原上传接口 */
    public function config()
    {
        $cfg = OssDirectService::config();
        return app('json')->success([
            'open' => OssDirectService::enabled($cfg) ? 1 : 0,
            'image_max_mb' => (int)$cfg['image_max_mb'] ?: 20,
            'video_max_mb' => (int)$cfg['video_max_mb'] ?: 300,
        ]);
    }

    /** 未登录不签：前端回退到原上传接口 */
    public function sign()
    {
        if (!$this->request->isLogin()) {
            return app('json')->fail('请先登录');
        }
        $file = $this->request->params(['kind', 'ext', 'size', 'name']);
        try {
            return app('json')->success(OssDirectService::sign(OssDirectService::CLIENT_API, (int)$this->request->uid(), 0, $file));
        } catch (\RuntimeException $e) {
            Log::error('OSS 直传签名失败(api): ' . $e->getMessage());
            return app('json')->fail($e->getMessage());
        }
    }

    /** OSS 上传完成回调：验签 → 登记；返回体由 OSS 转给客户端 */
    public function callback(UploadMediaRepository $repository)
    {
        [$path, $query] = array_pad(explode('?', (string)$this->request->url(), 2), 2, '');
        $verified = OssDirectService::verifyOssCallback(
            (string)$this->request->header('authorization'),
            (string)$this->request->header('x-oss-pub-key-url'),
            $path,
            $query,
            (string)$this->request->getContent()
        );
        if (!$verified) {
            Log::warning('OSS 回调验签失败: ' . $path);
            return response(['status' => 403, 'message' => '回调签名错误'], 403, [], 'json');
        }
        try {
            return app('json')->success($repository->register($this->request->post()));
        } catch (ValidateException $e) {
            return response(['status' => 400, 'message' => $e->getMessage()], 400, [], 'json');
        }
    }

    /** MNS 推送 IMM 转码完成：验签后按 TaskId 找到视频，交给轮询任务用 GetTask 确认状态 */
    public function immNotify(UploadMediaRepository $repository)
    {
        $headers = array_change_key_case((array)$this->request->header(), CASE_LOWER);
        $path = explode('?', (string)$this->request->url(), 2)[0];
        if (!MnsSignature::verify($this->request->method(), $path, $headers)) {
            Log::warning('MNS 推送验签失败');
            return response('', 403);
        }
        $taskId = $this->findTaskId((string)$this->request->getContent());
        $media = $taskId !== '' ? $repository->getByTaskId($taskId) : null;
        if ($media) {
            // 只确认一次，不再续排：延时轮询链仍在跑，避免两条链重复查询
            Queue::push(OssVideoPollJob::class, ['id' => (int)$media['id'], 'try' => OssVideoPollJob::MAX_TRIES]);
        }
        return response('', 204);
    }

    /** MNS 消息体可能是 JSON、内层 Message 再嵌一层 JSON 字符串或 base64，统一按正则找 TaskId */
    protected function findTaskId(string $body): string
    {
        $candidates = [$body];
        $json = json_decode($body, true);
        if (is_array($json) && isset($json['Message']) && is_string($json['Message'])) {
            $candidates[] = $json['Message'];
            $decoded = base64_decode($json['Message'], true);
            if ($decoded !== false) {
                $candidates[] = $decoded;
            }
        }
        foreach ($candidates as $text) {
            if (preg_match('/\\\\?"TaskId\\\\?"\s*:\s*\\\\?"([\w\-]+)/', $text, $m)) {
                return $m[1];
            }
        }
        return '';
    }
}
