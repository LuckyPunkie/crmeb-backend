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
use app\Request;
use crmeb\basic\BaseController;
use app\common\repositories\store\staff\StaffsRepository;
use app\common\repositories\store\order\StoreOrderRepository;

class ReservationService extends BaseController
{
    protected $storeOrderRepository;

    protected $staffsRepository;

    public function __construct(App $app, StoreOrderRepository $storeOrderRepository, StaffsRepository $staffsRepository)
    {
        parent::__construct($app);
        $this->storeOrderRepository = $storeOrderRepository;
        $this->staffsRepository = $staffsRepository;
    }

    /**
     * 预约日历列表
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function list(Request $request)
    {
        $params = $request->params([
            ['service_type', ''],
            ['reservation_keyword', ''],
            ['staff_id', ''],
            ['uid', ''],
            ['phone', ''],
            ['nickname', ''],
            ['reservation_date', ''],
            ['order_type', 4],
            ['reservation_status', []]
        ]);
        $params['reservation_date'] = $params['reservation_date'] ? $params['reservation_date'] : date('Y-m-d');

        return app('json')->success($this->storeOrderRepository->getStaffOrders($this->request->merId(), $params));
    }
}
