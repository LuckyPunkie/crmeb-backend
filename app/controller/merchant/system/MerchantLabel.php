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

    public function labels()
    {
        return app('json')->success($this->repository->getLabelsWithStatus($this->request->merId()));
    }

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

    /**
     * 商家保存标签公告文案
     */
    public function saveAnnouncement($id)
    {
        if (!$id) return app('json')->fail('参数错误');
        $content = (string)$this->request->param('content', '');
        if (mb_strlen($content) > 500) return app('json')->fail('公告不能超过 500 字');
        try {
            $this->repository->saveAnnouncement((int)$id, $this->request->merId(), $content);
        } catch (\InvalidArgumentException $e) {
            return app('json')->fail($e->getMessage());
        }
        return app('json')->success('保存成功');
    }

    /**
     * 查看某标签退款账户信息（返回 online/offline/info 用于弹窗展示）
     */
    public function refundInfo($id)
    {
        if (!$id) return app('json')->fail('参数错误');
        try {
            $result = $this->repository->checkRefundLabelMargin((int)$id, $this->request->merId(), $this->request->adminId());
        } catch (\Exception $e) {
            return app('json')->fail($e->getMessage());
        }
        return app('json')->success($result);
    }

    /**
     * 提交某标签保证金退款申请（走店铺退款完全相同的流程 + Financial 记录）
     */
    public function refundApply($id)
    {
        if (!$id) return app('json')->fail('参数错误');
        $account = $this->request->params(['type', 'name', 'code', 'pic']);
        try {
            $this->repository->refundLabelMargin((int)$id, $this->request->merId(), $this->request->adminId(), $account);
        } catch (\Exception $e) {
            return app('json')->fail($e->getMessage());
        }
        return app('json')->success('提交成功');
    }
}
