<?php

namespace app\common\repositories\system\merchant;

use app\common\dao\system\merchant\MerchantLabelDao as dao;
use app\common\repositories\BaseRepository;

class MerchantLabelRepository extends BaseRepository
{
    protected $dao;

    public function __construct(dao $dao)
    {
        $this->dao = $dao;
    }

    public function lst(array $where, int $page, int $limit): array
    {
        return $this->dao->lst($where, $page, $limit);
    }

    public function create(array $data): void
    {
        $this->dao->create($this->filter($data));
    }

    public function update(int $id, array $data): void
    {
        $this->dao->update($id, $this->filter($data));
    }

    public function delete(int $id): void
    {
        $this->dao->delete($id);
    }

    public function getAll(): array
    {
        return $this->dao->getAll();
    }

    /**
     * 返回所有标签，附带当前商户的加入状态和保证金状态
     */
    public function getLabelsWithStatus(int $merId): array
    {
        $labels = $this->dao->getAll();
        if (empty($labels)) return [];

        $stores = \think\facade\Db::name('merchant_label_store')
            ->where('mer_id', $merId)
            ->select()->toArray();

        $storeMap = [];
        foreach ($stores as $store) {
            $storeMap[$store['label_id']] = $store;
        }

        foreach ($labels as &$label) {
            if (isset($storeMap[$label['id']])) {
                $s = $storeMap[$label['id']];
                $label['is_margin'] = (int)$s['is_margin'];
                $label['joined'] = ($s['is_margin'] == 0 || $s['is_margin'] == 10);
            } else {
                $label['is_margin'] = 0;
                $label['joined'] = false;
            }
        }
        return $labels;
    }

    /**
     * 商户加入标签（幂等），返回是否需要缴纳保证金
     */
    public function joinLabel(int $labelId, int $merId): array
    {
        $label = $this->dao->get($labelId);
        if (!$label) throw new \InvalidArgumentException('标签不存在');

        $exists = \think\facade\Db::name('merchant_label_store')
            ->where('label_id', $labelId)
            ->where('mer_id', $merId)
            ->find();

        if ($exists) {
            if ($exists['is_margin'] == 10 || $exists['is_margin'] == 0) {
                return ['need_deposit' => false];
            }
            // is_margin == 1: 待缴纳
            return ['need_deposit' => true, 'deposit_amount' => (float)$label->deposit_amount];
        }

        if ($label->has_deposit) {
            \think\facade\Db::name('merchant_label_store')->insert([
                'label_id'             => $labelId,
                'mer_id'               => $merId,
                'is_margin'            => 1,
                'announcement_content' => '',
                'paid_deposit'         => 0,
            ]);
            return ['need_deposit' => true, 'deposit_amount' => (float)$label->deposit_amount];
        } else {
            \think\facade\Db::name('merchant_label_store')->insert([
                'label_id'             => $labelId,
                'mer_id'               => $merId,
                'is_margin'            => 0,
                'announcement_content' => '',
                'paid_deposit'         => 0,
            ]);
            return ['need_deposit' => false];
        }
    }

    /**
     * 获取标签保证金支付二维码
     */
    public function getMarginCode(int $labelId, int $merId, int $payType = 1): array
    {
        return app()->make(\app\common\repositories\system\serve\ServeOrderRepository::class)
            ->QrCode($merId, 'labelMargin', ['label_id' => $labelId, 'pay_type' => $payType]);
    }

    private function filter(array $data): array
    {
        $allowed = [
            'label_name', 'has_deposit', 'deposit_amount',
            'description', 'show_description', 'logo', 'announcement_name',
        ];
        return array_intersect_key($data, array_flip($allowed));
    }
}
