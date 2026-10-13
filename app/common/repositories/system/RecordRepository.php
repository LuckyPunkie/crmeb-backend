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


namespace app\common\repositories\system;

use app\common\dao\system\RecordDao;
use app\common\repositories\BaseRepository;
use app\common\repositories\user\UserAddressRepository;

class RecordRepository extends BaseRepository
{

    //文章关联商品
    const TYPE_ADDRESS_RECORD  =  'address_record';

    protected $dao;

    /**
     * @param RecordDao $dao
     */
    public function __construct(RecordDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 添加记录到数据库。
     *
     * 根据传入的类型和数据，对特定类型的记录进行增加操作。目前支持的类型主要是地址记录。
     * 该方法首先检查传入的数据是否为空，如果为空，则直接返回，不进行任何操作。
     * 根据传入的类型，使用工厂模式获取相应的用户地址仓库实例。
     * 对于地址记录，通过地址ID获取地址详情，然后根据地址的省市信息，分别对省份和城市（如果城市为市辖区，则为区县）的记录数量进行增加操作。
     *
     * @param string $type 记录的类型，用于确定具体的操作逻辑。
     * @param array $data 记录的数据，包含相关字段的信息，如地址ID和增加的数量。
     */
    public function addRecord(string $type, array $data)
    {
        // 如果数据为空，则直接返回，不进行后续操作。
        if (empty($data)) return ;

        // 根据类型执行不同的操作逻辑。
        switch ($type) {
            case self::TYPE_ADDRESS_RECORD :
                // 通过依赖注入获取用户地址仓库实例。
                $userAddressRepository = app()->make(UserAddressRepository::class);
                // 根据地址ID获取地址详情。
                $addres = $userAddressRepository->getWhere(['address_id' => $data['address_id']]);

                // 如果城市为市辖区，则使用区县ID和区县名称，否则使用城市ID和城市名称。
                $cityid =  ($addres['city'] == '市辖区') ? $addres['district_id'] : $addres['city_id'] ;
                $city =  ($addres['city'] == '市辖区') ? $addres['district'] : $addres['city'] ;

                // 分别对省份和城市（区县）的记录数量进行增加操作。
                $this->dao->incType($type, $addres['province_id'], $data['num'],['title' => $addres['province']]);
                $this->dao->incType($type, $cityid, $data['num'],['title' => $city]);
                break;
        }
    }
}
