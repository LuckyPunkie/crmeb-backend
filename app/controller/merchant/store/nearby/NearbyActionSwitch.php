<?php

namespace app\controller\merchant\store\nearby;

use app\common\repositories\system\merchant\MerchantFeatureSwitchRepository;
use crmeb\basic\BaseController;
use think\App;

/**
 * 店铺功能开关 商家开关
 *  - action：店铺详情页「线上下单」「买单」按钮
 *  - blindbox：买单支付成功页「店老板请你免费开盲盒」入口
 *  - welfare：买单页公益商品 / 购物享免单
 */
class NearbyActionSwitch extends BaseController
{
    protected $repository;

    public function __construct(App $app, MerchantFeatureSwitchRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    public function detail()
    {
        return $this->doDetail(MerchantFeatureSwitchRepository::GROUP_ACTION);
    }

    public function save()
    {
        return $this->doSave(MerchantFeatureSwitchRepository::GROUP_ACTION);
    }

    public function blindboxDetail()
    {
        return $this->doDetail(MerchantFeatureSwitchRepository::GROUP_BLINDBOX);
    }

    public function blindboxSave()
    {
        return $this->doSave(MerchantFeatureSwitchRepository::GROUP_BLINDBOX);
    }

    public function welfareDetail()
    {
        return $this->doDetail(MerchantFeatureSwitchRepository::GROUP_WELFARE);
    }

    public function welfareSave()
    {
        return $this->doSave(MerchantFeatureSwitchRepository::GROUP_WELFARE);
    }

    protected function doDetail(string $group)
    {
        return app('json')->success($this->repository->getMerSwitch((int)$this->request->merId(), $group));
    }

    protected function doSave(string $group)
    {
        $feature = (string)$this->request->param('feature', '');
        $value = (int)$this->request->param('value', 0);
        try {
            return app('json')->success($this->repository->saveMerSwitch((int)$this->request->merId(), $group, $feature, $value));
        } catch (\Throwable $e) {
            return app('json')->fail($e->getMessage());
        }
    }
}
