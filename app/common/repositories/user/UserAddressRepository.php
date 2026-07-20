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

use app\common\repositories\BaseRepository;
use app\common\dao\user\UserAddressDao as dao;
use app\common\repositories\store\shipping\CityRepository;

/**
 * Class UserAddressRepository
 * @package app\common\repositories\user
 * @day 2020/6/3
 * @mixin dao
 */
class UserAddressRepository extends BaseRepository
{
    /**
     * @var dao
     */
    protected $dao;


    /**
     * UserAddressRepository constructor.
     * @param dao $dao
     */
    public function __construct(dao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 检查用户字段是否存在
     *
     * 本函数通过调用DAO层的方法来确认特定用户ID和字段ID的组合是否存在。
     * 主要用于在进行数据操作前验证数据的完整性或唯一性，避免重复或错误的操作。
     *
     * @param int $id 字段ID，表示要检查的具体字段。
     * @param int $uid 用户ID，表示字段所属的用户。
     * @return bool 返回true表示字段存在，返回false表示字段不存在。
     */
    public function fieldExists(int $id,int $uid)
    {
        // 调用DAO层的方法，传入主键、字段ID和用户ID，检查该组合是否存在
        return $this->dao->userFieldExists($this->dao->getPk(),$id,$uid);
    }

    /**
     * 检查用户是否设置为默认
     *
     * 本函数通过调用DAO层的方法来查询指定用户是否被标记为默认用户。这在系统中可能用于判断某个用户是否是默认的登录选项或者系统设置等。
     *
     * @param int $uid 用户ID
     *            传入要查询的用户的唯一标识ID。这是识别用户的键值，在数据库中作为主键或者外键使用。
     * @return bool 返回查询结果
     *          函数返回一个布尔值，表示指定用户是否被标记为默认。返回true表示是默认用户，返回false表示不是默认用户。
     */
    public function defaultExists(int $uid)
    {
        // 调用DAO方法查询用户默认设置
        return $this->dao->userFieldExists('is_default',1,$uid);
    }

    /**
     * 检查给定ID的记录是否设为默认值
     *
     * 本函数通过查询数据库来确定指定ID的记录是否被标记为默认。这在系统中通常用于判断某个选项
     * 或配置是否是默认选择，例如默认语言、默认支付方式等。
     *
     * @param int $id 需要检查的记录的ID。这是一个整数类型的参数，用于唯一标识数据库中的记录。
     * @return bool 返回一个布尔值，表示给定ID的记录是否被标记为默认。如果记录是默认的，则返回true；否则返回false。
     */
    public function checkDefault(int $id)
    {
        // 通过DAO对象查询数据库，根据主键ID获取记录
        $res = $this->dao->getWhere([$this->dao->getPk() => $id]);

        // 返回查询结果中'is_default'字段的值，该字段用于标记记录是否为默认
        return $res['is_default'];
    }

    /**
     * 根据省份和城市名称获取城市ID
     *
     * 本函数通过省份和城市名称查询城市ID。首先，它根据省份名称获取该省份的数据，
     * 然后使用城市名称和获取到的省份ID来获取具体城市的数据。如果找不到确切的城市数据，
     * 则尝试查找以“直辖”开头的城市数据，这些城市也属于同一个省份。
     * 这样做的目的是确保即使城市名称不完全匹配，也能尽可能准确地返回对应的城市ID。
     *
     * @param string $province 省份名称
     * @param string $city 城市名称
     * @return int 城市ID
     */
    public function getCityId($province,$city)
    {
        // 实例化CityRepository类，用于后续的数据查询操作
        $make = app()->make(CityRepository::class);

        // 根据省份名称查询省份数据
        $provinceData = $make->getWhere(['name' => $province]);

        // 根据城市名称和省份ID查询城市数据
        $cityData = $make->getWhere(['name' => $city,'parent_id' => $provinceData['city_id']]);

        // 如果查询不到具体城市数据，尝试查找以“直辖”开头的城市数据
        if(!$cityData)$cityData = $make->getWhere([['name','like','直辖'.'%'],['parent_id' ,'=', $provinceData['city_id']]]);

        // 返回查询到的城市ID
        return $cityData['city_id'];
    }

    /**
     * 获取用户列表
     *
     * 根据给定的用户ID、页码和每页数量，查询用户列表。首先计算总记录数，然后根据页码和每页数量进行分页查询。
     * 主要用于分页显示用户列表的情况。
     *
     * @param int $uid 用户ID，用于指定查询哪个用户的列表。
     * @param int $page 当前页码，用于确定查询的起始位置。
     * @param int $limit 每页的数量，用于控制每页显示的记录数。
     * @return array 返回包含用户列表和总记录数的数组。
     */
    public function getList($uid,$page, $limit, $tourist_unique_key = '')
    {
        // 查询所有属于指定用户的记录
        $query = $this->dao->getAll($uid);
        if($tourist_unique_key){
            $query = $query->where('tourist_unique_key', $tourist_unique_key);
        }

        // 计算总记录数
        $count = $query->count();

        // 进行分页查询，并按照是否为默认排序
        $list = $query->page($page, $limit)->order('is_default desc')->select();

        // 返回包含总记录数和用户列表的数组
        return compact('count','list');
    }

    /**
     * 根据ID和用户ID获取地址信息
     *
     * 本函数通过调用DAO层的方法，查询并返回满足特定条件的地址信息。
     * 其中，条件包括地址ID（$id）和用户ID（$uid）的匹配。
     * 查询结果还会额外附带'area'字段，用于提供地址所在的区域信息。
     *
     * @param int $id 地址ID，用于唯一标识一个地址记录。
     * @param int $uid 用户ID，用于确定地址所属的用户。
     * @return object 返回满足条件的地址对象，包含地址详情及附加的区域信息。
     */
    public function get($id,$uid)
    {
        // 根据地址ID和用户ID查询地址信息，并附加区域信息
        return $this->dao->getWhere(['address_id' => $id,'uid' => $uid])->append(['area']);
    }
}
