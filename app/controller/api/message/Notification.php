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

namespace app\controller\api\message;

use app\common\repositories\user\UserNotificationRepository;
use crmeb\basic\BaseController;
use think\App;

class Notification extends BaseController
{
    protected $repository;

    public function __construct(App $app, UserNotificationRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    public function list()
    {
        [$page, $limit] = $this->getPage();
        $type = $this->request->param('type', null);
        $uid = $this->request->uid();

        $result = $this->repository->getList($uid, $page, $limit, $type);
        return app('json')->success($result);
    }

    public function markRead($id)
    {
        $uid = $this->request->uid();
        $this->repository->markRead(intval($id), $uid);
        return app('json')->success('标记成功');
    }

    public function unreadCount()
    {
        $uid = $this->request->uid();
        $count = $this->repository->getUnreadCount($uid);
        return app('json')->success(['unread_count' => $count]);
    }
}
