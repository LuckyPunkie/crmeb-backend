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

use app\common\repositories\system\config\ConfigClassifyRepository;
use app\common\repositories\system\config\ConfigRepository;
use app\common\repositories\system\config\ConfigValueRepository;
use app\validate\admin\SystemNoticeConfigValidate;
use crmeb\basic\BaseController;
use think\App;
use  app\common\repositories\system\notice\SystemNoticeConfigRepository;

/**
 * 通知管理
 */
class SystemNoticeConfig extends BaseController
{
    /**
     * @var CommunityTopicRepository
     */
    protected $repository;

    /**
     * User constructor.
     * @param App $app
     * @param  $repository
     */
    public function __construct(App $app, SystemNoticeConfigRepository $repository)
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
        $where = $this->request->params(['keyword', 'type']);
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->repository->getList($where, $page, $limit));
    }

    /**
     * 创建表单
     * @return \think\response\Json
     * @author Qinii
     * @day 10/26/21
     */
    public function createForm()
    {
        return app('json')->success(formToData($this->repository->form(null)));
    }

    /**
     * 创建
     * @return \think\response\Json
     * @author Qinii
     */
    public function create()
    {
        $data = $this->checkParams();
        $data['notice_key'] = trim($data['notice_key']);
        if ($this->repository->fieldExists('notice_key', $data['notice_key'], null))
            return app('json')->fail('通知键名称重复');
        $this->repository->create($data);
        return app('json')->success('添加成功');
    }

    /**
     * 修改表单
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function updateForm($id)
    {
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        return app('json')->success(formToData($this->repository->form($id)));
    }

    /**
     * 修改
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function update($id)
    {
        $data = $this->checkParams();
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        if ($this->repository->fieldExists('notice_key', $data['notice_key'], $id))
            return app('json')->fail('通知键名称重复');
        $this->repository->update($id, $data);

        return app('json')->success('编辑成功');
    }


    /**
     * 删除
     * @param $id
     * @return mixed
     * @author Qinii
     */
    public function delete($id)
    {
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        $this->repository->update($id, ['is_del' => 1]);
        return app('json')->success('删除成功');
    }


    /**
     * 验证
     * @return array|mixed|string|string[]
     * @author Qinii
     */
    public function checkParams()
    {
        $data = $this->request->params(['notice_title', 'notice_key', 'notice_info', 'notice_sys', 'notice_wechat', 'notice_routine', 'notice_sms', 'type']);
        app()->make(SystemNoticeConfigValidate::class)->check($data);
        return $data;
    }

    /**
     * 获取通知列表
     * @return mixed
     * @author Qinii
     */
    public function getOptions()
    {
        return app('json')->success($this->repository->options());
    }

    /**
     * 状态修改
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function switchStatus($id)
    {
        $status = $this->request->param('status', 0) == 1 ? 1 : 0;
        $key = $this->request->param('key', '');

        if (!in_array($key, ['notice_sys', 'notice_wechat', 'notice_routine', 'notice_sms']))
            return app('json')->fail('参数有误');
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');

        $this->repository->swithStatus($id, $key, $status);
        return app('json')->success('修改成功');
    }

    /**
     * 获取模板id
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function getTemplateId($id)
    {
        $data = $this->repository->changeForm($id);
        return app('json')->success(formToData($data));
    }

    /**
     * 设置模板id
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function setTemplateId($id)
    {
        $params = $this->request->params(['sms_tempid', 'sms_ali_tempid', 'routine_tempid', 'wechat_tempid', ['notice_routine', -1], ['notice_wechat', -1], ['notice_sms', -1]]);
        foreach ($params as $k => $v) {
            if (!empty($v)) {
                $data[$k] = $v;
            }
        }
        $this->repository->update($id, $data);
        return app('json')->success('修改成功');
    }

}
