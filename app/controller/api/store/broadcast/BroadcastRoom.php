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


namespace app\controller\api\store\broadcast;


use app\common\repositories\store\broadcast\BroadcastRoomRepository;
use crmeb\basic\BaseController;
use think\App;

class BroadcastRoom extends BaseController
{
    /**
     * @var BroadcastRoomRepository
     */
    protected $repository;

    public function __construct(App $app, BroadcastRoomRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 直播间列表
     * @return \think\response\Json
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/10
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->repository->userList([], $page, $limit));
    }

    /**
     * 热门直播间
     * @return \think\response\Json
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/10
     */
    public function hot()
    {
        [$page, $limit] = $this->getPage();
        $where = ['hot' => 1];
        $where['mer_id'] = $this->request->param('mer_id/d');
        return app('json')->success($this->repository->userList($where, $page, $limit));
    }
}
