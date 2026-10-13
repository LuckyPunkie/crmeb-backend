<?php

namespace app\controller\admin\delivery;

use app\common\model\delivery\DeliveryService;
use app\common\repositories\delivery\DeliveryServiceRepository;
use app\common\repositories\delivery\DeliveryServiceWithdrawRepository;
use app\validate\DeliveryServiceWithdrawManageValidate;
use crmeb\basic\BaseController;
use crmeb\services\ExcelService;
use think\App;

class DeliveryServiceWithdraw extends BaseController
{
    protected $repository;

    public function __construct(App $app, DeliveryServiceWithdrawRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params([
            'keyword', 'withdraw_sn', 'service_id', 'uid', 'nickname', 'phone', 'real_name',
            'status', 'payment_method', 'date', ['create_time', []],
        ]);
        $where['service_type'] = DeliveryService::TYPE_PLATFORM;
        $where['mer_id'] = 0;
        return app('json')->success($this->repository->getList($where, $page, $limit));
    }

    public function title()
    {
        $where = $this->request->params([
            'keyword', 'withdraw_sn', 'service_id', 'uid', 'nickname', 'phone', 'real_name',
            'payment_method', 'date', ['create_time', []],
        ]);
        $where['service_type'] = DeliveryService::TYPE_PLATFORM;
        $where['mer_id'] = 0;
        return app('json')->success($this->repository->getStatusStatistics($where));
    }

    public function export(ExcelService $excelService)
    {
        $where = $this->request->params([
            'keyword', 'withdraw_sn', 'service_id', 'uid', 'nickname', 'phone', 'real_name',
            'status', 'payment_method', 'date', ['create_time', []],
        ]);
        $where['service_type'] = DeliveryService::TYPE_PLATFORM;
        $where['mer_id'] = 0;

        return app('json')->success($excelService->deliveryServiceWithdraw($where));
    }

    public function detail($id)
    {
        $data = $this->repository->getDetail((int)$id, DeliveryService::TYPE_PLATFORM, 0);
        if (!$data) {
            return app('json')->fail('提现申请不存在');
        }
        return app('json')->success($data);
    }

    public function createForm()
    {
        return app('json')->success(formToData(
            $this->repository->form(null, DeliveryService::TYPE_PLATFORM, 0)
        ));
    }

    public function create(DeliveryServiceWithdrawManageValidate $validate, DeliveryServiceRepository $serviceRepository)
    {
        $data = $this->request->params(['service_id', 'amount', 'payment_method', 'payment_info', ['remark', '']]);
        $validate->scene('create')->check($data);
        $service = $serviceRepository->getWhere([
            'service_id' => $data['service_id'],
            'type' => DeliveryService::TYPE_PLATFORM,
            'mer_id' => 0,
            'is_del' => 0,
        ]);
        if (!$service) {
            return app('json')->fail('平台配送员不存在');
        }
        $this->repository->create((int)$service->service_id, (int)$service->uid, 0, $data);
        return app('json')->success('添加成功');
    }

    public function updateForm($id)
    {
        return app('json')->success(formToData(
            $this->repository->form((int)$id, DeliveryService::TYPE_PLATFORM, 0)
        ));
    }

    public function update($id, DeliveryServiceWithdrawManageValidate $validate)
    {
        $data = $this->request->params(['amount', 'payment_method', 'payment_info', ['remark', '']]);
        $validate->scene('update')->check($data);
        $this->repository->updatePending((int)$id, DeliveryService::TYPE_PLATFORM, 0, $data);
        return app('json')->success('编辑成功');
    }

    public function delete($id)
    {
        $this->repository->cancel((int)$id, DeliveryService::TYPE_PLATFORM, 0);
        return app('json')->success('删除成功');
    }

    public function auditForm($id)
    {
        return app('json')->success(formToData(
            $this->repository->auditForm((int)$id, DeliveryService::TYPE_PLATFORM, 0)
        ));
    }

    public function audit($id, DeliveryServiceWithdrawManageValidate $validate)
    {
        $data = $this->request->params(['status', ['audit_reason', '']]);
        $validate->scene('audit')->check($data);
        $this->repository->audit(
            (int)$id,
            DeliveryService::TYPE_PLATFORM,
            0,
            (int)$this->request->adminId(),
            $data
        );
        return app('json')->success('审核成功');
    }

    public function remarkForm($id)
    {
        return app('json')->success(formToData(
            $this->repository->remarkForm((int)$id, DeliveryService::TYPE_PLATFORM, 0)
        ));
    }

    public function remark($id, DeliveryServiceWithdrawManageValidate $validate)
    {
        $data = $this->request->params([['remark', '']]);
        $validate->scene('remark')->check($data);
        $this->repository->updateRemark((int)$id, DeliveryService::TYPE_PLATFORM, 0, (string)$data['remark']);
        return app('json')->success('备注修改成功');
    }

    public function transferForm($id)
    {
        return app('json')->success(formToData(
            $this->repository->transferForm((int)$id, DeliveryService::TYPE_PLATFORM, 0)
        ));
    }

    public function transfer($id, DeliveryServiceWithdrawManageValidate $validate)
    {
        $data = $this->request->params([['payment_sn', ''], ['payment_vouchers', []]]);
        $validate->scene('transfer')->check($data);
        $this->repository->transfer((int)$id, DeliveryService::TYPE_PLATFORM, 0, $data);
        return app('json')->success('转账记录成功');
    }
}
