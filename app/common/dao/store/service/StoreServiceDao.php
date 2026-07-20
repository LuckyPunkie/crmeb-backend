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


namespace app\common\dao\store\service;


use app\common\dao\BaseDao;
use app\common\model\store\service\StoreService;
use think\db\BaseQuery;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Model;

/**
 * Class StoreServiceDao
 * @package app\common\dao\store\service
 * @author xaboy
 * @day 2020/5/29
 */
class StoreServiceDao extends BaseDao
{

    /**
     * @return string
     * @author xaboy
     * @day 2020/5/29
     */
    protected function getModel(): string
    {
        return StoreService::class;
    }

    /**
     * 根据条件搜索商店信息。
     *
     * 该方法通过接收一个包含各种搜索条件的数组，来查询数据库中符合这些条件的商店信息。
     * 查询时，只会返回未被删除的商店（is_del为0）。此外，可以根据传入的条件如状态、关键字、商家ID等进行过滤。
     * 这种灵活的查询方法允许根据不同的需求组合各种搜索条件，以获取精确的搜索结果。
     *
     * @param array $where 包含搜索条件的数组，每个条件对应数据库表中的一个字段。
     * @return BaseQuery 返回一个构建器对象，可用于进一步的查询操作或获取结果。
     */
    public function search(array $where)
    {
        // 从StoreService中获取数据库实例，并初始化查询构建器
        return StoreService::getDB()
            ->where('is_del', 0)
            ->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
            // 如果指定了状态，则添加状态条件到查询
            $query->where('status', $where['status']);
        })->when(isset($where['keyword']) && $where['keyword'] !== '', function ($query) use ($where) {
            // 如果指定了关键字，则添加模糊搜索条件到查询
            $query->where(function($query) use($where){
                $query->whereLike('nickname', "%{$where['keyword']}%")->whereOr('uid', $where['keyword']);
            });
        })->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
            // 如果指定了商家ID，则添加商家ID条件到查询
            $query->where('mer_id', $where['mer_id']);
        })->when(isset($where['customer']) && $where['customer'] !== '', function ($query) use ($where) {
            // 如果指定了客户名称，则添加客户名称条件到查询
            $query->where('customer', $where['customer']);
        })->when(isset($where['is_verify']) && $where['is_verify'] !== '', function ($query) use ($where) {
            // 如果指定了验证状态，则添加验证状态条件到查询
            $query->where('is_verify', $where['is_verify']);
        })->when(isset($where['is_goods']) && $where['is_goods'] !== '', function ($query) use ($where) {
            // 如果指定了商品状态，则添加商品状态条件到查询
            $query->where('is_goods', $where['is_goods']);
        })->when(isset($where['is_open']) && $where['is_open'] !== '', function ($query) use ($where) {
            // 如果指定了营业状态，则添加营业状态条件到查询
            $query->where('is_open', $where['is_open']);
        })->when(isset($where['uid']) && $where['uid'] !== '', function ($query) use ($where) {
            // 如果指定了用户ID，则添加用户ID条件到查询
            $query->where('uid', $where['uid']);
        })->when(isset($where['service_id']) && $where['service_id'] !== '', function ($query) use ($where) {
            // 如果指定了服务ID，则添加服务ID条件到查询
            $query->where('service_id', $where['service_id']);
        });
    }

    /**
     * 根据用户ID和可选的商户ID获取服务信息
     *
     * 此方法用于查询与特定用户相关联的服务信息。它允许指定一个可选的商户ID来过滤结果，
     * 仅返回属于该商户的服务。这是在处理多商户系统中，针对特定用户和商户的服务查询的常见需求。
     *
     * @param int $uid 用户ID。这是查询服务所必需的参数，用于确定服务与哪个用户相关联。
     * @param int|null $merId 商户ID。这是一个可选参数，用于进一步筛选服务，只返回属于指定商户的服务。
     *                       如果未提供此参数，则查询将不考虑商户过滤，返回与用户相关联的所有服务。
     * @return array|null 返回符合查询条件的服务信息数组。如果找不到符合条件的服务，则返回null。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getService($uid, $merId = null)
    {
        // 从StoreService中获取数据库实例，并构建查询条件
        return StoreService::getDB()->where('uid', $uid)->when(!is_null($merId), function ($query) use($merId) {
            // 如果提供了商户ID，则添加额外的查询条件来过滤商户
            $query->where('mer_id', $merId);
        })->where('is_del', 0)->where('is_open',1)->find();
    }

    /**
     * 检查指定字段是否存在指定值（且未被删除）。
     *
     * 此方法用于查询数据库中是否存在指定字段的值，并且该记录没有被删除。
     * 可以通过传递一个排除ID来排除特定的记录。
     *
     * @param string $field 要检查的字段名。
     * @param mixed $value 字段应该具有的值。
     * @param int|null $except 排除的ID，可选参数，用于排除特定的记录。
     * @return bool 如果找到符合条件的记录，则返回true，否则返回false。
     */
    public function fieldExists($field, $value, ?int $except = null): bool
    {
        // 初始化查询，查询具有指定字段值并且未被删除的记录
        $query = ($this->getModel())::getDB()->where($field, $value)->where('is_del', 0);

        // 如果提供了排除ID，则添加条件以排除该ID的记录
        if (!is_null($except)) {
            $query->where($this->getPk(), '<>', $except);
        }

        // 返回查询结果是否存在，即记录数是否大于0
        return $query->count() > 0;
    }

    /**
     * 检查指定商户ID和ID组合是否存在对应的记录。
     *
     * 本函数用于查询数据库中是否存在特定商户ID和ID组合的记录，
     * 其中ID通常代表某个实体的唯一标识，而mer_id则表示该实体所属的商户ID。
     * 函数通过计算符合条件的记录数量来判断记录是否存在，如果数量大于0，则表示存在。
     *
     * @param int $merId 商户ID，用于限定查询的商户范围。
     * @param int $id 需要查询的ID，用于指定具体的实体。
     * @return bool 如果存在符合条件的记录，则返回true，否则返回false。
     * @throws DbException
     */
    public function merExists(int $merId, int $id)
    {
        // 通过StoreService获取数据库操作对象，并构造查询条件，查询指定ID、商户ID，并且未被删除的记录数量。
        return StoreService::getDB()->where($this->getPk(), $id)->where('mer_id', $merId)->where('is_del', 0)->count($this->getPk()) > 0;
    }

    /**
     * 检查用户是否已设置特定服务
     *
     * 本函数用于查询指定用户是否为特定商家设置了服务。通过传入用户的ID（$uid）和商家的ID（$merId），
     * 函数将查询数据库中是否存在相应的服务记录。如果存在，则表示用户已设置该服务；如果不存在，则表示用户未设置。
     * 可选参数$except允许指定一个服务ID进行排除查询，即查询时不包括指定的服务ID。
     *
     * @param string $merId 商家ID，用于指定查询的商家。
     * @param string $uid 用户ID，用于指定查询的用户。
     * @param int|null $except 可选参数，用于指定需要排除的服务ID。
     * @return bool 如果用户已设置服务则返回true，否则返回false。
     * @throws DbException
     */
    public function issetService($merId, $uid, ?int $except = null)
    {
        // 通过StoreService获取数据库操作对象，并构造查询条件
        // 查询条件包括：用户ID（$uid）、商家ID（$merId）、服务未被删除（'is_del'为0）
        // 如果传入了$except参数，则进一步排除指定的服务ID
        return StoreService::getDB()->where('uid', $uid)->when($except, function ($query, $except) {
                $query->where($this->getPk(), '<>', $except);
            })->where('mer_id', $merId)->where('is_del', 0)->count($this->getPk()) > 0;
    }

    /**
     * 检查用户是否绑定特定服务
     *
     * 本函数用于查询指定用户是否绑定了一定的服务实例。通过传入用户的ID（$uid），
     * 它可以在数据库中搜索与该用户ID相关联的服务绑定记录。如果可选参数$except被提供，
     * 函数将排除这个ID，不考虑它是否被绑定。
     *
     * @param int $uid 用户的唯一标识符。用于查询与该用户相关联的绑定记录。
     * @param int|null $except 可选参数，用于指定一个ID，函数将排除这个ID进行查询。
     *                        这允许检查用户是否绑定了除特定ID之外的任何服务。
     * @return bool 如果用户绑定了至少一个服务，则返回true；否则返回false。
     * @throws DbException
     */
    public function isBindService($uid, ?int $except = null)
    {
        // 从StoreService获取数据库实例，并构造查询条件
        // 首先，查询与用户ID($uid)匹配的记录
        return StoreService::getDB()->where('uid', $uid)
            // 如果提供了$except参数，则添加额外的查询条件，排除特定ID
            ->when($except, function ($query, $except) {
                $query->where($this->getPk(), '<>', $except);
            })
            // 确保查询不包括已删除的记录
            ->where('is_del', 0)
            // 计算满足条件的记录数，如果大于0，则表示用户绑定了服务
            ->count($this->getPk()) > 0;
    }

    /**
     * 删除记录
     *
     * 本函数用于标记指定ID的数据记录为删除状态。在实际数据库操作中，并不直接物理删除记录，
     * 而是通过将记录的is_del字段设置为1来表示该记录已被删除。这种方式可以避免因误删导致的数据损失，
     * 同时也便于后续如果需要恢复数据或进行数据清理工作。
     *
     * @param int $id 需要被标记为删除的记录的ID
     * @return int 返回影响的行数，即被标记为删除的记录数
     * @throws DbException
     */
    public function delete(int $id)
    {
        // 通过StoreService获取数据库操作对象，并构造SQL语句，更新指定ID的记录的is_del字段为1
        return StoreService::getDB()->where($this->getPk(), $id)->update(['is_del' => 1]);
    }

    /**
     * 根据商家ID获取聊天服务信息
     *
     * 本函数旨在通过商家ID从数据库中检索与聊天服务相关的特定信息。
     * 它使用了StoreService的数据库访问功能来执行查询，并应用了多个条件来筛选结果，
     * 包括商家ID、是否被删除、以及状态。查询结果按照状态、排序和创建时间进行排序。
     * 最后，隐藏了is_del字段以防止其在结果中显示。
     *
     * @param string $merId 商家ID，用于查询特定商家的聊天服务信息。
     * @return array 返回符合查询条件的聊天服务信息数组，如果不存在则为null。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getChatService($merId)
    {
        // 使用StoreService的数据库访问方法执行查询
        return StoreService::getDB()->where('mer_id', $merId)->where('is_del', 0)->where('status', 1)->order('status DESC, sort DESC, create_time ASC')
            ->hidden(['is_del'])->find();
    }

    /**
     * 根据商家ID获取随机服务信息
     *
     * 本函数旨在从数据库中检索与特定商家ID相关联的服务，并从中随机选择一个返回。
     * 它首先根据一系列条件过滤服务，如是否开启、是否删除、状态等，然后按照特定排序规则获取数据。
     * 如果没有找到符合条件的服务，或者找到了但数量为1，则直接返回该服务（如果存在）。
     * 在有多个服务符合要求的情况下，会随机选择一个返回。
     *
     * @param string $merId 商家ID，用于查询特定商家的服务
     * @return array|null 返回随机选择的服务信息数组，如果没有找到符合条件的服务则返回null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getRandService($merId)
    {
        // 根据条件从数据库中查询符合要求的服务，排序规则为状态降序、排序降序、创建时间升序
        $services = StoreService::getDB()->where('mer_id', $merId)->where('is_open',1)->where('is_del', 0)->where('status', 1)->order('status DESC, sort DESC, create_time ASC')
            ->hidden(['is_del'])->select();

        // 检查查询结果是否存在且不为空
        if (!$services || !count($services)) return null;

        // 如果只找到一个服务，直接返回该服务
        if (count($services) === 1) {
            return $services[0];
        }

        // 随机选择一个服务返回，确保随机数在有效范围内
        return $services[max(random_int(0, count($services) - 1), 0)];
    }

    /**
     * 根据服务ID获取有效服务信息
     *
     * 本函数用于从数据库中查询指定服务ID的服务信息，只返回开启状态、正常状态且未被删除的服务。
     * 这样可以确保获取到的信息是当前可用的服务，避免了返回已关闭、异常或被删除的服务信息。
     *
     * @param int $id 服务ID
     * @return array 返回符合查询条件的服务信息数组，如果未找到则返回空数组。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getValidServiceInfo($id)
    {
        // 通过StoreService类的getDB方法获取数据库操作对象
        // 然后使用where方法指定查询条件，包括服务ID、服务状态、是否开启以及是否被删除
        // 最后使用hidden方法隐藏is_del字段，不将其返回给调用者
        // 返回查询结果，如果不存在符合要求的服务，则返回空数组
        return StoreService::getDB()->where('service_id', $id)->where('is_open',1)->where('status', 1)->where('is_del', 0)->hidden(['is_del'])->find();
    }

    /**
     * 获取通知服务信息
     *
     * 本函数用于查询指定商户ID的通知服务相关信息。
     * 通过筛选条件，获取到满足条件的记录中与通知服务相关的用户ID、电话和昵称信息。
     * 主要用于通知服务的查询和管理，确保通知对象的准确性和有效性。
     *
     * @param string $merId 商户ID，用于指定查询的商户范围。
     * @return array 返回一个包含用户ID、电话和昵称的数组，数组中的每个元素代表一个满足条件的通知服务对象。
     */
    public function getNoticeServiceInfo($merId)
    {
        // 使用StoreService的数据库操作对象，查询满足条件的通知服务信息。
        // 条件包括：商户ID为$merId，状态为启用，通知开启，未被删除。
        // 返回查询结果中uid, phone, nickname字段组成的列数组。
        return StoreService::getDB()->where('mer_id', $merId)->where('status', 1)->where('notify', 1)
            ->where('is_del', 0)->column('uid,phone,nickname');
    }


    /**
     * 将指定记录的状态设置为关闭
     *
     * 本函数通过传入的ID和字段名称，更新对应记录的状态为关闭（0）。
     * 主要用于在数据库中更新某些实体的状态，例如文章、订单等的关闭操作。
     *
     * @param int $id 主键ID，用于定位特定记录
     * @param string $field 需要与ID匹配的字段名称，允许灵活指定字段进行匹配而非固定主键
     */
    public function close($id,$field)
    {
        // 使用模型获取数据库实例，并通过where子句指定条件，更新记录的状态为0（关闭）
        $this->getModel()::getDB()->where($field, $id)->update(['status' => 0]);
    }

}
