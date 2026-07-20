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
use app\common\model\user\User;
use app\common\model\system\merchant\FinancialRecord;

class FinancialRecordDao extends BaseDao
{

    protected function getModel(): string
    {
        return FinancialRecord::class;
    }

    /**
     * 生成订单编号
     *
     * 本函数旨在生成一个唯一的订单编号。编号由时间戳和随机数组成，确保了编号的唯一性和时间顺序。
     * 时间戳精确到毫秒，随机数确保在同一毫秒内仍能生成不同的编号。
     *
     * @return string 返回生成的订单编号
     */
    public function getSn()
    {
        // 获取当前时间的微秒和秒部分
        list($msec, $sec) = explode(' ', microtime());

        // 将微秒和秒转换为毫秒，并格式化为整数
        $msectime = number_format((floatval($msec) + floatval($sec)) * 1000, 0, '', '');

        // 生成订单编号：前缀 + 毫秒时间戳 + 随机数
        // 保证随机数在特定范围内，避免生成重复的订单编号
        $orderId = 'jy' . $msectime . mt_rand(10000, max(intval($msec * 10000) + 10000, 98369));

        return $orderId;
    }


    /**
     * 对数据进行增量更新操作。
     * 此函数用于处理特定业务逻辑，即给定数据数组和商户ID后，通过生成序列号并设置特定的业务标志，来创建新的数据记录。
     *
     * @param array $data 数据数组，包含需要进行增量更新的所有字段。
     * @param string $merId 商户ID，用于标识数据所属的商户。
     * @return mixed 返回执行创建操作的结果，具体类型取决于create方法的实现。
     */
    public function inc(array $data, $merId)
    {
        // 为数据数组添加商户ID，用于标识数据来源。
        $data['mer_id'] = $merId;
        // 设置业务标志为1，表示这是一条财务相关记录。
        $data['financial_pm'] = 1;
        // 生成序列号，并将其添加到数据数组中，用于唯一标识该记录。
        $data['financial_record_sn'] = $this->getSn();
        // 调用create方法，使用加工后的数据数组创建新的记录，并返回创建结果。
        return $this->create($data);
    }


    /**
     * 解密数据并创建记录
     *
     * 本函数用于处理解密后的数据，并根据这些数据创建相应的记录。
     * 它首先将传入的商户ID赋值给数据数组，然后设置财务相关字段的值，
     * 最后调用创建方法来实际创建记录。
     *
     * @param array $data 解密后的数据数组
     * @param string $merId 商户ID，用于标识数据所属的商户
     * @return mixed 创建记录的结果，可能是ID或其他标识符
     */
    public function dec(array $data, $merId)
    {
        // 将商户ID赋值给数据数组
        $data['mer_id'] = $merId;

        // 初始化财务相关字段的值
        $data['financial_pm'] = 0;

        // 生成记录序列号，并赋值给数据数组
        $data['financial_record_sn'] = $this->getSn();

        // 调用创建方法，传入处理后的数据数组，返回创建结果
        return $this->create($data);
    }

    /**
     * 根据条件搜索数据。
     *
     * 该方法用于根据提供的条件数组来构建查询语句，灵活地根据不同的条件进行数据库查询。
     * 条件数组中的每个键值对代表一个查询条件，方法内部通过判断键的存在与否来决定是否添加相应的查询条件。
     * 支持的条件包括财务类型、商户ID、用户信息、用户ID、关键字、日期范围以及是否为商户等。
     *
     * @param array $where 包含查询条件的数组。
     *
     * @return \Illuminate\Database\Query\Builder|static 返回构建好的查询语句。
     */
    public function search(array $where)
    {
        // 获取模型实例并连接数据库。
        $query = $this->getModel()::getDB();

        // 如果条件数组中包含'financial_type'键，且其值不为空，添加WHERE IN查询条件。
        $query = $query->when(isset($where['financial_type']) && $where['financial_type'] !== '', function ($query) use ($where) {
            $query->whereIn('financial_type', $where['financial_type']);
        });

        // 如果条件数组中包含'mer_id'键，且其值不为空，添加WHERE查询条件。
        $query = $query->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
            $query->where('mer_id', $where['mer_id']);
        });

        // 如果条件数组中包含'user_info'键，且其值不为空，添加WHERE查询条件。
        $query = $query->when(isset($where['user_info']) && $where['user_info'] !== '', function ($query) use ($where) {
            $query->where('user_info', $where['user_info']);
        });

        // 如果条件数组中包含'user_id'键，且其值不为空，添加WHERE查询条件。
        $query = $query->when(isset($where['uid']) && $where['uid'] !== '', function ($query) use ($where) {
            $query->where('user_id', $where['uid']);
        });
        $query = $query->when(isset($where['nickname']) && $where['nickname'] !== '', function ($query) use ($where) {
            $uid = User::where('nickname', 'like', "%{$where['nickname']}%")->column('uid');
            $query->whereIn('user_id', $uid);
        });
        // 如果条件数组中包含'keyword'键，且其值不为空，添加WHERE LIKE查询条件，搜索字段包括订单号、用户信息和财务记录号。
        $query = $query->when(isset($where['keyword']) && $where['keyword'] !== '', function ($query) use ($where) {
            $query->whereLike('order_sn|user_info|financial_record_sn', "%{$where['keyword']}%");
        });
        $query = $query->when(isset($where['order_sn']) && $where['order_sn'] !== '', function ($query) use ($where) {
            $query->whereLike('order_sn', "%{$where['order_sn']}%");
        });
        $query = $query->when(isset($where['real_name']) && $where['real_name'] !== '', function ($query) use ($where) {
            $query->whereLike('user_info', "%{$where['real_name']}%");
        });
        $query = $query->when(isset($where['financial_pm']) && $where['financial_pm'] !== '', function ($query) use ($where) {
            $query->where('financial_pm', $where['financial_pm']);
        });
        $query->when(isset($where['pay_type']) && $where['pay_type'] !== '', function ($query) use ($where) {
            if (is_int($where['pay_type'])) {
                $query->where('pay_type', $where['pay_type']);
            } else {
                $query->whereIn('pay_type', $where['pay_type']);
            }
        });

        // 如果条件数组中包含'date'键，且其值不为空，调用getModelTime函数添加日期范围查询条件。
        $query = $query->when(isset($where['date']) && $where['date'] !== '', function ($query) use ($where) {
            getModelTime($query, $where['date'], 'create_time');
        });

        // 如果条件数组中包含'is_mer'键，根据其值添加不同的WHERE查询条件，判断是否为商户以及类型条件。
        $query = $query->when(isset($where['is_mer']) && $where['is_mer'] !== '', function ($query) use ($where) {
            if($where['is_mer']){
                $query->where('mer_id',$where['is_mer'])->where('type','in',[0,1]);
            }else{
                $query->where('type','in',[1,2]);
            }
        });

        // 返回构建好的查询语句。
        return $query;
    }

    /**
     *  根据条件和时间查询出相对类型的数量个金额
     * @param int $type
     * @param array $where
     * @param string $date
     * @param array $financialType
     * @return array
     * @author Qinii
     * @day 4/14/22
     */
    public function getDataByType(int $type, array $where, string  $date, array $financialType)
    {
        if (empty($financialType)) return [0,0];
        $query = $this->search($where)->where('financial_type','in',$financialType);

        if($type == 1) {
            $query->whereDay('create_time',$date);
        } else if ($type ==2) {
            $query->whereMonth('create_time',$date);
        }

        $count  = $query->group('order_id')->count();
        $number = $query->sum('number');

        return [$count,$number];
    }

    public function getDateByPayType(int $type, array $where, string $date, array $payType)
    {
        if (empty($payType)) return [0,0];
        $query = $this->search($where)->whereIn('pay_type', $payType);
        if($type == 1) {
            $query->whereDay('create_time',$date);
        } else if ($type ==2) {
            $query->whereMonth('create_time',$date);
        }
        $count  = $query->group('order_id')->count();
        $number = $query->sum('number');
        return [$count,$number];
    }
}
