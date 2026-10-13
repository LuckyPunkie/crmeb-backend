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


namespace app\common\model\system\merchant;


use app\common\model\system\Relevance;
use app\common\model\circle\Circle;
use app\common\dao\store\product\ProductDao;
use app\common\model\BaseModel;
use app\common\model\store\coupon\StoreCouponProduct;
use app\common\model\store\coupon\StoreCouponUser;
use app\common\model\store\product\Product;
use app\common\model\store\product\Spu;
use app\common\model\store\service\StoreService;
use app\common\model\system\config\SystemConfigValue;
use app\common\model\system\financial\Financial;
use app\common\model\system\serve\ServeOrder;
use app\common\repositories\store\StoreActivityRepository;

class Merchant extends BaseModel
{

    /**
     * @return string
     * @author xaboy
     * @day 2020-03-30
     */
    public static function tablePk(): string
    {
        return 'mer_id';
    }

    /**
     * @return string
     * @author xaboy
     * @day 2020-03-30
     */
    public static function tableName(): string
    {
        return 'merchant';
    }

    public function getDeliveryWayAttr($value)
    {
        if (!$value) return [];
        return explode(',',$value);
    }

    public function product()
    {
        return $this->hasMany(Product::class, 'mer_id', 'mer_id');
    }

    public function config()
    {
        return $this->hasMany(SystemConfigValue::class, 'mer_id', 'mer_id');
    }

    public function getConfigAttr()
    {
        $configKeys = [
            'mer_integral_status',  // 积分状态
            'mer_integral_rate',    // 积分抵扣比例
            'mer_store_stock',      // 警戒库存
            'mer_take_status',      // 自提点状态
            'mer_take_name',        // 自提点名称
            'mer_take_phone',       // 自提点电话
            'mer_take_address',     // 自提点地址
            'mer_take_location',    // 自提点经纬度
            'mer_take_day',         // 自提点工作时间
            'svip_coupon_merge',    // 是否支持付费会员和优惠卷合并使用
            'mer_take_time',        // 自提点时间
            'mer_open_receipt'      // 是否开启发票
        ];
        return merchantConfig($this->mer_id, $configKeys);
    }

    public function showProduct()
    {
        return $this->hasMany(Product::class, 'mer_id', 'mer_id')
            ->where((new ProductDao())->productShow())
            ->field('mer_id,product_id,store_name,image,price,is_show,status,is_gift_bag,is_good')
            ->order('is_good DESC,sort DESC');
    }

    /**
     *  商户列表下的推荐
     * @return \think\Collection
     * @author Qinii
     * @day 4/20/22
     */
    public function getAllRecommendAttr()
    {
        $list = Product::where('mer_id', $this['mer_id'])
            ->with(['reservation','merchant'])
            ->where((new ProductDao())->productShow())
            ->field('mer_id,product_id,store_name,image,price,is_show,status,is_gift_bag,is_good,cate_id,activity_label_ids,delivery_free,give_coupon_ids,delivery_way,type,product_type')
            ->order('is_good DESC, sort DESC, create_time DESC')
            ->limit(10)
            ->select()->append(['show_svip_info','activity_labels']);
        if ($list) {
            $list = app()->make(StoreActivityRepository::class)->getPic($list->toArray(),StoreActivityRepository::ACTIVITY_TYPE_BORDER);
        }
       return $list;
    }

    public function getCityRecommendAttr()
    {
        $list = Product::where('mer_id', $this['mer_id'])
            ->with(['reservation','merchant'])
            ->where((new ProductDao())->productShow())
            ->whereLike('delivery_way',"%1%")
            ->field('mer_id,product_id,store_name,image,price,is_show,status,is_gift_bag,is_good,cate_id,activity_label_ids,delivery_free,give_coupon_ids,delivery_way,type,product_type')
            ->order('sort DESC, create_time DESC')
            ->limit(3)
            ->select()->append(['activity_labels']);
        if ($list) {
            $list = app()->make(StoreActivityRepository::class)->getPic($list->toArray(),StoreActivityRepository::ACTIVITY_TYPE_BORDER);
        }
        return $list;
    }

    public function getStoreGroupIdsAttr()
    {
        $storeGroupIds = Relevance::where('right_id', $this->mer_id)
            ->where('type', 'store_group')
            ->column('left_id');

        if(!$storeGroupIds) {
            return [];
        }
        return StoreGroup::whereIn('store_group_id', $storeGroupIds)
            ->column('store_group_id');
    }


    public function recommend()
    {
        return $this->hasMany(Product::class, 'mer_id', 'mer_id')
            ->with(['reservation','merchant'])
            ->where((new ProductDao())->productShow())
            ->where('is_good', 1)
            ->field('mer_id,product_id,store_name,image,price,is_show,status,is_gift_bag,is_good,sales,create_time,activity_label_ids,delivery_free,give_coupon_ids,delivery_way,type,product_type')
            ->order('is_good DESC,sort DESC,create_time DESC')
            ->limit(3);
    }


    public function coupon()
    {
        $time = date('Y-m-d H:i:s');
        return $this->hasMany(StoreCouponUser::class, 'mer_id', 'mer_id')->where('start_time', '<', $time)->where('end_time', '>', $time)
            ->where('is_fail', 0)->where('status', 0)->order('coupon_price DESC, coupon_user_id ASC')
            ->with(['product' => function ($query) {
                $query->field('coupon_id,product_id');
            }, 'coupon' => function ($query) {
                $query->field('coupon_id,type');
            }]);
    }

    public function merchantRegion()
    {
        return $this->hasOne(Circle::class, 'circle_id', 'region_id')
            ->field('circle_id, pid, name, circle_agent_id, commission_type, commission_rate, level, status,type')
            ->where('status', 1)->where('type', 0);
    }

    public function merchantBusiness()
    {
        return $this->hasOne(Circle::class, 'circle_id', 'business_id')
            ->field('circle_id, pid, name, circle_agent_id, level, status,type')
            ->where('status', 1)->where('type', 1);
    }

    public function getServicesTypeAttr()
    {
        //0:关闭
        //1:系统客服
        //2:拨打电话
        //3:企业微信
        //4:跳转链接
        $config = merchantConfig($this->mer_id,['services_type','service_phone','mer_customer_url','mer_customer_corpId','mer_customer_link']);
        if ($config['services_type'] == 1) {
            $where = ['mer_id' => $this->mer_id, 'is_open' => 1,'status' => 1];
            $service = StoreService::where($where)->count();
            if (!$service)  $config['services_type'] = 0;
        }
        return $config;
    }

    public function marginOrder()
    {
        return $this->hasMany(ServeOrder::class, 'mer_id','mer_id')->where('type', 10)->order('create_time DESC');
    }

    public function refundMarginOrder()
    {
        return $this->hasOne(Financial::class, 'mer_id', 'mer_id')
            ->where('type',1)
            ->where('status', -1)
            ->order('create_time DESC')
            ->limit(1);
    }

    public function merchantCategory()
    {
        return $this->hasOne(MerchantCategory::class, 'merchant_category_id', 'category_id');
    }

    public function merchantType()
    {
        return $this->hasOne(MerchantType::class, 'mer_type_id', 'type_id');
    }

    public function getMerTypeNameAttr()
    {
        return MerchantType::where('mer_type_id',$this->type_id)->value('type_name');
    }

    public function typeName()
    {
        return $this->merchantType()->bind(['type_name']);
    }

    public function categoryName()
    {
        return $this->merchantCategory()->bind(['category_name']);
    }

    public function getMerCommissionRateAttr()
    {
        return $this->commission_swtich ? $this->commission_rate : $this->merchantCategory->commission_rate;
    }

    public function getOpenReceiptAttr()
    {
        return (int)merchantConfig($this->mer_id, 'mer_open_receipt');
    }

    public function admin()
    {
        return $this->hasOne(MerchantAdmin::class, 'mer_id', 'mer_id')->where('level', 0);
    }


    public function searchKeywordAttr($query, $value)
    {
        $query->whereLike('mer_name|mer_keyword', "%{$value}%");
    }

    public function getFinancialAlipayAttr($value)
    {
        return $value ? json_decode($value) : $value;
    }

    public function getFinancialWechatAttr($value)
    {
        return $value ? json_decode($value) : $value;
    }

    public function getFinancialBankAttr($value)
    {
        return $value ? json_decode($value) : $value;
    }

    public function getMerCertificateAttr()
    {
        return merchantConfig($this->mer_id, 'mer_certificate');
    }

    public function getIssetCertificateAttr()
    {
        return count(merchantConfig($this->mer_id, 'mer_certificate') ?: []) > 0;
    }

    public function getMarginRemindStatusAttr()
    {
        if (systemConfig('margin_remind_switch') == '1' && $this->mer_state) {
            if ($this->is_margin == 10) {
                if($this->ot_margin > $this->margin) {
                    if (!$this->margin_remind_time) {
                        $day = systemConfig('margin_remind_day') ?: 0;
                        if($day) {
                            $time = strtotime(date('Y-m-d 23:59:59',strtotime("+ $day day",time())));
                            $this->margin_remind_time = $time;
                        } else {
                            $this->status = 0;
                            $this->mer_state = 0;
                        }
                        $this->save();
                    }
                    return $this->margin_remind_time;
                }
            }
        }
        return null;
    }

    public function searchMerIdsAttr($query, $value)
    {
        $query->whereIn('mer_id',$value);
    }
}
