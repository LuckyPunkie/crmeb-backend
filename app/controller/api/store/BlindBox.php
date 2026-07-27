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

namespace app\controller\api\store;

use app\common\repositories\store\BlindBoxShareRepository;
use app\common\repositories\store\product\ProductAttrValueRepository;
use app\common\repositories\store\product\ProductRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\user\UserAddressRepository;
use app\common\repositories\user\UserBlindboxCabinetRepository;
use app\common\repositories\user\UserBlindboxRecycleRepository;
use app\common\model\store\order\StoreOrder;
use app\common\model\store\order\StoreGroupOrder;
use crmeb\basic\BaseController;
use think\App;
use think\exception\ValidateException;
use think\facade\Db;

class BlindBox extends BaseController
{
    protected $userInfo = null;

    public function __construct(App $app)
    {
        parent::__construct($app);
        try {
            $this->userInfo = $this->request->isLogin() ? $this->request->userInfo() : null;
        } catch (\Throwable $e) {
            $this->userInfo = null;
        }
    }

    /**
     * 普通店铺主页盲盒入口
     * GET /api/store/blindbox/entry?mer_id=
     */
    public function entry(BlindBoxShareRepository $shareRepo)
    {
        $merId = (int)$this->request->param('mer_id', 0);
        try {
            $data = $shareRepo->entryInfo($merId);
            if ($this->userInfo) {
                $data['bound_share_mer_id'] = $shareRepo->getBound((int)$this->userInfo->uid);
            }
            return app('json')->success($data);
        } catch (ValidateException $e) {
            return app('json')->fail($e->getMessage());
        }
    }

    /**
     * 绑定盲盒分享来源店铺（从普通店点进盲盒时调用）
     * POST /api/store/blindbox/bind_share  share_mer_id
     */
    public function bindShare(BlindBoxShareRepository $shareRepo)
    {
        $shareMerId = (int)$this->request->param('share_mer_id', 0);
        try {
            $bound = $shareRepo->bind((int)$this->request->uid(), $shareMerId);
            return app('json')->success('绑定成功', [
                'share_mer_id' => $bound,
                'ttl' => BlindBoxShareRepository::BIND_TTL,
            ]);
        } catch (ValidateException $e) {
            return app('json')->fail($e->getMessage());
        }
    }

    /**
     * 盲盒店铺列表（首页盲盒专区）
     */
    public function shopList()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['keyword', 'category_id', 'order']);
        $this->tryBindShareFromRequest();

        $query = app()->make(MerchantRepository::class)->search(array_merge($where, [
            'is_del' => 0,
            'status' => 1,
        ]))->where('is_blindbox', 1);

        $count = $query->count();
        $list = $query->page($page, $limit)
            ->field('mer_id,mer_name,mer_avatar,mer_banner,mer_state,is_trader,category_id')
            ->order('mer_id DESC')
            ->select();

        foreach ($list as $item) {
            $minPrice = Db::name('store_product')
                ->where('mer_id', $item['mer_id'])
                ->where('is_del', 0)
                ->where('is_show', 1)
                ->where('status', 1)
                ->min('price');
            $item['min_blindbox_price'] = $minPrice ?: 0;
        }

        return app('json')->success(compact('list', 'count'));
    }

    /**
     * 盲盒店铺商品列表
     */
    public function productList()
    {
        [$page, $limit] = $this->getPage();
        $merId = (int)$this->request->param('mer_id');
        $type = $this->request->param('type', 'all');
        $this->tryBindShareFromRequest();

        if ($merId <= 0) {
            return app('json')->fail('请选择盲盒店铺');
        }

        $merchantRepository = app()->make(MerchantRepository::class);
        $merchant = $merchantRepository->get($merId);
        if (!$merchant || !$merchant['is_blindbox']) {
            return app('json')->fail('该店铺不是盲盒店铺');
        }

        $productRepository = app()->make(ProductRepository::class);
        $query = $productRepository->getSearch([])
            ->alias('Product')
            ->where('Product.mer_id', $merId)
            ->where('Product.is_del', 0)
            ->where('Product.is_show', 1)
            ->where('Product.status', 1)
            ->where('Product.is_used', 1);

        switch ($type) {
            case 'hot':
                $query->order('Product.sales DESC');
                break;
            case 'new':
                $query->order('Product.product_id DESC');
                break;
            case 'low':
                $query->order('Product.price ASC');
                break;
            default:
                $query->order('Product.sort DESC, Product.product_id DESC');
                break;
        }

        $count = $query->count();
        $list = $query->page($page, $limit)
            ->field('Product.product_id,Product.mer_id,Product.store_name,Product.image,Product.price,Product.ot_price,Product.sales,Product.stock')
            ->select();

        return app('json')->success(compact('list', 'count'));
    }

    /**
     * 盲盒商品详情（含款式池+概率公示）
     */
    public function detail($id)
    {
        $id = (int)$id;
        $this->tryBindShareFromRequest();
        $productRepository = app()->make(ProductRepository::class);
        $merchantRepository = app()->make(MerchantRepository::class);

        $product = $productRepository->getWhere(['product_id' => $id]);
        if (!$product) {
            return app('json')->fail('商品不存在');
        }

        $merchant = $merchantRepository->get($product['mer_id']);
        if (!$merchant || !$merchant['is_blindbox']) {
            return app('json')->fail('该商品不是盲盒商品');
        }

        $blindboxConfig = json_decode($product['extension_one'], true) ?: [];

        $attrValueRepository = app()->make(ProductAttrValueRepository::class);
        $attrValues = $attrValueRepository->search(['product_id' => $id])->select();
        $attrList = $attrValues ? $attrValues->toArray() : [];

        $totalWeight = 0;
        foreach ($attrList as $attr) {
            $totalWeight += (int)($attr['probability_weight'] ?? 0);
        }
        $totalWeight = max($totalWeight, 1);

        $formattedAttrs = [];
        foreach ($attrList as $attr) {
            $probabilityPercent = round(($attr['probability_weight'] ?? 0) / $totalWeight * 100, 1);
            $rarity = app()->make(UserBlindboxCabinetRepository::class)->calcRarity($probabilityPercent);

            $formattedAttrs[] = [
                'value_id' => $attr['value_id'],
                'sku' => $attr['sku'] ?? ($attr['suk'] ?? ''),
                'detail' => $attr['detail'],
                'unique' => $attr['unique'],
                'image' => $attr['image'],
                'price' => $attr['price'],
                'stock' => $attr['stock'],
                'probability_weight' => $attr['probability_weight'],
                'probability' => $probabilityPercent . '%',
                'rarity' => $rarity['code'],
                'rarity_name' => $rarity['name'],
            ];
        }

        $data = [
            'product_id' => $product['product_id'],
            'store_name' => $product['store_name'],
            'image' => $product['image'],
            'slider_image' => $product['slider_image'],
            'price' => $product['price'],
            'ot_price' => $product['ot_price'],
            'sales' => $product['sales'],
            'stock' => $product['stock'],
            'mer_id' => $product['mer_id'],
            'description' => $product['description'],
            'reply_count' => $product['reply_count'] ?? 0,
            'is_blindbox' => true,
            'merchant' => [
                'mer_id' => $merchant['mer_id'],
                'mer_name' => $merchant['mer_name'],
                'is_blindbox' => (bool)$merchant['is_blindbox'],
                'mer_avatar' => $merchant['mer_avatar'],
            ],
            'blindbox_config' => [
                'pool_expose_count' => intval($blindboxConfig['pool_expose_count'] ?? 4),
                'once_max_count' => intval($blindboxConfig['once_max_count'] ?? 5),
                'limit_num' => intval($blindboxConfig['limit_num'] ?? 0),
                'refund_switch' => intval($blindboxConfig['refund_switch'] ?? 0),
                'rules' => $blindboxConfig['rules'] ?? '',
            ],
            'attr_values' => $formattedAttrs,
            'total_weight' => $totalWeight,
        ];

        if ($this->userInfo) {
            $uid = $this->userInfo->uid;
            $cabinetRepo = app()->make(UserBlindboxCabinetRepository::class);
            $stats = $cabinetRepo->getCabinetStats($uid, $id);

            $data['user_buy_count'] = $stats['totalDraws'];
            $data['user_cabinet'] = [
                'collected_count' => $stats['collectedCount'],
                'total_count' => $stats['totalCount'],
                'rate' => $stats['rate'] . '%',
            ];
        }

        return app('json')->success($data);
    }

    /**
     * 开盒结果（支付成功后查看抽中的款式）
     * $orderId 可为子订单 order_id，或支付页传来的 group_order_id
     *
     * 注意：盒柜按 uid+product+sku 合并数量，重复抽中同款时不会新建 order_id 行，
     * 因此开盒结果必须以订单 cart_info 为准，不能只靠盒柜 order_id 反查。
     */
    public function result($orderId)
    {
        $orderId = (int)$orderId;
        $uid = $this->request->uid();
        $orderRepo = app()->make(\app\common\repositories\store\order\StoreOrderRepository::class);
        $cabinetRepo = app()->make(UserBlindboxCabinetRepository::class);

        // 1) 先定位订单（兼容 group_order_id / order_id）
        $order = $orderRepo->search([
            'order_id' => $orderId,
            'uid' => $uid,
        ])->with(['orderProduct'])->find();
        if (!$order) {
            $order = $orderRepo->search([
                'group_order_id' => $orderId,
                'uid' => $uid,
            ])->with(['orderProduct'])->find();
        }
        if (!$order || empty($order['is_blindbox_order'])) {
            return app('json')->fail('开盒结果不存在');
        }
        if ((int)$order['paid'] !== 1) {
            return app('json')->fail('订单未支付，暂无开盒结果');
        }

        $orderId = (int)$order['order_id'];
        $orderProduct = $order->orderProduct[0] ?? null;
        if (!$orderProduct) {
            return app('json')->fail('开盒结果不存在');
        }

        $cartInfo = $orderProduct['cart_info'] ?? [];
        if (is_string($cartInfo)) {
            $cartInfo = json_decode($cartInfo, true) ?: [];
        }
        $productInfo = $cartInfo['product'] ?? [];
        $attr = $cartInfo['productAttr'] ?? [];
        if (!$attr) {
            return app('json')->fail('开盒结果不存在');
        }

        $productId = (int)($orderProduct['product_id'] ?? ($productInfo['product_id'] ?? 0));
        $skuName = (string)($attr['sku'] ?? ($attr['suk'] ?? ''));
        $skuImage = (string)($attr['image'] ?? '');
        $price = $attr['price'] ?? 0;
        $weight = (float)($attr['probability_weight'] ?? 0);

        // 若 cart_info 缺权重，回表补齐
        if ($weight <= 0) {
            $valueId = (int)($attr['value_id'] ?? 0);
            $unique = (string)($attr['unique'] ?? ($orderProduct['product_sku'] ?? ''));
            $attrRow = null;
            if ($valueId > 0) {
                $attrRow = \app\common\model\store\product\ProductAttrValue::where('value_id', $valueId)->find();
            } elseif ($unique !== '') {
                $attrRow = \app\common\model\store\product\ProductAttrValue::where('unique', $unique)->find();
            }
            if ($attrRow) {
                $weight = (float)$attrRow['probability_weight'];
                if ($skuName === '') {
                    $skuName = (string)($attrRow['sku'] ?? ($attrRow['suk'] ?? ''));
                }
                if ($skuImage === '') {
                    $skuImage = (string)$attrRow['image'];
                }
                if (!$price) {
                    $price = $attrRow['price'];
                }
            }
        }

        $rarity = ['code' => 'C', 'name' => '普通'];
        if ($productId > 0 && $weight > 0) {
            $totalWeight = \app\common\model\store\product\ProductAttrValue::where('product_id', $productId)->sum('probability_weight');
            $totalWeight = max((float)$totalWeight, 1);
            $probabilityPercent = round($weight / $totalWeight * 100, 1);
            $rarity = $cabinetRepo->calcRarity($probabilityPercent);
        }

        $data = [
            'product_name' => (string)($productInfo['store_name'] ?? ''),
            'product_image' => (string)($productInfo['image'] ?? ''),
            'sku_name' => $skuName,
            'sku_image' => $skuImage ?: (string)($productInfo['image'] ?? ''),
            'price' => $price,
            'rarity' => $rarity['code'],
            'rarity_name' => $rarity['name'],
            'order_id' => $orderId,
            'product_id' => $productId,
        ];

        return app('json')->success($data);
    }

    /**
     * 我的盒柜列表
     */
    public function cabinet(UserBlindboxCabinetRepository $repo)
    {
        $uid = $this->request->uid();
        $productId = (int)$this->request->param('product_id', 0);
        $type = $this->request->param('type', 'all');
        [$page, $limit] = $this->getPage();

        return app('json')->success($repo->getCabinetList($uid, $productId, $type, $page, $limit));
    }

    /**
     * 盒柜统计
     */
    public function cabinetStats(UserBlindboxCabinetRepository $repo)
    {
        $uid = $this->request->uid();
        $productId = (int)$this->request->param('product_id', 0);

        $stats = $repo->getCabinetStats($uid, $productId);

        if ($productId > 0) {
            $product = app()->make(ProductRepository::class)->get($productId);
            if ($product && $product->mer_id) {
                $merchant = app()->make(MerchantRepository::class)->get($product->mer_id);
                if ($merchant) {
                    $stats['blindbox_recycle_type'] = (int)($merchant['blindbox_recycle_type'] ?? 1);
                    $stats['blindbox_recycle_point'] = (int)($merchant['blindbox_recycle_point'] ?? 100);
                }
            }
        }

        return app('json')->success($stats);
    }

    /**
     * 回收款式
     */
    public function recycle(UserBlindboxRecycleRepository $repo)
    {
        $uid = $this->request->uid();
        [$cabinetId, $quantity, $rewardType] = $this->request->getMore([
            ['cabinet_id', 0],
            ['quantity', 1],
            ['reward_type', 1],
        ], true);

        if ($cabinetId <= 0) {
            return app('json')->fail('请选择要回收的款式');
        }
        if ($quantity <= 0) {
            return app('json')->fail('回收数量必须大于0');
        }

        try {
            $result = $repo->recycle($uid, (int)$cabinetId, (int)$quantity, (int)$rewardType);
            return app('json')->success($result, '回收成功');
        } catch (\think\exception\ValidateException $e) {
            return app('json')->fail($e->getMessage());
        } catch (\Exception $e) {
            \think\facade\Log::error('盲盒回收失败: ' . $e->getMessage());
            return app('json')->fail('回收服务暂不可用，请稍后再试');
        }
    }

    /**
     * 回收记录
     */
    public function recycleRecords(UserBlindboxRecycleRepository $repo)
    {
        $uid = $this->request->uid();
        [$page, $limit] = $this->getPage();

        return app('json')->success($repo->getUserRecords($uid, $page, $limit));
    }

    /**
     * 盲盒申请发货：用户填写收货地址后调用，将地址写入订单
     * POST /api/store/blindbox/apply_ship  order_id address_id
     */
    public function applyShip(UserAddressRepository $addressRepo)
    {
        $orderId   = (int)$this->request->param('order_id');
        $addressId = (int)$this->request->param('address_id');
        $uid       = $this->request->uid();

        if ($orderId <= 0 || $addressId <= 0) {
            return app('json')->fail('参数错误');
        }

        $order = StoreOrder::where('order_id', $orderId)
            ->where('uid', $uid)
            ->where('paid', 1)
            ->where('is_blindbox_order', 1)
            ->where('status', 0)
            ->find();

        if (!$order) {
            return app('json')->fail('订单不存在或状态不符');
        }

        if (!empty($order->user_address)) {
            return app('json')->fail('该订单已设置收货地址');
        }

        $address = $addressRepo->getWhere(['address_id' => $addressId, 'uid' => $uid]);
        if (!$address) {
            return app('json')->fail('收货地址不存在');
        }

        $userAddress = ($address['province'] ?? '') . ($address['city'] ?? '') . ($address['district'] ?? '') . ($address['street'] ?? '') . ($address['detail'] ?? '');

        Db::transaction(function () use ($order, $address, $userAddress) {
            $order->real_name   = $address['real_name'];
            $order->user_phone  = $address['phone'];
            $order->user_address = $userAddress;
            $order->save();

            StoreGroupOrder::where('group_order_id', $order->group_order_id)->update([
                'real_name'    => $address['real_name'],
                'user_phone'   => $address['phone'],
                'user_address' => $userAddress,
            ]);
        });

        return app('json')->success('申请发货成功');
    }

    /**
     * 请求带 share_mer_id 且已登录时自动绑定归因
     */
    protected function tryBindShareFromRequest(): void
    {
        if (!$this->userInfo) {
            return;
        }
        $shareMerId = (int)$this->request->param('share_mer_id', 0);
        if ($shareMerId <= 0) {
            return;
        }
        try {
            app()->make(BlindBoxShareRepository::class)->bind((int)$this->userInfo->uid, $shareMerId);
        } catch (\Throwable $e) {
            // 入口浏览不阻断主流程
        }
    }
}
