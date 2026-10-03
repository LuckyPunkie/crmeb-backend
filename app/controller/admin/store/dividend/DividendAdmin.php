<?php

namespace app\controller\admin\store\dividend;

use app\common\repositories\store\dividend\MerchantDividendRepository;
use crmeb\basic\BaseController;
use think\App;

/**
 * 商家分红
 */
class DividendAdmin extends BaseController
{
    protected $repository;

    public function __construct(App $app, MerchantDividendRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 预览：只计算，不落库、不生成订单
     */
    public function preview()
    {
        $data = $this->request->params(['start_time', 'end_time', 'total_amount']);
        try {
            return app('json')->success($this->repository->calculate($data['start_time'], $data['end_time'], (string)$data['total_amount']));
        } catch (\Throwable $e) {
            return app('json')->fail($e->getMessage());
        }
    }

    /**
     * 确认发放：真正生成批次、明细、订单、商户入账
     */
    public function issue()
    {
        $data = $this->request->params(['start_time', 'end_time', 'total_amount', ['remark', '']]);
        try {
            $batch = $this->repository->issue(
                $data['start_time'],
                $data['end_time'],
                (string)$data['total_amount'],
                (int)$this->request->adminId(),
                (string)$data['remark']
            );
            return app('json')->success($batch);
        } catch (\Throwable $e) {
            return app('json')->fail($e->getMessage());
        }
    }

    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['keyword']);
        return app('json')->success($this->repository->lst($where, $page, $limit));
    }

    public function detail($id)
    {
        try {
            return app('json')->success($this->repository->detail((int)$id));
        } catch (\Throwable $e) {
            return app('json')->fail($e->getMessage());
        }
    }
}
