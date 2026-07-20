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

namespace app\controller\admin\analytics;

use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\analytics\ProductAnalyticsRepository;

class  StoreProduct extends BaseController
{
    #StoreOrderRepository
    public $repository;
    public function __construct(App $app, ProductAnalyticsRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }


    /**
     * 浏览量
     * 收藏量
     * 加购数
     * 下单数
     * 支付数
     * 退款数
     */
    public function top()
    {
        $date = $this->request->param('date','today');
        $date = $date ?: 'today';
        $data = $this->repository->top($date,$this->request->merId());
        return app('json')->success($data);
    }


    public function lineChart()
    {
        $date = $this->request->param('date','week');
        $date = $date ?: 'week';
        $data = $this->repository->lineChart($date,$this->request->merId());
        return app('json')->success($data);
    }

    public function typePieCahrt($type)
    {
        if ($type) {
            $data = $this->repository->catePieCahrt($this->request->merId());
        } else {
            $data = $this->repository->typePieCahrt($this->request->merId());
        }
        return app('json')->success($data);
    }

}
