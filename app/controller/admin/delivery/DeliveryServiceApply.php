<?php

namespace app\controller\admin\delivery;

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

    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params([
            'keyword',
            'status',
            'uid',
            'nickname',
            'name',
            'phone',
            ['create_time', []],
        ]);

        return app('json')->success($this->repository->getList($where, $page, $limit));
    }

    public function title()
    {
        $where = $this->request->params([
            'keyword',
            'uid',
            'nickname',
            'name',
            'phone',
            ['create_time', []],
        ]);

        return app('json')->success($this->repository->getStatusStatistics($where));
    }

    public function detail($id)
    {
        $apply = $this->repository->getDetail((int)$id);
        if (!$apply) {
            return app('json')->fail('申请记录不存在');
        }

        return app('json')->success($apply);
    }

    public function audit($id, DeliveryServiceApplyValidate $validate)
    {
        $data = $this->request->params(['status', ['audit_reason', '']]);
        $validate->scene('audit')->check($data);
        $this->repository->audit((int)$id, $this->request->adminId(), $data);

        return app('json')->success('审核成功');
    }
    public function auditFrom($id)
    {
        return app('json')->success(formToData($this->repository->switchStatusForm($id)));
    }
}
