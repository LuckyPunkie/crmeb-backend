<?php

namespace app\controller\admin\user;

use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\user\UserCertificationRepository as repository;
use app\common\repositories\user\UserBotImportRepository;

class UserCertification extends BaseController
{
    protected $repository;

    public function __construct(App $app, repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 以用户为单位的审核列表
     * GET /sys/user/certification/user_lst
     */
    public function userLst()
    {
        $where = [
            'uid' => $this->request->param('uid', 0),
            'nickname' => $this->request->param('nickname', ''),
            'keyword' => $this->request->param('keyword', ''),
            'profile_review_status' => $this->request->param('profile_review_status', ''),
            'profile_review_urgent' => $this->request->param('profile_review_urgent', ''),
        ];
        $page = (int)$this->request->param('page', 1);
        $limit = (int)$this->request->param('limit', 20);
        $data = $this->repository->adminUserList($where, $page, $limit);
        return app('json')->success($data);
    }

    /**
     * 用户审核详情
     * GET /sys/user/certification/user_detail/:uid
     */
    public function userDetail(int $uid)
    {
        try {
            $data = $this->repository->adminUserDetail($uid);
        } catch (\InvalidArgumentException $e) {
            return app('json')->fail($e->getMessage());
        }
        return app('json')->success($data);
    }

    /**
     * 以用户为单位提交审核
     * POST /sys/user/certification/user_review/:uid
     * body: { items: [{id, status, remark}] }
     */
    public function userReview(int $uid)
    {
        $items = $this->request->param('items/a', []);
        try {
            $this->repository->reviewByUser($uid, $items);
        } catch (\InvalidArgumentException $e) {
            return app('json')->fail($e->getMessage());
        }
        return app('json')->success('审核完成');
    }

    /**
     * 认证列表（兼容旧：按资质条）
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

        foreach ($data['list'] as &$item) {
            $item['images']   = $this->decodeImages($item['images'] ?? null);
            $item['nickname'] = $item['user']['nickname'] ?? '';
            $item['avatar']   = $item['user']['avatar'] ?? '';
            $item['phone']    = $item['user']['phone'] ?? '';
        }
        unset($item);

        return app('json')->success($data);
    }

    private function decodeImages($images): array
    {
        if (is_array($images)) {
            return array_values(array_filter($images));
        }
        if ($images === null || $images === '') {
            return [];
        }
        $decoded = json_decode((string) $images, true);
        if (is_array($decoded)) {
            return array_values(array_filter($decoded));
        }
        if (is_string($decoded) && $decoded !== '') {
            $decoded2 = json_decode($decoded, true);
            if (is_array($decoded2)) {
                return array_values(array_filter($decoded2));
            }
        }
        if (is_string($images) && (strpos($images, 'http') === 0 || strpos($images, '/') === 0)) {
            return [$images];
        }
        return [];
    }

    /**
     * 审核（拒绝 status=2 / 重新通过 status=1）— 兼容旧单条接口
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
