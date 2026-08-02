<?php
// +----------------------------------------------------------------------
// | 商户后台 - 扫码下单台号管理
// +----------------------------------------------------------------------

namespace app\controller\merchant\store\scanOrder;

use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\store\scanOrder\ScanOrderTableRepository;
use app\validate\merchant\scanOrder\ScanOrderTableValidate;

class ScanOrderTable extends BaseController
{
    protected $repository;

    public function __construct(App $app, ScanOrderTableRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * GET /mer/scan_order/table/lst
     */
    public function lst()
    {
        $merId = (int)$this->request->merId();
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->repository->getList($merId, $page, $limit));
    }

    /**
     * POST /mer/scan_order/table/create
     */
    public function create(ScanOrderTableValidate $validate)
    {
        $data = $this->request->params(['table_label']);
        $validate->check($data);
        $row = $this->repository->createTable((int)$this->request->merId(), (string)$data['table_label']);
        return app('json')->success($row);
    }

    /**
     * DELETE /mer/scan_order/table/:id
     */
    public function delete($id)
    {
        $this->repository->deleteTable((int)$this->request->merId(), (int)$id);
        return app('json')->success('删除成功');
    }

    /**
     * GET /mer/scan_order/table/qrcode/:id
     */
    public function qrcode($id)
    {
        $merId = (int)$this->request->merId();
        $force = (int)$this->request->param('force', 0) === 1;
        $detail = $this->repository->getDetail($merId, (int)$id);
        $url = $this->repository->getQrcodeUrl(
            $merId,
            (int)$id,
            (string)$detail['table_label'],
            $force
        );
        return app('json')->success([
            'id' => (int)$id,
            'table_label' => $detail['table_label'],
            'jump_url' => $detail['jump_url'],
            'sign' => $detail['sign'],
            'qrcode' => $url,
            'download_name' => '台号_' . $detail['table_label'] . '.png',
        ]);
    }
}
