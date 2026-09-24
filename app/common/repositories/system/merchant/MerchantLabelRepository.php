<?php

namespace app\common\repositories\system\merchant;

use app\common\dao\system\merchant\MerchantLabelDao as dao;
use app\common\repositories\BaseRepository;
use app\common\repositories\system\financial\FinancialRepository;
use think\facade\Db;

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
     * 商户端标签列表：附带加入状态、保证金金额、公告内容、退款状态
     * is_margin 语义（与商户主保证金保持一致）:
     *   0=已加入无需保证金 / 1=待缴 / 10=已加入并已缴 / -10=退款审核中 / -1=已退款
     */
    public function getLabelsWithStatus(int $merId): array
    {
        $labels = $this->dao->getAll();
        if (empty($labels)) return [];

        $stores = Db::name('merchant_label_store')
            ->where('mer_id', $merId)
            ->select()->toArray();

        $storeMap = [];
        foreach ($stores as $store) {
            $storeMap[$store['label_id']] = $store;
        }

        foreach ($labels as &$label) {
            if (isset($storeMap[$label['id']])) {
                $s = $storeMap[$label['id']];
                $label['is_margin']            = (int)$s['is_margin'];
                $label['joined']               = in_array((int)$s['is_margin'], [0, 10, -10], true);
                $label['paid_deposit']         = $s['paid_deposit'];
                $label['announcement_content'] = $s['announcement_content'] ?? '';
                $label['store_id']             = (int)$s['id'];
            } else {
                $label['is_margin']            = 0;
                $label['joined']               = false;
                $label['paid_deposit']         = '0.00';
                $label['announcement_content'] = '';
                $label['store_id']             = 0;
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

        $exists = Db::name('merchant_label_store')
            ->where('label_id', $labelId)
            ->where('mer_id', $merId)
            ->find();

        if ($exists) {
            if (in_array((int)$exists['is_margin'], [0, 10], true)) {
                return ['need_deposit' => false];
            }
            // is_margin == 1: 待缴纳
            return ['need_deposit' => true, 'deposit_amount' => (float)$label->deposit_amount];
        }

        if ($label->has_deposit) {
            Db::name('merchant_label_store')->insert([
                'label_id'             => $labelId,
                'mer_id'               => $merId,
                'is_margin'            => 1,
                'announcement_content' => '',
                'paid_deposit'         => 0,
            ]);
            return ['need_deposit' => true, 'deposit_amount' => (float)$label->deposit_amount];
        } else {
            Db::name('merchant_label_store')->insert([
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

    /**
     * 商户保存标签公告
     */
    public function saveAnnouncement(int $labelId, int $merId, string $content): void
    {
        $row = Db::name('merchant_label_store')
            ->where('label_id', $labelId)
            ->where('mer_id', $merId)
            ->find();
        if (!$row) throw new \InvalidArgumentException('尚未加入该标签');
        Db::name('merchant_label_store')
            ->where('id', $row['id'])
            ->update([
                'announcement_content' => $content,
                'update_time'          => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * 校验商户是否可退某个标签的保证金；返回 online/offline 金额与该商户收款方式
     * （与 FinancialRepository::checkRefundMargin 逻辑等价，范围限定为该 label 的支付订单）
     */
    public function checkRefundLabelMargin(int $labelId, int $merId, int $adminId, bool $create = false, array $account = []): array
    {
        $label = $this->dao->get($labelId);
        if (!$label) throw new \think\exception\ValidateException('标签不存在');

        $store = Db::name('merchant_label_store')
            ->where('label_id', $labelId)
            ->where('mer_id', $merId)
            ->find();
        if (!$store) throw new \think\exception\ValidateException('尚未加入该标签');
        if (!in_array((int)$store['is_margin'], [10, -10], true) || (float)$store['paid_deposit'] <= 0) {
            throw new \think\exception\ValidateException('无可退保证金');
        }
        if ((int)$store['is_margin'] === -10) {
            // 允许再次申请：仅在 admin 已拒绝的场景（走 switchStatus -1 分支时才会保留 -10）
        }

        $merchant = app()->make(\app\common\repositories\system\merchant\MerchantRepository::class)
            ->get($merId);

        // 查找该 label 对应的已支付保证金订单
        // eb_serve_order: type=TYPE_LABEL_MARGIN(30), meal_id=label_id
        $orders = Db::name('serve_order')
            ->where('mer_id', $merId)
            ->where('status', 1)
            ->where('pay_type', '<>', \app\common\repositories\system\serve\ServeOrderRepository::PAY_TYPE_SYS)
            ->where('type', \app\common\repositories\system\serve\ServeOrderRepository::TYPE_LABEL_MARGIN)
            ->where('meal_id', $labelId)
            ->field('order_id,order_sn,pay_price,pay_type,mer_id')
            ->select()->toArray();

        $extract = (float)$store['paid_deposit'];
        $financial = [];
        $online = $offline = 0;
        $financialRepo = app()->make(FinancialRepository::class);

        foreach ($orders as $order) {
            $refundPrice = bcsub((string)$order['pay_price'], (string)$extract, 2) < 0
                ? (float)$order['pay_price']
                : (float)$extract;
            $extract = (float)bcsub((string)$extract, (string)$refundPrice, 2);
            $online = (float)bcadd((string)$refundPrice, (string)$online, 2);
            if ($create) {
                $row = $financialRepo->payOrderRefund($merchant, $adminId, $refundPrice, (object)$order);
                $row['label_id'] = $labelId;
                $row['mark'] = '标签保证金退款-' . $label['label_name'];
                $financial[] = $row;
            }
            if (bccomp((string)$extract, '0', 2) === 0) break;
        }

        if (bccomp((string)$extract, '0', 2) === 1) {
            $offline = $extract;
            if ($create) {
                $row = $financialRepo->payOrderRefund($merchant, $adminId, $extract, [], $account);
                $row['label_id'] = $labelId;
                $row['mark'] = '标签保证金退款(线下)-' . $label['label_name'];
                $financial[] = $row;
            }
        }

        $info = $offline > 0 ? $financialRepo->getType($merchant) : [];
        return compact('online', 'offline', 'financial', 'info');
    }

    /**
     * 商户提交某标签的保证金退款申请
     */
    public function refundLabelMargin(int $labelId, int $merId, int $adminId, array $account): void
    {
        $res = $this->checkRefundLabelMargin($labelId, $merId, $adminId, true, $account);
        if ($res['offline'] > 0 && (empty($account['type']) || empty($account['name']) || empty($account['code']) || empty($account['pic']))) {
            throw new \think\exception\ValidateException('请填写线下收款信息');
        }
        Db::transaction(function () use ($res, $labelId, $merId) {
            if (!empty($res['financial'])) {
                Db::name('financial')->insertAll($res['financial']);
            }
            Db::name('merchant_label_store')
                ->where('label_id', $labelId)
                ->where('mer_id', $merId)
                ->update([
                    'is_margin'   => -10,
                    'update_time' => date('Y-m-d H:i:s'),
                ]);
        });
    }

    private function filter(array $data): array
    {
        $allowed = ['label_name', 'has_deposit', 'deposit_amount', 'description', 'logo'];
        return array_intersect_key($data, array_flip($allowed));
    }
}
