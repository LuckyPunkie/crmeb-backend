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


namespace app\common\repositories\store\coupon;


use app\common\dao\store\coupon\StoreCouponDao;
use app\common\dao\store\coupon\StoreCouponProductDao;
use app\common\model\store\coupon\StoreCoupon;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\product\ProductRepository;
use app\common\repositories\store\StoreCategoryRepository;
use app\common\repositories\system\merchant\MerchantCategoryRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\system\merchant\MerchantTypeRepository;
use app\common\repositories\user\UserRepository;
use FormBuilder\Exception\FormBuilderException;
use FormBuilder\Factory\Elm;
use FormBuilder\Form;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Route;

/**
 * 优惠券
 */
class StoreCouponRepository extends BaseRepository
{

    //店铺券
    const TYPE_STORE_ALL = 0;
    //店铺商品券
    const TYPE_STORE_PRODUCT = 1;
    //平台券
    const TYPE_PLATFORM_ALL = 10;
    //平台分类券
    const TYPE_PLATFORM_CATE = 11;
    //平台跨店券
    const TYPE_PLATFORM_STORE = 12;

    //获取方式
    const GET_COUPON_TYPE_RECEIVE = 0;
    //消费满赠
    const GET_COUPON_TYPE_PAY_MEET = 1;
    //新人券
    const GET_COUPON_TYPE_NEW = 2;
    //买赠
    const GET_COUPON_TYPE_PAY = 3;
    //首单赠送
    const GET_COUPON_TYPE_FIRST = 4;
    //会员券
    const GET_COUPON_TYPE_SVIP = 5;
    //后台赠送
    const GET_COUPON_TYPE_ADMIN = 6;

    /**
     * @var StoreCouponDao
     */
    protected $dao;

    /**
     * StoreCouponIssueRepository constructor.
     * @param StoreCouponDao $dao
     */
    public function __construct(StoreCouponDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取列表数据
     * 根据商家ID、查询条件、分页信息和每页数量来获取特定数据列表。
     *
     * @param int|null $merId 商家ID，用于指定查询的商家数据，可为空。
     * @param array $where 查询条件数组，用于指定查询的过滤条件。
     * @param int $page 当前页码，用于分页查询。
     * @param int $limit 每页数量，用于分页查询。
     * @return array 返回包含总数和列表数据的数组。
     */
    public function getList(?int $merId, array $where, $page, $limit)
    {
        // 构建基础查询条件，包括商家ID和额外的查询条件，并关联商家信息，只获取指定字段。
        $baseQuery = $this->dao->search($merId, $where)->with(['merchant' => function ($query) {
            $query->field('mer_id,mer_name,is_trader');
        }]);

        // 计算满足条件的数据总数。
        $count = $baseQuery->count($this->dao->getPk());

        // 根据分页信息获取满足条件的数据列表。
        $list = $baseQuery->page($page, $limit)->select();

        // 为列表中的每个项目追加额外的信息字段。
        foreach ($list as $item) {
            $item->append(['used_num', 'send_num']);
        }

        // 返回包含数据总数和列表数据的数组。
        return compact('count', 'list');
    }


    /**
     * 系统列表查询方法
     * 该方法用于根据给定的条件和分页信息，从数据库中查询并返回符合条件的数据列表和总数。
     *
     * @param array $where 查询条件数组，用于构建SQL的WHERE子句。
     * @param int $page 当前页码，用于进行分页查询。
     * @param int $limit 每页数据的数量，用于控制分页查询的结果集大小。
     * @return array 返回包含总数和数据列表的数组。
     */
    public function sysLst(array $where, int $page, int $limit)
    {
        // 构建基础查询条件，设置偏移量为0，并应用$where参数作为查询条件
        $baseQuery = $this->dao->search(0, $where);

        // 计算符合条件的数据总数
        $count = $baseQuery->count($this->dao->getPk());

        // 进行分页查询，根据$page和$limit参数获取当前页的数据列表
        $list = $baseQuery->page($page, $limit)->select();

        // 遍历查询结果列表，为每个数据项追加'used_num'和'send_num'字段
        foreach ($list as $item) {
            $item->append(['used_num', 'send_num']);
        }

        // 将数据总数和数据列表打包成数组返回
        return compact('count', 'list');
    }
    /**
     * 获取指定条件下的VIP列表
     *
     * 此函数用于根据给定的条件查询VIP用户列表。它支持通过$uid来筛选出特定用户的VIP信息。
     * 通过使用with语法，可以优化查询性能，只加载所需的关联数据。
     *
     * @param array $where 查询条件数组，用于指定查询VIP的条件。
     * @param int $uid 用户ID，用于筛选特定用户的VIP信息。如果$uid为0或未提供，则不进行用户ID筛选。
     * @return array 返回查询结果的数组，包含符合条件的VIP用户信息。
     */
    public function sviplist(array $where,$uid)
    {
        // 初始化with数组，用于后续定义需要加载的关联数据
        $with = [];

        // 如果$uid有值，表示需要筛选特定用户的VIP信息，此时设置with来加载该用户的VIP期次信息
        if ($uid) {
            $with['svipIssue'] = function ($query) use ($uid) {
                // 在查询VIP期次信息时，添加条件限制只查询指定$uid的用户
                $query->where('uid', $uid);
            };
        }

        // 执行查询，首先调用validCouponQueryWithMerchant方法来构建查询基础条件，然后加载with定义的关联数据，最后通过setOption设置查询字段，并实际执行查询操作
        $list  = $this->validCouponQueryWithMerchant($where, $uid)
                      ->with($with)
                      ->setOption('field',[]) // 这里可能表示设置查询时的额外选项，例如在这里清空了查询的字段列表
                      ->field('C.*') // 明确指定查询的结果字段，这里查询的是VIP信息表中的所有字段
                      ->select();

        // 返回查询结果
        return $list;
    }

    /**
     * 获取API列表
     *
     * 该方法用于根据给定的条件、分页和限制参数，以及用户ID来获取API列表。
     * 这些条件用于查询满足特定业务需求的数据，例如，可能根据商家信息和发布者ID来过滤结果。
     * 方法还处理了PC端的特殊展示逻辑，仅返回满足特定条件的API列表。
     *
     * @param array $where 查询条件数组
     * @param int $page 当前页码
     * @param int $limit 每页记录数
     * @param int $uid 用户ID，用于查询特定用户的发布内容
     * @return array 包含总数和列表数据的数组
     */
    public function apiList(array $where, int $page, int $limit, $uid)
    {
        // 定义要包含的关联数据，首先是商家信息
        $with = [
            'merchant' => function ($query) {
                // 仅查询商家ID、名称和是否为交易商的信息
                $query->field('mer_id,mer_name,is_trader');
            },
        ];
        // 如果用户ID有效，则添加发布者的关联查询条件
        if ($uid) {
            $with['issue'] = function ($query) use ($uid) {
                // 根据用户ID查询发布的内容
                $query->where('uid', $uid);
            };
        }
        // 构建基础查询条件，包括验证和处理查询条件以及关联数据
        $baseQuery = $this->validCouponQueryWithMerchant($where, $uid)
            ->with($with);
        // 计算满足条件的总记录数
        $count = $baseQuery->count($this->dao->getPk());
        // 执行查询，获取分页后的API列表，并附加产品列表数据
        $list = $baseQuery->setOption('field',[])->field('C.*')->page($page, $limit)->select()->append(['ProductLst'])->toArray();
        foreach($list as $key => $val) {
            if(empty($val['ProductLst'])) {
                unset($list[$key]);
            }
        }
        $list = array_values($list);
        // 处理PC端的特殊展示逻辑，仅返回包含产品列表的前三个API
        $arr = [];
        if ($where['is_pc']) {
            foreach ($list as $item) {
                if ($item['ProductLst']) {
                    $arr[] = $item;
                }
                if (count($arr) >= 3) break;
            }
            $list = $arr ?? $list ;
        }
        // 返回包含总数和列表数据的数组
        return compact('count', 'list');
    }

    /**
     * 创建优惠券
     *
     * 该方法用于在数据库中创建一个新的优惠券。它首先检查传入的数据是否包含必要的字段，
     * 然后在事务中执行优惠券的创建和相关产品的绑定操作。如果在执行过程中遇到任何问题，
     * 事务将被回滚。
     *
     * @param array $data 包含优惠券信息和相关产品ID的数据数组
     * @throws ValidateException 如果未选择产品且优惠券类型为1，则抛出验证异常
     */
    public function create1(array $data)
    {
        // 如果提供了总数量，则计算剩余数量
        if (isset($data['total_count'])) {
            $data['remain_count'] = $data['total_count'];
        }

        // 使用事务来确保数据的一致性
        Db::transaction(function () use ($data) {
            // 提取产品ID，并删除不再需要的product_id键
            $products = array_column((array)$data['product_id'], 'id');
            unset($data['product_id']);

            // 如果优惠券类型为1且未选择任何产品，则抛出异常
            if ($data['type'] == 1 && !count($products)) {
                throw new ValidateException('请选择产品');
            }

            // 创建优惠券
            $coupon = $this->dao->create($data);

            // 如果没有选择任何产品，则直接返回创建的优惠券
            if (!count($products)) {
                return $coupon;
            }

            // 准备要插入的产品和优惠券关系数据
            $lst = [];
            foreach ($products as $product) {
                $lst[] = [
                    'product_id' => (int)$product,
                    'coupon_id' => $coupon->coupon_id
                ];
            }

            // 批量插入产品和优惠券的关系数据
            app()->make(StoreCouponProductDao::class)->insertAll($lst);
        });
    }

    /**
     * 克隆优惠券表单。
     * 该方法用于根据优惠券ID获取优惠券信息，并对其进行处理，以用于克隆一个新的优惠券表单。
     *
     * @param int $id 优惠券ID
     * @return array 克隆的优惠券表单数据
     */
    public function cloneCouponForm($id)
    {
        // 通过ID获取优惠券信息，包括与其相关的产品信息
        $couponInfo = $this->dao->getWith($id, ['product'])->toArray();

        // 如果优惠券已过期，将时间范围转换为数组格式
        if ($couponInfo['is_timeout']) {
            $couponInfo['range_date'] = [$couponInfo['start_time'], $couponInfo['end_time']];
        }

        // 如果优惠券有类型限制，将使用时间范围转换为数组格式
        if ($couponInfo['coupon_type']) {
            $couponInfo['use_start_time'] = [$couponInfo['use_start_time'], $couponInfo['use_end_time']];
        }

        // 初始化产品ID列表，用于后续处理
        $couponInfo['product_id'] = [];

        // 如果设置了相关产品，获取这些产品的ID和图片信息
        if (count($couponInfo['product'])) {
            $productIds = array_column($couponInfo['product'], 'product_id');
            /** @var ProductRepository $make */
            $make = app()->make(ProductRepository::class);
            $products = $make->productIdByImage($couponInfo['mer_id'], $productIds);
            foreach ($products as $product) {
                $couponInfo['product_id'][] = ['id' => $product['product_id'], 'src' => $product['image']];
            }
        }

        // 根据优惠券的最低使用金额，确定优惠券的使用类型
        $couponInfo['use_type'] = $couponInfo['use_min_price'] > 0 ? 1 : 0;

        // 创建并返回克隆的优惠券表单数据，设置表单标题为“复制优惠券”
        return $this->form()->formData($couponInfo)->setTitle('复制优惠券');
    }

    /**
     *  商户优惠券创建表单
     * @return Form
     * @throws FormBuilderException
     * @author xaboy
     * @day 2020/5/20
     */
    public function form()
    {
        return Elm::createForm(Route::buildUrl('merchantCouponCreate')->build(), [
            Elm::input('title', '优惠券名称：')->placeholder('请输入优惠卷名称')->attrs(['style' => ['width' => '460px']])->required('请输入优惠券名称'),
            Elm::radio('type', '优惠券类型：', 0)
                ->setOptions([
                    ['value' => 0, 'label' => '店铺券'],
                    ['value' => 1, 'label' => '商品券'],
                ])->control([
                    [
                        'value' => 1,
                        'rule' => [
                            Elm::frameImages('product_id', '商品', '/' . config('admin.merchant_prefix') . '/setting/storeProduct?field=product_id')
                                ->width('680px')->height('480px')->modal(['modal' => false])->prop('srcKey', 'src')->required('请选择商品'),
                        ]
                    ],
                ]),
            Elm::number('coupon_price', '优惠券面值：')->min(0)->precision(2)->required('请输入优惠券面值'),
            Elm::radio('use_type', ' 使用门槛：', 0)
                ->setOptions([
                    ['value' => 0, 'label' => '无门槛'],
                    ['value' => 1, 'label' => '有门槛'],
                ])->appendControl(0, [
                    Elm::hidden('use_min_price', 0)
                ])->appendControl(1, [
                    Elm::number('use_min_price', '优惠券最低消费')->min(0)->required('请输入优惠券最低消费'),
                ]),
            Elm::radio('coupon_type', '使用有效期：', 0)
                ->setOptions([
                    ['value' => 0, 'label' => '天数'],
                    ['value' => 1, 'label' => '时间段'],
                ])->control([
                    [
                        'value' => 0,
                        'rule' => [
                            Elm::number('coupon_time', '天数', 0)->min(0)->required('请输入优惠券有效期天数'),
                        ]
                    ],
                    [
                        'value' => 1,
                        'rule' => [
                            Elm::dateTimeRange('use_start_time', '时间段')->required('请选择优惠券有效期时间段'),
                        ]
                    ],
                ]),
            Elm::radio('is_timeout', '领取时间：', 0)->options([['label' => '限时', 'value' => 1], ['label' => '不限时', 'value' => 0]])
                ->appendControl(1, [Elm::dateTimeRange('range_date', ' ')->placeholder('不填为永久有效')]),
            Elm::radio('send_type', '获取方式：', 0)->setOptions([
                ['value' => self::GET_COUPON_TYPE_RECEIVE, 'label' => '领取'],
//                ['value' => 1, 'label' => '消费满赠'],
//                ['value' => 2, 'label' => '新人券'],
                ['value' => self::GET_COUPON_TYPE_ADMIN, 'label' => '后台发放'],
                ['value' => self::GET_COUPON_TYPE_PAY, 'label' => '赠送券'],
            ])->appendControl(1, [Elm::number('full_reduction', '满赠金额：', 0)->min(0)->placeholder('赠送优惠券的最低消费金额')]),
            Elm::radio('is_limited', '是否限量：', 0)->options([['label' => '限量', 'value' => 1], ['label' => '不限量', 'value' => 0]])
                ->appendControl(1, [Elm::number('total_count', '发布数量：', 0)->min(0)]),
            Elm::number('sort', '排序：', 0)->precision(0)->max(99999),
            Elm::radio('status', '状态：', 1)->options([['label' => '开启', 'value' => 1], ['label' => '关闭', 'value' => 0]]),
        ])->setTitle('发布优惠券');
    }

    /**
     * 接收优惠券
     *
     * 本函数用于处理用户领取优惠券的逻辑。它首先验证优惠券是否有效且未被领取，
     * 如果验证失败，则抛出异常提示用户。如果验证成功，则调用发送优惠券的函数，
     * 将优惠券发送给用户。
     *
     * @param int $id 优惠券ID
     * @param int $uid 用户ID
     * @throws ValidateException 如果优惠券失效或已领取，则抛出此异常
     */
    public function receiveCoupon($id, $uid)
    {
        // 验证优惠券是否有效且未被领取
        $coupon = $this->dao->validCoupon($id, $uid);
        if (!$coupon)
            throw new ValidateException('优惠券失效');

        // 检查优惠券是否已经发行，如果已发行则抛出异常
        if (!is_null($coupon['issue']))
            throw new ValidateException('优惠券已领取');

        // 发送优惠券给用户
        $this->sendCoupon($coupon, $uid,StoreCouponUserRepository::SEND_TYPE_RECEIVE);
    }

    /**
     * 接收SVIP优惠券
     *
     * 本函数用于处理用户接收SVIP优惠券的逻辑。它首先验证优惠券的有效性和可用性，
     * 然后将优惠券发送给用户。如果优惠券无效或已领取，则抛出异常。
     *
     * @param int $id 优惠券ID
     * @param int $uid 用户ID
     * @throws ValidateException 如果优惠券失效或已领取，则抛出此异常
     */
    public function receiveSvipCounpon($id,$uid)
    {
        // 验证优惠券是否有效且未被领取
        $coupon = $this->dao->validSvipCoupon($id, $uid);
        if (!$coupon)
            throw new ValidateException('优惠券失效');

        // 如果优惠券已被领取，则抛出异常
        if (!is_null($coupon['svipIssue']))
            throw new ValidateException('优惠券已领取');

        // 发送优惠券给用户
        $this->sendCoupon($coupon, $uid,StoreCouponUserRepository::SEND_TYPE_RECEIVE);
    }

    /**
     * 发送优惠券
     *
     * 该方法用于在系统中发送优惠券给指定用户。它首先触发一个'before'事件，允许任何监听此事件的代码在优惠券发送前执行操作。
     * 然后，在一个数据库事务中，执行发送优惠券的准备工作，包括校验和更新优惠券状态，并实际发行优惠券给用户。
     * 如果优惠券是有限制的，还会更新优惠券的剩余数量。发送完成后，触发相应的发送事件，以便监听此事件的代码可以执行后续操作。
     *
     * @param StoreCoupon $coupon 优惠券对象，包含要发送的优惠券的信息
     * @param int $uid 接收优惠券的用户ID
     * @param string $type 优惠券发送的类型，用于区分不同的发送场景
     */
    public function sendCoupon(StoreCoupon $coupon, $uid, $type)
    {
        // 触发发送优惠券前的事件，允许执行任何前置操作
        event('user.coupon.send.before', compact('coupon', 'uid', 'type'));

        // 使用数据库事务确保发送过程的原子性
        Db::transaction(function () use ($uid, $type, $coupon) {
            // 执行发送优惠券前的准备工作
            $this->preSendCoupon($coupon, $uid, $type);
            // 发行优惠券给指定用户
            app()->make(StoreCouponIssueUserRepository::class)->issue($coupon['coupon_id'], $uid);

            // 如果优惠券有限制次数，则更新优惠券的剩余数量
            if ($coupon->is_limited) {
                $coupon->remain_count--;
                $coupon->save();
            }
        });

        // 触发发送优惠券的事件，允许执行任何后续操作
        event('user.coupon.send', compact('coupon', 'uid', 'type'));
        // 触发特定类型的发送优惠券事件，提供更细粒度的事件订阅
        event('user.coupon.send.' . $type, compact('coupon', 'uid', 'type'));
    }


    /**
     * 预发送优惠券
     *
     * 该方法用于在发送优惠券前进行必要的数据准备和校验，确保优惠券的发送符合业务规则。
     * 主要用于优惠券的批量发送或自动发送场景，例如在用户下单后自动发送优惠券。
     *
     * @param StoreCoupon $coupon 优惠券对象，包含待发送的优惠券的具体信息。
     * @param int $uid 接收优惠券的用户ID，用于指定优惠券的接收者。
     * @param string $type 发送类型，默认为'send'，可扩展其他发送类型以满足不同的业务需求。
     * @return mixed 返回创建的优惠券用户记录，通常是一个包含优惠券ID等信息的对象或数组。
     */
    public function preSendCoupon(StoreCoupon $coupon, $uid, $type = 'send')
    {
        // 根据传入的优惠券、用户ID和发送类型，创建优惠券发送的数据对象。
        $data = $this->createData($coupon, $uid, $type);

        // 使用依赖注入获取StoreCouponUserRepository实例，并调用其create方法，将优惠券发送数据持久化到数据库中。
        return app()->make(StoreCouponUserRepository::class)->create($data);
    }

    /**
     * 创建优惠券数据
     *
     * 本函数用于根据传入的优惠券信息和用户ID，生成用于存储的优惠券数据数组。
     * 这包括了优惠券的基本信息，以及根据不同的优惠券类型和获取方式，设定相应的有效时间。
     *
     * @param StoreCoupon $coupon 优惠券对象，包含优惠券的详细信息
     * @param int $uid 用户ID，表示该优惠券归属的用户
     * @param string $type 优惠券的发放类型，默认为'send'，表示发放类型的枚举值
     * @return array 返回包含优惠券所有必要信息的数据数组
     */
    public function createData(StoreCoupon $coupon, $uid, $type = 'send')
    {
        // 初始化优惠券数据数组，包含基本属性
        $data = [
            'uid' => $uid,
            'coupon_title' => $coupon['title'],
            'coupon_price' => $coupon['coupon_price'],
            'use_min_price' => $coupon['use_min_price'],
            'type' => $type,
            'coupon_id' => $coupon['coupon_id'],
            'mer_id' => $coupon['mer_id']
        ];

        // 根据优惠券的获取类型，设置不同的有效时间
        if ($coupon['send_type'] == self::GET_COUPON_TYPE_SVIP) {
            // SVIP获取的优惠券，有效时间为当前月的第一天到下个月的最后一天
            $data['start_time'] = date('Y-m-d 00:00:00', time());
            $firstday = date('Y-m-01', time());
            $data['end_time'] = date('Y-m-d 23:59:59', strtotime("$firstday +1 month -1 day"));
        } else {
            // 根据优惠券类型，设置固定有效时间或即时生效并设定过期时间
            if ($coupon['coupon_type'] == 1) {
                // 固定有效期的优惠券，使用优惠券的开始和结束时间
                $data['start_time'] = $coupon['use_start_time'];
                $data['end_time'] = $coupon['use_end_time'];
            } else {
                // 即时生效的优惠券，有效期为当前时间往后推设定的天数
                $data['start_time'] = date('Y-m-d H:i:s');
                $data['end_time'] = date('Y-m-d H:i:s', strtotime("+ {$coupon['coupon_time']}day"));
            }
        }

        // 返回构建好的优惠券数据数组
        return $data;
    }

    /**
     *  优惠券发送费多用户
     * @param $uid
     * @param $id
     * @author Qinii
     * @day 2020-06-19
     */
    public function sendCouponByUser($uid, $id)
    {
        foreach ($uid as $item) {
            $coupon = $this->dao->validCoupon($id, $item);
            if (!$coupon || !is_null($coupon['issue']))
                continue;
            if ($coupon->is_limited && 0 == $coupon->remain_count)
                continue;
            $this->sendCoupon($coupon, $item,StoreCouponUserRepository::SEND_TYPE_RECEIVE);
        }
    }

    /**
     * 更新优惠券信息的表单生成方法
     *
     * 本方法用于根据给定的商家ID和优惠券ID，从数据库中获取相关数据，并生成用于更新优惠券名称的表单。
     * 如果找不到对应的数据，则抛出一个验证异常。
     *
     * @param int $merId 商家ID，用于查询特定商家的优惠券信息。
     * @param int $id 优惠券ID，用于查询特定优惠券的信息。
     * @return Elm 表单元素对象，包含了更新优惠券名称的表单及其验证规则。
     * @throws ValidateException 如果找不到对应的优惠券数据，则抛出此异常。
     */
    public function updateForm(int $merId, int $id)
    {
        // 根据商家ID和优惠券ID查询数据库中的优惠券信息
        $data = $this->dao->getWhere(['mer_id' => $merId, 'coupon_id' => $id]);

        // 如果查询结果为空，则表示找不到相关数据，抛出异常
        if (!$data) throw new ValidateException('数据不存在');

        // 创建表单对象，并设置表单提交的URL
        $form = Elm::createForm(Route::buildUrl('systemCouponUpdate', ['id' => $id])->build());

        // 设置表单的验证规则，包括优惠券名称的输入字段及其验证规则
        $form->setRule([
            Elm::input('title', '优惠券名称', $data['title'])->required('请输入优惠券名称'),
        ]);

        // 设置表单的标题为“编辑优惠券名称”
        return $form->setTitle('编辑优惠券名称');
    }


    /**
     * 平台优惠券创建表单
     * @return Form
     * @author Qinii
     */
    public function sysForm()
    {
        return Elm::createForm(Route::buildUrl('systemCouponCreate')->build(), [
            Elm::input('title', '优惠券名称：')->placeholder('请输入优惠卷名称')->attrs(['style' => ['width' => '460px']])->required('请输入优惠券名称'),
            Elm::radio('type', '优惠券类型：', 10)
                ->setOptions([
                    ['value' => self::TYPE_PLATFORM_ALL, 'label' => '通用券'],
                    ['value' => self::TYPE_PLATFORM_CATE, 'label' => '品类券'],
                    ['value' => self::TYPE_PLATFORM_STORE, 'label' => '跨店券'],
                ])->control([
                    [
                        'value' => self::TYPE_PLATFORM_CATE,
                        'rule' => [
                            Elm::cascader('cate_ids', '选择品类：')->options(function (){
                                return app()->make(StoreCategoryRepository::class)->getTreeList(0, 1);
                            })->props(['props' => ['checkStrictly' => true, 'emitPath' => false, 'multiple' => true]])
                        ]
                    ],
                    [
                        'value' => self::TYPE_PLATFORM_STORE,
                        'rule' => [
                            Elm::radio('mer_type', '选择商户：',2)
                                ->setOptions([
                                    ['value' => 1, 'label' => '分类筛选'],
                                    ['value' => 2, 'label' => '指定店铺'],
                                ])->control([
                                    [
                                        'value' => 1,
                                        'rule' => [
                                            Elm::select('is_trader', '商户类别：')->options([
                                                ['value' => '', 'label' => '全部'],
                                                ['value' => 1, 'label' => '自营'],
                                                ['value' => 0, 'label' => '非自营'],
                                            ]),
                                            Elm::select('category_id', '商户分类：')->options(function (){
                                                $options = app()->make(MerchantCategoryRepository::class)->allOptions();
                                                $options = array_merge([['value' => '', 'label' => '全部']],$options);
                                                return $options;
                                            }),
                                            Elm::select('type_id', '店铺类型：')->options(function (){
                                                $options = app()->make(MerchantTypeRepository::class)->getOptions();
                                                return array_merge([['value' => '', 'label' => '全部']],$options);
                                            })
                                        ]
                                    ],
                                    [
                                        'value' => 2,
                                        'rule' => [
                                            Elm::frameImages('mer_ids', '商户：', '/' . config('admin.admin_prefix') . '/setting/crossStore?field=mer_ids')
                                                ->width('680px')
                                                ->height('480px')
                                                ->modal(['modal' => false])
                                                ->prop('srcKey', 'src')
                                                ->required('请选择优惠券适用店铺'),
                                        ]
                                    ],
                                ]),
                        ]
                    ],
                ]),
            Elm::number('coupon_price', '优惠券面值：')->min(0)->precision(2)->required('请输入优惠券面值'),
            Elm::radio('use_type', ' 使用门槛：', 0)
                ->setOptions([
                    ['value' => 0, 'label' => '无门槛'],
                    ['value' => 1, 'label' => '有门槛'],
                ])->appendControl(0, [
                    Elm::hidden('use_min_price', 0)
                ])->appendControl(1, [
                    Elm::number('use_min_price', '优惠券最低消费：')->min(0)->required('请输入优惠券最低消费'),
                ]),
            Elm::radio('send_type', '获取方式：', 0)->setOptions([
                ['value' => self::GET_COUPON_TYPE_RECEIVE, 'label' => '领取'],
                //['value' => self::GET_COUPON_TYPE_NEW, 'label' => '新人券'],
                ['value' => self::GET_COUPON_TYPE_ADMIN, 'label' => '后台发送'],
                ['value' => self::GET_COUPON_TYPE_SVIP, 'label' => '付费会员券'],
            ])->control([
                [
                    'value' => self::GET_COUPON_TYPE_RECEIVE,
                    'rule' => [
                        Elm::radio('coupon_type', '使用有效期：', 0)
                            ->setOptions([
                                ['value' => 0, 'label' => '天数'],
                                ['value' => 1, 'label' => '时间段'],
                            ])->control([
                                [
                                    'value' => 0,
                                    'rule' => [
                                        Elm::number('coupon_time', '天数', 0)->min(0)->required('请输入优惠券有效期天数'),
                                    ]
                                ],
                                [
                                    'value' => 1,
                                    'rule' => [
                                        Elm::dateTimeRange('use_start_time', '时间段')->required('请选择优惠券有效期时间段'),
                                    ]
                                ],
                            ]),
                        Elm::radio('is_timeout', '领取时间：', 0)->options([['label' => '限时', 'value' => 1], ['label' => '不限时', 'value' => 0]])
                            ->appendControl(1, [Elm::dateTimeRange('range_date', ' ')->placeholder('不填为永久有效')]),
                    ]
                ],
                [
                    'value' => self::GET_COUPON_TYPE_NEW,
                    'rule' => [
                        Elm::radio('coupon_type', '使用有效期：', 0)
                            ->setOptions([
                                ['value' => 0, 'label' => '天数'],
                                ['value' => 1, 'label' => '时间段'],
                            ])->control([
                                [
                                    'value' => 0,
                                    'rule' => [
                                        Elm::number('coupon_time', '天数', 0)->min(0)->required('请输入优惠券有效期天数'),
                                    ]
                                ],
                                [
                                    'value' => 1,
                                    'rule' => [
                                        Elm::dateTimeRange('use_start_time', '时间段')->required('请选择优惠券有效期时间段'),
                                    ]
                                ],
                            ]),
                        Elm::radio('is_timeout', '领取时间：', 0)->options([['label' => '限时', 'value' => 1], ['label' => '不限时', 'value' => 0]])
                            ->appendControl(1, [Elm::dateTimeRange('range_date', ' ')->placeholder('不填为永久有效')]),
                    ]
                ],
                [
                    'value' => self::GET_COUPON_TYPE_ADMIN,
                    'rule' => [
                        Elm::radio('coupon_type', '使用有效期：', 0)
                            ->setOptions([
                                ['value' => 0, 'label' => '天数'],
                                ['value' => 1, 'label' => '时间段'],
                            ])->control([
                                [
                                    'value' => 0,
                                    'rule' => [
                                        Elm::number('coupon_time', '天数', 0)->min(0)->required('请输入优惠券有效期天数'),
                                    ]
                                ],
                                [
                                    'value' => 1,
                                    'rule' => [
                                        Elm::dateTimeRange('use_start_time', '时间段')->required('请选择优惠券有效期时间段'),
                                    ]
                                ],
                            ])
                    ]
                ],
            ])->appendControl(1, [
                Elm::number('full_reduction', '满赠金额：', 0)->min(0)->placeholder('赠送优惠券的最低消费金额')
            ])->appendRule('suffix', [
                'type' => 'div',
                'style' => ['color' => '#999999'],
                'domProps' => [
                    'innerHTML' =>'会员优惠券创建成功后会自动发送给创建时间之后的新付费会员；
                    之后每月1日零点自动发送给所有付费会员；在创建优惠券之前已成为付费会员的用户可在会员中心手动领取优惠券
                    未使用优惠券，每月初会清空。',
                ]
            ]),
            Elm::radio('is_limited', '是否限量：', 0)->options([['label' => '限量', 'value' => 1], ['label' => '不限量', 'value' => 0]])
                ->appendControl(1, [Elm::number('total_count', '发布数量：', 0)->min(0)]),
            Elm::number('sort', '排序：', 0)->precision(0)->max(99999),
            Elm::radio('status', '状态：', 1)->options([['label' => '开启', 'value' => 1], ['label' => '关闭', 'value' => 0]]),
        ])->setTitle('发布优惠券');
    }


    /**
     * 创建优惠券
     * 根据传入的数据不同，创建不同类型的优惠券，并关联不同的产品或分类
     *
     * @param array $data 创建优惠券所需的数据，包含优惠券类型、产品ID、分类ID等
     * @throws ValidateException 当未选择产品、分类或商户时抛出异常
     */
    public function create($data)
    {
        // 如果设置了总数量，则计算剩余数量
        if (isset($data['total_count'])) {
            $data['remain_count'] = $data['total_count'];
        }

        // 优惠券产品类型，默认为0（表示普通商品）
        $productType = 0;
        // 产品ID数组
        $products = [];

        // 根据优惠券类型处理不同的逻辑
        switch ($data['type']) {
            case 1: // 商品优惠券
                // 提取产品ID，去除无关数据
                $products = array_column((array)$data['product_id'], 'id');
                unset($data['product_id']);
                // 如果没有选择产品，抛出异常
                if (!count($products)) {
                    throw new ValidateException('请选择产品');
                }
                break;
            case 11: // 分类优惠券
                // 提取分类ID，去除无关数据
                $products = $data['cate_ids'];
                unset($data['cate_ids']);
                // 如果没有选择分类，抛出异常
                if (!count($products)) {
                    throw new ValidateException('请选择产品分类');
                }
                // 设置优惠券产品类型为1（表示分类优惠券）
                $productType = 1;
                break;
            case 12: // 商户优惠券
                // 判断是否按条件查询商户
                if ($data['mer_type'] == 1) {
                    // 商户查询条件
                    $where = [
                        'type_id' => $data['type_id'],
                        'is_trader' => $data['is_trader'],
                        'category_id' => $data['category_id'],
                    ];
                    // 根据条件查询商户ID
                    $products = app()->make(MerchantRepository::class)->search($where)->column('mer_id');
                    // 如果没有符合条件的商户，抛出异常
                    if (!count($products)) {
                        throw new ValidateException('选择条件下无商户');
                    }
                } else {
                    // 直接提取商户ID
                    $products = array_column((array)$data['mer_ids'], 'id');
                    // 如果没有选择商户，抛出异常
                    if (!count($products)) {
                        throw new ValidateException('请选择商户');
                    }
                }
                // 设置优惠券产品类型为2（表示商户优惠券）
                $productType = 2;
                break;
        }

        // 去除创建优惠券无关的数据
        unset(
            $data['product_id'],
            $data['cate_ids'],
            $data['is_trader'],
            $data['mer_type'],
            $data['category_id'],
            $data['mer_ids']
        );

        // 使用事务执行以下操作，确保数据的一致性
        Db::transaction(function () use ($data, $products, $productType) {
            // 创建优惠券
            $coupon = $this->dao->create($data);
            // 如果没有产品需要关联，直接返回创建的优惠券
            if (!count($products)) {
                return $coupon;
            }

            // 准备要插入的优惠券-产品关联数据
            $lst = [];
            foreach ($products as $product) {
                $lst[] = [
                    'product_id' => (int)$product,
                    'coupon_id' => $coupon->coupon_id,
                ];
            }
            // 批量插入优惠券-产品关联数据
            app()->make(StoreCouponProductDao::class)->insertAll($lst);
        });
    }


    /**
     * 克隆系统优惠券表单。
     * 该方法用于根据给定的优惠券ID，获取优惠券信息并克隆一份新的优惠券表单数据。
     * 主要处理了优惠券的时间范围、使用条件、产品关联等信息的整理，以便于形成新的优惠券创建表单。
     *
     * @param int $id 优惠券ID
     * @return array 克隆后的优惠券表单数据
     */
    public function cloneSysCouponForm($id)
    {
        // 根据优惠券ID获取优惠券详细信息，包括关联的产品信息
        $couponInfo = $this->dao->getWith($id, ['product'])->toArray();

        // 如果优惠券已过期，将时间范围转换为数组格式
        if ($couponInfo['is_timeout']) {
            $couponInfo['range_date'] = [$couponInfo['start_time'], $couponInfo['end_time']];
        }

        // 如果优惠券有类型限制，转换使用时间范围为数组格式
        if ($couponInfo['coupon_type']) {
            $couponInfo['use_start_time'] = [$couponInfo['use_start_time'], $couponInfo['use_end_time']];
        }

        // 初始化产品ID列表，用于后续处理
        $couponInfo['product_id'] = [];

        // 如果有指定产品，根据优惠券类型处理产品信息
        if (count($couponInfo['product'])) {
            if ($couponInfo['type'] == 11) {
                // 对于类型为11的优惠券，将产品ID收集到cate_ids数组中
                foreach ($couponInfo['product'] as $product) {
                    $couponInfo['cate_ids'][] = $product['product_id'];
                }
            } else {
                // 对于其他类型的优惠券，获取商家信息，并将商家ID和图片信息收集到mer_ids数组中
                $productIds = array_column($couponInfo['product'], 'product_id');
                $make = app()->make(MerchantRepository::class);
                $products = $make->merIdByImage($productIds);
                foreach ($products as $product) {
                    $couponInfo['mer_ids'][] = ['id' => $product['mer_id'], 'src' => $product['mer_avatar']];
                }
            }
        }

        // 根据优惠券的最低使用金额，确定是否有限制使用条件
        $couponInfo['use_type'] = $couponInfo['use_min_price'] > 0 ? 1 : 0;

        // 移除原始产品信息，避免数据冗余
        unset($couponInfo['product']);

        // 返回处理后的优惠券信息，用于生成新的优惠券表单
        return $this->sysForm()->formData($couponInfo)->setTitle('复制优惠券');
    }

    /**
     * 根据优惠券ID获取产品列表
     *
     * 本函数通过优惠券ID来查询相关的产品信息，支持根据优惠券类型筛选不同的产品来源。
     * 例如，当优惠券类型为品类时，将返回该品类下的所有产品；当优惠券类型为商户时，将返回该商户的所有产品。
     *
     * @param int $id 优惠券ID
     * @param int $page 当前页码
     * @param int $limit 每页显示数量
     * @return array 包含产品总数和产品列表的数组
     * @throws ValidateException 当查询的信息不存在时抛出异常
     */
    public function getProductList(int $id,int $page,int $limit)
    {
        // 通过优惠券ID获取优惠券信息
        $res = $this->dao->get($id);
        // 实例化优惠券产品仓库，用于后续根据优惠券ID查询相关产品ID
        $productRepository = app()->make(StoreCouponProductRepository::class);
        // 根据优惠券ID查询相关产品ID
        $ids = $productRepository->search(['coupon_id' => $id])->column('product_id');

        // 初始化产品总数和列表
        $count = 0;
        $list = [];

        // 根据优惠券类型进行不同逻辑的查询
        switch ($res['type']) {
            case 11: // 品类优惠券
                // 如果品类ID为空，抛出异常
                if (empty($ids)) throw new ValidateException('品类信息不存在');
                // 实例化品类仓库，用于查询子品类ID
                $storeCategoryRepository = app()->make(StoreCategoryRepository::class);
                // 获取所有子品类ID，并合并原品类ID
                $cateId = $storeCategoryRepository->selectChildrenId($ids);
                $cateId = array_merge($cateId, $ids);
                // 实例化产品仓库，查询属于这些品类ID的产品
                $query = app()->make(ProductRepository::class)->getSearch([])->whereIn('cate_id', $cateId);
                // 定义查询的字段
                $field = 'product_id,store_name,image,stock,price,sales,cate_id';
                // 计算产品总数
                $count = $query->count();
                // 分页查询产品，并指定查询字段
                $list = $query->page($page, $limit)->setOption('field',[])->field($field)->select();
                break;
            case 12: // 商户优惠券
                // 如果商户ID为空，抛出异常
                if (empty($ids)) throw new ValidateException('商户信息不存在');
                // 实例化商户仓库，用于查询商户信息
                $make = app()->make(MerchantRepository::class);
                // 定义查询字段
                $field = 'mer_id,category_id,type_id,mer_name,mer_phone,is_trader';
                // 带上关联信息查询商户，并根据商户ID筛选
                $with = ['merchantType','merchantCategory'];
                $query = $make->search([])->whereIn($make->getPk(),$ids)->with($with);
                // 计算商户总数
                $count = $query->count();
                // 分页查询商户，并指定查询字段
                $list = $query->page($page, $limit)->setOption('field',[])->field($field)->select();
                break;
            default:
                // 如果优惠券类型不匹配任何条件，不执行任何操作
                break;
        }

        // 返回产品总数和列表的数组
        return compact('count', 'list');
    }

    /**
     * 发送SVIP优惠券
     * 该方法用于向系统中的SVIP用户发送特定类型的优惠券。它首先查询符合条件的优惠券ID和SVIP用户ID，
     * 然后针对每个优惠券，找出尚未收到该优惠券的SVIP用户，最后为这些用户发送优惠券。
     */
    public function sendSvipCoupon()
    {
        // 初始化数据数组，用于存储发送优惠券所需的信息
        $data = ['mark' => [], 'is_all' => '', 'search' => '',];

        // 查询所有SVIP用户的UID
        $uids = app()->make(UserRepository::class)->search(['is_svip' => 1])->column('uid');

        // 获取StoreCouponIssueUserRepository实例，用于后续检查用户是否已领取优惠券
        $isMake = app()->make(StoreCouponIssueUserRepository::class);

        // 获取StoreCouponSendRepository实例，用于发送优惠券
        $senMake = app()->make(StoreCouponSendRepository::class);

        // 查询可用的SVIP优惠券ID
        $couponIds = $this->dao->validCouponQuery(null, StoreCouponRepository::GET_COUPON_TYPE_SVIP)->column('coupon_id');

        // 如果有可用的优惠券ID和SVIP用户，那么开始发送优惠券
        if ($couponIds && $uids) {
            foreach ($couponIds as $item) {
                // 查询已领取过该优惠券的用户UID
                $issUids = $isMake->getSearch([])->whereMonth('create_time')->whereIn('uid', $uids)->column('uid');

                // 找出尚未领取该优惠券的SVIP用户UID
                $uids_ = array_values(array_diff($uids, $issUids));

                // 为当前优惠券设置待发送的用户UID
                $data['coupon_id'] = $item;
                $data['uid'] = $uids_;

                // 如果有用户尚未领取该优惠券，则发送优惠券
                if (!empty($data['uid'])) {
                    return $senMake->create($data, 0);
                }
            }
        }
    }
    /**
     * 获取商品赠送的优惠券信息
     *
     * @param array $giveCouponIds
     * @return void
     */
    public function getProductGiveCoupons(array $giveCouponIds)
    {
        if(empty($giveCouponIds)){
            return [];
        }

        return $this->selectWhere([['coupon_id', 'in', $giveCouponIds]], 'coupon_id,title')->toArray();
    }
}
