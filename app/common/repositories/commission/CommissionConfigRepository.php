<?php

namespace app\common\repositories\commission;

use app\common\dao\commission\CommissionConfigDao;
use app\common\dao\commission\CommissionConfigLogDao;
use app\common\repositories\BaseRepository;
use think\exception\ValidateException;

class CommissionConfigRepository extends BaseRepository
{
    protected CommissionConfigLogDao $logDao;

    public function __construct(CommissionConfigDao $dao, CommissionConfigLogDao $logDao)
    {
        $this->dao    = $dao;
        $this->logDao = $logDao;
    }

    public function getConfig(): array
    {
        $red   = $this->dao->getByType('red');
        $paid  = $this->dao->getByType('paid');
        $video = $this->dao->getByType('video');

        return [
            'red'   => $red   ? $red->toArray()   : ['type' => 'red',   'rate' => 0],
            'paid'  => $paid  ? $paid->toArray()  : ['type' => 'paid',  'rate' => 0],
            'video' => $video ? $video->toArray() : ['type' => 'video', 'rate' => 0],
            'logs'  => $this->logDao->getRecentLogs(30),
        ];
    }

    public function saveConfig(float $redRate, float $paidRate, float $videoRate, string $operator, string $remark = ''): void
    {
        if ($redRate < 0 || $redRate > 100) {
            throw new ValidateException('红包抽成比例须在 0~100 之间');
        }
        if ($paidRate < 0 || $paidRate > 100) {
            throw new ValidateException('付费阅读抽成比例须在 0~100 之间');
        }
        if ($videoRate < 0 || $videoRate > 100) {
            throw new ValidateException('付费视频抽成比例须在 0~100 之间');
        }

        $red   = $this->dao->getByType('red');
        $paid  = $this->dao->getByType('paid');
        $video = $this->dao->getByType('video');

        if ($red && round((float)$red['rate'], 2) !== round($redRate, 2)) {
            $this->dao->setRate('red', $redRate);
            $this->logDao->addLog([
                'type'     => 'red',
                'old_rate' => $red['rate'],
                'new_rate' => $redRate,
                'operator' => $operator,
                'remark'   => $remark,
            ]);
        }

        if ($paid && round((float)$paid['rate'], 2) !== round($paidRate, 2)) {
            $this->dao->setRate('paid', $paidRate);
            $this->logDao->addLog([
                'type'     => 'paid',
                'old_rate' => $paid['rate'],
                'new_rate' => $paidRate,
                'operator' => $operator,
                'remark'   => $remark,
            ]);
        }

        if ($video && round((float)$video['rate'], 2) !== round($videoRate, 2)) {
            $this->dao->setRate('video', $videoRate);
            $this->logDao->addLog([
                'type'     => 'video',
                'old_rate' => $video['rate'],
                'new_rate' => $videoRate,
                'operator' => $operator,
                'remark'   => $remark,
            ]);
        } elseif (!$video) {
            \think\facade\Db::name('commission_config')->insert([
                'type'            => 'video',
                'rate'            => $videoRate,
                'effective_date'  => date('Y-m-d', strtotime('+1 day')),
                'update_time'     => date('Y-m-d H:i:s'),
            ]);
            $this->logDao->addLog([
                'type'     => 'video',
                'old_rate' => 0,
                'new_rate' => $videoRate,
                'operator' => $operator,
                'remark'   => $remark,
            ]);
        }
    }

    /** 获取抽佣比例（小数，如 0.1 表示 10%） */
    public function getRateDecimal(string $type): float
    {
        $config = $this->dao->getByType($type);
        if (!$config) {
            return 0.0;
        }
        return round((float)$config['rate'] / 100, 4);
    }
}
