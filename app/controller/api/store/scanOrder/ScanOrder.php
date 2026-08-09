<?php
// +----------------------------------------------------------------------
// | 用户端 - 扫码下单（商品 / 配置 / 台号信息）
// +----------------------------------------------------------------------

namespace app\controller\api\store\scanOrder;

use think\App;
use crmeb\basic\BaseController;
use app\common\model\store\StoreCategory;
use app\common\model\store\product\Product;
use app\common\model\store\product\ProductCate;
use app\common\repositories\store\scanOrder\ScanOrderConfigRepository;
use app\common\repositories\store\scanOrder\ScanOrderTableRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use think\exception\ValidateException;

class ScanOrder extends BaseController
{
    protected $tableRepository;
    protected $configRepository;

    public function __construct(
        App $app,
        ScanOrderTableRepository $tableRepository,
        ScanOrderConfigRepository $configRepository
    ) {
        parent::__construct($app);
        $this->tableRepository = $tableRepository;
        $this->configRepository = $configRepository;
    }

    /**
     * 在线点餐选桌：按商家拉取台号列表
     * GET /api/scan_order/tables?mer_id=
     */
    public function tables()
    {
        $merId = (int)$this->request->param('mer_id', 0);
        if ($merId <= 0) {
            return app('json')->fail('缺少商家参数');
        }
        $mer = app()->make(MerchantRepository::class)->get($merId);
        if (!$mer || (int)$mer['is_del'] === 1 || (int)$mer['status'] !== 1) {
            return app('json')->fail('商家不存在或已下架');
        }
        $list = $this->tableRepository->getPublicTableList($merId);
        return app('json')->success([
            'mer_id' => $merId,
            'mer_name' => (string)$mer['mer_name'],
            'list' => $list,
        ]);
    }

    /**
     * 扫码进店上下文：台号 + 商户 + 配置
     * GET /api/scan_order/context
     */
    public function context()
    {
        [$merId, $tableId, $sign] = $this->parseAccessParams();
        $mer = app()->make(MerchantRepository::class)->get($merId);
        if (!$mer || (int)$mer['is_del'] === 1) {
            return app('json')->fail('商家不存在');
        }
        $config = $this->configRepository->getConfig($merId);
        $tableLabel = '';
        if ($tableId > 0) {
            $table = $this->tableRepository->assertTableAccess($merId, $tableId, $sign, true);
            $tableLabel = (string)$table['table_label'];
        }

        return app('json')->success([
            'mer_id' => $merId,
            'mer_name' => (string)$mer['mer_name'],
            'mer_avatar' => (string)($mer['mer_avatar'] ?? ''),
            'table_id' => $tableId,
            'table_label' => $tableLabel,
            'sign' => $sign,
            'config' => [
                'need_pay' => (int)$config['need_pay'],
                'voice_enable' => (int)$config['voice_enable'],
                'auto_print' => (int)$config['auto_print'],
            ],
        ]);
    }

    /**
     * 扫码下单：商户商品分类（仅含有可点餐商品的分类）
     * GET /api/scan_order/categories
     */
    public function categories()
    {
        [$merId, $tableId, $sign] = $this->parseAccessParams();
        if ($tableId > 0) {
            $this->tableRepository->assertTableAccess($merId, $tableId, $sign, true);
        }

        $productIds = Product::getDB()
            ->where('mer_id', $merId)
            ->where('is_del', 0)
            ->where('is_show', 1)
            ->where('status', 1)
            ->where('mer_status', 1)
            ->where('product_type', 0)
            ->where('is_scan_order', 1)
            ->column('product_id');

        if (!$productIds) {
            return app('json')->success(['list' => []]);
        }

        $cateIds = ProductCate::getDB()
            ->whereIn('product_id', $productIds)
            ->column('mer_cate_id');
        $cateIds = array_values(array_unique(array_filter(array_map('intval', $cateIds))));
        if (!$cateIds) {
            return app('json')->success(['list' => []]);
        }

        $list = StoreCategory::getDB()
            ->where('mer_id', $merId)
            ->where('is_show', 1)
            ->whereIn('store_category_id', $cateIds)
            ->field('store_category_id,cate_name,pid,sort,pic')
            ->order('sort DESC, store_category_id ASC')
            ->select()
            ->toArray();

        return app('json')->success(['list' => $list]);
    }

    /**
     * 扫码下单商品列表
     * GET /api/scan_order/goods
     * 可选：mer_cate_id 商户商品分类
     */
    public function goods()
    {
        [$merId, $tableId, $sign] = $this->parseAccessParams();
        if ($tableId > 0) {
            $this->tableRepository->assertTableAccess($merId, $tableId, $sign, true);
        }
        [$page, $limit] = $this->getPage();
        $limit = min(max($limit, 1), 50);
        $keyword = trim((string)$this->request->param('keyword', ''));
        $merCateId = (int)$this->request->param('mer_cate_id', 0);

        $query = Product::getDB()
            ->where('mer_id', $merId)
            ->where('is_del', 0)
            ->where('is_show', 1)
            ->where('status', 1)
            ->where('mer_status', 1)
            ->where('product_type', 0)
            ->where('is_scan_order', 1);

        if ($keyword !== '') {
            $query->whereLike('store_name', '%' . $keyword . '%');
        }

        if ($merCateId > 0) {
            // 含当前分类及其子分类
            $childIds = StoreCategory::getDB()
                ->whereLike('path', '%/' . $merCateId . '/%')
                ->column('store_category_id');
            $ids = array_values(array_unique(array_merge([$merCateId], array_map('intval', $childIds))));
            $productIds = ProductCate::getDB()
                ->whereIn('mer_cate_id', $ids)
                ->column('product_id');
            $productIds = array_values(array_unique(array_map('intval', $productIds)));
            if (!$productIds) {
                return app('json')->success(['count' => 0, 'list' => []]);
            }
            $query->whereIn('product_id', $productIds);
        }

        $count = (clone $query)->count();
        $list = $query
            ->field('product_id,mer_id,store_name,image,price,ot_price,stock,sales,unit_name,spec_type,store_info')
            ->order('sort DESC, product_id DESC')
            ->page($page, $limit)
            ->select()
            ->toArray();

        return app('json')->success(compact('count', 'list'));
    }

    protected function parseAccessParams(): array
    {
        $merId = (int)$this->request->param('mer_id', 0);
        $tableId = (int)$this->request->param('table_id', 0);
        $sign = (string)$this->request->param('sign', '');
        if ($merId <= 0) {
            throw new ValidateException('缺少商家参数');
        }
        return [$merId, $tableId, $sign];
    }
}
