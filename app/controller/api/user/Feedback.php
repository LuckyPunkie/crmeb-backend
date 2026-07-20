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


namespace app\controller\api\user;

use crmeb\basic\BaseController;
use app\common\repositories\user\FeedbackRepository;
use app\validate\api\FeedbackValidate;
use think\App;

class Feedback extends BaseController
{
    protected $repository;

    public function __construct(App $app, FeedbackRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 用户反馈
     * @param FeedbackValidate $validate
     * @param FeedbackRepository $repository
     * @return mixed
     * @author xaboy
     * @day 2020/5/28
     */
    public function feedback(FeedbackValidate $validate)
    {
        $data = $this->request->params(['type', 'content', ['images', []], 'realname', 'contact',['status',0]]);
        $validate->check($data);
        $data['uid'] = $this->request->uid();
        $FeedBack = $this->repository->create($data);

        event('user.feedback',compact('FeedBack'));
        return app('json')->success('反馈成功');
    }

    /**
     * 用户反馈列表
     * @return mixed
     * @author xaboy
     * @day 2020/5/28
     */
    public function feedbackList()
    {
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->repository->getList(['uid' => $this->request->uid(),'is_del' => 0], $page, $limit));
    }

    /**
     * 反馈详情
     * @param $id
     * @return \think\response\Json
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/10
     */
    public function detail($id)
    {
        if (!$this->repository->uidExists($id, $this->request->uid()))
            return app('json')->fail('数据不存在');
        $feedback = $this->repository->get($id);
        return app('json')->success($feedback);
    }
}
