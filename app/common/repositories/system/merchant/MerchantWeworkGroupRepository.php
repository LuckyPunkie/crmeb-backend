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

namespace app\common\repositories\system\merchant;

use app\common\dao\system\merchant\MerchantWeworkGroupDao;
use app\common\model\system\merchant\MerchantWeworkGroup;
use app\common\repositories\BaseRepository;

class MerchantWeworkGroupRepository extends BaseRepository
{
    public function __construct(MerchantWeworkGroupDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 按商户+分店读取配置（branch_id=0 为总店）
     */
    public function getByMerBranch(int $merId, int $branchId = 0): ?array
    {
        $row = MerchantWeworkGroup::getDB()
            ->where('mer_id', $merId)
            ->where('branch_id', $branchId)
            ->where('status', 1)
            ->find();

        return $row ? $row->toArray() : null;
    }

    /**
     * 保存（存在则更新，不存在则创建）
     */
    public function saveByMerBranch(int $merId, int $branchId, array $data): void
    {
        $payload = [
            'corp_id' => (string)($data['corp_id'] ?? ''),
            'group_name' => (string)($data['group_name'] ?? ''),
            'group_num' => max(0, (int)($data['group_num'] ?? 0)),
            'group_last_msg' => (string)($data['group_last_msg'] ?? ''),
            'qrcode_url' => (string)($data['qrcode_url'] ?? ''),
            'group_link' => (string)($data['group_link'] ?? ''),
            'status' => isset($data['status']) ? (int)$data['status'] : 1,
            'update_time' => date('Y-m-d H:i:s'),
        ];

        $existing = MerchantWeworkGroup::getDB()
            ->where('mer_id', $merId)
            ->where('branch_id', $branchId)
            ->find();

        if ($existing) {
            $this->dao->update($existing['id'], $payload);
            return;
        }

        $payload['mer_id'] = $merId;
        $payload['branch_id'] = $branchId;
        $payload['create_time'] = date('Y-m-d H:i:s');
        $this->dao->create($payload);
    }

    /**
     * C 端 / 后台统一返回结构
     */
    public function toApiPayload(?array $row): array
    {
        $has = $row && (string)($row['qrcode_url'] ?? '') !== '';

        return [
            'has_group' => (bool)$has,
            'branch_id' => (int)($row['branch_id'] ?? 0),
            'corp_id' => (string)($row['corp_id'] ?? ''),
            'group_name' => (string)($row['group_name'] ?? ''),
            'group_num' => (int)($row['group_num'] ?? 0),
            'group_last_msg' => (string)($row['group_last_msg'] ?? ''),
            'qrcode_url' => (string)($row['qrcode_url'] ?? ''),
            'group_link' => (string)($row['group_link'] ?? ''),
        ];
    }
}
