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

use app\common\repositories\store\GuaranteeTemplateRepository;
use app\common\repositories\store\product\CdkeyLibraryRepository;
use app\common\repositories\store\product\ProductAttrValueRepository;
use app\common\repositories\store\product\ProductBatchProcessRepository;
use app\common\repositories\store\product\SpuRepository;
use app\common\repositories\store\shipping\ShippingTemplateRepository;
use app\common\repositories\store\StoreCategoryRepository;
use app\common\repositories\system\operate\OperateLogRepository;
use crmeb\services\UploadService;
use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\store\product\ProductRepository as repository;
use think\exception\ValidateException;
use think\facade\Cache;

class Product extends BaseController
{
    protected $repository;

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
     * 获取列表数据
     *
     * @return \think\response\Json
     */
    public function lst()
    {
        // 获取分页参数
        [$page, $limit] = $this->getPage();
        // 获取查询参数
        $type = $this->request->param('type', 1);
        $where = $this->request->params([
            'temp_id',
            'cate_id',
            'keyword',
            'mer_cate_id',
            'is_gift_bag',
            'status',
            'us_status',
            'product_id',
            'mer_labels',
            ['order', 'sort'],
            'is_ficti',
            'svip_price_type',
            'filters_type',
            'is_action',
            'is_good',
            'not_product_id',
            'form_id',
            'mer_form_id',
            'cate_hot',
            'brand_id',
            'activity_label_ids'
        ]);
        // 根据类型和商家ID获取查询条件
        $where = array_merge($where, $this->repository->switchType($type, $this->request->merId(), 0));
        // 调用仓库方法获取列表数据并返回JSON格式的响应
        return app('json')->success($this->repository->getList($this->request->merId(), $where, $page, $limit));
    }


    /**
     * 获取商品详情
     * @param int $id 商品ID
     * @return \think\response\Json
     */
    public function detail($id)
    {
        // 判断商品是否存在
        if (!$this->repository->merExists($this->request->merId(), $id))
            return app('json')->fail('数据不存在');
        // 返回商品详情
        $is_copy = $this->request->param('is_copy',0);
        return app('json')->success($this->repository->getAdminOneProduct($id, null,0,$is_copy));
    }

    public function getEdit($id)
    {
        // 判断商品是否存在
        if (!$this->repository->merExists($this->request->merId(), $id))
            return app('json')->fail('数据不存在');
        // 返回商品详情
        $is_copy = $this->request->param('is_copy',0);
        $data = $this->repository->getEdit($id, $is_copy);
        return app('json')->success($data);
    }

    /**
     * 创建商品
     * @return \think\response\Json
     */
    public function create()
    {
        // 获取参数
        $params = $this->request->params($this->repository::CREATE_PARAMS);
        // 校验参数
        $data = $this->repository->checkParams($params, $this->request->merId());
        // 设置商家ID和管理员ID
        $data['mer_id'] = $this->request->merId();
        $data['admin_id'] = $this->request->merAdminId();
        // 判断是否为礼包商品并且商家礼包数量是否超过限制
        if ($data['is_gift_bag'] && !$this->repository->checkMerchantBagNumber($data['mer_id']))
            return app('json')->fail('礼包数量超过数量限制');
        // 设置商品状态
        $data['status'] = $this->request->merchant()->is_audit ? 0 : 1;
        // 设置商家状态
        $data['mer_status'] = ($this->request->merchant()->is_del || !$this->request->merchant()->mer_state || !$this->request->merchant()->status) ? 0 : 1;
        // 设置评分
        $data['rate'] = 5;
        // 设置管理员信息
        $data['admin_info'] = $this->request->adminInfo();
        // 创建商品
        $this->repository->create($data, 0);
        // 返回创建成功信息
        return app('json')->success('添加成功');
    }

    /**
     * 修改商品
     * @param int $id 商品ID
     * @return \think\response\Json
     */
    public function update($id)
    {
        // 获取参数
        $params = $this->request->params($this->repository::CREATE_PARAMS);
        $data = $this->repository->checkParams($params, $this->request->merId(), $id);
        // 判断商品是否存在
        if (!$this->repository->merExists($this->request->merId(), $id))
            return app('json')->fail('数据不存在');
        // 获取商品信息
        $pro = $this->repository->getWhere(['product_id' => $id]);

        if ($pro->status == -2) {
            $data['status'] = 0;
        } else {
            // 设置商品状态
            $data['status'] = $this->request->merchant()->is_audit ? 0 : 1;
        }
        // 设置商家状态
        $data['mer_status'] = ($this->request->merchant()->is_del || !$this->request->merchant()->mer_state || !$this->request->merchant()->status) ? 0 : 1;
        // 设置商家ID和管理员ID
        $data['mer_id'] = $this->request->merId();
        // 设置管理员信息
        $data['admin_info'] = $this->request->adminInfo();

        $productData = $this->repository->edit($id, $data, $this->request->merId(), 0);
        $product = $productData->toArray();
        ksort($product);
        $cache_unique = 'get_product_show_' . $id . '_' . md5(json_encode($product));
        Cache::delete($cache_unique);
        return app('json')->success('编辑成功');
    }


    /**
     * 删除商品
     *
     * @param int $id 商品ID
     * @return \think\response\Json
     */
    public function delete($id)
    {
        // 判断商品是否存在
        if (!$this->repository->merExists($this->request->merId(), $id))
            return app('json')->fail('数据不存在');
        // 判断商品是否已经上架
        if ($this->repository->getWhereCount(['product_id' => $id, 'is_show' => 1, 'status' => 1]))
            return app('json')->fail('商品上架中');
        // 删除商品
        $this->repository->delete($id);
        //queue(ChangeSpuStatusJob::class,['product_type' => 0,'id' => $id]);
        app()->make(CdkeyLibraryRepository::class)->cancel($id);
        // 返回操作结果
        return app('json')->success('转入回收站');
    }

    /**
     * 批量永久删除商品
     *
     * @return \think\response\Json
     */
    public function batchDelete()
    {
        $ids = $this->request->param('ids');
        $ids = is_array($ids) ? $ids : explode(',', (string)$ids);
        $ids = array_values(array_filter(array_unique(array_map('intval', $ids))));

        if (empty($ids))
            return app('json')->fail('请选择商品');

        $existsIds = $this->repository->getOnlyTranshed(['mer_id' => $this->request->merId()])
            ->whereIn('product_id', $ids)
            ->column('product_id');
        sort($existsIds);
        $checkIds = $ids;
        sort($checkIds);
        if ($existsIds != $checkIds)
            return app('json')->fail('只能删除回收站的商品');

        foreach ($ids as $id) {
            $seckillProduct = $this->repository->getWhere(['old_product_id' => $id, 'is_del' => 0, 'product_type' => 1], 'product_id, store_name, is_show');
            if($seckillProduct) {
                return app('json')->fail('该商品有秒杀商品不可删除，请先删除关联秒杀商品再操作！秒杀商品ID：'. $seckillProduct->product_id);
            }
        }

        foreach ($ids as $id) {
            $this->repository->destory($id);
        }

        return app('json')->success('删除成功');
    }


    /**
     * 永久删除商品
     *
     * @param int $id 商品ID
     * @return \think\response\Json
     */
    public function destory($id)
    {
        // 判断商品是否存在于回收站
        if (!$this->repository->merDeleteExists($this->request->merId(), $id))
            return app('json')->fail('只能删除回收站的商品');
//        if(app()->make(StoreCartRepository::class)->getProductById($id))
//            return app('json')->fail('商品有被加入购物车不可删除');

        $seckillProduct = $this->repository->getWhere(['old_product_id' => $id, 'is_del' => 0, 'product_type' => 1], 'product_id, store_name, is_show');
        if($seckillProduct) {
            return app('json')->fail('该商品有秒杀商品不可删除，请先删除关联秒杀商品再操作！秒杀商品ID：'. $seckillProduct->product_id);
        }
        // 永久删除商品
        $this->repository->destory($id);
        return app('json')->success('删除成功');
    }


    /**
     * 获取状态过滤器
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStatusFilter()
    {
        // 获取查询参数
        $where = $this->request->params([
            'temp_id',
            'cate_id',
            'keyword',
            'mer_cate_id',
            'is_gift_bag',
            'status',
            'us_status',
            'product_id',
            'mer_labels',
            ['order', 'sort'],
            'is_ficti',
            'svip_price_type',
            'filters_type',
            'is_action',
            'is_good',
            'not_product_id',
            'form_id',
            'mer_form_id',
            'cate_hot',
            'brand_id',
            'activity_label_ids'
        ]);
        // 调用 repository 的 getFilter 方法获取商品状态过滤器
        return app('json')->success($this->repository->getFilter($this->request->merId(), $where, '商品', 0));
    }

    /**
     * 配置信息
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function config()
    {
        $data = systemConfig(['extension_status', 'svip_switch_status', 'integral_status', 'extension_one_rate', 'extension_two_rate']);
        $merData = merchantConfig($this->request->merId(), ['mer_integral_status', 'mer_integral_rate', 'mer_svip_status', 'svip_store_rate']);
        // 计算商家 svip 店铺比例
        $svip_store_rate = $merData['svip_store_rate'] > 0 ? bcdiv($merData['svip_store_rate'], 100, 2) : 0;
        // 判断商家 svip 状态
        $data['mer_svip_status'] = ($data['svip_switch_status'] && $merData['mer_svip_status'] != 0) ? 1 : 0;
        // 设置商家 svip 店铺比例
        $data['svip_store_rate'] = $svip_store_rate;
        // 判断积分状态
        $data['integral_status'] = $data['integral_status'] && $merData['mer_integral_status'] ? 1 : 0;
        // 设置积分比例
        $data['integral_rate'] = $merData['mer_integral_rate'] ?: 0;
        // 获取商家配送方式
        $data['delivery_way'] = $this->request->merchant()->delivery_way ?: [2];
        // 获取商家审核状态
        $data['is_audit'] = $this->request->merchant()->is_audit;
        // 设置分销一比例
        $data['extension_one_rate'] = $data['extension_one_rate'] ? $data['extension_one_rate'] * 100 : 0;
        // 设置分销二比例
        $data['extension_two_rate'] = $data['extension_two_rate'] ? $data['extension_two_rate'] * 100 : 0;
        // 是否公益店铺（只读，供商品表单展示命中/分销金额）
        $data['is_welfare_shop'] = (int)($this->request->merchant()->is_welfare_shop ?? 0);
        // 返回配置信息
        return app('json')->success($data);
    }


    /**
     * 从回收站中恢复指定ID的商品
     *
     * @param int $id 商品ID
     * @return \Illuminate\Http\JsonResponse 返回JSON格式的操作结果
     */
    public function restore($id)
    {
        // 判断商品是否存在于回收站中
        if (!$this->repository->merDeleteExists($this->request->merId(), $id))
            return app('json')->fail('只能恢复回收站的商品');
        // 恢复商品
        $this->repository->restore($id);
        // 返回操作结果
        return app('json')->success('商品已恢复');
    }

    /**
     * 批量恢复商品
     *
     * @return \think\response\Json
     */
    public function batchRestore()
    {
        $ids = $this->request->param('ids');
        $ids = is_array($ids) ? $ids : explode(',', (string)$ids);
        $ids = array_values(array_filter(array_unique(array_map('intval', $ids))));
        if (empty($ids))
            return app('json')->fail('请选择商品');

        $deleteIds = $this->repository->getOnlyTranshed(['mer_id' => $this->request->merId()])
            ->whereIn('product_id', $ids)
            ->column('product_id');
        sort($deleteIds);
        $checkIds = $ids;
        sort($checkIds);
        if ($deleteIds != $checkIds)
            return app('json')->fail('只能恢复回收站的商品');

        // 批量恢复
        $this->repository->batchRestore($this->request->merId(), $ids);

        return app('json')->success('商品已恢复');
    }

    /**
     * 获取上传临时密钥
     *
     * @return \Illuminate\Http\JsonResponse 返回JSON格式的上传临时密钥
     */
    public function temp_key()
    {
        // 创建上传服务实例
        $upload = UploadService::create();
        // 获取上传临时密钥
        $re = $upload->getTempKeys();
        // 返回上传临时密钥
        return app('json')->success($re);
    }


    /**
     * 更新商品排序
     *
     * @param int $id 商品ID
     * @return \think\response\Json 返回JSON格式的操作结果
     */
    public function updateSort($id)
    {
        // 获取请求参数中的排序值
        $sort = $this->request->param('sort');
        // 调用商品仓库的更新排序方法
        $this->repository->updateSort($id, $this->request->merId(), ['sort' => $sort]);
        // 返回操作成功的JSON格式数据
        return app('json')->success('修改成功');
    }

    /**
     * 预览商品信息
     *
     * @return \think\response\Json 返回JSON格式的操作结果
     */
    public function preview()
    {
        // 获取请求参数中的商品信息
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
        // 调用商品仓库的预览方法并返回操作结果
        return app('json')->success($this->repository->preview($data));
    }


    /**
     * 设置SPU标签
     * @param int $id SPU ID
     * @return \think\response\Json 返回JSON格式的操作结果
     */
    public function setLabels($id)
    {
        // 获取请求参数中的标签数据
        $data = $this->request->params(['mer_labels']);
        // 调用SpuRepository类的setLabels方法设置SPU标签
        app()->make(SpuRepository::class)->setLabels($id, 0, $data, $this->request->merId());
        // 返回操作成功的JSON格式数据
        return app('json')->success('修改成功');
    }

    /**
     * 获取属性值
     * @param int $id 属性ID
     * @return \think\response\Json 返回JSON格式的属性值
     */
    public function getAttrValue($id)
    {
        // 调用repository对象的getAttrValue方法获取属性值
        $data = $this->repository->getAttrValue($id, $this->request->merId());
        return app('json')->success($data);
    }

    /**
     * 免审
     * @param int $id SPU ID
     * @return \think\response\Json 返回JSON格式的操作结果
     * @throws \app\common\exception\ValidateException
     */
    public function freeTrial($id)
    {
        // 定义需要获取的请求参数
        $params = [
            "mer_cate_id",
            "sort",
            "is_show",
            "is_good",
            "attr",
            "attrValue",
            'spec_type'
        ];
        // 获取请求参数中的数据
        $data = $this->request->params($params);
        // 判断商户分类是否存在
        if (!empty($data['mer_cate_id'])) {
            $count = app()->make(StoreCategoryRepository::class)->getWhereCount(['store_category_id' => $data['mer_cate_id'], 'is_show' => 1, 'mer_id' => $this->request->merId()]);
            if (!$count) throw new ValidateException('商户分类不存在或不可用');
        }
        // 设置状态为1
        $data['status'] = 1;
        // 调用repository对象的freeTrial方法进行免费试用
        $this->repository->freeTrial($id, $data, $this->request->merId(), $this->request->adminInfo());
        return app('json')->success('编辑成功');
    }


    /**
     * 上下架
     * @Author:Qinii
     * @Date: 2020/5/18
     * @param int $id
     * @return mixed
     */
    public function switchStatus($id)
    {
        $status = $this->request->param('status', 0) == 1 ? 1 : 0;
        $this->repository->switchShow($id, $status, 'is_show', $this->request->merId(), $this->request->adminInfo());
        return app('json')->success('修改成功');
    }


    /**
     * 批量上下架
     * @return \think\response\Json
     * @author Qinii
     * @day 2022/9/6
     */
    public function batchShow()
    {
        $ids = $this->request->param('ids');
        if (empty($ids)) return app('json')->fail('请选择商品');
        $status = $this->request->param('status') == 1 ? 1 : 0;
        $this->repository->batchSwitchShow($ids, $status, 'is_show', $this->request->merId(), $this->request->adminInfo());
        return app('json')->success('修改成功');
    }

    /**
     * 批量设置模板
     * @return \think\response\Json
     * @author Qinii
     * @day 2022/9/6
     */
    public function batchTemplate()
    {
        $ids = $this->request->param('ids');
        $ids = is_array($ids) ? $ids : explode(',', $ids);
        $data = $this->request->params(['temp_id']);
        if (empty($ids)) return app('json')->fail('请选择商品');
        if (empty($data['temp_id'])) return app('json')->fail('请选择运费模板');
        if (!$this->repository->merInExists($this->request->merId(), $ids)) return app('json')->fail('请选择您自己商品');
        $make = app()->make(ShippingTemplateRepository::class);
        if (!$make->merInExists($this->request->merId(), [$data['temp_id']]))
            return app('json')->fail('请选择您自己的运费模板');
        $data['delivery_free'] = 0;
        $this->repository->updates($ids, $data);
        return app('json')->success('修改成功');
    }

    /**
     * 批量设置服务保障
     * @return \think\response\Json
     */
    public function batchGuarantee()
    {
        $ids = $this->request->param('ids');
        $ids = is_array($ids) ? $ids : explode(',', $ids);
        $data = $this->request->params(['guarantee_template_id']);

        if (empty($ids)) return app('json')->fail('请选择商品');
        if (empty($data['guarantee_template_id'])) return app('json')->fail('请选择保障服务');

        if (!$this->repository->merInExists($this->request->merId(), $ids))
            return app('json')->fail('请选择您自己的商品');

        $make = app()->make(GuaranteeTemplateRepository::class);
        if (!$make->merInExists($this->request->merId(), [$data['guarantee_template_id']]))
            return app('json')->fail('请选择您自己的保障服务');

        $this->repository->updates($ids, $data);
        return app('json')->success('修改成功');
    }

    /**
     * 批量标签
     * @return \think\response\Json
     * @author Qinii
     * @day 2022/9/6
     */
    public function batchLabels()
    {
        $ids = $this->request->param('ids');
        $data = $this->request->params(['mer_labels']);
        if (empty($ids)) return app('json')->fail('请选择商品');
        if (!$this->repository->merInExists($this->request->merId(), $ids))
            return app('json')->fail('请选择您自己商品');
        app()->make(SpuRepository::class)->batchLabels($ids, $data, $this->request->merId());
        return app('json')->success('修改成功');
    }

    /**
     * 批量设置推荐类型
     * @return \think\response\Json
     * @author Qinii
     * @day 2022/9/6
     */
    public function batchHot()
    {
        $ids = $this->request->param('ids');
        $data['is_good'] = 1;
        if (empty($ids)) return app('json')->fail('请选择商品');
        if (!$this->repository->merInExists($this->request->merId(), $ids))
            return app('json')->fail('请选择您自己商品');
        $this->repository->updates($ids, $data);
        return app('json')->success('修改成功');
    }

    /**
     * 批量设置佣金
     * @param ProductAttrValueRepository $repository
     * @return \think\response\Json
     * @author Qinii
     * @day 2022/12/26
     */
    public function batchExtension(ProductAttrValueRepository $repository)
    {
        $ids = $this->request->param('ids');
        $data = $this->request->params(['extension_one', 'extension_two']);
        if ($data['extension_one'] > 1 || $data['extension_one'] < 0 || $data['extension_two'] < 0 || $data['extension_two'] > 1) {
            return app('json')->fail('比例0～1之间');
        }
        if (empty($ids)) return app('json')->fail('请选择商品');
        if (!$this->repository->merInExists($this->request->merId(), $ids))
            return app('json')->fail('请选择您自己商品');
        $repository->updatesExtension($ids, $data);
        return app('json')->success('修改成功');
    }

    /**
     * 批量设置商品SVIP类型
     */
    public function batchSvipType()
    {
        // 获取请求参数中的商品ID
        $ids = $this->request->param('ids');
        // 获取请求参数中的SVIP价格类型
        $data = $this->request->params([['svip_price_type', 0]]);

        // 如果商品ID为空，则返回错误信息
        if (empty($ids)) return app('json')->fail('请选择商品');
        // 如果商品不属于当前商家，则返回错误信息
        if (!$this->repository->merInExists($this->request->merId(), $ids))
            return app('json')->fail('请选择您自己商品');
        // 更新商品SVIP类型
        $this->repository->updates($ids, $data);
        // 返回操作成功信息
        return app('json')->success('修改成功');
    }

    /**
     * 判断商品属性是否符合格式要求
     * @param int $id 商品ID
     * @return \think\response\Json 返回JSON格式的数据
     */
    public function isFormatAttr($id)
    {
        // 获取请求参数中的商品属性、商品项和商品类型
        $data = $this->request->params([
            ['attrs', []],
            ['items', []],
            ['product_type', 0],
        ]);
        $is_copy = $this->request->param('is_copy',0);
        // 判断商品属性是否符合格式要求
        $data = $this->repository->isFormatAttr($data['attrs'], $id,[],$is_copy);
        // 返回操作成功信息和商品属性
        return app('json')->success($data);
    }


    /**
     * 获取批量操作类型
     * @return \think\response\Json
     *
     * @date 2023/10/12
     * @author yyw
     */
    public function getBatchList()
    {
        $productBatch = app()->make(ProductBatchProcessRepository::class);
        return app('json')->success($productBatch->getTypeList());
    }

    /**
     * 商品批量操作
     * @return \think\response\Json
     *
     * @date 2023/10/12
     * @author yyw
     */
    public function batchProcess()
    {
        $ids = $this->request->param('ids', []);
        $batch_type = $this->request->param('batch_type');
        $batch_select_type = $this->request->param('batch_select_type', 'select');

        $admin_info = $this->request->adminInfo();

        // 商品列表 搜索条件
        $where = $this->request->param('where', []) ?: [];
        if (!empty($where)) {
            $where = array_merge($where, $this->repository->switchType($where['type'], $this->request->merId(), 0));
        }

        $productBatch = app()->make(ProductBatchProcessRepository::class);
        $type_info = $productBatch->getTypeInfo($batch_type);
        $data = $this->request->params($type_info['param']);
        $res = $productBatch->setProductIds($this->request->merId(), $batch_type, $batch_select_type, $where, $ids, $data, $admin_info);
        if (is_string($res)) {
            return app('json')->success($res);
        }
        return app('json')->success('修改成功');
    }

    /**
     * 获取操作日志列表
     *
     * @param int $product_id 商品ID
     * @return \think\response\Json
     */
    public function getOperateList($product_id)
    {
        // 从请求参数中获取类型和日期
        $where = $this->request->params([
            ['type', ''],
            ['date', '']
        ]);
        // 设置关联ID和关联类型
        $where['relevance_id'] = $product_id;
        $where['relevance_type'] = OperateLogRepository::RELEVANCE_PRODUCT;
        // 获取分页信息
        [$page, $limit] = $this->getPage();
        // 调用操作日志仓库的lst方法获取操作日志列表并返回JSON格式的成功响应
        return app('json')->success(app()->make(OperateLogRepository::class)->lst($where, $page, $limit));
    }

    /**
     *  解绑卡密库
     * @return \think\response\Json
     * @author Qinii
     */
    public function unbind()
    {
        $data = $this->request->params(['value_id','library_id']);
        $this->repository->unbindCdkeyLibrary($data['value_id'],$data['library_id']);
        return app('json')->success('删除成功');
    }

    /**
     * 可以用于选择为活动商品的 商品选择列表
     * @return \think\response\Json
     * @author Qinii
     */
    public function product_list()
    {
        $where = $this->request->params([
            ['keyword',''],     //商品搜索
            ['mer_labels', ''], //商户标签
            ['is_show',''],     //上下架
            ['mer_cate_id',''], //商户分类
            ['in_type','0,1'],  //商品类型
            ['status', 1],      //商品审核状态
            ['cate_pid',''],    //平台商品分类选择 1，2 级
            ['cate_id',''],     //平台商品分类 3级
        ]);
        [$page, $limit] = $this->getPage();
        $data = $this->repository->getList($this->request->merId(),  $where, $page, $limit);
        return app('json')->success($data);
    }

}
