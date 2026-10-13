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


namespace app\common\repositories\system;


use app\common\dao\system\CacheDao;
use app\common\repositories\BaseRepository;
use think\db\exception\DbException;
use think\exception\ValidateException;
use think\facade\Cache;

/**
 * Class CacheRepository
 * @package app\common\repositories\system
 * @author xaboy
 * @day 2020-04-24
 * @mixin CacheDao
 */
class CacheRepository extends BaseRepository
{

    //积分说明
    const  INTEGRAL_RULE    = 'sys_integral_rule';
    //商户入驻申请协议
    const  INTEGRAL_AGREE   = 'sys_intention_agree';
    //预售协议
    const  PRESELL_AGREE    = 'sys_product_presell_agree';
    //微信菜单
    const  WECHAT_MENUS     = 'wechat_menus';
    //发票说明
    const  RECEIPT_AGREE    = 'sys_receipt_agree';
    //佣金说明
    const  EXTENSION_AGREE  = 'sys_extension_agree';
    //商户类型说明
    const  MERCHANT_TYPE    = 'sys_merchant_type';
    //商户分类说明
    const  MERCHANT_CATEGORY = 'sys_merchant_category';
    //分销等级规则
    const  SYS_BROKERAGE    = 'sys_brokerage';
    //用户协议
    const  USER_AGREE       = 'sys_user_agree';
    //用户隐私协议
    const  USER_PRIVACY     = 'sys_userr_privacy';
    //免费会员
    const  SYS_MEMBER       = 'sys_member';
    //关于我们
    const  ABOUT_US         = 'sys_about_us';
    //资质证照
    const  SYS_CERTIFICATE  = 'sys_certificate';
    //注销声明
    const CANCELLATION_MSG  =  'the_cancellation_msg';
    //注销重要提示
    const CANCELLATION_PROMPT = 'the_cancellation_prompt';
    //平台规则
    const PLATFORM_RULE     = 'platform_rule';
    //优惠券说明
    const COUPON_AGREE = 'sys_coupon_agree';
    //付费会员协议
    const SYS_SVIP = 'sys_svip';
    //分销说明
    const PROMOTER_EXPLAIN = 'promoter_explain';
    //商圈入驻协议
    const CIRCLE_ENTRY_AGREE = 'circle_entry_agree';
    //商户入驻协议
    const BUSINESS_ENTRY_AGREE = 'business_entry_agree';


    public function getAgreeList($type)
    {
        $data = [
            ['label' => '用户协议',        'key' => self::USER_AGREE],
            ['label' => '隐私政策',        'key' => self::USER_PRIVACY],
            ['label' => '注销提示',     'key' => self::CANCELLATION_PROMPT],
        ];
        if (!systemConfig('mini_review_status')) {
            $data = array_merge($data,[
                ['label' => '平台规则',        'key' => self::PLATFORM_RULE],
                ['label' => '店铺入驻申请协议', 'key' => self::INTEGRAL_AGREE],
                ['label' => '代理入驻申请协议', 'key' => self::CIRCLE_ENTRY_AGREE],
                ['label' => '商户入驻申请协议', 'key' => self::BUSINESS_ENTRY_AGREE],
            ]);
        }
        if (!$type) {
            $data[] = ['label' => '注销声明', 'key' => self::CANCELLATION_MSG];
            $data[] = ['label' => '关于我们', 'key' => self::ABOUT_US];
            $data[] = ['label' => '资质证照', 'key' => self::SYS_CERTIFICATE];
        }
        return $data;
    }

    /**
     * 获取协议键值集合
     * 该方法用于返回一个包含所有协议键值的数组。这些键值代表了用户在使用服务前需要同意的各种协议。
     *
     * @return array 返回一个数组，数组中的每个元素都是一个代表协议类型的常量。
     */
    public function getAgreeKey(){
        // 返回包含所有协议键值的数组
        return [
            self::INTEGRAL_RULE, // 积分规则
            self::INTEGRAL_AGREE, // 积分协议
            self::PRESELL_AGREE, // 预售协议
            self::WECHAT_MENUS, // 微信菜单协议
            self::RECEIPT_AGREE, // 发票协议
            self::EXTENSION_AGREE, // 扩展协议
            self::MERCHANT_TYPE, // 商家类型协议
            self::MERCHANT_CATEGORY, // 商家分类协议
            self::SYS_BROKERAGE, // 系统佣金协议
            self::USER_AGREE, // 用户协议
            self::USER_PRIVACY, // 用户隐私协议
            self::SYS_MEMBER, // 会员系统协议
            self::ABOUT_US, // 关于我们协议
            self::SYS_CERTIFICATE, // 系统证书协议
            self::CANCELLATION_MSG, // 取消消息协议
            self::CANCELLATION_PROMPT, // 取消提示协议
            self::PLATFORM_RULE, // 平台规则协议
            self::COUPON_AGREE, // 优惠券协议
            self::SYS_SVIP, // SVIP系统协议
            self::PROMOTER_EXPLAIN, // 推广说明协议
            self::CIRCLE_ENTRY_AGREE, // 商圈入驻协议
            self::BUSINESS_ENTRY_AGREE, // 商户入驻协议
        ];
    }


    /**
     * CacheRepository constructor.
     * @param CacheDao $dao
     */
    public function __construct(CacheDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 保存结果到数据存储
     *
     * 本函数用于根据给定的键值对结果进行保存。如果键不存在，则创建一个新的记录；
     * 如果键已存在，则更新该记录的相关信息。保存的信息包括键、结果和过期时间。
     *
     * @param string $key 存储的键名，用于唯一标识一条记录。
     * @param mixed $result 要保存的结果数据，可以是任意类型。
     * @param int $expire_time 记录的过期时间，0表示永不过期。
     */
    public function save(string $key, $result, int $expire_time = 0)
    {
        // 检查键是否已存在
        if (!$this->dao->fieldExists('key', $key)) {
            // 如果键不存在，则创建新记录
            $this->dao->create(compact('key', 'result', 'expire_time'));
        } else {
            // 如果键已存在，则更新记录的结果和过期时间
            $this->dao->keyUpdate($key, compact('result', 'expire_time'));
        }
    }

    /**
     * 根据给定的关键字获取结果数据。
     *
     * 本函数旨在通过提供的关键字，从预定义的同意列表中查找对应的标签，并从数据访问对象（DAO）中获取该关键字对应的数据。
     * 如果关键字在同意列表中找到，将设置标题为对应的标签。然后，从DAO获取关键字对应的结果数据，如果数据不存在，则设置为空字符串。
     * 最后，返回包含标题和结果数据的数组。
     *
     * @param string $key 需要查询结果的关键字。
     * @return array 包含标题和查询结果的数据数组。
     */
    public function getResult($key)
    {
        // 初始化标题为空字符串
        $data['title'] = '';

        // 遍历同意列表中所有项目，寻找与关键字匹配的标签
        foreach ($this->getAgreeList(1) as $item) {
            if ($item['key'] == $key) {
                // 如果找到匹配的关键字，设置标题为对应的标签
                $data['title'] = $item['label'];
            }
        }

        // 从数据访问对象中获取关键字对应的结果数据，如果不存在则设置为空字符串
        $data[$key] = $this->dao->getResult($key) ?? '';

        // 返回包含标题和结果数据的数组
        return $data;
    }

    /**
     * 通过键值获取结果
     *
     * 本函数旨在通过指定的键值从数据访问对象（DAO）中获取对应的结果数据。
     * 它是类中的一个公共方法，可供类的外部调用，以获取特定键值对应的数据。
     *
     * @param string $key 需要获取结果的键值
     * @return mixed 返回与键值对应的结果数据，数据类型取决于具体实现
     */
    public function getResultByKey($key)
    {
        // 通过DAO对象的getResult方法，根据键值获取结果数据
        return $this->dao->getResult($key);
    }


    /**
     * 保存数组中的所有数据
     *
     * 该方法通过遍历给定的数组，并调用save方法来逐个保存数组中的键值对。
     * 主要用于一次性处理多个数据项的保存操作，减少代码重复和提高效率。
     *
     * @param array $data 包含需要保存的数据的键值对数组。数组的每个元素都是一个键值对，
     *                   其中键代表数据的唯一标识，值为要保存的具体数据。
     */
    public function saveAll(array $data)
    {
        // 遍历数组，对每个元素调用save方法进行保存
        foreach ($data as $k => $v) {
            $this->save($k, $v);
        }
    }


    /**
     * 设置用户协议内容
     * @return mixed
     */
    public function setUserAgreement($content)
    {
        $html = <<<HTML
<!doctype html>
<html class="x-admin-sm">
    <head>
        <meta charset="UTF-8">
        <title>隐私协议</title>
        <meta name="renderer" content="webkit|ie-comp|ie-stand">
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
        <meta name="viewport" content="width=device-width,user-scalable=yes, minimum-scale=0.4, initial-scale=0.8,target-densitydpi=low-dpi" />
        <meta http-equiv="Cache-Control" content="no-siteapp" />
    </head>
    <body class="index">
    $content
    </body>
</html>
HTML;
        file_put_contents(public_path() . 'protocol.html', $html);
    }

    public function setUserRegister($content)
    {
        $html = <<<HTML
<!doctype html>
<html class="x-admin-sm">
    <head>
        <meta charset="UTF-8">
        <title>用户协议</title>
        <meta name="renderer" content="webkit|ie-comp|ie-stand">
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
        <meta name="viewport" content="width=device-width,user-scalable=yes, minimum-scale=0.4, initial-scale=0.8,target-densitydpi=low-dpi" />
        <meta http-equiv="Cache-Control" content="no-siteapp" />
    </head>
    <body class="index">
    $content
    </body>
</html>
HTML;
        file_put_contents(public_path() . 'register.html', $html);
    }

    /**
     * 整理城市数据用的方法
     * @return array
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/24
     */
    public function addres()
    {
        return [];
        $re = (Cache::get('AAAAAA'));
        unset($re['省市编码']);
        if (!$re) throw new ValidateException('无数据');
        $shen = [];
        $shi = [];
        $qu = [];
        foreach ($re as $key => $value) {
            $item = explode(',', $value);
            $cout = count($item);
            //省
            if ($cout == 2) {
                $shen[$item[1]] = [
                    'value' => $key,
                    'label' => $item[1],
                ];
            }
            //市
            if ($cout == 3) {
                if ($item[1] == '') {
                    $shen[$item[2]] = [
                        'value' => $key,
                        'label' => $item[2],
                    ];
                    $item[1] = $item[2];
                }
                $_v = [
                    'value' => $key,
                    'label' => $item[2]
                ];
                $shi[$item[1]][] = $_v;
            }
            //区
            if ($cout == 4) {
                $_v = [
                    'value' => $key,
                    'label' => $item[3]
                ];
                $qu[$item[2]][] = $_v;
            }
        }
        $data = [];
        foreach ($shen as $s => $c) {
            foreach ($shi as $i => $c_) {
                if ($c['label'] == $i) {
                    if ($c['label'] == $i) {
                        $san = [];
                        foreach ($c_ as $key => $value) {
                            if (isset($qu[$value['label']])) {
                                $value['children'] = $qu[$value['label']];
                            }
                            $san[] = $value;
                        }
                    }
                    $c['children'] = $san;
                }
            }
            $zls[$s] = $c;
        }
        $data = array_values($zls);
        file_put_contents('address.js', json_encode($data, JSON_UNESCAPED_UNICODE));
        //$this->save('applyments_addres',$data);
    }
}
