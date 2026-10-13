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
namespace app\common\repositories\delivery;

use app\common\dao\delivery\DeliveryStationDao;
use app\common\repositories\BaseRepository;
use app\common\repositories\system\config\ConfigClassifyRepository;
use app\common\repositories\system\config\ConfigValueRepository;
use crmeb\services\DeliverySevices;
use FormBuilder\Factory\Elm;
use think\Exception;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Route;
use app\common\repositories\user\UserAddressRepository;

/**
 * 同城配送门店
 */
class DeliveryStationRepository extends BaseRepository
{
    use DeliveryTrait;

    public function __construct(DeliveryStationDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 同城配送设置表单生成方法
     * 该方法用于构建同城配送的配置表单，包括是否开启同城配送、选择配送类型以及相应的配置项。
     * 配送类型可以是达达快送或UU跑腿，每种配送类型需要不同的配置参数，如AppKey、AppSecret等。
     *
     * @return \think\response\View 返回一个包含同城配送设置表单的视图
     */
    public function deliveryForm()
    {
        // 从系统配置中获取同城配送相关的配置数据
        $formData = systemConfig([
            'delivery_status',
            'uupt_appkey',
            'uupt_app_id',
            'uupt_open_id',
            'delivery_type',
            'dada_app_key',
            'dada_app_sercret',
            'dada_source_id',
        ]);

        // 构建表单提交的URL
        $formActionUrl = Route::buildUrl('systemDeliveryConfigSave')->build();

        // 使用Element UI构建表单
        $form = Elm::createForm($formActionUrl);

        // 设置表单规则，包括是否开启同城配送的开关和选择配送类型的单选按钮
        $form->setRule([
            // 开关控件，用于开启或关闭同城配送
            Elm::switches('delivery_status', '是否开启同城配送：', $formData['delivery_status'])->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->className('switch-width-double')->activeText('开启'),
            // 单选按钮，用于选择配送服务类型：达达快送或UU跑腿
            Elm::radio('delivery_type', '配送类型：', $formData['delivery_type'])
                ->setOptions([
                    ['value' => DeliverySevices::DELIVERY_TYPE_DADA, 'label' => '达达快送'],
                    ['value' => DeliverySevices::DELIVERY_TYPE_UU, 'label' => 'UU跑腿'],
                ])
                ->control([
                    // 配送类型为达达快送时的配置项：AppKey、AppSecret和商户ID
                    [
                        'value' => DeliverySevices::DELIVERY_TYPE_DADA,
                        'rule' => [
                            Elm::input('dada_app_key', 'AppKey (达达)：')->value($formData['dada_app_key'])->placeholder('请输入AppKey (达达)')->required('请输入AppKey (达达)'),
                            Elm::input('dada_app_sercret', 'AppSercret (达达)：')->value($formData['dada_app_sercret'])->placeholder('请输入AppSercret (达达)')->required('请输入AppSercret (达达)'),
                            Elm::input('dada_source_id', '商户ID (达达)：')->value($formData['dada_source_id'])->placeholder('请输入商户ID (达达)')->required('请输入商户ID (达达)'),
                        ]
                    ],
                    // 配送类型为UU跑腿时的配置项：AppKey、AppId和OpenId
                    [
                        'value' => DeliverySevices::DELIVERY_TYPE_UU,
                        'rule' => [
                            Elm::input('uupt_appkey', 'AppKey (UU跑腿)：')->value($formData['uupt_appkey'])->placeholder('请输入AppKey (UU跑腿)')->required('请输入AppKey (UU跑腿)'),
                            Elm::input('uupt_app_id', 'AppId (UU跑腿)：')->value($formData['uupt_app_id'])->placeholder('请输入AppId (UU跑腿)')->required('请输入AppId (UU跑腿)'),
                            Elm::input('uupt_open_id', 'OpenId (UU跑腿)：')->value($formData['uupt_open_id'])->placeholder('请输入OpenId (UU跑腿)')->required('请输入OpenId (UU跑腿)'),
                        ]
                    ],
                ]),
        ]);

        // 设置表单标题
        $form->setTitle('同城配送设置');

        // 返回包含构建好的表单的视图
        return $form;
    }

    /**
     * 获取支持配送类型
     * @return mixed
     * @author Qinii
     */
    public function getBusiness($type, int $merId = 0)
    {
        return DeliverySevices::create($type, $merId)->getBusiness();
    }

    /**
     *  创建门店
     * @param array $data
     * @return mixed
     * @author Qinii
     * @day 2/14/22
     */
    public function save(array $data)
    {
        return Db::transaction(function () use ($data) {
            if (!$data['origin_shop_id']) $data['origin_shop_id'] = $data['mer_id'] . '_' . $this->getSn();
            if($data['bind_type'] == 0 && $data['type']) {
                DeliverySevices::create($data['type'])->addShop($data);
            }
            unset($data['bind_type']);
            return $this->dao->create($data);
        });

    }

    /**
     * 生成订单编号
     *
     * 本函数旨在生成一个唯一的订单编号。编号由微秒时间戳和一个随机整数组成，
     * 以此确保订单编号的全局唯一性。
     *
     * @return string 返回生成的订单编号
     */
    public function getSn()
    {
        // 获取当前时间的微秒和秒部分
        list($msec, $sec) = explode(' ', microtime());

        // 将微秒和秒转换为毫秒，并去掉小数点，确保编号是整数
        $msectime = number_format((floatval($msec) + floatval($sec)) * 1000, 0, '', '');

        // 生成订单编号：毫秒时间戳后跟一个4位随机数，确保唯一性
        // 随机数生成范围是10000到当前毫秒时间戳转换的整数加10000，或98369之间的较大值
        // 这样做是为了避免在极短的时间内生成相同的订单编号
        // $orderId = $msectime . random_int(10000, max(intval($msec * 10000) + 10000, 98369));

        return $msectime;
    }


    /**
     *  更新门店
     * @param $id
     * @param $data
     * @return mixed
     * @author Qinii
     * @day 2/14/22
     */
    public function edit($id, $merId, $data)
    {
        $res = $this->dao->getSearch([$this->dao->getPk() => $id, 'mer_id' => $merId])->find();
        if (!$res) throw new ValidateException('提货点不存在或不属于您');

        $origin_shop_id = $data['origin_shop_id'];
        $data['origin_shop_id'] = $res['origin_shop_id'];
        if ($data['bind_type'] == 1){
            $data['origin_shop_id'] = $origin_shop_id;
        }
        return Db::transaction(function () use ($id, $data, $merId) {
            if($data['type']) {
                DeliverySevices::create($data['type'], $merId)->updateShop($data);
            }
            unset($data['bind_type']);
            return $this->dao->update($id, $data);
        });
    }

    /**
     * 商户门店列表
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     * @author Qinii
     * @day 2/17/22
     */
    public function merList(array $where, int $page, int $limit)
    {
        $query = $this->dao->search($where);
        $count = $query->count();
        $list = $query->page($page, $limit)->order('create_time DESC')->select();
        return compact('count', 'list');
    }

    /**
     *  系统门店列表
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     * @author Qinii
     */
    public function sysList(array $where, int $page, int $limit)
    {
        $query = $this->dao->getSearch($where)->with([
            'merchant' => function ($query) {
                $query->field('mer_id,mer_name');
            }
        ]);
        $count = $query->count();
        $list = $query->page($page, $limit)->order('create_time DESC')->select();
        return compact('count', 'list');
    }

    /**
     * 根据ID和商户ID获取详细信息
     *
     * 本函数用于查询指定ID和可选的商户ID对应的数据详情。特别地，它还会加载该数据关联的商户信息，
     * 但只包含商户的ID和名称。如果查询结果为空，则抛出一个验证异常，指出门店不存在。
     *
     * @param int $id 数据主键ID
     * @param int|null $merId 商户ID，可为空，表示不按商户ID过滤
     * @return array 查询到的数据详情
     * @throws ValidateException 如果未查询到数据，则抛出门店不存在的异常
     */
    public function detail(int $id, ?int $merId)
    {
        // 根据主键ID准备查询条件
        $where[$this->dao->getPk()] = $id;
        // 如果提供了商户ID，则加入查询条件
        if ($merId) $where['mer_id'] = $merId;

        // 执行查询，并加载关联的商户信息，但只包含指定的字段
        $res = $this->dao->getSearch($where)
            ->with([
                'merchant' => function ($query) {
                    // 明确指定查询商户的ID和名称
                    $query->field('mer_id,mer_name');
                }
            ])
            ->find();

        // 如果查询结果为空，则抛出异常，指出门店不存在
        if (!$res) throw new ValidateException('门店不存在');
        // 返回查询结果
        return $res;
    }

    /**
     * 删除数据条目。
     *
     * 本函数用于根据给定的ID和商户ID删除特定的数据项。它首先通过ID和商户ID查询数据是否存在，
     * 如果数据不存在，则抛出一个验证异常；如果数据存在，则执行删除操作。
     *
     * @param int $id 数据项的主键ID。
     * @param int $merId 商户的ID，用于确定数据项所属的商户。
     * @return bool 返回删除操作的结果，通常是TRUE表示删除成功，FALSE表示删除失败。
     * @throws ValidateException 如果查询的数据项不存在，则抛出此异常。
     */
    public function destory($id, $merId)
    {
        // 根据主键ID和商户ID构建查询条件
        $where = [
            $this->dao->getPk() => $id,
            'mer_id' => $merId,
        ];

        // 根据查询条件查找数据项
        $res = $this->dao->getSearch($where)->find();

        // 如果查询结果为空，即数据项不存在，则抛出异常
        if (!$res) throw new ValidateException('数据不存在');

        // 执行删除操作，并返回删除结果
        return $this->dao->delete($id);
    }

    /**
     * 标记商家订单 form 的生成方法
     *
     * 本方法用于生成一个用于商家标记订单的表单。通过传入订单ID和商家ID，
     * 方法检索订单的当前备注信息，并构建一个表单以允许商家添加或更新订单备注。
     *
     * @param int $id 订单ID，用于定位特定的订单。
     * @param int $merId 商家ID，用于确定操作订单的商家。
     * @return string 返回生成的表单HTML代码。
     */
    public function markForm($id, $merId)
    {
        // 根据订单ID和商家ID构建查询条件
        $where = [
            $this->dao->getPk() => $id,
            'mer_id' => $merId,
        ];

        // 根据查询条件获取订单的当前备注信息
        $formData = $this->dao->getWhere($where);

        // 构建表单URL，指向处理商家订单标记的路由
        $formUrl = Route::buildUrl('merchantStoreDeliveryMark', ['id' => $id])->build();

        // 创建表单对象，并设置表单提交的URL
        $form = Elm::createForm($formUrl);

        // 设置表单的验证规则，这里仅包含一个文本输入框用于输入备注信息
        $form->setRule([
            Elm::text('mark', '备注：', $formData['mark'])->placeholder('请输入备注'),
        ]);

        // 设置表单的标题
        $form->setTitle('备注');

        // 返回生成的表单HTML代码
        return $form;
    }

    /**
     * 获取搜索选项
     * 该方法通过查询数据库获取搜索选项，每个选项包含value和label两个属性，分别代表选项的值和显示文本。
     * 这些选项通常用于下拉列表等选择控件，以提供用户友好的选择方式。
     *
     * @param array $where 查询条件，用于筛选选项。此参数应包含任何需要的条件，以限定查询的结果集。
     * @return object $list 返回一个数组，其中每个元素都是一个包含value和label属性的对象。这些对象代表了可选的搜索条件。
     */
    public function getOptions($where)
    {
        // 使用DAO层的getSearch方法查询数据，指定查询的字段为station_id和station_name，
        // 并按create_time降序排列。最终返回查询结果的数组。
        $list = $this->dao->getSearch($where)->field('station_id value, station_name label')->order('create_time DESC')
        ->select();
        return $list;
    }



    /**
     * 获取城市列表
     * @return mixed
     * @author Qinii
     */
    public function getCityLst($type, int $merId = 0)
    {
        $key = 'delivery_get_city_lst_' . $type . '_' . $merId;
        if (!$data = Cache::get($key)) {
            $data = DeliverySevices::create($type, $merId)->getCity([]);
            Cache::set($key, $data, 3600);
        }
        return $data;
    }

    /**
     * 获取配送余额
     *
     * 本函数用于查询当前系统的配送余额。它首先通过系统配置确定配送方式，然后根据配送方式创建相应的配送服务对象，
     * 最后通过该对象获取配送余额。如果系统没有配置配送方式，则默认返回配送余额为0。
     *
     * @return array 返回一个包含配送余额的数组。如果未配置配送类型，则数组中配送余额为0。
     */
    public function getBalance()
    {
        // 通过系统配置获取配送方式
        $type = systemConfig('delivery_type');

        // 如果未配置配送方式，则直接返回配送余额为0
        if (!$type) return ['deliverBalance' => 0];

        // 根据配送方式创建配送服务对象，并调用其方法获取配送余额
        return DeliverySevices::create(systemConfig('delivery_type'))->getBalance([]);
    }

    /**
     * 获取充值方式
     *
     * 本方法通过调用DeliveryServices类的静态方法create，根据配置的配送类型创建相应的配送服务对象，
     * 并进一步调用该对象的getRecharge方法来获取充值方式。此方法的设计允许系统灵活地支持不同类型的配送服务，
     * 以及对应的充值方式获取逻辑，增强了系统的可扩展性和灵活性。
     *
     * @return array 充值方式列表
     */
    public function getRecharge()
    {
        // 根据系统配置的配送类型创建配送服务对象
        return DeliverySevices::create(systemConfig('delivery_type'))->getRecharge([]);
    }
    /**
     * 获取提货点列表
     * 根据距离排序
     *
     * @param array $params
     * @return void
     */
    public function getListSortByDistance(array $params)
    {
        $lat = 0;
        $lng = 0;
        if($params['switch_city']) {
            $params['mer_delivery_type'] = merchantConfig($params['mer_id'], 'mer_delivery_type');
            $address = app()->make(UserAddressRepository::class)->get($params['address_id'], $params['uid']);
            $addressDetail = $address['province'] . $address['city'] . $address['district'] . $address['street'] . $address['detail'];
            try{
                $addressLatAndLong = lbs_address([], $addressDetail);
                $lat = $addressLatAndLong['location']['lat'];
                $lng = $addressLatAndLong['location']['lng'];
            }catch (\Exception $exception) {

            }
        }
        if($params['switch_take']) {
            $lat = $params['latitude'];
            $lng = $params['longitude'];
        }
        unset($params['switch_city'], $params['latitude'], $params['longitude']);

        // 获取提货点列表
        $list = $this->dao->search($params)->order('station_id DESC,create_time DESC')->select()->toArray();
        if ($lat && $lng) {
            // 计算距离
            foreach($list as &$item){
                $stationLat = $item['lat'];
                $stationLong = $item['lng'];
                $item['distance'] = '';
                $item['distanceM'] = 0;
                if (!$stationLat || !$stationLong)
                    continue;

                $distance = getDistance($lat, $lng, $stationLat, $stationLong);
                $item['distanceM'] = $distance;
                // 距离单位转换
                if ($distance < 0.9) {
                    $distance = max(bcmul($distance, 1000, 0), 1).'m';
                    if ($distance == '1m') {$distance = '100m以内';}
                } else {
                    $distance.= 'km';
                }
                $item['distance'] = $distance;
            }
            // 距离排序
            usort($list, function($a, $b) {
                return ($a['distanceM'] ?? 0) <=> ($b['distanceM'] ?? 0);
            });
        }

        return $list;
    }
    /**
     * 配送站信息
     * @param int $stationId
     * @param int $merId
     * @return mixed
     */
    public function deliveryStationInfo(int $stationId, int $merId)
    {
        $where = ['station_id' => $stationId, 'mer_id' => $merId, 'status' => 1];
        $station = $this->getWhere($where);
        if (!$station) {
            throw new ValidateException('提货点不存在');
        }

        return $station->toArray();
    }
    /**
     * 配送半径验证
     *
     * @param array $address
     * @param array $deliveryStation
     * @return boolean
     */
    public function checkRadius(array $address, array $deliveryStation): bool
    {
        // 根据经纬度计算距离
        $distance = $this->getAddressDistance($deliveryStation['lat'], $deliveryStation['lng'], $address);
        // 配送半径验证
        if ($deliveryStation['radius'] < $distance) {
            return false;
        }

        return true;
    }
    /**
     * 配送区域验证
     *
     * @param array $address
     * @param array $deliveryStation
     * @return boolean
     */
    public function checkRegion(array $address, array $deliveryStation): bool
    {
        $isRegion = false;
        $addresString = $address['province_id'] . ',' . $address['city_id'] . ',' . $address['district_id'];
        foreach ($deliveryStation['region'] as $region) {
            $regionString = implode(',', $region);
            if (strpos($addresString, $regionString) !== false) {
                $isRegion = true;
                break;
            }
        }

        return $isRegion;
    }
    /**
     * 配送围栏验证
     *
     * @param array $address
     * @param array $deliveryStation
     * @return boolean
     */
    public function checkFence(array $address, array $deliveryStation): bool
    {
        $isFence = false;
        $addressDetail = $address['province'] . $address['city'] . $address['district'] . $address['street'] . $address['detail'];
        $addressLatAndLong = lbs_address([], $addressDetail);
        foreach ($deliveryStation['fence'] as $fence) {
            $method = 'checkIn' . ucfirst($fence['type']);
            if (method_exists($this, $method)) {
                if ($this->$method($fence, $addressLatAndLong)) {
                    $isFence = true;
                    break;
                }
            }
        }

        return $isFence;
    }


}
