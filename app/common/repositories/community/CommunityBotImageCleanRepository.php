<?php

namespace app\common\repositories\community;

use app\common\repositories\user\UserRepository;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;

/**
 * 机器人帖外链图失效清理（由 C 端 image @error 上报触发）
 */
class CommunityBotImageCleanRepository
{
    /**
     * 上报某帖某张图加载失败
     *
     * @return array{removed:bool,post_deleted:bool,user_cancelled:bool}
     */
    public function reportFail(int $communityId, string $imageUrl): array
    {
        $result = ['removed' => false, 'post_deleted' => false, 'user_cancelled' => false];
        $imageUrl = trim($imageUrl);
        if ($communityId <= 0 || $imageUrl === '') {
            return $result;
        }

        $lockKey = 'bot_img_fail:' . $communityId . ':' . md5($this->normalizeUrl($imageUrl));
        if (Cache::get($lockKey)) {
            return $result;
        }
        Cache::set($lockKey, 1, 5);

        $row = Db::name('community')
            ->where('community_id', $communityId)
            ->where('is_del', 0)
            ->field('community_id,uid,image')
            ->find();
        if (!$row) {
            return $result;
        }

        $uid = (int)$row['uid'];
        $author = Db::name('user')
            ->where('uid', $uid)
            ->field('uid,user_type,status,cancel_time')
            ->find();
        if (!$author || ($author['user_type'] ?? '') !== 'import') {
            return $result;
        }
        if (!empty($author['cancel_time']) || (int)($author['status'] ?? 0) !== 1) {
            return $result;
        }

        $images = $this->parseImages($row['image'] ?? '');
        if (!$images) {
            return $result;
        }

        $alive = [];
        $removed = false;
        foreach ($images as $img) {
            if ($this->urlsEqual($img, $imageUrl)) {
                $removed = true;
                continue;
            }
            $alive[] = $img;
        }
        if (!$removed) {
            return $result;
        }

        $result['removed'] = true;
        $communityRepo = app()->make(CommunityRepository::class);

        if (!$alive) {
            $communityRepo->destory($communityId);
            $result['post_deleted'] = true;
            if ($this->shouldCancelBot($uid)) {
                $this->cancelBot($uid);
                $result['user_cancelled'] = true;
            }
            return $result;
        }

        Db::name('community')
            ->where('community_id', $communityId)
            ->update(['image' => implode(',', $alive)]);

        return $result;
    }

    /**
     * 该机器人是否已没有任何「未删且仍有图」的帖
     */
    protected function shouldCancelBot(int $uid): bool
    {
        $images = Db::name('community')
            ->where('uid', $uid)
            ->where('is_del', 0)
            ->column('image');
        foreach ($images as $image) {
            if ($this->parseImages($image)) {
                return false;
            }
        }
        return true;
    }

    protected function cancelBot(int $uid): void
    {
        try {
            $userRepo = app()->make(UserRepository::class);
            $user = $userRepo->get($uid);
            if (!$user || !empty($user['cancel_time'])) {
                return;
            }
            if (($user['user_type'] ?? '') !== 'import') {
                return;
            }
            $userRepo->cancel($user);
            Log::info('bot image all gone, cancelled uid=' . $uid);
        } catch (\Throwable $e) {
            Log::error('bot cancel failed uid=' . $uid . ' ' . $e->getMessage());
        }
    }

    protected function parseImages($raw): array
    {
        if (is_array($raw)) {
            $list = $raw;
        } else {
            $raw = trim((string)$raw);
            if ($raw === '') {
                return [];
            }
            $list = explode(',', $raw);
        }
        $out = [];
        foreach ($list as $img) {
            $img = trim((string)$img);
            if ($img !== '') {
                $out[] = $img;
            }
        }
        return $out;
    }

    protected function normalizeUrl(string $url): string
    {
        $url = trim($url);
        $url = preg_replace('#^http://#i', 'https://', $url);
        return rtrim($url, '/');
    }

    protected function urlsEqual(string $a, string $b): bool
    {
        return $this->normalizeUrl($a) === $this->normalizeUrl($b);
    }
}
