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

namespace app\controller\merchant\store\shipping;

use think\App;
use crmeb\basic\BaseController;
use app\validate\merchant\ShippingTemplateValidate as validate;
use app\common\repositories\store\shipping\ShippingTemplateRepository as repository;

class ShippingTemplate extends BaseController
{
    protected $repository;

    /**
     * ShippingTemplate constructor.
     * @param App $app
     * @param repository $repository
     */
    public function __construct(App $app, repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 获取列表数据
     * @return \think\response\Json
     */
    public function lst()
    {
        // 获取分页参数
        [$page, $limit] = $this->getPage();
        // 获取查询条件
        $where = $this->request->params(['type', 'name']);
        // 调用 repository 类的 search 方法获取数据并返回 JSON 格式的成功响应
        return app('json')->success($this->repository->search($this->request->merId(), $where, $page, $limit));
    }

    /**
     * 获取列表数据
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getList()
    {
        // 调用 repository 类的 getList 方法获取数据并返回 JSON 格式的成功响应
        // 调用 repository 类的 getList 方法获取数据并返回 JSON 格式的成功响应
        return app('json')->success($this->repository->getList($this->request->merId()));
    }


    /**
     * 创建数据
     * @param validate $validate 验证器实例
     * @return \think\response\Json
     */
    public function create(validate $validate)
    {
        // 校验参数
        $data = $this->checkParams($validate);
        // 设置商家 ID
        $data['mer_id'] = $this->request->merId();
        // 调用 repository 类的 create 方法创建数据并返回 JSON 格式的成功响应
        $this->repository->create($data);
        return app('json')->success('添加成功');
    }


    /**
     * 获取指定ID的商品详情
     *
     * @param int $id 商品ID
     * @return \think\response\Json
     */
    public function detail($id)
    {
        if (!$this->repository->merExists($this->request->merId(), $id))
            // 如果不存在则返回失败信息
            return app('json')->fail('数据不存在');
        // 如果存在则返回商品详情
        return app('json')->success($this->repository->getOne($id));
    }


    /**
     * 更新指定ID的数据
     *
     * @param int $id 数据ID
     * @param \think\Validate $validate 验证器实例
     * @return \think\response\Json 操作成功的JSON格式结果
     */
    public function update($id, validate $validate)
    {
        // 获取验证通过的数据
        // 获取验证通过的数据
        $data = $this->checkParams($validate);
        // 判断数据是否存在
        if (!$this->repository->merExists($this->request->merId(), $id))
            return app('json')->fail('数据不存在');
        // 更新数据
        $this->repository->update($id, $data, $this->request->merId());

        // 返回操作成功的JSON格式结果
        // 返回操作成功的JSON格式结果
        return app('json')->success('编辑成功');
    }


    /**
     * 根据ID删除商品模板
     *
     * @param int $id 商品模板ID
     * @return \Illuminate\Http\JsonResponse 返回JSON格式的操作结果
     */
    public function delete($id)
    {
        if (!$this->repository->merExists($this->request->merId(), $id))
            return app('json')->fail('数据不存在');
        if ($this->repository->merDefaultExists($this->request->merId(), $id))
            return app('json')->fail('默认模板不能删除');
        if ($this->repository->getProductUse($this->request->merId(), $id))
            return app('json')->fail('模板使用中，不能删除');
        // 删除商品模板
        $this->repository->delete($id);
        // 返回操作成功的JSON格式结果
        return app('json')->success('删除成功');
    }

    /**
     * 检查参数是否符合要求
     *
     * @param validate $validate 验证器对象
     * @return array 返回符合要求的参数数组
     */
    public function checkParams(validate $validate)
    {
        $data = $this->request->params(['name', 'type', 'appoint', 'undelivery', 'region', 'free', 'undelives', 'sort', 'info']);
        // 使用验证器对参数进行校验
        $validate->check($data);
        // 返回符合要求的参数数组
        return $data;
    }


    /**
     * 设置默认模板
     * @param $id
     * @return \think\response\Json
     *
     * @date 2023/10/07
     * @author yyw
     */
    public function setDefault($id)
    {
        if (!$this->repository->merExists($this->request->merId(), $id))
            return app('json')->fail('数据不存在');
        if ($this->repository->merDefaultExists($this->request->merId(), $id))
            return app('json')->fail('当前模板已是默认模板了');

        $this->repository->setDefault($this->request->merId(), $id);
        return app('json')->success('设置成功');
    }
}
