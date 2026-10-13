<?php

namespace app\controller\api\user;

use app\common\repositories\user\UserFieldsRepository;
use crmeb\basic\BaseController;
use think\App;

class UserFields extends BaseController
{

    /**
     * @var UserFieldsRepository
     */
    protected $repository;

    public function __construct(App $app, UserFieldsRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 获取用户表单数据
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     *
     * @date 2023/09/26
     * @author yyw
     */
    public function info()
    {
        return app('json')->success($this->repository->info((int)$this->request->uid()));
    }

    /**
     * 保存或修改
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     *
     * @date 2023/09/26
     * @author yyw
     */
    public function save()
    {
        $data = $this->request->param('extend_info', []);
        $this->repository->save((int)$this->request->uid(), $data);
        return app('json')->success('操作成功');
    }

    /**
     * 删除
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     *
     * @date 2023/09/26
     * @author yyw
     */
    public function delete()
    {
        $this->repository->delete((int)$this->request->uid());
        return app('json')->success('删除成功');
    }
}
