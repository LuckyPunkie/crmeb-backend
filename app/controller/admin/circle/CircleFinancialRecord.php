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
use app\common\repositories\circle\CircleFinancialRecordRepository;

class CircleFinancialRecord extends BaseController
{
    protected $repository;

    public function __construct(App $app, CircleFinancialRecordRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    protected function getRepository()
    {
        return $this->repository;
    }

    public function list()
    {
        $where = $this->request->params(['circle_id', 'mer_name', 'agent_id', 'order_time', 'order_sn', 'order_id', 'order_status']);
        [$page, $limit] = $this->getPage();

        return app('json')->success($this->getRepository()->getListFinancial($page, $limit, $where));
    }
}
