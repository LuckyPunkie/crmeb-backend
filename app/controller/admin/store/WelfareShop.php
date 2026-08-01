<?php

namespace app\controller\admin\store;

use app\common\repositories\system\merchant\MerchantRepository;
use crmeb\basic\BaseController;

/**
 * 平台公益店铺管理
 * 菜单位置：营销 > 公益商户管理
 */
class WelfareShop extends BaseController
{
    public function merchants(MerchantRepository $repo)
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['keyword', 'is_welfare_shop', 'is_trader', 'category_id', 'type_id']);

        $isWelfare = $where['is_welfare_shop'] ?? '';
        unset($where['is_welfare_shop']);

        $query = $repo->search(array_merge($where, ['is_del' => 0]));
        if ($isWelfare !== '' && $isWelfare !== null) {
            $query->where('is_welfare_shop', (int)$isWelfare);
        }

        $count = $query->count();
        $list = $query->page($page, $limit)
            ->field('mer_id,mer_name,mer_avatar,is_trader,category_id,type_id,is_welfare_shop,mer_state,create_time')
            ->order('mer_id DESC')
            ->select();

        return app('json')->success(compact('list', 'count'));
    }

    public function enable($id, MerchantRepository $repo)
    {
        $merchant = $repo->get($id);
        if (!$merchant) {
            return app('json')->fail('商户不存在');
        }
        if ((int)$merchant['is_welfare_shop'] === 1) {
            return app('json')->fail('该商户已开启公益店铺');
        }
        $repo->update($id, ['is_welfare_shop' => 1]);
        return app('json')->success('公益店铺已开启');
    }

    public function disable($id, MerchantRepository $repo)
    {
        $merchant = $repo->get($id);
        if (!$merchant) {
            return app('json')->fail('商户不存在');
        }
        if ((int)$merchant['is_welfare_shop'] === 0) {
            return app('json')->fail('该商户已关闭公益店铺');
        }
        $repo->update($id, ['is_welfare_shop' => 0]);
        return app('json')->success('公益店铺已关闭');
    }

    public function batch(MerchantRepository $repo)
    {
        $merIds = $this->request->param('mer_ids', []);
        $status = (int)$this->request->param('is_welfare_shop', 0);
        if (empty($merIds)) {
            return app('json')->fail('请选择商户');
        }
        $merIds = array_map('intval', $merIds);
        $repo->updates($merIds, ['is_welfare_shop' => $status]);
        $action = $status === 1 ? '开启' : '关闭';
        return app('json')->success('已批量' . $action . '公益店铺', ['affected' => count($merIds)]);
    }
}
