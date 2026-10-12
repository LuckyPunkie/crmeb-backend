<?php

namespace app\controller\admin\system\merchant;

use app\common\repositories\system\merchant\MerchantFeatureSwitchRepository;
use crmeb\basic\BaseController;
use think\App;

/**
 * 店铺功能开关 平台总开关
 *  - 买单下单设置：店铺详情页「线上下单」「买单」按钮
 *  - 盲盒入口设置：买单支付成功页「店老板请你免费开盲盒」入口
 *  - 公益商品设置：买单页公益商品 / 购物享免单
 */
class MerchantActionSwitch extends BaseController
{
    protected $repository;

    public function __construct(App $app, MerchantFeatureSwitchRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    public function getModes()
    {
        return app('json')->success($this->repository->getModes(MerchantFeatureSwitchRepository::GROUP_ACTION));
    }

    public function saveModes()
    {
        return $this->doSave(MerchantFeatureSwitchRepository::GROUP_ACTION, ['online_order_mode', 'bill_mode']);
    }

    public function getBlindboxModes()
    {
        return app('json')->success($this->repository->getModes(MerchantFeatureSwitchRepository::GROUP_BLINDBOX));
    }

    public function saveBlindboxModes()
    {
        return $this->doSave(MerchantFeatureSwitchRepository::GROUP_BLINDBOX, ['pay_blindbox_mode']);
    }

    public function getWelfareModes()
    {
        return app('json')->success($this->repository->getModes(MerchantFeatureSwitchRepository::GROUP_WELFARE));
    }

    public function saveWelfareModes()
    {
        return $this->doSave(MerchantFeatureSwitchRepository::GROUP_WELFARE, ['bill_welfare_mode']);
    }

    protected function doSave(string $group, array $fields)
    {
        $data = $this->request->params($fields);
        try {
            return app('json')->success($this->repository->saveModes($group, $data));
        } catch (\Throwable $e) {
            return app('json')->fail($e->getMessage());
        }
    }
}
