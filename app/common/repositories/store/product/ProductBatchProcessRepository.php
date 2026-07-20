<?php

namespace app\common\repositories\store\product;

use app\common\dao\store\product\ProductDao;
use app\common\dao\store\product\ProductReservationDao;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\shipping\ShippingTemplateRepository;
use app\common\repositories\system\form\FormRepository;
use app\common\repositories\system\operate\OperateLogRepository;
use crmeb\jobs\BatchUpdateProductPriceJob;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Log;
use think\facade\Queue;
use think\facade\Validate;

class ProductBatchProcessRepository extends BaseRepository
{
    protected $reservationDao;

    private $type_list = [
        'cate' => ['name' => '商品分类', 'value' => 'cate', 'param' => ['cate_id'], 'validate' => ['cate_id|平台商品分类' => 'require|integer']],
        //        'mer_cate' => ['name' => '商户商品分类', 'value' => 'mer_cate', 'param' => ['mer_cate_ids'], 'validate' => ['mer_cate_ids|商户商品分类' => 'require|integer']],
        'label' => ['name' => '商品标签', 'value' => 'label', 'param' => ['mer_labels'], 'validate' => ['mer_labels|商品标签' => 'array']],
        'delivery_method' => ['name' => '配送方式', 'value' => 'delivery_method', 'param' => ['delivery_way','reservation_type'], 'validate' => ['delivery_way|配送方式' => 'require|array','reservation_type|预约服务方式' => 'array']],
        'postage' => ['name' => '运费设置', 'value' => 'postage', 'param' => ['temp_id', 'delivery_free'], 'validate' => ['temp_id|运费模板' => 'integer', 'delivery_free|是否全国包邮' => 'require|in:0,1']],
        'commission' => ['name' => '佣金设置', 'value' => 'commission', 'param' => ['extension_one', 'extension_two'], 'validate' => ['extension_one|一级佣金比例' => 'require|float|between:0,1', 'extension_two|二级佣金比例' => 'require|float|between:0,1']],
        'member' => ['name' => '付费会员设置', 'value' => 'member', 'param' => [['svip_price_type', 0]], 'validate' => ['svip_price_type|付费会员' => 'require|in:0,1']],
        'sys_form' => ['name' => '自定义留言设置', 'value' => 'sys_form', 'param' => [['mer_form_id', 0]], 'validate' => ['mer_form_id|商户表单id' => 'integer']],
        'price' => ['name' => '商品价格设置', 'value' => 'price', 'param' => [['price_type', 'add'], ['price_number', 0]], 'validate' => ['price_type|计算方式' => 'require|in:add,sub,mul,div', 'price_number|变动值' => 'require']],
    ];

    public function __construct(ProductDao $dao, ProductReservationDao $reservationDao)
    {
        $this->dao = $dao;
        $this->reservationDao = $reservationDao;
    }

    /**
     * 获取操作列表
     * @return array
     *
     * @date 2023/10/09
     * @author yyw
     */
    public function getTypeList()
    {
        $res = [];
        foreach ($this->type_list as $item) {
            $res [] = ['name' => $item['name'], 'value' => $item['value']];
        }
        return $res;
    }

    /**
     * 验证类型
     * @param string $type
     * @return bool
     *
     * @date 2023/10/09
     * @author yyw
     */
    public function validateType(string $type)
    {
        if (empty($this->type_list[$type])) {
            throw new ValidateException('类型异常');
        }
        return true;
    }

    /**
     * 获取操作类型
     * @param string $type
     * @return mixed
     *
     * @date 2023/10/09
     * @author yyw
     */
    public function getTypeInfo(string $type)
    {
        $this->validateType($type);
        return $this->type_list[$type];
    }

    /**
     * 验证数据
     * @param string $type
     * @param array $data
     * @return array
     *
     * @date 2023/10/09
     * @author yyw
     */
    public function validateData(string $type, array $data)
    {
        $info = $this->getTypeInfo($type);
        if ($info['validate']) {
            $validate = Validate::rule($info['validate']);
            if (!$validate->check($data)) {
                throw new ValidateException($validate->getError());
            }
        }
        $res_data = [];
        foreach ($info['param'] as $param) {
            if (is_array($param)) {
                $res_data[$param[0]] = $data[$param[0]] ?? '';
            } else {
                $res_data[$param] = $data[$param] ?? '';
            }
        }
        return $res_data;

    }

    /**
     * 获取要修改的商品id
     * @param int $mer_id
     * @param string $batch_type
     * @param array $where
     * @param array $ids
     *
     * @date 2023/10/07
     * @author yyw
     */
    public function setProductIds(int $mer_id, string $type, string $batch_select_type, array $where = [], array $ids = [], array $data = [], $admin_info = [])
    {
        switch ($batch_select_type) {
            case "select":
                if (!$this->dao->merInExists($mer_id, $ids)) {
                    throw new ValidateException('请选择您自己商品');
                }
                break;
            case 'all':
                if (isset($where['page'])) unset($where['page']);
                if (isset($where['limit'])) unset($where['limit']);
                if (!isset($where['type'])) {
                    throw new ValidateException('商品搜索类型异常');
                }
                $where_type = $where['type'] ?? 1;
                unset($where['type']);
                $where = array_merge($where, app()->make(ProductRepository::class)->switchType($where_type, $mer_id));
                if ($type !== 'price') {
                    $ids = $this->dao->search($mer_id, $where)->column('Product.product_id') ?? [];
                } else {
                    $ids = [];
                }
                break;
            default:
                throw new ValidateException('请选择需要批量设置的商品');
        }


        return $this->batchProcess($mer_id, $type, $ids, $data, $where, $admin_info);
    }


    /**
     * 批量操作
     * @param string $type
     * @param array $data
     * @return bool|\think\response\Json
     * @throws \think\db\exception\DbException
     *
     * @date 2023/10/09
     * @author yyw
     */
    public function batchProcess(int $mer_id, string $type, array $ids = [], array $data = [], array $where = [], $admin_info = [])
    {
        if (empty($this->type_list[$type])) {
            throw new ValidateException('类型异常');
        }

        if ($type != 'price' && empty($ids)) {
            throw new ValidateException('批量设置商品id为空，请检查');
        }
        if (empty($mer_id)) {
            throw new ValidateException('商户id异常，请检查');
        }

        $data = $this->validateData($type, $data);;

        switch ($type) {
            case 'cate':
                $this->dao->updates($ids, $data);
                break;
            case 'mer_cate':
                $this->batchMerCate($mer_id, $ids, $data['mer_cate_ids']);
                break;
            case 'label':
                app()->make(SpuRepository::class)->batchLabels($ids, $data, $mer_id);
                break;
            case 'delivery_method':
                //验证参数
                if (count($data['delivery_way']) > 3 || !is_array($data['delivery_way'])) {
                    throw new ValidateException('配送方式参数异常');
                }
                // 1:自提/到店 2:快递/上门 3:同城配送
                foreach ($data['delivery_way'] as $value) {
                    if (!in_array($value, [1, 2, 3])) {
                        throw new ValidateException('没有该配送方式，请选择正确的配送方式');
                    }
                }
                if(isset($data['reservation_type']) && !empty($data['reservation_type'])) {
                    foreach ($data['reservation_type'] as $value) {
                        if (!in_array($value, [1, 2])) {
                            throw new ValidateException('预约商品，请选择正确的预约服务方式');
                        }
                    }
                    $reservationType = count($data['reservation_type']) == 2 ? 3 : $data['reservation_type'][0];
                    $this->reservationDao->query([])->whereIn($this->dao->getPk(), $ids)->update(['reservation_type' => $reservationType]);
                }
                unset($data['reservation_type']);

                if (in_array(1, $data['delivery_way'])) {
                    $this->dao->query([])->whereIn($this->dao->getPk(), $ids)->where('type', 1)->update(['delivery_way' => 1]);
                }
                $data['delivery_way'] = implode(',', $data['delivery_way']);
                $this->dao->query([])->whereIn($this->dao->getPk(), $ids)->where('type', 0)->update($data);
                break;
            case 'postage':
                if ($data['delivery_free'] == 1) {
                    $data['temp_id'] = 0;
                } else {
                    if (!app()->make(ShippingTemplateRepository::class)->merInExists($mer_id, [$data['temp_id']]))
                        throw new ValidateException('请选择您自己的运费模板');
                }
                $this->dao->updates($ids, $data);
                break;
            case 'commission':
                if ($data['extension_one'] > 1 || $data['extension_one'] < 0 || $data['extension_two'] < 0 || $data['extension_two'] > 1) {
                    return app('json')->fail('比例0～1之间');
                }
                app()->make(ProductAttrValueRepository::class)->updatesExtension($ids, $data);
                break;
            case 'member':
                $this->dao->updates($ids, $data);
                break;
            case 'sys_form':
                $this->batchForm($mer_id, $ids, (int)$data['mer_form_id']);
                break;
            case 'price':
                $message = $this->queueBatchPrice($mer_id, $data['price_type'], $data['price_number'], $ids, $where, $admin_info);
                break;
        }

        return $message ?? true;
    }


    /**
     * 批量修改商户分类
     * @param int $mer_id
     * @param array $ids
     * @param array $mer_cate_ids
     * @return bool
     *
     * @date 2023/10/09
     * @author yyw
     */
    public function batchMerCate(int $mer_id, array $ids, array $mer_cate_ids)
    {
        foreach ($ids as $id) {
            // 整理分类数据
            $mer_cate = app()->make(ProductRepository::class)->setMerCate($mer_cate_ids, $id, $mer_id);
            // 删除以前分类数据
            $productCateRepository = app()->make(ProductCateRepository::class);
            $productCateRepository->clearAttr($id);
            // 保存新的分类数据
            if (!empty($mer_cate)) {
                $productCateRepository->insert($mer_cate);
            }
        }
        return true;
    }

    /**
     * 队列  批量修改价格
     * @param int $mer_id
     * @param array $ids
     * @param string $type
     * @param string $number
     * @return bool
     *
     * @date 2023/10/09
     * @author yyw
     */
    public function queueBatchPrice(int $mer_id, string $type, string $number, array $ids = [], array $where = [], $admin_info = [])
    {
        if (!in_array($type, ['add', 'sub', 'mul', 'div'])) {
            throw new ValidateException('修改价格类型错误');
        }
        if ($number === '') {
            return true;
        }
        if ($number <= 0) {
            throw new ValidateException('修改价格的变动值或比例不能小于等于0');
        }
        if ($where) {
            $this->dao->search($mer_id, $where)->with(['attrValue'])->chunk(10, function ($product_list) use ($mer_id, $type, $number, $ids, $admin_info) {
                Queue::push(BatchUpdateProductPriceJob::class, compact('mer_id', 'type', 'number', 'product_list', 'admin_info'));
            }, 'Product.product_id');
        } else {
            Queue::push(BatchUpdateProductPriceJob::class, compact('mer_id', 'type', 'number', 'ids', 'admin_info'));
        }

        return '已加入消息队列，请稍后刷新';
    }


    /**
     * 批量修改价格
     * @param int $mer_id
     * @param array $ids
     * @param string $type
     * @param string $number
     * @return bool
     *
     * @date 2023/10/09
     * @author yyw
     */
    public function batchPrice(array $data = [])
    {

        $mer_id = $data['mer_id'];
        $type = $data['type'];
        $number = $data['number'];
        if (!in_array($type, ['add', 'sub', 'mul', 'div'])) {
            throw new ValidateException('修改价格类型错误');
        }
        if ($number <= 0) {
            throw new ValidateException('修改价格的变动值或比例不能小于等于0');
        }

        if (!empty($data['ids'])) {
            foreach ($data['ids'] as $id) {
                $productInfo = $this->dao->getWith($id, ['attrValue']);
                $this->updateProduct($mer_id, $type, $number, $productInfo, $data['admin_info'] ?? []);
            }
        }
        if (!empty($data['product_list'])) {
            foreach ($data['product_list'] as $productInfo) {
                $this->updateProduct($mer_id, $type, $number, $productInfo, $data['admin_info'] ?? []);
            }
        }


        return true;
    }

    /**
     * 批量更新表单ID
     * @param int $mer_id
     * @param int $form_id
     * @return bool
     * @throws \think\db\exception\DbException
     *
     * @date 2023/10/11
     * @author yyw
     */
    public function batchForm(int $mer_id, array $ids = [], int $form_id = 0)
    {
        if ($form_id) {
            //验证模板是不存在
            /** @var FormRepository $formRepository */
            $formRepository = app()->make(FormRepository::class);
            if (!$form_info = $formRepository->get($form_id)) {
                throw new ValidateException('表单数据异常');
            }
            if ($form_info['mer_id'] != $mer_id) {
                throw new ValidateException('请选择自己的表单');
            }

            $this->dao->updates($ids, ['mer_form_id' => $form_id]);
        } else {
            $this->dao->updates($ids, ['mer_form_id' => 0]);
        }

        return true;
    }

    /**
     * 计算价格
     * @param string $ot_price
     * @param string $type
     * @param string $number
     * @return mixed|string|null
     *
     * @date 2023/10/09
     * @author yyw
     */
    public function calculatePrice(string $ot_price, string $type, string $number)
    {
        switch ($type) {
            case 'add':
                $price = bcadd($ot_price, $number, 2);
                break;
            case 'sub':
                $price = max(bcsub($ot_price, $number, 2), 0);
                break;
            case 'mul':
                $pct = bcdiv($number, '100', 4);
                $price = bcmul($ot_price, $pct, 2);
                break;
            case 'div':
                if ($number === '0') {
                    throw new ValidateException('分母不能为0');
                }
                $pct = bcdiv($number, '100', 4);
                $price = bcdiv($ot_price, $pct, 2);
                break;
            default:
                throw new ValidateException('修改价格类型错误');
        }
        return $price;
    }

    /**
     * 修改商品
     * @param int $mer_id
     * @param string $type
     * @param string $number
     * @param $productInfo
     * @return void
     *
     * @date 2023/10/12
     * @author yyw
     */
    public function updateProduct(int $mer_id, string $type, string $number, $productInfo, $admin_info = []): void
    {

        Db::transaction(function () use ($mer_id, $type, $number, $productInfo, $admin_info) {
            $id = $productInfo['product_id'];
            $price = $this->calculatePrice($productInfo['price'], $type, $number);
            $this->dao->update($id, ['price' => $price]);
            $update_infos = [
                OperateLogRepository::MERCHANT_INC_PRODUCT_PRICE => '',
                OperateLogRepository::MERCHANT_DEC_PRODUCT_PRICE => '',
            ];
            //计算sku价格
            foreach ($productInfo['attrValue'] as $attrValue) {
                $sku_price = $this->calculatePrice($attrValue['price'], $type, $number);

                $change_price = bcsub($sku_price, $attrValue['price'], 2);
                if ($change_price > 0) {
                    $update_infos[OperateLogRepository::MERCHANT_INC_PRODUCT_PRICE] .= $attrValue['sku'] . '价格增加了' . $change_price . ',';
                } else {
                    $update_infos[OperateLogRepository::MERCHANT_DEC_PRODUCT_PRICE] .= $attrValue['sku'] . '价格减少了' . -$change_price . ',';
                }

                //更新sku
                app()->make(ProductAttrValueRepository::class)->update($attrValue['value_id'], ['price' => $sku_price]);
            }
            //更新spu
            app()->make(SpuRepository::class)->updatePrice($mer_id, $id, $price);
            if (!empty($admin_info)) {
                event('create_operate_log', [
                    'category' => OperateLogRepository::MERCHANT_EDIT_PRODUCT,
                    'data' => [
                        'product' => $productInfo,
                        'admin_info' => $admin_info,
                        'update_infos' => $update_infos,
                    ],
                ]);
            }

        });
    }

}
