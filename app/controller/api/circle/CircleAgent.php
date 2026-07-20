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

namespace app\controller\api\circle;

use think\App;
use crmeb\basic\BaseController;
use app\validate\api\CircleAgentValidate;
use app\common\repositories\circle\CircleAgentRepository;

class CircleAgent extends BaseController
{
    protected $repository;
    protected $validate;

    public function __construct(App $app, CircleAgentRepository $repository, CircleAgentValidate $validate)
    {
        parent::__construct($app);
        $this->repository = $repository;
        $this->validate = $validate;

        if (!$this->request->uid()) {
            app('json')->fail('请先登录');
        }
    }

    protected function getRepository()
    {
        return $this->repository;
    }

    protected function getValidate()
    {
        return $this->validate;
    }

    public function list()
    {
        [$page, $limit] = $this->getPage();
        $where['uid'] = $this->request->uid();
        $where['type'] = $this->request->param('type', 0);

        return app('json')->success($this->getRepository()->getAgentList($page, $limit, $where));
    }

    public function detail($id)
    {
        if (!$id) {
            return app('json')->fail('参数错误');
        }

        $data = $this->getRepository()->getWhere(['circle_agent_id' => $id, 'uid' => $this->request->uid()]);
        if (!$data) {
            return app('json')->fail('数据不存在');
        }

        return app('json')->success($data);
    }

    public function create()
    {
        $data = $this->request->params(['name', 'phone', 'qualification', 'remark', 'type', 'business_name','business_store_category','business_store_type', ['extend', []]]);

        $validate = $this->getValidate();
        if (!$validate->add($data)) {
            return app('json')->fail($validate->getError());
        }

        $res = $this->getRepository()->createAgent($this->request->uid(), $data);
        if (!$res) {
            return app('json')->fail('添加失败', $res);
        }

        return app('json')->success('添加成功', $res);
    }

    public function update($id)
    {
        if (!$id) {
            return app('json')->fail('参数错误');
        }

        $data = $this->request->params(['name', 'phone', 'qualification', 'remark', 'type', 'business_name','business_store_category','business_store_type', ['extend', []]]);

        $validate = $this->getValidate();
        if (!$validate->edit($data)) {
            return app('json')->fail($validate->getError());
        }

        $res = $this->getRepository()->updateAgent($id, $this->request->uid(), $data);
        if (!$res) {
            return app('json')->fail('修改失败', $res);
        }

        return app('json')->success('修改成功');
    }

    public function revoke($id)
    {
        if (!$id) {
            return app('json')->fail('参数错误');
        }

        $res = $this->getRepository()->revokeAgent($id, $this->request->uid());
        if (!$res) {
            return app('json')->fail('撤销失败', $res);
        }
        return app('json')->success('撤销成功');
    }
}
