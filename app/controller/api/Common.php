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


namespace app\controller\api;


use crmeb\services\pay\Pay;
use crmeb\services\wechat\Payment;
use app\common\repositories\delivery\DeliveryOrderRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\store\product\ProductAssistSetRepository;
use app\common\repositories\store\product\ProductGroupBuyingRepository;
use app\common\repositories\store\product\ProductGroupRepository;
use app\common\repositories\store\product\ProductPresellRepository;
use app\common\repositories\store\product\ProductRepository;
use app\common\repositories\store\shipping\ExpressRepository;
use app\common\repositories\store\StoreCategoryRepository;
use app\common\repositories\system\attachment\AttachmentCategoryRepository;
use app\common\repositories\system\attachment\AttachmentRepository;
use app\common\repositories\system\CacheRepository;
use app\common\repositories\system\diy\DiyRepository;
use app\common\repositories\system\groupData\GroupDataRepository;
use app\common\repositories\system\groupData\GroupRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\system\notice\SystemNoticeConfigRepository;
use app\common\repositories\user\UserRepository;
use app\common\repositories\user\UserSignRepository;
use app\common\repositories\user\UserVisitRepository;
use app\common\repositories\wechat\TemplateMessageRepository;
use app\common\repositories\wechat\WechatUserRepository;
use crmeb\basic\BaseController;
use crmeb\services\AlipayService;
use crmeb\services\CopyCommand;
use crmeb\services\ImageWaterMarkService;
use crmeb\services\UploadService;
use Exception;
use Joypack\Tencent\Map\Bundle\Location;
use Joypack\Tencent\Map\Bundle\LocationOption;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Log;
use think\Response;
use app\controller\api\store\product\BindSpreadTrait;

/**
 * Class Common
 * @package app\controller\api
 * @author xaboy
 * @day 2020/5/28
 */
class Common extends BaseController
{
    use BindSpreadTrait;

    /**
     * @return mixed
     * @author xaboy
     * @day 2020/5/28
     */
    public function hotKeyword()
    {
        $type = $this->request->param('type/d');
        switch ($type) {
            case 0:
                $keyword = systemGroupData('hot_keyword');
                break;
            case 1:
                $keyword = systemGroupData('community_hot_keyword');
                break;
        }
        return app('json')->success($keyword);
    }

    /**
     * C端-获取快递公司列表
     * @param ExpressRepository $repository 快递公司仓储
     * @return mixed
     */
    public function express(ExpressRepository $repository)
    {
        return app('json')->success($repository->options());
    }

    /**
     * C端-获取个人中心页主题、横幅与菜单配置
     * @return mixed
     */
    public function menus()
    {
        return app('json')->success([
            'global_theme' => $this->getThemeVar(systemConfig('global_theme')),
            'banner' => systemGroupData('my_banner'),
            'menu' => systemGroupData('my_menus')
        ]);
    }

    /**
     * C端-获取退款说明文案（按行拆分返回）
     * @return mixed
     */
    public function refundMessage()
    {
        return app('json')->success(explode("\n", systemConfig('refund_message')));
    }

    /**
     * 获取指定类型的主题变量
     * @param $type 主题类型
     * @return mixed
     */
    private function getThemeVar($type)
    {
        return app()->make(DiyRepository::class)->getThemeVar($type);
    }

    /**
     * C端-获取系统配置信息（缓存10分钟）
     * @return mixed
     */
    public function config()
    {
        $config = Cache::remember('get_api_config',function(){
            $config = systemConfig(['open_update_info', 'store_street_theme', 'is_phone_login', 'global_theme', 'integral_status', 'mer_location', 'alipay_open', 'hide_mer_status', 'mer_intention_open', 'share_info', 'share_title', 'share_pic', 'store_user_min_recharge', 'recharge_switch', 'balance_func_status', 'yue_pay_status', 'site_logo', 'routine_logo', 'site_name', 'login_logo', 'procudt_increase_status',  'member_status', 'copy_command_status', 'community_status', 'community_reply_status', 'community_app_switch',  'recommend_switch','member_interests_status', 'beian_sn', 'community_reply_auth', 'hot_ranking_switch', 'svip_switch_status', 'margin_ico', 'margin_ico_switch', 'first_avatar_switch', 'wechat_phone_switch', 'community_auth', 'promoter_type', 'register_popup_url','register_popup_pic','offline_switch','pay_weixin_open','integral_clear_time','integral_community_give','integral_community_give_limit','integral_freeze','integral_money','integral_order_rate','integral_status','integral_user_give','agent_application_form','business_application_form', 'business_background_image','category_number_count','platform_delivery_form','platform_delivery_status','platform_delivery_background']);
            $cache = app()->make(CacheRepository::class)->search(['copyright_status', 'copyright_context', 'copyright_image', 'sys_intention_agree']);
            if (!isset($cache['sys_intention_agree'])) {
                $cache['sys_intention_agree'] = systemConfig('sys_intention_agree');
            }
            $title = app()->make(UserSignRepository::class)->signConfig();
            if (!$title) {
                $config['member_status'] = 0;
            }
            $config['tempid'] = app()->make(SystemNoticeConfigRepository::class)->getSubscribe();
            $config['global_theme'] = $this->getThemeVar($config['global_theme']);
            $config['wechat_config_appid'] = systemConfig('wechat_appid') ? true : false;
            $config = array_merge($config, $cache);
            $config['ajcaptcha'] = 'blockPuzzle';
            $config['service_type'] = systemConfig(['sys_service_switch','customer_url','customer_corpId',
                'sys_phone','customer_link']);
            $config['yue_pay_status'] = ($config['yue_pay_status'] && $config['balance_func_status']) ? 1: 0;
            $config['extract_switch'] = systemConfig(['withdraw_type','extract_switch','sys_extension_type']);
            if (empty($config['extract_switch']['withdraw_type'])) $config['extract_switch']['withdraw_type'] = ['4'];
            return $config;
        }, 600);
        return app('json')->success($config);
    }

    /**
     * @param GroupDataRepository $repository
     * @return mixed
     * @author xaboy
     * @day 2020/6/3
     */
    public function userRechargeQuota(GroupDataRepository $repository)
    {
        $recharge_quota = $repository->groupDataId('user_recharge_quota', 0);
        $recharge_attention = explode("\n", systemConfig('recharge_attention'));
        return app('json')->success(compact('recharge_quota', 'recharge_attention'));
    }

    /**
     * @param $field
     * @return mixed
     * @author xaboy
     * @day 2020/5/28
     */
    public function uploadImage($field)
    {
        $name = $this->request->param('name/s');
        $file = $this->request->file($field);
        if (!$file)
            return app('json')->fail('请上传图片');
        if ($name) {
            $f = $this->request->getOriginFile($field);
            if ($f) {
                $f['name'] = $name;
            }
            $this->request->setOriginFile($field, $f);
            $file = $this->request->file($field);
        }
        $file = is_array($file) ? $file[0] : $file;
        validate(["$field|图片" => [
            'fileSize' => config('upload.filesize'),
            'fileExt' => 'jpg,jpeg,png,bmp,gif',
            'fileMime' => 'image/jpeg,image/png,image/gif,application/octet-stream'
        ]])->check([$field => $file]);
        $upload = UploadService::create();
        $info = $upload->to('def')->move($field);
        if ($info === false) {
            return app('json')->fail($upload->getError());
        }
        $res = $upload->getUploadInfo();
        $res['dir'] = tidy_url($res['dir']);
        return app('json')->success('上传成功', ['path' => $res['dir']]);
    }

    /**
     * C端-扫码上传图片（校验扫码商户后上传，并记录附件信息）
     * @param $field 上传文件字段名
     * @param $token 扫码上传令牌
     * @param AttachmentCategoryRepository $repository 附件分类仓储
     * @return mixed
     */
    public function scanUploadImage($field, $token, AttachmentCategoryRepository $repository)
    {
        $id = $this->request->param('pid/d');
        $merId = $this->request->param('mer_id/d',0);
        $_merId = Cache::get('scan_mer_'.$token);
        if ($merId != $_merId) return app('json')->fail('商户ID错误，请重新扫码上传');
        $name = $this->request->param('name/s');
        $file = $this->request->file($field);
        if (!$file)
            return app('json')->fail('请上传图片');
        if ($name) {
            $f = $this->request->getOriginFile($field);
            if ($f) {
                $f['name'] = $name;
            }
            $this->request->setOriginFile($field, $f);
            $file = $this->request->file($field);
        }
        if (!$file)
            return app('json')->fail('请上传图片');
        $file = is_array($file) ? $file[0] : $file;
        if ($id) {
            if (!$category = $repository->get($id, $merId))
                return app('json')->fail('目录不存在');
            $info = [
                'enname' => $category->attachment_category_enname,
                'id' => $category->attachment_category_id
            ];
        } else {
            $info = [
                'enname' => 'def',
                'id' => 0
            ];
        }
        validate(["$field|图片" => [
            'fileSize' => config('upload.filesize'),
            'fileExt' => 'jpg,jpeg,png,bmp,gif',
            'fileMime' => config('upload.image_fileMime'),
        ]])->check([$field => $file]);

        $type = (int)systemConfig('upload_type') ?: 1;
        $upload = UploadService::create($type);
        $data = $upload->to($info['enname'])->move($field);
        if ($data === false) {
            return app('json')->fail($upload->getError());
        }
        $res = $upload->getUploadInfo();
        $res['dir'] = tidy_url($res['dir']);
        $_name = '.' . $file->getOriginalExtension();
        $data = [
            'attachment_category_id' => $info['id'],
            'attachment_name' => str_replace($_name, '', $file->getOriginalName()),
            'attachment_src' => $res['dir']
        ];
        $attach = app()->make(AttachmentRepository::class)->create((int)$type, (int)$merId, 0, $data);
        Cache::sadd('scan_'.$token,$attach->attachment_id);
        return app('json')->success(['src' => $data['attachment_src']]);
    }

    /**
     * 微信支付回调入口（按渠道分发小程序/公众号/APP/服务商/合单支付回调处理）
     * @param $type 支付渠道类型
     * @return mixed
     */
    public function notify($type)
    {
        Log::info('微信支付回调类型:$type:' . var_export([$type], true));
        switch (urldecode($type)) {
            case 'routine':
                return Payment::instance()->setAccessEnd(Payment::MINI)->handleNotify();
            case 'wechat':
                return Payment::instance()->setAccessEnd(Payment::WEB)->handleNotify();
            case 'app':
                return Payment::instance()->setAccessEnd(Payment::APP)->handleNotify();
            case 'partner':
                return (new Pay('partner'))->handleNotify();
            case 'combine':
                return $this->wechatCombinePayNotify();
            default:
                throw new ValidateException('参数错误');
        }
    }

    /**
     * 微信退款回调入口（按渠道分发小程序/公众号/APP退款回调处理）
     * @param $type 退款渠道类型
     * @return mixed
     */
    public function refundNotify($type)
    {
        switch (urldecode($type)) {
            case 'routine':
                return Payment::instance()->setAccessEnd(Payment::MINI)->handleRefundedNotify();
            case 'wechat':
                return Payment::instance()->setAccessEnd(Payment::WEB)->handleRefundedNotify();
            case 'app':
                return Payment::instance()->setAccessEnd(Payment::APP)->handleRefundedNotify();
            default:
                throw new ValidateException('参数错误');
        }
    }


    /**
     * 电商收付通合并支付回调
     */
    public function wechatCombinePayNotify()
    {
        try {
            return Payment::instance()->CombineClient()->handleNotify(function ($notify, $successful) {
                if (!$successful) {
                    return false;
                }
                Log::info('微信合单支付成功回调' . var_export($notify, true));
                if (isset($notify['combine_out_trade_no'])) {
                    $is_combine = 1;
                    $order_sn = $notify['combine_out_trade_no'];
                } else {
                    $is_combine = 2;
                    $order_sn = $notify['out_trade_no'] ?? '';
                }
                try {
                    event('pay_success_order', ['order_sn' => $order_sn, 'data' => $notify, 'is_combine' => $is_combine]);
                } catch (\Exception $e) {
                    Log::info('微信合单支付回调业务处理失败:' . $e->getMessage());
                    return false;
                }
                return true;
            });
        } catch (Exception $e) {
            Log::info('服务商支付回调失败:' . var_export([$e->getMessage(), $e->getFile() . ':' . $e->getLine()], true));
        }
    }


    /**
     * 商户转账回调（企业付款到零钱等转账结果通知）
     * @param $type 转账回调类型
     * @return mixed
     */
    public function mchNotify($type)
    {
        try {
            Log::info('转账回调header:' . var_export([$this->request->header()], true));
            Log::info('转账回调type:' . $type);
            return Payment::instance()->handleMchNotify();
        } catch (Exception $e) {
            Log::info('转账回调失败:' . var_export([$e->getMessage(), $e->getFile() . ':' . $e->getLine(), $this->request->header()], true));
        }
    }

    /**
     *  支付宝支付回调
     * @return void
     * @author Qinii
     */
    public function alipayNotify()
    {
        $post = $_POST;
        $get = $_GET;
        $_POST = $this->request->post();
        $_GET = $this->request->get();
        Log::info('支付宝回调开始'.var_export([$this->request->param()],true));
        try {
            return AlipayService::handleNotify();
        } catch (Exception $e) {
            Log::info('支付宝回调失败:' . var_export([$e->getMessage()], true));
        } finally {
            $_POST = $post;
            $_GET = $get;
        }
    }

    /**
     * C端-获取系统版本及运行环境信息
     * @return mixed
     */
    public function getVersion()
    {
        return app('json')->success(['version' => get_code_version(), 'host' => request()->host(), 'system' => PHP_OS, 'php' => @phpversion()]);
    }

    /**
     * 获取图片base64
     * @return mixed
     */
    public function get_image_base64()
    {
        list($imageUrl, $codeUrl) = $this->request->params([
            ['image', ''],
            ['code', ''],
        ], true);
        checkSuffix([$imageUrl, $codeUrl]);
        try {
            $codeTmp = $code = $codeUrl ? image_to_base64($codeUrl) : '';
            if (!$codeTmp) {
                $putCodeUrl = put_image($codeUrl);
                $code = $putCodeUrl ? image_to_base64('./runtime/temp' . $putCodeUrl) : '';
                $code && unlink('./runtime/temp' . $putCodeUrl);
            }

            $imageTmp = $image = $imageUrl ? image_to_base64($imageUrl) : '';
            if (!$imageTmp) {
                $putImageUrl = put_image($imageUrl);
                $image = $putImageUrl ? image_to_base64('./runtime/temp' . $putImageUrl) : '';
                $image && unlink('./runtime/temp' . $putImageUrl);
            }
            return app('json')->success(compact('code', 'image'));
        } catch (Exception $e) {
            return app('json')->fail($e->getMessage());
        }
    }

    /**
     * C端-获取首页数据（横幅、菜单、热销、活动、广告、分类）
     * @return mixed
     */
    public function home()
    {
        $banner = systemGroupData('home_banner', 1, 10);
        $menu = systemGroupData('home_menu');
        $hot = systemGroupData('home_hot', 1, 4);
        $activity = systemGroupData('sys_activity', 1, 1)[0] ?? null;
        $activity_lst = systemGroupData('sys_activity', 1, 3);
        $ad = systemConfig(['home_ad_pic', 'home_ad_url']);
        $category = app()->make(StoreCategoryRepository::class)->getTwoLevel();
        return app('json')->success(compact('banner', 'menu', 'hot', 'ad','activity', 'activity_lst','category'));
    }

    /**
     * C端-获取活动列表（按商户或平台活动分组数据）
     * @param $id 商户ID，为0时获取平台活动
     * @return mixed
     */
    public function activityLst($id)
    {
        $merId = (int)$id;
        [$page, $limit] = $this->getPage();
        return app('json')->success($merId ? merchantGroupData($merId, 'mer_activity', $page, $limit) : systemGroupData('sys_activity', $page, $limit));
    }

    /**
     * C端-获取活动详情（附带商户信息）
     * @param $id 活动数据ID
     * @return mixed
     */
    public function activityInfo($id)
    {
        $activity = app()->make(GroupDataRepository::class)->getData((int)$id,1);
        if (!$activity) {
            return app('json')->fail('活动不存在');
        }
        $activity['merchant'] = $activity['group_mer_id'] ? app()->make(MerchantRepository::class)->search(['mer_id' => $activity['group_mer_id']])->field('mer_name,mer_avatar')->find() : null;
        return app('json')->success($activity);
    }

    /**
     * C端-记录用户页面访问（区分小程序与其他页面）
     * @return mixed
     */
    public function visit()
    {
        if (!$this->request->isLogin()) return app('json')->success();
        [$page, $type] = $this->request->params(['page', 'type'], true);
        $uid = $this->request->uid();
        if (!$page || !$uid) return app('json')->fail();
        $userVisitRepository = app()->make(UserVisitRepository::class);
        $type == 'routine' ? $userVisitRepository->visitSmallProgram($uid, $page) : $userVisitRepository->visitPage($uid, $page);
        return app('json')->success();
    }

    /**
     * C端-获取首页分类横幅（new/hot/best/good）
     * @param $type 分类类型：new、hot、best、good
     * @return mixed
     */
    public function hotBanner($type)
    {
        if (!in_array($type, ['new', 'hot', 'best', 'good']))
            $data = [];
        else
            $data = systemGroupData($type . '_home_banner');
        return app('json')->success($data);
    }

    /**
     * C端-获取支付链接（从缓存中读取）
     * @param $key 支付链接缓存键
     * @return mixed
     */
    public function pay_key($key)
    {
        $cache = Cache::store();
        if (!$cache->has('pay_key' . $key)) {
            return app('json')->fail('支付链接不存在');
        }
        return app('json')->success($cache->get('pay_key' . $key));
    }

    /**
     * C端-腾讯地图坐标解析（经纬度逆地理编码获取地址信息）
     * @return mixed
     */
    public function lbs_geocoder()
    {
        $data = explode(',', $this->request->param('location/s', ''));
        $locationOption = new LocationOption(systemConfig('tx_map_key'));
        $locationOption->setLocation($data[0] ?? '', $data[1] ?? '');
        $location = new Location($locationOption);
        $res = $location->request();
        if ($res->error) {
            return app('json')->fail($res->error);
        }
        if ($res->status) {
            return app('json')->fail($res->message);
        }
        if (!$res->result) {
            return app('json')->fail('获取失败');
        }
        return app('json')->success($res->result);
    }

    /**
     * C端-获取指定区域与地址的定位信息
     * @return mixed
     */
    public function lbs_address()
    {
        $region = $this->request->param('region/s');
        $address = $this->request->param('address/s');
        $location =  lbs_address($region, $address);
        return app('json')->success($location);
    }


    /**
     * C端-解析分享口令获取商品/活动信息，并绑定上下级推广关系
     * @return mixed
     */
    public function getCommand()
    {
        $key = $this->request->param('key/s');
        if (!preg_match('/^(\/@[1-9]{1}).*\*\//', $key)) {
            return app('json')->fail('无效口令');
        }
        $userInfo = $this->request->isLogin() ? $this->request->userInfo() : null;
        $command = app()->make(CopyCommand::class)->getMassage($key);
        if (empty($command)) return app('json')->fail('无效口令');
        $info = [];
        if ($command['uid']) {
            $user = app()->make(UserRepository::class)->get($command['uid']);
            $info = [
                'uid' => $user['uid'],
                'nickname' => $user['nickname'],
                'avatar' => $user['avatar'],
            ];
        }
        switch ($command['type']) {
            case 0:
                $data = app()->make(ProductRepository::class)->detail((int)$command['id'], $userInfo);
                $ret['product_id'] = $command['id'];
                break;
            case 1:
                $data = app()->make(ProductRepository::class)->detail((int)$command['id'], $userInfo);
                $ret['product_id'] = $command['id'];
                break;
            case 2:
                $data = app()->make(ProductPresellRepository::class)->apiDetail((int)$command['id'], $userInfo);
                $ret['activity_id'] = $command['id'];
                break;
            case 4:
                $data = app()->make(ProductGroupRepository::class)->apiDetail($command['id'], $userInfo);
                $ret['activity_id'] = $command['id'];
                break;
            case 30:
                $data = app()->make(ProductAssistSetRepository::class)->detail($command['id'], $userInfo);
                $ret['activity_id'] = $command['id'];
                break;
            case 40:
                $data = app()->make(ProductGroupBuyingRepository::class)->detail($command['id'], $userInfo);
                $ret['activity_id'] = $command['id'];
                break;
        }
        if ($userInfo && $command['uid']) app()->make(UserRepository::class)->bindSpread($userInfo, $command['uid']);
        $ret['product_type'] = $command['type'];
        $ret['user'] = $info;
        $ret['com'] = $command['com'];
        $ret['data'] = $data;
        return app('json')->success($ret);
    }

    /**
     * C端-获取静态脚本配置
     * @return mixed
     */
    public function script()
    {
        return app('json')->success([]);
        return \response(systemConfig('static_script'));
    }

    /**
     * C端-获取APP版本更新信息
     * @return mixed
     */
    public function appVersion()
    {
        return app('json')->success(systemConfig([
            'appVersion',
            'iosAddress',
            'androidAddress',
            'openUpgrade'
        ]));
    }

    /**
     * 同城配送订单回调（更新配送订单状态）
     * @return void
     */
    public function deliveryNotify()
    {
        try {
            $params = $this->request->param();
            app()->make(DeliveryOrderRepository::class)->notify($params);
        } catch (Exception $e) {
            Log::info('同城配送订单回调失败:' . var_export([$e->getMessage(), $e->getFile() . ':' . $e->getLine()], true));
        }
    }

    /**
     * 商家寄件回调（解密数据后处理发货通知）
     * @return mixed
     */
    public function callBackNotify()
    {
        $data = $this->request->params([
            ['type', ''],
            ['data', ''],
            ['id',0],
        ]);
        $token = merchantConfig($data['id'], 'serve_token');
        try{
            $data['data'] = $this->decrypt($data['data'], $token);
            Log::info('商家寄件回调成功:' . var_export($data, true));
            app()->make(StoreOrderRepository::class)->shipmentNotify($data);
            return 'success';
        }catch (Exception $e) {
            Log::info('商家寄件回调失败:' . var_export(['data' => $data, 'msg'=> $e->getMessage()], true));
        }
    }
    function decrypt(string $encryptedData, string $key)
    {
        $key = substr($key, 0, 32);
        $decodedData = base64_decode($encryptedData);
        $iv = substr($decodedData, 0, 16);
        $encrypted = substr($decodedData, 16);
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return json_decode($decrypted, true);
    }

    /**
     * C端-获取DIY页面装修数据
     * @return mixed
     */
    public function diy()
    {
        $params = $this->request->params(['id','did']);
        $merId = $params['id'] ?: 0;
        $id = $params['did'] ?: 0;
        $data = app()->make(DiyRepository::class)->show((int)$merId, (int)$id, 1);
        return app('json')->encode($data);
    }

    /**
     * C端-获取底部导航配置（登录用户同时绑定推广关系）
     * @return mixed
     */
    public function getNavigation()
    {
        $userInfo = $this->request->isLogin() ? $this->request->userInfo() : null;
        $this->bindSpread($userInfo);

        return app('json')->success(app()->make(DiyRepository::class)->getNavigation());
    }

    /**
     * C端-获取小程序微页面（DIY）数据（带缓存）
     * @return mixed
     */
    public function micro()
    {
        $version = $this->request->param('version/s', 0);
        $id = $this->request->param('id/d', 0);
        $param = explode('=',$id);
        if ($param && $param[0] == '/pages/small_page/index?id') $id = $param[1];
        $data = Cache::remember('sys.get_sys_micro_'.$id,function()use($id){
            $data = app()->make(DiyRepository::class)->getMicro($id);
            return json_encode($data, JSON_UNESCAPED_UNICODE);
        }, 3600);
        $data = json_decode($data, true);
        if($version){
            return app('json')->encode($data);
        }
        return app('json')->success('ok',$data['data']);
    }

    /**
     * 是否关注
     * @return mixed
     */
    public function subscribe()
    {
        if ($this->request->isLogin()) {
            $user = $this->request->userInfo();
            if ($user && $user['wechat_user_id']) {
                $wechatUserService = app()->make(WechatUserRepository::class);
                $subscribe = $wechatUserService->getWhereCount([
                        'wechat_user_id' => $user['wechat_user_id'],
                        'subscribe' => 1
                    ]) > 0;
                return app('json')->success(['subscribe' => $subscribe]);
            }
        }
        return app('json')->success(['subscribe' => false, 'qrcode' => systemConfig('wechat_qrcode')]);
    }

    /**
     * C端-获取开屏广告数据及配置
     * @return mixed
     */
    public function open_screen()
    {
        $key = ['open_screen_advertising'];
        $group = app()->make(GroupRepository::class)->getSearch([])->where('group_key','in',$key)->find();
        $data= app()->make(GroupDataRepository::class)->getGroupDataLst($this->request->merId(),$group->group_id,1, 10);
        $data['config'] = systemConfig(['open_screen_switch','open_screen_time','open_screen_space']);
        return app('json')->success($data);
    }

    /**
     * C端-上传资质证书图片（上传后添加水印）
     * @param $field 上传文件字段名
     * @return mixed
     */
    public function uploadCertificate($field)
    {
        $file = $this->request->file($field);
        if (!$file) return app('json')->fail('请上传证书');
        validate(["file|图片" => [
            'fileSize' => config('upload.filesize'),
            'fileExt' => 'jpg,jpeg,png,bmp',
            'fileMime' => 'image/jpeg,image/png',
        ]])->check(['file' => $file]);
        $upload = UploadService::create(1);
        $data = $upload->to('attach')->move($field, false, false);
        if ($data === false) {
            return app('json')->fail($upload->getError());
        }
        app()->make(ImageWaterMarkService::class)->run(public_path() . $upload->getFileInfo()->filePath);
        return app('json')->success(['path' => tidy_url($upload->getFileInfo()->filePath)]);
    }
}
