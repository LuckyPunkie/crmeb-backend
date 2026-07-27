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
        $red  = $this->dao->getByType('red');
        $paid = $this->dao->getByType('paid');

        return [
            'red'  => $red  ? $red->toArray()  : ['type' => 'red',  'rate' => 0],
            'paid' => $paid ? $paid->toArray() : ['type' => 'paid', 'rate' => 0],
            'logs' => $this->logDao->getRecentLogs(30),
        ];
    }

    public function saveConfig(float $redRate, float $paidRate, string $operator, string $remark = ''): void
    {
        if ($redRate < 0 || $redRate > 50) {
            throw new ValidateException('红包抽成比例须在 0~50 之间');
        }
        if ($paidRate < 0 || $paidRate > 50) {
            throw new ValidateException('付费阅读抽成比例须在 0~50 之间');
        }

        $red  = $this->dao->getByType('red');
        $paid = $this->dao->getByType('paid');

        if ($red && (float)$red['rate'] !== $redRate) {
            $this->dao->setRate('red', $redRate);
            $this->logDao->addLog([
                'type'     => 'red',
                'old_rate' => $red['rate'],
                'new_rate' => $redRate,
                'operator' => $operator,
                'remark'   => $remark,
            ]);
        }

        if ($paid && (float)$paid['rate'] !== $paidRate) {
            $this->dao->setRate('paid', $paidRate);
            $this->logDao->addLog([
                'type'     => 'paid',
                'old_rate' => $paid['rate'],
                'new_rate' => $paidRate,
                'operator' => $operator,
                'remark'   => $remark,
            ]);
        }
    }
}
