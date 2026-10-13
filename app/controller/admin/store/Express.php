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

namespace app\controller\admin\store;

use crmeb\jobs\ExpressSyncJob;
use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\store\shipping\ExpressRepository as repository;
use think\facade\Queue;

/**
 * 快递公司
 */
class Express extends BaseController
{
    /**
     * @var repository
     */
    protected $repository;

    /**
     * City constructor.
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
     * @Author:Qinii
     * @Date: 2020/5/13
     * @return mixed
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['keyword', 'code']);
        $mer_id = $this->request->merId();
        if ($mer_id) $where['is_show'] = 1;
        return app('json')->success($this->repository->search($where, $page, $limit, $mer_id));
    }

    /**
     * 商户修改状态
     *
     * @param [type] $id
     * @return void
     */
    public function merStatus($id)
    {
        $merStatus = $this->request->param('mer_status');
        if(!in_array($merStatus,['0', '1'])) {
            return app('json')->fail('参数错误');
        }

        $merId = $this->request->merId();
        if(!$merId) {
            return app('json')->fail('商户不存在');
        }

        return app('json')->success('修改成功', $this->repository->changeMerStatus($id, $merStatus, $merId));
    }

    /**
     * 详情
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function detail($id)
    {
        return app('json')->success($this->repository->get($id));
    }

    /**
     * 添加
     * @Author:Qinii
     * @Date: 2020/5/13
     * @return mixed
     */
    public function create()
    {
        $data = $this->request->params(['name', 'code', 'is_show', 'sort']);
        if (empty($data['name']))
            return app('json')->fail('名称不可为空');
        if ($this->repository->codeExists($data['code'], null))
            return app('json')->fail('编码重复');
        if ($this->repository->nameExists($data['name'], null))
            return app('json')->fail('名称重复');
        $this->repository->create($data);
        return app('json')->success('添加成功');
    }

    /**
     * 编辑
     * @Author:Qinii
     * @Date: 2020/5/13
     * @param $id
     * @return mixed
     */
    public function update($id)
    {
        $data = $this->request->params(['name', 'code', 'is_show', 'sort']);
        if (!$this->repository->fieldExists($id))
            return app('json')->fail('数据不存在');
        if (empty($data['name']))
            return app('json')->fail('名称不可为空');
        if ($this->repository->codeExists($data['code'], $id))
            return app('json')->fail('编码重复');
        if ($this->repository->nameExists($data['name'], $id))
            return app('json')->fail('名称重复');

        $this->repository->update($id, $data);
        return app('json')->success('编辑成功');
    }

    /**
     * 删除
     * @Author:Qinii
     * @Date: 2020/5/13
     * @param $id
     * @return mixed
     */
    public function delete($id)
    {
        if (!$this->repository->fieldExists($id))
            return app('json')->fail('数据不存在');

        $this->repository->delete($id);
        return app('json')->success('删除成功');

    }

    /**
     * 添加表单
     * @Author:Qinii
     * @Date: 2020/5/22
     * @return mixed
     */
    public function createForm()
    {
        return app('json')->success(formToData($this->repository->form($this->request->merId())));
    }

    /**
     * 编辑表单
     * @Author:Qinii
     * @Date: 2020/5/22
     * @param $id
     * @return mixed
     */
    public function updateForm($id)
    {
        if (!$this->repository->fieldExists($id))
            return app('json')->fail('数据不存在');
        return app('json')->success(formToData($this->repository->updateForm($this->request->merId(), $id)));
    }

    /**
     * 状态修改
     * @Author:Qinii
     * @Date: 2020/5/22
     * @param int $id
     * @return mixed
     */
    public function switchStatus($id)
    {
        $status = $this->request->param('is_show', 0) == 1 ? 1 : 0;
        if (!$this->repository->fieldExists($id))
            return app('json')->fail('数据不存在');

        $this->repository->switchStatus($id, ['is_show' => $status]);
        return app('json')->success('修改成功');
    }

    /**
     *  同步信息
     * @return \think\response\Json
     * @author Qinii
     * @day 7/23/21
     */
    public function syncAll()
    {
        $config = systemConfig(['serve_account','serve_token']);
        if (!$config || !$config['serve_account'] || !$config['serve_token']) return app('json')->fail('请先配置一号通账号，再进行物流同步');
        Queue::push(ExpressSyncJob::class, []);
        return app('json')->success('后台同步中，请稍后来查看～');
    }

    /**
     * 快递公司月结账号等添加表单
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function partnerForm($id)
    {
        $merId = $this->request->merId();
        return app('json')->success(formToData($this->repository->partnerForm($id, $merId)));
    }

    /**
     *  快递公司月结账号等添加
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function partner($id)
    {
        $data = $this->request->params(['account', 'key', 'net_name']);

        if (!$expressInfo = $this->repository->get($id))
            return app('json')->fail('编辑的记录不存在!');
        if ($expressInfo['partner_id'] == 1 && !$data['account'])
            return app('json')->fail('请输入月结账号');
        if ($expressInfo['partner_key'] == 1 && !$data['key'])
            return app('json')->fail('请输入月结密码');
        if ($expressInfo['net'] == 1 && !$data['net_name'])
            return app('json')->fail('请输入取件网点');
        if ($expressInfo['check_man'] == 1 && !$data['check_man'])
            return app('json')->fail('请输入承载快递员名称');
        if ($expressInfo['partner_name'] == 1 && !$data['partner_name'])
            return app('json')->fail('请输入客户账户名称');
        if ($expressInfo['is_code'] == 1 && !$data['code'])
            return app('json')->fail('请输入承载编号');

        $data['express_id'] = $id;
        $data['mer_id'] = $this->request->merId();

        $this->repository->updatePartne($data);
        return app('json')->success('修改成功');
    }

    /**
     * 获取所有
     * @return \think\response\Json
     * @author Qinii
     */
    public function options()
    {
        $merId = $this->request->merId();
        return app('json')->success($this->repository->options($merId));
    }
}
