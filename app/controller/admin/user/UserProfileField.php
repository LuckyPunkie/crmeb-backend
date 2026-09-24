<?php

namespace app\controller\admin\user;

use app\common\repositories\user\UserProfileFieldRepository;
use crmeb\basic\BaseController;
use think\App;

/**
 * 交友资料字段配置
 */
class UserProfileField extends BaseController
{
    protected $repository;

    public function __construct(App $app, UserProfileFieldRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    public function lst()
    {
        $where = $this->request->params([
            ['is_show', ''],
            ['status', ''],
        ]);
        return app('json')->success($this->repository->lst($where));
    }

    public function create()
    {
        $data = $this->request->params([
            'field_key',
            'title',
            'icon',
            'type',
            ['options', []],
            'placeholder',
            ['is_show', 1],
            ['status', 1],
            ['sort', 100],
            ['bind_column', ''],
        ]);
        $this->repository->create($data);
        return app('json')->success('创建成功');
    }

    public function update($id)
    {
        $data = $this->request->params([
            'field_key',
            'title',
            'icon',
            'type',
            ['options', []],
            'placeholder',
            ['is_show', 1],
            ['status', 1],
            ['sort', 100],
            ['bind_column', ''],
        ]);
        $this->repository->update((int)$id, $data);
        return app('json')->success('保存成功');
    }

    public function delete($id)
    {
        $this->repository->delete((int)$id);
        return app('json')->success('删除成功');
    }

    public function setShow($id)
    {
        $isShow = (int)$this->request->param('is_show', 1);
        $this->repository->setShow((int)$id, $isShow);
        return app('json')->success('操作成功');
    }

    public function setStatus($id)
    {
        $status = (int)$this->request->param('status', 1);
        $this->repository->setStatus((int)$id, $status);
        return app('json')->success($status ? '已上架' : '已下架');
    }

    public function saveSort()
    {
        $ids = $this->request->param('ids', []);
        if (!is_array($ids)) {
            $ids = [];
        }
        $this->repository->saveSort($ids);
        return app('json')->success('排序已保存');
    }
}
