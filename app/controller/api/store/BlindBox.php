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

use app\common\repositories\store\product\ProductAttrValueRepository;
use app\common\repositories\store\product\ProductRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\user\UserBlindboxCabinetRepository;
use app\common\repositories\user\UserBlindboxRecycleRepository;
use crmeb\basic\BaseController;
use think\App;
use think\facade\Db;

class BlindBox extends BaseController
{
    protected $userInfo = null;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->userInfo = $this->request->isLogin() ? $this->request->userInfo() : null;
    }

    /**
     * 盲盒店铺列表（首页盲盒专区）
     */
    public function shopList()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['keyword', 'category_id', 'order']);

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

        if ($merId <= 0) {
            return app('json')->fail('请选择盲盒店铺');
        }

        $merchantRepository = app()->make(MerchantRepository::class);
        $merchant = $merchantRepository->get($merId);
        if (!$merchant || !$merchant['is_blindbox']) {
            return app('json')->fail('该店铺不是盲盒店铺');
        }

        $productRepository = app()->make(ProductRepository::class);
        $where = [
            'mer_id' => $merId,
            'is_del' => 0,
            'is_show' => 1,
            'status' => 1,
        ];

        $query = $productRepository->search($merId, $where);

        switch ($type) {
            case 'hot':
                $query->order('sales DESC');
                break;
            case 'new':
                $query->order('product_id DESC');
                break;
            case 'low':
                $query->order('price ASC');
                break;
            default:
                $query->order('sort DESC, product_id DESC');
                break;
        }

        $count = $query->count();
        $list = $query->page($page, $limit)
            ->field('product_id,mer_id,store_name,image,price,ot_price,sales,stock')
            ->select();

        return app('json')->success(compact('list', 'count'));
    }

    /**
     * 盲盒商品详情（含款式池+概率公示）
     */
    public function detail($id)
    {
        $id = (int)$id;
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

        $totalWeight = $attrValues->sum('probability_weight');
        $totalWeight = max($totalWeight, 1);

        $formattedAttrs = [];
        foreach ($attrValues as $attr) {
            $probabilityPercent = round($attr['probability_weight'] / $totalWeight * 100, 1);
            $rarity = app()->make(UserBlindboxCabinetRepository::class)->calcRarity($probabilityPercent);

            $formattedAttrs[] = [
                'value_id' => $attr['value_id'],
                'sku' => $attr['suk'],
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
     */
    public function result($orderId)
    {
        $orderId = (int)$orderId;
        $uid = $this->request->uid();

        $cabinetRepo = app()->make(UserBlindboxCabinetRepository::class);
        $cabinet = $cabinetRepo->getWhere(['order_id' => $orderId, 'uid' => $uid]);

        if (!$cabinet) {
            return app('json')->fail('开盒结果不存在');
        }

        $product = $cabinet->product;
        $attrValue = $cabinet->attrValue;

        $rarity = ['code' => 'C', 'name' => '普通'];
        if ($attrValue && $attrValue->probability_weight > 0) {
            $productId = $cabinet->product_id;
            $totalWeight = \app\common\model\store\product\ProductAttrValue::where('product_id', $productId)->sum('probability_weight');
            $totalWeight = max($totalWeight, 1);
            $probabilityPercent = round($attrValue->probability_weight / $totalWeight * 100, 1);
            $rarity = $cabinetRepo->calcRarity($probabilityPercent);
        }

        $data = [
            'product_name' => $product ? $product['store_name'] : '',
            'product_image' => $product ? $product['image'] : '',
            'sku_name' => $attrValue ? $attrValue['suk'] : '',
            'sku_image' => $attrValue ? $attrValue['image'] : '',
            'price' => $attrValue ? $attrValue['price'] : 0,
            'rarity' => $rarity['code'],
            'rarity_name' => $rarity['name'],
            'order_id' => $orderId,
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
}
