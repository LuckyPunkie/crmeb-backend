<?php

namespace app\controller\api\user;

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
     * 获取当前用户所有认证记录
     * GET /api/user/certification
     */
    public function list()
    {
        $uid  = $this->request->uid();
        $list = $this->repository->getByUid($uid);

        // 解码 images 字段
        foreach ($list as &$item) {
            $item['images'] = $item['images'] ? json_decode($item['images'], true) : [];
        }

        return app('json')->success($list);
    }

    /**
     * 提交/更新认证（自动通过）
     * POST /api/user/certification/save
     */
    public function save()
    {
        $uid         = $this->request->uid();
        $type        = $this->request->param('type', '');
        $description = $this->request->param('description', '');
        $images      = $this->request->param('images', []);

        if (!$type) {
            return app('json')->fail('认证类型不能为空');
        }

        if (!is_array($images)) {
            $images = $images ? json_decode($images, true) : [];
        }

        try {
            $this->repository->save($uid, $type, $description, $images);
        } catch (\InvalidArgumentException $e) {
            return app('json')->fail($e->getMessage());
        }

        return app('json')->success('提交成功，已自动通过认证');
    }
}
