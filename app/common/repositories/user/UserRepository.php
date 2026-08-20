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

namespace app\common\repositories\user;

use app\common\dao\BaseDao;
use app\common\dao\user\UserDao;
use app\common\model\user\User;
use app\common\model\wechat\WechatUser;
use app\common\repositories\BaseRepository;
use app\common\repositories\community\CommunityRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\store\service\StoreServiceRepository;
use app\common\repositories\system\attachment\AttachmentRepository;
use app\common\repositories\wechat\WechatUserRepository;
use crmeb\exceptions\AuthException;
use crmeb\jobs\SendNewPeopleCouponJob;
use crmeb\jobs\UserBrokerageLevelJob;
use crmeb\services\JwtTokenService;
use crmeb\services\QrcodeService;
use crmeb\services\RedisCacheService;
use crmeb\services\wechat\OfficialAccount;
use FormBuilder\Exception\FormBuilderException;
use FormBuilder\Factory\Elm;
use FormBuilder\Form;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\db\Query;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Db;
use think\facade\Queue;
use think\facade\Route;
use think\Model;

/**
 *  用户相关处理服务
 * Class UserRepository
 * @package app\common\repositories\user
 * @author xaboy
 * @day 2020-04-28
 * @mixin UserDao
 */
class UserRepository extends BaseRepository
{


    /**
     * UserRepository constructor.
     * @param UserDao $dao
     */
    public function __construct(UserDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取用户筛选条件列表
     *
     * 该方法提供了一个用户筛选条件的列表，这些条件可用于搜索或过滤用户。列表包括各种用户属性，
     * 如UID、昵称、手机号等。可以根据需要选择特定的筛选条件。
     *
     * @param bool $key 是否返回键值数组。默认为false，返回包含标签和值的数组；如果为true，只返回值数组。
     * @return array 返回一个包含用户筛选条件的数组。如果$key为true，返回值的数组。
     */
    public function filters($key = false)
    {
        // 定义用户筛选条件的列表，每个条件包括标签和对应的值
        $data = [
            ['label' => 'UID',       'value' => 'uid'],
            ['label' => '用户昵称',   'value' => 'nickname'],
            ['label' => '手机号',     'value' => 'phone'],
            ['label' => '性别',       'value' => 'sex'],
            ['label' => '身份',       'value' => 'is_promoter'],
            ['label' => '用户分组',    'value' => 'group_id'],
            ['label' => '用户标签',    'value' => 'label_id'],
            ['label' => '用户类别',    'value' => 'is_svip'],
            ['label' => '生日',        'value' => 'birthday'],
            ['label' => '消费次数',     'value' => 'pay_count'],
            ['label' => '会员等级',     'value' => 'member_level'],
            ['label' => '余额',        'value' => 'now_money'],
            ['label' => '首次访问时间', 'value' => 'create_time'],
            ['label' => '最近访问时间', 'value' => 'last_time'],
        ];

        // 如果$key为true，只返回值的数组
        if ($key) {
            $data = array_column($data, 'value');
        }

        // 返回用户筛选条件的数组
        return $data;
    }


    /**
     * 将用户设置为推广员
     *
     * 本函数用于将指定的用户标识为推广员，并记录设置时间为当前时间。通过更新数据库中用户的相应字段来实现。
     *
     * @param int $uid 用户ID，用于指定哪个用户将被设置为推广员。
     * @return mixed 返回更新操作的结果。具体类型取决于数据库操作的实现。
     */
    public function promoter($uid)
    {
        // 更新用户信息，将用户设置为推广员，并记录设置时间为当前时间
        return $this->dao->update($uid, ['is_promoter' => 1, 'promoter_time' => date('Y-m-d H:i:s')]);
    }

    /**
     *  满额分销 - 购买金额达到指定数量 成为分销员
     * @param $uid
     * @author Qinii
     * @day 2024/4/9
     */
    public function meetWithPromoter($uid)
    {
        $user = $this->dao->get($uid);
        if (!$user->is_promoter && $user->promoter_switch && systemConfig('promoter_type') == 3){
            $storeOrderRepository = app()->make(StoreOrderRepository::class);
            $pay_price = $storeOrderRepository->getSearch([])
                ->where(['uid' => $uid,'paid' => 1])
                ->whereNotNull('pay_time')
                ->sum('pay_price');
            $promoter_low_money = systemConfig('promoter_low_money');
            if (bccomp($pay_price,$promoter_low_money,2) !== -1) $this->promoter($uid);
        }
    }

    /**
     * 创建用户表单
     * 该方法用于生成创建新用户的表单，表单包含用户账号、密码、昵称、头像、真实姓名、手机号、身份证、性别、状态、是否为推广员等字段。
     * @return Form
     */
    public function createForm()
    {
        // 构建表单提交的URL
        $url = Route::buildUrl('systemUserCreate')->build();

        // 生成表单实例，并设置表单提交地址
        return Elm::createForm($url, [
            // 手机号(账号)字段，必填
            Elm::input('account', '手机号(账号)：')->placeholder('请输入手机号(账号)')->required('请输入手机号(账号)'),
            // 密码字段，必填
            Elm::password('pwd', '密码：')->placeholder('请输入密码')->required('请输入密码'),
            // 确认密码字段，必填
            Elm::password('repwd', '确认密码：')->placeholder('请输入确认密码')->required('请输入确认密码'),
            // 昵称字段，必填
            Elm::input('nickname', '用户昵称：')->placeholder('请输入用户昵称')->required('请输入用户昵称'),
            // 头像字段，使用框架内置的图片选择组件
            Elm::frameImage('avatar', '头像：', '/' . config('admin.admin_prefix') . '/setting/uploadPicture?field=avatar&type=1')
                ->modal(['modal' => false])
                ->icon('el-icon-camera')
                ->width('1000px')
                ->height('600px'),
            // 真实姓名字段
            Elm::input('real_name', '真实姓名：')->placeholder('请输入真实姓名'),
            // 手机号字段
            Elm::input('phone', '手机号：')->placeholder('请输入手机号'),
            // 身份证字段
            Elm::input('card_id', '身份证：')->placeholder('请输入身份证'),
            // 性别字段，使用单选框形式，默认为保密
            Elm::radio('sex', '性别：', 0)->options([
                ['value' => 0, 'label' => '保密：'],
                ['value' => 1, 'label' => '男：'],
                ['value' => 2, 'label' => '女：'],
            ]),
            // 状态字段，使用单选框形式，默认为正常
            Elm::radio('status', '状态：', 1)->options([
                ['value' => 0, 'label' => '禁用'],
                ['value' => 1, 'label' => '正常'],
            ])->requiredNum('请选择状态'),
            // 是否为推广员字段，使用单选框形式，默认为开启
            Elm::radio('is_promoter', '推广员：', 1)->options([
                ['value' => 0, 'label' => '关闭'],
                ['value' => 1, 'label' => '开启'],
            ])->requiredNum('请选择推广员状态'),
        ])
        // 设置表单标题
        ->setTitle('添加用户')
        // 设置表单初始数据
        ->formData([]);
    }

    /**
     * 保存用户扩展信息
     *
     * 本函数用于更新用户ID为$uid的用户的扩展信息。如果用户不存在，则抛出一个异常。
     * 使用本函数前，应确保传入的用户ID对应的存在于数据库中的用户。
     *
     * @param int $uid 用户ID，用于标识要更新信息的用户。
     * @param array $data 用户的扩展信息数组，默认为空数组。数组中的键值对表示要更新的字段及其对应的新值。
     * @throws ValidateException 如果用户信息查询结果为空，则抛出此异常，表示用户信息异常。
     */
    public function extendInfoSave(int $uid, array $data = [])
    {
        // 根据用户ID查询用户信息
        $userInfo = $this->dao->get($uid);
        // 如果查询结果为空，即用户不存在，则抛出异常
        if (empty($userInfo)) {
            throw new ValidateException('用户信息异常');
        }
        // 此处省略了更新用户扩展信息的代码逻辑
    }


    /**
     * 创建修改密码的表单
     *
     * 本函数用于生成一个用于修改用户密码的表单。通过传入用户ID，获取用户数据，并构建相应的表单字段，
     * 包括账号字段（展示用户账号，不可修改）、新密码字段和确认新密码字段。表单提交地址为修改密码的处理路由，
     * 保证用户在填写新密码并确认后，能够正确地提交修改请求。
     *
     * @param int $id 用户ID，用于根据ID获取用户数据，并在表单中展示用户账号。
     * @return Form|string
     * @throws ValidateException 如果根据用户ID未能获取到用户数据，则抛出异常，提示“用户不存在”。
     */
    public function changePasswordForm(int $id)
    {
        // 根据用户ID获取用户数据
        $formData = $this->dao->get($id);
        // 如果未能获取到用户数据，则抛出异常
        if (!$formData) throw new ValidateException('用户不存在');

        // 构建表单提交地址，并创建表单元素
        return Elm::createForm(Route::buildUrl('systemUserChangePassword', ['id' => $id])->build(), [
            // 创建展示用户账号的输入字段，该字段为只读状态
            Elm::input('account', '账号：', $formData->account)->placeholder('请输入账号')->disabled(true),
            // 创建新密码输入字段，要求必填
            Elm::password('pwd', '新密码：')->placeholder('新密码')->required('请输入新密码'),
            // 创建确认新密码输入字段，要求必填
            Elm::password('repwd', '确认新密码：')->placeholder('确认新密码')->required('请输入确认新密码'),
        ])->setTitle('修改密码')->formData([]);
    }

    /**
     * 创建用户表单
     * 该方法用于生成一个用于编辑用户的表单，表单包含用户的各种信息字段，如ID、真实姓名、手机号等。
     * 表单提交的URL是根据当前用户ID动态生成的，确保了表单提交的目标地址与当前编辑的用户ID对应。
     *
     * @param int $id 用户ID，用于获取用户信息并预填充到表单中。
     * @return Form|\think\response\View
     */
    public function userForm($id)
    {
        // 通过用户ID获取用户信息
        $user = $this->dao->get($id);
        // 将用户ID转换为字符串类型，以确保表单中的ID字段类型一致
        $user['uid'] = (string)$user['uid'];

        // 创建表单实例，设置表单提交URL和表单数据
        return Elm::createForm(Route::buildUrl('systemUserUpdate', compact('id'))->build(), [
            // 用户ID字段，设置为只读和必填
            Elm::input('uid', '用户 ID：', '')->disabled(true)->placeholder('请输入用户 ID')->required(true),
            // 真实姓名字段
            Elm::input('real_name', '真实姓名：')->placeholder('请输入真实姓名'),
            // 手机号字段
            Elm::input('phone', '手机号：')->placeholder('请输入手机号'),
            // 生日字段
            Elm::date('birthday', '生日：'),
            // 身份证字段
            Elm::input('card_id', '身份证：')->placeholder('请输入身份证'),
            // 用户地址字段
            Elm::input('addres', '用户地址：')->placeholder('请输入用户地址'),
            // 备注字段
            Elm::textarea('mark', '备注：')->placeholder('请输入备注'),
            // 用户分组选择字段，动态获取用户分组选项
            Elm::select('group_id', '用户分组：')->options(function () {
                $data = app()->make(UserGroupRepository::class)->allOptions();
                $options = [['value' => 0, 'label' => '请选择']];
                foreach ($data as $value => $label) {
                    $options[] = compact('value', 'label');
                }
                return $options;
            })->placeholder('请选择用户分组'),
            // 用户标签多选字段，动态获取用户标签选项
            Elm::selectMultiple('label_id', '用户标签：')->options(function () {
                $data = app()->make(UserLabelRepository::class)->allOptions();
                $options = [];
                foreach ($data as $value => $label) {
                    $value = (string)$value;
                    $options[] = compact('value', 'label');
                }
                return $options;
            })->placeholder('请选择用户标签'),
            // 用户状态单选字段，选项为开启或关闭
            Elm::radio('status', '状态：', 1)->options([
                ['value' => 0, 'label' => '关闭'],
                ['value' => 1, 'label' => '开启'],
            ])->requiredNum('请选择状态'),
            // 是否为推广员单选字段，选项为开启或关闭
            Elm::radio('is_promoter', '推广员：', 1)->options([
                ['value' => 0, 'label' => '关闭'],
                ['value' => 1, 'label' => '开启'],
            ])->requiredNum('请选择推广员状态'),
        ])
        // 设置表单标题和预填充数据
        ->setTitle('编辑')->formData($user->toArray());
    }

    /**
     *  用户列表
     * @param array $where
     * @param $page
     * @param $limit
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-05-07
     */
    public function getList(array $where, int $page, int $limit, array $viewSearch = [])
    {
        $query = $this->dao->search($where,$viewSearch);
        $query->with([
            'spread' => function ($query) {
                $query->field('uid,nickname,spread_uid');
            },
            'member' => function ($query) {
                $query->field('user_brokerage_id,brokerage_level,brokerage_name,brokerage_icon');
            },
            'group']);
        $make = app()->make(UserLabelRepository::class);
        $count = $query->count('User.' . $this->dao->getPk());
        $list = $query->page($page, $limit)->select()->each(function ($item) use ($make) {
            return $item->label = count($item['label_id']) ? $make->labels($item['label_id']) : [];
        });

        return compact('count', 'list');
    }

    /**
     * 获取公开列表
     *
     * 本函数用于根据给定的条件和分页信息，从数据库中检索并返回公开用户的列表。
     * 这里的“公开”指的是列表中的用户信息是对外公开的，仅包含有限的用户详情。
     *
     * @param array $where 搜索条件数组，用于指定查询的条件。
     * @param int $page 当前页码，用于实现分页功能。
     * @param int $limit 每页显示的记录数，用于控制分页大小。
     * @return array 返回包含用户列表和总数的数组。
     */
    public function getPulbicLst(array $where, $page, $limit)
    {
        // 根据搜索条件发起查询
        $query = $this->dao->search($where);

        // 统计满足条件的总记录数
        $count = $query->count();

        // 依据当前页码和每页记录数，从查询结果中获取指定页的用户信息
        // 这里指定了只返回uid、nickname和avatar这三个字段
        $list = $query->page($page, $limit)->setOption('field', [])->field('uid,nickname,avatar')->select();

        // 将总数和用户列表打包成数组返回
        return compact('count', 'list');
    }

    /**
     * 计算推广员相关统计数据
     *
     * @param array $where 查询条件
     * @return array 包含各种统计数据的数组
     *
     * 该方法用于根据给定的查询条件，计算和返回推广员的各类统计数据，包括：
     * - 分销员人数
     * - 推广人数
     * - 推广订单数
     * - 推广订单金额
     * - 已提现金额
     * - 未提现金额
     */
    public function promoterCount($where)
    {
        if (systemConfig('promoter_type') != 2) {
            // 将查询条件中的推广员标识设置为1，表示只查询推广员
            $where['is_promoter'] = 1;
            $where['promoter_switch'] = 1;
        } else {
            $where['is_promoter'] = [0,1];
        }
        // 查询并计算各种统计数据
        $total = $this->dao->search($where)
            ->field('sum(spread_count) as spread_count,sum(spread_pay_count) as spread_pay_count,sum(spread_pay_price) as spread_pay_price,count(uid) as total_user,sum(brokerage_price) as brokerage_price')->find();

        // 如果查询结果为空，则初始化统计数据数组
        $total = $total ? $total->toArray() : ['spread_count' => 0, 'spread_pay_count' => 0, 'spread_pay_price' => 0, 'total_user' => 0, 'brokerage_price' => 0];

        // 获取已提现金额
        $total['total_extract'] = app()->make(UserExtractRepository::class)->getTotalExtractPrice($where);

        // 构建并返回包含各种统计数据的数组
        return [
            [
                'className' => 'el-icon-s-goods',
                'count' => $total['total_user'] ?? 0,
                'name' => '分销员人数(人)'
            ],
            [
                'className' => 'el-icon-s-order',
                'count' => $total['spread_count'] ?? 0,
                'name' => '推广人数(人)'
            ],
            [
                'className' => 'el-icon-s-cooperation',
                'count' => (int)($total['spread_pay_count'] ?? 0),
                'name' => '推广订单数'
            ],
            [
                'className' => 'el-icon-s-cooperation',
                'count' => (float)($total['spread_pay_price'] ?? 0),
                'name' => '推广订单金额'
            ],
            [
                'className' => 'el-icon-s-cooperation',
                'count' => (float)($total['total_extract'] ?? 0),
                'name' => '已提现金额(元)'
            ],
            [
                'className' => 'el-icon-s-cooperation',
                'count' => (float)($total['brokerage_price'] ?? 0),
                'name' => '未提现金额(元)'
            ],
        ];
    }

    /**
     * 获取推广员列表
     *
     * 根据给定的条件和分页信息，查询推广员列表及其相关信息，包括推广员的提取信息和佣金信息。
     *
     * @param array $where 查询条件数组
     * @param int $page 当前页码
     * @param int $limit 每页数据条数
     * @return array 返回包含推广员列表和总数的信息
     */
    public function promoterList(array $where, $page, $limit)
    {

        if (systemConfig('promoter_type') != 2) {
            // 将查询条件中的推广员标识设置为1，表示只查询推广员
            $where['is_promoter'] = 1;
            $where['promoter_switch'] = 1;
        } else {
            $where['is_promoter'] = [0,1];
        }

        // 构建查询，包括推广员的传播信息和佣金信息
        $query = $this->dao->search($where)
            ->with([
                'spread' => function ($query) {
                    // 查询推广员的传播者信息，包括uid、nickname和spread_uid
                    $query->field('uid,nickname,spread_uid');
                },
                'brokerage' => function ($query) {
                    // 查询推广员的佣金信息，包括brokerage_level、brokerage_name和brokerage_icon
                    $query->field('brokerage_level,brokerage_name,brokerage_icon');
                }
            ]);

        // 计算满足条件的推广员总数
        $count = $query->count($this->dao->getPk());

        // 分页查询推广员信息，并转换为数组格式
        $list = $query->page($page, $limit)->select()->toArray();

        // 如果查询到推广员信息
        if (count($list)) {
            // 获取所有推广员的提取信息
            $promoterInfo = app()->make(UserExtractRepository::class)->getPromoterInfo(array_column($list, 'uid'));

            // 将提取信息按照uid进行组合
            if (count($promoterInfo)) {
                $promoterInfo = array_combine(array_column($promoterInfo, 'uid'), $promoterInfo);
            }

            // 遍历推广员列表，补充提取总额、提取总次数和佣金总额信息
            foreach ($list as $k => $item) {
                // 补充推广员的提取总额和提取总次数
                $list[$k]['total_extract_price'] = $promoterInfo[$item['uid']]['total_price'] ?? 0;
                $list[$k]['total_extract_num'] = $promoterInfo[$item['uid']]['total_num'] ?? 0;

                // 计算推广员的佣金总额，保留两位小数
                $list[$k]['total_brokerage_price'] = (float)bcadd($item['brokerage_price'], $list[$k]['total_extract_num'], 2);
            }
        }

        // 返回推广员列表和总数
        return compact('count','list');
    }

    /**
     * 根据关键字搜索商家用户列表
     *
     * 本函数用于根据给定的关键字搜索商家用户，并返回分页后的商家用户列表以及总数。
     * 主要用于商家管理相关的功能模块，提供数据支持。
     *
     * @param string $keyword 搜索关键字，用于匹配商家用户的用户名或昵称等信息。
     * @param int $page 当前页码，用于实现分页功能。
     * @param int $limit 每页显示的数量，用于控制分页的显示数量。
     * @return array 返回包含商家用户总数和分页后商家用户列表的数组。
     */
    public function merList(string $keyword, $page, $limit)
    {
        // 根据关键字查询商家用户，这里不具体注释ORM的调用过程
        $query = $this->dao->searchMerUser($keyword);

        // 统计符合搜索条件的商家用户总数
        $count = $query->count($this->dao->getPk());

        // 获取分页后的商家用户列表，只选取需要的字段
        $list = $query->page($page, $limit)->setOption('field', [])->field('uid,nickname,avatar,user_type,sex')->select();

        // 将总数和列表信息组合成数组返回
        return compact('count', 'list');
    }

    /**
     * 创建用户组更改表单
     * 该方法用于生成一个用于更改用户组的表单，表单根据用户ID的数量（单个或多个）动态生成URL，并提供用户组选择选项。
     *
     * @param mixed $id 用户ID，可以是单个ID或ID数组
     * @return Form|string
     */
    public function changeGroupForm($id)
    {
        // 检查$id是否为数组
        $isArray = is_array($id);

        // 如果$id不是数组，获取单个用户的对象
        if (!$isArray) {
            $user = $this->dao->get($id);
        }

        // 通过依赖注入获取用户组仓库，用于获取所有用户组选项
        /** @var UserGroupRepository $make */
        $data = app()->make(UserGroupRepository::class)->allOptions();

        // 根据$id是否为数组，构建不同的表单提交URL，创建表单元素
        return Elm::createForm(Route::buildUrl($isArray ? 'systemUserBatchChangeGroup' : 'systemUserChangeGroup', $isArray ? [] : compact('id'))->build(), [
            // 隐藏字段用于提交用户ID
            Elm::hidden('ids', $isArray ? $id : [$id]),
            // 下拉选择框用于选择用户组，初始选中用户当前的用户组
            Elm::select('group_id', '用户分组：', $isArray ? '' : $user->group_id)
                ->options(function () use ($data) {
                    // 构建包含“请选择”选项的用户组选项列表
                    $options = [['label' => '请选择分组', 'value' => 0]];
                    // $options = [];
                    foreach ($data as $value => $label) {
                        $options[] = compact('value', 'label');
                    }
                    return $options;
                })
                ->placeholder('请选择用户分组')
        ])->setTitle('修改用户分组');
    }

    /**
     * 创建用户标签更改表单
     * 该方法用于生成一个表单，用于更改单个或多个用户的标签。表单根据$id$是否为数组来决定是更改单个用户还是多个用户。
     *
     * @param mixed $id 用户ID，可以是单个ID或ID数组
     * @return Form 表单实例
     */
    public function changeLabelForm($id)
    {
        // 检查$id$是否为数组
        $isArray = is_array($id);
        // 如果$id$不是数组，获取单个用户对象
        if (!$isArray) {
            $user = $this->dao->get($id);
        }

        // 通过依赖注入获取用户标签仓库，用于获取所有标签选项
        /** @var UserLabelRepository $make */
        $userLabelRepository = app()->make(UserLabelRepository::class);
        $data = $userLabelRepository->allOptions();

        // 根据$id$是否为数组，构建不同的表单提交URL，创建表单实例
        return Elm::createForm(Route::buildUrl($isArray ? 'systemUserBatchChangeLabel' : 'systemUserChangeLabel', $isArray ? [] : compact('id'))->build(), [
            // 隐藏字段，用于提交用户ID
            Elm::hidden('ids', $isArray ? $id : [$id]),
            // 多选下拉列表，用于选择用户标签，初始值为用户当前标签
            Elm::selectMultiple('label_id', '用户标签：', $userLabelRepository->labels(isset($user) ? ($user->label_id) : []))
                ->options
    (function () use ($data) {
                $options = [];
                // 构建下拉列表的选项数组
                foreach ($data as $value => $label) {
                    $value = (string)$value;
                    $options[] = compact('value', 'label');
                }
                return $options;
            })->placeholder('请选择用户标签')
        ])->setTitle('修改用户标签');
    }

    /**
     * 设置分销推广员
     * @param $id
     * @return Form
     * @author Qinii
     * @day 2023/10/26
     */
    public function batchSpreadForm($id)
    {
        /** @var UserLabelRepository $make */
        $data = app()->make(UserLabelRepository::class)->allOptions();
        return Elm::createForm(Route::buildUrl('getMemberLevelBatchSpread', compact('id'))->build(), [
            Elm::hidden('uids', $id),
            Elm::radio('promoter_switch', '推广资格：', 1)->options([
                    ['value' => 1, 'label' => '是'],
                    ['value' => 0, 'label' => '否']]
            )->appendRule('suffix', [
                'type' => 'div',
                'style' => ['color' => '#999999'],
                'domProps' => [
                    'innerHTML' => '关闭用户的推广资格后，在任何分销模式下该用户都无分销权限',
                ]
            ]),
            Elm::radio('is_promoter', '推广权限：', 1)->options([
                    ['value' => 1, 'label' => '是'],
                    ['value' => 0, 'label' => '否']]
            )->appendRule('suffix', [
                'type' => 'div',
                'style' => ['color' => '#999999'],
                'domProps' => [
                    'innerHTML' => '在推广资格开启的情况，此处推广权限可控制礼包分销、手动分销、满额分销模式下的推广员权限',
                ]
            ]),
        ])->setTitle('批量设置推广员');
    }

    /**
     * 创建一个表单来修改当前用户的余额
     *
     * 该方法通过Elm组件库创建一个表单，用于系统后台修改用户余额。表单包含两个字段：
     * 1. 修改类型：通过单选按钮（radio）选择是增加还是减少余额；
     * 2. 修改金额：通过数字输入框（number）输入具体的金额。
     * 表单提交的URL是根据给定的用户ID动态生成的，确保了表单操作的特定性。
     *
     * @param int $id 用户ID，用于构建表单提交的URL，确保操作针对特定用户。
     * @return \Encore\Admin\Widgets\Form|Form
     */
    public function changeNowMoneyForm($id)
    {
        // 创建表单，并设置表单提交的URL
        return Elm::createForm(Route::buildUrl('systemUserChangeNowMoney', compact('id'))->build(), [
            // 创建单选按钮字段，用于选择增加或减少余额
            Elm::radio('type', '修改余额：', 1)->options([
                ['label' => '增加', 'value' => 1],
                ['label' => '减少', 'value' => 0],
            ])->requiredNum('请选择修改余额类型'),
            // 创建数字输入框字段，用于输入修改的金额
            Elm::number('now_money', '金额:')->required('请输入金额')->min(0)->max(999999)
        ])->setTitle('修改用户余额');
    }


    /**
     * 创建一个用于修改用户积分的表单
     *
     * 该方法通过Elm组件库创建一个表单，用于用户积分的增加或减少操作。表单提交的URL是根据给定的用户ID动态生成的，
     * 表单包含两个字段：类型（增加或减少积分）和当前积分值。表单标题为“修改用户积分”。
     *
     * @param int $id 用户ID，用于构建表单提交的URL
     * @return \Encore\Admin\Widgets\Form|Form
     */
    public function changeIntegralForm($id)
    {
        // 创建表单，设置表单提交的URL和包含的字段
        return Elm::createForm(Route::buildUrl('systemUserChangeIntegral', compact('id'))->build(), [
            // 创建单选按钮组，用于选择积分修改类型（增加或减少）
            Elm::radio('type', '修改积分：', 1)->options([
                ['label' => '增加', 'value' => 1],
                ['label' => '减少', 'value' => 0],
            ])->requiredNum('请选择修改积分类型'),
            // 创建数字输入框，用于输入当前积分值，要求非空，最小值为0，最大值为999999
            Elm::number('now_money', '积分')->required('请输入积分')->min(0)->max(999999)
        ])->setTitle('修改用户积分');
    }

    /**
     * 修改用户余额
     *
     * 本函数用于根据类型增加或减少用户的余额，并在数据库中记录相应的操作。
     * 通过事务处理确保操作的原子性，防止数据不一致。
     *
     * @param int $id 用户ID
     * @param int $adminId 操作管理员ID
     * @param int $type 操作类型，1表示增加，其他表示减少
     * @param float $nowMoney 修改的余额数量
     */
    public function changeNowMoney($id, $adminId, $type, $nowMoney)
    {
        // 根据用户ID获取用户信息
        $user = $this->dao->get($id);
        // 使用事务处理来确保操作的原子性
        Db::transaction(function () use ($id, $adminId, $user, $type, $nowMoney) {
            // 根据操作类型计算新的余额
            $balance = $type == 1 ? bcadd($user->now_money, $nowMoney, 2) : bcsub($user->now_money, $nowMoney, 2);
            // 保存用户的新余额
            $user->save(['now_money' => $balance]);

            // 创建用户账单记录对象
            /** @var UserBillRepository $make */
            $make = app()->make(UserBillRepository::class);
            // 根据操作类型增加或减少用户的账单记录
            if ($type == 1) {
                // 增加余额时的操作
                $make->incBill($id, 'now_money', 'sys_inc_money', [
                    'link_id' => $adminId,
                    'status' => 1,
                    'title' => '系统增加余额',
                    'number' => $nowMoney,
                    'mark' => '系统增加了' . floatval($nowMoney) . '余额',
                    'balance' => $balance
                ]);
            } else {
                // 减少余额时的操作
                $make->decBill($id, 'now_money', 'sys_dec_money', [
                    'link_id' => $adminId,
                    'status' => 1,
                    'title' => '系统减少余额',
                    'number' => $nowMoney,
                    'mark' => '系统减少了' . floatval($nowMoney) . '余额',
                    'balance' => $balance
                ]);
            }
        });
    }

    /**
     * 修改用户积分
     *
     * 该方法用于处理用户积分的增加或减少操作。通过传入的类型参数来判断是增加还是减少积分，
     * 并在事务中确保操作的原子性。操作完成后，还会记录用户的积分变动详情。
     *
     * @param int $id 用户ID
     * @param int $adminId 操作管理员ID
     * @param int $type 积分变动类型，1表示增加，其他表示减少
     * @param int $integral 积分变动的数值
     */
    public function changeIntegral($id, $adminId, $type, $integral)
    {
        // 根据用户ID获取用户信息
        $user = $this->dao->get($id);
        // 使用事务处理来确保积分修改和账单记录的操作原子性
        Db::transaction(function () use ($id, $adminId, $user, $type, $integral) {
            // 强制将积分值转换为整型，确保数据类型的一致性和准确性
            $integral = (int)$integral;
            // 根据类型增加或减少积分，并保存新的积分值
            $balance = $type == 1 ? bcadd($user->integral, $integral, 0) : max(0, bcsub($user->integral, $integral, 0));
            $user->save(['integral' => $balance]);
            /** @var UserBillRepository $make */
            // 创建账单记录器实例，用于记录用户的积分变动情况
            $make = app()->make(UserBillRepository::class);
            // 根据积分变动类型，记录相应的增加或减少积分的账单
            if ($type == 1) {
                $make->incBill($id, 'integral', 'sys_inc', [
                    'link_id' => $adminId,
                    'status' => 1,
                    'title' => '系统增加积分',
                    'number' => $integral,
                    'mark' => '系统增加了' . $integral . '积分',
                    'balance' => $balance
                ]);
            } else {
                $make->decBill($id, 'integral', 'sys_dec', [
                    'link_id' => $adminId,
                    'status' => 1,
                    'title' => '系统减少积分',
                    'number' => $integral,
                    'mark' => '系统减少了' . $integral . '积分',
                    'balance' => $balance
                ]);
            }
        });
    }

    /**
     * 对密码进行加密处理
     *
     * 使用BCrypt算法对密码进行加密，以提高密码的安全性。BCrypt算法是一种基于口令的加密方式，它通过使用大的随机盐值来增强加密强度。
     * 此方法不接受任何额外的参数，因为BCrypt算法的配置选项已经被内部处理，以提供最佳的安全实践。
     *
     * @param string $password 需要加密的明文密码
     * @return string 返回加密后的密码hash值
     */
    public function encodePassword($password)
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    /**
     * 根据用户类型参数确定具体的用户类型。
     *
     * 此函数用于根据传入的用户类型参数，映射为特定的用户类型值。
     * 它处理了三种情况：
     * 1. 如果参数值为 'apple'，则返回 'app'，表示苹果用户；
     * 2. 如果参数值为空或不存在，则返回 'h5'，表示H5用户；
     * 3. 对于其他所有情况，直接返回参数值本身。
     *
     * @param string $type 用户类型参数，可能的值包括但不限于 'apple'、'android' 等。
     * @return string 返回映射后的用户类型值，可能的返回值包括 'app'、'h5' 以及传入的其他任意类型参数值。
     */
    public function userType($type)
    {
        // 当用户类型参数为 'apple' 时，映射为 'app'
        if ($type === 'apple') {
            return 'app';
        }
        // 当用户类型参数为空或不存在时，默认视为 'h5' 类型
        if (!$type)
            return 'h5';
        // 对于其他所有情况，直接返回用户类型参数值
        return $type;
    }


    /**
     * 同步微信基础认证信息到用户账号。
     *
     * 该方法用于将微信用户的基本信息（如ID、昵称、头像等）同步到系统的用户账号中。
     * 这样做的目的是为了在用户使用微信登录时，能够快速便捷地建立或更新用户信息，
     * 以便用户能够顺利进行后续的操作。
     *
     * @param array $auth 微信认证信息，包含用户ID和用户类型。
     * @param User $user 系统中的用户对象，用于存储同步后的信息。
     */
    public function syncBaseAuth(array $auth, User $user)
    {
        // 通过微信用户ID获取微信用户信息。
        $wechatUser = app()->make(WechatUserRepository::class)->get($auth['id']);

        // 如果微信用户存在，则进一步处理信息同步。
        if ($wechatUser) {
            // 准备需要同步到用户账号的基础信息。
            $data = ['wechat_user_id' => $auth['id'], 'user_type' => $auth['type']];

            // 如果微信用户有昵称，则同步昵称到用户账号。
            if ($wechatUser['nickname']) {
                $data['nickname'] = $wechatUser['nickname'];
            }

            // 如果微信用户有头像URL，则同步头像到用户账号。
            if ($wechatUser['headimgurl']) {
                $data['avatar'] = $wechatUser['headimgurl'];
            }

            // 同步微信用户的性别信息，如果微信用户没有性别信息，则默认为0。
            $data['sex'] = $wechatUser['sex'] ?? 0;

            // 更新用户信息。
            $user->save($data);
        }
    }

    /**
     * 同步微信用户信息到数据库。
     *
     * 该方法用于处理微信用户数据的同步操作。它首先尝试根据微信用户ID查找已存在的用户记录，
     * 如果找到，则更新用户信息；如果没有找到，则创建一个新的用户记录。
     *
     * @param WechatUser $wechatUser 微信用户对象，包含从微信接口获取的用户信息。
     * @param string $userType 用户类型，默认为'wechat'，表示微信用户。
     * @return BaseDao|User|object|Model
     */
    public function syncWechatUser(WechatUser $wechatUser, $userType = 'wechat')
    {
        // 根据微信用户ID查找已存在的用户记录。
        $user = $this->dao->wechatUserIdBytUser($wechatUser->wechat_user_id);
        $request = request();

        // 如果用户记录存在，则更新用户信息。
        if ($user) {
            $user->save(array_filter([
                'nickname' => $wechatUser['nickname'] ?? '',
                'avatar' => $wechatUser['headimgurl'] ?? '',
                'sex' => $wechatUser['sex'] ?? 0,
                'last_time' => date('Y-m-d H:i:s'),
                'last_ip' => $request->ip(),
            ]));
        } else {
            // 如果用户记录不存在，则创建一个新的用户记录。
            $user = $this->create($userType, [
                'account' => 'wx' . $wechatUser->wechat_user_id . time(),
                'wechat_user_id' => $wechatUser->wechat_user_id,
                'pwd' => $this->encodePassword($this->dao->defaultPwd()),
                'nickname' => $wechatUser['nickname'] ?? '',
                'avatar' => $wechatUser['headimgurl'] ?? '',
                'sex' => $wechatUser['sex'] ?? 0,
                'spread_uid' => 0,
                'is_promoter' => 0,
                'last_time' => date('Y-m-d H:i:s'),
                'last_ip' => $request->ip()
            ]);
        }

        return $user;
    }

    /**
     * 根据用户类型和信息创建用户。
     *
     * 本函数用于根据提供的用户类型和详细信息创建一个新的用户。它首先处理额外的信息，
     * 然后设置用户类型和默认状态，最后通过数据访问对象（DAO）创建用户，并执行创建后的注册操作。
     * 如果存在扩展信息，则将其单独保存。
     *
     * @param string $type 用户类型。
     * @param array $userInfo 用户详细信息。
     * @return object 创建的用户对象。
     */
    public function create(string $type, array $userInfo)
    {
        // 初始化扩展信息数组
        $extend_info = [];
        // 检查并提取userInfo中的扩展信息，然后从userInfo中移除
        if(isset($userInfo['extend_info'])){
            $extend_info = $userInfo['extend_info'];
            unset($userInfo['extend_info']);
        }

        // 设置用户类型，根据$type调用userType方法获取具体类型
        $userInfo['user_type'] = $this->userType($type);
        // 如果userInfo中没有设置状态，则默认为1
        if (!isset($userInfo['status'])) {
            $userInfo['status'] = 1;
        }
        $userInfo['last_ip'] = app('request')->ip();
        // 通过DAO创建用户对象
        $user = $this->dao->create($userInfo);
        // 标记新创建的用户对象
        $user->isNew = true;
        // 执行创建用户后的注册操作
        $this->registerAfter($user);
        // 保存用户的扩展信息
        // 保存扩展字段数据
        $this->saveFields((int)$user['uid'], $extend_info);
        // 返回创建的用户对象
        return $user;
    }

    /**
     *  新人注册后赠送积分优惠券等
     * @param $user
     * @return void
     * @author Qinii
     */
    public function registerAfter($user)
    {
        $uid = $user->uid;
        $userBillRepository = app()->make(UserBillRepository::class);
        if (systemConfig('newcomer_status')) {
            if (systemConfig('register_integral_status')) {
                $integral = (int)systemConfig('register_give_integral');
                $user->integral = $integral;
                $userBillRepository->incBill($uid, 'integral', 'sys_inc', [
                    'link_id' => 0,
                    'status' => 1,
                    'title' => '新人赠送积分',
                    'number' => $integral,
                    'mark' => '系统增加了' . $integral . '积分',
                    'balance' => $integral
                ]);
            }
            if (systemConfig('register_money_status')) {
                $nowMoney = (int)systemConfig('register_give_money');
                $user->now_money = $nowMoney;
                // 增加余额时的操作
                $userBillRepository->incBill($uid, 'now_money', 'sys_inc_money', [
                    'link_id' => 0,
                    'status' => 1,
                    'title' => '新人赠送余额',
                    'number' => $nowMoney,
                    'mark' => '系统增加了' . floatval($nowMoney) . '余额',
                    'balance' => $nowMoney
                ]);

            }
            $user->save();
            try {Queue::push(SendNewPeopleCouponJob::class, $user->uid);} catch (\Exception $e) {}
        }
        return true;
    }

    /**
     * 创建用户的JWT令牌
     *
     * 本函数用于生成针对用户的JSON Web Token (JWT)，该令牌用于用户身份验证。
     * 它通过JwtTokenService创建令牌，并将令牌及其过期时间存储到缓存中。
     *
     * @param User $user 用户对象，用于生成令牌的用户信息。
     * @return array 包含令牌和过期时间的数组。
     */
    public function createToken(User $user)
    {
        // 实例化JWT令牌服务类
        $service = new JwtTokenService();

        // 从配置获取用户令牌的有效期，默认为365天
        $exp = intval(Config::get('admin.user_token_valid_exp', 365));

        // 使用服务类创建令牌，包括用户ID、令牌类型和过期时间
        $token = $service->createToken($user->uid, 'user', strtotime("+ {$exp}day"));

        // 将生成的令牌及其过期时间存储到缓存中
        $this->cacheToken($token['token'], $token['out']);

        // 返回生成的令牌信息
        return $token;
    }

    /**
     * 登录成功后
     * @param User $user
     * @author xaboy
     * @day 2020/6/22
     */
    public function loginAfter(User $user)
    {
        $user->last_time = date('Y-m-d H:i:s', time());
        $user->last_ip = request()->ip();
        $isPromoter = $user->is_promoter;
        //如果人人分销不想改变用户本来的分销状态,就将if 中的代码注释代码注释,并修改 get_extension_info 方法中的注释部分
        if (!$isPromoter && systemConfig('extension_status')) {
            $isPromoter = systemConfig('promoter_type') == 2  ? 1 : 0;
            $user->is_promoter = $isPromoter;
        }
        $user->save();
    }

    /**
     * 缓存用户令牌。
     *
     * 本函数用于将用户的令牌及其过期时间存储到缓存中。缓存的目的是为了提高访问速度，
     * 避免频繁地对数据库或持久层进行读写操作。令牌的缓存时间由$exp参数指定，
     * 在此时间范围内，用户无需再次进行身份验证。
     *
     * @param string $token 用户的令牌。这是一个唯一标识用户的字符串。
     * @param int $exp 令牌的过期时间，以秒为单位。从当前时间开始，令牌将在$exp秒后失效。
     */
    public function cacheToken(string $token, int $exp)
    {
        // 使用文件缓存系统存储用户令牌及其过期时间。
        // 'user_'前缀用于区分不同类型的缓存数据，这里特指用户令牌。
        // $exp作为第三个参数指定缓存的过期时间。
        Cache::set('user_' . $token, time() + $exp, $exp);
    }

    /**
     * 检查令牌（Token）的有效性。
     *
     * 本函数通过验证令牌是否存在以及是否过期来确保用户的身份验证状态。
     * 它首先从缓存中检查令牌是否存在，如果不存在，则抛出一个授权异常。
     * 接着，它获取令牌的最后更新时间，并检查是否超过预设的有效期，如果超过，则同样抛出授权异常。
     * 这样做可以有效地防止未授权的访问，并确保用户的会话安全。
     *
     * @param string $token 用户的令牌，用于身份验证。
     * @throws AuthException 如果令牌无效或已过期，则抛出此异常。
     */
    public function checkToken(string $token)
    {
        // 检查缓存中是否存在以'user_'前缀加上令牌值为键的记录
        $has = Cache::has('user_' . $token);
        // 如果令牌不存在于缓存中，则抛出授权异常
        if (!$has)throw new AuthException('无效的token');
        // 从缓存中获取令牌的最后更新时间
        $lastTime = Cache::get('user_' . $token);
        // 检查令牌是否过期，如果过期，则抛出授权异常
        if (($lastTime + (intval(Cache::get('admin.user_token_valid_exp', 15))) * 24 * 60 * 60) < time())
            throw new AuthException('token 已过期，请重新登录');
    }

    /**
     * 更新用户的令牌
     *
     * 本函数用于更新用户令牌的缓存，确保用户令牌的有效性。令牌是用户身份验证的关键，
     * 通过更新令牌，我们可以延长用户的登录状态，而无需用户频繁输入登录凭据。
     *
     * @param string $token 用户的令牌。这是一个唯一标识用户的字符串，用于验证用户身份。
     */
    public function updateToken(string $token)
    {
        // 使用文件缓存系统存储用户令牌的过期时间。
        // 这里将令牌的过期时间设置为配置文件中指定的值（默认为15天）乘以24小时、60分钟、60秒，以确保令牌在指定时间内有效。
        Cache::set('user_' . $token, time(), intval(Config::get('admin.user_token_valid_exp', 15)) * 24 * 60 * 60);
    }

    /**
     * 清除指定用户的令牌缓存。
     *
     * 本函数用于从缓存系统中删除指定用户的令牌，通常在用户登出或令牌失效时调用。
     * 通过删除令牌缓存，可以确保用户需要重新认证才能访问受保护的资源。
     *
     * @param string $token 用户的令牌。此令牌用于唯一标识当前用户会话。
     */
    public function clearToken(string $token)
    {
        // 使用文件缓存系统删除指定用户的令牌缓存。
        Cache::delete('user_' . $token);
    }

    /**
     * 验证验证码是否正确。
     * 该方法用于校验用户输入的验证码是否与缓存中存储的验证码一致。如果不一致或验证码已过期，则抛出异常。
     *
     * @param string $key 验证码的唯一标识键，用于拼接缓存键名。
     * @param string $code 用户输入的验证码。
     * @throws ValidateException 如果验证码过期或输入错误，则抛出验证异常。
     */
    public function checkCode(string $key, string $code)
    {
        // 从缓存中获取存储的验证码
        $_code = Cache::get('am_captcha' . $key);
        // 如果缓存中没有验证码，表示验证码已过期，抛出异常
        if (!$_code) {
            throw new ValidateException('验证码过期');
        }
        // 比较用户输入的验证码和缓存中的验证码，不区分大小写
        // 如果不一致，抛出验证码错误的异常
        if (strtolower($_code) != strtolower($code)) {
            throw new ValidateException('验证码错误');
        }
        // 验证码验证通过后，删除缓存中的验证码，防止重复使用
        // 删除code
        Cache::delete('am_captcha' . $key);
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
     * 注册新用户
     *
     * 本函数用于处理用户的注册流程，通过电话号码进行注册。如果提供了密码，则对密码进行加密；
     * 如果没有提供密码，则使用默认密码。注册信息包括电话号码、加密后的密码、昵称、头像地址和最后登录的IP地址。
     *
     * @param string $phone 用户的电话号码，作为账号标识。
     * @param string|null $pwd 用户输入的密码，如果为null，则使用默认密码。
     * @param string $user_type 用户类型，默认为'h5'，用于区分不同类型的用户注册。
     * @return mixed 注册结果，具体类型取决于create方法的返回值。
     */
    public function registr(string $phone, ?string $pwd, $user_type = 'h5')
    {
        // 根据是否提供了密码，选择性地对其进行加密处理
        $pwd = $pwd ? $this->encodePassword($pwd) : $this->encodePassword($this->dao->defaultPwd());

        // 构建用户注册信息数组，包括电话号码、加密后的密码、昵称、头像地址和最后登录的IP地址
        $data = [
            'account' => $phone,
            'pwd' => $pwd,
            'nickname' => substr($phone, 0, 3) . '****' . substr($phone, 7, 4),
            'avatar' => '',
            'phone' => $phone,
            'last_ip' => app('request')->ip()
        ];
        env('registr.before',compact('data'));
        // 调用create方法，根据用户类型创建新用户
        return $this->create($user_type, $data);
    }

    /**
     * 生成小程序推广图片
     * 该方法用于根据用户信息生成小程序的推广图片，包括在海报中添加二维码、用户昵称和邀请信息。
     * @param User $user 用户对象，包含用户ID和昵称等信息
     * @return array 返回生成的推广图片信息数组
     * @throws ValidateException 如果生成二维码或海报失败，抛出异常
     */
    public function routineSpreadImage(User $user)
    {
        //小程序
        $name = md5('surt' . $user['uid'] . $user['is_promoter'] . date('Ymd')) . '.jpg';
        $attachmentRepository = app()->make(AttachmentRepository::class);
        $imageInfo = $attachmentRepository->getWhere(['attachment_name' => $name]);
        $spreadBanner = systemGroupData('spread_banner');
        if (!count($spreadBanner)) return [];
        $siteName = systemConfig('site_name');
        $siteUrl = systemConfig('site_url');
        $uploadType = (int)systemConfig('upload_type') ?: 1;

        $urlCode = app()->make(QrcodeService::class)->getRoutineQrcodePath($name, 'pages/index/index', 'spid=' . $user['uid']);
        if (!$urlCode)
            throw new ValidateException('二维码生成失败');
        $filelink = [
            'Bold' => 'public/font/Alibaba-PuHuiTi-Regular.otf',
            'Normal' => 'public/font/Alibaba-PuHuiTi-Regular.otf',
        ];
        if (!file_exists($filelink['Bold'])) throw new ValidateException('缺少字体文件Bold');
        if (!file_exists($filelink['Normal'])) throw new ValidateException('缺少字体文件Normal');
        $resRoutine = true;
        foreach ($spreadBanner as $key => &$item) {
            $posterInfo = '海报生成失败:(';
            $config = array(
                'image' => array(
                    array(
                        'url' => $urlCode,     //二维码资源
                        'stream' => 0,
                        'left' => 114,
                        'top' => 790,
                        'right' => 0,
                        'bottom' => 0,
                        'width' => 120,
                        'height' => 120,
                        'opacity' => 100
                    )
                ),
                'text' => array(
                    array(
                        'text' => $user['nickname'],
                        'left' => 250,
                        'top' => 840,
                        'fontPath' => $filelink['Bold'],     //字体文件
                        'fontSize' => 16,             //字号
                        'fontColor' => '40,40,40',       //字体颜色
                        'angle' => 0,
                    ),
                    array(
                        'text' => '邀请您加入' . $siteName,
                        'left' => 250,
                        'top' => 880,
                        'fontPath' => $filelink['Normal'],     //字体文件
                        'fontSize' => 16,             //字号
                        'fontColor' => '40,40,40',       //字体颜色
                        'angle' => 0,
                    )
                ),
                'background' => $item['pic']
            );
            $resRoutine = $resRoutine && $posterInfo = setSharePoster($config, 'routine/spread/poster');
            if (!is_array($posterInfo)) throw new ValidateException($posterInfo);
            $posterInfo['dir'] = tidy_url($posterInfo['dir'], 0, $siteUrl);
            if ($resRoutine) {
                $attachmentRepository->create($uploadType, -1, $user->uid, [
                    'attachment_category_id' => 0,
                    'attachment_name' => $posterInfo['name'],
                    'attachment_src' => $posterInfo['dir']
                ]);
                $item['poster'] = $posterInfo['dir'];
            }
        }
        return $spreadBanner;
    }

    /**
     * 生成微信二维码
     *
     * 该方法用于生成用户的微信二维码，二维码中包含用户ID等信息，用于用户扫码登录或其他微信相关功能。
     *
     * @param User $user 用户对象，包含用户的唯一标识符uid。
     * @return string 返回微信二维码的存储路径。
     */
    public function wxQrcode(User $user)
    {
        // 生成唯一的二维码文件名，基于用户ID和当前日期，确保每天的二维码文件名不同
        $name = md5('uwx_i' . $user['uid'] . date('Ymd')) . '.jpg';

        // 构建二维码的存储key，基于用户ID，用于后续的查询或删除操作
        $key = 'home_' . $user['uid'];

        // 调用二维码服务类，生成微信二维码并返回其存储路径
        // 这里使用了依赖注入的方式来获取QrcodeService实例
        // 参数包括二维码文件名、二维码指向的URL、是否压缩以及存储的key
        return app()->make(QrcodeService::class)->getWechatQrcodePath($name, rtrim(systemConfig('site_url'), '/') . '?spid=' . $user['uid'], false, $key);
    }

    /**
     * 生成微信小程序二维码
     *
     * 该方法用于生成微信小程序的二维码，特定于某个用户。二维码的名称通过用户的UID、是否为推广员以及当前日期进行哈希处理，以确保唯一性。
     * 生成的二维码链接指向小程序的指定页面，并携带用户UID作为参数，以便于追踪和管理。
     *
     * @param User $user 用户对象，包含用户的UID和是否为推广员的信息。
     * @return string 返回生成的微信小程序二维码的路径。
     */
    public function mpQrcode(User $user)
    {
        // 根据用户信息和当前日期生成唯一的二维码名称
        $name = md5('surt_i' . $user['uid'] . $user['is_promoter'] . date('Ymd')) . '.jpg';

        // 调用QrcodeService服务类，生成指向小程序指定页面的二维码，并携带用户UID作为参数
        return app()->make(QrcodeService::class)->getRoutineQrcodePath($name, 'pages/index/index', 'spid=' . $user['uid']);
    }

    /**
     * 生成微信传播图片
     * 该方法用于生成用户的微信传播图片，包括二维码和宣传海报。
     * @param User $user 用户对象，用于获取用户UID和昵称等信息。
     * @return array 返回生成的宣传海报信息数组。
     */
    public function wxSpreadImage(User $user)
    {
        // 生成二维码文件名
        $name = md5('uwx' . $user['uid'] . $user['is_promoter'] . date('Ymd')) . '.jpg';
        // 获取系统设置的宣传banner信息
        $spreadBanner = systemGroupData('spread_banner');
        // 如果没有设置宣传banner，直接返回空数组
        if (!count($spreadBanner)) return [];
        // 获取网站名称
        $siteName = systemConfig('site_name');
        // 实例化附件仓库
        $attachmentRepository = app()->make(AttachmentRepository::class);
        // 根据文件名获取二维码信息
        $imageInfo = $attachmentRepository->getWhere(['attachment_name' => $name]);
        // 获取网站URL
        $siteUrl = rtrim(systemConfig('site_url'), '/');
        // 获取上传类型
        $uploadType = (int)systemConfig('upload_type') ?: 1;
        $resWap = true;

        // 检查二维码图片是否存在，如果不存在则删除数据库记录并置为空
        // 检测远程文件是否存在
        if (isset($imageInfo['attachment_src']) && strstr($imageInfo['attachment_src'], 'http') !== false && curl_file_exist($imageInfo['attachment_src']) === false) {
            $imageInfo->delete();
            $imageInfo = null;
        }
        // 如果没有二维码信息，则生成二维码
        if (!$imageInfo) {
            // 构造二维码链接
            $codeUrl = $siteUrl . '?spread=' . $user['uid'] . '&spid=' . $user['uid'];//二维码链接
            // 如果开启微信分享，使用微信服务生成永久二维码
            if (systemConfig('open_wechat_share')) {
                $qrcode = OfficialAccount::foreverQrCode('_scan_url_home_' . $user['uid']);
                $codeUrl = OfficialAccount::qrCodeUrl($qrcode['ticket']);
            }
            // 生成二维码图片信息
            $imageInfo = app()->make(QrcodeService::class)->getQRCodePath($codeUrl, $name);
            // 如果生成失败，抛出异常
            if (is_string($imageInfo)) throw new ValidateException('二维码生成失败');

            // 处理二维码图片路径
            $imageInfo['dir'] = tidy_url($imageInfo['dir'], null, $siteUrl);

            // 创建二维码附件记录
            $attachmentRepository->create($uploadType, -1, $user->uid, [
                'attachment_category_id' => 0,
                'attachment_name' => $imageInfo['name'],
                'attachment_src' => $imageInfo['dir']
            ]);
            $urlCode = $imageInfo['dir'];
        } else {
            $urlCode = $imageInfo['attachment_src'];
        }

        // 定义字体文件路径
        $filelink = [
            'Bold' => 'public/font/Alibaba-PuHuiTi-Regular.otf',
            'Normal' => 'public/font/Alibaba-PuHuiTi-Regular.otf',
        ];
        // 检查字体文件是否存在，如果不存在，抛出异常
        if (!file_exists($filelink['Bold'])) throw new ValidateException('缺少字体文件Bold');
        if (!file_exists($filelink['Normal'])) throw new ValidateException('缺少字体文件Normal');

        // 遍历宣传banner，生成海报
        foreach ($spreadBanner as $key => &$item) {
            $posterInfo = '海报生成失败:(';
            // 构建海报生成的配置信息，包括二维码和文字等
            $config = array(
                'image' => array(
                    array(
                        'url' => $urlCode,     //二维码资源
                        'stream' => 0,
                        'left' => 114,
                        'top' => 790,
                        'right' => 0,
                        'bottom' => 0,
                        'width' => 120,
                        'height' => 120,
                        'opacity' => 100
                    )
                ),
                'text' => array(
                    array(
                        'text' => $user['nickname'],
                        'left' => 250,
                        'top' => 840,
                        'fontPath' => $filelink['Bold'],     //字体文件
                        'fontSize' => 16,             //字号
                        'fontColor' => '40,40,40',       //字体颜色
                        'angle' => 0,
                    ),
                    array(
                        'text' => '邀请您加入' . $siteName,
                        'left' => 250,
                        'top' => 880,
                        'fontPath' => $filelink['Normal'],     //字体文件
                        'fontSize' => 16,             //字号
                        'fontColor' => '40,40,40',       //字体颜色
                        'angle' => 0,
                    )
                ),
                'background' => $item['pic']
            );
            // 生成海报，并检查生成结果
            $resWap = $resWap && $posterInfo = setSharePoster($config, 'wap/spread/poster');
            if (!is_array($posterInfo)) throw new ValidateException('海报生成失败');
            // 处理海报路径
            $posterInfo['dir'] = tidy_url($posterInfo['dir'], null, $siteUrl);
            // 创建海报附件记录
            $attachmentRepository->create($uploadType, -1, $user->uid, [
                'attachment_category_id' => 0,
                'attachment_name' => $posterInfo['name'],
                'attachment_src' => $posterInfo['dir']
            ]);
            // 如果生成成功，更新banner信息
            if ($resWap) {
                $item['wap_poster'] = $posterInfo['dir'];
            }
        }
        // 返回生成的宣传海报信息数组
        return $spreadBanner;
    }

    /**
     * 根据用户ID获取用户名
     *
     * 本函数通过用户ID查询数据库，返回对应用户的昵称。
     * 这样做的目的是为了在不暴露用户其他信息的情况下，提供一种标识用户的方式。
     *
     * @param int $uid 用户ID，作为查询条件
     * @return string 返回用户的昵称
     */
    public function getUsername($uid)
    {
        // 使用User类的静态方法getDB来获取数据库连接，并构造查询语句，仅返回nickname字段的值
        return User::getDB()->where('uid', $uid)->value('nickname');
    }

    /**
     * 更新用户佣金排名
     *
     * 本函数用于根据用户ID（$uid）增加或减少用户的佣金，并更新其在周榜和月榜上的排名。
     * $inc参数指定佣金的变化量，$type参数指定操作类型，默认为'+', 表示增加佣金。
     *
     * @param int $uid 用户ID
     * @param float $inc 佣金变化量
     * @param string $type 操作类型，默认为'+', 表示增加佣金；'-'表示减少佣金。
     */
    public function incBrokerage($uid, $inc, $type = '+')
    {
        // 构造月度佣金排行榜的键名
        $moneyKey = 'b_top_' . date('Y-m');
        // 构造周度佣金排行榜的键名
        $weekKey = 'b_top_' . monday();

        // 从周度佣金排行榜中获取用户当前佣金，准备进行更新
        //TODO 佣金周榜
        $brokerage = Cache::zscore($weekKey, $uid);
        // 根据$type的值决定是增加还是减少佣金，并保留两位小数
        $brokerage = $type == '+' ? bcadd($brokerage, $inc, 2) : bcsub($brokerage, $inc, 2);
        // 更新用户在周度佣金排行榜上的佣金及排名
        Cache::zadd($weekKey, $brokerage, $uid);

        // 从月度佣金排行榜中获取用户当前佣金，准备进行更新
        //TODO 佣金月榜
        $brokerage = Cache::zscore($moneyKey, $uid);
        // 根据$type的值决定是增加还是减少佣金，并保留两位小数
        $brokerage = $type == '+' ? bcadd($brokerage, $inc, 2) : bcsub($brokerage, $inc, 2);
        // 更新用户在月度佣金排行榜上的佣金及排名
        Cache::zadd($moneyKey, $brokerage, $uid);
    }


    /**
     * 删除排行榜中的个人排行
     * @param $uid
     * @author Qinii
     * @day 2022/10/18
     */
    public function delBrokerageTop($uid)
    {
        $moneyKey = 'b_top_' . date('Y-m');
        $weekKey = 'b_top_' . monday();
        Cache::zrem($weekKey, $uid);
        Cache::zrem($moneyKey, $uid);
    }

    /**
     * 获取本周经纪人排名列表及用户排名位置
     *
     * 本函数用于查询本周经纪人的排名列表，并结合给定的用户ID，计算该用户在排名中的位置。
     * 主要应用于需要展示经纪人排名竞争情况的场景，例如团队内部排名激励等。
     *
     * @param int $uid 用户ID，用于查询特定用户在排名中的位置。
     * @param int $page 分页参数，指定查询的页码，用于分页查询排名列表。
     * @param int $limit 每页的记录数，用于控制分页查询时每页显示的排名数量。
     * @return array 返回包含本周经纪人排名列表和用户排名位置的数组。
     */
    public function brokerageWeekTop($uid, $page, $limit)
    {
        // 根据当前周一的日期生成缓存键名，用于存储和查询本周的排名列表
        $key = 'b_top_' . monday();

        // 调用topList方法获取本周经纪人的排名列表，并结合userPosition方法获取用户排名位置
        // 返回一个包含排名列表和用户排名位置的数组
        return $this->topList($key, $page, $limit) + ['position' => $this->userPosition($key, $uid)];
    }


    /**
     * 获取当前月份的经纪排行榜，并包含用户在榜上的位置
     *
     * 本函数用于查询当前月份经纪人的排行榜单，并且返回指定用户的排名位置。
     * 通过结合排行榜数据和用户位置信息，为用户提供了一个全面了解当前排名情况的途径。
     *
     * @param int $uid 用户ID，用于查询用户在排行榜上的位置。
     * @param int $page 分页页码，用于获取指定页的排行榜数据。
     * @param int $limit 每页的数据条数，用于控制排行榜数据的分页展示。
     * @return array 返回包含排行榜数据和用户位置信息的数组。
     */
    public function brokerageMonthTop($uid, $page, $limit)
    {
        // 根据当前月份生成排行榜的缓存键名
        $key = 'b_top_' . date('Y-m');

        // 调用topList方法获取当前月份的排行榜数据
        // 并通过userPosition方法获取指定用户在排行榜上的位置信息
        // 最后将排行榜数据和用户位置信息合并返回
        return $this->topList($key, $page, $limit) + ['position' => $this->userPosition($key, $uid)];
    }


    /**
     * //TODO 绑定上下级关系
     * @param User $user
     * @param int $spreadUid
     * @throws DbException
     * @author xaboy
     * @day 2020/6/22
     */
    public function bindSpread(User $user, $spreadUid)
    {
        if ($spreadUid && !$user->spread_uid && $user->uid != $spreadUid && ($spread = $this->dao->get($spreadUid)) && $spread->spread_uid != $user->uid && !$spread->cancel_time) {
            $config = systemConfig(['extension_limit', 'extension_limit_day', 'integral_user_give','integral_status']);
            event('user.spread.before', compact('user', 'spreadUid'));
            Db::transaction(function () use ($spread, $spreadUid, $user, $config) {
                $user->spread_uid = $spreadUid;
                $user->spread_time = date('Y-m-d H:i:s');
                if ($config['extension_limit'] && $config['extension_limit_day']) {
                    $user->spread_limit = date('Y-m-d H:i:s', strtotime('+ ' . $config['extension_limit_day'] . ' day'));
                }
                $spread->spread_count++;
                if ($config['integral_status'] && $config['integral_user_give'] > 0 && $user->isNew) {
                    $integral = (int)$config['integral_user_give'];
                    $spread->integral += $integral;
                    app()->make(UserBillRepository::class)->incBill($spreadUid, 'integral', 'spread', [
                        'link_id' => $user->uid,
                        'status' => 1,
                        'title' => '邀请好友',
                        'number' => $integral,
                        'mark' => '邀请好友奖励' . $integral . '积分',
                        'balance' => $spread->integral
                    ]);
                }
                $spread->save();
                $user->save();
                $redisCacheService = app()->make(RedisCacheService::class);
                //TODO 推广人月榜
                $redisCacheService->zincrby('s_top_' . date('Y-m'), 1, $spreadUid);
                //TODO 推广人周榜
                $redisCacheService->zincrby('s_top_' . monday(), 1, $spreadUid);
            });
            Queue::push(UserBrokerageLevelJob::class, ['uid' => $spreadUid, 'type' => 'spread_user', 'inc' => 1]);
            // 被邀请人获得成长值
            app()->make(UserBrokerageRepository::class)->incMemberValue($user->uid, 'member_share_num', $spreadUid);
            // 邀请人获得成长值
            app()->make(UserBrokerageRepository::class)->incMemberValue($spreadUid, 'member_share_num', $user->uid);
            event('user.spread', compact('user', 'spreadUid'));
        }
    }

    /**
     * 获取用户在排行榜中的位置
     *
     * 本函数通过缓存获取指定用户在排行榜中的位置。排行榜数据以ZSET的形式存储，
     * 使用用户的UID作为成员，用户的分数作为分数值。函数首先尝试通过ZREVRANK命令
     * 获取用户在排行榜中的逆序排名，即从高分到低分的排名。如果用户不存在于排行榜中，
     * 则返回0，表示用户不在排行榜中或排行榜中没有记录。
     *
     * @param string $key 排行榜的键名。排行榜的键名应该唯一标识一个排行榜。
     * @param int $uid 用户的UID。UID应该在应用程序中全局唯一，用于标识用户。
     * @return int 用户在排行榜中的位置。返回0表示用户不在排行榜中。
     */
    public function userPosition($key, $uid)
    {
        // 通过ZREVRANK命令获取用户在排行榜中的逆序排名
        $index = Cache::zrevrank($key, $uid);

        // 如果用户不存在于排行榜中，返回0
        if ($index === false)
            return 0;
        // 如果用户存在于排行榜中，返回用户的排名（逆序排名加1）
        else
            return $index + 1;
    }

    /**
     * 获取排行榜列表
     *
     * 本函数用于根据给定的排行榜键名、页码和每页数量，从缓存中获取指定页的排行榜列表。
     * 排行榜数据以用户的UID、头像、昵称为基础，并包括每个用户在排行榜上的分数。
     *
     * @param string $key 排行榜的键名。
     * @param int $page 当前页码。
     * @param int $limit 每页的数量。
     * @return array 返回包含排行榜数据总数和排行榜列表的数组。
     */
    public function topList($key, $page, $limit)
    {
        // 从缓存中获取指定页的排行榜数据，包括用户UID和分数，并按分数降序排列
        $res = Cache::zrevrange($key, ($page - 1) * $limit, $limit, true);
        // 提取排行榜数据中的用户UID作为数组键
        $ids = array_keys($res);
        // 获取排行榜中总的数据数量
        $count = Cache::zcard($key);
        // 根据用户UID查询用户详细信息，包括UID、头像和昵称，并转换为数组格式
        $list = count($ids) ? $this->dao->users($ids, 'uid,avatar,nickname')->toArray() : [];
        // 为每个用户添加其在排行榜上的分数
        foreach ($list as $k => $v) {
            $list[$k]['count'] = $res[$v['uid']] ?? 0;
        }
        // 提取每个用户在排行榜上的分数作为数组，用于后续的排序
        $sort = array_column($list, 'count');
        // 根据用户分数降序排列用户列表
        array_multisort($sort, SORT_DESC, $list);
        // 返回包含排行榜数据总数和排序后的用户列表的数组
        return compact('count', 'list');
    }

    /**
     * 获取本周推广排行榜
     *
     * 本函数用于获取本周的推广排行榜列表，排行榜数据基于特定的键名进行检索。
     * 键名由's_top_'前缀和当前星期一的日期组成，确保每周的排行榜数据独立。
     * 使用分页和限制来控制返回的数据量，提高数据检索的效率和灵活性。
     *
     * @param int $page 排行榜的页码，用于分页查询。
     * @param int $limit 每页的数据条数限制，控制返回的数据量。
     * @return mixed 返回调用topList方法的结果，具体类型取决于topList的实现。
     */
    public function spreadWeekTop($page, $limit)
    {
        // 构建本周推广排行榜的键名，以's_top_'开头，加上当前星期一的日期。
        $key = 's_top_' . monday();
        // 调用topList方法获取排行榜数据，传入键名、页码和数据条数限制。
        return $this->topList($key, $page, $limit);
    }


    /**
     * 获取当前月份的传播榜数据
     *
     * 本函数用于获取当前月份内在平台上的传播榜数据，榜单数据根据用户的某种传播指标进行排序。
     * 主要用于展示或统计当前月份内用户或内容的传播情况。
     *
     * @param int $page 榜单的页码，用于分页查询。
     * @param int $limit 每页的数据条数，用于控制返回的数据量。
     * @return mixed 返回查询结果，具体格式根据底层实现而定。
     */
    public function spreadMonthTop($page, $limit)
    {
        // 根据当前月份生成缓存键名，确保每个月份的传播榜数据独立
        $key = 's_top_' . date('Y-m');
        // 调用底层的topList方法，获取指定缓存键名的榜单数据
        return $this->topList($key, $page, $limit);
    }

    /**
     * 获取指定用户推荐的一级列表
     *
     * 本函数用于查询指定用户（$uid）推广的一级用户列表。它支持分页和条件查询，
     * 主要用于展示或统计指定用户的一级推广成果。
     *
     * @param int $uid 推广用户的ID
     * @param array $where 查询条件，用于进一步筛选推广用户
     * @param int $page 当前页码，用于分页查询
     * @param int $limit 每页显示的记录数，用于分页查询
     * @return array 返回包含用户列表和总数的数组
     */
    public function getOneLevelList($uid, $where, $page, $limit)
    {
        // 将指定的推广用户ID设置为查询条件
        $where['spread_uid'] = $uid;

        // 根据条件查询推广用户数据
        $query = $this->search($where);

        // 统计满足条件的推广用户总数
        $count = $query->count();

        // 设置查询字段，并进行分页查询，返回推广用户的具体信息
        $list = $query->setOption('field', [])->field('uid,avatar,nickname,pay_count,pay_price,spread_count,spread_time')->page($page, $limit)->select();

        // 返回包含用户列表和总数的数组
        return compact('list', 'count');
    }

    /**
     * 获取二级列表数据
     * 本函数用于根据给定的条件获取特定用户的二级列表数据，包括用户ID、头像、昵称、支付次数、支付金额、推广次数和推广时间等信息。
     *
     * @param int $uid 用户ID，用于获取该用户的下级用户数据。
     * @param array $where 查询条件，用于进一步筛选数据。
     * @param int $page 当前页码，用于分页查询。
     * @param int $limit 每页数据条数，用于分页查询。
     *
     * @return array 返回包含列表数据和总数的数组。
     */
    public function getTwoLevelList($uid, $where, $page, $limit)
    {
        // 获取指定用户的下级用户ID列表
        $where['spread_uids'] = $this->dao->getSubIds($uid);

        // 如果下级用户ID列表不为空
        if (count($where['spread_uids'])) {
            // 根据条件查询数据
            $query = $this->search($where);
            // 计算满足条件的数据总数
            $count = $query->count();
            // 获取满足条件的列表数据，指定查询字段，并进行分页
            $list = $query->setOption('field', [])->field('uid,avatar,nickname,pay_count,pay_price,spread_count,spread_time')->page($page, $limit)->select();
        } else {
            // 如果下级用户ID列表为空，则列表数据为空数组，总数为0
            $list = [];
            $count = 0;
        }

        // 返回列表数据和总数的数组
        return compact('list', 'count');
    }

    /**
     * 获取用户等级列表
     * 根据给定的条件和分页信息，查询用户等级列表。条件包括用户ID和传播者ID等。
     * 主要用于统计和展示用户等级分布情况。
     *
     * @param int $uid 用户ID，用于查询用户的下属或直接传播者。
     * @param array $where 查询条件数组，包含level等条件。
     * @param int $page 当前页码，用于分页查询。
     * @param int $limit 每页数据条数，用于分页查询。
     * @return array 返回包含用户等级列表和总数的数组。
     */
    public function getLevelList($uid, array $where, $page, $limit)
    {
        // 如果查询条件中没有指定等级，则查询该用户的所有下属用户ID
        if (!$where['level']) {
            $ids = $this->dao->getSubIds($uid);
            $ids[] = $uid; // 将当前用户ID也加入查询列表
            $where['spread_uids'] = $ids; // 更新查询条件为下属用户ID列表
        } elseif ($where['level'] == 2) { // 如果查询条件指定为二级传播者，则只查询直接下属的用户ID
            $ids = $this->dao->getSubIds($uid);
            if (!count($ids)) return ['list' => [], 'count' => 0]; // 如果没有二级传播者，则直接返回空结果
            $where['spread_uids'] = $ids; // 更新查询条件为二级传播者ID列表
        } else { // 如果查询条件指定了具体等级，则直接查询该用户的传播者ID
            $where['spread_uid'] = $uid;
        }

        // 根据更新后的查询条件进行查询
        $query = $this->search($where);
        $count = $query->count(); // 计算满足条件的总记录数
        $list = $query->setOption('field', [])->field('uid,phone,avatar,nickname,is_promoter,pay_count,pay_price,spread_count,create_time,spread_time,spread_limit')->page($page, $limit)->select(); // 查询满足条件的用户列表，指定返回字段，并进行分页

        return compact('list', 'count'); // 返回用户列表和总数的数组
    }

    /**
     * 查询下级订单
     * 根据用户ID($uid)和指定条件($where)，分页查询下级用户的订单信息。
     * 如果指定了级别($where['level'])，则根据级别查询不同范围的下级用户订单。
     * $page 和 $limit 用于分页。
     *
     * @param int $uid 用户ID
     * @param int $page 分页页码
     * @param int $limit 分页限制条数
     * @param array $where 查询条件
     * @return array 返回包含订单总数和订单列表的数组
     */
    public function subOrder($uid, $page, $limit, array $where = [])
    {
        $field = 'spread_uids';
        if (isset($where['level']) && $where['level'] !== '' && $where['level'] != -1) {
            if ($where['level'] == 1) {
                $field = 'spread_uid';
            } else if ($where['level'] == 2) {
                $field = 'top_uid';
            }
        }
        $ids[$field] = $uid;
        $storeOrderRepository = app()->make(StoreOrderRepository::class);
        $query = $storeOrderRepository->usersOrderQuery($where, $ids, (!isset($where['level']) || !$where['level'] || $where['level'] == -1) ? $uid : 0);
        $count = $query->count();
        $list = $query->page($page, $limit)->field('uid,order_sn,pay_time,extension_one,extension_two,is_selfbuy,order_id,spread_uid,top_uid,status')->with([
            'user' => function ($query) {
                $query->field('avatar,nickname,uid');
            },
            'orderProduct',
        ])->select();
        foreach ($list as $k => $item) {
            if (($item['is_selfbuy'] && $uid == $item['uid']) || $item['spread_uid'] == $uid) {
                $list[$k]['brokerage'] = $item['extension_one'];
            } else {
                $list[$k]['brokerage'] = $item['extension_two'];
            }
            unset($list[$k]['extension_one'], $list[$k]['extension_two']);
        }
        return compact('count', 'list');
    }

    /**
     * 获取主要用户信息
     *
     * 本函数用于处理用户主账号的逻辑。如果当前用户是主账号，则直接返回该用户信息；
     * 否则，尝试获取主账号的信息，并在某些条件下更新主账号的微信用户ID。
     *
     * @param User $user 当前操作的用户对象
     * @return User|array|Model
     */
    public function mainUser(User $user)
    {
        // 检查用户是否为主账号，或用户ID与主账号ID相同，如果是则直接返回该用户
        if (!$user->main_uid || $user->uid == $user->main_uid) return $user;

        // 尝试获取主账号信息
        $switchUser = $this->dao->get($user->main_uid);

        // 如果主账号信息获取失败，则返回当前用户
        if (!$switchUser) return $user;

        // 如果当前用户有微信用户ID，而主账号没有，则将微信用户ID同步到主账号，并保存更新
        if ($user->wechat_user_id && !$switchUser->wechat_user_id) {
            $switchUser->wechat_user_id = $user->wechat_user_id;
            $switchUser->save();
        }

        // 返回主账号信息
        return $switchUser;
    }

    /**
     * 切换用户身份
     *
     * 本函数用于实现当前用户与另一用户之间的身份切换。这涉及到两个用户的uid之间的主从关系变更。
     * 如果尝试切换到的用户与当前用户相同，或目标用户不存在，将抛出验证异常。
     * 同时，如果目标用户未绑定微信用户ID，会将其绑定到当前用户的微信用户ID。
     *
     * @param User $user 当前用户对象
     * @param int $uid 要切换到的用户的ID
     * @return User 切换后的用户对象
     * @throws ValidateException 如果操作失败，抛出验证异常
     */
    public function switchUser(User $user, $uid)
    {
        // 检查是否尝试切换到自己，或目标用户不存在，抛出异常
        if ($user->uid == $uid || !$this->dao->existsWhere(['uid' => $uid, 'phone' => $user->phone]))
            throw new ValidateException('操作失败');

        // 更新当前用户为主用户ID
        $this->dao->update($user->uid, ['main_uid' => $uid]);
        // 更新搜索索引中的主用户ID
        $this->dao->getSearch([])->where('main_uid', $user->uid)->update(['main_uid' => $uid]);

        // 获取目标用户对象
        $switchUser = $this->dao->get($uid);
        // 如果目标用户未绑定微信用户ID，将其绑定到当前用户的微信用户ID
        if (!$switchUser->wechat_user_id) {
            $switchUser->wechat_user_id = $user->wechat_user_id;
            $switchUser->save();
        }

        // 返回切换后的用户对象
        return $switchUser;
    }

    /**
     * 生成用户令牌并返回相关信息。
     *
     * 本函数用于在用户验证成功后，生成一个令牌（token）并返回包含令牌及相关信息的数组。
     * 主要用于身份验证和会话管理，令牌包含用户的部分信息和过期时间。
     *
     * @param object $user 用户对象，包含用户详细信息。
     * @param array $tokenInfo 令牌信息数组，包含令牌字符串和过期时间。
     * @throws ValidateException 如果用户状态为禁用，则抛出异常。
     * @return array 返回包含令牌、过期时间及用户信息的数组。
     */
    public function returnToken($user, $tokenInfo)
    {
        // 检查用户状态，如果用户被禁用，则抛出异常。
        if (!$user->status) {
            throw new ValidateException('账号已被禁用');
        }

        // 隐藏用户对象中的一些敏感或不需要的信息，然后转换为数组。
        $user = $user->hidden(['label_id', 'group_id', 'main_uid', 'pwd', 'addres', 'card_id', 'last_time', 'last_ip', 'create_time', 'mark', 'status', 'spread_uid', 'spread_time', 'real_name', 'birthday', 'brokerage_price'])->toArray();

        // 构建并返回包含令牌、过期时间和用户信息的数组。
        return [
            'token' => $tokenInfo['token'], // 令牌字符串
            'exp' => $tokenInfo['out'], // 令牌过期描述
            'expires_time' => strtotime('+ ' . $tokenInfo['out'] . 'seconds'), // 令牌实际的过期时间戳
            'user' => $user // 用户信息数组
        ];
    }

    /**
     * 切换用户佣金到余额
     *
     * 该方法用于将用户的佣金转移到余额中。它通过调整用户的now_money和brokerage_price字段的值来实现此目的。
     * 在这个过程中，它还记录了两次交易，一次是佣金增加到余额，另一次是佣金从佣金余额减少。
     * 这个过程是在一个数据库事务中完成的，以确保数据的一致性。
     *
     * @param User $user 用户对象，表示进行佣金转移的用户
     * @param float $brokerage 转移的佣金金额
     */
    public function switchBrokerage(User $user, $brokerage)
    {
        // 将佣金金额加到用户的现金额上
        $user->now_money = bcadd($user->now_money, $brokerage, 2);
        // 从用户的佣金价格中减去佣金金额
        $user->brokerage_price = bcsub($user->brokerage_price, $brokerage, 2);

        // 开始一个数据库事务，以确保接下来的操作要么全部成功，要么全部失败
        Db::transaction(function () use ($brokerage, $user) {
            // 保存用户对象的更改，更新数据库中的用户记录
            $user->save();

            // 创建用户账单记录，佣金增加到余额
            app()->make(UserBillRepository::class)->incBill($user->uid, 'now_money', 'brokerage', [
                'link_id' => 0,
                'status' => 1,
                'title' => '佣金转入余额',
                'number' => $brokerage,
                'mark' => '成功转入余额' . floatval($brokerage) . '元',
                'balance' => $user->now_money
            ]);

            // 创建用户账单记录，佣金从佣金余额减少
            app()->make(UserBillRepository::class)->decBill($user->uid, 'brokerage', 'now_money', [
                'link_id' => 0,
                'status' => 1,
                'title' => '佣金转入余额',
                'number' => $brokerage,
                'mark' => '成功转入余额' . floatval($brokerage) . '元',
                'balance' => $user->brokerage_price
            ]);
        });
    }

    /**
     * 移除文档中的指定标签
     *
     * 本函数用于从文档的标签列表中移除一个特定的标签。它通过搜索具有给定标签ID的文档，
     * 然后更新文档的标签ID字段，从现有的标签串中移除目标标签。
     * 标签是用逗号分隔的字符串，更新时会确保字符串两端没有多余的逗号。
     *
     * @param int $id 要移除的标签ID
     * @return bool 更新操作的结果，true表示成功，false表示失败
     */
    public function rmLabel($id)
    {
        // 构造SQL语句，用于从文档的标签列表中移除指定的标签ID
        // 使用Db::raw处理SQL中的原始字符串，确保正确的字符串拼接和函数调用
        return $this->search(['label_id' => $id])->update([
            'label_id' => Db::raw('trim(BOTH \',\' FROM replace(CONCAT(\',\',label_id,\',\'),\',' . $id . ',\',\',\'))')
        ]);
    }


    /**
     * 修改推荐人信息表单
     *
     * 该方法用于生成一个修改用户推荐人信息的表单。它首先根据用户ID获取用户信息，
     * 然后基于这些信息创建一个表单，表单中包含用户的昵称、推荐人的ID和昵称，
     * 以及推荐人的头像。用户可以通过这个表单来更改他们的推荐人信息。
     *
     * @param int $id 用户ID
     * @return \think\form\Form 创建的表单对象
     */
    public function changeSpreadForm($id)
    {
        // 根据用户ID获取用户对象
        $user = $this->dao->get($id);

        // 创建表单，并设置表单的提交URL
        $form = Elm::createForm(Route::buildUrl('systemUserSpreadChange', compact('id'))->build());

        // 设置表单的验证规则
        $form->setRule([
            // 显示用户昵称
            [
                'type' => 'span',
                'title' => '用户昵称：',
                'native' => false,
                'children' => [$user->nickname]
            ],
            // 显示上级推荐人ID
            [
                'type' => 'span',
                'title' => '上级推荐人 Id：',
                'native' => false,
                'children' => [$user->spread ? (string)$user->spread->uid : '无']
            ],
            // 显示上级推荐人昵称
            [
                'type' => 'span',
                'title' => '上级推荐人昵称：',
                'native' => false,
                'children' => [$user->spread ? (string)$user->spread->nickname : '无']
            ],
            // 显示上级推荐人的头像，并提供修改功能
            Elm::frameImage('spid', '上级推荐人：', '/' . config('admin.admin_prefix') . '/setting/referrerList?field=spid')
                ->prop('srcKey', 'src')
                ->value($user->spread ? [
                    'src' => $user->spread->avatar,
                    'id' => $user->spread->uid,
                ] : [])
                ->icon('el-icon-camera')
                ->modal(['modal' => false])
                ->width('1000px')
                ->height('600px'),
        ]);

        // 设置表单标题
        return $form->setTitle('修改推荐人');
    }


    /**
     * 修改用户的推广ID
     *
     * 本函数用于处理用户推广关系的变更。当用户的新推广ID与当前的不同时，
     * 会更新用户的推广ID及相关信息，并在推广日志中记录这一变更。
     * 同时，根据配置，更新用户的推广限制时间。
     *
     * @param int $uid 用户ID
     * @param int $spread_id 新的推广ID
     * @param int $admin 是否为管理员操作，默认为0表示非管理员
     * @return void
     */
    public function changeSpread($uid, $spread_id, $admin = 0)
    {
        // 实例化推广日志仓库
        $spreadLogRepository = app()->make(UserSpreadLogRepository::class);
        // 获取用户对象
        $user = $this->dao->get($uid);
        // 如果用户当前的推广ID与新ID相同，则不进行处理
        if ($user->spread_uid == $spread_id)
            return;
        // 获取系统配置，包括推广限制及限制天数
        $config = systemConfig(['extension_limit', 'extension_limit_day']);
        // 开启数据库事务处理
        Db::transaction(function () use ($config, $user, $spreadLogRepository, $spread_id, $admin) {
            // 保存旧的推广ID，用于后续操作
            $old = $user->spread_uid ?: 0;
            // 添加推广日志
            $spreadLogRepository->add($user->uid, $spread_id, $old, $admin);
            // 根据新的推广ID，更新用户的推广时间
            $user->spread_time = $spread_id ? date('Y-m-d H:i:s') : null;
            // 根据配置，更新用户的推广限制时间
            if ($spread_id && $config['extension_limit'] && $config['extension_limit_day']) {
                $user->spread_limit = date('Y-m-d H:i:s', strtotime('+ ' . $config['extension_limit_day'] . ' day'));
            } else {
                $user->spread_limit = null;
            }
            // 更新用户的推广ID
            $user->spread_uid = $spread_id;
            // 如果新的推广ID不为空，则增加对应推广ID的推广计数
            if ($spread_id) {
                $this->dao->incSpreadCount($spread_id);
            }
            // 如果旧的推广ID不为空，则减少对应推广ID的推广计数
            if ($old) {
                $this->dao->decSpreadCount($old);
            }
            // 保存用户对象，完成更新
            $user->save();
        });
    }


    /**
     * 同步推广状态
     *
     * 本函数用于同步推广的狀態。它首先检查系统配置，看是否限制了扩展功能。
     * 如果没有限制，它将调用数据访问对象（DAO）来执行实际的同步操作。
     * 这个函数的存在是为了在需要时更新推广状态，确保系统的推广数据与实际状态保持一致。
     *
     * @return void
     */
    public function syncSpreadStatus()
    {
        // 检查系统配置，如果允许扩展功能，则同步推广状态
        if (systemConfig('extension_limit')) {
            $this->dao->syncSpreadStatus();
        }
    }

    /**
     * 积分增加
     * @param int $uid
     * @param int $number
     * @param $title
     * @param $type
     * @param $data
     * @author Qinii
     * @day 6/9/21
     */
    public function incIntegral(int $uid, int $number, $title, $type, $data)
    {
        Db::transaction(function () use ($uid, $number, $title, $type, $data) {

            $user = $this->dao->get($uid);
            $user->integral = $user->integral + $number;
            $user->save();

            app()->make(UserBillRepository::class)
                ->incBill($uid, 'integral', $type,
                    [
                        'link_id' => 0,
                        'status' => 1,
                        'title' => $title,
                        'number' => $data['number'],
                        'mark' => $data['mark'],
                        'balance' => $data['balance'],
                    ]);
        });
    }


    /**
     * 根据用户ID和类型生成会员或分销商的表单
     *
     * @param int $id 用户ID
     * @param int $type 类型标记，1代表会员等级，0代表分销等级
     * @return Form 表单实例
     *
     * 此方法根据传入的用户ID和类型，构造相应的表单用于编辑会员等级或分销商等级。
     * 如果类型为1，则构建会员等级表单；否则构建分销商等级表单。表单提交地址通过路由生成，
     * 并根据类型动态获取相应的级别选项。如果用户不是分销商且类型为0，则抛出异常提示数据不存在。
     */
    public function memberForm(int $id, int $type)
    {
        // 根据类型决定创建会员等级表单还是分销商等级表单
        if ($type) {
            $form = Elm::createForm(Route::buildUrl('systemUserMemberSave', ['id' => $id])->build());
            $field = 'member_level';
        } else {
            $form = Elm::createForm(Route::buildUrl('systemUserSpreadSave', ['id' => $id])->build());
            $field = 'brokerage_level';
        }

        // 根据用户ID获取用户数据
        $data = $this->dao->get($id);
        // 如果用户数据不存在，则抛出异常
        if (!$data) throw new ValidateException('数据不存在');

        // 获取用户的扩展信息，包括是否可以分销
        $extensionInfo = get_extension_info($data);//获取用户是否可以分销以及是否内购
        // 如果用户不是分销商且类型为0，则抛出异常
        if (!$type && !$extensionInfo['isPromoter']) throw new ValidateException('用户不是分销员');

        // 根据类型动态生成级别选择项，并设置表单验证规则
        $rules = [
            Elm::select($field, '级别：', $data->$field ?: '')
                ->options(function () use ($type) {
                    $options = app()->make(UserBrokerageRepository::class)->options(['type' => $type])->toArray();
                    // $options = array_merge([['value' => 0 , 'label' => '请选择']],$options);
                    $options = array_merge([],$options);
                    return $options;
                })->placeholder('请选择级别')->col(Elm::col()->labelWidth('52px'))
        ];
        $form->setRule($rules);

        // 根据类型设置表单标题
        return $form->setTitle($type ? '编辑会员等级' : '编辑分销等级');
    }

    /**
     * 更新用户级别
     *
     * 根据提供的用户ID和类型，更新用户的会员级别或代理级别。如果新级别与旧级别相同，则不进行更新。
     * 在更新过程中，如果新级别不存在，则抛出验证异常。更新操作在数据库事务中完成，确保数据的一致性。
     * 对于会员级别更新，还会清零用户的成长值，并记录相关操作日志。
     *
     * @param int $id 用户ID
     * @param array $data 包含新级别数据的数组
     * @param int $type 类型标志，1表示更新会员级别，0表示更新代理级别
     * @throws ValidateException 如果新级别不存在，则抛出此异常
     * @return bool 如果级别未更新则返回true，否则返回false
     */
    public function updateLevel(int $id, array $data, int $type, $return = true)
    {
        // 创建用户经纪公司仓库实例
        $make = app()->make(UserBrokerageRepository::class);
        // 根据用户ID获取用户信息
        $user = $this->dao->get($id);
        // 根据类型确定字段名
        $field = $type ? 'member_level' : 'brokerage_level';
        // 如果新旧级别相同，则不更新并返回true
        if ($data[$field] == $user->$field) return true;
        // 检查新级别是否存在
        $bro = $make->getWhere(['brokerage_level' => $data[$field], 'type' => $type]);
        if (!$bro && $return) {
            throw new ValidateException('等级不存在');
        }
        // 使用数据库事务处理更新操作
        Db::transaction(function () use ($id, $data, $field, $user, $type, $bro) {
            // 更新用户级别
            $user->$field = $data[$field];
            $bro->user_num++;
            $bro->save();
            // 如果是更新会员级别
            if ($type) {
                // 清零用户的成长值，并记录相关操作日志
                app()->make(UserBillRepository::class)->decBill($user->uid, 'sys_members', 'platform_clearing', [
                    'number' => $user->member_value,
                    'title' => '平台修改等级',
                    'balance' => 0,
                    'status' => 0,
                    'mark' => '平台修改等级清除成长值' . ':' . $user->member_value,
                ]);
                $user->member->user_num--;
                $user->member->save();

                $user->member_value = 0;
            } else {
                $user->brokerage->user_num--;
                $user->brokerage->save();

            }
            // 保存用户更新
            $user->save();
            // 如果是更新代理级别，删除相关的代理佣金记录
            if ($type == 0) app()->make(UserBillRepository::class)->search(['category' => 'sys_brokerage'])->where('uid', $id)->delete();
        });
    }

    /**
     * 取消用户的注册状态，将用户相关信息做删除或重置处理。
     *
     * @param User $user 需要取消注册状态的用户对象。
     */
    public function cancel(User $user)
    {
        // 开启数据库事务处理，确保一系列操作的原子性。
        Db::transaction(function () use ($user) {
            $uid = $user->uid;
            $name = '已注销用户' . substr(uniqid(true, true), -6);

            // 如果用户绑定了微信信息，更新微信信息，清除相关数据。
            if ($user->wechat) {
                $user->wechat->save([
                    'unionid' => '',
                    'openid' => '',
                    'routine_openid' => '',
                    'nickname' => $name,
                    'headimgurl' => '',
                    'city' => '',
                    'province' => '',
                    'country' => '',
                ]);
            }

            // 更新用户基本信息，重置为默认值或清除。
            $user->save([
                'account' => '',
                'real_name' => '',
                'nickname' => $name,
                'avatar' => '',
                'phone' => '',
                'address' => '',
                'card_id' => '',
                'main_uid' => 0,
                'label_id' => '',
                'group_id' => 0,
                'spread_uid' => 0,
                'status' => 0,
                'is_promoter' => 0,
                'wechat_user_id' => 0,
                'cancel_time' => date('Y-m-d H:i:s')
            ]);

            // 更新搜索引擎中的用户数据，解除主账号关联。
            $this->getSearch([])->where('main_uid', $uid)->update(['main_uid' => 0]);

            // 删除用户的收货地址信息。
            app()->make(UserAddressRepository::class)->getSearch([])->where('uid', $uid)->delete();

            // 删除用户的商家信息。
            app()->make(UserMerchantRepository::class)->getSearch([])->where('uid', $uid)->delete();

            // 删除用户的发票信息。
            app()->make(UserReceiptRepository::class)->getSearch([])->where('uid', $uid)->delete();

            // 更新店铺服务信息，解除用户关联，并设置为关闭状态。
            app()->make(StoreServiceRepository::class)->getSearch([])->where('uid', $uid)->update(['uid' => 0, 'status' => 0, 'is_open' => 0]);

            // 更新所有传播者信息，解除与被取消用户的关系。
            $this->getSearch([])->where('spread_uid', $uid)->update(['spread_uid' => 0]);

            // 删除用户的推广佣金记录。
            $this->delBrokerageTop($uid);

            // 从推广人月榜中移除用户。
            //TODO 推广人月榜
            Cache::zrem('s_top_' . date('Y-m'), $uid);

            // 从推广人周榜中移除用户。
            //TODO 推广人周榜
            Cache::zrem('s_top_' . monday(), $uid);

            // 删除用户在社区中的相关信息。
            app()->make(CommunityRepository::class)->destoryByUid($uid);
        });
    }

    /**
     * 创建SVIP表单
     * 该方法用于生成编辑付费会员期限的表单。根据会员的状态和类型，动态生成表单字段，允许用户修改会员的付费状态和期限。
     *
     * @param int $id 用户ID，用于获取用户当前的会员信息。
     * @return \think\form\Form 创建的表单对象。
     * @throws ValidateException 如果用户数据不存在，则抛出验证异常。
     */
    public function svipForm(int $id)
    {
        // 通过用户ID获取用户数据
        $formData = $this->dao->get($id);
        // 如果用户数据不存在，则抛出异常
        if (!$formData) throw new ValidateException('数据不存在');

        // 创建表单对象，并设置表单提交的URL
        $form = Elm::createForm(Route::buildUrl('systemUserSvipUpdate', ['id' => $id])->build());

        // 根据用户当前是否是SVIP，动态生成表单规则
        $rule = [
            // 生成开关控件，用于切换用户是否是SVIP的状态
            Elm::switches('is_svip', '付费会员：', $formData->is_svip > 0 ? 1 : 0)->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->className('switch-width-double')->activeText('开启'),
        ];

        // 如果用户是永久会员，则显示会员类型的输入框，并设置为只读
        if ($formData->is_svip == 3) {
            $rule[] = Elm::input('is_svip_type', '会员类型：', '永久会员')->disabled(true)->appendRule('suffix', [
                'type' => 'div',
                'style' => ['color' => '#999999'],
                'domProps' => [
                    'innerHTML' => '永久会员，若关闭后再次开启将不再是永久会员，请谨慎操作',
                ]
            ])->placeholder('请输入会员类型');
        } else {
            // 如果用户不是永久会员，则显示修改类型的单选框和会员期限的输入框
            $rule[] = Elm::radio('type', '修改类型：', 1)->options([
                ['label' => '增加', 'value' => 1],
                ['label' => '减少', 'value' => 0],
            ])->requiredNum('请选择修改类型');
            $rule[] = Elm::number('add_time', '会员期限（天）：')->required('请输入会员期限（天）')->min(1);
            // 显示当前会员有效期的输入框，并设置为只读
            $rule[] = Elm::input('end_time', '当前有效期期限：', $formData->is_svip > 0 ? $formData->svip_endtime : 0)->placeholder('请输入当前有效期期限')->disabled(true);
        }

        // 设置表单的规则
        $form->setRule($rule);
        // 设置表单的标题
        return $form->setTitle('编辑付费会员期限');
    }

    /**
     * 设置付费会员
     * @param $id
     * @param $data
     * @author Qinii
     * @day 2022/11/22
     */
    public function svipUpdate($id, $data, $adminId)
    {
        $user = app()->make(UserRepository::class)->get($id);
        if (!$user) throw new ValidateException('用户不存在');
        if ($user['is_svip'] < 1 && ($data['is_svip'] == 0 || !$data['type']))
            throw new ValidateException('该用户还不是付费会员');
        if ($user['is_svip'] == 3 && $data['is_svip'] == 1)
            throw new ValidateException('该用户已是永久付费会员');
        if ($data['is_svip']) {
            $day = ($data['type'] == 1 ? '+ ' : '- ') . (int)$data['add_time'];
            $endtime = ($user['svip_endtime'] && $user['is_svip'] != 0) ? $user['svip_endtime'] : date('Y-m-d H:i:s', time());
            $is_svip = 1;
            $svip_endtime = date('Y-m-d H:i:s', strtotime("$endtime  $day day"));
            //结束时间小于当前 就关闭付费会员
            if (strtotime($svip_endtime) <= time()) {
                $is_svip = 0;
            }
        } else {
            $is_svip = 0;
            $svip_endtime = date('Y-m-d H:i:s', time());
        }
        $make = app()->make(UserOrderRepository::class);
        $res = [
            'title' => $data['is_svip'] == 0 ? '平台取消会员资格' : ($data['type'] ? '平台赠送' : '平台扣除'),
            'link_id' => 0,
            'order_sn' => app()->make(StoreOrderRepository::class)->getNewOrderId(StoreOrderRepository::TYPE_SN_USER_ORDER),
            'pay_price' => 0,
            'order_info' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'uid' => $id,
            'order_type' => UserOrderRepository::TYPE_SVIP . $is_svip,
            'pay_type' => 'sys',
            'status' => 1,
            'pay_time' => date('Y-m-d H:i:s', time()),
            'admin_id' => $adminId,
            'end_time' => $svip_endtime,
            'other' => $user->is_svip == -1 ? 'first' : '',
        ];

        Db::transaction(function () use ($user, $res, $is_svip, $svip_endtime, $make) {
            $make->create($res);
            $user->is_svip = $is_svip;
            $user->svip_endtime = $svip_endtime;
            $user->save();
        });
    }

    /**
     * 更新用户基本信息
     *
     * 本函数用于在数据库中更新用户的昵称和头像信息。如果用户已绑定了微信账号，
     * 则同时更新微信账号的昵称和头像信息。更新操作在事务中执行，确保数据的一致性。
     *
     * @param array $data 包含用户新昵称和头像信息的数据数组。
     * @param User $user 用户对象，表示需要更新信息的用户。
     */
    public function updateBaseInfo($data, $user)
    {
        // 开启数据库事务处理
        Db::transaction(function () use ($data, $user) {
            // 过滤并更新用户的基本信息，包括昵称和头像
            $user->save(array_filter([
                'nickname' => $data['nickname'] ?? '',
                'avatar' => $data['avatar'] ?? '',
            ]));

            // 如果用户已绑定微信账号，同样更新微信账号的昵称和头像信息
            if (isset($user->wechat)) {
                $user->wechat->save(array_filter([
                    'nickname' => $data['nickname'] ?? '',
                    'headimgurl' => $data['avatar'] ?? '',
                ]));
            }
        });
    }


    /**
     * 获取默认删除用户信息
     * @param array $user_info
     * @param array $append_info
     * @return array
     *
     * @date 2023/09/07
     * @author yyw
     */
    public function getDelUserInfo(array $user_info = [], array $append_info = [])
    {
        return [
                'nickname' => $user_info['nickname'] ?? '用户已被删除',
                'phone' => $user_info['phone'] ?? '00000000000',
                'uid' => $user_info['uid'] ?? '-1'
            ] + $append_info;
    }


    /**
     * 获取用户等级下拉列表
     * @return array
     */
    public function getMemberLevelSelectList()
    {
        return app()->make(UserBrokerageRepository::class)->options(['type' => 1])->toArray();
    }

    /**
     * 搜索社区用户列表
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     *
     * @date 2023/10/21
     * @author yyw
     */
    public function getCommunityUserList(array $where = [], int $page = 1, int $limit = 10)
    {
        $query = $this->dao->search($where)
            ->field('uid,nickname,real_name,avatar,sex,birthday,phone,label_id,count_start,count_fans,count_content');

        if (!empty($where['age_min']) || !empty($where['age_max'])) {
            $ageMin = max(0, intval($where['age_min'] ?? 0));
            $ageMax = max(0, intval($where['age_max'] ?? 0));
            $birthdayStart = $ageMax > 0 ? date('Y-m-d', strtotime('-' . $ageMax . ' years')) : null;
            $birthdayEnd = $ageMin > 0 ? date('Y-m-d', strtotime('-' . $ageMin . ' years')) : null;

            // 兼容 user_profile.birth_month（YYYY-MM），并允许无生日用户不被年龄条件直接剔除
            $profileUids = [];
            if ($birthdayStart || $birthdayEnd) {
                $profileQuery = \think\facade\Db::name('user_profile')->where('birth_month', '<>', '');
                if ($birthdayStart) {
                    $profileQuery->where('birth_month', '>=', substr($birthdayStart, 0, 7));
                }
                if ($birthdayEnd) {
                    $profileQuery->where('birth_month', '<=', substr($birthdayEnd, 0, 7));
                }
                $profileUids = $profileQuery->column('uid');
            }

            $query->where(function ($q) use ($birthdayStart, $birthdayEnd, $profileUids) {
                // 1) 无生日：先放行（资料未完善）
                $q->where(function ($q2) {
                    $q2->whereNull('birthday')
                        ->whereOr('birthday', '')
                        ->whereOr('birthday', '0000-00-00');
                });
                // 2) 有 birthday 且落在区间
                $q->whereOr(function ($q2) use ($birthdayStart, $birthdayEnd) {
                    $q2->whereNotNull('birthday')->where('birthday', '<>', '')->where('birthday', '<>', '0000-00-00');
                    if ($birthdayStart) {
                        $q2->where('birthday', '>=', $birthdayStart);
                    }
                    if ($birthdayEnd) {
                        $q2->where('birthday', '<=', $birthdayEnd);
                    }
                });
                // 3) 资料表 birth_month 命中
                if ($profileUids) {
                    $q->whereOr('uid', 'in', $profileUids);
                }
            });
        }

        if (isset($where['education']) && $where['education'] !== '') {
            $educations = is_array($where['education'])
                ? $where['education']
                : array_filter(explode(',', (string)$where['education']));
            if ($educations) {
                $uids = \think\facade\Db::name('user_profile')
                    ->whereIn('education', $educations)
                    ->column('uid');
                $query->whereIn('uid', $uids ?: [0]);
            }
        }

        if (!empty($where['height_min']) || !empty($where['height_max'])) {
            $hMin = max(0, intval($where['height_min'] ?? 0));
            $hMax = max(0, intval($where['height_max'] ?? 0));
            $heightQuery = \think\facade\Db::name('user_profile')->where('height', '>', 0);
            if ($hMin > 0) {
                $heightQuery->where('height', '>=', $hMin);
            }
            if ($hMax > 0) {
                $heightQuery->where('height', '<=', $hMax);
            }
            $heightUids = $heightQuery->column('uid');
            $query->whereIn('uid', $heightUids ?: [0]);
        }

        $count = $query->count();
        $list = $query->page($page, $limit)->select()->toArray();

        if ($list) {
            $uids = array_column($list, 'uid');
            $profiles = \think\facade\Db::name('user_profile')
                ->whereIn('uid', $uids)
                ->field('uid,education,height,birth_month,job_title,current_city,hometown_city,relationship_status,dating_purpose')
                ->select()
                ->toArray();
            $profileMap = [];
            foreach ($profiles as $profile) {
                $profileMap[$profile['uid']] = $profile;
            }
            $eduLabelMap = [
                1 => '高中', 2 => '大专', 3 => '本科', 4 => '硕士',
                5 => '博士', 6 => '中专', 7 => '小学', 8 => '初中',
            ];
            // 与用户资料表单对齐
            $relMap = [1 => '单身', 2 => '恋爱中', 3 => '已婚', 4 => '已育', 5 => '离异', 6 => '丧偶'];
            $datingMap = [1 => '找对象', 2 => '普通交友', 3 => '不确定'];

            $allLabelIds = [];
            foreach ($list as $row) {
                if (empty($row['label_id'])) continue;
                $ids = is_array($row['label_id']) ? $row['label_id'] : explode(',', (string)$row['label_id']);
                foreach ($ids as $id) {
                    $id = intval($id);
                    if ($id > 0) $allLabelIds[$id] = $id;
                }
            }
            $labelNameMap = [];
            if ($allLabelIds) {
                $labelNameMap = \think\facade\Db::name('user_label')
                    ->whereIn('label_id', array_values($allLabelIds))
                    ->column('label_name', 'label_id');
            }

            foreach ($list as &$item) {
                $profile = $profileMap[$item['uid']] ?? [];
                $item['education'] = isset($profile['education']) ? intval($profile['education']) : 0;
                $item['height'] = isset($profile['height']) ? intval($profile['height']) : 0;
                $item['job_title'] = $profile['job_title'] ?? '';
                $item['current_city'] = $profile['current_city'] ?? '';
                $item['education_label'] = $eduLabelMap[$item['education']] ?? '';
                if (
                    (empty($item['birthday']) || $item['birthday'] === '0000-00-00')
                    && !empty($profile['birth_month'])
                ) {
                    $item['birthday'] = $profile['birth_month'] . '-01';
                }

                $tags = [];
                if (!empty($item['label_id'])) {
                    $ids = is_array($item['label_id']) ? $item['label_id'] : explode(',', (string)$item['label_id']);
                    foreach ($ids as $id) {
                        $id = intval($id);
                        if ($id && !empty($labelNameMap[$id])) {
                            $tags[] = $labelNameMap[$id];
                        }
                    }
                }
                if (!empty($profile['job_title'])) {
                    $tags[] = $profile['job_title'];
                }
                if (!empty($profile['dating_purpose']) && !empty($datingMap[$profile['dating_purpose']])) {
                    $tags[] = $datingMap[$profile['dating_purpose']];
                }
                if (!empty($profile['relationship_status']) && !empty($relMap[$profile['relationship_status']])) {
                    $tags[] = $relMap[$profile['relationship_status']];
                }
                if (!empty($profile['current_city'])) {
                    $tags[] = $profile['current_city'];
                }
                $item['tags'] = array_values(array_unique(array_filter($tags)));
            }
            unset($item);
        }

        return compact('count', 'list');
    }


    /**
     * 更新用户内容数
     * @return bool
     * @throws DbException
     *
     * @date 2023/10/21
     * @author yyw
     */
    public function syncUserCountContent()
    {
        $communityRepository = app()->make(CommunityRepository::class);
        $user_count = $communityRepository->getCountByGroupUid();

        foreach ($user_count as $count) {
            $this->dao->update($count['uid'], ['count_content' => $count['count']]);
        }
        return true;
    }

    /**
     * 获取用户推广订单详情
     * @param int $uid
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     *
     * @date 2023/10/21
     * @author yyw
     */
    public function userOrderDetail($uid)
    {
        $info = $this->dao->userOrderDetail($uid);
        // 添加用户扩展信息
        $res = $info->toArray();
        $res['is_promoter'] = $info->getData('is_promoter');
        $res['extend_info'] = app()->make(UserFieldsRepository::class)->info((int)$uid, false)['extend_info'];
        return $res;
    }

    /**
     * 保存或者编辑扩展字段
     * @param int $uid
     * @param array $extend_info
     * @return true
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function saveFields(int $uid, array $extend_info = [])
    {
        // 组合数据
        $save_data = [];
        foreach ($extend_info as $item) {
            $save_data[] = [
                'field' => $item['field'],
                'value' => empty($item['value']) ? null : $item['value'],
            ];
        }
        return app()->make(UserFieldsRepository::class)->save($uid, $save_data, false);
    }

    /**
     *  获取用户扩展字段
     * @param int $uid
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getFields()
    {
        return app()->make(UserInfoRepository::class)->getSearch(['is_used' => 1])->order(['sort', 'create_time' => 'ASC'])->select()->toArray();
    }

    /**
     * 判断用户是否可以分销以及是否内购
     * @param int $uid 用户ID
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getExtensionInfo($user)
    {
        $isPromoter = false;//是否分销员
        $isSelfBuy = false;//是否内购
        if(systemConfig('extension_status') && !$user){
            $isPromoter = systemConfig('extension_all') || $user->is_promoter ? true : false;
            $isSelfBuy = $isPromoter && systemConfig('extension_self') ? 1 : 0;//是否内购
        }
        return ['isPromoter'=>$isPromoter,'isSelfBuy'=>$isSelfBuy];
    }

    public function queryCustomer(array $where, int $page, int $size) : array
    {
        $search = $where['search'] ?? null;
        unset($where['search']);

        // 根据条件初始化查询
        $query = $this->dao->search($where);
        if(isset($search)) {
            $query->where('User.uid|User.nickname|User.phone', 'LIKE', '%' . $search . '%');
        }

        // 计算总条数
        $count = $query->count();
        // 获取用户列表
        $field = 'User.uid, User.nickname, User.phone, CONCAT(SUBSTRING(User.phone, 1, 3), "****", SUBSTRING(User.phone, 8, 4)) as masked_phone, User.real_name, User.avatar, User.sex, User.is_svip, User.now_money, User.integral';
        $list = $query->setOption('field', [])->field($field)->page($page, $size)->order('User.uid DESC')->select();

        return compact('count', 'list');
    }
     /**
      * 商户添加新用户
      * 代客下单时，商户可以为用户添加新用户。商户需要提供用户的电话号码，系统会自动生成一个默认密码
      *
      * @param array $params
      * @return mixed 具体类型取决于create方法的返回值
      */
    public function merchantRegistrs(array $params)
    {
        $phone = $params['phone'];
        $data = [
            'account' => $phone,
            'pwd' => $this->encodePassword($this->dao->defaultPwd()),
            'nickname' => !empty($params['nickname']) ? $params['nickname'] : substr($phone, 0, 3) . '****' . substr($phone, 7, 4),
            'avatar' => '',
            'phone' => $phone,
            'is_svip' => -1,
            'last_ip' => app('request')->ip()
        ];
        env('registr.before',compact('data'));

        return $this->create('h5', $data);
    }

    public function userInfo(int $uid)
    {
        return $this->dao->get($uid);
    }
}
