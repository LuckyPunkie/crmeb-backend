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
     * @param bool $onlyEnabled true=仅返回已开启（C 端）；false=后台可读关闭态
     */
    public function getByMerBranch(int $merId, int $branchId = 0, bool $onlyEnabled = true): ?array
    {
        $query = MerchantWeworkGroup::getDB()
            ->where('mer_id', $merId)
            ->where('branch_id', $branchId);

        if ($onlyEnabled) {
            $query->where('status', 1);
        }

        $row = $query->find();

        return $row ? $row->toArray() : null;
    }

    /**
     * 保存（存在则更新，不存在则创建）
     */
    public function saveByMerBranch(int $merId, int $branchId, array $data): void
    {
        $payload = [
            'corp_id' => '',
            'group_name' => (string)($data['group_name'] ?? ''),
            'group_num' => 0,
            'group_last_msg' => (string)($data['group_last_msg'] ?? ''),
            'qrcode_url' => (string)($data['qrcode_url'] ?? ''),
            'group_link' => '',
            'status' => isset($data['status']) ? ((int)$data['status'] ? 1 : 0) : 0,
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
        $status = (int)($row['status'] ?? 0);
        $qrcode = (string)($row['qrcode_url'] ?? '');
        $has = $row && $status === 1 && $qrcode !== '';

        return [
            'has_group' => (bool)$has,
            'status' => $status,
            'branch_id' => (int)($row['branch_id'] ?? 0),
            'group_name' => (string)($row['group_name'] ?? ''),
            'group_last_msg' => (string)($row['group_last_msg'] ?? ''),
            'qrcode_url' => $qrcode,
        ];
    }
}
