<?php
// +----------------------------------------------------------------------
// | 商户后台 - 扫码下单设置
// +----------------------------------------------------------------------

namespace app\controller\merchant\store\scanOrder;

use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\store\scanOrder\ScanOrderConfigRepository;

class ScanOrderConfig extends BaseController
{
    protected $repository;

    public function __construct(App $app, ScanOrderConfigRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * GET /mer/scan_order/config
     */
    public function get()
    {
        return app('json')->success($this->repository->getConfig((int)$this->request->merId()));
    }

    /**
     * POST /mer/scan_order/config
     */
    public function save()
    {
        $data = $this->request->params(['need_pay', 'voice_enable', 'auto_print']);
        $row = $this->repository->saveConfig((int)$this->request->merId(), $data);
        return app('json')->success($row);
    }

    /**
     * GET /mer/scan_order/printer_status
     */
    public function printerStatus()
    {
        $merId = (int)$this->request->merId();
        $bound = $this->repository->isPrinterBound($merId);
        return app('json')->success([
            'bound' => $bound ? 1 : 0,
            'message' => $bound ? '已绑定小票打印机' : '未绑定小票打印机',
        ]);
    }
}
