<?php

namespace app\controller\merchant\store\seckill;

use crmeb\basic\BaseController;
use app\common\repositories\store\StoreSeckillTimeRepository;
use think\App;
class SeckillTime extends BaseController
{
    protected  $repository;

    /**
     * SeckillActive constructor.
     * @param App $app
     * @param StoreSeckillTimeRepository $repository
     */
    public function __construct(App $app ,StoreSeckillTimeRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 获取秒杀场次列表
     * @return void
     * FerryZhao 2024/4/19
     */
    public function lst()
    {
        $activeId = $this->request->param('active_id') ?: null;
        return app('json')->success($this->repository->select($activeId));
    }
}