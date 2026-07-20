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


use Swoole\Timer;
use think\facade\Log;
use think\swoole\Manager;

class TimerService
{
    /**
     * @var Manager
     */
    protected $manager;

    protected $workerId = 0;

    public function __construct(Manager $manager)
    {
        $this->manager = $manager;
    }

    public function tick($limit, $fn)
    {
        if ($this->workerId !== $this->manager->getWorkerId()) {
            return null;
        }

        //$limit 任务执行时间: 1000 == 1秒
        return Timer::tick($limit, function () use ($fn) {
            $this->runInSandbox($fn);
        });
    }

    protected function runInSandbox(callable $fn): void
    {
        $this->manager->runWithBarrier([$this->manager, 'runInSandbox'], function () use ($fn) {
            try {
                $fn();
            } catch (\Throwable $e) {
                Log::error('定时器报错[' . class_basename($this) . ']: ' . $e->getMessage());
            }
        });
    }
}
