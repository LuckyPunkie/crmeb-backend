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

use app\common\repositories\store\product\ProductRepository;
use app\common\repositories\store\product\SpuRepository;
use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\store\product\ProductGroupRepository;
use app\validate\merchant\StoreProductGroupValidate;
use think\exception\ValidateException;

class ProductGroup extends BaseController
{
    protected  $repository ;

    /**
     * ProductGroup constructor.
     * @param App $app
     * @param ProductGroupRepository $repository
     */
    public function __construct(App $app ,ProductGroupRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 获取商家列表
     *
     * @return \think\response\Json
     */
    public function lst()
    {
        // 获取分页参数
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['product_status', 'keyword', 'active_type', 'status', 'us_status', 'product_group_id', 'mer_labels']);
        // 设置查询条件
        $where['is_show'] = $where['status'];
        unset($where['status']);
        $where['mer_id'] = $this->request->merId();
        return app('json')->success($this->repository->getMerchantList($where, $page, $limit));
    }

    /**
     * 创建商品组
     *
     * @param StoreProductGroupValidate $validate 商品组验证器
     * @return \think\response\Json
     */
    public function create(StoreProductGroupValidate $validate)
    {
        // 校验参数
        $data = $this->checkParams($validate);
        $this->repository->create($this->request->merId(), $data);
        // 返回操作成功结果
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
        // 构建查询条件
        $where = [
            $this->repository->getPk() => $id,
            'mer_id' => $this->request->merId()
        ];
        if (!$this->repository->getWhere($where))
            return app('json')->fail('数据不存在');
        $data = $this->repository->detail($this->request->merId(), $id);
        // 返回成功响应
        return app('json')->success($data);
    }

    /**
     * 根据ID删除商品组
     *
     * @param int $id 商品组ID
     * @return \think\response\Json
     */
    public function delete($id)
    {
        // 构建查询条件
        $where = [
            $this->repository->getPk() => $id, // 主键ID
            'mer_id' => $this->request->merId() // 商家ID
        ];
        // 判断数据是否存在
        if (!$this->repository->getWhere($where))
            return app('json')->fail('数据不存在');
        // 更新商品组状态为已删除
        $this->repository->update($id, ['is_del' => 1]);
        // 触发商品组删除事件
        event('product.groupDelete', compact('id'));
        // 修改SPU状态为已删除
        app()->make(SpuRepository::class)->changeStatus($id, 4);
        // 返回删除成功信息
        return app('json')->success('删除成功');
    }


    /**
     * 更新商品分组
     *
     * @param int $id 商品分组ID
     * @param StoreProductGroupValidate $validate 验证器实例
     * @return \think\response\Json
     */
    public function update($id, StoreProductGroupValidate $validate)
    {
        // 获取验证通过的参数
        $data = $this->checkParams($validate);
        // 构建查询条件
        $where = [
            $this->repository->getPk() => $id,
            'mer_id' => $this->request->merId()
        ];
        // 判断数据是否存在
        if (!$this->repository->getWhere($where))
            return app('json')->fail('数据不存在');

        // 更新数据
        $this->repository->edit($id, $data);
        // 返回操作结果
        return app('json')->success('编辑成功');
    }


    /**
     * 切换商品组状态
     *
     * @param int $id 商品组ID
     * @return \think\response\Json
     */
    public function switchStatus($id)
    {
        // 获取状态值
        $status = $this->request->param('status', 0) == 1 ? 1 : 0;
        if (!$this->repository->detail($this->request->merId(), $id))
            return app('json')->fail('数据不存在');
        // 更新商品组状态
        $this->repository->update($id, ['is_show' => $status]);
        app()->make(SpuRepository::class)->changeStatus($id, 4);
        return app('json')->success('修改成功');
    }


    /**
     * 检查参数并返回数据
     * @param StoreProductGroupValidate $validate 商品组验证器
     * @return array 返回验证后的数据
     * @throws ValidateException 如果开团人数小于2或活动开始时间大于结束时间或团长分佣比例不在0-100之间则抛出异常
     */
    public function checkParams(StoreProductGroupValidate $validate)
    {
        // 定义需要验证的参数
        $params = [
            "image", "slider_image", "store_name", "store_info", "product_id", "is_show", "temp_id", "once_pay_count", "guarantee_template_id",
            "start_time", "end_time", "buying_num", "buying_count_num", "status", "pay_count", "time", "ficti_status", "ficti_num", "attrValue",
            'unit_name', 'content', 'sort', 'mer_labels', 'delivery_way', 'delivery_free', ['leader_extension', 0], ['leader_rate', 0]
        ];
        // 获取请求参数
        $data = $this->request->params($params);
        // 如果开团人数小于2则抛出异常
        if ($data['buying_count_num'] < 2) throw new ValidateException('开团人数不得少于2人');
        // 如果活动开始时间大于结束时间则抛出异常
        if ($data['end_time'] < $data['start_time']) throw new ValidateException('活动开始时间必须大于结束时间');
        // 如果团长分佣比例不在0-100之间则抛出异常
        if ($data['leader_extension'] && ($data['leader_rate'] < 0 || $data['leader_rate'] > 100)) {
            throw new ValidateException('团长分佣比例需在0-100之间');
        }
        // 验证数据
        $validate->check($data);
        // 返回验证后的数据
        return $data;
    }

    /**
     * 更新商品组排序
     * @param int $id 商品组ID
     * @return \think\response\Json 返回操作结果
     */
    public function updateSort($id)
    {
        $sort = $this->request->param('sort');
        // 调用商品组仓库的更新排序方法
        $this->repository->updateSort($id, $this->request->merId(), ['sort' => $sort]);
        // 返回操作成功提示
        return app('json')->success('修改成功');
    }


    /**
     * 预览商品信息
     *
     * @param ProductRepository $repository 商品仓库对象
     * @return \think\response\Json 返回JSON格式的预览结果
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
        // 添加商家ID和状态信息到请求参数中
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
     * @return \think\response\Json 返回JSON格式的操作结果
     */
    public function setLabels($id)
    {
        // 获取请求参数中的标签信息
        $data = $this->request->params(['mer_labels']);
//        if (empty($data['mer_labels'])) return app('json')->fail('标签为空');

        // 调用SPU仓库的设置标签方法并返回JSON格式的操作结果
        app()->make(SpuRepository::class)->setLabels($id, 4, $data, $this->request->merId());
        return app('json')->success('修改成功');
    }

}
