<?php
/**
 *  +----------------------------------------------------------------------
 *  | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
 *  +----------------------------------------------------------------------
 *  | Copyright (c) 2016~2022 https://www.crmeb.com All rights reserved.
 *  +----------------------------------------------------------------------
 *  | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
 *  +----------------------------------------------------------------------
 *  | Author: CRMEB Team <admin@crmeb.com>
 *  +----------------------------------------------------------------------
 */

namespace crmeb\services\wechat\message;


class Article extends Message
{
    /**
     * @var string
     */
    protected string $type = 'mpnews';

    /**
     * Properties.
     *
     * @var array
     */
    protected array $properties = [
        'thumb_media_id',
        'author',
        'title',
        'content',
        'digest',
        'source_url',
        'show_cover',
    ];

    /**
     * Aliases of attribute.
     *
     * @var array
     */
    protected array $jsonAliases = [
        'content_source_url' => 'source_url',
        'show_cover_pic' => 'show_cover',
    ];

    /**
     * @var array
     */
    protected array $required = [
        'thumb_media_id',
        'title',
        'content',
        'show_cover',
    ];
}
