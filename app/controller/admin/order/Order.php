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
use app\common\repositories\store\order\StoreOrderRepository as repository;
use crmeb\services\ExcelService;
use think\App;

/**
 * 订单
 */
class Order extends BaseController
{
    protected $repository;

    public function __construct(App $app, repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     *  每个商户的订单列表
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     * @day 2024/5/10
     */
    public function lst($id)
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['date', 'order_sn', 'order_type', 'keywords', 'username', 'activity_type', 'group_order_sn', 'store_name', 'filter_delivery', 'filter_product', 'delivery_id','nickname','uid','phone','real_name']);
        $where['mer_id'] = $id;
        $where['is_spread'] = $this->request->param('is_spread', '');
        return app('json')->success($this->repository->adminMerGetList($where, $page, $limit));
    }

    /**
     *  平台对订单备注
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
     *  平台对订单备注
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
     *  订单统计
     * @return \think\response\Json
     * @author Qinii
     */
    public function title()
    {
        $where = $this->request->params(['type', 'date', 'mer_id', 'keywords', 'status', 'username', 'order_sn', 'is_trader', 'activity_type', 'filter_delivery', 'filter_product','nickname','uid','phone','real_name']);
        $where['is_spread'] = $this->request->param('is_spread', 0);
        return app('json')->success($this->repository->getStat($where, $where['status']));
    }

    /**
     * 订单列表
     * @return mixed
     * @author Qinii
     * @day 2020-06-25
     */
    public function getAllList()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['type', 'date', 'mer_id', 'keywords', 'status', 'username', 'order_sn', 'is_trader', 'activity_type', 'group_order_sn', 'store_name', 'spread_name', 'top_spread_name', 'filter_delivery', 'filter_product','nickname','uid','phone','real_name','delivery_name','delivery_phone']);
        $pay_type = $this->request->param('pay_type', '');
        if ($pay_type != '') $where['pay_type'] = $this->repository::PAY_TYPE_FILTEER[$pay_type];
        $where['is_spread'] = $this->request->param('is_spread', 0);
        $data = $this->repository->adminGetList($where, $page, $limit);
        return app('json')->success($data);
    }

    /**
     *  自提订单统计
     * @return \think\response\Json
     * @author Qinii
     */
    public function takeTitle()
    {
        $where = $this->request->params(['date', 'order_sn', 'keywords', 'username', 'is_trader']);
        $where['take_order'] = 1;
        $where['status'] = '';
        $where['verify_date'] = $where['date'];
        unset($where['date']);
        $pay_type = $this->request->param('pay_type','');
        if ($pay_type != '') $where['pay_type'] = $this->repository::PAY_TYPE_FILTEER[$pay_type];
        return app('json')->success($this->repository->getStat($where, ''));
    }

    /**
     *  自提订单列表
     * @return mixed
     * @author Qinii
     * @day 2020-08-17
     */
    public function getTakeList()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['date', 'order_sn', 'keywords', 'username', 'is_trader']);
        $where['take_order'] = 1;
        $where['status'] = '';
        $where['verify_date'] = $where['date'];
        unset($where['date']);
        $pay_type = $this->request->param('pay_type','');
        if ($pay_type != '') $where['pay_type'] = $this->repository::PAY_TYPE_FILTEER[$pay_type];
        return app('json')->success($this->repository->adminGetList($where, $page, $limit));
    }

    /**
     *
     * @return mixed
     * @author Qinii
     * @day 2020-08-17
     */
    public function chart()
    {
        $where = $this->request->params(['type', 'date', 'mer_id', 'keywords', 'username', 'order_sn', 'is_trader', 'activity_type', 'group_order_sn', 'store_name', 'spread_name', 'top_spread_name', 'filter_delivery', 'filter_product','nickname','uid','phone','real_name','delivery_name','delivery_phone']);
        $pay_type = $this->request->param('pay_type', '');
        if ($pay_type != '') $where['pay_type'] = $this->repository::PAY_TYPE_FILTEER[$pay_type];

        return app('json')->success($this->repository->OrderTitleNumber(null, null, $where));
    }

    /**
     *  分销订单头部统计
     * @return \think\response\Json
     * @author Qinii
     * @day 2023/7/7
     */
    public function spreadChart()
    {
        $where = $this->request->params(['type', 'date', 'mer_id', 'keywords', 'username', 'order_sn', 'is_trader', 'activity_type', 'group_order_sn', 'store_name', 'spread_name', 'top_spread_name', 'filter_delivery', 'filter_product','nickname','uid','phone','real_name','delivery_name','delivery_phone']);
        $pay_type = $this->request->param('pay_type', '');
        if ($pay_type != '') $where['pay_type'] = $this->repository::PAY_TYPE_FILTEER[$pay_type];

        return app('json')->success($this->repository->OrderTitleNumber(null, 2, $where));
    }

    /**
     *  自提订单头部统计
     * @return mixed
     * @author Qinii
     * @day 2020-08-17
     */
    public function takeChart()
    {
        $where = $this->request->params(['date', 'order_sn', 'keywords', 'username', 'is_trader']);
        $where['take_order'] = 1;
        $where['verify_date'] = $where['date'];
        unset($where['date']);
        $pay_type = $this->request->param('pay_type','');
        if ($pay_type != '') $where['pay_type'] = $this->repository::PAY_TYPE_FILTEER[$pay_type];

        return app('json')->success($this->repository->OrderTitleNumber(null, 1, $where));
    }

    /**
     *  订单类型
     * @return mixed
     * @author Qinii
     * @day 2020-08-15
     */
    public function orderType()
    {
        return app('json')->success($this->repository->orderType([]));
    }

    /**
     *  订单详情
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function detail($id)
    {
        $data = $this->repository->getOne($id, null);
        if (!$data)
            return app('json')->fail('数据不存在');
        return app('json')->success($data);
    }

    /**
     * 订单操作记录
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function status($id)
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['date', 'user_type']);
        $where['id'] = $id;
        return app('json')->success($this->repository->getOrderStatus($where, $page, $limit));
    }

    /**
     *  快递查询
     * @param $id
     * @return mixed
     * @author Qinii
     * @day 2020-06-25
     */
    public function express($id)
    {
        if (!$this->repository->getWhereCount(['order_id' => $id]))
            return app('json')->fail('订单信息或状态错误');
        return app('json')->success($this->repository->express($id, null));
    }

    public function reList($id)
    {
        [$page, $limit] = $this->getPage();
        $where = ['reconciliation_id' => $id, 'type' => 0];
        return app('json')->success($this->repository->reconList($where, $page, $limit));
    }

    /**
     *  导出文件
     * @author Qinii
     * @day 2020-07-30
     */
    public function excel()
    {
        $where = $this->request->params(['type', 'date', 'mer_id', 'keywords', 'status', 'username', 'order_sn', 'take_order', 'is_trader', 'activity_type', 'group_order_sn', 'store_name', 'filter_delivery', 'filter_product', 'pay_type','uid','phone','real_name','delivery_name','delivery_phone']);
        if ($where['pay_type'] != '') $where['pay_type'] = $this->repository::PAY_TYPE_FILTEER[$where['pay_type']];
        if ($where['take_order']) {
            $where['verify_date'] = $where['date'];
            unset($where['date']);
        }
        [$page, $limit] = $this->getPage();
        $data = app()->make(ExcelService::class)->order($where, $page, $limit);
        return app('json')->success($data);
    }

    /**
     *  拆分后自订单
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     * @day 2023/2/22
     */
    public function childrenList($id)
    {
        $data = $this->repository->childrenList($id, 0);
        return app('json')->success($data);
    }
}
