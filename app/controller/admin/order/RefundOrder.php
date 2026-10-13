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

namespace app\controller\admin\order;

use crmeb\basic\BaseController;
use app\common\repositories\store\order\StoreOrderStatusRepository;
use app\common\repositories\store\order\StoreRefundOrderRepository as repository;
use crmeb\services\ExcelService;
use think\App;

/**
 * 退款
 */
class RefundOrder extends BaseController
{
    protected $repository;

    public function __construct(App $app, repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }


    /**
     *  列表
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function lst($id)
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['date','nickname','uid','phone','real_name']);
        $where['mer_id'] = $id;
        $where['status'] = 3;
        return app('json')->success($this->repository->getAdminList($where, $page, $limit));
    }

    public function detail($id)
    {
        return app('json')->success($this->repository->getOne($id));
    }

    public function log($id)
    {
        list($page, $limit) = $this->getPage();
        $where = $this->request->params(['date', 'user_type']);
        $where['id'] = $id;
        $where['type'] = StoreOrderStatusRepository::TYPE_REFUND;
        $data = app()->make(StoreOrderStatusRepository::class)->search($where, $page, $limit);
        return app('json')->success($data);
    }

    /**
     *  备注
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function markForm($id)
    {
        if (!$this->repository->getWhereCount([$this->repository->getPk() => $id]))
            return app('json')->fail('数据不存在');
        return app('json')->success(formToData($this->repository->adminMarkForm($id)));
    }

    /**
     *  备注
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function mark($id)
    {
        if (!$this->repository->getWhereCount([$this->repository->getPk() => $id]))
            return app('json')->fail('数据不存在');
        $data = $this->request->params(['admin_mark']);
        $this->repository->update($id, $data);

        return app('json')->success('备注成功');
    }


    /**
     *  列表
     * @return \think\response\Json
     * @author Qinii
     */
    public function getAllList()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['refund_order_sn', 'status', 'refund_type', 'date', 'mer_id', 'order_sn', 'is_trader','uid','phone','real_name','nickname']);
        return app('json')->success($this->repository->getAllList($where, $page, $limit));
    }

    /**
     * 根据和解单ID获取和解记录列表
     *
     * 本函数用于根据提供的和解单ID，检索和解记录列表。它首先确定分页信息，然后构建查询条件，
     * 最后调用仓库层的方法获取和解记录，并以JSON格式返回结果。
     *
     * @param int $id 和解单的唯一标识符
     * @return \Illuminate\Http\JsonResponse 返回包含和解记录列表的JSON响应
     */
    public function reList($id)
    {
        // 获取当前请求的分页信息
        [$page, $limit] = $this->getPage();

        // 定义查询条件，筛选和解单ID和类型为1的记录
        $where = ['reconciliation_id' => $id, 'type' => 1];

        // 调用仓库层的reconList方法获取和解记录，并返回成功的JSON响应
        return app('json')->success($this->repository->reconList($where, $page, $limit));
    }

    /**
     *  导出
     * @return \think\response\Json
     * @author Qinii
     */
    public function excel()
    {
        $where = $this->request->params(['refund_order_sn', 'status', 'refund_type', 'date', 'order_sn', 'id', 'mer_id']);
        [$page, $limit] = $this->getPage();
        $data = app()->make(ExcelService::class)->refundOrder($where, $page, $limit);
        return app('json')->success($data);
    }

    /**
     * 平台审核退款单
     * @param $id
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function approve($id)
    {
        $data = $this->request->params(['status','platform_mark']);
        $this->repository->approve($id,$data);
        return app('json')->success('操作成功');
    }
}
