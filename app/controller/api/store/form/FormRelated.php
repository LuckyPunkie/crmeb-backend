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
use think\App;
use crmeb\basic\BaseController;

class FormRelated extends BaseController
{
    protected $repository;

    /**
     * ProductCategory constructor.
     * @param App $app
     * @param StoreActivityRelatedRepository $repository
     */
    public function __construct(App $app, StoreActivityRelatedRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 获取用户提交的报名
     * @return \think\response\Json
     * @author Qinii
     * @day 2023/10/8
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where['activity_type'] = $this->repository::ACTIVITY_TYPE_FORM;
        $where['uid'] = $this->request->uid();
        //$where['is_del'] = 0;
        return app('json')->success($this->repository->getList($where, $page, $limit));
    }

    /**
     * 用户提交表单数据的保存
     * @param FormRepository $formRepository
     * @return \think\response\Json
     * @author Qinii
     * @day 2023/10/8
     */
    public function create($id, FormRepository $formRepository,StoreActivityRepository $repository)
    {
        $user = $this->request->userInfo();
        if (!$id) return app('json')->fail('缺少活动ID');
        $createData = [
            'uid' => $user->uid,
            'nickname' => $user->nickname,
            'avatar' => $user->avatar,
            'phone' => $user->phone,
            'activity_id' => $id,
            'activity_type' => $this->repository::ACTIVITY_TYPE_FORM,
        ];
        if ($this->repository->getSearch($createData)->count())
            return app('json')->fail('您已提交过了，请勿重复提交');

        $res = $repository->getSearch(['activity_id' => $id,'activity_type' => $repository::ACTIVITY_TYPE_FORM])->find();
        $repository->verifyActivityStatus($res, true);
        $form_id = $res['link_id'];

        $params = $formRepository->getFormKeys($form_id);
        $data = $this->request->params($params['data']);
        foreach ($data as $k => $v){
            if ($params['val'][$k]['val'] && !$v )
                return app('json')->fail($params['val'][$k]['label'].'为必填项');
        }

        $createData['value'] = json_encode($data,JSON_UNESCAPED_UNICODE);
        $createData['link_id'] = $form_id;
        $createData['keys'] = $params['form'];
        $createData['form_value'] = $params['form_value'];
        $this->repository->save($id, $createData);
        return app('json')->success('提交成功');
    }

    /**
     *  个人提交信息详情
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     * @day 2023/11/16
     */
    public function show($id)
    {
        $uid = $this->request->uid();
        $data = $this->repository->show($id, $uid);
        return app('json')->encode($data);
    }
}
