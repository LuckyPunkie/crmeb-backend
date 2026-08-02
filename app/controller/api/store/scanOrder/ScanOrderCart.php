<?php
// +----------------------------------------------------------------------
// | 用户端 - 扫码下单本店购物车（cart_scene=scan_order）
// | 支持登录用户 / 游客(tourist_unique_key)，登录后可合并
// +----------------------------------------------------------------------

namespace app\controller\api\store\scanOrder;

use think\App;
use crmeb\basic\BaseController;
use app\common\model\store\order\StoreCart;
use app\common\model\store\product\Product;
use app\common\repositories\store\order\StoreCartRepository;
use app\common\repositories\store\product\ProductAttrValueRepository;
use app\common\repositories\store\scanOrder\ScanOrderTableRepository;
use think\exception\ValidateException;

class ScanOrderCart extends BaseController
{
    const SCENE = 'scan_order';

    protected $cartRepository;
    protected $tableRepository;

    public function __construct(
        App $app,
        StoreCartRepository $cartRepository,
        ScanOrderTableRepository $tableRepository
    ) {
        parent::__construct($app);
        $this->cartRepository = $cartRepository;
        $this->tableRepository = $tableRepository;
    }

    public function lst()
    {
        [$uid, $tourist, $merId] = $this->resolveOwner(true);
        // 已登录且带了游客标识：先合并，避免确认页读不到游客车
        if ($uid > 0 && $tourist !== '') {
            $this->mergeTouristCart($uid, $tourist, $merId);
        }
        $query = $this->baseQuery($uid, $tourist, $merId)
            ->with([
                'product' => function ($query) {
                    $query->field('product_id,image,store_name,is_show,status,is_del,unit_name,price,mer_status,stock,spec_type');
                },
                'productAttr' => function ($query) {
                    $query->field('product_id,stock,price,unique,sku,image');
                },
            ])
            ->order('cart_id DESC');
        $list = $query->select()->toArray();

        $totalNum = 0;
        $totalPrice = 0;
        foreach ($list as &$item) {
            $num = (int)$item['cart_num'];
            $price = (float)($item['productAttr']['price'] ?? $item['product']['price'] ?? 0);
            $item['price'] = $price;
            $item['line_price'] = round($price * $num, 2);
            $totalNum += $num;
            $totalPrice += $item['line_price'];
        }
        unset($item);

        return app('json')->success([
            'list' => $list,
            'total_num' => $totalNum,
            'total_price' => round($totalPrice, 2),
        ]);
    }

    public function count()
    {
        [$uid, $tourist, $merId] = $this->resolveOwner(true);
        if ($uid > 0 && $tourist !== '') {
            $this->mergeTouristCart($uid, $tourist, $merId);
        }
        $count = (int)$this->baseQuery($uid, $tourist, $merId)->sum('cart_num');
        return app('json')->success(['count' => $count]);
    }

    public function create()
    {
        $data = $this->request->params([
            'mer_id', 'table_id', 'sign',
            'product_id', 'product_attr_unique',
            ['cart_num', 1],
            ['tourist_unique_key', ''],
        ]);
        $merId = (int)$data['mer_id'];
        $tableId = (int)$data['table_id'];
        $sign = (string)$data['sign'];
        $productId = (int)$data['product_id'];
        $unique = (string)$data['product_attr_unique'];
        $cartNum = max(1, (int)$data['cart_num']);

        $this->tableRepository->assertTableAccess($merId, $tableId, $sign, true);
        [$uid, $tourist] = $this->resolveOwnerIds((string)$data['tourist_unique_key']);

        $product = Product::getDB()
            ->where('product_id', $productId)
            ->where('mer_id', $merId)
            ->where('is_del', 0)
            ->where('is_show', 1)
            ->where('status', 1)
            ->where('is_scan_order', 1)
            ->find();
        if (!$product) {
            return app('json')->fail('商品不可用或不在扫码下单渠道');
        }

        $sku = app()->make(ProductAttrValueRepository::class)->getOptionByUnique($unique);
        if (!$sku || (int)$sku['product_id'] !== $productId) {
            return app('json')->fail('SKU不存在');
        }
        if ((int)$sku['stock'] < $cartNum) {
            return app('json')->fail('库存不足');
        }

        $existQ = StoreCart::getDB()->where([
            'mer_id' => $merId,
            'cart_scene' => self::SCENE,
            'product_attr_unique' => $unique,
            'is_del' => 0,
            'is_new' => 0,
            'is_pay' => 0,
        ]);
        if ($uid > 0) {
            $existQ->where('uid', $uid);
        } else {
            $existQ->where('uid', 0)->where('tourist_unique_key', $tourist);
        }
        $exist = $existQ->find();

        if ($exist) {
            $newNum = (int)$exist['cart_num'] + $cartNum;
            if ((int)$sku['stock'] < $newNum) {
                return app('json')->fail('库存不足');
            }
            $this->cartRepository->update((int)$exist['cart_id'], ['cart_num' => $newNum]);
            $cartId = (int)$exist['cart_id'];
        } else {
            $cart = $this->cartRepository->create([
                'uid' => $uid,
                'mer_id' => $merId,
                'cart_scene' => self::SCENE,
                'tourist_unique_key' => $uid > 0 ? '' : $tourist,
                'product_id' => $productId,
                'product_attr_unique' => $unique,
                'cart_num' => $cartNum,
                'product_type' => 0,
                'is_new' => 0,
                'is_pay' => 0,
                'is_del' => 0,
                'is_fail' => 0,
                'source' => 0,
                'source_id' => $productId,
            ]);
            $cartId = (int)$cart['cart_id'];
        }

        return app('json')->success(['cart_id' => $cartId]);
    }

    public function change($id)
    {
        $cartNum = (int)$this->request->param('cart_num', 1);
        if ($cartNum < 1) {
            return app('json')->fail('数量不能小于1');
        }
        $cart = $this->getOwnCart((int)$id);
        $sku = app()->make(ProductAttrValueRepository::class)->getOptionByUnique($cart['product_attr_unique']);
        if (!$sku) {
            return app('json')->fail('SKU不存在');
        }
        if ((int)$sku['stock'] < $cartNum) {
            return app('json')->fail('库存不足');
        }
        $this->cartRepository->update((int)$id, ['cart_num' => $cartNum]);
        return app('json')->success('修改成功');
    }

    public function delete()
    {
        $ids = $this->request->param('ids', []);
        if (is_string($ids)) {
            $ids = array_filter(explode(',', $ids));
        }
        if (!is_array($ids) || !$ids) {
            return app('json')->fail('请选择商品');
        }
        [$uid, $tourist] = $this->resolveOwnerIds((string)$this->request->param('tourist_unique_key', ''));
        $q = StoreCart::getDB()
            ->where('cart_scene', self::SCENE)
            ->whereIn('cart_id', array_map('intval', $ids));
        if ($uid > 0) {
            $q->where('uid', $uid);
        } else {
            $q->where('uid', 0)->where('tourist_unique_key', $tourist);
        }
        $q->update(['is_del' => 1]);
        return app('json')->success('删除成功');
    }

    /**
     * 登录后合并游客本店购物车
     * POST /api/scan_order/cart/merge
     */
    public function merge()
    {
        $uid = (int)$this->request->uid();
        if ($uid <= 0) {
            return app('json')->fail('请先登录');
        }
        $tourist = trim((string)$this->request->param('tourist_unique_key', ''));
        $merId = (int)$this->request->param('mer_id', 0);
        if ($tourist === '') {
            return app('json')->success(['merged' => 0]);
        }
        $merged = $this->mergeTouristCart($uid, $tourist, $merId);
        return app('json')->success(['merged' => $merged]);
    }

    /**
     * 把游客本店购物车合并到登录用户（列表/提交前可复用）
     */
    public function mergeTouristCart(int $uid, string $tourist, int $merId = 0): int
    {
        if ($uid <= 0 || $tourist === '') {
            return 0;
        }
        $q = StoreCart::getDB()
            ->where('cart_scene', self::SCENE)
            ->where('uid', 0)
            ->where('tourist_unique_key', $tourist)
            ->where('is_del', 0)
            ->where('is_pay', 0);
        if ($merId > 0) {
            $q->where('mer_id', $merId);
        }
        $rows = $q->select();
        $merged = 0;
        foreach ($rows as $row) {
            $exist = StoreCart::getDB()->where([
                'uid' => $uid,
                'mer_id' => $row['mer_id'],
                'cart_scene' => self::SCENE,
                'product_attr_unique' => $row['product_attr_unique'],
                'is_del' => 0,
                'is_new' => 0,
                'is_pay' => 0,
            ])->find();
            if ($exist) {
                $this->cartRepository->update((int)$exist['cart_id'], [
                    'cart_num' => (int)$exist['cart_num'] + (int)$row['cart_num'],
                ]);
                $this->cartRepository->update((int)$row['cart_id'], ['is_del' => 1]);
            } else {
                $this->cartRepository->update((int)$row['cart_id'], [
                    'uid' => $uid,
                    'tourist_unique_key' => '',
                ]);
            }
            $merged++;
        }
        return $merged;
    }

    protected function resolveOwner(bool $needMerId): array
    {
        $merId = (int)$this->request->param('mer_id', 0);
        if ($needMerId && $merId <= 0) {
            throw new ValidateException('缺少商家参数');
        }
        [$uid, $tourist] = $this->resolveOwnerIds((string)$this->request->param('tourist_unique_key', ''));
        return [$uid, $tourist, $merId];
    }

    protected function resolveOwnerIds(string $touristParam = ''): array
    {
        $uid = 0;
        try {
            $uid = (int)$this->request->uid();
        } catch (\Throwable $e) {
            $uid = 0;
        }
        $tourist = trim($touristParam !== '' ? $touristParam : (string)$this->request->param('tourist_unique_key', ''));
        if ($uid <= 0) {
            if ($tourist === '') {
                throw new ValidateException('请先登录或提供设备标识');
            }
            return [0, $tourist];
        }
        // 已登录也保留 tourist，便于合并/联合查询
        return [$uid, $tourist];
    }

    protected function baseQuery(int $uid, string $tourist, int $merId)
    {
        $q = StoreCart::getDB()->where([
            'mer_id' => $merId,
            'cart_scene' => self::SCENE,
            'is_del' => 0,
            'is_new' => 0,
            'is_pay' => 0,
        ]);
        if ($uid > 0 && $tourist !== '') {
            // 登录用户：本人车 + 同设备游客车（合并前兜底）
            $q->where(function ($query) use ($uid, $tourist) {
                $query->where('uid', $uid)
                    ->whereOr(function ($q2) use ($tourist) {
                        $q2->where('uid', 0)->where('tourist_unique_key', $tourist);
                    });
            });
        } elseif ($uid > 0) {
            $q->where('uid', $uid);
        } else {
            $q->where('uid', 0)->where('tourist_unique_key', $tourist);
        }
        return $q;
    }

    protected function getOwnCart(int $cartId): array
    {
        [$uid, $tourist] = $this->resolveOwnerIds((string)$this->request->param('tourist_unique_key', ''));
        $q = StoreCart::getDB()
            ->where('cart_id', $cartId)
            ->where('cart_scene', self::SCENE)
            ->where('is_del', 0)
            ->where('is_pay', 0);
        if ($uid > 0) {
            $q->where('uid', $uid);
        } else {
            $q->where('uid', 0)->where('tourist_unique_key', $tourist);
        }
        $cart = $q->find();
        if (!$cart) {
            throw new ValidateException('购物车信息不存在');
        }
        return $cart->toArray();
    }
}
