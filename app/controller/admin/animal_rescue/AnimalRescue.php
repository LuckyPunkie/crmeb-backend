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

namespace app\controller\admin\animal_rescue;

use app\common\repositories\animal_rescue\AdoptionRepository;
use app\common\repositories\animal_rescue\AnimalRescueRepository;
use app\validate\api\AnimalRescueValidate;
use crmeb\basic\BaseController;
use think\App;

/**
 * 流浪动物救助 - 后台管理控制器
 * Class AnimalRescue
 * @package app\controller\admin\animal_rescue
 */
class AnimalRescue extends BaseController
{
    /**
     * @var AnimalRescueRepository
     */
    protected $repository;

    /**
     * @param App $app
     * @param AnimalRescueRepository $repository
     */
    public function __construct(App $app, AnimalRescueRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 帖子列表（管理）
     * @return \think\response\Json
     */
    public function lst()
    {
        $where = $this->request->params(['keyword', 'type', 'status', 'fund_status']);
        $where['is_del'] = 0;
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->repository->getAdminList($where, $page, $limit));
    }

    /**
     * 删除帖子（管理）
     * @param int $id
     * @return \think\response\Json
     */
    public function delete($id)
    {
        $this->repository->adminDelete((int)$id);
        return app('json')->success('删除成功');
    }

    /**
     * 审核帖子
     * @param int $id
     * @param AnimalRescueValidate $validate
     * @return \think\response\Json
     */
    public function audit($id, AnimalRescueValidate $validate)
    {
        $data = $this->request->params(['status', 'remark']);
        $validate->scene('audit')->check($data);
        $this->repository->auditPost((int)$id, (int)$data['status'], $data['remark'] ?? '');
        $msg = $data['status'] == 1 ? '审核通过' : '审核驳回';
        return app('json')->success($msg);
    }

    /**
     * 领养申请列表（管理）
     * @param AdoptionRepository $adoptionRepo
     * @return \think\response\Json
     */
    public function adoptionLst(AdoptionRepository $adoptionRepo)
    {
        $where = $this->request->params(['status', 'post_id']);
        [$page, $limit] = $this->getPage();
        return app('json')->success($adoptionRepo->getAdminAdoptionList($where, $page, $limit));
    }

    /**
     * 审核领养申请
     * @param int $id
     * @param AnimalRescueValidate $validate
     * @param AdoptionRepository $adoptionRepo
     * @return \think\response\Json
     */
    public function adoptionAudit($id, AnimalRescueValidate $validate, AdoptionRepository $adoptionRepo)
    {
        $data = $this->request->params(['status', 'remark']);
        $validate->scene('audit')->check($data);
        $adoptionRepo->auditAdoption((int)$id, (int)$data['status'], $data['remark'] ?? '');
        $msg = $data['status'] == 2 ? '审核通过' : '审核驳回';
        return app('json')->success($msg);
    }

    /**
     * 数据统计
     * @return \think\response\Json
     */
    public function statistics()
    {
        return app('json')->success($this->repository->getStatistics());
    }

    /**
     * 拨款审核列表
     */
    public function fundAuditLst(\app\common\repositories\animal_rescue\FundAuditRepository $fundRepo)
    {
        $where = $this->request->params(['status', 'post_id']);
        [$page, $limit] = $this->getPage();
        return app('json')->success($fundRepo->getAdminAuditList($where, $page, $limit));
    }

    /**
     * 拨款审核详情
     */
    public function fundAuditDetail($id, \app\common\repositories\animal_rescue\FundAuditRepository $fundRepo)
    {
        $info = $fundRepo->getAuditDetail((int)$id);
        if (!$info) {
            return app('json')->fail('记录不存在');
        }
        return app('json')->success($info);
    }

    /**
     * 拨款审核通过并拨款
     */
    public function fundAuditApprove($id, \app\common\repositories\animal_rescue\FundAuditRepository $fundRepo)
    {
        $actualAmount = (float)$this->request->param('actual_amount', 0);
        try {
            $adminId = (int)$this->request->adminId();
            $fundRepo->approveAndAllocate((int)$id, $actualAmount, $adminId);
            return app('json')->success('审核通过，已执行拨款');
        } catch (\think\exception\ValidateException $e) {
            return app('json')->fail($e->getError() ?: $e->getMessage());
        } catch (\Exception $e) {
            return app('json')->fail('操作失败：' . $e->getMessage());
        }
    }

    /**
     * 拨款审核拒绝
     */
    public function fundAuditReject($id, \app\common\repositories\animal_rescue\FundAuditRepository $fundRepo)
    {
        $reason = (string)$this->request->param('reject_reason', '');
        try {
            $adminId = (int)$this->request->adminId();
            $fundRepo->rejectAudit((int)$id, $reason, $adminId);
            return app('json')->success('已拒绝');
        } catch (\think\exception\ValidateException $e) {
            return app('json')->fail($e->getError() ?: $e->getMessage());
        } catch (\Exception $e) {
            return app('json')->fail('操作失败：' . $e->getMessage());
        }
    }

    /**
     * 退回待提交凭证
     * @param int $id 帖子 post_id
     */
    public function fundAuditRollback($id, \app\common\repositories\animal_rescue\FundAuditRepository $fundRepo)
    {
        try {
            $fundRepo->rollbackToWaitVoucher((int)$id);
            return app('json')->success('已退回，发布人可重新编辑凭证');
        } catch (\think\exception\ValidateException $e) {
            return app('json')->fail($e->getError() ?: $e->getMessage());
        }
    }

    /**
     * 领养保证金托管列表
     */
    public function depositLst(AdoptionRepository $adoptionRepo)
    {
        $where = $this->request->params(['status', 'post_id', 'uid', 'paid', 'order_sn']);
        [$page, $limit] = $this->getPage();
        return app('json')->success($adoptionRepo->getAdminDepositList($where, $page, $limit));
    }

    /**
     * 领养保证金汇总
     */
    public function depositStatistics(AdoptionRepository $adoptionRepo)
    {
        return app('json')->success($adoptionRepo->getDepositStatistics());
    }

    /**
     * 月捐结算记录
     */
    public function settlementLst(\app\common\repositories\animal_rescue\FundAuditRepository $fundRepo)
    {
        $where = $this->request->params(['merchant_id', 'settlement_month', 'post_id']);
        [$page, $limit] = $this->getPage();
        return app('json')->success($fundRepo->getAdminSettlementList($where, $page, $limit));
    }
}
