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


namespace app\controller\merchant\user;


use app\common\repositories\user\LabelRuleRepository;
use app\common\repositories\user\UserLabelRepository;
use app\validate\merchant\LabelRuleValidate;
use crmeb\basic\BaseController;
use think\App;
use think\exception\ValidateException;

class LabelRule extends BaseController
{
    protected $repository;

    /**
     * 构造函数
     *
     * @param App $app 应用实例
     * @param LabelRuleRepository $repository 标签规则仓库实例
     */
    public function __construct(App $app, LabelRuleRepository $repository)
    {
        // 调用父类构造函数
        parent::__construct($app);
        // 初始化标签规则仓库实例
        $this->repository = $repository;
    }

    /**
     * 获取标签列表
     *
     * @return mixed
     */
    public function getList()
    {
        // 从请求参数中获取查询条件
        $where = $this->request->params(['keyword', 'type']);
        // 设置商家ID
        $where['mer_id'] = $this->request->merId();
        // 获取分页参数
        [$page, $limit] = $this->getPage();
        // 调用标签规则仓库的获取标签列表方法并返回结果
        return app('json')->success($this->repository->getList($where, $page, $limit));
    }

    /**
     * 创建标签
     *
     * @return mixed
     */
    public function create()
    {
        // 检查参数
        $data = $this->checkParams();
        $data['mer_id'] = $this->request->merId();
        // 判断标签名是否已存在
        if (app()->make(UserLabelRepository::class)->existsName($data['label_name'], $data['mer_id'], 1))
            return app('json')->fail('标签名已存在');
        // 调用标签规则仓库的创建标签方法
        $this->repository->create($data);
        // 返回操作成功的结果
        return app('json')->success('添加成功');
    }


    /**
     * 更新标签规则
     *
     * @param int $id 标签规则ID
     * @return \think\response\Json
     */
    public function update($id)
    {
        // 获取参数并校验
        $data = $this->checkParams();
        // 获取商家ID
        $mer_id = $this->request->merId();
        // 根据条件查询标签规则
        if (!$label = $this->repository->getWhere(['label_rule_id' => $id, 'mer_id' => $mer_id]))
            return app('json')->fail('数据不存在');
        // 判断标签名是否已存在
        if (app()->make(UserLabelRepository::class)->existsName($data['label_name'], $mer_id, 1, $label->label_id))
            return app('json')->fail('标签名已存在');
        // 更新标签规则
        $this->repository->update(intval($id), $data);
        return app('json')->success('编辑成功');
    }

    /**
     * 删除标签规则
     *
     * @param int $id 标签规则ID
     * @return \think\response\Json
     */
    public function delete($id)
    {
        // 判断标签规则是否存在
        if (!$this->repository->existsWhere(['label_rule_id' => $id, 'mer_id' => $this->request->merId()]))
            return app('json')->fail('数据不存在');
        // 删除标签规则
        $this->repository->delete(intval($id));
        return app('json')->success('删除成功');
    }


    /**
     * 同步标签规则用户数量
     * @param int $id 标签规则ID
     * @return \think\response\Json
     */
    public function sync($id)
    {
        // 判断标签规则是否存在
        if (!$this->repository->existsWhere(['label_rule_id' => $id, 'mer_id' => $this->request->merId()]))
            return app('json')->fail('数据不存在');
        // 同步标签规则用户数量
        $this->repository->syncUserNum(intval($id));
        // 返回操作结果
        return app('json')->success('更新成功');

    }

    /**
     * 校验参数
     * @return array
     * @throws \app\common\exception\ValidateException
     */
    public function checkParams()
    {
        // 获取请求参数
        $data = $this->request->params(['label_name', 'min', 'max', 'type', 'data']);
        // 校验参数
        app()->make(LabelRuleValidate::class)->check($data);
        // 如果类型为空，则校验最小值和最大值是否为整数
        if (!$data['type']) {
            if (false === filter_var($data['min'], FILTER_VALIDATE_INT) || false === filter_var($data['max'], FILTER_VALIDATE_INT)) {
                throw new ValidateException('数值必须为整数');
            }
        }
        // 返回校验后的参数
        return $data;
    }

}
