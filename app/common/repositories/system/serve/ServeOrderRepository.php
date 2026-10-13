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

namespace app\common\repositories\system\serve;

use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\AddShortUrlResponseBody\data;
use app\common\dao\system\serve\ServeOrderDao;
use app\common\model\system\serve\ServeOrder;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\store\product\ProductCopyRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\user\UserBillRepository;
use app\common\repositories\user\UserRepository;
use crmeb\services\merchant\MerchantCoreService;
use crmeb\services\pay\Pay;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;

class ServeOrderRepository extends BaseRepository
{
    protected $dao;

    public function __construct(ServeOrderDao $dao)
    {
        $this->dao = $dao;
    }
    //复制商品
    const TYPE_COPY_PRODUCT = 1;
    //电子面单
    const TYPE_DUMP = 2;
    //保证金 margin
    const TYPE_MARGIN = 10;
    //补缴
    const TYPE_MARGIN_MAKE_UP = 11;
    //同城配送delivery
    const TYPE_DELIVERY = 20;

    const PAY_TYPE_WEIXIN = 1;
    const PAY_TYPE_ALIPAY= 2;
    const PAY_TYPE_SYS = 3;


    /**
     * 购买一号通 支付
     * @param $merId
     * @param $data
     * @return array
     * @author Qinii
     * @day 1/26/22
     */
    public function meal($merId, $data)
    {
        $ret = app()->make(ServeMealRepository::class)->get($data['meal_id']);
        if(!$ret)  throw new ValidateException('数据不存在');
        $key = 'Meal_'.$merId.'_'.$data['meal_id'].'_'.date('YmdH',time());
        $arr = [
            'meal_id' => $ret['meal_id'],
            'name'    => $ret['name'],
            'num'     => $ret['num'],
            'price'   => $ret['price'],
            'type'    => $ret['type'],
        ];
        $param = [
            'status' => 0,
            'is_del' => 0,
            'mer_id' => $merId,
            'type'   => $ret['type'],
            'meal_id'=> $ret['meal_id'],
            'pay_type' => $data['pay_type'],
            'attach' => 'meal',
            'order_info' => json_encode($arr,JSON_UNESCAPED_UNICODE),
            'pay_price' => $ret['price'],
        ];
        return compact('key', 'param');
    }

    /**
     * 商户保证金 支付
     * @param $merId
     * @param $data
     * @return array
     * @author Qinii
     * @day 1/26/22
     */
    public function margin($merId, $data)
    {
        $ret = app()->make(MerchantRepository::class)->get($merId);
        if ($ret['is_margin'] !== 1 && $data['type'] == 10)
            throw new ValidateException('此商户无需支付保证金');
        if ($data['type'] == self::TYPE_MARGIN_MAKE_UP) {
            $margin = bcsub($ret->ot_margin,$ret->margin,2);
        } else {
            $margin = $ret['margin'];
        }
        $key = 'Margin_'.$merId.'_'.date('YmdH',time());
        $arr = [
            'type_id'   => $ret['type_id'],
            'is_margin' => $ret['is_margin'],
            'margin'    => $margin,
        ];
        $param = [
            'status' => 0,
            'is_del' => 0,
            'mer_id' => $merId,
            'type'   => self::TYPE_MARGIN,
            'meal_id'=> $ret['type_id'],
            'pay_type' => $data['pay_type'],
            'attach' => 'meal',
            'order_info' => json_encode($arr,JSON_UNESCAPED_UNICODE),
            'pay_price' => $margin
        ];
        return compact('key', 'param');
    }

    /**
     * 处理配送相关信息的函数
     *
     * 该函数用于生成与配送相关的键名和参数，以供后续处理订单或配送时使用。
     * 它结合了商家ID、当前时间和订单金额等信息，创建了一个唯一且难以伪造的键名，
     * 同时也构造了订单的参数数组，包含了订单的状态、支付方式等重要信息。
     *
     * @param string $merId 商家ID，用于区分不同商家的订单
     * @param array $data 包含订单价格和支付方式等信息的数据数组
     * @return array 返回包含键名和参数的数组，供其他部分使用
     */
    public function delivery($merId, $data)
    {
        // 生成唯一的键名，用于标识和存储订单信息
        $key = 'Delivery_'.$merId.'_'.md5(date('YmdH',time()).$data['price']);

        // 初始化订单详细信息数组，这里仅包含订单价格
        $arr = ['price' => $data['price']];

        // 构造订单参数数组，包含了订单的各种状态和信息
        $param = [
            'status' => 0, // 订单状态，初始为0表示未处理
            'is_del' => 0, // 订单删除状态，0表示未删除
            'mer_id' => $merId, // 商家ID
            'type'   => 20, // 订单类型，这里假设为20表示配送订单
            'meal_id'=> 0, // 餐品ID，初始为0，可能根据实际业务进行修改
            'pay_type' => $data['pay_type'], // 支付方式，从$data中获取
            'attach' => 'meal', // 订单附加信息，这里标识为meal
            'order_info' => json_encode($arr,JSON_UNESCAPED_UNICODE), // 将订单详细信息编码为JSON格式
            'pay_price' => $data['price'], // 订单支付价格，从$data中获取
        ];

        // 返回包含键名和参数的数组
        return compact('key', 'param');
    }


    /**
     * 生成二维码
     *
     * 该方法用于根据商家ID、二维码类型和具体数据生成相应的二维码配置。
     * 它首先尝试从缓存中获取二维码配置，如果缓存不存在，则生成新的二维码配置，
     * 并将其与相关数据一起存储在缓存中，以供后续使用。
     *
     * @param int $merId 商家ID，用于标识商家
     * @param string $type 二维码类型，决定使用哪种方式生成二维码
     * @param array $data 生成二维码所需的具体数据，包括支付类型等
     * @return array 返回包含二维码配置、过期时间和价格的信息
     */
    public function QrCode(int $merId, string $type, array $data)
    {
        // 根据二维码类型调用相应的方法，并获取生成二维码所需的键值和参数
        $res = $this->{$type}($merId, $data);
        $key = $res['key'];
        $param = $res['param'];

        // 尝试从缓存中获取二维码配置
        if(!$result = Cache::get($key)){
            // 生成新的订单号
            $order_sn = app()->make(StoreOrderRepository::class)->getNewOrderId(StoreOrderRepository::TYPE_SN_SERVER_ORDER);
            // 使用订单号作为订单描述和支付body
            $param['order_sn'] = $order_sn;
            $param['body'] = $order_sn;
            // 根据支付类型直接调用 Pay 驱动
            $payType = $data['pay_type'] == 1 ? 'weixinQr' : 'alipayQr';
            if ($payType === 'weixinQr') {
                $code =  (new Pay('weixin'))->pay('weixinQr', $param, '');
            } else {
                $param['attach'] = 'meal';
                $code = (new Pay('alipay'))->pay('alipayQr', $param, '');
            }

            // 设置二维码过期时间，并生成包含配置、过期时间和价格的数组
            $endtime = time() + 1800 ;
            $result = [
                'config' => $code['config'],
                'endtime'=> date('Y-m-d H:i:s',$endtime),
                'price'  => $param['pay_price']
            ];
            // 将二维码配置存储在缓存中，设置过期时间为30分钟
            Cache::set($key,$result,30);
            // 将订单号和相关参数存储在缓存中，过期时间为24小时
            $param['key'] = $key;
            Cache::set($order_sn,$param,60 * 24);
        }

        // 返回二维码配置信息
        return $result;
    }

    /**
     * 处理支付成功的逻辑。
     * 当收到支付成功的通知时，本函数将验证订单是否存在。如果订单不存在于数据库中，则尝试从缓存中恢复订单数据，
     * 并将订单状态更新为已支付，同时清除相关的缓存数据。
     *
     * @param array $data 包含订单信息的数据数组，其中应包含订单号(order_sn)。
     */
    public function paySuccess($data)
    {
        // 根据订单号尝试从数据库中获取订单信息
        $get = $this->dao->getWhere(['order_sn' => $data['order_sn']]);
        // 如果订单不存在于数据库中
        if(!$get){
            // 尝试从缓存中获取订单数据
            $dat = Cache::get($data['order_sn']);
            $key = $dat['key'];
            // 移除不需要的字段，以准备更新数据库中的订单信息
            unset($dat['attach'],$dat['body'],$dat['key']);

            // 设置订单状态为已支付，并记录支付时间
            $dat['status'] = 1;
            $dat['pay_time'] = date('y_m-d H:i:s', time());
            // 使用事务处理来确保数据的一致性
            Db::transaction(function () use($data, $dat,$key){
                // 在数据库中创建（或更新）订单信息
                $res = $this->dao->create($dat);
                // 处理支付成功后的逻辑，如发送通知等
                $this->payAfter($dat,$res);
                // 清除订单号和key相关的缓存数据
                Cache::delete($data['order_sn']);
                Cache::delete($key);
            });
        }
    }

    /**
     * 根据支付类型处理支付后业务逻辑
     *
     * @param array $dat 包含订单、商户、支付金额、服务类型等信息的数组。
     * @param mixed|null $ret 支付订单模型或保留参数。
     * @return void
     * @throws ValidateException 保证金、配送余额等核心字段写入未授权或业务校验失败时抛出。
     */
    public function payAfter($dat, $ret = null)
    {
        // 解析订单信息
        $info = json_decode($dat['order_info']);

        // 根据支付类型执行不同的业务逻辑
        switch ($dat['type']) {
            case self::TYPE_COPY_PRODUCT:
                // 处理复制产品套餐支付
                app()->make(ProductCopyRepository::class)->add([
                    'type' => 'pay_copy',
                    'num' => $info->num,
                    'info' => $dat['order_info'],
                    'message' => '购买复制商品套餐',
                ], $dat['mer_id']);
                break;
            case self::TYPE_DUMP:
                // 处理电子面单套餐支付
                app()->make(ProductCopyRepository::class)->add([
                    'type' => 'pay_dump',
                    'num' => $info->num,
                    'info' => $dat['order_info'],
                    'message' => '购买电子面单套餐',
                ], $dat['mer_id']);
                break;
            case self::TYPE_MARGIN:
                // 处理保证金支付逻辑
                $res = app()->make(MerchantRepository::class)->get($dat['mer_id']);
                if ($res['is_margin'] == 1) {
                    // 如果已经缴纳保证金，则更新保证金金额
                    $margin = $res->margin;
                    $ot_margin = $res->margin;
                    $_margin = $res->margin;
                } else {
                    // 否则，更新原保证金和现保证金金额，并计算差额
                    $margin = $res->ot_margin;
                    $ot_margin = $res->ot_margin;
                    $_margin = bcsub($res->ot_margin, $res->margin, 2);
                }
                if (bccomp($_margin, $dat['pay_price'], 2) === 0) {
                    // 如果支付金额与差额相符，更新保证金状态
                    $res->ot_margin = $ot_margin;
                    $res->margin = $margin;
                    $res->margin_remind_time = '';
                    $res->is_margin = 10;
                } else {
                    // 否则，设置为保证金支付异常状态
                    // 支付金额与订单金额不一致
                    $res->is_margin = 20;
                }
                app()->make(MerchantCoreService::class)->writeMerchant((int)$dat['mer_id'], ['ot_margin', 'margin', 'margin_remind_time', 'is_margin'], function () use ($res) {
                    $res->save();
                }, 'serve_order_margin_pay');
                // 记录保证金缴纳账单
                $bill = [
                    'title' => '线上缴纳保证金',
                    'mer_id' => $dat['mer_id'],
                    'number' => $dat['pay_price'],
                    'mark' => '缴纳保证金',
                    'balance' => $res->margin,
                ];
                app()->make(UserBillRepository::class)->bill(0, 'mer_margin', 'mer_margin', 1, $bill);
                break;
            case self::TYPE_MARGIN_MAKE_UP:
                // 处理保证金补缴逻辑
                $res = app()->make(MerchantRepository::class)->get($dat['mer_id']);
                if (bccomp($res['margin'], $dat['pay_price'], 2) === 0) {
                    // 如果支付金额与保证金补齐金额相符，更新保证金余额
                    $res->margin = bcadd($res['margin'], $dat['pay_price'], 2);
                    $res->margin_remind_time = '';
                    $res->is_margin = 10;
                } else {
                    // 否则，设置为保证金支付异常状态
                    // 支付金额与订单金额不一致
                    $res->is_margin = 20;
                }
                app()->make(MerchantCoreService::class)->writeMerchant((int)$dat['mer_id'], ['margin', 'margin_remind_time', 'is_margin'], function () use ($res) {
                    $res->save();
                }, 'serve_order_margin_make_up');
                // 记录保证金补缴账单
                $bill = [
                    'mer_id' => $dat['mer_id'],
                    'number' => $dat['pay_price'],
                    'mark' => '线上补缴保证金',
                    'balance' => $res->margin,
                ];
                app()->make(UserBillRepository::class)->bill(0, 'mer_margin', 'pay_margin', 1, $bill);
                break;
            case self::TYPE_DELIVERY:
                // 处理同城配送充值
                $res = app()->make(MerchantRepository::class)->get($dat['mer_id']);
                if ($res) {
                    // 更新配送余额
                    $res->delivery_balance = bcadd($res->delivery_balance, $dat['pay_price'], 2);
                    app()->make(MerchantCoreService::class)->writeMerchant((int)$dat['mer_id'], ['delivery_balance'], function () use ($res) {
                        $res->save();
                    }, 'serve_order_delivery_recharge');
                } else {
                    // 记录异常信息
                    Log::info('同城配送充值异常 ：' . json_encode($dat));
                }
                break;
            default:
                break;
        }
        return;
    }


    /**
     * 获取服务订单列表
     *
     * 根据给定的条件和分页参数，从数据库中检索服务订单列表。此方法专注于查询操作，不涉及数据的增删改。
     *
     * @param array $where 查询条件，允许通过数组传递多个条件。
     * @param int $page 当前页码，用于分页查询。
     * @param int $limit 每页的记录数，用于分页查询。
     * @return array 返回包含订单总数和订单列表的数组。
     */
    public function getList(array $where, int $page, int $limit)
    {
        // 默认不查询已删除的订单
        $where['is_del'] = 0;

        // 构建查询语句，包括关联查询和排序
        $query = $this->dao->search($where)
            ->with([
                'merchant' => function($query){
                    // 关联查询商家信息，包括商家类型，并只选取需要的字段
                    $query->with(['merchantType']);
                    $query->field('mer_id,mer_name,is_trader,mer_avatar,type_id,mer_phone,mer_address,is_margin,margin,real_name,ot_margin');
                }
            ])
            ->order('ServeOrder.create_time DESC');

        // 计算满足条件的订单总数
        $count = $query->count();

        // 分页查询满足条件的订单列表
        $list = $query->page($page, $limit)->select();

        // 将订单总数和订单列表打包成数组返回
        return compact('count', 'list');
    }
}
