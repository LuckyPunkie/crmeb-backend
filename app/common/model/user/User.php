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


namespace app\common\model\user;


use app\common\model\BaseModel;
use app\common\model\delivery\DeliveryService;
use app\common\model\store\service\StoreService;
use app\common\model\store\staff\Staffs;
use app\common\model\wechat\WechatUser;
use app\common\repositories\store\coupon\StoreCouponUserRepository;
use app\common\repositories\store\order\StoreGroupOrderRepository;
use app\common\repositories\store\service\StoreServiceLogRepository;
use app\common\repositories\system\config\ConfigValueRepository;
use app\common\repositories\user\UserBillRepository;
use app\common\repositories\user\UserExtractRepository;
use app\common\repositories\user\UserHistoryRepository;
use app\common\repositories\user\UserRelationRepository;
use app\common\repositories\user\UserRepository;

/**
 * Class User
 * @package app\common\model\user
 * @author xaboy
 * @day 2020-04-28
 */
class User extends BaseModel
{

    /**
     * @return string
     * @author xaboy
     * @day 2020-03-30
     */
    public static function tablePk(): string
    {
        return 'uid';
    }

    /**
     * @return string
     * @author xaboy
     * @day 2020-03-30
     */
    public static function tableName(): string
    {
        return 'user';
    }

    /**
     * @param $value
     * @return string
     * @author xaboy
     * @day 2020-05-09
     */
    public function getBirthdayAttr($value)
    {
        return $value == '0000-00-00' ? '' : $value;
    }

    public function getIsSvipAttr($value)
    {
        if (systemConfig('svip_switch_status') == 1) {
            return $value;
        } else {
            return $value > 0 ? 0 : $value;
        }
    }

    public function getPhoneAttr($value)
    {
        if (env('SHOW_PHONE', false) && app('request')->hasMacro('adminInfo') && $value && is_numeric($value)) {
            if (app('request')->userType() !== 2 || app('request')->adminInfo()['level'] !== 0) {
                return substr_replace($value, '****', 3, 4);
            }
        }
        return $value;
    }

    public function getAccountAttr($value)
    {
        if (env('SHOW_PHONE', false) && app('request')->hasMacro('adminInfo') && $value && is_numeric($value)) {
            if (app('request')->userType() !== 2 || app('request')->adminInfo()['level'] !== 0) {
                return substr_replace($value, '****', 3, 4);
            }
        }
        return $value;
    }

    /**
     * @param $value
     * @return array
     * @author xaboy
     * @day 2020-05-09
     */
    public function getLabelIdAttr($value)
    {
        if (empty($value)) {
            return [];
        }
        return array_map(function ($item) {
            return (int)$item;
        }, explode(',', $value));
    }

    /**
     * @param $value
     * @return string
     * @author xaboy
     * @day 2020-05-09
     */
    public function setLabelIdAttr($value)
    {
        return is_array($value) ? implode(',', $value) : $value;
    }

    public function getValidSpreadUidAttr()
    {
        if (!$this->spread_uid) return 0;
        $data = self::getDB()->where('uid', $this->spread_uid)->field('is_promoter,spread_uid')->find();
        $extensionInfo = get_extension_info($data);//获取用户是否可以分销以及是否内购
        return $data && $extensionInfo['isPromoter'] ?  $this->spread_uid : 0;
    }

    public function getValidSpreadAttr()
    {
        if (!$this->spread_uid) return null;
        $data = self::getDB()->where('uid', $this->spread_uid)->find();
        $extensionInfo = get_extension_info($data);//获取用户是否可以分销以及是否内购
        return $data && $extensionInfo['isPromoter'] ?  $data :  null;
    }

    public function getValidTopUidAttr()
    {
        if (!$this->top_uid) return 0;
        $data = self::getDB()->where('uid', $this->spread_uid)->find();
        $extensionInfo = get_extension_info($data);//获取用户是否可以分销以及是否内购
        return $data && $extensionInfo['isPromoter'] ?  $this->top_uid :  0;
    }

    public function getValidTopAttr()
    {
        if (!$this->top_uid) return null;
        $data = self::getDB()->where('uid', $this->top_uid)->find();
        $extensionInfo = get_extension_info($data);//获取用户是否可以分销以及是否内购
        return $data && $extensionInfo['isPromoter'] ?  $data :  null;
    }

    public function getTopUidAttr()
    {
        return self::getDB()->where('uid', $this->spread_uid)->value('spread_uid') ?: 0;
    }

    /**
     * @return \think\model\relation\HasOne
     * @author xaboy
     * @day 2020-05-09
     */
    public function group()
    {
        return $this->hasOne(UserGroup::class, 'group_id', 'group_id');
    }

    public function spread()
    {
        return $this->hasOne(User::class, 'uid', 'spread_uid');
    }

    public function brokerage()
    {
        return $this->hasOne(UserBrokerage::class, 'brokerage_level', 'brokerage_level')->where('type', 0);
    }

    public function member()
    {
        return $this->hasOne(UserBrokerage::class, 'brokerage_level', 'member_level')->where('type', 1);
    }

    public function memberIcon()
    {
        return $this->hasOne(UserBrokerage::class, 'brokerage_level', 'member_level')->where('type', 1)->bind(['member_icon' => 'brokerage_icon']);
    }

    public function getUserLabelAttr()
    {
        return UserLabel::whereIn('label_id', $this->label_id)->select();
    }

    public function getUserFields()
    {
        return $this->hasOne(UserFields::class, 'uid', 'uid');
    }

    /**
     * @param $spreadUid
     * @author xaboy
     * @day 2020-04-28
     */
    public function setSpread($spreadUid)
    {
        if (self::getDB()->where('uid', $spreadUid)->value('is_promoter'))
            $this->save([
                'spread_uid' => $spreadUid,
                'spread_time' => date('Y-m-d H:i:s')
            ]);
    }

    public function service()
    {
        return $this->hasOne(StoreService::class, 'uid', 'uid')
            ->where('mer_id', '<>', 0)
            ->where('is_del', 0)
            ->where('is_open', 1)
            ->field('service_id,uid,nickname,avatar,customer,mer_id,is_verify,is_goods,is_open')
            ->order('is_verify DESC,customer DESC');
    }

    public function topService()
    {
        return $this->hasOne(StoreService::class, 'uid', 'uid')
            ->where('mer_id', 0)->where('is_open',1)
            ->where('is_del', 0)
            ->field('service_id,uid,nickname,avatar,customer,mer_id,is_verify,is_goods')
            ->order('is_verify DESC,customer DESC');
    }
    public function staffs()
    {
        return $this->hasMany(Staffs::class, 'uid', 'uid')->where('status', 1);
    }

    public function delivery()
    {
        return $this->hasMany(DeliveryService::class, 'uid', 'uid')->where('status', 1);
    }

    public function getLockBrokerageAttr()
    {
        return app()->make(UserBillRepository::class)->lockBrokerage($this->uid) ?: 0;
    }

    public function getLockIntegralAttr()
    {
        return app()->make(UserBillRepository::class)->lockIntegral($this->uid) ?: 0;
    }

    public function getYesterdayBrokerageAttr()
    {
        return app()->make(UserBillRepository::class)->yesterdayBrokerage($this->uid) ?: 0;
    }

    public function getTotalExtractAttr()
    {
        return app()->make(UserExtractRepository::class)->userTotalExtract($this->uid) ?: 0;
    }

    public function getTotalBrokerageAttr()
    {
        return app()->make(UserBillRepository::class)->totalBrokerage($this->uid) ?: 0;
    }

    public function getTotalBrokeragePriceAttr()
    {
        return bcadd($this->lock_brokerage, $this->brokerage_price, 2);
    }

    public function getTotalIntegralAttr()
    {
        return bcadd($this->lock_integral, $this->integral, 0);
    }

    public function getTotalRechargeAttr()
    {
        return app()->make(UserBillRepository::class)->userNowMoneyIncTotal($this->uid);
    }

    public function getTotalConsumeAttr()
    {
        return app()->make(StoreGroupOrderRepository::class)->totalNowMoney($this->uid);
    }

    public function getTotalCollectProductAttr()
    {
        return app()->make(UserRelationRepository::class)->getWhereCount(['uid' => $this->uid, 'type' => 1]);
    }

    public function getTotalCollectStoreAttr()
    {
        return app()->make(UserRelationRepository::class)->getWhereCount(['uid' => $this->uid, 'type' => 10]);
    }

    public function getTotalVisitProductAttr()
    {
        return app()->make(UserHistoryRepository::class)->userTotalHistory($this->uid);
    }

    public function getTotalCouponAttr()
    {
        return app()->make(StoreCouponUserRepository::class)->userTotal($this->uid, 0);
    }

    public function getTotalUnreadAttr()
    {
        return app()->make(StoreServiceLogRepository::class)->totalUnReadNum($this->uid);
    }

    public function getOneLevelCountAttr()
    {
        return app()->make(UserRepository::class)->getOneLevelCount($this->uid);
    }

    public function getTwoLevelCountAttr()
    {
        return app()->make(UserRepository::class)->getTwoLevelCount($this->uid);
    }

    public function getSpreadTotalAttr()
    {
        return $this->one_level_count + $this->two_level_count;
    }

    public function wechat()
    {
        return $this->hasOne(WechatUser::class, 'wechat_user_id', 'wechat_user_id');
    }

    public function fields()
    {
        return $this->hasOne(UserFields::class, 'uid', 'uid');
    }

    public function getIntegralAttr($val)
    {
        return is_null($val) ? 0 : $val;
    }


//    public function getUserTypeAttr()
//    {
//        if($this->wechat['openid']){
//            return 'wechat';
//        }elseif($this->wechat['routine_openid']){
//            return 'routine';
//        }else{
//            return 'H5';
//        }
//    }

    public function getSubscribeAttr()
    {
        if ($this->wechat['openid'] && $this->wechat['subscribe']) {
            return true;
        } else {
            return false;
        }
    }

    public function getAvatarAttr($value)
    {
        if (empty($value)) {
            return systemConfig('user_default_avatar') ?: rtrim(systemConfig('site_url'), '/') . '/static/f.png';
        }
        return $value;
    }


    /**
     * 是否分销获取器
     * @param $value
     * @param $data
     * @return void
     */
    public function getIsPromoterAttr($value,$data)
    {
        $extensionInfo = get_extension_info($data);//获取用户是否可以分销以及是否内购
        return $extensionInfo['isPromoter'];
    }


}
