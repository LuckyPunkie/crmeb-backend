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

namespace app\common\repositories\store\shipping;

use app\common\repositories\BaseRepository;
use app\common\dao\store\shipping\ShippingTemplateDao as dao;
use app\common\repositories\store\product\ProductRepository;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * Class ShippingTemplateRepository
 *
 * @mixin dao
 */
class ShippingTemplateRepository extends BaseRepository
{

    /**
     * ShippingTemplateRepository constructor.
     * @param dao $dao
     */
    public function __construct(dao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 根据商家ID获取列表
     *
     * 此方法通过商家ID从数据访问对象（DAO）获取相关列表，并对列表中的项目进行处理，标记系统默认项目。
     * 主要用于在前端展示时，区分系统默认项目与其他项目。
     *
     * @param int $merId 商家ID，用于查询特定商家的数据列表。
     * @return array 返回处理后的列表，包含每个项目的详细信息。
     */
    public function getList(int $merId)
    {
        // 从DAO获取商家ID对应的列表
        $list = $this->dao->getList($merId);

        // 遍历列表，标记系统默认项目
        foreach ($list as &$item) {
            // 如果项目被设置为默认，则在其名称后添加系统默认标记
            if ($item['is_default'] == 1) {
                $item['name'] .= '（系统默认）';
            }
        }

        // 返回处理后的列表
        return $list;
    }

    /**
     * 检查指定商户是否存在
     *
     * 本函数通过调用DAO层的方法来查询指定商户ID是否在数据库中存在。
     * 主要用于验证商户ID的合法性和唯一性，确保后续操作的准确性。
     *
     * @param int $merId 商户的唯一标识ID
     * @param mixed $id 需要验证存在的ID，这个参数的具体类型取决于业务逻辑，可以是用户的ID等
     * @return bool 返回true表示指定的商户ID存在，返回false表示不存在
     */
    public function merExists(int $merId,$id)
    {
        // 调用DAO层的方法来检查指定的商户ID和主键ID是否存在
        return $this->dao->merFieldExists($merId,$this->getPk(),$id);
    }


    /**
     * 检查默认记录是否存在
     *
     * 本函数用于确定给定商家ID和ID对应的默认记录是否已存在。
     * 它通过构建查询条件并查询数据对象来实现这一目的。
     * 如果存在匹配的默认记录，则返回true，否则返回false。
     *
     * @param int $merId 商家ID，用于限定查询的商家范围
     * @param mixed $id 需要检查的记录ID，可以是任意类型的值，取决于数据表的主键类型
     * @return bool 如果默认记录存在则返回true，否则返回false
     */
    public function merDefaultExists(int $merId,$id)
    {
        // 构建查询条件，包括商家ID、是否为默认记录以及主键ID
        $where = ['mer_id' => $merId,'is_default' => 1, $this->dao->getPk() => $id];

        // 根据构建的查询条件查询数据对象，并根据查询结果的存在与否返回对应的布尔值
        return $this->dao->getWhere($where) ? true : false;
    }

    /**
     * 检查商品是否存在
     *
     * 该方法用于验证指定商家ID和商品ID对应的商品是否存在于临时商品列表中。
     * 主要用于场景如活动商品的临时展示和管理，确保商家和商品信息的准确性。
     *
     * @param int $merId 商家ID，用于指定查询的商家范围。
     * @param int $id 商品ID，用于指定查询的商品。
     * @return bool 返回true表示商品存在，返回false表示商品不存在。
     */
    public function getProductUse(int $merId ,int $id)
    {
        // 通过依赖注入的方式获取ProductRepository实例，并调用其merTempExists方法检查商品是否存在
        return app()->make(ProductRepository::class)->merTempExists($merId,$id);
    }

    /**
     * 根据ID获取一条信息，包括关联数据。
     *
     * 此方法用于根据提供的ID从数据库中检索一条记录，并根据是否在API上下文中加载不同的关联数据。
     * 它默认加载‘free’，‘region’和‘undelives’三个关联数据集，根据API的上下文，可能需要加载这些集合中城市名称的额外信息。
     *
     * @param int $id 主键ID，用于定位特定记录。
     * @param int $api 一个标志，当为1时，表示此请求是在API上下文中，会加载额外的城市名称信息。
     * @return object 返回包含所需数据的数据库记录对象。
     */
    public function getOne(int $id, $api = 0)
    {
        // 定义需要加载的关联数据集
        $with = ['free','region','undelives'];

        // 根据ID和关联数据集查询记录
        $result = $this->dao->getWhere([$this->dao->getPk() => $id],'*',$with);

        // 根据API上下文，确定是否需要加载额外的城市名称信息
        if ($api){
            // 在API上下文中，如果关联数据存在，则准备加载城市名称的属性
            if ($result['free']) $append[] = 'free.city_name';
            if ($result['region']) $append[] = 'region.city_name';
            if ($result['undelives']) $append[] = 'undelives.city_name';
        } else {
            // 在非API上下文中，加载城市ID的属性
            $append = ['free.city_ids','region.city_ids','undelives.city_ids'];
        }

        // 添加额外的属性到查询结果中
        $result->append($append);

        // 返回处理后的查询结果
        return $result;
    }

    /**
     * 根据条件搜索信息。
     *
     * 本函数用于根据给定的商家ID、搜索条件、分页信息和每页数量，从数据库中检索相关信息。
     * 它首先构造一个查询，然后获取符合条件的记录总数，接着根据分页信息获取具体的数据列表。
     * 最后，对列表中的默认项进行标记，并返回包含总数和列表的数据结构。
     *
     * @param int $merId 商家ID，用于限定搜索范围。
     * @param array $where 搜索条件，一个包含搜索关键字的数组。
     * @param int $page 当前页码，用于分页查询。
     * @param int $limit 每页记录数，用于分页查询。
     * @return array 返回一个包含总数和列表的数组，列表中的每个项可能被标记为系统默认。
     */
    public function search(int $merId, array $where, int $page, int $limit)
    {
        // 构造查询条件，根据商家ID和搜索条件查询数据
        $query = $this->dao->search($merId, $where);

        // 计算符合条件的记录总数
        $count = $query->count($this->dao->getPk());

        // 根据分页信息获取具体的数据列表
        $list = $query->page($page, $limit)->select();

        // 遍历列表，对默认项进行标记
        foreach ($list as &$item) {
            if ($item['is_default'] == 1) {
                $item['name'] .= '（系统默认）';
            }
        }

        // 返回包含总数和列表的数据结构
        return compact('count', 'list');
    }

    /**
     * 更新配送模板信息。
     *
     * 该方法用于根据给定的ID和数据数组来更新配送模板的详细信息。
     * 它首先从数据数组中提取区域、免费配送和未送达信息，然后更新数据库中相应字段。
     * 同时，它还会根据新的配置删除旧的区域、免费配送和未送达规则，并根据需要插入新的规则。
     * 这个过程在一个数据库事务中完成，以确保数据的一致性。
     *
     * @param int $id 配送模板的ID。
     * @param array $data 包含更新后配送模板信息的数据数组。
     */
    public function update(int $id,array $data)
    {
        // 开始一个数据库事务，并传递ID和数据数组作为使用变量。
        Db::transaction(function()use ($id,$data) {
            // 从数据数组中提取区域、免费配送和未送达信息。
            $region = $data['region'];
            $free = $data['free'] ?? '';
            $undelives = $data['undelives']??'';

            // 从数据数组中移除不需要的字段，准备更新配送模板的其他信息。
            unset($data['region'],$data['free'],$data['undelives'],$data['city_ids']);

            // 使用DAO更新配送模板的基本信息。
            $this->dao->update($id, $data);

            // 删除与当前模板ID相关的所有区域、免费配送和未送达规则。
            (app()->make(ShippingTemplateRegionRepository::class))->batchRemove([], [$id]);
            (app()->make(ShippingTemplateFreeRepository::class))->batchRemove([], [$id]);
            (app()->make(ShippingTemplateUndeliveRepository::class))->batchRemove([], [$id]);

            // 如果配置了指定配送，則根据免费配送信息创建新的免费配送规则。
            if($data['appoint']) {
                $settlefree = $this->settleFree($free, $id);
                (app()->make(ShippingTemplateFreeRepository::class)->insertAll($settlefree));
            }

            // 根据新的区域配置，创建并插入新的区域配送规则。
            $settleRegion = $this->settleRegion($region,$id);
            (app()->make(ShippingTemplateRegionRepository::class)->insertAll($settleRegion));

            // 如果配置了未送达处理，根据未送达信息创建新的未送达规则。
            if($data['undelivery'] == 1){
                $settleUndelives = $this->settleUndelives($undelives,$id);
                (app()->make(ShippingTemplateUndeliveRepository::class))->create($settleUndelives);
            }

        });
    }


    /**
     * 删除记录
     *
     * 本函数用于根据提供的ID删除一条或多条记录。它首先检查传入的ID是否为数组，
     * 如果是数组，则遍历每个ID，并调用DAO对象的delete方法来逐个删除这些记录。
     * 如果传入的ID不是数组，那么它直接调用DAO对象的delete方法来删除对应的记录。
     * 这种设计允许灵活地处理单个或多个记录的删除操作，简化了删除操作的代码。
     *
     * @param mixed $id 要删除的记录的ID。它可以是一个整数或一个整数数组。
     */
    public function delete($id)
    {
        // 检查传入的ID是否为数组
        if (is_array($id)) {
            // 如果是数组，则遍历每个ID进行删除
            foreach ($id as $i) {
                $this->dao->delete($i);
            }
        } else {
            // 如果不是数组，则直接删除单个ID对应的记录
            $this->dao->delete($id);
        }
    }

    /**
     * 创建配送模板
     * @param array $data 配送模板相关数据
     * 包含区域、是否免费、未配送处理方式等信息
     */
    public function create(array $data)
    {
        // 使用数据库事务确保操作的原子性
        Db::transaction(function() use ($data) {
            // 提取区域信息
            $region = $data['region'];
            // 提取是否免费配送的信息，默认为空字符串
            $free = $data['free'] ?? '';
            // 提取未配送处理方式的信息，默认为空字符串
            $undelives = $data['undelives'] ?? '';

            // 如果指定未配送处理方式为2（即开启状态），则清空未配送处理方式的数据
            if (isset($data['undelivery']) && $data['undelivery'] == 2) {
                $data['undelives'] = [];//开启状态下过滤数据
            }

            // 移除不需要的字段，以准备创建配送模板
            unset($data['region'], $data['free'], $data['undelives'], $data['city_ids']);

            // 调用DAO层创建配送模板
            $temp = $this->dao->create($data);

            // 如果指定了配送时间预约，则处理免费配送条件与模板的关联
            if ($data['appoint']) {
                // 计算免费配送条件
                $settlefree = $this->settleFree($free, $temp['shipping_template_id']);
                // 插入计算后的免费配送条件
                (app()->make(ShippingTemplateFreeRepository::class)->insertAll($settlefree));
            }

            // 处理区域与模板的关联
            $settleRegion = $this->settleRegion($region, $temp['shipping_template_id']);
            // 插入处理后的区域信息
            (app()->make(ShippingTemplateRegionRepository::class)->insertAll($settleRegion));

            // 如果指定了未配送处理方式为1（即开启状态），则处理未配送的处理方式与模板的关联
            if ($data['undelivery'] == 1) {
                // 计算未配送处理方式
                $settleUndelives = $this->settleUndelives($undelives, $temp['shipping_template_id']);
                // 创建处理后的未配送处理方式
                (app()->make(ShippingTemplateUndeliveRepository::class))->create($settleUndelives);
            }
        });
    }

    /**
     * 处理免费配送信息
     *
     * 此函数用于根据传入的数据集合并免费配送信息，准备数据库插入操作。
     * 数据集中每个元素代表一个配送区域及其对应的配送数量和价格。
     * 如果城市ID不是数组形式，则抛出异常，确保数据准确性。
     *
     * @param array $data 包含配送区域ID、配送数量和价格的数据数组。
     * @param int $id 模板ID，用于关联配送信息。
     * @return array 返回整理后的免费配送信息数组，适合进行数据库插入操作。
     * @throws ValidateException 如果城市ID不是数组，则抛出验证异常。
     */
    public function settleFree($data,$id)
    {
        // 遍历数据集，处理每个配送区域的信息
        foreach ($data as $v){
            // 检查城市ID是否为数组，确保数据格式正确
            if (isset($v['city_id']) && !is_array($v['city_id'])) {
                throw new ValidateException('包邮参数类型错误');
            }
            // 将城市ID转换为字符串格式，用于后续处理
            $city = '/'.implode('/',$v['city_id']).'/';
            // 构建免费配送信息数组
            $free[] = [
                'temp_id' => $id,
                'city_id' => $city,
                'number' => $v['number'],
                'price' => $v['price']
            ];
        }
        // 返回处理后的免费配送信息数组
        return $free;
    }

    /**
     * 处理未配送的订单结算
     *
     * 该方法用于处理针对特定未配送订单的结算操作。它验证了传入数据的正确性，并返回一个包含临时ID和城市ID的数组。
     * 主要用于在订单系统中，对特定条件下未成功配送的订单进行特别处理，例如，更改配送城市或取消订单等。
     *
     * @param array $data 包含订单相关信息的数据数组
     * @param int $id 临时ID，用于标识待处理的订单
     * @throws ValidateException 如果$data['city_id']不是数组且设置了'city_id'，则抛出验证异常
     * @return array 返回一个包含临时ID和城市ID的数组，用于进一步的订单处理
     */
    public function settleUndelives($data,$id)
    {
        // 检查$data中是否设置了'city_id'且其类型不是数组，如果是，则抛出类型错误异常
        if (isset($v['city_id']) && !is_array($data['city_id'])) throw new ValidateException('指定不配送参数类型错误');

        // 返回一个数组，包含临时ID和城市ID，用于后续的订单处理
        return ['temp_id' => $id, 'city_id' => $data['city_id']];
    }

    /**
     * 结算区域信息处理函数
     * 该函数用于处理给定的区域数据，并根据这些数据生成一个结构化的数组，每个数组元素代表一个区域的结算信息。
     * @param array $data 包含区域详细信息的数组，每个元素本身是一个数组，包含城市ID、首次配送费用、续费配送费用等信息。
     * @param int $id 用于标识当前操作的临时ID，具体用途根据上下文决定。
     * @return array 返回一个结构化数组，每个元素包含城市ID路径、临时ID、首次配送费用、续费配送费用等信息。
     */
    public function settleRegion($data,$id)
    {
        // 初始化一个空数组，用于存储处理后的结果
        $result = [];
        // 遍历输入的区域数据数组
        foreach ($data as $k => $v){
            // 构建结果数组的单个元素，包含城市ID路径、临时ID、首次配送费用、续费配送费用等信息
            // 如果当前键值$k大于0，说明是一个非首个元素，将城市ID用'/'连接并添加到路径中，否则设置为0
            $result[] = [
                'city_id' => ($k > 0 ) ? '/'.implode('/',$v['city_id']).'/' : 0,
                'temp_id' => $id,
                'first' => $v['first'],
                'first_price'=> $v['first_price'],
                'continue' => $v['continue'],
                'continue_price'=> $v['continue_price'],
            ];
        }
        // 返回处理后的结果数组
        return $result;
    }

    /**
     * 创建默认配送模板
     * 该方法用于生成一个默认的配送模板，适用于没有特定配送设置的商家。默认模板设置了基本的配送规则配置。
     *
     * @param int $merId 商家ID，用于关联模板和商家。
     * @return mixed 返回创建的配送模板的数据，具体类型取决于create方法的实现。
     */
    public function createDefault(int $merId)
    {
        // 定义默认配送模板的数据结构
        $data = [
            "name" => "默认模板", // 模板名称
            "type" => 1, // 配送类型，这里假设为一种固定的类型
            "appoint" => 0, // 是否允许指定配送，这里设置为不允许
            "undelivery" => 0, // 是否允许不配送，这里设置为不允许
            'mer_id' => $merId, // 商家ID，用于关联模板和商家
            "region" => [[ // 配送区域设置，这里以最简单的形式初始化
                "first" => 0, // 首重价格
                "first_price" => 0, // 首重费用
                "continue" => 0, // 续重价格
                "continue_price" => 0, // 续重费用
                "city_id" => 0 // 默认城市ID，用于初始配置
              ]]
        ];

        // 调用create方法来创建配送模板
        return $this->create($data);
    }


    /**
     * 检查商户模板是否可以删除。
     *
     * 此函数用于在删除商户模板前进行一系列的验证，以确保模板可以被安全删除。
     * 它主要检查以下三个条件：
     * 1. 模板是否存在。
     * 2. 模板是否为默认模板，默认模板不允许删除。
     * 3. 模板是否正在被使用，正在使用的模板不允许删除。
     * 如果任何检查失败，将抛出一个ValidateException异常，异常信息包含具体的错误原因。
     *
     * @param string $merId 商户ID，用于标识模板所属的商户。
     * @param int $id 模板ID，用于唯一标识模板。
     * @throws ValidateException 如果模板不存在、是默认模板或正在使用中，则抛出此异常。
     */
    public function check($merId, $id)
    {
        // 检查模板是否存在，如果不存在则抛出异常。
        if(!$this->merExists($merId, $id))
            throw new ValidateException('数据不存在,ID:'.$id);

        // 检查模板是否为默认模板，如果是则抛出异常。
        if($this->merDefaultExists($merId, $id))
            throw new ValidateException('默认模板不能删除,ID:'.$id);

        // 检查模板是否正在被使用，如果正在使用则抛出异常。
        if($this->getProductUse($merId, $id))
            throw new ValidateException('模板使用中，不能删除,ID:'.$id);
    }

}
