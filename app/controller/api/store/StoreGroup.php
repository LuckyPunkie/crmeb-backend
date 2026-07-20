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

namespace app\controller\api\store;

use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\system\merchant\StoreGroupRepository;

class StoreGroup extends BaseController
{
    protected $repository;

    public function __construct(App $app, StoreGroupRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    protected function getRepository()
    {
        return $this->repository;
    }

    public function recommendList()
    {
        $params = $this->request->params(['latitude', 'longitude']);
        // 如果没有定位坐标则返回空数组
        if (empty($params['latitude']) || empty($params['longitude'])) {
            return app('json')->success([]);
        }

        return app('json')->success($this->getRepository()->recommendList($params));
    }

    public function options()
    {
        $data = $this->getRepository()->getAllOptions();
        $data = formatCascaderData($data, 'name');

        return app('json')->success($data);
    }
}
