<?php

namespace app\controller\admin\system\notice;

use app\common\repositories\user\UserNotificationRepository;
use crmeb\basic\BaseController;
use think\App;

/**
 * C 端用户系统通知推送
 */
class UserSystemNotice extends BaseController
{
    protected $repository;

    public function __construct(App $app, UserNotificationRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 推送系统消息到 App 收件箱
     * title, content 必填；uids 可选（逗号分隔或数组），空则广播全体
     */
    public function push()
    {
        $title = trim((string)$this->request->param('title', ''));
        $content = trim((string)$this->request->param('content', ''));
        if ($title === '' || $content === '') {
            return app('json')->fail('标题和内容不能为空');
        }

        $uidsParam = $this->request->param('uids', []);
        $uids = [];
        if (is_string($uidsParam) && $uidsParam !== '') {
            $uids = array_filter(array_map('intval', explode(',', $uidsParam)));
        } elseif (is_array($uidsParam)) {
            $uids = array_filter(array_map('intval', $uidsParam));
        }

        $count = $this->repository->broadcastSystemMessage($title, $content, $uids);
        return app('json')->success(['count' => $count]);
    }
}
