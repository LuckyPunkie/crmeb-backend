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
use app\validate\admin\CircleAgentValidate;
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
     * 获取代理列表
     *
     * @return void
     */
    public function list()
    {
        $where = $this->request->params(['type', 'name', 'phone', 'uid', 'user_phone', 'business_name', 'nickname', 'is_apply', 'status', ['create_time', []]]);
        [$page, $limit] = $this->getPage();

        return app('json')->success($this->getRepository()->getAgentList($page, $limit, $where, ['circle', 'admin', 'auditAdmin']));
    }
    /**
     * 获取代理详情
     *
     * @param int $id
     * @return void
     */
    public function detail($id)
    {
        if (!$id) {
            return app('json')->fail('参数错误');
        }

        $data = $this->getRepository()->getWith($id, ['circle', 'user', 'admin', 'auditAdmin'])
            ->append(['merchant_count', 'order_count', 'total_amount', 'frozen_amount']);
        if (!$data) {
            return app('json')->fail('数据不存在');
        }

        return app('json')->success($data);
    }
    /**
     * 添加代理
     *
     * @return void
     */
    public function create()
    {
        $data = $this->request->params(['type', 'name', 'phone', 'qualification', 'remark', 'uid', 'business_name', 'account', 'password', ['extend', []]]);

        $validate = $this->getValidate();
        if (!$validate->add($data)) {
            return app('json')->fail($validate->getError());
        }

        $res = $this->getRepository()->adminCreate($this->request->adminId(), $data);
        if (!$res) {
            return app('json')->fail('添加失败', $res);
        }

        return app('json')->success('添加成功');
    }
    /**
     * 修改代理
     *
     * @param int $id
     * @return void
     */
    public function update($id)
    {
        if (!$id) {
            return app('json')->fail('参数错误');
        }

        $data = $this->request->params(['type', 'name', 'phone', 'qualification', 'remark', 'uid', 'business_name', ['extend', []]]);

        $validate = $this->getValidate();
        if (!$validate->edit($data)) {
            return app('json')->fail($validate->getError());
        }

        $res = $this->getRepository()->adminUpdate($id, $this->request->adminId(), $data);
        if (!$res) {
            return app('json')->fail('修改失败', $res);
        }

        return app('json')->success('修改成功');
    }
    /**
     * 审核代理
     *
     * @param int $id
     * @return void
     */
    public function audit($id)
    {
        if (!$id) {
            return app('json')->fail('参数错误');
        }

        $data = $this->request->params(['status', 'audit_reason']);
        $validate = $this->getValidate();
        if (!$validate->audit($data)) {
            return app('json')->fail($validate->getError());
        }

        $res = $this->getRepository()->audit($id, $this->request->adminId(), $data);
        if (!$res) {
            return app('json')->fail('审核失败', $res);
        }
        return app('json')->success('审核成功');
    }
    /**
     * 删除代理
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
            return app('json')->fail('删除失败', $res);
        }
        return app('json')->success('删除成功');
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
        $where = $this->request->params(['keyword', 'circle_id']);

        return app('json')->success($this->getRepository()->associatedMerchantList($id, $page, $limit, $where));
    }
    /**
     * 获取结算方式
     *
     * @param int $id
     * @return void
     */
    public function getSettlementMethod($id)
    {
        if (!$id) {
            return app('json')->fail('参数错误');
        }

        return app('json')->success($this->getRepository()->getSettlementMethod($id));
    }
    /**
     * 设置结算方式
     *
     * @param int $id
     * @return void
     */
    public function setSettlementMethod($id)
    {
        if (!$id) {
            return app('json')->fail('参数错误');
        }

        $adminInfo = $this->request->adminInfo();
        if ($id != $adminInfo['circle_agent_id']) {
            return app('json')->fail('登录账号非商圈代理身份，无权操作');
        }

        $data = $this->request->params(['payment_method', 'payment_name', 'payment_account', 'payment_bank', 'payment_qr_img']);
        $validate = $this->getValidate();
        if (!$validate->setSettlementMethod($data)) {
            return app('json')->fail($validate->getError());
        }

        $res = $this->getRepository()->setSettlementMethod($id, $data);
        if (!$res) {
            return app('json')->fail('设置失败');
        }

        return app('json')->success('设置成功');
    }
    /**
     * 获取代理下拉列表
     *
     * @param int $type
     * @return void
     */
    public function options()
    {
        $type = $this->request->param('type', 0);
        if ($type == '') {
            return app('json')->fail('参数错误');
        }

        $res = $this->getRepository()->options($type);
        if (!$res) {
            return app('json')->fail('获取失败');
        }

        return app('json')->success($res);
    }

    /**
     * 重置密码
     *
     * @return void
     */
    public function resetPassword(int $id)
    {
        if (!$id) {
            return app('json')->fail('参数错误');
        }

        $res = $this->getRepository()->resetPassword($id);
        if (!$res) {
            return app('json')->fail('重置失败');
        }

        return app('json')->success('重置成功');
    }
}
