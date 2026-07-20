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


namespace app\common\repositories\store\service;


use app\common\dao\store\service\StoreServiceDao;
use app\common\model\store\service\StoreService;
use app\common\repositories\BaseRepository;
use crmeb\exceptions\AuthException;
use crmeb\services\JwtTokenService;
use FormBuilder\Exception\FormBuilderException;
use FormBuilder\Factory\Elm;
use FormBuilder\Factory\Iview;
use FormBuilder\Form;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Route;

/**
 * 客服
 */
class StoreServiceRepository extends BaseRepository
{
    /**
     * StoreServiceRepository constructor.
     * @param StoreServiceDao $dao
     */
    public function __construct(StoreServiceDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 根据条件获取列表数据
     *
     * 本函数用于根据给定的条件数组 $where，从数据库中检索满足条件的数据列表。
     * 它支持分页查询，每页的数据数量由 $limit 指定，查询的页码由 $page 指定。
     * 查询结果包括满足条件的数据总数 $count 和实际查询到的数据列表 $list。
     *
     * @param array $where 查询条件，以数组形式传递，键值对表示字段名和字段值。
     * @param int $page 查询的页码，用于实现分页查询。
     * @param int $limit 每页显示的数据数量。
     * @return array 返回包含 'count' 和 'list' 两个元素的数组，'count' 表示满足条件的数据总数，'list' 表示查询到的数据列表。
     */
    public function getList(array $where, $page, $limit)
    {
        // 构建查询语句，根据 $where 条件进行搜索，并包含 'user' 关联数据，但只选取特定字段。
        $query = $this->dao->search($where)
            ->with(['user' => function ($query) {
                // 在 'user' 关联数据中，只选取 'nickname', 'avatar', 'uid', 'cancel_time' 四个字段。
                $query->field('nickname,avatar,uid,cancel_time');
            }])
            ->order('sort DESC,create_time DESC'); // 按 'sort' 和 'create_time' 降序排序。

        // 计算满足条件的数据总数。
        $count = $query->count();

        // 进行分页查询，获取当前页的数据列表。
        $list = $query->page($page, $limit)->select();

        // 将数据总数和数据列表一起返回。
        return compact('count', 'list');
    }

    /**
     * 创建客服表单
     * 该方法用于生成添加或编辑客服的表单界面。根据$merId参数的值，决定是创建新的客服还是编辑已有的客服。
     * 如果是编辑客服，表单中会包含开关控件来配置客服的权限和状态；如果是新建客服，密码和确认密码字段将是必填的。
     *
     * @param string $merId 商户ID，如果存在，则表示编辑商户客服；如果不存在，则表示添加管理员客服。
     * @param bool $isUpdate 表示是否为更新操作，默认为false，即添加操作。
     * @return string 返回生成的表单HTML代码。
     */
    public function form($merId, $isUpdate = false)
    {
        // 创建密码字段用于输入客服密码
        $pwd = Elm::password('pwd', '客服密码：')->placeholder('请输入客服密码');
        // 创建确认密码字段
        $confirm_pwd = Elm::password('confirm_pwd', '确认密码：')->placeholder('请输入确认密码');
        // 如果不是更新操作，密码字段必须填写
        if (!$isUpdate) {
            $pwd->required('请输入客服密码');
            $confirm_pwd->required('请输入确认密码');
        }

        // 初始化管理员权限规则数组
        $adminRule = $filed = [];
        // 如果提供了商户ID，生成管理员权限配置的开关控件
        $adminRule = [
            // 客服状态开关，开启表示客服在线
            Elm::switches('status', '客服状态：', 1)->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->className('switch-width-double')->activeText('开启')->col(12),
        ];
        if($merId){
            $adminRule = array_merge($adminRule, [
                Elm::switches('customer', '订单管理：', 1)->activeValue(1)->inactiveValue(0)->inactiveText('关闭')
                    ->className('switch-width-double')->activeText('开启')->col(12),
                // 商品管理开关
                Elm::switches('is_goods', '商品管理：', 1)->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->className('switch-width-double')->activeText('开启')->col(12),
                // 开启核销开关
                Elm::switches('is_verify', '开启核销：', 1)->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->className('switch-width-double')->activeText('开启'),
            ]);
        }

        // 订单管理开关
        $adminRule[] = Elm::switches('notify', '订单通知：', 1)->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->className('switch-width-double')->activeText('开启')->control([
            [
                'value' => 1,
                'rule' => [
                    Elm::input('phone', '通知电话：')->placeholder('请输入通知电话')
                ]
            ]
        ]);
        // 定义管理员权限字段的规则
        $filed = [
            "value" => 1,
            "rule"  => [
                "customer","is_goods","is_verify","notify","status"
            ],

        ];
        // 添加排序字段，允许输入0到99999之间的整数
        $adminRule[] = Elm::number('sort', '排序：', 0)->precision(0)->max(99999);
        // 根据是否有商户ID，确定配置的前缀，用于生成用户列表和上传图片的URL
        $prefix = $merId ? config('admin.merchant_prefix') : config('admin.admin_prefix');
        // 生成并返回表单HTML代码
        return Elm::createForm(Route::buildUrl('merchantServiceCreate')->build(), array_merge([
            // 用户选择框，从用户列表中选择用户
            Elm::frameImage('uid', '用户：', '/' . $prefix . '/setting/userList?field=uid&type=1')->prop('srcKey', 'src')->width('1000px')->height('600px')->appendValidate(Iview::validateObject()->message('请选择用户')->required('请选择用户'))->icon('el-icon-camera')->modal(['modal' => false]),
            // 头像上传控件
            Elm::frameImage('avatar', '客服头像：', '/' . $prefix . '/setting/uploadPicture?field=avatar&type=1')->width('1000px')->height('600px')->props(['footer' => false])->icon('el-icon-camera')->modal(['modal' => false]),
            // 昵称输入框
            Elm::input('nickname', '客服昵称：')->placeholder('请输入客服昵称')->required('请输入客服昵称'),
            // 账号输入框
            Elm::input('account', '客服账号：')->placeholder('请输入客服账号')->required('请输入客服账号'),
            // 密码输入框
            $pwd,
            // 确认密码输入框
            $confirm_pwd,
            // 账号状态开关，开启表示账号可用
            Elm::switches('is_open', '账号状态：', 1)->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->className('switch-width-double')->activeText('开启')->col(12)->control([$filed]),

        ], $adminRule))->setTitle('添加客服');
    }

    /**
     * 更新表单信息
     * 该方法用于根据给定的ID获取表单数据，并准备相应的数据以用于表单编辑界面。
     * @param int $id 表单ID，用于查询特定的表单数据。
     * @return mixed 返回一个用于编辑表单的视图对象，该对象已配置好相关的表单数据和操作URL。
     */
    public function updateForm($id)
    {
        // 根据$id获取表单数据，同时只查询用户的avatar和uid字段
        $service = $this->dao->getWith($id, ['user' => function ($query) {
            $query->field('avatar,uid');
        }])->toArray();

        // 如果获取到用户信息
        if($service['user'] ?? null){
            // 构建一个新的uid字段，包含用户的id和头像信息
            $service['uid'] = ['id' => $service['uid'], 'src' => $service['user']['avatar'] ?: $service['avatar']];
        }else{
            // 如果没有用户信息，移除uid字段
            unset($service['uid']);
        }

        // 移除不用于表单编辑的字段
        unset($service['user'], $service['pwd']);

        // 返回一个配置好表单数据、标题和操作URL的表单视图对象
        return $this->form($service['mer_id'], true)
                    ->formData($service)
                    ->setTitle('编辑客服')
                    ->setAction(Route::buildUrl('merchantServiceUpdate', compact('id'))->build());
    }

    /**
     * 根据商家ID和用户ID获取聊天服务对象
     *
     * 本函数旨在根据提供的商家ID和用户ID，获取与之相关的聊天服务信息。
     * 如果用户ID提供且有效，将尝试根据最后的服务记录来获取服务对象。
     * 如果没有有效的最后服务记录，或者用户ID未提供，则随机获取一个可用的服务对象。
     *
     * @param string $merId 商家ID，用于确定聊天服务的范围
     * @param int $uid 用户ID，可选，用于获取用户特定的服务记录
     * @return object|null 返回聊天服务对象，如果无法获取则返回null
     */
    public function getChatService($merId, $uid = 0)
    {
        // 默认服务对象为空
        $service = null;

        // 如果用户ID提供，尝试获取最后的服务记录
        if ($uid) {
            // 实例化存储服务日志的仓库
            $logRepository = app()->make(StoreServiceLogRepository::class);
            // 获取指定商家和用户最后的服务ID
            $lastServiceId = $logRepository->getLastServiceId($merId, $uid);
        }

        // 如果存在有效的最后服务ID，尝试获取对应的服务对象
        if (isset($lastServiceId) && $lastServiceId) {
            $service = $this->getValidServiceInfo($lastServiceId);
        }

        // 如果已经获取到服务对象，则直接返回
        if ($service) return $service;

        // 如果没有获取到服务对象，尝试随机获取一个服务对象
        $service = $this->dao->getRandService($merId);

        // 如果随机获取成功，则返回服务对象
        if ($service) return $service;
    }

    /**
     * 根据用户ID获取服务列表
     * 此函数用于查询与特定用户相关联的服务列表。它支持过滤条件和系统服务的排序方向。
     *
     * @param int $uid 用户ID，用于查询与该用户相关联的服务。
     * @param array $where 查询过滤条件，允许通过数组传递额外的过滤条件。
     * @param int $is_sys 系统服务标志，用于确定服务列表是按升序还是降序排列系统服务。
     *                   1 表示升序，非1表示降序。
     * @return array 返回一个经过处理的服务列表数组，每个服务包括商户信息。
     */
    public function getServices($uid, array $where = [],$is_sys = 1)
    {
        // 添加用户ID到查询条件
        $where['uid'] = $uid;

        // 执行查询，带条件和排序，并隐藏密码字段
        $res = $this->search($where)
            ->with(['merchant' => function ($query) {
                // 仅加载商户的特定字段
                $query->where(['status' => 1, 'mer_state' => 1])->field('mer_id,mer_avatar,mer_name,status');
            }])
            ->where('mer_id', $is_sys ? '=' : '>',0)
            ->order('mer_id '. ($is_sys ? 'ASC' : 'DESC'))
            ->select()
            ->hidden(['pwd'])
            ->toArray();

        // 获取系统配置，用于填充默认商户信息
        $config = systemConfig(['site_logo', 'site_name']);
        $list = [];
        if ($is_sys) {
            $item = $res[0];
            // 系统服务使用系统配置的Logo和名称
            $item['merchant'] = [
                'mer_avatar' => $config['site_logo'],
                'mer_name' => $config['site_name'],
                'mer_id' => 0,
            ];
            $item['mer_id'] = 0;
            $list[] = $item;
            return $list;
        }
        // 遍历服务列表，为系统服务填充默认商户信息
        foreach ($res as $key => $item){
            if ($item['merchant'])
                $list[] = $item;
        }
        unset($item); // 断开引用，避免潜在的引用问题
        // 返回处理后的服务列表
        return $list;
    }


    /**
     * 创建服务令牌
     *
     * 本函数用于生成针对管理员服务的JWT令牌。令牌用于在一段时间内验证请求的合法性。
     * 它通过JwtTokenService创建令牌，并将令牌及其过期时间存储到缓存中。
     *
     * @param StoreService $admin 管理员服务对象，用于获取服务ID，该ID是生成令牌的标识之一。
     * @return array 返回包含令牌和过期时间的数组。
     */
    public function createToken(StoreService $admin)
    {
        // 实例化JWT令牌服务类
        $service = new JwtTokenService();

        // 从配置中获取令牌的过期时间，默认为3小时，转换为整型
        $exp = intval(Config::get('admin.token_exp', 3));

        // 使用服务ID、令牌类型和服务过期时间创建令牌
        $token = $service->createToken($admin->service_id, 'service', strtotime("+ {$exp}hour"));

        // 将生成的令牌及其过期时间存储到缓存中
        $this->cacheToken($token['token'], $token['out']);

        // 返回生成的令牌信息
        return $token;
    }

    /**
     * 缓存令牌
     *
     * 本函数用于缓存给定的令牌及其过期时间。缓存的目的是为了提高访问效率，
     * 避免频繁的数据库查询或计算。令牌通常用于认证或访问控制等场景。
     *
     * @param string $token 令牌字符串。这是一个唯一标识，用于在缓存中查找或标识缓存项。
     * @param int $exp 令牌的过期时间，以秒为单位。这个时间从当前时间开始计算。
     */
    public function cacheToken(string $token, int $exp)
    {
        // 构建缓存键名，并设置缓存值为当前时间加上过期时间，过期时间参数再次强调了缓存的持续时间。
        Cache::set('service_' . $token, time() + $exp, $exp);
    }

    /**
     * 检查令牌的有效性
     *
     * 本函数用于验证传入的令牌是否有效，有效意味着该令牌曾在指定时间内被使用过。
     * 它首先检查令牌是否存在于缓存中，如果不存在，则抛出一个授权异常，指出令牌无效。
     * 接着，它获取令牌的最后使用时间，并计算令牌自上次使用以来是否已过期。
     * 如果令牌过期，同样抛出一个授权异常，指出令牌已过期。
     * 这样做的目的是为了确保每个请求的合法性，防止未授权的访问。
     *
     * @param string $token 待验证的令牌
     * @throws AuthException 如果令牌无效或已过期
     */
    public function checkToken(string $token)
    {
        // 检查缓存中是否存在该令牌
        $has = Cache::has('service_' . $token);
        // 如果令牌不存在于缓存中，则抛出无效令牌异常
        if (!$has)
            throw new AuthException('无效的token');

        // 获取令牌的最后使用时间
        $lastTime = Cache::get('service_' . $token);
        // 检查令牌是否过期，如果过期则抛出异常
        if (($lastTime + (intval(Config::get('admin.token_valid_exp', 15))) * 60) < time())
            throw new AuthException('token 已过期');
    }

    /**
     * 更新令牌的缓存时间
     *
     * 本函数用于更新特定令牌的缓存时间，确保令牌在一段时间内保持有效。
     * 它通过计算配置文件中指定的令牌有效时长（默认为15分钟），并更新缓存来实现。
     * 如果令牌在缓存中超时，用户需要重新获取令牌以进行操作。
     *
     * @param string $token 需要更新缓存时间的令牌字符串
     */
    public function updateToken(string $token)
    {
        // 根据配置文件中设定的令牌有效时长（默认15分钟），更新令牌的缓存时间
        Cache::set('service_' . $token, time(), intval(Config::get('admin.token_valid_exp', 15)) * 60);
    }

    /**
     * 清除指定令牌的缓存。
     *
     * 本函数用于从缓存系统中删除特定标识符的令牌，以实现令牌的有效 令牌通常用于认证或访问控制等场景，因此，及时清除无效的期管理或当令牌不再需要时令牌对于维护系统安全至关重要。
     *确保其被安全删除。
     *
     * @param string $token 需要清除的令牌字符串。
     */
    public function clearToken(string $token)
    {
        // 根据令牌生成缓存键名，并删除该缓存项。
        Cache::delete('service_' . $token);
    }



    /**
     * 检测验证码
     * @param string $key key
     * @param string $code 验证码
     * @author 张先生
     * @date 2020-03-26
     */
    public function checkCode(string $key, string $code)
    {
        $_code = Cache::get('ser_captcha' . $key);
        if (!$_code) {
            throw new ValidateException('验证码过期');
        }

        if (strtolower($_code) != strtolower($code)) {
            throw new ValidateException('验证码错误');
        }

        //删除code
        Cache::delete('ser_captcha' . $key);
    }

    /**
     * 创建登录验证码键
     *
     * 本函数用于生成一个唯一的登录验证码键，并将该验证码与键关联起来存储在缓存中。
     * 验证码键的生成结合了微秒时间和随机数，以确保唯一性。
     * 验证码在缓存中的有效期通过配置文件定义，以分钟为单位。
     *
     * @param string $code 登录验证码
     * @return string 生成的验证码键
     */
    public function createLoginKey(string $code)
    {
        // 生成一个唯一的验证码键，基于当前微秒时间戳和随机数
        $key = uniqid(microtime(true), true);

        // 将验证码与键关联，存储到缓存中，并设定过期时间
        // 缓存有效期通过配置文件获取，默认为5分钟
        Cache::set('ser_captcha' . $key, $code, Config::get('admin.captcha_exp', 5) * 60);

        // 返回生成的验证码键
        return $key;
    }
}
