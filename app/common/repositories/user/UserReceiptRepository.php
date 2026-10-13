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
use app\common\dao\user\UserReceiptDao;

class UserReceiptRepository extends BaseRepository
{
    protected $dao;

    public function __construct(UserReceiptDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 检查给定的用户ID是否存在于指定的数据记录中。
     *
     * 此函数用于通过用户ID和主键ID组合来查询数据记录的数量。
     * 如果存在匹配的记录且未被删除，则返回计数结果，这表明给定的用户ID是存在的。
     * 主要用于验证数据的唯一性和存在性，特别是在执行更新或删除操作之前。
     *
     * @param int $id 数据记录的主键ID
     * @param int $uid 要查询的用户ID
     * @return int 返回匹配记录的数量，如果为0，则表示用户ID不存在或已被删除。
     */
    public function uidExists(int $id,int $uid)
    {
        // 根据主键ID、用户ID和删除状态（未删除）查询数据记录的数量。
        return $this->dao->getWhereCount(['uid' => $uid,$this->dao->getPk() => $id,'is_del' => 0]);
    }


    /**
     * 获取默认记录
     *
     * 本方法用于查询指定用户ID的默认记录。通过调用DAO层的方法，传递条件数组来查询数据库中满足条件的默认记录。
     * 主要用于在系统中确定某些设置或信息的默认值。
     *
     * @param int $uid 用户ID。本参数用于指定查询哪个用户的默认记录。
     * @return mixed 返回查询结果。具体类型取决于DAO层的实现，可能是一个对象、数组或者null。
     */
    public function getIsDefault(int $uid)
    {
        // 构造查询条件，查询指定用户ID且标记为默认的记录
        return $this->dao->getWhere(['uid' => $uid,'is_default' => 1]);
    }

    /**
     * 获取列表数据
     *
     * 本函数用于根据条件获取特定的数据列表。它首先确保待查询的数据未被删除（is_del = 0），
     * 然后调用DAO层的getSearch方法进行查询。查询结果将按照is_default降序和create_time升序排列。
     *
     * @param array $where 查询条件数组。该数组包含了用户自定义的查询条件，本函数会在此基础上添加一个固定条件：is_del = 0。
     * @return array 返回查询结果集，是一个由符合条件的数据组成的数组。
     */
    public function getList(array $where)
    {
        // 设置数据未删除的标记，确保只查询未被删除的数据
        $where['is_del'] = 0;

        // 调用DAO层的getSearch方法进行查询，并指定排序方式为is_default降序和create_time升序
        return $this->dao->getSearch($where)->order('is_default DESC , create_time ASC')->select();
    }

    /**
     * 根据条件获取详细信息
     *
     * 本函数通过调用DAO层的getSearch方法，传入查询条件，进而获取符合条件的数据详情。
     * 主要用于在业务逻辑层中进行数据的查询操作，封装了对数据库查询的细节，提高了代码的可维护性和可读性。
     *
     * @param array $where 查询条件，以数组形式传递，数组的每个元素都是一个查询条件。
     * @return mixed 返回查询结果，通常是数组或者对象，具体取决于DAO层的find方法的实现。
     */
    public function detail(array $where)
    {
        // 调用DAO层的方法进行查询，并返回查询结果
        return $this->dao->getSearch($where)->find();
    }
}
