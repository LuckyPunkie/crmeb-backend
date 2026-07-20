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
use app\validate\admin\ActivityCateValidate;
use app\common\repositories\store\StoreActivityCateRepository;
use app\common\repositories\store\StoreActivityLabelRepository;

class StoreActivityCate extends BaseController
{
    protected $repository;

    public function __construct(App $app, StoreActivityCateRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 分类列表
     * @return \think\response\Json
     * @author Qinii
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where = ['type' => 0];
        return app('json')->success($this->repository->getList($where, $page, $limit));
    }

    public function select()
    {
        return app('json')->success($this->repository->select());
    }

    /**
     * 创建表单
     * @return \think\response\Json
     * @author Qinii
     */
    public function createForm()
    {
        return app('json')->success(formToData($this->repository->form(0)));
    }

    /**
     * 创建
     * @return \think\response\Json
     * @author Qinii
     */
    public function create()
    {
        $data = $this->checkParams();
        $this->repository->create($data);
        return app('json')->success('创建成功');
    }

    /**
     * 修改表单
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function updateForm($id)
    {
        return app('json')->success(formToData($this->repository->form($id)));
    }

    /**
     * 修改
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function update($id)
    {
        $data = $this->checkParams();
        $this->repository->update($id, $data);
        return app('json')->success('编辑成功');
    }

    public function delete($id)
    {
        $count = app()->make(StoreActivityLabelRepository::class)->getSearch(['label_cate' => $id])->count();
        if($count){
            return app('json')->fail('该分类下有标签，不能删除分类！');
        }

        $this->repository->delete($id);
        return app('json')->success('删除成功');
    }

    public function checkParams()
    {
        $data = $this->request->params([
            'name',
            'pic',
            'status',
            'sort'
        ]);
        app()->make(ActivityCateValidate::class)->check($data);
        return $data;
    }
}
