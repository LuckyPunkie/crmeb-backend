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


class Video extends Media
{
    /**
     **
     * Messages type.
     *
     * @var string
     */
    protected string $type = 'video';

    /**
     * Properties.
     *
     * @var array
     */
    protected array $properties = [
        'title',
        'description',
        'media_id',
        'thumb_media_id',
    ];

    /**
     * Video constructor.
     *
     * @param string $mediaId
     * @param array $attributes
     */
    public function __construct(string $mediaId, array $attributes = [])
    {
        parent::__construct($mediaId, 'video', $attributes);
    }

    /**
     * @return array[]
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/26
     */
    public function toXmlArray(): array
    {
        return [
            'Video' => [
                'MediaId' => $this->get('media_id'),
                'Title' => $this->get('title'),
                'Description' => $this->get('description'),
            ],
        ];
    }
}
