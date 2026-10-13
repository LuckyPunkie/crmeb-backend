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


namespace app\common\dao\system\merchant;


use app\common\dao\BaseDao;
use app\common\model\system\merchant\MerchantAdmin;
use think\db\BaseQuery;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Db;
use think\Model;

/**
 * Class MerchantAdminDao
 * @package app\common\dao\system\merchant
 * @author xaboy
 * @day 2020-04-17
 */
class MerchantAdminDao extends BaseDao
{

    /**
     * @return string
     * @author xaboy
     * @day 2020-04-16
     */
    protected function getModel(): string
    {
        return MerchantAdmin::class;
    }

    /**
     * 根据条件搜索商家管理员
     *
     * 本函数用于查询商家管理员信息，支持根据商家ID、额外条件和级别进行筛选。
     * 商家ID是必需的参数，用于确定查询的商家范围。额外条件是一个数组，可以包含日期、关键字和状态等信息，用于进一步细化查询条件。级别是一个可选参数，用于筛选特定级别的商家管理员。
     *
     * @param int $merId 商家ID，用于限定查询的商家范围
     * @param array $where 额外的查询条件，包括日期、关键字和状态等
     * @param int|null $level 管理员级别，可选参数，用于筛选特定级别的管理员
     * @return \Illuminate\Database\Query\Builder 查询构建器对象，用于进一步的查询操作或数据获取
     */
    public function search(int $merId, array $where = [], ?int $level = null)
    {
        // 初始化查询构建器，限定查询已删除的商家管理员，以及指定商家ID
        $query = MerchantAdmin::getDB()->where('is_del', 0)->where('mer_id', $merId);

        // 如果指定了日期条件，则进一步限定查询日期范围
        $query = $query->when(isset($where['date']) && $where['date'] !== '', function ($query) use ($where) {
            getModelTime($query, $where['date']);
        });

        // 如果指定了级别，则进一步限定查询特定级别的商家管理员
        if (!is_null($level)) $query->where('level', $level);

        // 如果指定了关键字，则使用LIKE查询匹配关键字出现在真实姓名或账号中的商家管理员
        if (isset($where['keyword']) && $where['keyword'] !== '') {
            $query = $query->whereLike('real_name|account', '%' . $where['keyword'] . '%');
        }

        // 如果指定了状态，则进一步限定查询特定状态的商家管理员
        if (isset($where['status']) && $where['status'] !== '') {
            $query = $query->where('status', intval($where['status']));
        }

        // 返回构建好的查询构建器对象
        return $query;
    }

    /**
     * 根据商家ID获取商家管理员账号
     *
     * 本函数旨在通过商家ID查询商家管理员数据库记录，并提取出账号信息。
     * 它使用了MerchantAdmin类的getDB方法来获取数据库操作对象，然后通过where方法指定查询条件，
     * 最后使用value方法获取查询结果中的账号字段值。
     *
     * @param int $merId 商家ID，用于查询特定商家的管理员账号。
     * @return string 返回查询到的商家管理员账号。如果未找到对应商家ID的管理员账号，则返回空字符串。
     */
    public function merIdByAccount(int $merId): string
    {
        // 通过商家ID和级别为0查询商家管理员账号
        return MerchantAdmin::getDB()->where('mer_id', $merId)->where('level', 0)->value('account');
    }

    /**
     * 根据商家ID获取管理员信息
     *
     * 本函数用于查询并返回指定商家ID对应的管理员信息。特别地，它只返回级别为0的管理员，
     * 这意味着这个函数旨在获取主要或者高级管理员的相关信息，而不是所有管理员。
     *
     * @param int $merId 商家ID，用于指定要查询的商家。
     * @return array 返回包含管理员信息的数组，如果找不到符合条件的管理员则返回空数组。
     */
    public function merIdByAdmin(int $merId)
    {
        // 使用MerchantAdmin类的getDB方法获取数据库实例，并通过where方法指定查询条件，最后调用find方法查询并返回数据。
        return MerchantAdmin::getDB()->where('mer_id', $merId)->where('level', 0)->find();
    }

    /**
     * 根据账号和商家ID查询商家管理员信息
     *
     * 本函数用于通过商家管理员的账号和商家ID，在数据库中查询对应的商家管理员详细信息。
     * 这是对MerchantAdmin类的单例实例的调用，使用了多个where条件来精确查询数据，
     * 并指定了查询的字段，以获取必要的管理员信息。
     *
     * @param string $account 商家管理员账号
     * @param int $merId 商家ID
     * @return array|false 商家管理员信息的数组，如果未找到则返回false
     */
    public function accountByAdmin(string $account, int $merId)
    {
        // 通过getInstance方法获取MerchantAdmin类的单例实例
        // 使用链式调用方式依次设置查询条件：账号、是否被删除、商家ID
        // 最后指定查询的字段，并调用find方法来执行查询操作，返回符合条件的第一条数据
        return MerchantAdmin::getInstance()->where('account', $account)
            ->where('is_del', 0)->where('mer_id', $merId)
            ->field(['account', 'pwd', 'real_name', 'login_count', 'merchant_admin_id', 'status', 'mer_id'])
            ->find();
    }

    /**
     * 通过顶级管理员账号查询管理员信息
     *
     * 本函数用于查询指定管理员账号是否为顶级管理员，并返回其详细信息。
     * 通过账号、是否被删除、管理员级别进行筛选，确保只获取有效的顶级管理员信息。
     * 返回的管理员信息包括账号、密码、真实姓名、登录次数、管理员ID、状态和商家ID。
     *
     * @param string $account 管理员账号
     * @return array|false|null|\think\db\false|\think\Model 商家管理员信息，如果未找到则返回false
     */
    public function accountByTopAdmin(string $account)
    {
        // 使用MerchantAdmin类的单例模式查询管理员信息
        // 筛选条件为账号、是否被删除、管理员级别
        // 返回指定字段的信息，包括账号、密码、真实姓名、登录次数、管理员ID、状态和商家ID
        return MerchantAdmin::getInstance()->where('account', $account)
            ->where('is_del', 0)->where('level', 0)
            ->field(['account', 'pwd', 'real_name', 'login_count', 'merchant_admin_id', 'status', 'mer_id'])
            ->find();
    }

    /**
     * 根据商户账号获取商户ID
     *
     * 本函数旨在通过商户账号查找并返回对应的商户ID。它使用了MerchantAdmin类的单例模式，
     * 并结合数据库查询语句，实现了根据账号查找商户ID的功能。此功能对于需要根据商户账号进行
     * 某些操作的场景非常有用，例如权限验证、数据统计等。
     *
     * @param string $account 商户账号，用于查询的唯一标识
     * @return string 商户ID，如果找不到对应账号则返回空字符串
     */
    public function accountByMerchantId(string $account)
    {
        // 使用MerchantAdmin类的单例模式获取实例
        $merchantAdmin = MerchantAdmin::getInstance();
        // 根据传入的商户账号查询数据库，返回mer_id字段的值
        return $merchantAdmin->where('account', $account)->value('mer_id');
    }

    /**
     * 根据管理员ID获取管理员信息
     *
     * 本函数通过ID查询管理员数据，特别注意，它只返回未被删除的管理员信息。
     * 使用is_del字段为0作为查询条件，确保只获取到未标记为删除的管理员记录。
     *
     * @param int $id 管理员ID
     * @return object|null 返回管理员对象，如果找不到则返回null
     */
    public function get($id)
    {
        // 通过MerchantAdmin类的单例模式获取实例，并构造查询条件，查询指定ID的管理员信息
        return MerchantAdmin::getInstance()->where('is_del', 0)->find($id);
    }

    /**
     * 检查指定ID的管理员是否存在。
     *
     * 该方法用于确定数据库中是否存在指定ID且满足特定条件的管理员。
     * 它支持根据商家ID和级别进一步筛选结果。
     *
     * @param int $id 管理员的唯一标识ID。
     * @param int $merId 商家ID，用于指定特定商家的管理员。默认为0，表示不进行商家ID的筛选。
     * @param int|null $level 管理员级别，用于指定特定级别的管理员。如果为null，则不进行级别筛选。
     * @return bool 如果找到满足条件的管理员返回true，否则返回false。
     */
    public function exists(int $id, int $merId = 0, ?int $level = null)
    {
        // 初始化查询，根据管理员ID和是否已删除进行筛选
        $query = MerchantAdmin::getDB()->where($this->getPk(), $id)->where('is_del', 0);

        // 如果提供了商家ID，则进一步根据商家ID进行筛选
        if ($merId) $query->where('mer_id', $merId);

        // 如果提供了级别，则进一步根据级别进行筛选
        if (!is_null($level)) $query->where('level', $level);

        // 返回查询结果是否存在，存在返回true，否则返回false
        return $query->count() > 0;
    }

    /**
     * 检查商家字段是否存在
     *
     * 本函数用于查询指定商家是否存在指定字段的特定值。这在需要验证商家信息的唯一性或存在性时非常有用。
     * 例如，可以使用此函数来检查是否有其他商家使用了相同的名称或电话号码。
     *
     * @param int $merId 商家ID，用于指定查询的商家。
     * @param string $field 要查询的字段名，可以是商家信息中的任何字段。
     * @param mixed $value 要查询的字段值，对应字段应与此值匹配。
     * @param int|null $except 排除的ID，可选参数，用于指定不查询指定ID的记录。
     * @return bool 返回true如果找到匹配的记录，否则返回false。
     */
    public function merFieldExists(int $merId, $field, $value, ?int $except = null): bool
    {
        // 构建查询，指定商家ID和要查询的字段及其值
        $query = MerchantAdmin::getDB()->where($field, $value)->where('mer_id', $merId);

        // 如果提供了排除ID，则在查询中添加排除条件
        if (!is_null($except)) {
            $query->where($this->getPk(), '<>', $except);
        }

        // 返回查询结果是否存在，即是否有匹配的记录
        return $query->count() > 0;
    }

    /**
     * 检查是否存在顶级管理员
     *
     * 本函数用于确定数据库中是否存在一个特定ID的顶级管理员，该管理员未被删除且级别为0。
     * 这对于诸如权限分配、系统管理等关键操作的安全性验证非常重要。
     *
     * @param int $id 管理员的唯一标识ID
     * @return bool 如果存在符合条件的顶级管理员返回true，否则返回false
     */
    public function topExists(int $id)
    {
        // 构建查询条件，查询指定ID、未删除、级别为0的管理员记录
        $query = MerchantAdmin::getDB()->where($this->getPk(), $id)->where('is_del', 0)->where('level', 0);

        // 判断查询结果是否存在，存在返回true，否则返回false
        return $query->count() > 0;
    }

    /**
     * 根据顶级管理员ID获取商家ID
     *
     * 本函数用于查询数据库，根据提供的顶级管理员ID（$merId），
     * 获取对应的商家管理员ID。此功能适用于需要识别特定商家管理员的场景，
     * 例如在处理商家相关事务时需要确认操作者的身份。
     *
     * @param int $merId 顶级管理员的商家ID。这是一个用于内部标识商家的唯一整数。
     *                   它是查询数据库时的重要条件，用于精确匹配商家管理员。
     * @return int 返回查询到的商家管理员ID。如果没有找到匹配的管理员，将返回null。
     */
    public function merchantIdByTopAdminId(int $merId)
    {
        // 使用MerchantAdmin类的getDB方法获取数据库操作对象
        // 然后通过where方法指定查询条件：mer_id为$merId，is_del为0（未删除），level为0（顶级管理员）
        // 最后，使用value方法仅获取查询结果中的merchant_admin_id字段值
        return MerchantAdmin::getDB()->where('mer_id', $merId)->where('is_del', 0)->where('level', 0)->value('merchant_admin_id');
    }

    /**
     * 删除商户
     *
     * 本函数用于标记指定商户为删除状态，而不是物理删除该商户的数据。
     * 它通过更新商户管理员账户，在账户末尾添加特定标记来实现逻辑删除。
     * 这种方法保留了数据的审计痕迹，同时避免了直接删除数据可能带来的问题。
     *
     * @param int $merId 商户ID，用于指定要删除的商户。
     */
    public function deleteMer($merId)
    {
        // 使用数据库查询语句，定位到指定ID的商户，并更新其账户信息。
        // 在账户末尾添加'$del'标记，表示该账户已被删除。
        MerchantAdmin::getDB()->where('mer_id', $merId)->update(['account' => Db::raw('CONCAT(`account`,\'$del\')')]);
    }

}
