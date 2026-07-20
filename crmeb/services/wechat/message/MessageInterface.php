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


interface MessageInterface
{
    /**
     * @return string
     */
    public function getType(): string;

    /**
     * @param array $appends
     * @param bool $withType
     * @return array
     */
    public function transformForJsonRequest(array $appends = [], bool $withType = true): array;

    /**
     * @return string|array
     */
    public function transformToXml();
}
