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

/**
 * text消息
 * Class Text
 * @author 等风来
 * @email 136327134@qq.com
 * @date 2023/9/14
 * @package crmeb\services\wechat\message
 */
class Text extends Message
{
    /**
     * Message type.
     *
     * @var string
     */
    protected string $type = 'text';

    /**
     * Properties.
     *
     * @var array
     */
    protected array $properties = ['content'];

    /**
     * Text constructor.
     *
     * @param string $content
     */
    public function __construct(string $content = '')
    {
        parent::__construct(compact('content'));
    }

    /**
     * @return array
     */
    public function toXmlArray(): array
    {
        return [
            'Content' => $this->get('content'),
        ];
    }
}
