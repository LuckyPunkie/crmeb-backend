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
namespace app\controller\merchant\store\product;

use app\common\repositories\store\product\StoreDiscountRepository;
use app\validate\merchant\StoreDiscountsValidate;
use crmeb\basic\BaseController;
use think\App;
use think\exception\ValidateException;

class Discounts extends BaseController
{

    protected  $repository ;

    /**
     * Product constructor.
     * @param App $app
     * @param StoreDiscountRepository $repository
     */
    public function __construct(App $app ,StoreDiscountRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 获取列表
     */
    public function lst()
    {
        // 获取分页参数
        [$page, $limit] = $this->getPage();
        // 获取状态参数
        $status = $this->request->param('status');
        // 获取其他查询条件
        $where = $this->request->params(['keyword', 'store_name', 'title', 'type']);
        // 设置商家ID查询条件
        $where['is_show'] = $status;

        $where['mer_id'] = $this->request->merId();
        // 调用仓库方法获取数据
        $data = $this->repository->getMerlist($where, $page, $limit);
        // 返回JSON格式数据
        return app('json')->success($data);
    }

    /**
     * 创建数据
     */
    public function create()
    {
        // 校验参数
        $data = $this->checkParams();
        // 保存数据
        $this->repository->save($data);
        return app('json')->success('添加成功');
    }

    /**
     * 更新数据
     * @param int $id 数据ID
     */
    public function update($id)
    {
        // 校验参数
        $data = $this->checkParams();
        // 判断数据是否存在
        if (!$this->repository->getWhere(['mer_id' => $data['mer_id'], $this->repository->getPk() => $id]))
            return app('json')->fail('数据不存在');
        // 设置主键值
        $data['discount_id'] = $id;
        // 保存数据
        $this->repository->save($data);
        return app('json')->success('编辑成功');
    }

    /**
     * 获取详情
     * @param int $id 数据ID
     */
    public function detail($id)
    {
        // 获取数据
        $data = $this->repository->detail($id, $this->request->merId());
        if (!$data) return app('json')->fail('数据不存在');
        // 返回JSON格式数据
        return app('json')->success($data);
    }

    /**
     * 切换状态
     * @param int $id 数据ID
     */
    public function switchStatus($id)
    {
        $status = $this->request->param('status') == 1 ?: 0;

        if (!$this->repository->getWhere([$this->repository->getPk() => $id, 'mer_id' => $this->request->merId()]))
            return app('json')->fail('数据不存在');
        $this->repository->update($id, ['is_show' => $status]);
        return app('json')->success('修改成功');
    }

    /**
     * 根据ID删除数据
     * @param int $id 数据ID
     * @return \think\response\Json 返回JSON格式的删除结果
     */
    public function delete($id)
    {
        // 判断数据是否存在
        if (!$this->repository->getWhere([$this->repository->getPk() => $id, 'mer_id' => $this->request->merId()]))
            return app('json')->fail('数据不存在');
        // 更新数据状态为已删除
        $this->repository->update($id, ['is_del' => 1]);
        // 返回删除成功的JSON格式数据
        return app('json')->success('删除成功');
    }

    /**
     * 校验参数
     * @return array 返回校验后的参数数组
     * @throws \app\common\exception\ValidateException 如果参数校验失败则抛出异常
     */
    public function checkParams()
    {
        // 定义参数数组
        $params = [
            ['title', ''],
            ['image', ''],
            ['type', 0],
            ['is_limit', 0],
            ['limit_num', 0],
            ['is_time', 0],
            ['time', []],
            ['sort', 0],
            ['free_shipping', 0],
            ['status', 0],
            ['is_show', 1],
            ['products', []],
            ['temp_id', 0],
        ];
        // 获取请求参数并校验
        $data = $this->request->params($params);
        app()->make(StoreDiscountsValidate::class)->check($data);

        // 如果设置了时间限制，则校验时间格式和时间范围
        if ($data['is_time'] && is_array($data['time'])) {
            if (empty($data['time'])) throw new ValidateException('开始时间必须填写');
            [$start, $end] = $data['time'];
            $start = strtotime($start);
            $end = strtotime($end);
            if ($start > $end) {
                throw new ValidateException('开始时间必须小于结束时间');
            }
            if ($start < time() || $end < time()) {
                throw new ValidateException('套餐时间不能小于当前时间');
            }
        }
        // 校验商品属性
        foreach ($data['products'] as $item) {
            if (!isset($item['items']))
                throw new ValidateException('请选择' . $item['store_name'] . '的规格');
            foreach ($item['attr'] as $attr) {
                if ($attr['active_price'] > $attr['price']) throw new ValidateException('套餐价格高于原价');
            }
        }
        $data['mer_id'] = $this->request->merId();
        return $data;
    }


}
