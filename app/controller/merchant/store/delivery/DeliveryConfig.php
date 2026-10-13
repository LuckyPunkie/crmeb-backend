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

namespace app\controller\merchant\store\delivery;

use think\App;
use crmeb\basic\BaseController;
use app\validate\merchant\DeliveryConfigValidate;
use app\common\repositories\delivery\DeliveryConfigRepository;

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

    protected function getValidate()
    {
        return $this->validate;
    }

    protected function getRepository()
    {
        return $this->repository;
    }

    public function configuration()
    {
        $keys = [
            'mer_delivery_type',
            'mer_delivery_order_status',
            'dada_app_key',
            'dada_app_sercret',
            'dada_source_id',
            'uupt_appkey',
            'uupt_app_id',
            'uupt_open_id'
        ];
        return app('json')->success($this->getRepository()->deliverySettings($this->request->merId(), $keys));
    }

    public function update(int $id)
    {
        $merId = $this->request->merId();
        $basicSettingsParams = $this->request->params([
            'mer_delivery_type',
            ['mer_delivery_order_status', 0],
            'dada_app_key',
            'dada_app_sercret',
            'dada_source_id',
            'uupt_appkey',
            'uupt_app_id',
            'uupt_open_id'
        ]);
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
            ['commission_rate', 0]
        ]);

        $validate = $this->getValidate();
        if (!$validate->update($basicSettingsParams, $deliveryConfigParams)) {
            return app('json')->fail($validate->getError());
        }

        return app('json')->success(
            empty($id) ? '新增成功' : '修改成功',
            $this->getRepository()->update(
                $id,
                $merId,
                $basicSettingsParams,
                $deliveryConfigParams
            )
        );
    }
}
