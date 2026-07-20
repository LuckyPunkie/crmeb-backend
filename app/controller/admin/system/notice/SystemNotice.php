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


namespace app\controller\admin\system\notice;


use app\common\repositories\system\notice\SystemNoticeRepository;
use app\validate\admin\SystemNoticeValidate;
use crmeb\basic\BaseController;
use think\App;

/**
 * 通知公告
 */
class SystemNotice extends BaseController
{
    /**
     * @var SystemNoticeRepository
     */
    protected $repository;

    /**
     * SystemNotice constructor.
     * @param App $app
     * @param SystemNoticeRepository $repository
     */
    public function __construct(App $app, SystemNoticeRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 列表
     * @return \think\response\Json
     * @author xaboy
     * @day 2020/11/6
     */
    public function lst()
    {
        $where = $this->request->params(['keyword', 'date']);
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->repository->getList($where, $page, $limit));
    }

    /**
     * 创建
     * @param SystemNoticeValidate $validate
     * @return \think\response\Json
     * @author xaboy
     * @day 2020/11/6
     */
    public function create(SystemNoticeValidate $validate)
    {
        $data = $this->request->params(['type', 'mer_id', 'is_trader', 'category_id', 'notice_title']);
        $data['notice_content'] = $this->request->param('notice_content','');
        $validate->check($data);
        $this->repository->create($data, $this->request->adminId());
        return app('json')->success('发布成功');
    }
    /**
     * 编辑
     *
     * @param integer $id
     * @param SystemNoticeValidate $validate
     * @return void
     */
    public function update(int $id, SystemNoticeValidate $validate)
    {
        $data = $this->request->params(['type', 'mer_id', 'is_trader', 'category_id', 'notice_title']);
        $data['notice_content'] = $this->request->param('notice_content', '');
        $validate->check($data);
        if(!$this->repository->get($id)){
            return app('json')->fail('公告信息不存在');
        }

        return app('json')->success('编辑成功', $this->repository->update($id, $data, $this->request->adminId()));
    }
    /**
     * 切换状态
     *
     * @param integer $id
     * @return \think\response\Json
     */
    public function switchStatus(int $id)
    {
        $data = $this->request->params(['status']);
        if(!in_array($data['status'], [0, 1], true)){
            return app('json')->fail('状态值错误');
        }

        if(!$this->repository->get($id)){
            return app('json')->fail('公告信息不存在');
        }

        return app('json')->success('状态更新成功', $this->repository->switchStatus($id, $data, $this->request->adminId()));
    }

    public function detail($id)
    {
        $info = $this->repository->getWith($id, ['noticeLog']);
        if (!$info) {
            return app('json')->fail('公告信息不存在');
        }

        $info = $info->toArray();
        // 指定商户
        if($info['type'] == 1){
            $info['mer_id'] = array_column($info['noticeLog'], 'mer_id');
        }
        // 商户是否自营
        if ($info['type'] == 2) {
            $info['is_trader'] = $info['type_str'] == "自营" ? 1 : 0;
        }
        // 指定分类
        if ($info['type'] == 3) {
            $info['category'] = explode('/', $info['type_str']);
        }

        return app('json')->success($info);
    }

    public function delete($id)
    {
        return app('json')->success('删除成功', $this->repository->delete($id));
    }
}
