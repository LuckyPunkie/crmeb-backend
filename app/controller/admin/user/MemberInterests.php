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

use app\common\repositories\user\MemberinterestsRepository;
use app\common\repositories\user\UserBrokerageRepository;
use app\validate\admin\UserBrokerageValidate;
use crmeb\basic\BaseController;
use think\App;
use think\exception\ValidateException;

/**
 * 我的等级 / 付费会员 权益
 * app\controller\admin\user
 * MemberInterests
 */
class MemberInterests extends BaseController
{
    protected $repository;

    public function __construct(App $app, MemberinterestsRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 列表
     * @return \think\response\Json
     * @author Qinii
     */
    public function getLst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['name', ['type', $this->repository::TYPE_FREE]]);
        return app('json')->success($this->repository->getList($where, $page, $limit));
    }

    /**
     * 创建表单
     * @return \think\response\Json
     * @author Qinii
     */
    public function createForm()
    {
        return app('json')->success(formToData($this->repository->form()));
    }

    /**
     * 创建
     * @return \think\response\Json
     * @author Qinii
     */
    public function create()
    {
        $data = $this->checkParams();
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
        return app('json')->success(formToData($this->repository->form($id, $this->repository::TYPE_FREE)));
    }

    /**
     * 修改
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function update($id)
    {
        $id = (int)$id;
        $data = $this->checkParams();
        if (!$id || !$this->repository->get($id)) {
            return app('json')->fail('数据不存在');
        }
        $this->repository->update($id, $data);
        return app('json')->success('修改成功');
    }

    /**
     * 详情
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function detail($id)
    {
        $id = (int)$id;
        if (!$id || !$brokerage = $this->repository->get($id)) {
            return app('json')->fail('数据不存在');
        }
        return app('json')->success($brokerage->toArray());
    }

    /**
     * 删除
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function delete($id)
    {
        $id = (int)$id;
        if (!$id || !$brokerage = $this->repository->get($id)) {
            return app('json')->fail('数据不存在');
        }
        $brokerage->delete();
        return app('json')->success('删除成功');
    }

    /**
     * 验证数据
     * @return array
     * @author Qinii
     */
    public function checkParams()
    {
        $data = $this->request->params(['brokerage_level', 'name', 'info', 'pic', 'type', ['has_type', 0], ['link', ''], ['value', ''], ['on_pic', '']]);
        if ($data['type'] == $this->repository::TYPE_FREE) {
            if (!$data['name'] || !$data['pic'] || empty($data['brokerage_level']))
                throw new ValidateException('请填写正确的权益信息');
            $count = app()->make(UserBrokerageRepository::class)->getWhereCount(['brokerage_level' => $data['brokerage_level'], 'type' => $data['type']]);
            if (!$count) throw new ValidateException('会员等级不存在');
        } else {
            if (mb_strlen($data['name']) > 6) throw new ValidateException('名称必须小于6个字符');
            if ($data['value'] < 0 && in_array($data['has_type'], [$this->repository::HAS_TYPE_SIGN, $this->repository::HAS_TYPE_PAY, $this->repository::HAS_TYPE_MEMBER])) {
                throw new ValidateException('倍数不能位负数');
            }
        }

        return $data;
    }

    /**
     * 获取付费会员权益
     * @return \think\response\Json
     * @author Qinii
     */
    public function getSvipInterests()
    {
        $where['type'] = $this->repository::TYPE_SVIP;
        $data = $this->repository->getList($where, 1, 10);
        return app('json')->success($data['list']);
    }

    /**
     * 付费会员权益修改表单
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function updateSvipForm($id)
    {
        return app('json')->success(formToData($this->repository->svipForm($id)));
    }

    /**
     * 付费会员权益修改
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function switchWithStatus($id)
    {
        $status = $this->request->param('status') == 1 ? 1 : 0;
        try {
            $this->repository->update($id, ['status' => $status]);
            return app('json')->success('修改成功');
        } catch (\Exception $exception) {
            return app('json')->success('修改失败');
        }
    }
}
