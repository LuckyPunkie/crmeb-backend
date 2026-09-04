<?php

namespace app\controller\admin\gift;

use app\common\repositories\gift\GiftRepository;
use crmeb\basic\BaseController;
use think\App;
use think\exception\ValidateException;

class Gift extends BaseController
{
    /** @var GiftRepository */
    protected $repository;

    public function __construct(App $app, GiftRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params([
            ['gift_name', ''],
            ['status', ''],
        ]);
        return app('json')->success($this->repository->adminList($where, $page, $limit));
    }

    public function create()
    {
        $data = $this->request->params([
            ['gift_name', ''],
            ['gift_icon', ''],
            ['gift_price', 0],
            ['description', ''],
            ['sort_weight', 0],
        ]);
        if (!$data['gift_name']) {
            throw new ValidateException('礼物名称不能为空');
        }
        if ((float)$data['gift_price'] <= 0) {
            throw new ValidateException('礼物金额必须大于0');
        }
        $adminId = (int)($this->request->adminId() ?? 0);
        $id = $this->repository->adminCreate($data, $adminId);
        return app('json')->success(['gift_id' => $id]);
    }

    public function update($id)
    {
        $data = $this->request->params([
            ['gift_name', ''],
            ['gift_icon', ''],
            ['gift_price', ''],
            ['description', ''],
            ['sort_weight', ''],
        ]);
        $adminId = (int)($this->request->adminId() ?? 0);
        $this->repository->adminUpdate((int)$id, array_filter($data, fn($v) => $v !== ''), $adminId);
        return app('json')->success('保存成功');
    }

    public function status($id)
    {
        $status = (int)$this->request->param('status', 0);
        $adminId = (int)($this->request->adminId() ?? 0);
        $this->repository->adminSetStatus((int)$id, $status, $adminId);
        return app('json')->success('操作成功');
    }

    public function delete($id)
    {
        $adminId = (int)($this->request->adminId() ?? 0);
        $this->repository->adminDelete((int)$id, $adminId);
        return app('json')->success('删除成功');
    }
}
