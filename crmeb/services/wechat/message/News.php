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


class News extends Message
{
    /**
     * @var string
     */
    protected string $type = 'news';

    /**
     * @var array
     */
    protected array $properties = [
        'items',
    ];

    /**
     * News constructor.
     *
     * @param array $items
     */
    public function __construct(array $items = [])
    {
        parent::__construct(compact('items'));
    }

    /**
     * @param array $data
     * @param array $aliases
     *
     * @return array
     */
    public function propertiesToArray(array $data, array $aliases = []): array
    {
        return ['articles' => array_map(function ($item) {
            if ($item instanceof NewsItem) {
                return $item->toJsonArray();
            }
        }, $this->get('items'))];
    }

    /**
     * @return array
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/26
     */
    public function toXmlArray(): array
    {
        $items = [];

        foreach ($this->get('items') as $item) {
            if ($item instanceof NewsItem) {
                $items[] = $item->toXmlArray();
            }
        }

        return [
            'ArticleCount' => count($items),
            'Articles' => $items,
        ];
    }
}
