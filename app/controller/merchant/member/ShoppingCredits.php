<?php

namespace app\controller\merchant\member;

use app\common\repositories\merchant\ShoppingCreditsRepository;
use app\common\repositories\merchant\ShoppingCreditsRefundRepository;
use crmeb\basic\BaseController;
use think\App;

/**
 * 商户后台购物积分/购物金管理控制器：负责购物金充值套餐列表/详情/新增/编辑/删除/上下架、充值订单、退款订单、退款详情与退款审核等接口，继承自 BaseController
 */
class ShoppingCredits extends BaseController
{
    protected ShoppingCreditsRepository $repository;

    /**
     * 构造函数，初始化控制器
     * @param App $app 应用实例
     * @param ShoppingCreditsRepository $repository 购物金仓储
     */
    public function __construct(App $app, ShoppingCreditsRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 商户后台-充值套餐列表：获取当前店铺的购物金充值套餐列表
     * @return \think\response\Json
     */
    public function packages()
    {
        return app('json')->success($this->repository->packageList($this->request->merId()));
    }

    /**
     * 商户后台-充值套餐详情：获取当前店铺指定购物金充值套餐的详情
     * @param $id 套餐ID
     * @return \think\response\Json
     */
    public function packageDetail($id)
    {
        return app('json')->success($this->repository->packageDetail($this->request->merId(), (int)$id));
    }

    /**
     * 商户后台-添加充值套餐：新增当前店铺的购物金充值套餐
     * @return \think\response\Json
     */
    public function createPackage()
    {
        return app('json')->success('添加成功', $this->repository->savePackage(
            $this->request->merId(),
            $this->request->post()
        ));
    }

    /**
     * 商户后台-编辑充值套餐：更新当前店铺指定购物金充值套餐
     * @param $id 套餐ID
     * @return \think\response\Json
     */
    public function updatePackage($id)
    {
        return app('json')->success('编辑成功', $this->repository->savePackage(
            $this->request->merId(),
            $this->request->post(),
            (int)$id
        ));
    }

    /**
     * 商户后台-删除充值套餐：删除当前店铺指定购物金充值套餐
     * @param $id 套餐ID
     * @return \think\response\Json
     */
    public function deletePackage($id)
    {
        $this->repository->deletePackage($this->request->merId(), (int)$id);
        return app('json')->success('删除成功');
    }

    /**
     * 商户后台-套餐上下架：切换或设置指定购物金充值套餐的上架状态
     * @param $id 套餐ID
     * @return \think\response\Json
     */
    public function packageStatus($id)
    {
        $status = $this->request->has('status') ? (int)$this->request->param('status') : null;
        return app('json')->success(['show_status' => $this->repository->togglePackage(
            $this->request->merId(),
            (int)$id,
            $status
        )]);
    }

    /**
     * 商户后台-充值订单列表：按条件分页查询当前店铺的购物金充值订单
     * @return \think\response\Json
     */
    public function orders(ShoppingCreditsRefundRepository $refundRepository)
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['order_no', 'keyword', 'uid', 'nickname', 'phone', 'pay_type', 'date']);
        return app('json')->success($refundRepository->merchantOrders($this->request->merId(), $where, $page, $limit));
    }

    /**
     * 商户后台-退款订单列表：按条件分页查询当前店铺的购物金退款订单
     * @return \think\response\Json
     */
    public function refunds(ShoppingCreditsRefundRepository $refundRepository)
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['audit_type', 'order_no', 'uid', 'date']);
        return app('json')->success($refundRepository->merchantRefundOrders($this->request->merId(), $where, $page, $limit));
    }

    /**
     * 商户后台-退款订单统计：返回当前店铺退款申请的状态数量
     * @return \think\response\Json
     */
    public function refundStatistics(ShoppingCreditsRefundRepository $refundRepository)
    {
        return app('json')->success($refundRepository->refundStatistics($this->request->merId()));
    }

    /**
     * 商户后台-退款订单详情：获取当前店铺指定退款单号的退款详情
     * @param $refundNo 退款单号
     * @return \think\response\Json
     */
    public function refundDetail($refundNo, ShoppingCreditsRefundRepository $refundRepository)
    {
        return app('json')->success($refundRepository->refundDetail($this->request->merId(), (string)$refundNo));
    }

    public function auditRefundForm($id)
    {
        return app('json')->success(formToData($this->repository->switchStatusForm($id)));
    }

    /**
     * 商户后台-退款审核：审核购物金退款申请，通过则执行退款
     * @return \think\response\Json
     */
    public function auditRefund($id, ShoppingCreditsRefundRepository $refundRepository)
    {
        [$auditType, $reason] = $this->request->params(['audit_type', ['reason', '']], true);
        if (!in_array((int)$auditType, [-1, 1], true)) {
            return app('json')->fail('请选择审核状态');
        }
        $refundRepository->auditRefund(
            $this->request->merId(),
            (int)$id,
            (int)$auditType === 1,
            (string)$reason,
            (int)$this->request->adminId()
        );
        return app('json')->success('审核成功');
    }
}
