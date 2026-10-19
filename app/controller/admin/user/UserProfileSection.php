<?php

namespace app\controller\admin\user;

use app\common\repositories\user\UserProfileSectionRepository;
use crmeb\basic\BaseController;
use think\App;

/**
 * 交友资料版块配置
 */
class UserProfileSection extends BaseController
{
    protected $repository;

    public function __construct(App $app, UserProfileSectionRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    public function lst()
    {
        return app('json')->success($this->repository->lst());
    }

    public function create()
    {
        $data = $this->request->params(['title', ['is_show', 1]]);
        $this->repository->create($data);
        return app('json')->success('创建成功');
    }

    public function update($id)
    {
        $data = $this->request->params(['title', ['is_show', ''], ['items', null]]);
        $this->repository->update((int)$id, $data);
        return app('json')->success('保存成功');
    }

    public function delete($id)
    {
        $this->repository->delete((int)$id);
        return app('json')->success('删除成功');
    }

    public function saveSort()
    {
        $ids = $this->request->param('ids', []);
        $this->repository->saveSort(is_array($ids) ? $ids : []);
        return app('json')->success('排序已保存');
    }
}
