<?php

namespace app\controller\admin\delivery;

use app\common\repositories\delivery\DeliveryConfigRepository;
use app\validate\merchant\DeliveryConfigValidate;
use crmeb\basic\BaseController;
use think\App;

class DeliveryConfig extends BaseController
{
    protected $validate;
    protected $repository;

    public function __construct(App $app, DeliveryConfigValidate $validate, DeliveryConfigRepository $repository)
    {
        parent::__construct($app);
        $this->validate = $validate;
        $this->repository = $repository;
    }

    public function configuration()
    {
        return app('json')->success($this->repository->platformSettings());
    }

    public function update(int $id)
    {
        $basicSettingsParams = $this->request->params([
            ['platform_delivery_status', 0],
            ['platform_delivery_background', ''],
            ['platform_delivery_form', []],
            ['platform_delivery_grab_status', 1],
            ['platform_delivery_accept_status', 1],
            ['platform_delivery_pickup_status', 1],
            ['platform_delivery_start_status', 1],
        ]);
        $basicSettingsParams['platform_delivery_finish_status'] = 1;
        $basicSettingsParams['platform_delivery_form'] = json_encode($basicSettingsParams['platform_delivery_form']);
        $deliveryConfigParams = $this->request->params([
            ['min_delivery_amount', 0],
            ['base_shipping_fee', 0],
            ['free_shipping_amount', 0],
            'is_premium_stack_enabled',
            ['distance_premium_config', []],
            ['weight_premium_config', []],
            ['delivery_time_type', 1],
            ['selectable_days', 7],
            ['delivery_prompt', ''],
            ['commission_rate', 0],
            ['night_premium_config',[]]
        ]);

        if (!$this->validate->platformUpdate($basicSettingsParams, $deliveryConfigParams)) {
            return app('json')->fail($this->validate->getError());
        }

        return app('json')->success(
            empty($id) ? '新增成功' : '修改成功',
            $this->repository->updatePlatform($id, $basicSettingsParams, $deliveryConfigParams)
        );
    }
}
