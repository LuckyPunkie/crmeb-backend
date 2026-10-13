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

namespace app\controller\admin\store\marketing;

use app\common\repositories\store\StoreActivityRelatedRepository;
use app\validate\admin\StoreActivityValidate;
use crmeb\services\ExcelService;
use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\store\StoreActivityRepository as repository;
use think\exception\ValidateException;

/**
 * 系统表单
 */
class StoreForm extends BaseController
{

    /**
     * @var repository
     */
    protected $repository;

    /**
     * @param App $app
     * @param repository $repository
     */
    public function __construct(App $app, repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 列表
     * @return mixed
     * @Author: liusl
     * @Date: 2022/6/24
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['keyword', 'status', 'date', 'form_id']);
        $where['activity_type'] = repository::ACTIVITY_TYPE_FORM;
        return app('json')->success($this->repository->getAdminList($where, $page, $limit, ['systemFormKeys']));
    }

    /**
     * 创建
     * @param StoreActivityValidate $validate
     * @return \think\response\Json
     * @author Qinii
     * @day 2022/9/16
     */
    public function create(StoreActivityValidate $validate)
    {
        [$data, $extend] = $this->checkParams($validate);
        $this->repository->createActivity($data, $extend);
        return app('json')->success('添加成功');
    }

    /**
     * 参数验证
     * @param StoreActivityValidate $validate
     * @return array
     * @author Qinii
     * @day 2022/9/15
     */
    public function checkParams(StoreActivityValidate $validate)
    {
        $params = ["activity_name", "start_time", "end_time", "is_show", "pic", "images", 'info', 'color', 'sort', ['count', 0]];
        $data = $this->request->params($params);
        $validate->check($data);
        $data['activity_type'] = repository::ACTIVITY_TYPE_FORM;
        if ($data['images']) {
            $data['images'] = implode(',', $data['images']);
        } else {
            $data['images'] = '';
        }
        $data['status'] = 1;
        if (!$data['end_time'] && !$data['count']) {
            throw new ValidateException('请选择结束时间或活动总人数');
        }
        $form_id = $this->request->param('form_id');
        if (!$form_id) throw new ValidateException('请关联系统表单');
        $data['link_id'] = $form_id;
        $extend = [];
        $data['update_time'] = date('Y-m-d H:i:s', time());
        return [$data, $extend];
    }

    /**
     * 修改
     * @param StoreActivityValidate $validate
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     * @day 2022/9/17
     */
    public function update(StoreActivityValidate $validate, $id)
    {
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        [$data, $extend] = $this->checkParams($validate);
        $this->repository->updateActivity($id, $data, $extend);
        return app('json')->success('修改成功');
    }

    /**
     *  状态修改
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function statusSwitch($id)
    {
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        $status = $this->request->param('status', 0) == 1 ? 1 : 0;
        $this->repository->update($id, ['is_show' => $status]);
        return app('json')->success('修改成功');
    }

    /**
     *  详情
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     * @day 2022/9/16
     */
    public function detail($id)
    {
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        return app('json')->success($this->repository->detail($id, false));
    }

    /**
     * 删除
     * @param $id
     * @return mixed
     * @Author: liusl
     * @Date: 2022/6/27
     */
    public function delete($id)
    {
        if (!$info = $this->repository->get($id))
            return app('json')->fail('数据不存在');
        $info->append(['time_status']);
        if ($info->time_status == 1)
            return app('json')->fail('活动进行中不能删除');
        // 删除报名活动数据
        app()->make(StoreActivityRelatedRepository::class)->search(['activity_id' => $id, 'activity_type' => StoreActivityRelatedRepository::ACTIVITY_TYPE_FORM])->update(['is_del' => 1]);
        $this->repository->delete($id);
        return app('json')->success('删除成功');
    }

    /**
     *  当前活动 用户提交的信息记录
     * @param $id
     * @param StoreActivityRelatedRepository $storeActivityRelatedRepository
     * @return \think\response\Json
     * @author Qinii
     * @day 2023/10/18
     */
    public function activUserLst($id, StoreActivityRelatedRepository $storeActivityRelatedRepository)
    {
        $where = $this->request->params(['keyword']);
        [$page, $limit] = $this->getPage();
        $where['activity_id'] = $id;
        $data = $storeActivityRelatedRepository->getList($where, $page, $limit);
        return app('json')->success($data);
    }

    /**
     *  导出报名用户信息
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function activUserExcel($id)
    {
        [$page, $limit] = $this->getPage();
        $where['activity_id'] = $id;
        $where['keyword'] = $this->request->param('keyword', '');
        $data = app()->make(ExcelService::class)->userFormLst($where, $page, $limit);
        return app('json')->success($data);
    }
}
