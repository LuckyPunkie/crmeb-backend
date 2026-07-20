<?php

namespace app\controller\admin\store;

use app\common\repositories\store\product\ProductRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\user\UserBlindboxRecycleRepository;
use crmeb\basic\BaseController;

/**
 * 后台盲盒管理控制器
 * 菜单位置：营销 > 盲盒管理
 */
class BlindBox extends BaseController
{

    /**
     * 盲盒回收记录列表
     */
    public function recycleLst(UserBlindboxRecycleRepository $repo)
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['uid', 'product_id', 'reward_type', 'date_range', 'mer_id']);

        $query = $repo->search($where);

        if (!empty($where['mer_id'])) {
            $productIds = app()->make(ProductRepository::class)->search(0, ['mer_id' => $where['mer_id']])
                ->column('product_id');
            if ($productIds) {
                $query->whereIn('product_id', $productIds);
            }
        }

        $count = $query->count();
        $list = $query->page($page, $limit)
            ->order('create_time DESC')
            ->with([
                'user' => function ($query) {
                    $query->field('uid,nickname,phone');
                },
                'cabinet.attrValue' => function ($query) {
                    $query->field('value_id,suk,image');
                },
                'product' => function ($query) {
                    $query->field('product_id,store_name,mer_id');
                },
            ])
            ->select();

        return app('json')->success(compact('list', 'count'));
    }

    /**
     * 回收记录统计
     */
    public function recycleStats(UserBlindboxRecycleRepository $repo)
    {
        $where = $this->request->params(['uid', 'product_id', 'reward_type', 'date_range', 'mer_id']);

        $stats = $repo->getRecycleStats($where);

        return app('json')->success($stats);
    }

    /**
     * 盲盒店铺下拉筛选（仅盲盒店铺）
     */
    public function shopOptions(MerchantRepository $repo)
    {
        $list = $repo->search(['is_del' => 0])
            ->where('is_blindbox', 1)
            ->field('mer_id,mer_name')
            ->select();

        return app('json')->success($list);
    }

    /**
     * 盲盒商品下拉筛选
     */
    public function productOptions(ProductRepository $repo)
    {
        $merId = $this->request->param('mer_id', 0);

        $query = $repo->search(0, [
            'is_del' => 0,
            'is_show' => 1,
        ]);

        if ($merId > 0) {
            $query->where('mer_id', $merId);
        } else {
            $blindboxMerIds = app()->make(MerchantRepository::class)
                ->search(['is_del' => 0])
                ->where('is_blindbox', 1)
                ->column('mer_id');
            if ($blindboxMerIds) {
                $query->whereIn('mer_id', $blindboxMerIds);
            }
        }

        $list = $query->field('Product.product_id,Product.store_name,Product.mer_id')->select();

        return app('json')->success($list);
    }

    /**
     * 获取盲盒权限商户列表（用于"盲盒商户管理"页面）
     */
    public function blindboxMerchants(MerchantRepository $repo)
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['keyword', 'is_blindbox', 'is_trader', 'category_id', 'type_id']);

        $isBlindbox = $where['is_blindbox'] ?? '';
        unset($where['is_blindbox']);

        $query = $repo->search(array_merge($where, ['is_del' => 0]));
        if ($isBlindbox !== '' && $isBlindbox !== null) {
            $query->where('is_blindbox', (int)$isBlindbox);
        }

        $count = $query->count();
        $list = $query->page($page, $limit)
            ->field('mer_id,mer_name,mer_avatar,is_trader,category_id,type_id,is_blindbox,mer_state,create_time')
            ->order('mer_id DESC')
            ->select();

        return app('json')->success(compact('list', 'count'));
    }

    /**
     * 开启盲盒权限
     */
    public function enableBlindbox($id, MerchantRepository $repo)
    {
        $merchant = $repo->get($id);
        if (!$merchant) {
            return app('json')->fail('商户不存在');
        }

        if ($merchant['is_blindbox'] == 1) {
            return app('json')->fail('该商户已开启盲盒权限');
        }

        $repo->update($id, ['is_blindbox' => 1]);

        return app('json')->success(null, '盲盒权限已开启');
    }

    /**
     * 关闭盲盒权限
     */
    public function disableBlindbox($id, MerchantRepository $repo)
    {
        $merchant = $repo->get($id);
        if (!$merchant) {
            return app('json')->fail('商户不存在');
        }

        if ($merchant['is_blindbox'] == 0) {
            return app('json')->fail('该商户已关闭盲盒权限');
        }

        $repo->update($id, ['is_blindbox' => 0]);

        return app('json')->success(null, '盲盒权限已关闭');
    }

    /**
     * 批量设置盲盒权限
     */
    public function batchBlindbox(MerchantRepository $repo)
    {
        $merIds = $this->request->param('mer_ids', []);
        $status = (int)$this->request->param('is_blindbox', 0);

        if (empty($merIds)) {
            return app('json')->fail('请选择商户');
        }

        $merIds = array_map('intval', $merIds);
        $repo->updates($merIds, ['is_blindbox' => $status]);

        $action = $status === 1 ? '开启' : '关闭';
        return app('json')->success(['affected' => count($merIds)], '已批量' . $action . '盲盒权限');
    }
}
