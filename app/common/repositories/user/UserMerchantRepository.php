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


use app\common\dao\user\UserMerchantDao;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\staff\StaffsRepository;
use app\common\repositories\system\config\ConfigValueRepository;
use FormBuilder\Factory\Elm;
use think\facade\Db;
use think\facade\Route;
use app\common\repositories\store\service\StoreServiceRepository;

/**
 * Class UserMerchantRepository
 * @package app\common\repositories\user
 * @author xaboy
 * @day 2020/10/20
 * @mixin UserMerchantDao
 */
class UserMerchantRepository extends BaseRepository
{
    /**
     * UserMerchantRepository constructor.
     * @param UserMerchantDao $dao
     */
    public function __construct(UserMerchantDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 根据条件获取用户列表
     *
     * @param array $where 查询条件
     * @param int $page 分页页码
     * @param int $limit 每页数据数量
     * @return array 包含用户列表和总数的信息
     */
    public function getList(array $where, $page, $limit)
    {
        // 获取默认头像配置
        // 获取默认头像
        $user_default_avatar = app()->make(ConfigValueRepository::class)->get('user_default_avatar', 0);

        // 根据条件初始化查询
        $query = $this->dao->search($where);

        // 计算总条数
        $count = $query->count();

        // 实例化用户标签仓库
        $make = app()->make(UserLabelRepository::class);

        // 配置查询字段并进行分页查询，按用户商家ID降序排列
        $list = $query->setOption('field', [])->field('A.uid,A.user_merchant_id,B.avatar,B.nickname,B.user_type,A.last_pay_time,A.first_pay_time,A.label_id,A.create_time,A.last_time,A.pay_num,A.pay_price,B.phone,B.is_svip,B.svip_endtime,B.sex')
            ->page($page, $limit)->order('A.user_merchant_id DESC')->select()
            ->each(function ($item) use ($where, $make, $user_default_avatar) {
                // 根据环境变量和用户类型，隐藏或显示电话号码
                if (env('SHOW_PHONE',false) && $item->phone && is_numeric($item->phone)){
                    if (app('request')->userType() !== 2 || app('request')->adminInfo()['level'] != 0) {
                        $item->phone = substr_replace($item->phone, '****', 3, 4);
                    }
                }
                // 根据标签ID获取用户标签
                $item->label = count($item['label_id']) ? $make->labels($item['label_id'], $where['mer_id']) : [];
                // 设置默认头像
                if (empty($item->avatar)) {
                    $item->avatar = $user_default_avatar;
                }
            });

        // 返回用户总数和列表信息
        return compact('count', 'list');
    }

    /**
     * 添加客服场景：按手机号查全平台用户，并自动写入本店客户关联后返回
     */
    public function searchPlatformUserByPhoneForStaff(int $merId, string $phone, int $page, int $limit): array
    {
        $user = Db::name('user')->where('phone', $phone)->where('status', 1)->find();
        if (!$user) {
            return ['count' => 0, 'list' => []];
        }
        $uid = (int)$user['uid'];
        $exists = $this->dao->getWhere(['uid' => $uid, 'mer_id' => $merId]);
        if (!$exists) {
            $this->create($uid, $merId);
        }
        $where = ['mer_id' => $merId, 'keyword' => $phone];
        return $this->getList($where, $page, $limit);
    }

    /**
     * 创建一个新的记录。
     *
     * 本函数用于通过提供的用户ID和商家ID创建一个新的数据记录。它不涉及具体的业务逻辑处理，仅负责数据的插入操作。
     * 参数$uid代表用户的唯一标识，$merId代表商家的唯一标识。这两个标识符一起用于唯一确定新创建的数据记录。
     *
     * @param int $uid 用户ID，用于标识数据记录的所属用户。
     * @param int $merId 商家ID，用于标识数据记录的所属商家。
     * @return bool|mixed 返回创建操作的结果。成功时返回新创建的记录的ID，失败时返回false。
     */
    public function create($uid, $merId)
    {
        // 调用DAO层的create方法，传入包含用户ID和商家ID的数组，执行创建操作。
        return $this->dao->create([
            'uid' => $uid,
            'mer_id' => $merId,
        ]);
    }

    /**
     * 根据用户ID和商家ID获取用户信息。
     *
     * 本函数旨在通过用户ID和商家ID查询用户信息。如果用户不存在，则尝试创建该用户并返回。
     * 这种设计确保了即使用户数据在调用前未存在，也能通过一次调用即创建并获取到用户信息，
     * 提高了代码的可靠性和效率。
     *
     * @param int $uid 用户ID。这是识别用户的唯一标识。
     * @param int $mer_id 商家ID。表示用户所属的商家。
     * @return object 用户对象，包含用户信息。如果用户不存在，则为新创建的用户对象。
     */
    public function getInfo($uid, $mer_id)
    {
        // 根据$uid和$mer_id查询用户信息
        $user = $this->dao->getWhere(compact('uid', 'mer_id'));

        // 如果用户不存在，则尝试创建该用户
        if (!$user) $user = $this->create($uid, $mer_id);

        // 返回用户对象
        return $user;
    }

    /**
     * 更新用户的支付时间及相关支付统计信息。
     *
     * 本函数用于在用户完成支付后，更新其支付时间、支付次数、支付总额等统计信息。
     * 如果是首次支付，还会记录首次支付时间。
     *
     * @param int $uid 用户ID，用于标识唯一用户。
     * @param string $merId 商户ID，用于标识用户是在哪个商户完成的支付。
     * @param float $pay_price 本次支付的金额，以浮点数表示。
     * @param bool $flag 标志位，用于决定是否增加支付次数。默认为true，即增加支付次数。
     */
    public function updatePayTime($uid, $merId, $pay_price, $flag = true)
    {
        // 根据用户ID和商户ID获取用户信息。
        $user = $this->getInfo($uid, $merId);
        // 获取当前时间，用于更新支付时间。
        $time = date('Y-m-d H:i:s');
        // 更新用户的最后一次支付时间。
        $user->last_pay_time = $time;
        // 如果标志位为true，增加用户的支付次数。
        if ($flag) {
            $user->pay_num++;
        }
        // 更新用户的累计支付金额，使用bcadd确保浮点数计算的准确性。
        $user->pay_price = bcadd($user->pay_price, $pay_price, 2);
        // 如果用户尚未记录首次支付时间，将当前时间设为首次支付时间。
        if (!$user->first_pay_time) {
            $user->first_pay_time = $time;
        }
        // 保存更新后的用户信息。
        $user->save();
    }

    /**
     * 移除标签
     * 本函数用于从数据库中移除指定ID的标签。它通过搜索具有指定标签ID的记录，
     * 然后更新这些记录的标签ID字段，从现有的标签列表中移除指定的标签ID。
     *
     * @param int $id 标签的ID，指定要移除的标签。
     * @return bool 更新操作的结果，true表示成功，false表示失败。
     */
    public function rmLabel($id)
    {
        // 使用DAO层的search方法查询具有指定标签ID的记录，并准备更新这些记录的标签ID字段。
        // 更新操作通过在标签ID字段上执行SQL函数来实现，该函数从现有的标签ID字符串中移除指定的ID。
        return $this->dao->search(['label_id' => $id])->update([
            'A.label_id' => Db::raw('(trim(BOTH \',\' FROM replace(CONCAT(\',\',A.label_id,\',\'),\',' . $id . ',\',\',\')))')
        ]);
    }


    /**
     * 创建修改用户标签的表单
     *
     * 本函数用于生成一个表单，该表单允许操作者修改用户标签。通过提供的用户ID和商家ID，
     * 函数检索现有的用户标签，并为这些标签创建一个可以选择的列表。表单提交的URL是动态生成的，
     * 保证了表单提交的路由正确性。
     *
     * @param int $merId 商家ID，用于获取商家相关的用户标签选项
     * @param int $id 用户ID，用于获取当前用户已有的标签
     * @return \FormBuilder\Form|\Laravie\Codex\Contracts\Response
     */
    public function changeLabelForm($merId, $id)
    {
        // 通过用户ID获取用户对象
        $user = $this->dao->get($id);

        // 实例化用户标签仓库，用于后续获取所有标签选项和处理标签交集
        /** @var UserLabelRepository $make */
        $userLabelRepository = app()->make(UserLabelRepository::class);

        // 获取所有标签选项，包括商家拥有的标签
        $data = $userLabelRepository->allOptions($merId);

        // 创建表单，表单提交的URL是通过路由名称和参数动态构建的
        return Elm::createForm(Route::buildUrl('merchantUserChangeLabel', compact('id'))->build(), [
            // 创建多选下拉列表，用于选择用户的标签
            Elm::selectMultiple('label_id', '用户标签：', $userLabelRepository->intersection($user->label_id, $merId, 0))->options(function () use ($data) {
                $options = [];
                // 遍历标签数据，构建下拉列表的选项
                foreach ($data as $value => $label) {
                    $options[] = compact('value', 'label');
                }
                return $options;
            })
        ])->setTitle('修改用户标签');
    }

    public function getManagerUid($where)
    {
        $staffsRepository = app()->make(StaffsRepository::class);
        $storeServiceRepository = app()->make(StoreServiceRepository::class);

        $uids1 = $storeServiceRepository->search($where)->column('uid');
        $uids2 = $staffsRepository->search($where)->column('uid');
        $uids = array_unique(array_merge($uids1, $uids2));
        return $uids;
    }
}
