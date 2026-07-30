<?php

namespace app\common\repositories\circle;

use app\common\model\circle\Circle;
use app\common\model\circle\CircleAgent;
use app\common\model\system\admin\Admin;
use app\common\model\system\merchant\Merchant;
use app\common\repositories\animal_rescue\FundAuditRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\user\UserMerchantRepository;
use think\facade\Db;
use think\facade\Log;

/**
 * 商户入驻审核通过后自动开通：
 * 绑区域 → 补角色 → 创建店铺 → 绑 C 端用户 / 本店客户
 * 避免运营手工改库。
 */
class CircleAgentProvisionRepository
{
    public function afterApproved(int $circleAgentId): void
    {
        if ($circleAgentId <= 0) {
            return;
        }
        $agent = CircleAgent::getDB()->where('circle_agent_id', $circleAgentId)->find();
        if (!$agent || (int)$agent['status'] !== 1) {
            return;
        }

        try {
            $agentArr = $this->normalizeAgent($agent->toArray());
            $circleId = $this->ensureCircle($agentArr);
            $this->syncAdminRegions($circleAgentId, $circleId);
            $this->ensureDefaultRole($circleAgentId);
            $merId = $this->ensureMerchant($agentArr, $circleId);
            if ($merId > 0) {
                $typeId = (int)(Merchant::getDB()->where('mer_id', $merId)->value('type_id') ?: 0);
                app()->make(FundAuditRepository::class)->syncMerchantShelterFlag($merId, $typeId);
                $this->bindOwnerUser(
                    (int)($agentArr['uid'] ?? 0),
                    (string)($agentArr['phone'] ?? ''),
                    $merId
                );
            }
        } catch (\Throwable $e) {
            Log::error('CircleAgentProvision afterApproved failed: ' . $e->getMessage()
                . ' @' . $e->getFile() . ':' . $e->getLine()
                . ' agent=' . $circleAgentId);
        }
    }

    /**
     * 登录时补齐区域（后台无“设置区域”入口）
     */
    public function ensureAdminRegionsOnLogin($adminInfo): void
    {
        if (!$adminInfo || (int)($adminInfo['is_agent'] ?? 0) <= 0) {
            return;
        }
        $ids = $this->parseRegionIds($adminInfo['region_ids'] ?? '');
        if ($ids) {
            return;
        }
        $circleAgentId = (int)($adminInfo['circle_agent_id'] ?? 0);
        if ($circleAgentId <= 0) {
            return;
        }
        $circleIds = Circle::getDB()->where('circle_agent_id', $circleAgentId)->column('circle_id');
        if (!$circleIds) {
            $agent = CircleAgent::getDB()->where('circle_agent_id', $circleAgentId)->find();
            if ($agent) {
                $circleIds = [$this->ensureCircle($this->normalizeAgent($agent->toArray()))];
            }
        }
        $circleIds = array_values(array_filter(array_map('intval', (array)$circleIds)));
        if (!$circleIds) {
            return;
        }
        $value = ',' . implode(',', $circleIds) . ',';
        Admin::getDB()->where('admin_id', (int)$adminInfo['admin_id'])->update(['region_ids' => $value]);
        $adminInfo['region_ids'] = $value;
    }

    protected function normalizeAgent(array $agent): array
    {
        foreach (['business_name', 'name', 'phone', 'remark'] as $key) {
            if (!array_key_exists($key, $agent)) {
                continue;
            }
            $agent[$key] = $this->toScalarString($agent[$key]);
        }
        $agent['uid'] = (int)($agent['uid'] ?? 0);
        $agent['circle_agent_id'] = (int)($agent['circle_agent_id'] ?? 0);
        $agent['business_store_category'] = (int)($agent['business_store_category'] ?? 0);
        $agent['business_store_type'] = (int)($agent['business_store_type'] ?? 0);
        return $agent;
    }

    protected function toScalarString($value): string
    {
        if (is_array($value)) {
            return '';
        }
        if (is_object($value)) {
            return method_exists($value, '__toString') ? (string)$value : '';
        }
        return trim((string)$value);
    }

    protected function parseRegionIds($raw): array
    {
        if (is_array($raw)) {
            return array_values(array_filter(array_map('intval', $raw)));
        }
        return array_values(array_filter(array_map('intval', explode(',', (string)$raw))));
    }

    protected function ensureCircle(array $agent): int
    {
        $circleAgentId = (int)$agent['circle_agent_id'];
        $exist = (int)Circle::getDB()->where('circle_agent_id', $circleAgentId)->order('circle_id asc')->value('circle_id');
        if ($exist > 0) {
            return $exist;
        }
        $name = $agent['business_name'] !== '' ? $agent['business_name']
            : ($agent['name'] !== '' ? $agent['name'] : ('商户区域' . $circleAgentId));
        return (int)Circle::getDB()->insertGetId([
            'name' => $name,
            'circle_agent_id' => $circleAgentId,
            'status' => 1,
            'level' => 0,
            'pid' => 0,
            'path' => '',
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    protected function syncAdminRegions(int $circleAgentId, int $circleId): void
    {
        if ($circleId <= 0) {
            return;
        }
        // 用原始字段，避免 Admin 模型 accessor 把 region_ids 转成数组后再 (string) 报错
        $admins = Db::name('system_admin')
            ->where('circle_agent_id', $circleAgentId)
            ->where('is_del', 0)
            ->field('admin_id,region_ids')
            ->select();
        foreach ($admins as $admin) {
            $ids = $this->parseRegionIds($admin['region_ids'] ?? '');
            if (!in_array($circleId, $ids, true)) {
                $ids[] = $circleId;
            }
            $ids = array_values(array_unique(array_filter($ids)));
            Db::name('system_admin')->where('admin_id', (int)$admin['admin_id'])->update([
                'region_ids' => $ids ? (',' . implode(',', $ids) . ',') : '',
            ]);
        }
    }

    protected function ensureDefaultRole(int $circleAgentId): void
    {
        $roleId = (int)Db::name('system_role')->where('is_agent', 2)->where('status', 1)->order('role_id asc')->value('role_id');
        if ($roleId <= 0) {
            return;
        }
        Db::name('system_admin')
            ->where('circle_agent_id', $circleAgentId)
            ->where('is_del', 0)
            ->where(function ($q) {
                $q->whereNull('roles')->whereOr('roles', '')->whereOr('roles', '0');
            })
            ->update(['roles' => (string)$roleId]);
    }

    protected function ensureMerchant(array $agent, int $circleId): int
    {
        $phone = (string)($agent['phone'] ?? '');
        $uid = (int)($agent['uid'] ?? 0);
        $name = (string)($agent['business_name'] ?: $agent['name'] ?: ('商户' . ($phone ?: $agent['circle_agent_id'])));

        $exist = null;
        if ($phone !== '') {
            $exist = Merchant::getDB()->where('mer_phone', $phone)->where('is_del', 0)->find();
        }
        if (!$exist && $uid > 0) {
            $merId = (int)Db::name('user')->where('uid', $uid)->value('mer_id');
            if ($merId > 0) {
                $exist = Merchant::getDB()->where('mer_id', $merId)->where('is_del', 0)->find();
            }
        }
        if ($exist) {
            $update = [];
            if ($circleId > 0 && (int)$exist['region_id'] <= 0) {
                $update['region_id'] = $circleId;
            }
            if ($update) {
                Merchant::getDB()->where('mer_id', (int)$exist['mer_id'])->update($update);
            }
            return (int)$exist['mer_id'];
        }

        $categoryId = (int)($agent['business_store_category'] ?? 0);
        $typeId = (int)($agent['business_store_type'] ?? 0);
        if ($categoryId <= 0) {
            $categoryId = (int)Db::name('merchant_category')->order('merchant_category_id asc')->value('merchant_category_id');
        }
        if ($typeId <= 0) {
            $typeId = (int)Db::name('merchant_type')->order('mer_type_id asc')->value('mer_type_id');
        }

        $merName = $name;
        $i = 1;
        while (Merchant::getDB()->where('mer_name', $merName)->where('is_del', 0)->count()) {
            $merName = $name . '-' . $i;
            $i++;
            if ($i > 50) {
                $merName = $name . '-' . time();
                break;
            }
        }

        $account = $phone !== '' ? $phone : ('mer' . $agent['circle_agent_id']);
        if (Db::name('merchant_admin')->where('account', $account)->count()) {
            $account = $account . '_m' . $agent['circle_agent_id'];
        }
        // 与小程序审核页展示一致，店铺后台默认密码 000000
        $password = '000000';

        $merchant = app()->make(MerchantRepository::class)->createMerchant([
            'mer_name' => $merName,
            'mer_phone' => $phone,
            'mer_account' => $account,
            'mer_password' => $password,
            'category_id' => $categoryId,
            'type_id' => $typeId,
            'real_name' => (string)($agent['name'] ?? ''),
            'region_id' => $circleId,
            'status' => 1,
            'is_audit' => 1,
            'bind_uid' => $uid,
        ]);

        return (int)(is_object($merchant) ? $merchant->mer_id : ($merchant['mer_id'] ?? 0));
    }

    /**
     * 绑 C 端店主 mer_id + 本店客户关系
     */
    public function bindOwnerUser(int $uid, string $phone, int $merId): void
    {
        if ($merId <= 0) {
            return;
        }
        if ($uid <= 0 && $phone !== '') {
            $uid = (int)Db::name('user')->where('phone', $phone)->value('uid');
        }
        if ($uid > 0) {
            Db::execute('UPDATE `eb_user` SET `mer_id`=? WHERE `uid`=?', [$merId, $uid]);
            $this->ensureUserMerchantLink($uid, $merId);
            return;
        }
        if ($phone !== '') {
            Db::execute(
                'UPDATE `eb_user` SET `mer_id`=? WHERE `phone`=? AND (`mer_id` IS NULL OR `mer_id`=0)',
                [$merId, $phone]
            );
            $uid = (int)Db::name('user')->where('phone', $phone)->value('uid');
            if ($uid > 0) {
                $this->ensureUserMerchantLink($uid, $merId);
            }
        }
    }

    /**
     * 确保本店客户关联（添加客服选人依赖此表）
     */
    public function ensureUserMerchantLink(int $uid, int $merId): void
    {
        if ($uid <= 0 || $merId <= 0) {
            return;
        }
        $exists = (int)Db::name('user_merchant')->where(['uid' => $uid, 'mer_id' => $merId])->count();
        if ($exists > 0) {
            return;
        }
        app()->make(UserMerchantRepository::class)->create($uid, $merId);
    }
}
