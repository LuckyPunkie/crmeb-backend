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
use app\common\dao\user\UserBillDao;
use app\common\repositories\BaseRepository;
use think\facade\Db;
use app\common\repositories\store\product\ProductRepository;
use crmeb\jobs\SendSmsJob;
use think\facade\Queue;
use think\Model;

/**
 * Class UserBillRepository
 * @package app\common\repositories\user
 * @author xaboy
 * @day 2020-05-07
 * @mixin UserBillDao
 */
class UserBillRepository extends BaseRepository
{
    /**
     * @var UserBillDao
     */
    protected $dao;

    const TYPE_INFO = [
        'brokerage' => [
            'brokerage/now_money' => '佣金转入余额',
            'brokerage/order_one' => '获得一级推广佣金',
            'brokerage/order_two' => '获得二级推广佣金',
            'brokerage/refund_one' => '退还一级佣金',
            'brokerage/refund_two' => '退还二级佣金',
        ],
        'integral' => [
            'integral/cancel' => '退回积分',
            'integral/deduction' => '购买商品',
            'integral/points' => '兑换商品',
            'integral/lock' => '下单赠送积分',
            'integral/refund' => '订单退款',
            'integral/refund_lock' => '扣除赠送积分',
            'integral/sign_integral' => '签到赠送积分',
            'integral/spread' => '邀请好友',
            'integral/sys_dec' => '系统减少积分',
            'integral/sys_inc' => '系统增加积分',
            'integral/timeout' => '积分过期',
        ],
        'mer_integral' => [
            'mer_integral/deduction' => '积分抵扣',
            'mer_integral/refund' => '订单退款',
        ],
       'now_money' => [
           //'now_money/extract' => '佣金提现余额',
           'now_money/brokerage' => '佣金转入余额',
           'now_money/pay_product' => '购买商品',
           'now_money/presell' => '支付预售尾款',
           'now_money/recharge' => '余额充值',
           'now_money/sys_dec_money' => '系统减少余额',
           'now_money/sys_inc_money' => '系统增加余额',
           'svip_pay/svip_pay' => '付费会员支付'
       ],
        'mer_margin' => [
            'mer_margin/local_margin' => '线下缴纳保证金',
            'mer_margin/pay_margin' => '线上缴纳保证金',
        ],
        'mer_lock_money' => [
            'mer_lock_money/order' => '商户佣金冻结',
        ],
        'sys_members' => [
            'sys_members/member_upgrade' => '会员升级',
            'sys_members/platform_clearing' => '平台清除',
            'sys_members/member_pay_num'   => '下单获得成长值',
            'sys_members/member_sign_num'  => '签到获得成长值',
            'sys_members/member_reply_num' => '评价获得成长值',
            'sys_members/member_share_num' => '邀请获得成长值',
            'sys_members/member_community_num'  => '社区种草内容获得成长值',
        ]
    ];

    const CATEGORY_SVIP_PAY = 'svip_pay';
    const CATEGORY_NOW_MONEY = 'now_money';
    const CATEGORY_INTEGRAL = 'integral';
    const CATEGORY_BROKERAGE= 'brokerage';
    const CATEGORY_MER_MARGIN = 'mer_margin';
    const CATEGORY_MER_INTEGRAL = 'mer_integral';
    const CATEGORY_MER_LOCK_MONEY = 'mer_lock_money';
    const CATEGORY_SYS_MEMBERS = 'sys_members';

    // 需要去重复的类型
    const TO_REPEAT_TYPE = ['member_community_num'];


    /**
     * UserBillRepository constructor.
     * @param UserBillDao $dao
     */
    public function __construct(UserBillDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取用户列表数据
     *
     * 根据给定的条件查询用户相关列表信息，包括账单ID、项目名称、数量、余额、标记、创建时间和状态等。
     * 支持分页查询，便于前端进行数据展示。
     *
     * @param array $where 查询条件，用于定制查询的详细要求。
     * @param int $uid 用户ID，用于限定查询的用户范围。
     * @param int $page 当前页码，用于分页查询。
     * @param int $limit 每页数据条数，用于控制分页查询的数据量。
     * @return array 返回包含总数和列表数据的数组，方便前端进行分页展示。
     */
    public function userList($where, $uid, $page, $limit)
    {
        // 将用户ID添加到查询条件中
        $where['uid'] = $uid;

        // 构建查询语句，根据创建时间降序排列
        $query = $this->dao->search($where)->order('create_time DESC');

        // 计算满足条件的数据总数
        $count = $query->count();

        // 确定查询的字段，进行分页查询，并获取满足条件的数据列表
        $list = $query->setOption('field', [])->field('bill_id,pm,title,number,balance,mark,create_time,status')->page($page, $limit)->select();

        // 返回数据总数和数据列表，供前端使用
        return compact('count', 'list');
    }

    /**
     * 根据条件获取每个月的数据统计
     *
     * 本函数用于根据传入的条件数组，查询数据库中每个月的统计数据。
     * 包括每个月的标题、文章数量、评论数量等，并按照时间降序排列。
     *
     * @param array $where 查询条件数组
     * @return array 返回每个月的统计数据，包括月份和该月的具体数据列表
     */
    public function month(array $where)
    {
        // 根据条件查询数据库，统计每个月的记录，并按照时间降序排列
        $group = $this->dao->search($where)
            ->field('FROM_UNIXTIME(unix_timestamp(create_time),"%Y-%m") as time')
            ->order('time DESC')
            ->group('time')
            ->select();

        $ret = [];
        // 遍历每个月的统计数据，细化每个月的数据列表
        foreach ($group as $k => $item){
            // 设置每个月的名称
            $ret[$k]['month'] = $item['time'];
            // 查询该月的具体数据，包括标题、评论数、文章数和创建时间
            $query = $this->dao->getSearch($where)
                ->field('title,pm,number,create_time')
                ->whereMonth('create_time', $item['time']);
            // 获取该月的具体数据列表，并按照创建时间降序排列
            $ret[$k]['list'] = $query->order('create_time DESC')->select();
        }
        // 返回每个月的统计数据
        return $ret;
    }

    /**
     * 获取列表数据
     * 通过此方法可以从数据库中检索满足特定条件的列表数据。它支持分页查询，以优化性能和用户体验。
     *
     * @param string|array $where 查询条件，可以是字符串或数组，用于指定检索数据的具体条件。
     * @param int $page 当前页码，用于分页查询，指定要返回的数据页。
     * @param int $limit 每页数据的数量，用于分页查询，指定每页返回的数据条数。
     * @return array 返回包含两个元素的数组，'count'表示满足条件的数据总数量，'list'表示当前页的数据列表。
     */
    public function getList($where, $page, $limit)
    {
        // 根据提供的条件进行查询，并按创建时间降序和账单ID降序排序
        $query = $this->dao->searchJoin($where)->order(Db::raw('a.create_time DESC, bill_id DESC'));

        // 计算满足条件的数据总数量
        $count = $query->count();

        // 获取当前页的数据列表
        $list = $query->page($page, $limit)->select();

        // 返回包含数据总数量和当前页数据列表的数组
        return compact('count', 'list');
    }


    /**
     * 获取列表数据
     *
     * 本函数用于根据条件查询数据库中的数据列表，并进行分页处理。
     * 返回包含数据总数和当前分页数据的数组。
     *
     * @param string $where 查询条件，用于构造SQL语句中的WHERE子句。
     * @param int $page 当前页码，用于确定要返回的分页数据。
     * @param int $limit 每页显示的数据条数，用于确定分页数据的数量。
     * @return array 返回一个包含 'count' 和 'list' 两个元素的数组，'count' 表示数据总数，'list' 表示当前分页的数据列表。
     */
    public function getLst($where, $page, $limit)
    {
        // 根据$where条件搜索数据，并按创建时间降序排序
        $query = $this->dao->search($where)->order('create_time DESC');

        // 计算满足条件的数据总数
        $count = $query->count();

        // 获取当前页码的分页数据列表
        $list = $query->page($page, $limit)->select();

        // 将数据总数和当前分页数据列表打包成数组返回
        return compact('count', 'list');
    }

    /**
     * 创建账单
     *
     * 该方法用于生成用户的消费或收入账单。根据传入的参数，账单信息将被记录到数据库中。
     * 如果账单类别为'now_money'（即涉及现金变动），则会将发送短信通知的任务推入队列。
     *
     * @param int $uid 用户ID，用于标识账单所属的用户。
     * @param string $category 账单类别，用于分类账单类型。
     * @param string $type 账单的具体类型，进一步细化账单类别。
     * @param int $pm 账单的正负标识，用于区分收入还是支出。
     * @param array $data 其他账单相关数据，以键值对形式传入，将被合并到账单数据中。
     * @return object 返回创建的账单对象。
     */
    public function bill(int $uid, string $category, string $type, int $pm, array $data)
    {
        // 合并传入的数据与账单基础信息，准备创建账单
        $data['category'] = $category;
        $data['type'] = $type;
        $data['uid'] = $uid;
        $data['pm'] = $pm;

        // 通过数据访问对象创建账单记录
        $bill = $this->dao->create($data);

        // 如果账单类别是'now_money'，则推送发送短信通知的任务到队列
        if($category == 'now_money'){
            Queue::push(SendSmsJob::class,['tempId' => 'USER_BALANCE_CHANGE','id' => $bill->bill_id]);
        }

        // 返回创建的账单对象
        return $bill;
    }

    /**
     * 递增计费项
     * 此函数用于增加指定用户的计费项。它是一个简化的版本，通过调用另一个函数来实现。
     *
     * @param int $uid 用户ID，用于标识哪个用户产生了计费项。
     * @param string $category 计费项分类，用于细化计费项的类型。
     * @param string $type 计费项的类型，进一步定义计费的具体内容。
     * @param array $data 关于计费项的额外数据，以键值对形式提供。
     *
     * @return mixed 返回调用bill函数的结果，具体类型取决于bill函数的实现。
     */
    public function incBill(int $uid, string $category, string $type, array $data)
    {
        // 调用bill函数来处理计费项的增加，其中最后一个参数1表示增加操作。
        return $this->bill($uid, $category, $type, 1, $data);
    }

    /**
     * 减少用户账单
     * 此函数用于减少用户的某种账单。它是一个辅助函数，通过调用另一个函数来实现具体的账单减少操作。
     *
     * @param int $uid 用户ID。标识进行账单操作的用户。
     * @param string $category 账单分类。指示账单的具体类型。
     * @param string $type 账单操作类型。指示对账单进行的操作，例如增加、减少等。
     * @param array $data 额外数据。包含与账单操作相关的其他信息。
     *
     * @return mixed 返回账单操作的结果。具体类型取决于底层实现。
     */
    public function decBill(int $uid, string $category, string $type, array $data)
    {
        // 调用bill方法减少用户的账单，其中第五个参数设置为0，表示减少操作。
        return $this->bill($uid, $category, $type, 0, $data);
    }


    /**
     * 根据分类获取类型信息
     *
     * 本函数旨在根据给定的分类，从预定义的TYPE_INFO数组中提取并返回相关类型的详细信息。
     * 它通过遍历TYPE_INFO数组中指定分类下的所有类型，将类型和对应的标题打包成数组返回。
     * 这样做的目的是为了方便调用者一次性获取到特定分类下的所有类型及其标题，便于进一步处理或展示。
     *
     * @param string $category 分类名称，用于从TYPE_INFO数组中筛选类型信息
     * @return array 包含类型和标题的二维数组，每个元素是一个包含'type'和'title'两个键的关联数组
     */
    public function type($category)
    {
        // 初始化用于存储结果的数组
        $data = [];

        // 遍历TYPE_INFO数组中指定分类下的所有类型和标题
        foreach (self::TYPE_INFO[$category] as $type => $title) {
            // 将类型和标题打包成关联数组，并添加到结果数组中
            $data[] = compact('type', 'title');
        }

        // 返回包含所有类型信息的数组
        return $data;
    }

    /**
     * 积分日志头部统计
     * @return array
     * @author Qinii
     * @day 6/9/21
     */
    public function getStat($merId = 0)
    {
        if($merId){
            $isusd = app()->make(ProductRepository::class)->getSearch(['mer_id' => $merId])->sum('integral_total');
            $refund = $this->dao->search(['category' => 'mer_integral','type' => 'refund','mer_id' => $merId])->sum('number');
            $numb = app()->make(ProductRepository::class)->getSearch(['mer_id' => $merId])->sum('integral_price_total');
            return [
                [
                    'className' => 'el-icon-s-cooperation',
                    'count' => $isusd,
                    'field' => '个',
                    'name' => '已使用积分（分）'
                ],
                [
                    'className' => 'el-icon-edit',
                    'count' => $refund,
                    'field' => '次',
                    'name' => '退款订单返回积分（分）'
                ],
                [
                    'className' => 'el-icon-edit',
                    'count' => $numb,
                    'field' => '次',
                    'name' => '积分抵扣金额（元）'
                ],
            ];
        }
        // 总积分
        $integral = app()->make(UserRepository::class)->search(['status' => 1])->sum('integral');
        // 客户签到次数
        $sign = app()->make(UserSignRepository::class)->getSearch([])->count('*');
        // 签到送出积分
        $sign_integral = $this->dao->search(['type' => 'sign_integral'])->sum('number');
        // 使用积分
        $isusd  = $this->dao->search(['category' => 'integral','type' => 'deduction'])->sum('number');
        $refund = $this->dao->search(['category' => 'mer_integral','type' => 'refund'])->sum('number');
        $order = $isusd - $refund;

        // 下单赠送积分
        $order_integral1 = $this->dao->search(['category' => 'integral','type' => 'lock'])->sum('number');
        $order_integral2 = $this->dao->search(['category' => 'integral','type' => 'refund_lock'])->sum('number');
        $order_integral = $order_integral1 - $order_integral2;
        $order_integral = $order_integral < 0 ? 0 : $order_integral;
        // 冻结积分
        $freeze_integral = $this->dao->lockIntegral();

        return [
            [
                'className' => 'el-icon-s-cooperation',
                'count' => $integral,
                'field' => '个',
                'name' => '总积分'
            ],
            [
                'className' => 'el-icon-edit',
                'count' => $sign,
                'field' => '次',
                'name' => '客户签到次数'
            ],
            [
                'className' => 'el-icon-s-goods',
                'count' => $sign_integral ,
                'field' => '个',
                'name' => '签到送出积分'
            ],
            [
                'className' => 'el-icon-s-order',
                'count' => $order,
                'field' => '个',
                'name' => '使用积分'
            ],
            [
                'className' => 'el-icon-present',
                'count' => $order_integral,
                'field' => '个',
                'name' => '下单赠送积分'
            ],
            [
                'className' => 'el-icon-warning',
                'count' => $freeze_integral,
                'field' => '',
                'name' => '冻结积分'
            ],
        ];
    }


    /**
     * 判断是否需要去除重复添加
     * @param int $uid
     * @param string $type
     * @param int $link_id
     * @return bool
     */
    public function ToRepeat(int $uid, string $type, int $link_id)
    {
        if (in_array($type, self::TO_REPEAT_TYPE)) {
            //判断是否重复
            $make = app()->make(UserBillRepository::class);
            $count = $make->getWhereCount(['uid' => $uid, 'type' => $type, 'link_id' => $link_id]);
            if ($count) {
                return true;
            }
        }
        return false;
    }


}
