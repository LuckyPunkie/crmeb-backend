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


namespace app\common\repositories\system\merchant;


use app\common\dao\BaseDao;
use app\common\dao\system\merchant\MerchantAdminDao;
use app\common\model\system\merchant\Merchant;
use app\common\model\system\merchant\MerchantAdmin;
use app\common\repositories\BaseRepository;
use app\common\repositories\system\auth\RoleRepository;
use crmeb\exceptions\AuthException;
use crmeb\services\security\CoreAuthTokenService;
use FormBuilder\Exception\FormBuilderException;
use FormBuilder\Factory\Elm;
use FormBuilder\Form;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Route;
use think\Model;

/**
 * 商户管理员
 */
class MerchantAdminRepository extends BaseRepository
{

    const PASSWORD_TYPE_ADMIN = 1;
    const PASSWORD_TYPE_MERCHANT = 2;
    const PASSWORD_TYPE_SELF = 3;

    /**
     * MerchantAdminRepository constructor.
     * @param MerchantAdminDao $dao
     */
    public function __construct(MerchantAdminDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 根据条件获取角色列表
     *
     * 本函数用于查询特定商家下的角色列表，支持分页和条件查询。它首先计算总记录数，然后获取指定页码和每页数量的角色数据。
     * 对查询结果进行处理，合并商家账号信息，并解析角色名称。最后，返回包含角色列表和总记录数的数组。
     *
     * @param int $merId 商家ID，用于限定查询的商家范围
     * @param array $where 查询条件数组，用于筛选角色
     * @param int $page 当前页码，用于分页查询
     * @param int $limit 每页记录数，用于分页查询
     * @return array 返回包含角色列表和总记录数的数组
     */
    public function getList($merId, array $where, $page, $limit)
    {
        // 根据商家ID和查询条件进行初步查询
        $query = $this->dao->search($merId, $where, 1);
        // 计算总记录数
        $count = $query->count();
        // 进行分页查询，并隐藏某些字段
        $list = $query->page($page, $limit)->hidden(['pwd', 'is_del'])->select();
        // 查询商家主账号
        $topAccount = $this->dao->merIdByAccount($merId);
        // 遍历角色列表，合并账号信息，解析角色名称
        foreach ($list as $k => $role) {
            if ($topAccount) {
                // 合并商家主账号和角色账号
                $list[$k]['account'] = $topAccount . '@' . $role['account'];
            }
            // 解析角色名称
            $list[$k]['rule_name'] = $role->roleNames();
        }
        // 返回角色列表和总记录数
        return compact('list', 'count');
    }

    /**
     * 创建或编辑管理员表单
     *
     * 该方法用于生成用于添加或编辑管理员的表单。根据$id$的存在与否决定是创建新管理员还是编辑已有的管理员。
     * 表单包括选择角色、输入管理员姓名、账号、电话等字段。如果$id$为空，则还包括输入密码和确认密码的字段。
     *
     * @param int $merId 商户ID，用于获取角色选项
     * @param int|null $id 管理员ID，如果为null则表示创建新管理员，否则为编辑现有管理员
     * @param array $formData 表单默认数据，用于预填充表单
     * @return Form 返回生成的表单对象
     */
    public function form(int $merId, ?int $id = null, array $formData = []): Form
    {
        $form = Elm::createForm(is_null($id) ? Route::buildUrl('merchantAdminCreate')->build() : Route::buildUrl('merchantAdminUpdate', ['id' => $id])->build());

        $rules = [
            Elm::select('roles', '身份：', [])->options(function () use ($merId) {
                $data = app()->make(RoleRepository::class)->getAllOptions($merId);
                $options = [];

                foreach ($data as $value => $label) {
                    $options[] = compact('value', 'label');
                }
                return $options;
            })->placeholder('请选择身份')->multiple(true),
            Elm::input('real_name', '管理员姓名：')->placeholder('请输入管理员姓名'),
            Elm::input('account', '账号：')->placeholder('请输入账号')->required('请输入账号'),
            Elm::input('phone', ' 联系电话：')->placeholder('请输入联系电话'),
        ];
        if (!$id) {
            $rules[] = Elm::password('pwd', '密码：')->placeholder('请输入密码')->required('请输入密码');
            $rules[] = Elm::password('againPassword', '确认密码：')->placeholder('请输入确认密码')->required('请输入确认密码');
        }
        $rules[] = Elm::switches('status', '账号状态：', 1)->inactiveValue(0)->width(60)->activeValue(1)->inactiveText('停用')->activeText('正常')->className('switch-width-double');
        $form->setRule($rules);
        return $form->setTitle(is_null($id) ? '添加管理员' : '编辑管理员')->formData($formData);
    }

    /**
     * 更新表单数据的方法
     *
     * 本方法用于根据给定的商户ID和表单ID来更新表单数据。它首先通过ID获取现有的表单数据，
     * 然后使用这些数据来构建一个新的表单实例。此方法体现了对表单数据的更新操作，
     * 是业务逻辑中对数据修改的一个典型应用场景。
     *
     * @param int $merId 商户ID，用于指定表单所属的商户。
     * @param int $id 表单ID，用于唯一标识待更新的表单。
     * @return array|Form
     */
    public function updateForm(int $merId, int $id)
    {
        // 通过ID获取当前表单的数据，并转换为数组格式
        // 这里使用了链式调用，首先通过$this->dao->get($id)获取表单对象，然后调用toArray()方法将其转换为数组
        return $this->form($merId, $id, $this->dao->get($id)->toArray());
    }

    /**
     * 创建商家账户
     *
     * 本函数用于为指定的商家创建一个新的账户。它首先对密码进行加密处理，然后组装账户数据，
     * 最后调用创建账户的方法来实际创建账户。
     *
     * @param Merchant $merchant 商家对象，包含商家的相关信息。
     * @param string $account 账户名称。
     * @param string $pwd 商家账户的原始密码。
     * @return mixed 创建账户操作的结果，具体类型取决于create方法的返回。
     */
    public function createMerchantAccount(Merchant $merchant, $account, $pwd)
    {
        // 对密码进行加密处理
        $pwd = $this->passwordEncode($pwd);

        // 组装账户数据，包括加密后的密码、账户名、商家ID、商家名称、商家电话和初始等级
        $data = compact('pwd', 'account') + [
                'mer_id' => $merchant->mer_id,
                'real_name' => $merchant->real_name,
                'phone' => $merchant->mer_phone,
                'level' => 0
            ];

        // 调用创建账户的方法，传入组装好的数据
        return $this->create($data);
    }

    /**
     * 对密码进行加密处理
     *
     * 本函数使用BCrypt算法对密码进行加密。BCrypt是一种基于口令的加密算法，设计用于存储和验证用户密码。
     * 选择BCrypt算法是因为其在安全性和抗暴力破解方面的优势。
     *
     * @param string $password 需要加密的原始密码
     * @return string 返回加密后的密码hash值
     */
    public function passwordEncode($password)
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    /**
     * 管理员登录方法
     *
     * 该方法用于处理管理员的登录逻辑。它首先触发一个登录前的事件，然后根据账户格式验证并查询管理员信息。
     * 如果账户格式不符合标准（不包含 '@' 符号），则认为是顶级管理员账户。否则，认为是商户管理员账户，并需要验证商户是否存在。
     * 如果管理员信息不存在或密码不匹配，将增加登录失败次数并抛出异常。此外，如果管理员账户被禁用，也会抛出异常。
     * 成功登录后，更新管理员的登录信息并触发一个登录成功的事件。
     *
     * @param string $account 管理员账户名
     * @param string $password 管理员密码
     * @return AdminModel|array|\think\db\false|Model
     * @throws ValidateException 如果登录失败或账户状态异常，抛出此异常
     */
    public function login(string $account, string $password)
    {
        // 触发登录前的事件，可以用于日志记录、验证码验证等
        event('admin.merLogin.before',compact('account', 'password'));

        // 分割账户名以获取账号和域名部分
        $accountInfo = explode('@', $account, 2);

        // 如果账户名不包含 '@'，则认为是顶级管理员
        if (count($accountInfo) === 1) {
            $adminInfo = $this->dao->accountByTopAdmin($accountInfo[0]);
        } else {
            // 查询商户ID对应的管理员信息
            $merId = $this->dao->accountByMerchantId($accountInfo[0]);
            // 如果商户ID不存在，增加登录失败次数并抛出异常
            if (!$merId){
                $key = 'mer_login_failuree_'.$account;
                $numb = Cache::get($key) ?? 0;
                $numb++;
                Cache::set($key,$numb,15*60);
                throw new ValidateException('账号或密码错误');
            }
            // 根据管理员名称和商户ID查询管理员信息
            $adminInfo = $this->dao->accountByAdmin($accountInfo[1], $merId);
        }

        // 如果管理员信息不存在或密码不匹配，增加登录失败次数并抛出异常
        if (!$adminInfo || !password_verify($password, $adminInfo->pwd)){
            $key = 'mer_login_failuree_'.$account;
            $numb = Cache::get($key) ?? 0;
            $numb++;
            Cache::set($key,$numb,15*60);
            throw new ValidateException('账号或密码错误');
        }

        // 如果管理员账户被禁用，抛出异常
        if ($adminInfo['status'] != 1)
            throw new ValidateException('账号已关闭');

        // 获取商户信息
        /**
         * @var MerchantRepository $merchantRepository
         */
        $merchantRepository = app()->make(MerchantRepository::class);
        $merchant = $merchantRepository->get($adminInfo->mer_id);
        // 如果商户不存在或被禁用，抛出异常
        if (!$merchant)
            throw new ValidateException('商户不存在');
        if (!$merchant['status'])
            throw new ValidateException('商户已被锁定');

        // 更新管理员的登录信息
        $adminInfo->last_time = date('Y-m-d H:i:s');
        $adminInfo->last_ip = app('request')->ip();
        $adminInfo->login_count++;
        $adminInfo->save();

        // 触发登录成功的事件，可以用于日志记录、在线状态更新等
        event('admin.merLogin',compact('adminInfo'));

        // 返回登录成功的管理员信息
        return $adminInfo;
    }

    /**
     * 停用旧版商户 Token 缓存入口。
     *
     * v2 Token 会话缓存已迁移到 CoreAuthTokenService，本方法保留用于阻断旧源码调用。
     *
     * @param string $token 旧版商户 Token 字符串。
     * @param int $exp 旧版 Token 过期秒数。
     * @return void
     * @throws AuthException 始终抛出，提示旧版商户 token 缓存已停用。
     */
    public function cacheToken(string $token, int $exp)
    {
        throw new AuthException('旧版商户 token 缓存已停用');
    }

    /**
     * 校验商户后台 v2 Token。
     *
     * 实际校验逻辑由 CoreAuthTokenService 负责，旧 mer Token 会因缺少 v2 签名失败。
     *
     * @param string $token 商户后台 Token 字符串。
     * @return void
     * @throws AuthException Token 无效、过期、guard 不匹配或缺少 v2 签名时抛出。
     */
    public function checkToken(string $token)
    {
        app()->make(CoreAuthTokenService::class)->verifyMerchantToken($token);
    }

    /**
     * 刷新商户后台 v2 Token 会话有效期。
     *
     * @param string $token 商户后台 Token 字符串。
     * @return void
     * @throws AuthException Token 无效、过期、guard 不匹配或缺少 v2 签名时抛出。
     */
    public function updateToken(string $token)
    {
        app()->make(CoreAuthTokenService::class)->refreshMerchantToken($token);
    }

    /**
     * 清除 v2 Token 会话缓存。
     *
     * @param string $token 需要清理的 Token 字符串。
     * @return void
     */
    public function clearToken(string $token)
    {
        app()->make(CoreAuthTokenService::class)->clearToken($token);
    }

    /**
     * 创建商户后台 v2 Token。
     *
     * @param MerchantAdmin $admin 商户管理员模型。
     * @return array 返回包含 token、过期时间等信息的数组。
     */
    public function createToken(MerchantAdmin $admin)
    {
        return app()->make(CoreAuthTokenService::class)->createMerchantToken($admin);
    }

    /**
     * 验证验证码是否正确。
     * 该方法主要用于在非开发环境下验证用户输入的验证码是否与缓存中的验证码匹配。如果验证码不匹配或已过期，
     * 将抛出ValidateException异常。在开发环境下，验证码的验证逻辑被跳过，以方便开发。
     *
     * @param string $key 验证码的唯一标识键，用于拼接缓存键名。
     * @param string $code 用户输入的验证码内容。
     * @throws ValidateException 如果验证码过期或不正确，则抛出此异常。
     */
    public function checkCode(string $key, string $code)
    {
        // 如果不在开发模式下，则进行验证码的验证
        if (!env('DEVELOPMENT',false)){
            // 从缓存中获取存储的验证码，键名为'am_captcha'加上$key。
            $_code = Cache::get('am_captcha' . $key);
            // 如果缓存中没有找到验证码，表示验证码已过期，抛出异常。
            if (!$_code) {
                throw new ValidateException('验证码过期');
            }
            // 将存储的验证码和用户输入的验证码转换为小写后比较，不匹配则抛出异常。
            if (strtolower($_code) != strtolower($code)) {
                throw new ValidateException('验证码错误');
            }
            // 验证码验证通过后，删除缓存中的验证码。
            //删除code
            Cache::delete('am_captcha' . $key);
        }
    }

    /**
     * 创建登录验证码键
     *
     * 本函数用于生成一个唯一的登录验证码键，并将该验证码与键关联起来存储在缓存中。
     * 验证码键的生成结合了微秒时间和随机数，以确保唯一性。
     * 验证码在缓存中的有效期通过配置文件设定，以分钟为单位。
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
        Cache::set('am_captcha' . $key, $code, Config::get('admin.captcha_exp', 5) * 60);

        // 返回生成的验证码键
        return $key;
    }

    /**
     * 创建密码修改表单
     *
     * 本函数用于生成一个用于修改密码的表单。表单的行为根据用户类型的不同而有所不同。
     * 可以是系统管理员修改密码，也可以是商家管理员自己修改密码。
     *
     * @param int $id 用户ID，用于标识特定用户的密码修改操作。
     * @param int $userType 用户类型，定义了密码修改的操作范围。2表示商家管理员，其他值表示系统管理员。
     * @return Form|string
     */
    public function passwordForm(int $id, $userType = 2)
    {
        // 根据用户类型确定表单提交的行动
        $action = 'merchantAdminPassword';
        if ($userType == self::PASSWORD_TYPE_ADMIN) {
            $action = 'systemMerchantAdminPassword';
        } else if ($userType == self::PASSWORD_TYPE_SELF) {
            $action = 'merchantAdminEditPassword';
        }

        // 创建表单，包括密码和确认密码两个字段
        $form = Elm::createForm(Route::buildUrl($action, $userType == self::PASSWORD_TYPE_SELF ? [] : compact('id'))->build(), [
            $rules[] = Elm::password('pwd', '密码：')->placeholder('请输入密码')->required('请输入密码'),
            $rules[] = Elm::password('againPassword', '确认密码：')->placeholder('请输入确认密码')->required('请输入确认密码'),
        ]);

        // 设置表单标题为“修改密码”
        return $form->setTitle('修改密码');
    }

    /**
     * 创建并返回编辑管理员信息的表单
     *
     * 该方法通过Element UI的表单构建器生成一个用于编辑管理员信息的表单。表单包含了管理员姓名和联系电话两个必填字段。
     * 表单的提交地址是通过路由生成的管理员编辑地址。使用该表单可以方便地收集和验证管理员的更新信息。
     *
     * @param array $formData 管理员当前的信息数据，用于填充表单的默认值。
     * @return Form|\think\response\View
     */
    public function editForm(array $formData)
    {
        // 通过路由生成编辑管理员信息的URL，用于表单的提交动作
        $form = Elm::createForm(Route::buildUrl('merchantAdminEdit')->build());

        // 设置表单的验证规则，包括管理员姓名和联系电话两个字段
        $form->setRule([
            // 管理员姓名字段，必填，用于输入管理员的姓名
            Elm::input('merchant_admin_id', '管理员ID：')->disabled(true),
            Elm::input('real_name', '管理员姓名：')->placeholder('请输入管理员姓名')->required('请输入管理员姓名'),
            // 联系电话字段，用于输入管理员的联系电话
            Elm::input('phone', '联系电话：')->placeholder('请输入联系电话')
        ]);

        // 设置表单的标题为“修改信息”，并加载传入的管理员当前信息数据，用于表单显示
        return $form->setTitle('修改信息')->formData($formData);
    }

    /**
     * 更新数据库中指定ID的记录。
     *
     * 本函数用于根据提供的ID和数据数组更新数据库中的相应记录。特别地，如果数据数组中包含'roles'字段，
     * 该字段将被转换为以逗号分隔的字符串格式，这是因为数据库中可能需要以这种格式存储角色数据。
     *
     * @param int $id 要更新的记录的ID。
     * @param array $data 包含要更新到数据库的字段和值的数据数组。
     * @return mixed 返回DAO层执行更新操作的结果。具体类型取决于DAO层的实现。
     */
    public function update(int $id, array $data)
    {
        // 如果$data数组中包含'roles'键，将其值转换为逗号分隔的字符串
        if (isset($data['roles'])) {
            $data['roles'] = implode(',', $data['roles']);
        }
        // 调用DAO层的update方法执行更新操作，并返回结果
        return $this->dao->update($id, $data);
    }

}
