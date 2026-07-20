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


use app\common\dao\user\UserBrokerageDao;
use app\common\model\user\User;
use app\common\model\user\UserBrokerage;
use app\common\repositories\BaseRepository;
use app\common\repositories\system\CacheRepository;
use FormBuilder\Factory\Elm;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Route;

/**
 * @mixin UserBrokerageDao
 */
class UserBrokerageRepository extends BaseRepository
{

    const BROKERAGE_RULE_TYPE = ['spread_user', 'pay_money', 'pay_num', 'spread_money', 'spread_pay_num'];

    public function __construct(UserBrokerageDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取列表数据
     *
     * 根据给定的条件数组 $where、页码 $page 和每页数据数量 $limit，查询并返回符合条件的数据列表。
     * 此方法主要用于数据的分页查询，通过 dao 层的 search 方法进行查询，首先根据条件进行筛选，
     * 然后按照佣金级别升序、创建时间降序进行排序。最后，根据页码和每页数据数量进行分页，返回查询结果。
     *
     * @param array $where 查询条件数组
     * @param int $page 当前页码
     * @param int $limit 每页数据数量
     * @return array 返回包含列表数据和总条数的数组
     */
    public function getList(array $where, $page, $limit)
    {
        // 根据条件进行查询，并按照佣金级别升序、创建时间降序排序
        $query = $this->dao->search($where)->order('brokerage_level ASC,create_time DESC');

        // 统计符合条件的数据总条数
        $count = $query->count();

        // 根据当前页码和每页数据数量进行分页查询，并获取查询结果
        $list = $query->page($page, $limit)->select()->each(function ($item) {
            $item->user_num = User::where([
                'brokerage_level' => $item->brokerage_level,
                'promoter_switch' => 1,
                'is_promoter' => 1
            ])->count();
        });

        // 返回包含查询结果列表和总条数的数组
        return compact('list', 'count');
    }

    /**
     * 根据当前等级和类型获取下一个等级的信息
     *
     * 本函数旨在查询数据库中，当前等级($level)之后的下一个等级的信息。可以选择性地通过$type参数来筛选特定类型的等级。
     * 查询结果将按照佣金等级升序和创建时间降序返回第一个匹配项。
     *
     * @param string $level 当前等级的值
     * @param int $type 等级的类型，默认为0，表示不进行类型筛选
     * @return object 返回查询结果的对象，包含下一个等级的信息
     */
    public function getNextLevel($level,$type = 0)
    {
        // 使用查询构建器进行查询，筛选出next_level为$level且type为$type的数据，按佣金等级升序和创建时间降序排序，并返回第一条数据
        return $this->search(['next_level' => $level,'type' => $type])->order('brokerage_level ASC,create_time DESC')->find();
    }

    /**
     * 根据条件查询佣金等级选项
     *
     * 本函数用于根据传入的条件数组，从数据库中查询并返回佣金等级的相关信息。
     * 返回的信息格式化为适用于选项列表的形式，每个选项包含value和label属性。
     * value表示佣金等级的值，label表示佣金等级的显示名称。
     * 查询结果按照佣金等级升序，创建时间降序排序。
     *
     * @param array $where 查询条件数组
     * @return array 返回查询结果，格式为每个元素包含value和label属性的数组
     */
    public function options(array $where)
    {
        // 使用DAO层的search方法进行条件查询，指定返回的字段为brokerage_level和brokerage_name，
        // 并设置排序方式为brokerage_level升序，create_time降序。
        // 最后执行查询并返回结果。
        return $this->dao->search($where)
            ->field('brokerage_level as value,brokerage_name as label')
            ->order('brokerage_level ASC,create_time DESC')
            ->select()->each(function($item){
                $item['value'] = (int)$item['value'];
                return $item;
            });
    }

    /**
     * 获取所有类型为$type$的数据
     *
     * 本函数通过调用DAO层的search方法，查询类型为$type$的所有数据。
     * 查询结果将按照经纪等级（brokerage_level）升序和创建时间（create_time）降序进行排序。
     * 最后，函数返回查询结果。
     *
     * @param int $type 数据的类型，用于筛选查询条件。
     * @return array 返回查询结果，是一个包含多个数据项的数组。
     */
    public function all(int $type)
    {
        // 调用DAO层的search方法查询数据，指定类型为$type$，并设定排序规则
        // 返回查询结果，未对返回值做进一步处理，直接返回查询结果数组
        return $this->dao->search(['type' => $type])->order('brokerage_level ASC,create_time DESC')->select();
    }

    /**
     * 用户佣金增加函数
     *
     * 该函数用于处理用户佣金的增加逻辑。它首先根据当前用户的经纪等级获取下一个等级信息，
     * 然后检查是否存在相应的佣金记录。如果存在，则更新该记录的金额；如果不存在，则创建新的佣金记录。
     * 最后，检查用户是否满足升级到下一个经纪等级的条件。
     *
     * @param User $user 用户对象，表示当前操作的用户
     * @param string $type 佣金类型，用于区分不同种类的佣金
     * @param float $inc 佣金增加的金额，使用浮点数表示
     * @return bool 返回布尔值，表示用户是否成功升级到下一个经纪等级
     */
    public function inc(User $user, $type, $inc)
    {
        // 获取用户下一个经纪等级信息
        $nextLevel = $this->getNextLevel($user->brokerage_level);
        // 如果没有下一个等级，则返回false
        if (!$nextLevel) return false;

        // 实例化用户账单仓库类
        $make = app()->make(UserBillRepository::class);
        // 根据条件查询是否存在对应的佣金记录
        $bill = $make->getWhere(['uid' => $user->uid, 'link_id' => $nextLevel->user_brokerage_id, 'category' => 'sys_brokerage', 'type' => $type]);
        // 如果记录存在，则更新记录的金额并保存
        if ($bill) {
            $bill->number = bcadd($bill->number, $inc, 2);
            $bill->save();
        } else {
            // 如果记录不存在，则创建新的佣金记录
            $make->incBill($user->uid, 'sys_brokerage', $type, [
                'number' => $inc,
                'title' => $type,
                'balance' => 0,
                'status' => 0,
                'link_id' => $nextLevel->user_brokerage_id
            ]);
        }

        // 检查用户是否满足升级到下一个经纪等级的条件，并返回检查结果
        return $this->checkLevel($user, $nextLevel);
    }

    /**
     * 检查用户是否满足升级经纪人的条件
     *
     * 本函数用于评估当前用户是否满足升级为其指定经纪人的条件。它通过查询用户的交易记录，
     * 并对比经纪人的升级规则，来判断用户是否达到升级标准。如果用户满足条件，则进行升级操作，
     * 包括更新用户和经纪人的相关数据，并记录用户的经纪级别。
     *
     * @param User $user 当前待评估的用户对象
     * @param UserBrokerage $nextLevel 用户待升级的下一个经纪级别对象
     * @return bool 如果用户满足升级条件，则返回true；否则返回false。
     */
    public function checkLevel(User $user, UserBrokerage $nextLevel)
    {
        // 查询用户的交易记录，满足特定条件的记录将用于评估升级条件
        $info = app()->make(UserBillRepository::class)->search(['uid' => $user->uid, 'category' => 'sys_brokerage', 'link_id' => $nextLevel->user_brokerage_id])
            ->column('number', 'type');

        // 遍历经纪人的升级规则，检查用户是否满足所有规则条件
        foreach ($nextLevel['brokerage_rule'] as $k => $rule) {
            // 如果用户交易记录中不存在对应类型的记录，且规则要求的交易数量大于0，则不满足升级条件
            if (!isset($info[$k]) && $rule['num'] > 0) return false;
            // 如果用户交易记录数量小于规则要求的数量，则不满足升级条件
            if ($rule['num'] > 0 && $rule['num'] > $info[$k]) return false;
        }

        // 用户满足升级条件，准备进行数据更新
        $nextLevel->user_num++;
        Db::transaction(function () use ($nextLevel, $user) {
            // 保存更新后的下一个经纪级别信息
            $nextLevel->save();
            // 如果当前用户已有经纪人，并且其用户数量大于0，则减少该经纪人的用户数量
            if ($user->brokerage && $user->brokerage->user_num > 0) {
                $user->brokerage->user_num--;
                $user->brokerage->save();
            }
            // 更新用户经纪级别，并保存
            $user->brokerage_level = $nextLevel->brokerage_level;
            $user->save();

            // 将用户的经纪级别信息缓存起来，以便后续快速查询
            $key = 'notice_brokerage_level_' . $user->uid;
            app()->make(CacheRepository::class)->save($key,$nextLevel->brokerage_level);
        });

        // 返回true，表示用户满足升级条件
        return true;
    }

    /**
     * 根据用户的交易记录和下级经纪人的佣金规则，计算下级经纪人的佣金比率。
     *
     * @param User $user 当前用户对象
     * @param UserBrokerage $nextLevel 下级经纪人的佣金规则对象
     * @return array 返回计算后的佣金比率和任务完成情况
     */
    public function getLevelRate(User $user, UserBrokerage $nextLevel)
    {
        // 查询用户指定条件的交易记录，用于后续计算佣金比率
        $info = app()->make(UserBillRepository::class)->search(['uid' => $user->uid, 'category' => 'sys_brokerage', 'link_id' => $nextLevel->user_brokerage_id])
            ->column('number', 'type');

        // 获取下级经纪人的佣金规则
        $brokerage_rule = $nextLevel['brokerage_rule'];

        // 遍历佣金规则，计算每个规则对应的佣金比率和完成任务的数量
        foreach ($nextLevel['brokerage_rule'] as $k => $rule) {
            // 如果规则的交易数量要求为0或负数，则移除该规则
            if ($rule['num'] <= 0) {
                unset($brokerage_rule[$k]);
                continue;
            }

            // 根据用户的交易数量和规则的要求数量，计算佣金比率
            if (!isset($info[$k])) {
                $rate = 0;
            } else if ($rule['num'] > $info[$k]) {
                $rate = bcdiv($info[$k], $rule['num'], 2) * 100;
            } else {
                $rate = 100;
            }

            // 更新规则信息，添加佣金比率和已完成的任务数量
            $brokerage_rule[$k]['rate'] = $rate;
            $brokerage_rule[$k]['task'] = (float)(min($info[$k] ?? 0, $rule['num']));
        }

        // 返回计算后的佣金规则信息
        return $brokerage_rule;
    }

    /**
     * 创建或编辑会员等级表单
     *
     * @param int|null $id 会员等级ID，如果提供ID，则为编辑模式，否则为添加模式
     * @return \FormBuilder\Form|\think\form\Form
     */
    public function form(?int $id = null)
    {
        // 初始化表单数据数组
        $formData = [];
        // 如果ID存在，进入编辑模式
        if ($id) {
            // 创建表单，表单提交地址为更新会员等级的URL
            $form = Elm::createForm(Route::buildUrl('systemUserMemberUpdate', ['id' => $id])->build());
            // 通过ID获取会员等级数据
            $data = $this->dao->get($id);
            // 如果数据不存在，抛出异常
            if (!$data) throw new ValidateException('数据不存在');
            // 将获取的会员等级数据转换为数组，并赋值给formData
            $formData = $data->toArray();

        } else {
            // 如果ID不存在，进入添加模式
            // 创建表单，表单提交地址为创建会员等级的URL
            $form = Elm::createForm(Route::buildUrl('systemUserMemberCreate')->build());
        }

        // 定义表单规则，包括会员等级、会员名称、会员图标、所需成长值和背景图
        $rules = [
            Elm::number('brokerage_level', '会员等级：')->placeholder('请输入会员等级')->required(true),
            Elm::input('brokerage_name', '会员名称：')->placeholder('请输入会员名称')->required(true),
            Elm::frameImage('brokerage_icon', '会员图标：', '/' . config('admin.admin_prefix') . '/setting/uploadPicture?field=brokerage_icon&type=1')
                ->required(true)
                ->value($formData['brokerage_icon'] ?? '')
                ->modal(['modal' => false])
                ->icon('el-icon-camera')
                ->width('1000px')
                ->height('600px'),
            Elm::number('value', ' 所需成长值：',$formData['brokerage_rule']['value'] ?? 0)->required('请输入所需成长值'),
            Elm::frameImage('image', '背景图：', '/' . config('admin.admin_prefix') . '/setting/uploadPicture?field=image&type=1')
                ->value($formData['brokerage_rule']['image']??'')
                ->required('请上传背景图')
                ->modal(['modal' => false])
                ->icon('el-icon-camera')
                ->width('1000px')
                ->height('600px'),
        ];
        // 设置表单规则
        $form->setRule($rules);
        // 设置表单标题，根据ID是否存在决定是添加还是编辑会员等级
        return $form->setTitle(is_null($id) ? '添加会员等级' : '编辑会员等级')->formData($formData);
    }

    /**
     * 增加用户会员值
     *
     * 根据用户的特定行为（如下单、签到、评价等），增加用户的会员值。
     * 可以根据配置的成长值规则，以及用户是否为VIP，动态计算增长的会员值。
     * 如果用户是VIP，并且系统配置允许，会员值的增长还会受到VIP加成的影响。
     *
     * @param int $uid 用户ID
     * @param string $type 行为类型，对应不同的会员值增长规则
     * @param int $id 关联的ID，如订单ID、评论ID等，根据行为类型不同而变化
     * @param int $money 当行为类型为下单时，订单的支付金额，用于计算额外的会员值增长
     */
    public function incMemberValue(int $uid, string $type, int $id, int $money = 0)
    {
        // 检查是否启用了会员功能，如果没有启用，则直接返回
        if (!systemConfig('member_status')) return;

        // 创建用户账单仓库实例
        $make = app()->make(UserBillRepository::class);

        // 检查是否需要重复添加，如果需要，则直接返回
        // 判断是否要重复添加
        if ($make->ToRepeat($uid, $type, $id)) {
            return;
        }

        // 定义不同行为类型的描述和默认增长值
        $config = [
            'member_pay_num'   => '下单获得成长值',
            'member_sign_num'  => '签到获得成长值',
            'member_reply_num' => '评价获得成长值',
            'member_share_num' => '邀请获得成长值',
            'member_community_num'  => '社区种草内容获得成长值',
            'member_order_pay_num'  => ';下单获得比例成长值'
        ];

        // 根据行为类型获取配置的增长值，如果配置值小于等于0，则默认为0
        $inc = systemConfig($type) > 0 ? systemConfig($type) : 0;

        // 获取用户信息，包括会员值等
        $user = app()->make(UserRepository::class)->getWhere(['uid' => $uid], '*', ['member']);

        // 判断用户是否为VIP，并且VIP功能是否开启
        $svip_status = $user->is_svip > 0 && systemConfig('svip_switch_status') == '1';

        // 如果用户是VIP，并且设置了VIP加成比例，则根据比例调整增长值
        if ($svip_status) {
            $svipRate = app()->make(MemberinterestsRepository::class)->getSvipInterestVal(MemberinterestsRepository::HAS_TYPE_MEMBER);
            if ($svipRate > 0) {
                $inc = bcmul($svipRate, $inc, 0);
            }
        }

        // 构建增长标记，描述增长的原因和值
        $mark = $config[$type].':'.$inc;

        // 如果行为类型为下单，并且指定了订单支付金额，则根据配置的规则计算额外的会员值增长
        // 下单通过经验值获得比例乘以订单金额作为成长值
        $inc_ = 0;
        if ($type == 'member_pay_num' && $money) {
            $inc_ = systemConfig('member_order_pay_num') > 0 ? systemConfig('member_order_pay_num') : 0;
            $inc_ = (int)bcmul($money, $inc_, 0);
            $mark .= $config['member_order_pay_num'].':'.$inc_;
        }

        // 计算最终的会员值增长量
        $inc = $inc + $inc_;
        // 创建用户账单，记录会员值的增长情况
        $make->incBill($user->uid, 'sys_members', $type, [
            'number'  => $inc,
            'title'   => $config[$type],
            'balance' => $user->member_value + $inc,
            'status'  => 0,
            'link_id' => $id,
            'mark' => $mark,
        ]);
        // 检查用户的会员值是否满足升级条件
        $this->checkMemberValue($user, $inc);
    }

    /**
     * 连续升级
     * @param $nextLevel
     * @param $num
     * @return array
     * @author Qinii
     * @day 1/11/22
     */
    public function upUp($nextLevel, $num, $use_value)
    {
        $newLevel = $this->getNextLevel($nextLevel->brokerage_level, 1);
        if (!$newLevel) {
            return [$nextLevel, $num, $use_value];
        }

        $newNum = $num - $newLevel->brokerage_rule['value'];
        if ($newNum < 0) {
            return [$nextLevel, $num, $use_value];
        }

        $use_value += $newLevel->brokerage_rule['value'];
        $nextLevel = $newLevel;

        if ($newNum === 0) {
            return [$nextLevel, $newNum, $use_value];
        }

        return $this->upUp($nextLevel, $newNum, $use_value);
    }

    /**
     * 升级操作
     * @param User $user
     * @param int $inc
     * @author Qinii
     * @day 1/11/22
     */
    public function checkMemberValue(User $user, int $inc)
    {
        /**
         * 下一级所需经验值
         * 当前的经验值加上增加经验值是否够升级
         */
        $nextLevel = $this->getNextLevel($user->member_level, 1);
        //return Db::transaction(function () use ($inc, $user, $nextLevel) {
            $num = $user->member_value + $inc;
            if ($nextLevel) {
                if ($num >= $nextLevel->brokerage_rule['value']) {
                    $num -= $nextLevel->brokerage_rule['value'];
                    $use_value = $nextLevel->brokerage_rule['value'];  // 升级消耗成长值

                    [$nextLevel, $num, $use_value] = $this->upUp($nextLevel, $num, $use_value);
                    if ($user->member) {
                        $user->member->user_num--;
                        $user->member->save();
                    }
                    $nextLevel->user_num++;
                    $nextLevel->save();
                    $user->member_level = $nextLevel->brokerage_level;
                    $key = 'notice_member_level_' . $user->uid;
                    app()->make(CacheRepository::class)->save($key, $nextLevel->brokerage_level);
                    // 添加升级所需成长值记录
                    app()->make(UserBillRepository::class)->decBill($user->uid, 'sys_members', 'member_upgrade', ['number'  => $use_value,
                      'title'   => '升级消耗成长值',
                      'balance' => $num,
                      'status'  => 0,
                      'mark'    => '升级消耗成长值' . ':' . $use_value,
                    ]);
                }
            }
            $user->member_value = $num;
            $user->save();
            return $user;
        //});
    }
}
