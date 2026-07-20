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
namespace app\controller\admin\system\serve;

use app\common\repositories\system\serve\ServeMealRepository;
use app\validate\admin\MealValidata;
use crmeb\basic\BaseController;
use think\App;


/**
 * 一号通套餐
 */
class Config extends BaseController
{
    protected $repository;

    public function __construct(App $app, ServeMealRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 平台配置 商户购买套餐列表
     * @return mixed
     * @author Qinii
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['status', 'type']);
        $data = $this->repository->getList($where, $page, $limit);
        return app('json')->success($data);
    }

    /**
     * 添加套餐
     * @return mixed
     * @author Qinii
     */
    public function createForm()
    {
        return app('json')->success(formToData($this->repository->form()));
    }

    /**
     * 添加套餐
     * @return mixed
     * @author Qinii
     */
    public function create(MealValidata $validata)
    {
        $data = $this->request->params(['name', 'price', 'num', 'type', 'status', 'sort']);
        $validata->scene('create')->check($data);

        $this->repository->create($data);

        return app('json')->success('添加成功');
    }

    /**
     * 套餐详情
     * @param $id
     * @return mixed
     * @author Qinii
     */
    public function detail($id)
    {
        $data = $this->repository->get($id);
        if (!$data) return app('json')->fail('数据不存在');
        return app('json')->success($data);
    }

    /**
     * 修改套餐
     * @param $id
     * @return mixed
     * @author Qinii
     */
    public function updateForm($id)
    {
        return app('json')->success(formToData($this->repository->updateForm($id)));
    }

    /**
     * 修改套餐
     * @param $id
     * @return mixed
     * @author Qinii
     */
    public function update($id, MealValidata $validata)
    {
        $data = $this->request->params(['name', 'price', 'num', 'type', 'status', 'sort']);
        $validata->scene('create')->check($data);

        $this->repository->update($id, $data);

        return app('json')->success('编辑成功');
    }

    /**
     *  删除套餐
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function detele($id)
    {
        $this->repository->delete($id);
        return app('json')->success('删除成功');
    }

    /**
     * 套餐状态
     * @param $id
     * @return mixed
     * @author Qinii
     */
    public function switchStatus($id)
    {
        $status = $this->request->param('status', 1) == 1 ?: 0;
        $this->repository->update($id, ['status' => $status]);
        return app('json')->success('修改成功');
    }
}
