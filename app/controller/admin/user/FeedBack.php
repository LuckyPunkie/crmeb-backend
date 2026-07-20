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

namespace app\controller\admin\user;


use crmeb\basic\BaseController;
use think\App;
use app\common\repositories\user\FeedbackRepository as repository;

/**
 * 用户反馈
 * app\controller\admin\user
 * FeedBack
 */
class FeedBack extends BaseController
{
    /**
     * @var UserRepository
     */
    protected $repository;

    /**
     * User constructor.
     * @param App $app
     * @param  $repository
     */
    public function __construct(App $app, repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 列表
     * @return mixed
     * @author Qinii
     */
    public function lst()
    {
        $where = $this->request->params(['keyword', 'type', 'status', 'realname', ['is_del', 0]]);
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->repository->getList($where, $page, $limit));
    }


    /**
     * 详情
     * @param $id
     * @return mixed
     * @author Qinii
     */
    public function detail($id)
    {
        if (!$this->repository->fieldExists('feedback_id', $id))
            return app('json')->fail('数据不存在');
        $feedback = $this->repository->get($id)->toArray();
        [$feedback['category'], $feedback['type']] = explode('/', $feedback['type'], 2);
        return app('json')->success($feedback);
    }

    /**
     * 回复表单
     * @param $id
     * @return mixed
     * @author Qinii
     */
    public function replyForm($id)
    {
        return app('json')->success(formToData($this->repository->replyForm($id)));
    }

    /**
     * 回复提交
     * @param $id
     * @return mixed
     * @throws \think\db\exception\DbException
     * @author Qinii
     */
    public function reply($id)
    {
        if (!$this->repository->fieldExists('feedback_id', $id))
            return app('json')->fail('数据不存在');
        $data = $this->request->params(['reply', 'remake']);
        if (empty($data['reply'])) {
            return app('json')->fail('请填写回复内容');
        }
        $data['status'] = 1;
        $data['update_time'] = date('Y-m-d H:i:s');

        $this->repository->update($id, $data);
        if (!empty($data['reply'])) event('user.feedbackReply', compact('id', 'data'));
        return app('json')->success('回复成功');
    }

    /**
     * 删除
     * @param $id
     * @return mixed
     * @author Qinii
     */
    public function delete($id)
    {
        if (!$this->repository->fieldExists('feedback_id', $id))
            return app('json')->fail('数据不存在');
        $this->repository->update($id, ['is_del' => 1]);
        return app('json')->success('删除成功');
    }
}
