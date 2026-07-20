<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016-2026 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

namespace crmeb\services;

use think\facade\Cache;

/**
 * crmeb 缓存类
 * Class CacheService
 * @package crmeb\services
 * @mixin \Redis
 * @mixin \think\cache\driver\Redis
 */
class RedisCacheService
{
    public function handler()
    {
        return $this->driver()->handler();
    }

    public function driver()
    {
        return Cache::store('redis');
    }

    public function __call($name, $arguments)
    {
        $driver = $this->driver();
        if (method_exists($driver, $name)) {
            return call_user_func_array([$driver, $name], $arguments);
        }
        return call_user_func_array([$driver->handler(), $name], $arguments);
    }

}
