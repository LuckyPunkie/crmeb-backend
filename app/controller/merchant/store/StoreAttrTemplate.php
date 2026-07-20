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


namespace app\controller\merchant\store;


use crmeb\basic\BaseController;
use app\common\repositories\store\StoreAttrTemplateRepository;
use app\validate\merchant\StoreAttrTemplateValidate;
use think\App;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * Class StoreAttrTemplate
 * @package app\controller\merchant\store
 * @author xaboy
 * @day 2020-05-06
 */
class StoreAttrTemplate extends BaseController
{
    /**
     * @var StoreAttrTemplateRepository
     */
    protected $repository;

    /**
     * StoreAttrTemplate constructor.
     * @param App $app
     * @param StoreAttrTemplateRepository $repository
     */
    public function __construct(App $app, StoreAttrTemplateRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 获取列表数据
     *
     * @return \think\response\Json
     */
    public function lst()
    {
        // 获取分页参数
        [$page, $limit] = $this->getPage();
        // 调用 repository 层获取列表数据
        $data = $this->repository->getList($this->request->merId(), [], $page, $limit);
        // 返回 JSON 格式的成功响应
        return app('json')->success($data);
    }


    public function getlist()
    {
        // 调用 repository 类的 list 方法获取列表数据，并通过 json 组件返回成功状态和数据
        return app('json')->success($this->repository->list($this->request->merId()));
    }


    /**
     * 创建商品属性模板
     *
     * @param StoreAttrTemplateValidate $validate 商品属性模板验证器
     * @return \Psr\Http\Message\ResponseInterface JSON格式的响应结果
     */
    public function create(StoreAttrTemplateValidate $validate)
    {
        // 调用checkParams方法获取验证后的数据
        $data = $this->checkParams($validate);
        // 设置商家ID
        $data['mer_id'] = $this->request->merId();
        // 调用repository的create方法创建商品属性模板
        $this->repository->create($data);

        // 返回JSON格式的成功响应结果
        return app('json')->success('添加成功');
    }


    /**
     * 更新属性模板
     *
     * @param int $id 属性模板ID
     * @param StoreAttrTemplateValidate $validate 验证器实例
     * @return \Psr\Http\Message\ResponseInterface JSON格式的响应结果
     */
    public function update($id, StoreAttrTemplateValidate $validate)
    {
        // 获取当前商家ID
        $merId = $this->request->merId();

        // 判断商家是否存在该属性模板
        if (!$this->repository->merExists($merId, $id))
            return app('json')->fail('数据不存在');
        // 校验参数并添加商家ID
        $data = $this->checkParams($validate);
        $data['mer_id'] = $merId;
        // 更新属性模板
        $this->repository->update($id, $data);

        // 返回成功响应
        return app('json')->success('编辑成功');
    }


    /**
     * 根据ID删除数据
     *
     * @param int $id 数据ID
     * @return \think\response\Json 返回JSON格式的响应结果
     */
    public function delete($id)
    {
        // 获取当前商家ID
        $merId = $this->request->merId();
        // 判断数据是否存在
        if (!$this->repository->merExists($merId, $id))
            return app('json')->fail('数据不存在');
        // 删除数据
        $this->repository->delete($id, $merId);

        // 返回成功响应结果
        return app('json')->success('删除成功');
    }


    /**
     * 检查参数是否符合要求
     *
     * @param StoreAttrTemplateValidate $validate 商品属性模板验证器
     * @return array 返回符合要求的参数数组
     */
    public function checkParams(StoreAttrTemplateValidate $validate)
    {
        // 从请求中获取参数
        $data = $this->request->params(['template_name', ['template_value', []]]);
        // 对参数进行验证
        $validate->check($data);
        // 返回符合要求的参数数组
        return $data;
    }

}
