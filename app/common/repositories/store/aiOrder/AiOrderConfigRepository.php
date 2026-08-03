<?php
// +----------------------------------------------------------------------
// | AI 点餐 - 商户配置
// +----------------------------------------------------------------------

namespace app\common\repositories\store\aiOrder;

use app\common\model\store\aiOrder\AiOrderConfig;

class AiOrderConfigRepository
{
    public function defaults(): array
    {
        return [
            'enable' => 0,
            'dialect' => 'mandarin',
            'style' => 'friendly',
            'avatar' => '',
        ];
    }

    public function options(): array
    {
        return [
            'dialects' => config('ai_order.dialects') ?: [],
            'styles' => config('ai_order.styles') ?: [],
        ];
    }

    public function getConfig(int $merId): array
    {
        $data = $this->defaults();
        $row = AiOrderConfig::getDB()->where('mer_id', $merId)->find();
        if ($row) {
            $data['enable'] = (int)$row['enable'];
            $data['dialect'] = (string)$row['dialect'] ?: 'mandarin';
            $data['style'] = (string)$row['style'] ?: 'friendly';
            $data['avatar'] = (string)$row['avatar'];
        }
        $data['mer_id'] = $merId;
        $billing = app()->make(AiOrderBillingRepository::class);
        $data['ai_balance'] = $billing->getBalance($merId);
        $data['platform_open'] = $billing->platformOpen() ? 1 : 0;
        $data['min_balance'] = $billing->minBalance();
        $data['options'] = $this->options();
        return $data;
    }

    public function saveConfig(int $merId, array $data): array
    {
        $dialects = array_keys(config('ai_order.dialects') ?: []);
        $styles = array_keys(config('ai_order.styles') ?: []);

        $enable = isset($data['enable']) ? ((int)$data['enable'] ? 1 : 0) : 0;
        $dialect = (string)($data['dialect'] ?? 'mandarin');
        $style = (string)($data['style'] ?? 'friendly');
        $avatar = trim((string)($data['avatar'] ?? ''));

        if ($dialects && !in_array($dialect, $dialects, true)) {
            $dialect = 'mandarin';
        }
        if ($styles && !in_array($style, $styles, true)) {
            $style = 'friendly';
        }

        $payload = [
            'mer_id' => $merId,
            'enable' => $enable,
            'dialect' => $dialect,
            'style' => $style,
            'avatar' => mb_substr($avatar, 0, 500),
            'update_time' => time(),
        ];

        $exists = AiOrderConfig::getDB()->where('mer_id', $merId)->find();
        if ($exists) {
            AiOrderConfig::getDB()->where('mer_id', $merId)->update($payload);
        } else {
            AiOrderConfig::getDB()->insert($payload);
        }

        return $this->getConfig($merId);
    }

    public function isEnabled(int $merId): bool
    {
        $cfg = $this->getConfig($merId);
        return (int)$cfg['enable'] === 1 && (int)$cfg['platform_open'] === 1;
    }
}
