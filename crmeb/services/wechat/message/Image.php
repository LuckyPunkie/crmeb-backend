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
 * 图片
 * Class Image
 * @author 等风来
 * @email 136327134@qq.com
 * @date 2023/9/14
 * @package crmeb\services\wechat\message
 */
class Image extends Media
{
    /**
     * 消息类型
     * @var string
     */
    protected string $type = 'image';
}
