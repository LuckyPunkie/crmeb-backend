<?php
// +----------------------------------------------------------------------
// | 扫码下单 - 配置 Repository
// +----------------------------------------------------------------------

namespace app\common\repositories\store\scanOrder;

use app\common\dao\store\scanOrder\ScanOrderConfigDao;
use app\common\model\store\scanOrder\ScanOrderConfig;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\StorePrinterRepository;
use think\exception\ValidateException;

class ScanOrderConfigRepository extends BaseRepository
{
    public function __construct(ScanOrderConfigDao $dao)
    {
        $this->dao = $dao;
    }

    public function defaults(): array
    {
        return [
            'need_pay' => 1,
            'voice_enable' => 1,
            'auto_print' => 0,
        ];
    }

    public function getConfig(int $merId): array
    {
        $row = ScanOrderConfig::getDB()->where('mer_id', $merId)->find();
        $data = $this->defaults();
        if ($row) {
            $data['need_pay'] = (int)$row['need_pay'];
            $data['voice_enable'] = (int)$row['voice_enable'];
            $data['auto_print'] = (int)$row['auto_print'];
        }
        $data['mer_id'] = $merId;
        $data['printer_bound'] = $this->isPrinterBound($merId) ? 1 : 0;
        return $data;
    }

    public function saveConfig(int $merId, array $data): array
    {
        $needPay = isset($data['need_pay']) ? ((int)$data['need_pay'] ? 1 : 0) : 1;
        $voiceEnable = isset($data['voice_enable']) ? ((int)$data['voice_enable'] ? 1 : 0) : 1;
        $autoPrint = isset($data['auto_print']) ? ((int)$data['auto_print'] ? 1 : 0) : 0;

        if ($autoPrint && !$this->isPrinterBound($merId)) {
            throw new ValidateException('请先绑定小票打印机，再开启自动打印');
        }

        $payload = [
            'mer_id' => $merId,
            'need_pay' => $needPay,
            'voice_enable' => $voiceEnable,
            'auto_print' => $autoPrint,
            'update_time' => time(),
        ];

        $exists = ScanOrderConfig::getDB()->where('mer_id', $merId)->find();
        if ($exists) {
            ScanOrderConfig::getDB()->where('mer_id', $merId)->update($payload);
        } else {
            ScanOrderConfig::getDB()->insert($payload);
        }

        return $this->getConfig($merId);
    }

    public function isPrinterBound(int $merId): bool
    {
        try {
            $repo = app()->make(StorePrinterRepository::class);
            $count = $repo->getSearch(['mer_id' => $merId, 'status' => 1])->count();
            return $count > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
