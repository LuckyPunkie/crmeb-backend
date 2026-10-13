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

namespace app\controller\admin\circle;

use think\App;
use crmeb\basic\BaseController;
use app\validate\admin\CircleValidate;
use app\common\repositories\circle\CircleRepository;

class Circle extends BaseController
{
    protected $repository;
    protected $validate;

    public function __construct(App $app, CircleRepository $repository, CircleValidate $validate)
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
        $where = $this->request->params(['circle_agent_id', 'name', 'status', ['type',1]]);
        [$page, $limit] = $this->getPage();
        $data = $this->getRepository()->getListCircle($where, $page, $limit, ['circleAgent']);
        return app('json')->success($data);
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

        $data = $this->getRepository()->getWith($id, ['circleAgent', 'merchant', 'parent', 'parent.parent']);
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
            ['pid', 0],
            'name',
            'circle_agent_id',
            'commission_type',
            'commission_rate',
            ['sort', 0],
            ['status', 1],
            'merchant_ids',
            'type',
            'role_id',
            'business_store_category',
            'business_store_type'
        ]);

        $validate = $this->getValidate();
        if (!$validate->add($data)) {
            return app('json')->fail($validate->getError());
        }

        $res = $this->getRepository()->createCircle($data);
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
            ['pid', 0],
            'name',
            'circle_agent_id',
            'commission_type',
            'commission_rate',
            ['sort', 0],
            ['status', 1],
            'merchant_ids',
            'type',
            'role_id',
            'business_store_category',
            'business_store_type',
        ]);

        $validate = $this->getValidate();
        if (!$validate->edit($data)) {
            return app('json')->fail($validate->getError());
        }

        $res = $this->getRepository()->updateCircle($id, $data);
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
    public function switch($id)
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
     * 获取关联商户列表
     *
     * @param int $id
     * @return void
     */
    public function associatedMerchantList($id)
    {
        if (!$id) {
            return app('json')->fail('参数错误');
        }

        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['keyword']);

        return app('json')->success($this->getRepository()->associatedMerchantListCircle($id, $page, $limit, $where));
    }
    /**
     * 获取组织选项
     *
     * @return void
     */
    public function options()
    {
        $where = $this->request->params([['type', 0], ['status', 1],['circle_agent_id', '']]);
        if (!$where['circle_agent_id']) {
            $adminInfo = $this->request->adminInfo();
            if($adminInfo['circle_agent_id']) {
                $where['circle_agent_id'] = $adminInfo['circle_agent_id'];
            }
        }

        $data = $this->getRepository()->search($where)->field('circle_id as value,pid,name as label')->select();
        return app('json')->success($data);
    }
}
