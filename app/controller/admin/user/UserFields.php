<?php

namespace app\controller\admin\user;

use app\common\repositories\user\UserFieldsRepository;
use crmeb\basic\BaseController;
use think\App;

/**
 * Class UserFields
 * app\controller\admin\user
 * 用户扩展字段
 */
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
     *  保存用户扩展字段表单
     * @param $uid
     * @return \think\response\Json
     * @author yyw
     */
    public function saveForm($uid)
    {
        return app('json')->success(formToData($this->repository->extendInfoForm((int)$uid)));
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
    public function save($uid)
    {
        $data = $this->request->param();
        $save_data = [];
        // 组合数据
        foreach ($data as $key => $item) {
            $save_data[] = [
                'field' => $key,
                'value' => $item,
            ];
        }
        $this->repository->save((int)$uid, $save_data);
        return app('json')->success('操作成功');
    }

}
