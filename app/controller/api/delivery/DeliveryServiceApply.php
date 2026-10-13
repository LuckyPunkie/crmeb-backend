<?php

namespace app\controller\api\delivery;

use app\common\repositories\delivery\DeliveryServiceApplyRepository;
use app\validate\DeliveryServiceApplyValidate;
use crmeb\basic\BaseController;
use think\App;

class DeliveryServiceApply extends BaseController
{
    protected $repository;

    public function __construct(App $app, DeliveryServiceApplyRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    public function apply(DeliveryServiceApplyValidate $validate)
    {
        $data = $this->request->params([
            'avatar',
            'name',
            'phone',
            'certificate_front',
            'certificate_back',
            ['certificate_images', []],
            ['remark', ''],
            ['extend', []],
        ]);
        $validate->scene('apply')->check($data);
        $apply = $this->repository->submit($this->request->uid(), $data);

        return app('json')->success('提交成功', [
            'delivery_service_apply_id' => $apply->delivery_service_apply_id,
        ]);
    }

    public function detail()
    {
        $apply = $this->repository->getUserApply($this->request->uid());
        if (!$apply) {
            return app('json')->fail('暂无申请记录');
        }

        return app('json')->success($apply);
    }
}
