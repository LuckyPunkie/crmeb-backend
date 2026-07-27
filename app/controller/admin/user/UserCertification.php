<?php

namespace app\controller\admin\user;

use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\user\UserCertificationRepository as repository;

class UserCertification extends BaseController
{
    protected $repository;

    public function __construct(App $app, repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 认证列表
     * GET /sys/user/certification/lst
     */
    public function lst()
    {
        $where = [
            'uid'    => $this->request->param('uid', 0),
            'type'   => $this->request->param('type', ''),
            'status' => $this->request->param('status', ''),
        ];
        $page  = (int) $this->request->param('page', 1);
        $limit = (int) $this->request->param('limit', 20);

        $data = $this->repository->adminList($where, $page, $limit);

        // 解码 images
        foreach ($data['list'] as &$item) {
            $item['images'] = $item['images'] ? json_decode($item['images'], true) : [];
        }

        return app('json')->success($data);
    }

    /**
     * 审核（拒绝 status=2 / 重新通过 status=1）
     * POST /sys/user/certification/review/:id
     */
    public function review(int $id)
    {
        $status = (int) $this->request->param('status');
        $remark = $this->request->param('remark', '');

        if (!in_array($status, [1, 2])) {
            return app('json')->fail('状态参数不合法，1=通过 2=拒绝');
        }

        $this->repository->review($id, $status, $remark);

        return app('json')->success('审核完成');
    }
}
