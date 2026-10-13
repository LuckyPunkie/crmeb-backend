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


namespace app\common\repositories\store;


use app\common\repositories\BaseRepository;
use think\facade\Cache;

class IntegralRepository extends BaseRepository
{
    const CACHE_KEY = 'sys_int_next_day';

    /**
     * 获取下个月的第一天的日期戳
     *
     * 本函数用于计算当前日期所在月份结束后，下一个月的第一天的日期戳。
     * 通过strtotime函数结合日期字符串计算得出，确保了计算结果的准确性。
     *
     * @return int 返回下一个月第一天的日期戳
     */
    public function getNextDay()
    {
        // 使用strtotime和日期字符串计算下个月的第一天的日期戳
        return strtotime(date('Y-m-d', strtotime('first day of +1 month 00:00:00')));
    }


    /**
     * 获取无效积分的截止日期
     *
     * 本函数用于计算无效积分的截止日期，即超过此日期的积分将被视为无效。截止日期是根据系统配置的积分清理时间
     * （integral_clear_time）来确定的，它会返回距离当前时间最近的过去一个整月的最后一天的23:59:59的时间戳。
     * 如果系统配置的积分清理时间小于等于0，则认为不进行积分清理，直接返回0。
     *
     * @return int 返回无效积分截止日期的时间戳，如果积分清理时间设置为0或负数，则返回0。
     */
    public function getInvalidDay()
    {
        // 从系统配置中获取积分清理时间，单位为月
        $month = systemConfig('integral_clear_time');

        // 如果积分清理时间小于等于0，则不进行积分清理，直接返回0
        if ($month <= 0) return 0;

        // 计算截止日期的时间戳，即上个月的最后一天的23:59:59
        // 使用strtotime函数来解析日期字符串，并指定参考时间为基础时间戳减去1
        return strtotime('last day of -' . $month . ' month 23:59:59', $this->getTimeoutDay() - 1);
    }

    /**
     * 获取缓存中的过期天数，如果缓存不存在或需要清除，则重新计算并设置缓存。
     *
     * 此方法主要用于获取或更新一个特定缓存键的值，该值表示距离某个事件的过期天数。
     * 如果缓存已经存在且没有明确要求清除，则直接返回缓存值，以提高效率。
     * 如果缓存不存在，或者通过参数明确要求清除缓存，则重新计算过期天数，并设置新的缓存值。
     * 设置的缓存有效期为20天加12小时，以确保缓存不过期太快，同时允许一定的缓冲时间。
     *
     * @param bool $clear 是否清除缓存。如果设置为true，则无论缓存是否存在都会重新计算并设置缓存值。
     * @return mixed 返回缓存中的过期天数。如果缓存不存在且无法设置新缓存，则可能返回false。
     */
    public function getTimeoutDay($clear = false)
    {
        // 使用文件缓存驱动器实例
        $driver = Cache::store('file');

        // 检查缓存是否存在，或者是否需要清除缓存
        if (!$driver->has(self::CACHE_KEY) || $clear) {
            // 计算下一个过期天数，并设置缓存，有效期为20天加12小时
            $driver->set(self::CACHE_KEY, $this->getNextDay(), (20 * 24 * 3600) + 3600 * 12);
        }

        // 返回缓存中的过期天数
        return $driver->get(self::CACHE_KEY);
    }


    /**
     * 清除当天的定时任务执行时间缓存
     *
     * 本方法用于清除使用文件存储的缓存中特定键的值。特定键是预定义的常量，
     * 代表了当天的定时任务执行时间。通过清除这个缓存，可以迫使系统重新计算
     * 并存储定时任务的下次执行时间，以应对可能的配置更改或系统重启等情况。
     *
     * @return void 没有返回值
     */
    public function clearTimeoutDay()
    {
        // 使用文件缓存存储驱动，删除指定的缓存键
        Cache::store('file')->delete(self::CACHE_KEY);
    }

}
