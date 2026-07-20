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

namespace app\controller\admin\system\merchant;

use think\App;
use crmeb\basic\BaseController;
use app\validate\admin\StoreGroupValidate;
use app\common\repositories\system\merchant\StoreGroupRepository;

class StoreGroup extends BaseController
{
    protected $repository;
    protected $validate;

    public function __construct(App $app, StoreGroupRepository $repository, StoreGroupValidate $validate)
    {
        parent::__construct($app);
        $this->repository = $repository;
        $this->validate = $validate;
    }

    protected function getRepository()
    {
        return $this->repository;
    }

    protected function getValidate()
    {
        return $this->validate;
    }
    /**
     * 获取组织列表
     *
     * @return void
     */
    public function list()
    {
        $where = $this->request->params(['name', 'status']);

        return app('json')->success($this->getRepository()->getList($where));
    }
    /**
     * 获取组织详情
     *
     * @param int $id
     * @return void
     */
    public function detail($id)
    {
        if (!$id) {
            return app('json')->fail('参数错误');
        }

        $data = $this->getRepository()->getWith($id, ['parent', 'parent.parent'])->append(['merchant', 'merchant_count']);

        if (!$data) {
            return app('json')->fail('数据不存在');
        }

        return app('json')->success($data);
    }
    /**
     * 创建组织
     *
     * @return void
     */
    public function create()
    {
        $data = $this->request->params([
            'pid',
            'name',
            ['sort', 0],
            ['status', 1],
            ['positioning_status', 0],
            'longitude',
            'latitude',
            'merchant_ids',
            'address'
        ]);

        $validate = $this->getValidate();
        if (!$validate->add($data)) {
            return app('json')->fail($validate->getError());
        }

        $res = $this->getRepository()->create($data);
        if (!$res) {
            return app('json')->fail('添加失败', $res);
        }

        return app('json')->success('添加成功', $res);
    }
    /**
     * 更新组织
     *
     * @param int $id
     * @return void
     */
    public function update($id)
    {
        if (!$id) {
            return app('json')->fail('参数错误');
        }

        $data = $this->request->params([
            'pid',
            'name',
            ['sort', 0],
            ['status', 1],
            ['positioning_status', 0],
            'longitude',
            'latitude',
            'merchant_ids',
            'address'
        ]);

        $validate = $this->getValidate();
        if (!$validate->edit($data)) {
            return app('json')->fail($validate->getError());
        }

        $res = $this->getRepository()->update($id, $data);
        if (!$res) {
            return app('json')->fail('修改失败', $res);
        }

        return app('json')->success('修改成功');
    }
    /**
     * 删除组织
     *
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        if (!$id) {
            return app('json')->fail('参数错误');
        }

        $res = $this->getRepository()->delete($id);
        if (!$res) {
            return app('json')->fail('删除失败');
        }

        return app('json')->success('删除成功');
    }
    /**
     * 切换组织状态
     *
     * @param int $id
     * @return void
     */
    public function switchStatus($id)
    {
        if (!$id) {
            return app('json')->fail('参数错误');
        }

        $data = $this->request->params(['status']);

        $validate = $this->getValidate();
        if (!$validate->switch($data)) {
            return app('json')->fail($validate->getError());
        }

        $res = $this->getRepository()->switch($id, $data);
        if (!$res) {
            return app('json')->fail('切换失败');
        }

        return app('json')->success('切换成功');
    }
    /**
     * 设置组织模板
     *
     * @param int $id
     * @return void
     */
    public function setTemplate($id)
    {
        $data = $this->request->params([
            'diy_temp_id'
        ]);

        if (!$data['diy_temp_id']) {
            return app('json')->fail('参数错误');
        }

        $res = $this->getRepository()->setTemplate($id, $data);
        if (!$res) {
            return app('json')->fail('设置失败');
        }

        return app('json')->success('设置成功');
    }
    /**
     * 获取关联商户列表
     *
     * @param int $id
     * @return void
     */
    public function stores($id)
    {
        if (!$id) {
            return app('json')->fail('参数错误');
        }

        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['keyword']);

        return app('json')->success($this->getRepository()->stores($id, $page, $limit, $where));
    }

    public function options()
    {
        $data = $this->getRepository()->getAllOptions();
        $data = formatCascaderData($data, 'name');

        return app('json')->success($data);
    }
}
