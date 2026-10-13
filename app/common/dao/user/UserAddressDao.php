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


namespace app\common\dao\user;


use app\common\dao\BaseDao;
use app\common\model\user\UserAddress as model;

class UserAddressDao extends BaseDao
{
    /**
     * @return string
     * @author Qinii
     */
    protected function getModel(): string
    {
        return model::class;
    }


    /**
     * 检查用户字段是否存在
     *
     * 本函数用于确定在用户数据表中，给定的字段是否包含特定的值。
     * 这对于验证用户输入、防止重复数据等场景非常有用。
     *
     * @param string $field 要检查的字段名。这可以是用户数据表中的任何字段。
     * @param mixed $value 要与字段值进行比较的值。可以是任何数据类型。
     * @param int $uid 用户ID，用于限定查询的用户范围。
     * @return bool 如果找到匹配的记录，则返回true；否则返回false。
     */
    public function userFieldExists($field, $value,$uid): bool
    {
        // 通过模型获取数据库实例，并构造查询条件，检查是否存在uid为$uid且$field字段值为$value的记录
        return (($this->getModel()::getDB())->where('uid',$uid)->where($field,$value)->count()) > 0;
    }

    /**
     * 修改默认设置
     *
     * 本函数用于将指定用户的默认状态设置为0，即取消默认设置。这通常在需要重新指定默认选项或
     * 在用户取消其默认设置时调用。
     *
     * @param int $uid 用户ID
     *               传入需要修改默认设置的用户的唯一标识ID。这个ID用于在数据库中定位到具体的用户记录。
     * @return int 返回影响的行数
     *             函数返回的是执行更新操作后影响的行数。如果返回0，表示没有更新任何行，即没有找到指定ID的用户；
     *             如果返回大于0的数，表示成功更新了相应数量的行，即成功取消了指定用户的默认设置。
     */
    public function changeDefault(int $uid)
    {
        // 通过模型获取数据库实例，并使用where子句定位到指定UID的用户记录，然后更新is_default字段为0。
        return ($this->getModel()::getDB())->where('uid',$uid)->update(['is_default' => 0]);
    }

    /**
     * 获取指定用户的所有数据
     *
     * 本函数通过调用getModel方法获取模型实例，并使用该实例的getDB方法来检索数据库连接。
     * 然后，利用where方法指定查询条件，即uid等于传入的参数$uid。
     * 此方法体现了依赖注入的思想，通过参数传递模型实例，增强了代码的灵活性和可测试性。
     *
     * @param int $uid 用户ID，用于指定要查询的数据所属的用户。
     * @return 查询结果，是一个符合指定条件的数据集合。
     */
    public function getAll(int $uid)
    {
        // 通过模型获取数据库实例，并应用查询条件
        return (($this->getModel()::getDB())->where('uid',$uid));
    }
}
