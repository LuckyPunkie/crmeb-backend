<?php

namespace app\common\repositories\system;

use app\common\dao\system\UploadMediaDao;
use app\common\repositories\BaseRepository;
use app\common\repositories\system\attachment\AttachmentCategoryRepository;
use app\common\repositories\system\attachment\AttachmentRepository;
use crmeb\jobs\OssVideoProcessJob;
use crmeb\services\oss\OssDirectService;
use crmeb\services\UploadService;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Queue;

/**
 * 直传 OSS 登记：回调落库、加入素材库、视频实际播放路径
 */
class UploadMediaRepository extends BaseRepository
{
    const STATUS_DETECTING = 0;
    const STATUS_READY = 1;
    const STATUS_TRANSCODING = 2;
    const STATUS_FAILED = -1;

    /**
     * @var UploadMediaDao
     */
    protected $dao;

    public function __construct(UploadMediaDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * OSS 回调（已验签）：校验令牌后登记，返回值会由 OSS 原样转给客户端
     * @param array $post 回调 body：key/size/mime/w/h/t
     */
    public function register(array $post): array
    {
        $payload = OssDirectService::parseToken((string)($post['t'] ?? ''));
        $key = (string)($post['key'] ?? '');
        if (!$payload || $key === '' || $key !== (string)$payload['key']) {
            throw new ValidateException('上传凭证无效');
        }
        $kind = (string)$payload['k'];
        $media = $this->dao->getByKey($key);
        if (!$media) {
            $media = $this->dao->create([
                'client' => (string)$payload['c'],
                'owner_id' => (int)$payload['o'],
                'mer_id' => (int)$payload['m'],
                'kind' => $kind,
                'object_key' => $key,
                'play_key' => $key,
                'size' => (int)($post['size'] ?? 0),
                'mime' => mb_substr((string)($post['mime'] ?? ''), 0, 64),
                'width' => (int)($post['w'] ?? 0),
                'height' => (int)($post['h'] ?? 0),
                'status' => $kind === OssDirectService::KIND_VIDEO ? self::STATUS_DETECTING : self::STATUS_READY,
                'update_time' => date('Y-m-d H:i:s'),
            ]);
            if ($kind === OssDirectService::KIND_VIDEO) {
                Queue::push(OssVideoProcessJob::class, ['id' => (int)$media['id']]);
            }
        }

        $url = OssDirectService::publicUrl($key);
        $data = [
            'key' => $key,
            'url' => $url,
            // 兼容各端原上传接口的返回字段：api 用 path，后台用 src
            'path' => $url,
            'src' => $url,
            'width' => (int)$media['width'],
            'height' => (int)$media['height'],
        ];
        if ($kind === OssDirectService::KIND_VIDEO) {
            $data['cover'] = OssDirectService::snapshotUrl($key);
        }
        if ((int)$payload['p'] >= 0 && in_array($payload['c'], [OssDirectService::CLIENT_SYS, OssDirectService::CLIENT_MER], true)) {
            $data['attachment_id'] = $this->addAttachment($payload, $url, $data['cover'] ?? '');
        }
        return $data;
    }

    /** 后台 / 商家后台上传时同时写素材库，素材库照常可选用 */
    protected function addAttachment(array $payload, string $url, string $cover): int
    {
        $merId = (int)$payload['m'];
        $categoryId = (int)$payload['p'];
        if ($categoryId && !app()->make(AttachmentCategoryRepository::class)->get($categoryId, $merId)) {
            $categoryId = 0;
        }
        $name = (string)$payload['n'];
        $dot = strrpos($name, '.');
        $isVideo = $payload['k'] === OssDirectService::KIND_VIDEO;
        $attachment = app()->make(AttachmentRepository::class)->create(UploadService::STORAGE_ALIYUN, $merId, (int)$payload['o'], [
            'attachment_category_id' => $categoryId,
            'attachment_name' => ($dot ? substr($name, 0, $dot) : $name) ?: basename($payload['key']),
            'attachment_src' => $url,
            'attachment_cover' => $cover,
            'attachment_type' => $isVideo ? AttachmentRepository::TYPE_VIDEO : AttachmentRepository::TYPE_IMAGE,
        ]);
        return (int)$attachment->attachment_id;
    }

    /**
     * 源路径 → 实际播放路径（转码完成的换成 720P 产物）
     * @return array object_key => play_key
     */
    public function playKeys(array $objectKeys): array
    {
        $result = [];
        $miss = [];
        foreach (array_unique($objectKeys) as $key) {
            $cached = Cache::get($this->playCacheKey($key));
            if (is_string($cached) && $cached !== '') {
                $result[$key] = $cached;
            } else {
                $miss[] = $key;
            }
        }
        if ($miss) {
            $found = $this->dao->playKeys($miss);
            foreach ($miss as $key) {
                $play = (string)($found[$key] ?? '') ?: $key;
                $result[$key] = $play;
                // 转码中的视频之后还会变，缓存短一点
                Cache::set($this->playCacheKey($key), $play, $play === $key ? 120 : 86400);
            }
        }
        return $result;
    }

    public function updateMedia(int $id, array $data): void
    {
        $data['update_time'] = date('Y-m-d H:i:s');
        $this->dao->update($id, $data);
        $media = $this->dao->get($id);
        if ($media) {
            Cache::delete($this->playCacheKey((string)$media['object_key']));
        }
    }

    public function getMedia(int $id)
    {
        return $this->dao->get($id);
    }

    public function getByKey(string $key)
    {
        return $this->dao->getByKey($key);
    }

    public function getByTaskId(string $taskId)
    {
        return $this->dao->getByTaskId($taskId);
    }

    protected function playCacheKey(string $key): string
    {
        return 'oss_play:' . md5($key);
    }
}
