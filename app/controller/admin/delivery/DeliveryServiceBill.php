<?php

namespace app\controller\admin\delivery;

use app\common\model\delivery\DeliveryStation;
use app\common\repositories\delivery\DeliveryServiceBillRepository;
use crmeb\basic\BaseController;
use crmeb\services\ExcelService;
use think\App;

class DeliveryServiceBill extends BaseController
{
    protected $repository;

    public function __construct(App $app, DeliveryServiceBillRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params([
            'keyword', 'bill_sn', 'order_sn', 'service_id', 'uid', 'nickname', 'phone', 'bill_type', 'pm', 'status', 'date',
        ]);
        return app('json')->success($this->repository->getManageList(
            $where,
            $page,
            $limit,
            DeliveryStation::TYPE_PLATFORM
        ));
    }

    public function export(ExcelService $excelService)
    {
        $where = $this->request->params([
            'keyword', 'bill_sn', 'order_sn', 'service_id', 'uid', 'nickname', 'phone', 'bill_type', 'pm', 'status', 'date',
        ]);

        return app('json')->success($excelService->deliveryServiceBill(
            $where,
            DeliveryStation::TYPE_PLATFORM
        ));
    }

    public function detail($id)
    {
        $data = $this->repository->getManageDetail((int)$id, DeliveryStation::TYPE_PLATFORM);
        if (!$data) {
            return app('json')->fail('流水记录不存在');
        }
        return app('json')->success($data);
    }
}
