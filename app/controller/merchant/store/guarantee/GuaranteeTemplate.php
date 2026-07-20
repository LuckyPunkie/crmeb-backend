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
namespace app\controller\merchant\store\guarantee;

use app\common\repositories\store\GuaranteeRepository;
use app\common\repositories\store\GuaranteeTemplateRepository;
use app\validate\admin\GuaranteeTemplateValidate;
use think\App;
use crmeb\basic\BaseController;

class GuaranteeTemplate extends BaseController
{
    /**
     * @var GuaranteeTemplateRepository
     */
    protected $repository;

    /**
     * Product constructor.
     * @param App $app
     * @param GuaranteeTemplateRepository $repository
     */
    public function __construct(App $app, GuaranteeTemplateRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 获取保障模板列表
     * @return \think\response\Json
     */
    public function lst()
    {
        // 获取分页参数
        [$page, $limit] = $this->getPage();
        // 获取查询条件
        $where = $this->request->params(['date', 'keyword']);
        // 设置查询条件
        $where['is_del'] = 0;
        $where['mer_id'] = $this->request->merId();
        // 获取保障模板列表
        $data = $this->repository->getList($where, $page, $limit);
        // 返回成功响应
        return app('json')->success($data);
    }

    /**
     * 创建保障模板
     * @param GuaranteeTemplateValidate $validate 验证器实例
     * @return \think\response\Json
     */
    public function create(GuaranteeTemplateValidate $validate)
    {
        // 获取保障模板数据
        $data = $this->request->params(['template_name', 'template_value', ['status', 1], 'sort']);
        // 验证保障模板数据
        $validate->check($data);
        // 设置商家ID
        $data['mer_id'] = $this->request->merId();
        // 创建保障模板
        $this->repository->create($data);
        return app('json')->success('添加成功');
    }

    /**
     * 获取保障模板详情
     * @param int $id 保障模板ID
     * @return \think\response\Json
     */
    public function detail($id)
    {
        // 获取保障模板详情
        $ret = $this->repository->detail($id, $this->request->merId());
        return app('json')->success($ret);
    }

    /**
     * 修改保障模板
     * @param int $id 保障模板ID
     * @param GuaranteeTemplateValidate $validate 验证器实例
     * @return \think\response\Json
     */
    public function update($id, GuaranteeTemplateValidate $validate)
    {
        // 获取保障模板数据
        $data = $this->request->params(['template_name', 'template_value', ['status', 1], 'sort']);
        // 验证保障模板数据
        $validate->check($data);
        $this->repository->detail($id, $this->request->merId());

        // 设置商家ID
        $data['mer_id'] = $this->request->merId();
        $this->repository->edit($id, $data);

        return app('json')->success('编辑成功');
    }

    /**
     * 根据ID删除记录
     *
     * @param int $id 记录ID
     * @return \Illuminate\Http\JsonResponse 返回JSON格式的成功提示信息
     */
    public function delete($id)
    {
        // 获取指定ID的记录详情
        $this->repository->detail($id, $this->request->merId());

        // 删除指定ID的记录
        $this->repository->delete($id);

        // 返回JSON格式的成功提示信息
        return app('json')->success('删除成功');
    }


    /**
     * 添加模板筛选的条款数据
     * @return \think\response\Json
     * @author Qinii
     * @day 5/25/21
     */
    public function select()
    {
        $where['keyword'] = $this->request->param('keyword');
        $where['is_del'] = 0;
        $where['status'] = 1;
        $data = app()->make(GuaranteeRepository::class)->select($where);

        return app('json')->success($data);
    }

    /**
     * 对指定ID的数据进行排序操作
     *
     * @param int $id 数据ID
     * @return \think\response\Json 返回JSON格式的响应结果
     */
    public function sort($id)
    {
        $ret = $this->repository->detail($id, $this->request->merId());
        if (!$ret) return app('json')->fail('数据不存在');
        // 构造需要更新的数据
        $data = [
            'sort' => $this->request->param('sort'),
        ];
        $this->repository->update($id, $data);

        // 返回成功信息
        return app('json')->success('修改成功');
    }


    /**
     * 商品选择模板的下拉数据
     * @return \think\response\Json
     * @author Qinii
     * @day 5/25/21
     */
    public function list()
    {
        $data = $this->repository->list($this->request->merId());
        return app('json')->success($data);
    }

    /**
     * 切换状态
     *
     * @param int $id 记录ID
     * @return \think\response\Json
     */
    public function switchStatus($id)
    {
        $ret = $this->repository->detail($id, $this->request->merId());
        if (!$ret) return app('json')->fail('数据不存在');
        // 构造更新数据
        $data = [
            'status' => $this->request->param('status') == 1 ?: 0,
        ];
        $this->repository->update($id, $data);

        // 返回成功信息
        return app('json')->success('修改成功');
    }

}
