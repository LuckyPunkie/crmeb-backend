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

namespace app\controller\merchant\store\order;

use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\store\order\MerchantReconciliationRepository as repository;

class Reconciliation extends BaseController
{
    protected $repository;

    public function __construct(App $app,repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }


    /**
     * 获取列表数据
     *
     * @return \think\response\Json
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['date', 'status', 'is_accounts', 'reconciliation_id', 'keyword']);
        // 添加商家ID条件
        $where['mer_id'] = $this->request->merId();
        return app('json')->success($this->repository->getList($where, $page, $limit));
    }


    /**
     * 确认订单
     * @param $id
     * @return mixed
     * @author Qinii
     * @day 2020-06-15
     */
    public function switchStatus($id)
    {
        if(!$this->repository->merWhereCountById($id,$this->request->merId()))
            return app('json')->fail('数据不存在或状态错误');
        $status = ($this->request->param('status') == 1) ? 1 : 2;
        $data['status'] = $status;
        $data['mer_admin_id'] = $this->request->merId();
        $this->repository->switchStatus($id,$data);
        return app('json')->success('修改成功');
    }


    /**
     * 标记表单
     *
     * @param int $id 表单ID
     * @return \think\response\Json
     */
    public function markForm($id)
    {
        if (!$this->repository->getWhereCount([$this->repository->getPk() => $id, 'mer_id' => $this->request->merId()]))
            return app('json')->fail('数据不存在');
        // 返回标记后的表单数据
        return app('json')->success(formToData($this->repository->markForm($id)));
    }

    /**
     * 标记
     *
     * @param int $id 表单ID
     * @return \think\response\Json
     */
    public function mark($id)
    {
        if (!$this->repository->getWhereCount([$this->repository->getPk() => $id, 'mer_id' => $this->request->merId()]))
            return app('json')->fail('数据不存在');
        // 获取备注信息
        $data = $this->request->params(['mark']);
        $this->repository->update($id, $data);
        // 返回成功信息
        return app('json')->success('备注成功');
    }

}
