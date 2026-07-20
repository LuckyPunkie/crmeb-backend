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
use app\common\repositories\analytics\UserAnalyticsRepository;

class  User extends BaseController
{
    #StoreOrderRepository
    public $repository;
    public function __construct(App $app, UserAnalyticsRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }


    /**
     * 用户数量
     * 新增用户
     * 下单用户
     * 活跃用户
     * 付费会员
     * 新增付费会员
     */
    public function top()
    {
        $date = $this->request->param('date','today');
        $date = $date ?: 'today';
        $data = $this->repository->top($date);
        return app('json')->success($data);
    }


    public function lineChart()
    {
        /**
         * 新增用户数量
         * 活跃用户数量
         * 新增付费会员
         */
        $type = $this->request->param('type',0);
        $date = $this->request->param('date','week');
        $date = $date ?: 'week';
        $data = $this->repository->lineChart($type, $date);
        return app('json')->success($data);
    }

    public function typePieCahrt()
    {
        $date = $this->request->param('date','week');
        $date = $date ?: 'week';
        $data = $this->repository->typePieCahrt($date);
        return app('json')->success($data);
    }
}
