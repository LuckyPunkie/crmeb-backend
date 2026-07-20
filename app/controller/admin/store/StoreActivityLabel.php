<?php

// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016-2026 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

namespace app\controller\admin\store;

use think\App;
use crmeb\basic\BaseController;
use app\validate\admin\ActivityLabelValidate;
use app\common\repositories\store\StoreActivityCateRepository;
use app\common\repositories\store\StoreActivityLabelRepository;

class StoreActivityLabel extends BaseController
{
    protected $repository;

    public function __construct(App $app, StoreActivityLabelRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['label_cate']);
        return app('json')->success($this->repository->getList($where, $page, $limit));
    }

    public function create()
    {
        $data = $this->checkParams();
        $this->repository->create($data);
        return app('json')->success('添加成功');
    }

    public function update($id)
    {
        $data = $this->checkParams();
        $this->repository->update($id,$data);
        return app('json')->success('编辑成功');
    }

    public function detail($id)
    {
        $data = $this->repository->detail($id);
        return app('json')->success($data);
    }

    public function delete($id)
    {
        $data = $this->repository->delete($id);
        return app('json')->success('删除成功');
    }


    public function status($id)
    {
        $status = $this->request->param('status', 0) == 1 ? 1 : 0;
        $type = $this->request->param('type', 'is_show') == 'is_show' ? 'is_show' : 'status';
        $this->repository->update($id, [$type => $status]);
        return app('json')->success('编辑成功');
    }

    public function checkParams()
    {
        $data = $this->request->params([
            'type',
            'label_cate',
            'label_name',
            'style_type',
            'color',
            'bg_color',
            'border_color',
            'icon',
            'is_show',
            'status',
            'sort',
        ]);
        app()->make(ActivityLabelValidate::class)->check($data);
        return $data;
    }

    public function options(StoreActivityCateRepository $repository)
    {
        $where = ['status' => 1];
        return app('json')->success($repository->options($where));
    }

}
