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


namespace app\controller\admin;


use think\facade\Db;
use app\common\repositories\system\diy\DiyRepository;
use app\common\repositories\store\order\StoreOrderProductRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\system\auth\MenuRepository;
use app\common\repositories\system\CacheRepository;
use app\common\repositories\system\config\ConfigClassifyRepository;
use app\common\repositories\system\config\ConfigRepository;
use app\common\repositories\system\config\ConfigValueRepository;
use app\common\repositories\system\CountRepository;
use app\common\repositories\system\DataScreenRepository;
use app\common\repositories\system\merchant\MerchantCategoryRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\user\UserRepository;
use app\common\repositories\user\UserVisitRepository;
use crmeb\basic\BaseController;
use crmeb\services\HttpService;
use crmeb\services\UploadService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\ValidateException;
use think\facade\Cache;

/**
 * Class Common
 * @package app\controller\admin
 * @author xaboy
 * @day 2020/6/25
 */
class Common extends BaseController
{

    /**
     * @return mixed
     * @author xaboy
     * @day 2020/6/25
     */
    public function main()
    {
        $res = Cache::remember(self::class . '@sysmain', function () {
            $today = $this->mainGroup('today');
            $yesterday = $this->mainGroup('yesterday');
            $month = $this->mainGroup('month');
            $lastWeek = $this->mainGroup(date('Y-m-d', strtotime('- 7day')));
            $lastWeekRate = [];
            foreach ($lastWeek as $k => $item) {
                $lastWeekRate[$k] = $this->getRate($item, $today[$k], 4);
            }
            return compact('today', 'yesterday', 'lastWeekRate','month');
        }, 1800 + random_int(600, 1200));
        return app('json')->success($res);
    }

    /**
     * 上传视频key
     * @return \think\response\Json
     * @author Qinii
     * @day 3/11/22
     */
    public function temp_key()
    {
        $upload = UploadService::create();
        $re = $upload->getTempKeys();
        return app('json')->success($re);
    }

    /**
     * @param $date
     * @return array
     * @author xaboy
     * @day 2020/6/25
     */
    protected function mainGroup($date)
    {
        $userRepository = app()->make(UserRepository::class);
        $storeOrderRepository = app()->make(StoreOrderRepository::class);
        $merchantRepository = app()->make(MerchantRepository::class);
        $userVisitRepository = app()->make(UserVisitRepository::class);
        $payPrice = (float)$storeOrderRepository->dayOrderPrice($date);
        $userNum = (float)$userRepository->newUserNum($date);
        $storeNum = (float)$merchantRepository->dateMerchantNum($date);
        $visitUserNum = (float)$userVisitRepository->dateVisitUserNum($date);
        $visitNum = (float)$userVisitRepository->dateVisitNum($date);

        return compact('payPrice', 'userNum', 'storeNum', 'visitUserNum', 'visitNum');
    }

    /**
     * @param StoreOrderRepository $repository
     * @return mixed
     * @author xaboy
     * @day 2020/6/25
     */
    public function order(StoreOrderRepository $repository)
    {
        $today = $repository->dayOrderPriceGroup('today')->toArray();
        $yesterday = $repository->dayOrderPriceGroup('yesterday')->toArray();
        $today = array_combine(array_column($today, 'time'), array_column($today, 'price'));
        $yesterday = array_combine(array_column($yesterday, 'time'), array_column($yesterday, 'price'));
        $time = getTimes();
        $order = [];
        foreach ($time as $item) {
            $order[] = [
                'time' => $item,
                'today' => $today[$item] ?? 0,
                'yesterday' => $yesterday[$item] ?? 0,
            ];
        }
        $todayPrice = $repository->dayOrderPrice('today');
        $yesterdayPrice = $repository->dayOrderPrice('yesterday');
        return app('json')->success(compact('order', 'todayPrice', 'yesterdayPrice'));
    }

    /**
     * @param StoreOrderRepository $repository
     * @return mixed
     * @author xaboy
     * @day 2020/6/25
     */
    public function orderNum(StoreOrderRepository $repository)
    {
        $orderNum = $repository->dayOrderNum('today');

        $yesterdayNum = $repository->dayOrderNum('yesterday');
        $today = $repository->dayOrderNumGroup('today')->toArray();
        $today = array_combine(array_column($today, 'time'), array_column($today, 'total'));
        $monthOrderNum = $repository->dayOrderNum(date('Y/m/d', strtotime('first day of')) . ' 00:00:00' . '-' . date('Y/m/d H:i:s'));

        $date = date('Y/m/01 00:00:00', strtotime('last Month')) . '-' . date('Y/m/d 00:00:00', strtotime('-1 day', strtotime('first day of')));
        $beforeOrderNum = $repository->dayOrderNum($date);

        $monthRate = $this->getRate($beforeOrderNum, $monthOrderNum);
        $orderRate = $this->getRate($yesterdayNum, $orderNum);
        $time = getTimes();
        $data = [];
        foreach ($time as $item) {
            $data[] = [
                'total' => $today[$item] ?? 0,
                'time' => $item
            ];
        }
        $today = $data;
        return app('json')->success(compact('orderNum', 'today', 'monthOrderNum', 'monthRate', 'orderRate'));
    }

    /**
     * @param StoreOrderRepository $repository
     * @return mixed
     * @author xaboy
     * @day 2020/6/25
     */
    public function orderUser(StoreOrderRepository $repository)
    {
        $orderNum = $repository->dayOrderUserNum('today');
        $yesterdayNum = $repository->dayOrderUserNum('yesterday');
        $today = $repository->dayOrderUserGroup('today')->toArray();
        $today = array_combine(array_column($today, 'time'), array_column($today, 'total'));
        $monthOrderNum = $repository->dayOrderUserNum(date('Y/m/d', strtotime('first day of')) . ' 00:00:00' . '-' . date('Y/m/d H:i:s'));

        $date = gmdate('Y/m/01 00:00:00', strtotime('last Month')) . '-' . date('Y/m/d 00:00:00', strtotime('-1 day', strtotime('first day of')));
        $beforeOrderNum = $repository->dayOrderUserNum($date);

        $monthRate = $this->getRate($beforeOrderNum, $monthOrderNum);
        $orderRate = $this->getRate($yesterdayNum, $orderNum);
        $time = getTimes();
        $data = [];
        foreach ($time as $item) {
            $data[] = [
                'total' => $today[$item] ?? 0,
                'time' => $item
            ];
        }
        $today = $data;
        return app('json')->success(compact('orderNum', 'today', 'monthOrderNum', 'monthRate', 'orderRate'));
    }

    /**
     * @param StoreOrderProductRepository $repository
     * @return mixed
     * @author xaboy
     * @day 2020/6/25
     */
    public function merchantStock(StoreOrderProductRepository $repository)
    {
        $date = $this->request->param('date') ?: 'lately7';
        $res = Cache::remember(self::class . '@merchantStock' . $date, function () use ($date, $repository) {
            $total = $repository->dateProductNum($date);
            $list = $repository->orderProductGroup($date)->toArray();
            foreach ($list as &$item) {
                $item['rate'] = bcdiv($item['total'], $total, 2);
            }

            return compact('list', 'total');
        }, 2000 + random_int(600, 1200));
        return app('json')->success($res);
    }

    /**
     * @param UserVisitRepository $repository
     * @return mixed
     * @author xaboy
     * @day 2020/6/25
     */
    public function merchantVisit(UserVisitRepository $repository)
    {
        $date = $this->request->param('date') ?: 'lately7';
        $res = Cache::remember(self::class . '@merchantVisit' . $date, function () use ($date, $repository) {
            $total = $repository->dateVisitMerchantTotal($date);
            $list = $repository->dateVisitMerchantNum($date)->toArray();
            foreach ($list as &$item) {
                $item['rate'] = bcdiv($item['total'], $total, 2);
                $item['care_count'] = $item['care_ficti'] + $item['care_count'];
            }
            $res = compact('list', 'total');
            return  $res;
        }, 2000 + random_int(600, 1200));
        return app('json')->success($res);
    }

    /**
     * @param StoreOrderRepository $repository
     * @param MerchantCategoryRepository $merchantCategoryRepository
     * @return mixed
     * @author xaboy
     * @day 2020/6/25
     */
    public function merchantRate(StoreOrderRepository $repository, MerchantCategoryRepository $merchantCategoryRepository)
    {
        $date = $this->request->param('date') ?: 'lately7';
        $res = Cache::remember(self::class . '@merchantRate' . $date, function () use ($repository, $merchantCategoryRepository, $date) {
            $total = $repository->dateOrderPrice($date);
            $list = $merchantCategoryRepository->dateMerchantPriceGroup($date)->toArray();
            $rate = 1;
            $pay_price = $total;
            foreach ($list as &$item) {
                $item['rate'] = bcdiv($item['pay_price'], $total, 2);
                $rate = bcsub($rate, $item['rate'], 2);
                $pay_price = bcsub($pay_price, $item['pay_price'], 2);
            }
            if ($rate > 0 && count($list)) {
                $list[] = [
                    'pay_price' => $pay_price,
                    'category_name' => '其他类',
                    'rate' => $rate
                ];
            }
            return compact('list', 'total');
        }, 2000 + random_int(600, 1200));

        return app('json')->success($res);
    }

    public function userData(UserRepository $repository, UserVisitRepository $visitRepository)
    {
        $date = $this->request->param('date') ?: 'lately7';
        $res = Cache::remember(self::class . '@userData' . $date, function () use ($visitRepository, $repository, $date) {
            $newUserList = $repository->userNumGroup($date)->toArray();
            $newUserList = array_combine(array_column($newUserList, 'time'), array_column($newUserList, 'new'));
            $visitList = $visitRepository->dateVisitNumGroup($date)->toArray();
            $visitList = array_combine(array_column($visitList, 'time'), array_column($visitList, 'total'));
            $base = $repository->beforeUserNum(getStartModelTime($date));
            $time = getDatesBetweenTwoDays(getStartModelTime($date), date('Y-m-d'));
            $userList = [];
            $before = $base;
            foreach ($time as $item) {
                $new = $newUserList[$item] ?? 0;
                $before += $new;
                $userList[] = [
                    'total' => $before,
                    'new' => $new,
                    'visit' => $visitList[$item] ?? 0,
                    'day' => $item
                ];
            }
            return $userList;
        }, 2000 + random_int(600, 1200));

        return app('json')->success($res);
    }

    /**
     * @param $last
     * @param $today
     * @param int $scale
     * @return int|string|null
     * @author xaboy
     * @day 2020/6/25
     */
    protected function getRate($last, $today, $scale = 2)
    {
        if ($last == $today)
            return 0;
        else if ($last == 0)
            return $today;
        else if ($today == 0)
            return -$last;
        else
            return (float)bcdiv(bcsub($today, $last, $scale), $last, $scale);
    }

    /**
     * 申请授权
     * @return mixed
     */
    public function auth_apply()
    {
        $data = $this->request->params([
            ['company_name', ''],
            ['domain_name', ''],
            ['order_id', ''],
            ['phone', ''],
            ['label', 10],
            ['captcha', ''],
        ]);
        if (!$data['company_name']) {
            return app('json')->fail('请填写公司名称');
        }
        if (!$data['domain_name']) {
            return app('json')->fail('请填写授权域名');
        }
        if (!$data['phone']) {
            return app('json')->fail('请填写手机号码');
        }
        if (!$data['order_id']) {
            return app('json')->fail('请填写订单id');
        }
        if (!$data['captcha']) {
            return app('json')->fail('请填写验证码');
        }
        $res = HttpService::postRequest('http://authorize.crmeb.net/api/auth_apply', $data);
        if ($res === false) {
            return app('json')->fail('申请失败,服务器没有响应!');
        }
        $res = json_decode($res, true);
        if (isset($res['status'])) {
            if ($res['status'] == 400) {
                return app('json')->fail($res['msg'] ?? "申请失败");
            } else {
                return app('json')->success($res['msg'] ?? '申请成功', $res);
            }
        }
        return app('json')->fail("申请授权失败!");
    }

    public function uploadConfig(ConfigRepository $repository)
    {
        return app('json')->success(formToData($repository->uploadForm()));
    }

    public function saveUploadConfig(ConfigRepository $repository)
    {
        $formData = $this->request->post();
        if (!count($formData)) return app('json')->fail('保存失败');
        $repository->saveUpload($formData);

        return app('json')->success('保存成功');
    }

    public function loginConfig()
    {
        $login_logo = systemConfig('sys_login_logo');
        $menu_logo = systemConfig('sys_menu_logo');
        $menu_slogo = systemConfig('sys_menu_slogo');
        $login_title = systemConfig('sys_login_title');
        $sys_login_banner = systemConfig('sys_login_banner');
        $beian_sn = systemConfig('beian_sn');
        $login_banner = [];

        foreach ($sys_login_banner ?: [] as $item) {
            $login_banner[] = [
                'pic' => $item,
                'name' => $item
            ];
        }
        return app('json')->success(compact('login_banner', 'login_logo', 'login_title', 'menu_slogo', 'menu_logo', 'beian_sn'));
    }

    public function version()
    {
        $sys_open_version = systemConfig('sys_open_version');
        $data = [
            'version' => get_code_version('未知'),
            'type' => get_code_type(),
            'year' => '© 2014-' . date('Y', time()),
            'beian_sn' => systemConfig('beian_sn'),
            'url' => 'http://www.crmeb.com',
            'Copyright' => 'Copyright',
            'sys_open_version' => $sys_open_version === '' ? '1' : $sys_open_version,
            'host' => request()->host(),
            'system' => PHP_OS,
            'php' => @phpversion()
        ];

        $copyright = app()->make(CacheRepository::class)->getResultByKey('copyright_status');
        if (!$copyright) {
            $data['status'] = -1;
        } else {
            $copyright = app()->make(CacheRepository::class)->search(['copyright_status', 'copyright_context', 'copyright_image']);
            $data['status'] = 1;
            $data['Copyright'] = $copyright['copyright_context'] ?? '';
            $data['image'] = $copyright['copyright_image'] ?? '';
            $data['url'] = systemConfig('site_url');
        }
        return app('json')->success($data);
    }

    public function config()
    {
        $config = systemConfig(['delivery_type', 'delivery_status', 'sms_use_type', 'hot_ranking_lv', 'hot_ranking_switch']);
        return app('json')->success($config);
    }

    public function getChangeColor()
    {
        $type = systemConfig('global_theme');
        $theme = app()->make(DiyRepository::class)->getThemeVar($type);
        $theme['global_theme'] = $type;
        return app('json')->success($theme);
    }

    public function setChangeColor()
    {
        $data = $this->request->params(['global_theme']);
        $make = app()->make(ConfigValueRepository::class);
        $make->setFormData($data, 0);
        return app('json')->success('修改成功');
    }


    public function svaeCopyright()
    {
        $data = $this->request->params(['copyright_context', 'copyright_image']);
        $copyright = app()->make(CacheRepository::class)->getResultByKey('copyright_status');
        if (!$copyright)
            return app('json')->fail('请先获取版权授权');

        app()->make(CacheRepository::class)->saveAll($data);
        return app('json')->success('修改成功');
    }

    public function payAuth()
    {
        $host = 'https://shop.crmeb.net/html/index.html';
        $version = get_crmeb_version_code();
        $url = rtrim($this->request->host(), '/');
        $data['url'] = $host . '?url=' . $url . '&product=mer&label=10&venrsion=' . $version;
        return app('json')->success($data);
    }

    /**
     * 检测队列是否正常
     * @return \think\response\Json
     * @author Qinii
     * @day 2023/10/24
     */
    public function queue()
    {
        //$key = env('APP_KEY', 'merchant') . '_queue_status';
        //$queue = Cache::get($key);
        //if (!$queue) {
        //    return app('json')->status(201, '您的【队列】运行异常，会导致商品上下架无效、自动取消订单、订单自动好评、拼团到期退款等任务无法正常使用，请尽快启动队列', ['msg' => '>>> 点击查看处理方法 <<<', 'url' => 'https://www.crmeb.com/ask/thread/33264']);
        //}
        return app('json')->status(200, 'ok');
    }

    public function getAdminCount()
    {
        return app('json')->success(app()->make(CountRepository::class)->getAdminCount());
    }

    public function getAdminTodo()
    {
        return app('json')->success(app()->make(CountRepository::class)->getAdminTodo());
    }

    public function getMerchantTop()
    {
        $date = $this->request->param('date', 'lately7');
        $type = $this->request->param('type', 'sales');
        $sort = $this->request->param('sort', 'desc');
        return app('json')->success(app()->make(CountRepository::class)->getMerchantTop($date, $type, $sort));
    }

    /**
     *  数据大屏接口
     * @param $key
     * @return \think\response\Json
     * @author Qinii
     * @day 2023/12/4
     */
    public function data_screen($key)
    {
        $param['pid'] = $this->request->param('pid', 0);
        $data = app()->make(DataScreenRepository::class)->dataScreen($key, $param);
        return app('json')->success($data);
    }


    /**
     * 搜索获取菜单列表
     * @return void
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getMenusList()
    {
        $param = $this->request->param([
            'keyword','is_mer'
        ]);
        if(!isset($param['is_mer'])){
            throw new ValidateException('参数错误，请稍后重试');
        }
        $menuRepository = app()->make(MenuRepository::class);// 获取系统菜单服务实例
        $menusList = $menuRepository->getSearch([])->where([
            ['is_menu','=',1],
            ['is_mer','=',$param['is_mer']],
            ['pid', '>', 0],
        ])->field('menu_name,menu_id,route')->limit(0,10)->select()->toArray();// 查询菜单列表
        if(isset($param['keyword']) && $param['keyword']){
            $menusList = $menuRepository->getSearch([])->where(['is_menu'=>1,'is_mer'=>$param['is_mer']])->whereLike('menu_name','%' . $param['keyword'] . '%')->field('menu_name,menu_id,route')->select()->toArray();// 查询菜单列表

            $configRepository = app()->make(ConfigRepository::class);// 获取系统配置服务实例
            $configClassifyRepository = app()->make(ConfigClassifyRepository::class);// 获取系统配置分类服务实例

            $configList = $configRepository->getSearch([])->where(['status'=>1])->whereLike('config_name', '%' . $param['keyword'] . '%')->field('config_id,config_classify_id,config_name')->select()->toArray();
            $configClassifyList = $configClassifyRepository->getSearch([])->where(['status'=>1])->whereLike('classify_name', '%' . $param['keyword'] . '%')->field('config_classify_id,classify_name')->select()->toArray();
            $configAllList = array_merge($configList,$configClassifyList);//分类配置合并分类项
            $configClassifyIds = array_unique(array_column($configAllList, 'config_classify_id'));
            $configClassifyMenuArray = $configClassifyRepository->getSearch([])->whereIn('config_classify_id',$configClassifyIds)->field('menu_id,classify_name')->select()->toArray();//通过配置分类以及配置项获取菜单ID

            //拼接配置分类名称
            if(!empty($configClassifyMenuArray)){
                foreach ($configClassifyMenuArray as $item){
                    $menuInfo = $menuRepository->getSearch([])->where(['is_mer'=>$param['is_mer']])->whereIn('menu_id',$item['menu_id'])->field('menu_name,menu_id,route')->find();// 查询菜单详情
                    if(!empty($menuInfo)){
                        $menuInfo['menu_name'] .= '-'.$item['classify_name'];
                        $menusList[] = $menuInfo;
                    }
                }
            }
        }

        //路由拼接模块名
        if(!empty($menusList)){
            $moduleName = $param['is_mer'] ? config('admin.merchant_prefix') : config('admin.admin_prefix');
            foreach ($menusList as &$item){
                if(isset($item['route']) && $item['route']){
                    $item['route'] = '/' . $moduleName. $item['route'];
                }
            }
        }

        return app('json')->success($menusList);
    }

    public function getSystemInfo()
    {
        $info['server'] = [
            ['name' => '服务器系统', 'require' => '类UNIX', 'value' => PHP_OS],
            ['name' => 'WEB环境', 'require' => 'Swoole', 'value' => phpversion('swoole')],
        ];
        $gd_info = function_exists('gd_info') ? gd_info() : array();
        $info['environment'] = [
            ['name' => 'PHP版本', 'require' => '7.1-7.4', 'value' => phpversion()],
            ['name' => 'MySql版本', 'require' => '5.6-8.0', 'value' => Db::query("SELECT VERSION()")[0]['VERSION()']],
            ['name' => 'MySqli', 'require' => '开启', 'value' => function_exists('mysqli_connect')],
            ['name' => 'Openssl', 'require' => '开启', 'value' => function_exists('openssl_encrypt')],
            ['name' => 'Session', 'require' => '开启', 'value' => function_exists('session_start')],
            ['name' => 'Safe_Mode', 'require' => '开启', 'value' => !ini_get('safe_mode')],
            ['name' => 'GD', 'require' => '开启', 'value' => !empty($gd_info['GD Version'])],
            ['name' => 'Curl', 'require' => '开启', 'value' => function_exists('curl_init')],
            ['name' => 'Bcmath', 'require' => '开启', 'value' => function_exists('bcadd')],
            ['name' => 'Upload', 'require' => '开启', 'value' => (bool)ini_get('file_uploads')],
        ];

        $info['permissions'] = [
            ['name' => 'backup', 'require' => '读写', 'value' => is_readable(root_path('backup')) && is_writable(root_path('backup'))],
            ['name' => 'public', 'require' => '读写', 'value' => is_readable(root_path('public')) && is_writable(root_path('public'))],
            ['name' => 'runtime', 'require' => '读写', 'value' => is_readable(root_path('runtime')) && is_writable(root_path('runtime'))],
            ['name' => '.env', 'require' => '读写', 'value' => is_readable(root_path() . '.env') && is_writable(root_path() . '.env')],
            ['name' => '.version', 'require' => '读写', 'value' => is_readable(root_path() . '.version') && is_writable(root_path() . '.version')],
            ['name' => '.constant', 'require' => '读写', 'value' => is_readable(root_path() . '.constant') && is_writable(root_path() . '.constant')],
        ];
        if (function_exists('exec')) {
            $workermanOutput = $timerOutput = $queueOutput = [];
            //exec("ps aux | grep 'php think timer' | grep -v grep", $timerOutput);
            exec("ps aux | grep 'php think queue' | grep -v grep", $queueOutput);
            $info['process'] = [//['name' => '定时任务', 'require' => '开启', 'value' => count($timerOutput) > 0],
                ['name' => '消息队列', 'require' => '开启', 'value' => count($queueOutput) > 0],];
        }
        return app('json')->success($info);
    }


}

