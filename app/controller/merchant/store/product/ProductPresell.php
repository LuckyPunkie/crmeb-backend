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

use app\common\repositories\store\product\ProductPresellRepository as repository;
use app\common\repositories\store\product\ProductRepository;
use app\common\repositories\store\product\SpuRepository;
use crmeb\basic\BaseController;
use think\App;
use app\validate\merchant\StoreProductPresellValidate;
use think\exception\ValidateException;

class ProductPresell extends BaseController
{
    protected  $repository;

    /**
     * Product constructor.
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
     * @author Qinii
     * @day 2020-10-12
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['product_status', 'keyword', 'type', 'presell_type', 'is_show', 'us_status','product_presell_id','mer_labels']);
        $where['mer_id'] = $this->request->merId();
        return app('json')->success($this->repository->getMerchantList($where, $page, $limit));
    }

    /**
     * 添加
     * @param StoreProductPresellValidate $validate
     * @return mixed
     * @author Qinii
     * @day 2020-10-12
     */
    public function create(StoreProductPresellValidate $validate)
    {
        $data = $this->checkParams($validate);
        $this->repository->create($this->request->merId(), $data);
        return app('json')->success('添加成功');
    }

    /**
     * 详情
     * @param $id
     * @return mixed
     * @author Qinii
     * @day 2020-10-12
     */
    public function detail($id)
    {
        $data = $this->repository->detail($this->request->merId(), $id);
        return app('json')->success($data);
    }

    /**
     *
     * @param $id
     * @param StoreProductPresellValidate $validate
     * @return mixed
     * @author Qinii
     * @day 2020-10-13
     */
    public function update($id, StoreProductPresellValidate $validate)
    {
        $data = $this->checkParams($validate);
        $where = [
            $this->repository->getPk() => $id,
            'mer_id' => $this->request->merId()
        ];
        if (!$this->repository->getWhere($where))
            return app('json')->fail('数据不存在');
        $data['mer_id'] = $this->request->merId();
        $this->repository->edit($id, $data);
        return app('json')->success('编辑成功');
    }


    /**
     * 根据ID删除数据
     *
     * @param int $id 要删除的数据ID
     * @return \think\response\Json 返回JSON格式的删除结果
     */
    public function delete($id)
    {
        // 构造查询条件
        $where = [
            $this->repository->getPk() => $id, // 主键ID
            'mer_id' => $this->request->merId() // 商家ID
        ];
        // 调用Repository的delete方法删除数据
        $this->repository->delete($where);
        // 返回JSON格式的删除结果
        return app('json')->success('删除成功');
    }


    /**
     * 切换商品状态
     *
     * @param int $id 商品ID
     * @return \think\response\Json
     */
    public function switchStatus($id)
    {
        // 获取请求参数中的状态值，默认为0
        $status = $this->request->param('status', 0) == 1 ? 1 : 0;
        // 判断商品是否存在
        if (!$this->repository->detail($this->request->merId(), $id))
            return app('json')->fail('数据不存在');
        // 更新商品状态
        $this->repository->update($id, ['is_show' => $status]);
        // 调用SpuRepository类的changeStatus方法，将商品状态改为2
        app()->make(SpuRepository::class)->changeStatus($id, 2);
        // 返回操作结果
        return app('json')->success('修改成功');
    }


    /**
     * 获取数字统计信息
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function number()
    {
        // merId 方法用于获取当前请求的商户 ID
        return app('json')->success($this->repository->stat($this->request->merId()));
    }


    /**
     * 检查参数是否合法
     *
     * @param StoreProductPresellValidate $validate 商品预售验证器
     * @return array 返回合法的参数数组
     * @throws ValidateException 如果限量大于库存则抛出异常
     */
    public function checkParams(StoreProductPresellValidate $validate)
    {
        // 定义需要验证的参数列表
        $params = [
            "image", "slider_image", "store_name", "store_info", "product_id", "is_show", "temp_id", "attrValue", "sort", "guarantee_template_id",
            "start_time", "end_time", "final_start_time", "final_end_time", "status", "presell_type", 'pay_count', "delivery_type", "delivery_day",
            "product_status", 'mer_labels', 'delivery_way', 'delivery_free'
        ];
        // 获取请求参数
        $data = $this->request->params($params);
        // 遍历属性值数组，判断限量是否大于库存
        foreach ($data['attrValue'] as $datum) {
            if ($datum['stock'] > $datum['old_stock']) throw new ValidateException('限量不能大于库存');
        }
        // 使用验证器验证参数是否合法
        $validate->check($data);
        // 返回合法的参数数组
        return $data;
    }


    /**
     * 更新指定ID的记录的排序值
     *
     * @param int $id 记录ID
     * @return \think\response\Json 返回JSON格式的操作结果
     */
    public function updateSort($id)
    {
        // 从请求参数中获取排序值
        $sort = $this->request->param('sort');
        // 调用仓库类的更新排序方法，传入记录ID、商家ID和排序值
        $this->repository->updateSort($id, $this->request->merId(), ['sort' => $sort]);
        // 返回JSON格式的操作结果，表示修改成功
        return app('json')->success('修改成功');
    }


    /**
     * 预览商品
     *
     * @param ProductRepository $repository 商品仓库
     * @return \think\response\Json
     */
    public function preview(ProductRepository $repository)
    {
        // 获取请求参数
        $data = $this->request->param();
        // 添加商家信息到请求参数中
        $data['merchant'] = [
            'mer_name' => $this->request->merchant()->mer_name,
            'is_trader' => $this->request->merchant()->is_trader,
            'mer_avatar' => $this->request->merchant()->mer_avatar,
            'product_score' => $this->request->merchant()->product_score,
            'service_score' => $this->request->merchant()->service_score,
            'postage_score' => $this->request->merchant()->postage_score,
            'service_phone' => $this->request->merchant()->service_phone,
            'care_count' => $this->request->merchant()->care_count,
            'type_name' => $this->request->merchant()->type_name->type_name ?? '',
            'care' => true,
            'recommend' => $this->request->merchant()->recommend,
        ];
        // 添加商家ID和状态到请求参数中
        $data['mer_id'] = $this->request->merId();
        $data['status'] = 1;
        $data['mer_status'] = 1;
        $data['rate'] = 3;
        // 调用商品仓库的预览方法并返回结果
        return app('json')->success($repository->preview($data));
    }


    /**
     * 设置商品标签
     *
     * @param int $id 商品ID
     * @return \think\response\Json
     */
    public function setLabels($id)
    {
        // 从请求参数中获取标签数据
        $data = $this->request->params(['mer_labels']);
//        if (empty($data['mer_labels'])) return app('json')->fail('标签为空');

        // 调用 SpuRepository 类的 setLabels 方法，设置商品标签
        app()->make(SpuRepository::class)->setLabels($id, 2, $data, $this->request->merId());
        // 返回成功信息
        return app('json')->success('修改成功');
    }

}
