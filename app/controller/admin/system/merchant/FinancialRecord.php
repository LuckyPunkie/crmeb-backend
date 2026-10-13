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


use app\common\repositories\store\ExcelRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\system\merchant\FinancialRecordRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use crmeb\basic\BaseController;
use crmeb\services\ExcelService;
use think\App;

/**
 * 资金流水
 */
class FinancialRecord extends BaseController
{
    protected $repository;

    public function __construct(App $app, FinancialRecordRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 列表
     * @return \think\response\Json
     * @author Qinii
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['keyword', 'date', 'mer_id','uid','real_name','nickname','order_sn']);
        $pay_type = $this->request->param('pay_type', '');
        if ($pay_type != '') $where['pay_type'] = StoreOrderRepository::PAY_TYPE_FILTEER[$pay_type];
        $merId = $this->request->merId();
        if ($merId) {
            $where['mer_id'] = $merId;
            $where['financial_type'] = ['order', 'mer_accoubts', 'brokerage_one', 'brokerage_two', 'refund_brokerage_one', 'refund_brokerage_two', 'refund_order', 'order_platform_coupon', 'order_svip_coupon', 'svip'];
        } else {
            $where['financial_type'] = ['order', 'sys_accoubts', 'brokerage_one', 'brokerage_two', 'refund_brokerage_one', 'refund_brokerage_two', 'refund_order', 'order_platform_coupon', 'order_svip_coupon', 'svip'];
        }
        return app('json')->success($this->repository->getList($where, $page, $limit));
    }

    /**
     * 导出
     * @return \think\response\Json
     * @author Qinii
     * @day 3/23/21
     */
    public function export()
    {
        $where = $this->request->params(['keyword', 'date', 'mer_id']);
        $merId = $this->request->merId();
        if ($merId) {
            $where['mer_id'] = $merId;
            $where['financial_type'] = ['order', 'mer_accoubts', 'brokerage_one', 'brokerage_two', 'refund_brokerage_one', 'refund_brokerage_two', 'refund_order', 'order_platform_coupon', 'order_svip_coupon', 'svip'];
        } else {
            $where['financial_type'] = ['order', 'sys_accoubts', 'brokerage_one', 'brokerage_two', 'refund_brokerage_one', 'refund_brokerage_two', 'refund_order', 'order_platform_coupon', 'order_svip_coupon', 'svip'];
        }

        [$page, $limit] = $this->getPage();
        $data = app()->make(ExcelService::class)->financial($where, $page, $limit);
        return app('json')->success($data);
    }


    /**
     *  账单头部统计
     * @return \think\response\Json
     * @author Qinii
     * @day 3/23/21
     */
    public function getTitle()
    {
        $where = $this->request->params(['date']);
        $where['is_mer'] = $this->request->merId() ?? 0;
        if ($where['is_mer'] == 0) {
            $data = $this->repository->getAdminTitle($where);
        } else {
            $data = $this->repository->getMerchantTitle($where);
        }
        return app('json')->success($data);
    }


    /**
     *  账单管理列表
     * @return \think\response\Json
     * @author Qinii
     * @day 3/23/21
     */
    public function getList()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params([['type', 1], 'date']);
        $where['is_mer'] = $this->request->merId() ?? 0;
        $data = $this->repository->getAdminList($where, $page, $limit);
        return app('json')->success($data);
    }


    /**
     *  详情
     * @param $type
     * @return \think\response\Json
     * @author Qinii
     * @day 3/23/21
     */
    public function detail($type)
    {
        $date = $this->request->param('date');
        $where['date'] = empty($date) ? date('Y-m-d', time()) : $date;
        $where['is_mer'] = $this->request->merId() ?? 0;
        if ($this->request->merId()) {
            $data = $this->repository->merDetail($type, $where);
        } else {
            $data = $this->repository->adminDetail($type, $where);
        }

        return app('json')->success($data);
    }

    /**
     * 导出账单详情 - 下载账单
     * @param $type
     * @author Qinii
     * @day 3/25/21
     */
    public function exportDetail($type)
    {
        [$page, $limit] = $this->getPage();
        $date = $this->request->param('date');
        $where['date'] = empty($date) ? date('Y-m-d', time()) : $date;
        $where['type'] = $type;
        $where['is_mer'] = $this->request->merId() ?? 0;
        $data = app()->make(ExcelService::class)->exportFinancial($where, $page, $limit);
//        app()->make(ExcelRepository::class)->create($where, $this->request->adminId(), 'exportFinancial',$where['is_mer']);
        return app('json')->success($data);
    }

    /**
     *  流水统计
     * @return \think\response\Json
     * @author Qinii
     * @day 5/7/21
     */
    public function title()
    {
        $where = $this->request->params(['date']);

//        $data = $this->repository->getFiniancialTitle($this->request->merId(),$where);
        $data = [];
        return app('json')->success($data);
    }

    /**
     *  平台财务查看每个商户的财务信息列表
     * @return \think\response\Json
     * @author Qinii
     * @day 2023/10/18
     *
     */
    public function merchantFinancial()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['mer_id']);
        $where['is_del'] = 0;
        $data = $this->repository->merchantFinancial($where, $page, $limit);
        return app('json')->success($data);
    }

    /**
     *  财务记录
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     * @day 2023/10/18
     */
    public function merAcountsList($id)
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params([['type', 1], 'date']);
        $where['is_mer'] = $id;
        $data = $this->repository->getAdminList($where, $page, $limit);
        return app('json')->success($data);
    }

    /**
     * 头部统计
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     * @day 2023/10/18
     */
    public function merAcountsTitle($id)
    {
        $where = $this->request->params(['date']);
        $where['is_mer'] = $id;
        $data = $this->repository->getMerchantTitle($where);
        return app('json')->success($data);
    }

    /**
     *  详情
     * @param $type
     * @return \think\response\Json
     * @author Qinii
     * @day 2023/10/18
     */
    public function merDetail($type)
    {
        $date = $this->request->param('date');
        $where['date'] = empty($date) ? date('Y-m-d', time()) : $date;
        $where['is_mer'] = $this->request->param('mer_id');
        if (!$where['is_mer']) return app('json')->fail('请选择商户');
        $data = $this->repository->merDetail($type, $where);
        return app('json')->success($data);
    }

    /**
     *  导出
     * @param $type
     * @return \think\response\Json
     * @author Qinii
     * @day 2023/10/18
     */
    public function merExportDetail($type)
    {
        [$page, $limit] = $this->getPage();
        $date = $this->request->param('date');
        $where['date'] = empty($date) ? date('Y-m-d', time()) : $date;
        $where['type'] = $type;
        $where['is_mer'] = $this->request->param('mer_id');
        if (!$where['is_mer']) return app('json')->fail('请选择商户');
        $data = app()->make(ExcelService::class)->exportFinancial($where, $page, $limit);
        return app('json')->success($data);
    }
}
