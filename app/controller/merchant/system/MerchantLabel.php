<?php

namespace app\controller\merchant\system;

use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\system\merchant\MerchantLabelRepository;

class MerchantLabel extends BaseController
{
    protected $repository;

    public function __construct(App $app, MerchantLabelRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 获取所有商家标签（附带当前商户加入状态）
     */
    public function labels()
    {
        $merId = $this->request->merId();
        return app('json')->success($this->repository->getLabelsWithStatus($merId));
    }

    /**
     * 加入标签
     */
    public function join($id)
    {
        if (!$id) return app('json')->fail('参数错误');
        try {
            $result = $this->repository->joinLabel((int)$id, $this->request->merId());
        } catch (\InvalidArgumentException $e) {
            return app('json')->fail($e->getMessage());
        }
        return app('json')->success($result);
    }

    /**
     * 获取标签保证金支付二维码
     */
    public function marginCode($id)
    {
        if (!$id) return app('json')->fail('参数错误');
        try {
            $result = $this->repository->getMarginCode((int)$id, $this->request->merId());
        } catch (\Exception $e) {
            return app('json')->fail($e->getMessage());
        }
        return app('json')->success($result);
    }
}
