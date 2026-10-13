<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2020 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

namespace crmeb\services\wechat;


use crmeb\services\wechat\message\Article;
use crmeb\services\wechat\message\Image;
use crmeb\services\wechat\message\Media;
use crmeb\services\wechat\message\News;
use crmeb\services\wechat\message\NewsItem;
use crmeb\services\wechat\message\Text;
use crmeb\services\wechat\message\TextCard;
use crmeb\services\wechat\message\Transfer;
use crmeb\services\wechat\message\Video;
use crmeb\services\wechat\message\Voice;

/**
 * 消息回复模板
 * Class Messages
 * @package crmeb\services\wechat
 */
class Messages
{
    /**
     * 回复文本消息
     * @param string $content 文本内容
     * @return Text
     */
    public static function textMessage(string $content = ''): Text
    {
        $content = str_replace("\\n", "\n", $content); //返回数据处理换行符
        $content = str_replace("\\r", "\r", $content); //返回数据处理换行符
        return new Text($content);
    }

    /**
     * 回复图片消息
     * @param string $mediaId 媒体资源 ID
     * @return Image
     */
    public static function imageMessage(string $mediaId): Image
    {
        return new Image($mediaId);
    }

    /**
     * 回复视频消息
     * @param string $mediaId 媒体资源 ID
     * @param string $title 标题
     * @param string $description 描述
     * @param null $thumb_media_id 封面资源 ID
     * @return Video
     */
    public static function videoMessage(string $mediaId, string $title = '', string $description = '...', $thumb_media_id = null): Video
    {
        return new Video($mediaId, compact('title', 'description', 'thumb_media_id'));
    }

    /**
     * 回复声音消息
     * @param string $mediaId 媒体资源 ID
     * @return Voice
     */
    public static function voiceMessage(string $mediaId): Voice
    {
        return new Voice($mediaId);
    }

    /**
     * 回复图文消息
     * @param array|string $title
     * @param string $description
     * @param string $url
     * @param string $image
     * @return News
     */
    public static function newsMessage(array|string $title, string $description = '...', string $url = '', string $image = ''): News
    {
        if (is_array($title)) {
            if (isset($title[0]) && is_array($title[0])) {
                $items = [];
                foreach ($title as $news) {
                    $items[] = new NewsItem([
                        'title' => $news['title'] ?? '',
                        'description' => $news['description'] ?? '...',
                        'url' => $news['url'] ?? '',
                        'image' => $news['image'] ?? ''
                    ]);
                }
            } else {
                $items = [
                    new NewsItem([
                        'title' => $title['title'] ?? '',
                        'description' => $title['description'] ?? '...',
                        'url' => $title['url'] ?? '',
                        'image' => $title['image'] ?? ''
                    ])
                ];
            }
        } else {
            $items = [
                new NewsItem([
                    'title' => $title,
                    'description' => $description,
                    'url' => $url,
                    'image' => $image
                ])
            ];
        }
        return new News($items);
    }

    /**
     * 创建新闻消息类型
     * @param string|array $title
     * @param string $description
     * @param string $url
     * @param string $image
     * @return News
     */
    public static function newMessage(string|array $title, string $description = '...', string $url = '', string $image = ''): News
    {
        if (is_array($title)) {
            $items = [
                new NewsItem([
                    'title' => $title['title'],
                    'description' => $title['description'],
                    'url' => $title['url'],
                    'image' => $title['image']
                ])
            ];
        } else {
            $items = [
                new NewsItem([
                    'title' => $title,
                    'description' => $description,
                    'url' => $url,
                    'image' => $image
                ])
            ];
        }
        return new News($items);
    }

    /**
     * 回复文章消息
     * @param string|array $title 标题
     * @param string $thumb_media_id 图文消息的封面图片素材id（必须是永久 media_ID）
     * @param string $source_url 图文消息的原文地址，即点击“阅读原文”后的URL
     * @param string $content 图文消息的具体内容，支持HTML标签，必须少于2万字符，小于1M，且此处会去除JS
     * @param string $author 作者
     * @param string $digest 图文消息的摘要，仅有单图文消息才有摘要，多图文此处为空
     * @param int $show_cover_pic 是否显示封面，0为false，即不显示，1为true，即显示
     * @param int $need_open_comment 是否打开评论，0不打开，1打开
     * @param int $only_fans_can_comment 是否粉丝才可评论，0所有人可评论，1粉丝才可评论
     * @return Article
     */
    public static function articleMessage(array|string $title, string $thumb_media_id, string $source_url, string $content = '', string $author = '', string $digest = '', int $show_cover_pic = 0, int $need_open_comment = 0, int $only_fans_can_comment = 1): Article
    {
        $data = is_array($title) ? $title : compact('title', 'thumb_media_id', 'source_url', 'content', 'author', 'digest', 'show_cover_pic', 'need_open_comment', 'only_fans_can_comment');
        return new Article($data);
    }

    /**
     * 回复素材消息
     * @param string $mediaId
     * @param string|null $type mpnews、 mpvideo、voice、image
     * @return Media
     */
    public static function materialMessage(string $mediaId, string|null $type = null): Media
    {
        return new Media($mediaId, $type);
    }

    /**
     * 多客服消息转发
     * @param string|null $account
     * @return Transfer
     */
    public static function transfer(string $account = null): Transfer
    {
        return new Transfer($account);
    }

    /**
     * 消息模板
     * @param string $title
     * @param string $description
     * @param string $url
     * @return TextCard
     */
    public static function TextCardMessage(string $title, string $description, string $url): TextCard
    {
        return new TextCard([
            'title' => $title,
            'description' => $description,
            'url' => $url
        ]);
    }
}
