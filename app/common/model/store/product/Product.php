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

namespace app\common\model\store\product;

use app\common\model\store\StoreActivityLabel;
use app\common\dao\store\StoreSeckillActiveDao;
use app\common\model\BaseModel;
use app\common\model\store\coupon\StoreCouponProduct;
use app\common\model\store\Guarantee;
use app\common\model\store\GuaranteeTemplate;
use app\common\model\store\GuaranteeValue;
use app\common\model\store\parameter\ParameterValue;
use app\common\model\store\shipping\ShippingTemplate;
use app\common\model\store\StoreBrand;
use app\common\model\store\StoreCategory;
use app\common\model\store\StoreSeckillActive;
use app\common\model\store\StoreSeckillTime;
use app\common\model\system\form\Form;
use app\common\model\system\merchant\Merchant;
use app\common\model\store\parameter\ParameterProduct;
use app\common\repositories\store\StoreCategoryRepository;
use crmeb\services\VicWordService;
use think\db\BaseQuery;
use think\facade\Db;
use think\model\concern\SoftDelete;

class Product extends BaseModel
{
    use SoftDelete;

    protected $deleteTime = 'is_del';
    protected $defaultSoftDelete = 0;
    protected $append = ['activity_labels'];

    /**
     * @Author:Qinii
     * @Date: 2020/5/8
     * @return string
     */
    public static function tablePk(): string
    {
        return 'product_id';
    }

    /**
     * @Author:Qinii
     * @Date: 2020/5/8
     * @return string
     */
    public static function tableName(): string
    {
        return 'store_product';
    }



    /*
     * -----------------------------------------------------------------------------------------------------------------
     * 属性
     * -----------------------------------------------------------------------------------------------------------------
    */

    public function setActivityLabelIdsAttr($value)
    {
        if (is_array($value)) {
            $value = !empty($value) ?  implode(',', $value) : '';
        }
        return $value;
    }

    public function getActivityLabelIdsAttr($value)
    {
        if ($value && !is_array($value)) {
            $value = explode(',', $value);
        }
        return $value;
    }


    public function getSliderImageAttr($value)
    {
        if (empty($value)) return [];
        return !is_array($value) ? explode(',', $value) : $value;
    }

    public function getGiveCouponIdsAttr($value)
    {
        if (empty($value)) return [];
        return !is_array($value) ? explode(',', $value) : $value;
    }

    public function getGoodIdsAttr($value, $data)
    {
        if (empty($value)) return [];
        return !is_array($value) ? explode(',', $value) : $value;
    }

    //public function getParamTempIdAttr($value)
    //{
    //    if (empty($value)) return [];
    //    return !is_array($value) ? explode(',', $value) : $value;
    //}

    public function getMaxExtensionAttr($value)
    {
        if ($this->extension_type) {
            $org_extension = ($this->attrValue()->order('extension_one DESC')->value('extension_one'));
        } else {
            $org_extension = bcmul(($this->attrValue()->order('price DESC')->value('price')), systemConfig('extension_one_rate'), 2);
        }
        $spreadUser = (request()->hasMacro('isLogin') && request()->isLogin() && request()->userType() == 1) ? request()->userInfo() : null;
        if ($spreadUser && $spreadUser->brokerage_level > 0 && $spreadUser->brokerage && $spreadUser->brokerage->extension_one_rate > 0) {
            $org_extension = bcmul($org_extension, 1 + $spreadUser->brokerage->extension_one_rate, 2);
        }
        return $org_extension;
    }

    public function getMinExtensionAttr($value)
    {
        if ($this->extension_type) {
            $org_extension = ($this->attrValue()->order('extension_two ASC')->value('extension_two'));
        } else {
            $org_extension = bcmul(($this->attrValue()->order('price ASC')->value('price')), systemConfig('extension_two_rate'), 2);
        }
        $spreadUser = (request()->hasMacro('isLogin') && request()->isLogin() && request()->userType() == 1) ? request()->userInfo() : null;
        if ($spreadUser && $spreadUser->brokerage_level > 0 && $spreadUser->brokerage && $spreadUser->brokerage->extension_one_rate > 0) {
            $org_extension = bcmul($org_extension, 1 + $spreadUser->brokerage->extension_one_rate, 2);
        }
        return $org_extension;
    }

    public function check()
    {
        if (!$this || !$this->is_show || !$this->is_used || !$this->status || $this->is_del || !$this->mer_status) return false;
        return true;
    }

    /**
     *  秒杀商品结束时间
     * @return false|int
     * @author Qinii
     * @day 2020-08-15
     */
    public function getEndTimeAttr()
    {
        if ($this->product_type !== 1) return true;
        $end_day = strtotime($this->seckillActive['end_day']);
        if ($end_day > time()) {
            return strtotime($this->seckillActive['end_day']);
        } else {
           return time();
        }
    }

    /**
     *  自动上架时间转换
     * @param $value
     * @param $data
     * @return string
     */
    public function getAutoOnTimeAttr($value,$data)
    {
        if($value){
            return date('Y-m-d H:i:s',$value);
        }
    }
    /**
     *  自动下架时间转换
     * @param $value
     * @param $data
     * @return string
     */
    public function getAutoOffTimeAttr($value,$data)
    {
        if($value){
            return date('Y-m-d H:i:s',$value);
        }
    }

    /**
     * 秒杀商品状态
     * @return int|true
     * FerryZhao 2024/4/25
     */
    public function getSeckillStatusAttr($value,$data)
    {
        if ($this->product_type !== 1) return true;
        if ($this->seckillActive && $this->seckillActive['active_status'] !== -1) {
            $end_day = strtotime($this->seckillActive->getData('end_day'));
            if ($end_day <= time()) return -1;//已结束
            $start_day = strtotime($this->seckillActive['start_day']);
            if ($start_day <= time()) {
                $currentHour = date('G', time());
                $data = StoreSeckillTime::whereIn('seckill_time_id',$this->seckillActive->seckill_time_ids)
                    ->where('start_time','<=',$currentHour)->where('end_time','>',$currentHour)
                    ->where('status',1)
                    ->find();
                if ($data) {
                    return 1;
                } else {
                    return 0;
                }
            } else {
                //还未开始
                return 0;
            }
        }
        //已结束
        return -1;
    }

    public function getImageAttr($value)
    {
        if (is_int(strpos($value, 'http'))) {
            return $value;
        } elseif(strpos($value, '//') == 0){
            return $value;
        } else {
            return rtrim(systemConfig('site_url'), '/') . $value;
        }
    }

    public function getTopReplyAttr()
    {
        $res = [];
        if (systemConfig('sys_reply_status')) {
            $res = ProductReply::where('product_id', $this->product_id)->where('is_del', 0)->with(['orderProduct'])->field('reply_id,uid,nickname,merchant_reply_content,avatar,order_product_id,product_id,product_score,service_score,postage_score,comment,pics,rate,create_time')
                ->order('sort DESC,create_time DESC')->limit(1)->find();
            if (!$res) return null;
            if ($res['orderProduct'])
                $res['sku'] = $res['orderProduct']['cart_info']['productAttr']['sku'];
            unset($res['orderProduct']);
            if (strlen($res['nickname']) > 1) {
                $str = mb_substr($res['nickname'], 0, 1) . '*';
                if (strlen($res['nickname']) > 2) {
                    $str .= mb_substr($res['nickname'], -1, 1);
                }
                $res['nickname'] = $str;
            }
        }
        return $res;
    }

    public function getUsStatusAttr()
    {
        return ($this->status == 1) ? ($this->is_used == 1 ? ($this->mer_status == 1 && $this->is_show == 1 ? 1 : 0) : -1) : -1;
    }

    public function getGuaranteeTemplateAttr()
    {
        $gua = GuaranteeTemplate::where('guarantee_template_id', $this->guarantee_template_id)->where('status', 1)->where('is_del', 0)->find();
        if (!$gua) return [];
        $guarantee_id = GuaranteeValue::where('guarantee_template_id', $this->guarantee_template_id)->column('guarantee_id');
        return Guarantee::where('guarantee_id', 'in', $guarantee_id)->where('status', 1)->where('is_del', 0)->select();
    }

    public function getMaxIntegralAttr()
    {
        if (systemConfig('integral_status') && merchantConfig($this->mer_id, 'mer_integral_status')) {
            $price = ($this->attrValue()->order('price DESC')->value('price'));
            $rate = ($this->integral_rate < 0) ? merchantConfig($this->mer_id, 'mer_integral_rate') : $this->integral_rate;
            $rate = $rate > 0 ? $rate / 100 : 0;
            return bcmul($price, $rate, 2);
        }
        return '0';
    }

    public function getHotRankingAttr()
    {
        if ($this->product_type == 0) {
            $where = [
                'is_show' => 1,
                'status' => 1,
                'is_used' => 1,
                'product_type' => 0,
                'mer_status' => 1,
                'is_gift_bag' => 0,
                'cate_id' => $this->cate_id
            ];
            self::where($where)->order('sales DESC');
        }
    }

    public function getOtPriceAttr($value)
    {
        if ($this->product_type == 20) {
            return (int)$value;
        }
        return $value;
    }

    public function getCustomTempIdAttr($value)
    {
        return !empty($value) ? json_decode($value, true) : [];
    }

    public function getLabelsAttr($value)
    {
        return !empty($value) ? json_decode($value, true) : [];
    }

    public function getActivityLabelsAttr($value, $data)
    {
        // 类型映射
        $productTypeMap = [
            1 => 7, // 秒杀
            2 => 9, // 预售
            3 => 8, // 助力
            4 => 6  // 拼团
        ];
        $defaltLabelWhere = [];
        if (isset($data['delivery_free']) && $data['delivery_free'] == 1) {
            $defaltLabelWhere[] = 1; // 包邮
        }
        if (isset($this->issetCoupon) && !empty($this->issetCoupon)) {
            $defaltLabelWhere[] = 2; // 领券
        }
        if(isset($data['type'])) {
            if($data['type'] == 4 && isset($this->reservation) && !empty($this->reservation)) {
                // 预约类型处理,上门+到店
                if ($this->reservation->reservation_type == 3) {
                    $defaltLabelWhere[] = 3;
                    $defaltLabelWhere[] = 4;
                } else { // 3:上门 4:到店
                    $defaltLabelWhere[] = $this->reservation->reservation_type == 1 ? 4 : 3;
                }
            }
            if(isset($data['delivery_way'])) {
                $deliveryWay = is_string($data['delivery_way']) ? explode(',', $data['delivery_way']) : $data['delivery_way'];
                if ($data['type'] == 0 && in_array(3, $deliveryWay)) {
                    $defaltLabelWhere[] = 5; // 同城
                }
            }
        }
        if (isset($data['product_type']) && isset($productTypeMap[$data['product_type']])) {
            $defaltLabelWhere[] = $productTypeMap[$data['product_type']]; // 如果产品类型在映射中存在，直接使用映射值
        }
        if (isset($this->merchant) && $this->merchant->is_trader === 1) {
            $defaltLabelWhere[] = 10; // 自营
        }
        // 构建基础查询条件
        $field = 'id,label_name,style_type,color,bg_color,border_color,icon,default_type,is_show,status';
        $baseQuery = StoreActivityLabel::where('status', 1);
        // 复用查询对象，分别获取默认标签和自定义标签
        $defaultLabels = [];
        if ($defaltLabelWhere) {
            $defaultLabels = (clone $baseQuery)->whereIn('default_type', $defaltLabelWhere)->column($field);
        }
        $labels = [];
        if ($this->activity_label_ids) {
            $labels = $baseQuery->whereIn('id', $this->activity_label_ids)->column($field);
        }

        return array_merge($defaultLabels, $labels);
    }
    /**
     *  商品参数
     * @author Qinii
     * @day 2022/11/24
     */
    public function getParameterParamsAttr()
    {
        $parameter_value_id = ParameterProduct::where('product_id',$this->product_id)->column('parameter_value_id');
        $data = ParameterValue::whereIn('parameter_value_id',$parameter_value_id)->select()->toArray();
        $parameter = [];
        foreach ($data as $item) {
            $parameter[$item['name']][] = $item['value'];
        }

        $arr = array_map(function ($k, $v) {
            return ['label' => $k, 'value' => $v];
        }, array_keys($parameter), $parameter);

        return $arr;
    }

    public function attrResult()
    {
        return $this->hasOne(ProductResult::class,'product_id','product_id');
    }


    /**
     *  是否是会员
     * @return bool
     * @author Qinii
     * @day 2023/1/4
     */
    public function getIsVipAttr()
    {
        if (request()->hasMacro('isLogin') && request()->isLogin()) {
            if (request()->userType() == 1) {
                $userInfo = request()->userInfo();
                return $userInfo->is_svip > 0 ? true : false;
            } else {
                return true;
            }
        }
        return false;
    }

    /**
     *  是否展示会员价
     * @return bool
     * @author Qinii
     * @day 2023/1/4
     */
    public function getShowSvipPriceAttr()
    {
        if ($this->mer_svip_status != 0 && (systemConfig('svip_show_price') != 1 || $this->is_vip) && $this->svip_price_type > 0) {
            return true;
        }
        return false;
    }


    /**
     *  是否显示会员价等信息
     * @return array
     * @author Qinii
     * @day 2022/11/24
     */
    public function getShowSvipInfoAttr()
    {
        $res = [
            'show_svip' => true, //是否展示会员入口
            'is_svip' => false,  //当前用户是否是会员
            'show_svip_price' => false, //是否展示会员价
            'save_money' => 0, //当前商品会员优化多少钱
        ];
        if ($this->product_type == 0) {
            if (!systemConfig('svip_switch_status')) {
                $res['show_svip'] = false;
            } else {
                $res['is_svip'] = $this->is_vip;
                if ($this->show_svip_price) {
                    $res['show_svip_price'] = true;
                    $res['save_money'] = bcsub($this->price, $this->svip_price, 2);
                }
            }
        }
        return $res;
    }

    /**
     *  获取会员价
     * @return int|string
     * @author Qinii
     * @day 2023/1/4
     */
    public function getSvipPriceAttr()
    {
        if ($this->product_type == 0 && $this->mer_svip_status != 0 && $this->show_svip_price) {
            //默认比例
            if ($this->svip_price_type == 1) {
                $rate = merchantConfig($this->mer_id, 'svip_store_rate');
                $svip_store_rate = $rate > 0 ? bcdiv($rate, 100, 2) : 0;
                $price = $this->attrValue()->order('price ASC')->value('price');
                return bcmul($price, $svip_store_rate, 2);
            }
            //自定义
            if ($this->svip_price_type == 2) {
                return $this->getData('svip_price');
            }
        }
        return 0;
    }

    public function getIsSvipPriceAttr()
    {
        if ($this->product_type == 0 && $this->mer_svip_status != 0) {
            //默认比例
            if ($this->svip_price_type == 1) {
                $rate = merchantConfig($this->mer_id, 'svip_store_rate');
                $svip_store_rate = $rate > 0 ? bcdiv($rate, 100, 2) : 0;
                $price = $this->attrValue()->order('price ASC')->value('price');
                return bcmul($price, $svip_store_rate, 2);
            }
            //自定义
            if ($this->svip_price_type == 2) {
                return $this->getData('svip_price');
            }
        }
        return 0;
    }




    /*
     * -----------------------------------------------------------------------------------------------------------------
     *  关联模型
     * -----------------------------------------------------------------------------------------------------------------
    */
    public function merCateId()
    {
        return $this->hasMany(ProductCate::class, 'product_id', 'product_id')->field('product_id,mer_cate_id');
    }

    public function attr()
    {
        return $this->hasMany(ProductAttr::class, 'product_id', 'product_id');
    }

    public function attrValue()
    {
        return $this->hasMany(ProductAttrValue::class, 'product_id', 'product_id')->order('value_id ASC');
    }

    public function oldAttrValue()
    {
        return $this->hasMany(ProductAttrValue::class, 'product_id', 'old_product_id');
    }

    public function content()
    {
        return $this->hasOne(ProductContent::class, 'product_id', 'product_id');
    }

    public function reservation()
    {
        return $this->hasOne(ProductReservation::class, 'product_id', 'product_id');
    }

    protected function temp()
    {
        return $this->hasOne(ShippingTemplate::class, 'shipping_template_id', 'temp_id');
    }

    public function storeCategory()
    {
        return $this->hasOne(StoreCategory::class, 'store_category_id', 'cate_id')->field('store_category_id,cate_name');
    }

    public function merchant()
    {
        return $this->hasOne(Merchant::class, 'mer_id', 'mer_id')->field('is_trader,type_id,mer_id,mer_name,mer_avatar,product_score,service_score,postage_score,service_phone,care_count,is_margin');
    }

    public function reply()
    {
        return $this->hasMany(ProductReply::class, 'product_id', 'product_id')->order('create_time DESC');
    }

    public function brand()
    {
        return $this->hasOne(StoreBrand::class, 'brand_id', 'brand_id')->field('brand_id,brand_name');
    }

    public function seckillActive()
    {
        return $this->hasOne(StoreSeckillActive::class, 'seckill_active_id', 'active_id');
    }

    public function issetCoupon()
    {
        $time = date('Y-m-d H:i:s');
        $where1 = "(B.is_limited = 0 OR (B.is_limited = 1 AND B.remain_count > 0))";
        $where2 = "(B.is_timeout = 0 OR (B.is_timeout = 1 AND B.start_time < '{$time}' AND B.end_time > '{$time}'))";

        return $this->hasOne(StoreCouponProduct::class, 'product_id', 'product_id')->alias('A')
            ->rightJoin('StoreCoupon B', 'A.coupon_id = B.coupon_id')
            ->whereRaw($where1)
            ->whereRaw($where2)
            ->field('A.product_id,B.*')->where('status', 1)->where('type', 1)->where('send_type', 0)->where('is_del', 0)
            ->order('sort DESC,coupon_id DESC')->hidden(['is_del', 'status']);
    }

    public function assist()
    {
        return $this->hasOne(ProductAssist::class, 'product_id', 'product_id');
    }

    public function productGroup()
    {
        return $this->hasOne(ProductGroup::class, 'product_id', 'product_id');
    }

    public function guarantee()
    {
        return $this->hasOne(GuaranteeTemplate::class, 'guarantee_template_id', 'guarantee_template_id')->where('status', 1)->where('is_del', 0);
    }

    public function getForm()
    {
        return $this->hasOne(Form::class, 'form_id', 'mer_form_id');
    }

    public function getFormName()
    {
        return $this->hasOne(Form::class, 'form_id', 'mer_form_id')->bind(['mer_form_name' => 'name']);
    }


    /*
     * -----------------------------------------------------------------------------------------------------------------
     * 搜索器
     * -----------------------------------------------------------------------------------------------------------------
     */
    public function searchMerCateIdAttr($query, $value)
    {
        $cate_ids = (StoreCategory::where('path', 'like', '%/' . $value . '/%'))->column('store_category_id');
        $cate_ids[] = intval($value);
        $product_id = ProductCate::whereIn('mer_cate_id', $cate_ids)->column('product_id');
        $query->whereIn('Product.product_id', $product_id);
    }

    public function searchKeywordAttr($query, $value)
    {
        if (!$value) return;
        if (is_numeric($value)) {
            $query->whereLike("Product.store_name|Product.keyword|bar_code|Product.product_id", "%{$value}%");
        } else if (is_array($value)) {
            $query->where(function ($query) use ($value) {
                foreach ($value as $item) {
                    $query->whereOr('Product.store_name|Product.keyword', 'LIKE', "%$item%");
                }
            });
        } else {
            $word = app()->make(VicWordService::class)->getWord($value);
            $query->where(function ($query) use ($word, $value) {
                foreach ($word as $item) {
                    $query->whereOr('Product.store_name|Product.keyword', 'LIKE', "%$item%");
                }
                $query->order(Db::raw('REPLACE(Product.store_name,\'' . $value . '\',\'\')'));
            });
        }
    }

    public function searchStatusAttr($query, $value)
    {
        if ($value === -1) {
            $query->where('Product.status', 'in', [-1, -2]);
        } else {
            $query->where('Product.status', $value);
        }
    }

    public function searchCatePidAttr($query, $value)
    {
        $storeCategoryRepository = app()->make(StoreCategoryRepository::class);
        $ChildIdsData = $storeCategoryRepository->getSearch(['ids' =>$value])->where('is_show', 1)->append(['three_child_ids'], true)->select()->toArray();
        $childIds = array_reduce(array_column($ChildIdsData,'three_child_ids') ?: [], 'array_merge',[]);
        $query->whereIn('cate_id',$childIds);
    }

    public function searchCateIdAttr($query, $value)
    {
        $query->where('cate_id', $value);
    }

    public function searchCateIdsAttr($query, $value)
    {
        $query->whereIn('cate_id', $value);
    }

    public function searchIsShowAttr($query, $value)
    {
        if($value == 0){
            $query->whereIn('is_show',[0,2]);
        }else{
            $query->where('is_show', $value);
        }

    }

    public function searchPidAttr($query, $value)
    {
        $childrenId = app()->make(StoreCategoryRepository::class)->findChildrenId((int)$value);
        $ids = array_merge($childrenId, [(int)$value]);
        $query->whereIn('cate_id', $ids);
    }

    public function searchStockAttr($query, $value)
    {
        $value ? $query->where('stock', '<=', $value) : $query->where('stock', $value);
    }

    public function searchIsNewAttr($query, $value)
    {
        $query->where('is_new', $value);
    }

    public function searchPriceAttr($query, $value)
    {
        if (empty($value[0]) && !empty($value[1]))
            $query->where('price', '<', $value[1]);
        if (!empty($value[0]) && empty($value[1]))
            $query->where('price', '>', $value[0]);
        if (!empty($value[0]) && !empty($value[1]))
            $query->whereBetween('price', [$value[0], $value[1]]);
    }

    public function searchBrandIdAttr($query, $value)
    {
        $query->whereIn('brand_id', $value);
    }

    public function searchIsGiftBagAttr($query, $value)
    {
        $query->where('is_gift_bag', $value);
    }

    public function searchIsGoodAttr($query, $value)
    {
        $query->where('is_good', $value);
    }

    public function searchIsUsedAttr($query, $value)
    {
        $query->where('is_used', $value);
    }

    public function searchProductTypeAttr($query, $value)
    {
        $query->where('Product.product_type', $value);
    }

//    public function searchSeckillStatusAttr($query, $value)
//    {
//        $product_id = (new StoreSeckillActiveDao())->getStatus($value)->column('product_id');
//        $query->whereIn('Product.product_id', $product_id);
//    }

    public function searchStoreNameAttr($query, $value)
    {
        $query->where('Product.store_name', 'like', '%' . $value . '%');
    }

    public function searchMerStatusAttr($query, $value)
    {
        $query->where('mer_status', $value);
    }

    public function searchProductIdAttr($query, $value)
    {
        $query->where('Product.product_id', $value);
    }

    public function searchProductIdsAttr($query, $value)
    {
        $query->whereIn('Product.product_id', $value);
    }

    public function searchNotProductIdAttr($query, $value)
    {
        if (!empty($value)) {
            if (is_array($value)) {
                $query->whereNotIn('Product.product_id', $value);
            } else {
                $query->where('Product.product_id', '<>', $value);
            }
        }

    }

    public function searchPriceOnAttr($query, $value)
    {
        $query->where('price', '>=', $value);
    }

    public function searchPriceOffAttr($query, $value)
    {
        $query->where('price', '<=', $value);
    }

    public function searchisFictiAttr($query, $value)
    {
        $query->where('type', 'in', $value);
    }

    public function searchGuaranteeTemplateIdAttr($query, $value)
    {
        $query->whereIn('guarantee_template_id', $value);
    }

    public function searchTempIdAttr($query, $value)
    {
        $query->whereIn('Product.temp_id', $value);
    }

    public function searchDateAttr($query, $value)
    {
        getModelTime($query, $value, 'Product.create_time');
    }

    public function searchFormIdAttr($query, $value)
    {
        if ($value !== '') {
            $query->whereIn('Product.mer_form_id', $value);
        }

    }

    public function searchMerFormIdAttr($query, $value)
    {
        if ($value !== '') {
            $query->whereIn('Product.mer_form_id', $value);
        }
    }

    public function searchCateHotAttr($query, $value)
    {
        if ($value !== '') {
            $query->whereIn('Product.cate_hot', $value);
        }
    }

    public function searchInTypeAttr($query, $value)
    {
        if ($value !== '') {
            $query->whereIn('Product.type', $value);
        }
    }

    public function searchNotActiveIdAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('Product.active_id', '<>',$value);
        }
    }

}
