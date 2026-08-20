<?php

namespace app\controller\admin\store\equity;

use app\common\repositories\store\equity\EquityGrantRepository;
use app\common\repositories\store\equity\EquityProjectRepository;
use crmeb\basic\BaseController;
use think\App;

class EquityAdmin extends BaseController
{
    protected $repository;
    protected $grant;

    public function __construct(App $app, EquityProjectRepository $repository, EquityGrantRepository $grant)
    {
        parent::__construct($app);
        $this->repository = $repository;
        $this->grant = $grant;
    }

    public function pendingStores()
    {
        [$page, $limit] = $this->getPage();
        $status = $this->request->param('status', '');
        return app('json')->success($this->repository->pendingStores(['status' => $status], $page, $limit));
    }

    public function bindStore($id)
    {
        $newStoreId = (int)$this->request->param('new_store_id', 0);
        if ($newStoreId <= 0) {
            return app('json')->fail('请选择新店');
        }
        try {
            return app('json')->success($this->repository->bindNewStore((int)$id, $newStoreId));
        } catch (\Throwable $e) {
            return app('json')->fail($e->getMessage());
        }
    }

    public function refunds()
    {
        [$page, $limit] = $this->getPage();
        $status = $this->request->param('status', '');
        return app('json')->success($this->repository->refundList(['status' => $status], $page, $limit));
    }

    public function auditRefund($id)
    {
        $pass = (int)$this->request->param('pass', 0) === 1;
        $reason = (string)$this->request->param('audit_reason', '');
        $adminId = (int)$this->request->adminId();
        try {
            $this->grant->auditInvestRefund((int)$id, $pass, $adminId, $reason);
            return app('json')->success('操作成功');
        } catch (\Throwable $e) {
            return app('json')->fail($e->getMessage());
        }
    }

    public function noticeLst()
    {
        [$page, $limit] = $this->getPage();
        $projectId = (int)$this->request->param('project_id', 0);
        return app('json')->success($this->repository->noticeList(['project_id' => $projectId], $page, $limit));
    }

    public function noticeSave()
    {
        $id = (int)$this->request->param('id', 0);
        $data = $this->request->params([
            'project_id', 'title', 'period', 'expected_date', 'expected_amount', 'content', 'status',
        ]);
        try {
            return app('json')->success($this->repository->saveNotice($data, $id));
        } catch (\Throwable $e) {
            return app('json')->fail($e->getMessage());
        }
    }

    public function noticeWithdraw($id)
    {
        $this->repository->withdrawNotice((int)$id);
        return app('json')->success('已撤回');
    }

    public function executeDividend()
    {
        $projectId = (int)$this->request->param('project_id', 0);
        $totalAmount = (float)$this->request->param('total_amount', 0);
        $period = (string)$this->request->param('period', '');
        try {
            return app('json')->success($this->repository->executeDividend($projectId, $totalAmount, $period));
        } catch (\Throwable $e) {
            return app('json')->fail($e->getMessage());
        }
    }

    public function saveFinancialReport($id)
    {
        $data = $this->request->params([
            'start_date', 'end_date', 'cash_income', 'expense_list', 'cost_list',
            'staff_count', 'staff_wage_total', 'staff_wage_avg', 'staff_wage_structure', 'remark',
        ]);
        $adminId = (int)$this->request->adminId();
        try {
            return app('json')->success($this->repository->saveFinancialReport((int)$id, $data, $adminId));
        } catch (\Throwable $e) {
            return app('json')->fail($e->getMessage());
        }
    }

    public function financialReportLst()
    {
        [$page, $limit] = $this->getPage();
        $projectId = (int)$this->request->param('project_id', 0);
        return app('json')->success($this->repository->financialReportList([
            'project_id' => $projectId,
        ], $page, $limit));
    }

    public function deleteFinancialReport($id)
    {
        try {
            $this->repository->deleteFinancialReport((int)$id);
            return app('json')->success('已删除');
        } catch (\Throwable $e) {
            return app('json')->fail($e->getMessage());
        }
    }

    public function staffPool($id)
    {
        return app('json')->success($this->repository->staffPoolList((int)$id));
    }

    public function saveStaffPool($id)
    {
        $list = $this->request->param('list', []);
        if (!is_array($list)) {
            $list = [];
        }
        try {
            $this->repository->saveStaffPool((int)$id, $list);
            return app('json')->success('保存成功');
        } catch (\Throwable $e) {
            return app('json')->fail($e->getMessage());
        }
    }

    public function projectLst()
    {
        [$page, $limit] = $this->getPage();
        $status = $this->request->param('status', '');
        $query = \app\common\model\store\equity\EquityProject::getDB();
        if ($status !== '') {
            $query->where('status', (int)$status);
        }
        $count = $query->count();
        $list = $query->order('id', 'desc')->page($page, $limit)->select()->toArray();
        foreach ($list as &$row) {
            $mer = \app\common\model\system\merchant\Merchant::getDB()->where('mer_id', (int)$row['mer_id'])->find();
            $row['store_name'] = $mer['mer_name'] ?? '';
            if (!empty($row['new_store_id'])) {
                $newMer = \app\common\model\system\merchant\Merchant::getDB()->where('mer_id', (int)$row['new_store_id'])->find();
                $row['new_store_name'] = $newMer['mer_name'] ?? '';
                if ($row['new_store_name']) {
                    $row['store_name'] = $row['new_store_name'];
                }
            }
        }
        unset($row);
        return app('json')->success(compact('count', 'list'));
    }
}
