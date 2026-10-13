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

use app\common\repositories\store\product\ProductAssistRepository as repository;
use app\common\repositories\store\product\ProductRepository;
use app\common\repositories\store\product\SpuRepository;
use crmeb\basic\BaseController;
use think\App;
use app\validate\merchant\StoreProductAssistValidate;
use think\exception\ValidateException;

class ProductAssist extends BaseController
{
    protected  $repository ;

    /**
     * Product constructor.
     * @param App $app
     * @param repository $repository
     */
    public function __construct(App $app ,repository $repository)
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
        $where = $this->request->params(['product_status','keyword','is_show','type','presell_type','us_status','product_assist_id','mer_labels']);
        $where['mer_id'] = $this->request->merId();
        return app('json')->success($this->repository->getMerchantList($where,$page,$limit));
    }

    /**
     * 添加
     * @param StoreProductAssistValidate $validate
     * @return mixed
     * @author Qinii
     * @day 2020-10-12
     */
    public function create(StoreProductAssistValidate $validate)
    {
        $data = $this->checkParams($validate);
        $this->repository->create($this->request->merId(),$data);
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
        $data = $this->repository->detail($this->request->merId(),$id);
        return app('json')->success($data);
    }

    /**
     * 更新指定ID的商品辅助信息
     *
     * @param int $id 商品辅助信息ID
     * @param StoreProductAssistValidate $validate 商品辅助信息验证器
     * @return \Psr\Http\Message\ResponseInterface JSON格式的编辑成功结果
     */
    public function update($id, StoreProductAssistValidate $validate)
    {
        // 获取参数并验证
        // 获取参数并验证
        $data = $this->checkParams($validate->isUpdate());
        // 调用仓库的编辑方法
        $this->repository->edit($id, $data);
        // 返回JSON格式的编辑成功结果
        // 返回JSON格式的编辑成功结果
        return app('json')->success('编辑成功');
    }


    /**
     * 删除商品助力活动
     * @param int $id 商品助力活动ID
     * @return \think\response\Json 返回JSON格式的删除结果
     */
    public function delete($id)
    {
        // 构造查询条件
        $where = [
            $this->repository->getPk() => $id,
            'mer_id' => $this->request->merId()
        ];
        // 调用仓库的删除方法
        $this->repository->delete($where);
        // 返回JSON格式的删除成功结果
        return app('json')->success('删除成功');
    }

    /**
     * 切换商品助力活动状态
     * @param int $id 商品助力活动ID
     * @return \think\response\Json 返回JSON格式的修改结果
     */
    public function switchStatus($id)
    {
        // 获取状态值
        $status = $this->request->param('status', 0) == 1 ? 1 : 0;
        if (!$this->repository->detail($this->request->merId(), $id))
            return app('json')->fail('数据不存在');
        // 调用仓库的更新方法
        $this->repository->update($id, ['is_show' => $status]);
        app()->make(SpuRepository::class)->changeStatus($id, 3);
        // 返回JSON格式的修改成功结果
        return app('json')->success('修改成功');
    }

    /**
     * 检查参数是否合法
     *
     * @param StoreProductAssistValidate $validate 商品助力验证器
     * @return array 返回参数数组
     * @throws ValidateException 如果限量大于库存则抛出异常
     */
    public function checkParams(StoreProductAssistValidate $validate)
    {
        // 定义参数数组
        // 定义参数数组
        $params = [
            "image", "slider_image", "store_name", "store_info", "product_id", "is_show", "temp_id", "attrValue", "guarantee_template_id",
            "start_time", "end_time", "assist_user_count", "assist_count", "status", "pay_count", "product_status", "sort", 'mer_labels', 'delivery_way', 'delivery_free',
        ];
        // 获取请求参数
        $data = $this->request->params($params);
        // 检查属性值库存是否合法
        foreach ($data['attrValue'] as $datum) {
            if ($datum['stock'] > $datum['old_stock']) throw new ValidateException('限量不能大于库存');
        }
        // 验证参数是否合法
        $validate->check($data);
        // 返回参数数组
        return $data;
    }


    /**
     * 更新商品排序
     *
     * @param int $id 商品ID
     * @return \think\response\Json 返回JSON格式的修改结果
     */
    public function updateSort($id)
    {
        // 获取请求参数中的排序值
        $sort = $this->request->param('sort');
        $this->repository->updateSort($id, $this->request->merId(), ['sort' => $sort]);
        // 返回JSON格式的修改成功结果
        return app('json')->success('修改成功');
    }

    /**
     * 预览商品
     *
     * @param ProductRepository $repository 商品仓库实例
     * @return \think\response\Json 返回JSON格式的预览结果
     */
    public function preview(ProductRepository $repository)
    {
        $data = $this->request->param();
        // 设置商家信息
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
        // 设置商家ID和状态
        $data['mer_id'] = $this->request->merId();
        $data['status'] = 1;
        $data['mer_status'] = 1;
        $data['rate'] = 3;
        // 调用商品仓库的预览方法并返回JSON格式的预览结果
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
        app()->make(SpuRepository::class)->setLabels($id, 3, $data, $this->request->merId());
        // 返回成功信息
        return app('json')->success('修改成功');
    }


}
