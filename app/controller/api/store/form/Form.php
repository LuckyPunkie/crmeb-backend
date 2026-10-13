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

namespace app\controller\api\store\form;

use app\common\repositories\store\StoreActivityRelatedRepository;
use app\common\repositories\store\StoreActivityRepository;
use app\common\repositories\system\form\FormRepository;
use app\common\repositories\user\UserMerchantRepository;
use think\App;
use crmeb\basic\BaseController;
use think\exception\ValidateException;

class Form extends BaseController
{
    protected $repository;

    /**
     * ProductCategory constructor.
     * @param App $app
     * @param StoreActivityRepository $repository
     */
    public function __construct(App $app, StoreActivityRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 报名活动列表
     * @return \think\response\Json
     * @author Qinii
     * @day 2023/10/8
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where['activity_type'] = $this->repository::ACTIVITY_TYPE_FORM;
//        $where['status'] = 1;
        $where['is_show'] = 1;
        return app('json')->success($this->repository->getList($where, $page, $limit));
    }

    /**
     * 报名活动详情
     * @Author:Qinii
     * @Date: 2020/5/29
     * @param $id
     * @return mixed
     */
    public function detail($id)
    {
        $info = $this->repository->getWhere([$this->repository->getPk() => $id], '*', ['systemForm']);
        if (!$info) return app('json')->fail('数据不存在');
        //$this->repository->verifyActivityStatus($info);
        $info->append(['time_status']);
        // 判断该用户有没有提交表单
        $info['activity_related_id'] = $this->repository->verifyActivityData((int)$this->request->uid(), (int)$id);
        $data['data'] = $info;

        return app('json')->encode($data);
    }

    /**
     * 获取报名数据
     * @param $form_id
     * @return \think\response\Json
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/10
     */
    public function getFormInfo($form_id)
    {
        $info = app()->make(FormRepository::class)->getSearch(['form_id' => $form_id])->find();
        if (!$info) return app('json')->fail('数据不存在');

        return app('json')->encode($info);
    }

    /**
     * 分享报名
     * @param $id
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/10
     */
    public function getSharePosters($id)
    {
        $type = $this->request->param('type');
        $user = $this->request->userInfo();
        $activity = $this->repository->get((int)$id);
        if (empty($activity)) {
            return app('json')->success('报名活动异常');
        }
        $qrcode = $type == 'routine'
            ? $this->repository->mpQrcode((int)$user['uid'], $activity)
            : $this->repository->wxQrcode((int)$user['uid'], $activity);
        $poster = $activity['images'];
        $nickname = $user['nickname'];
        $mark = '邀您一起参加' . $activity['activity_name'] ?? '活动';

        return app('json')->success(compact('qrcode', 'poster', 'nickname', 'mark'));
    }

}
