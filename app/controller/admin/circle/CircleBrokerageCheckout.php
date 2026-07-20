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
use think\exception\ValidateException;
use app\common\repositories\circle\CircleAgentRepository;
use app\common\repositories\circle\CircleBrokerageCheckoutRepository;
use app\validate\admin\CircleBrokerageCheckoutValidate;

class CircleBrokerageCheckout extends BaseController
{
    protected $repository;
    protected $validate;

    public function __construct(
        App $app,
        CircleBrokerageCheckoutRepository $repository,
        CircleBrokerageCheckoutValidate $validate
    ) {
        parent::__construct($app);
        $this->repository = $repository;
        $this->validate = $validate;
    }

    public function getRepository()
    {
        return $this->repository;
    }

    public function getValidate()
    {
        return $this->validate;
    }

    public function list()
    {
        $where = $this->request->params(['agent_id', 'agent_phone', 'create_time', 'audit_status', 'status', 'withdrawal_type', 'withdrawal_sn']);
        [$page, $limit] = $this->getPage();

        return app('json')->success($this->getRepository()->getList($page, $limit, $where));
    }

    public function detail($id)
    {
        if (!$id) {
            return app('json')->fail('参数错误');
        }

        $data = $this->getRepository()->getWith($id, ['agent', 'agent.user', 'admin']);
        if (!$data) {
            return app('json')->fail('数据不存在');
        }

        return app('json')->success($data);
    }
    /**
     * 商圈提交结算get
     *
     * @return void
     */
    public function create()
    {
        $agentId = $this->request->param('agent_id');
        if (!$agentId) {
            return app('json')->fail('参数错误');
        }
        $data = app()->make(CircleAgentRepository::class)->getWith($agentId)->append(['frozen_amount'])->toArray();
        if (!$data) {
            return app('json')->fail('数据不存在');
        }
        // if(!$data['payment_name'] && !$data['payment_account']) {
        //     throw new ValidateException('未设置结算方式，无法申请结算');
        // }
        $data['total_amount'] = bcadd($data['frozen_amount'], $data['balance'], 2);

        return app('json')->success($data);
    }
    /**
     * 商圈提交结算申请
     *
     * @return void
     */
    public function save()
    {
        $data = $this->request->params(['agent_id', 'withdrawal_amount', 'withdrawal_type']);
        $validate = $this->getValidate();
        if (!$validate->add($data)) {
            return app('json')->fail($validate->getError());
        }

        if (!$this->isCircleAgent()) {
            return app('json')->fail('不是商圈账号，无法操作');
        }

        $res = $this->getRepository()->create($data);
        if (!$res) {
            return app('json')->fail('添加申请失败', $res);
        }

        return app('json')->success('添加申请成功');
    }
    /**
     * 商圈撤销结算申请
     *
     * @param integer $id
     * @return void
     */
    public function revoke(int $id)
    {
        if (!$id) {
            return app('json')->fail('参数错误');
        }

        if (!$this->isCircleAgent()) {
            return app('json')->fail('不是商圈账号，无法操作');
        }

        $res = $this->getRepository()->revoke($id);
        if (!$res) {
            return app('json')->fail('撤销失败', $res);
        }

        return app('json')->success('撤销成功');
    }
    /**
     * 商圈备注
     *
     * @param integer $id
     * @return void
     */
    public function remark(int $id)
    {
        if (!$id) {
            return app('json')->fail('参数错误');
        }

        if (!$this->isCircleAgent()) {
            return app('json')->fail('不是商圈账号，无法操作');
        }

        $data = $this->request->params([['remark', '']]);
        if (!isset($data['remark']) || $data['remark'] == '') {
            return app('json')->fail('备注不能为空');
        }

        $res = $this->getRepository()->remark($id, $data);
        if (!$res) {
            return app('json')->fail('备注失败', $res);
        }

        return app('json')->success('备注成功');
    }
    /**
     * 平台审核
     * audit_status 0待审核 1审核通过 -1审核拒绝 -2撤销
     *
     * @param integer $id
     * @return void
     */
    public function audit(int $id)
    {
        if (!$id) {
            return app('json')->fail('参数错误');
        }

        $data = $this->request->params(['audit_status', 'audit_reason']);
        $validate = $this->getValidate();
        if (!$validate->audit($data)) {
            return app('json')->fail($validate->getError());
        }

        if ($this->isCircleAgent()) {
            return app('json')->fail('不是平台账号，无法操作');
        }

        $res = $this->getRepository()->auditBrokerageCheckout($id, $this->request->adminId(), $data);
        if (!$res) {
            return app('json')->fail('审核失败', $res);
        }
        return app('json')->success('审核成功');
    }
    /**
     * 平台转账
     *
     * @param integer $id
     * @return void
     */
    public function transfer(int $id)
    {
        if (!$id) {
            return app('json')->fail('参数错误');
        }

        $data = $this->request->params(['transfer_voucher', 'transfer_remark']);
        $validate = $this->getValidate();
        if (!$validate->transfer($data)) {
            return app('json')->fail($validate->getError());
        }
        if ($this->isCircleAgent()) {
            return app('json')->fail('不是平台账号，无法操作');
        }

        $res = $this->getRepository()->transfer($id, $data);
        if (!$res) {
            return app('json')->fail('转账失败', $res);
        }
        return app('json')->success('转账成功');
    }
    /**
     * 平台备注
     *
     * @param integer $id
     * @return void
     */
    public function platformRemark(int $id)
    {
        if (!$id) {
            return app('json')->fail('参数错误');
        }

        if ($this->isCircleAgent()) {
            return app('json')->fail('不是商圈账号，无法操作');
        }

        $data = $this->request->params([['platform_remark', '']]);
        if (!isset($data['platform_remark']) || $data['platform_remark'] == '') {
            return app('json')->fail('备注不能为空');
        }

        $res = $this->getRepository()->platformRemark($id, $data);
        if (!$res) {
            return app('json')->fail('备注失败', $res);
        }
        return app('json')->success('备注成功');
    }

    protected function isCircleAgent()
    {
        return $this->request->isAgent() == 1;
    }
}
