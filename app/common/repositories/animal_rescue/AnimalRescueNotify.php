<?php

namespace app\common\repositories\animal_rescue;

use app\common\repositories\user\UserNotificationRepository;
use think\facade\Log;

/**
 * 爱心救助站内通知
 */
class AnimalRescueNotify
{
    /**
     * 发送系统通知（消息中心 type=animal_rescue）
     */
    public static function send(
        int $uid,
        string $title,
        string $text,
        int $postId = 0,
        int $postType = 1,
        string $jump = ''
    ): void {
        if ($uid <= 0) {
            return;
        }
        try {
            if ($jump === '' && $postId > 0) {
                $jump = '/pages/animal_rescue/detail/index?id=' . $postId . '&type=' . $postType;
            }
            $payload = UserNotificationRepository::buildNoteContent([
                'text' => $text,
                'content' => $text,
                'title' => $title,
                'post_id' => $postId,
                'post_type' => $postType,
                'jump' => $jump,
            ]);
            app()->make(UserNotificationRepository::class)->createAndPush(
                $uid,
                0,
                'animal_rescue',
                $title,
                $payload,
                'animal_rescue',
                $postId
            );
        } catch (\Throwable $e) {
            Log::error('animal_rescue notify fail: uid=' . $uid . ' title=' . $title . ' err=' . $e->getMessage());
        }
    }

    public static function postTitle($post): string
    {
        if (!$post) {
            return '爱心救助';
        }
        $title = is_array($post) ? (string)($post['title'] ?? '') : (string)($post->title ?? '');
        $animal = is_array($post) ? (string)($post['animal_name'] ?? '') : (string)($post->animal_name ?? '');
        $name = $title !== '' ? $title : $animal;
        return $name !== '' ? $name : '爱心救助';
    }

    public static function postType($post): int
    {
        if (!$post) {
            return 1;
        }
        return (int)(is_array($post) ? ($post['type'] ?? 1) : ($post->type ?? 1));
    }
}
