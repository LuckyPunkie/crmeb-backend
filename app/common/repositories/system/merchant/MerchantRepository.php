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


namespace app\common\repositories\system\merchant;


use think\facade\Log;
use crmeb\services\pay\Pay;
use crmeb\services\wechat\OfficialAccount;
use app\common\dao\system\merchant\MerchantDao;
use app\common\model\store\order\StoreOrder;
use app\common\model\store\product\ProductReply;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\MerchantTakeRepository;
use app\common\repositories\store\coupon\StoreCouponRepository;
use app\common\repositories\store\coupon\StoreCouponUserRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\store\product\ProductCopyRepository;
use app\common\repositories\store\product\ProductRepository;
use app\common\repositories\store\product\SpuRepository;
use app\common\repositories\store\service\StoreServiceRepository;
use app\common\repositories\store\shipping\ShippingTemplateRepository;
use app\common\repositories\store\StoreCategoryRepository;
use app\common\repositories\system\attachment\AttachmentCategoryRepository;
use app\common\repositories\system\attachment\AttachmentRepository;
use app\common\repositories\system\operate\OperateLogRepository;
use app\common\repositories\system\serve\ServeOrderRepository;
use app\common\repositories\user\UserBillRepository;
use app\common\repositories\user\UserRelationRepository;
use app\common\repositories\user\UserVisitRepository;
use app\common\repositories\wechat\RoutineQrcodeRepository;
use crmeb\jobs\ChangeMerchantStatusJob;
use crmeb\jobs\ClearMerchantStoreJob;
use crmeb\services\merchant\MerchantCoreService;
use crmeb\services\QrcodeService;
use crmeb\services\UploadService;
use FormBuilder\Exception\FormBuilderException;
use FormBuilder\Factory\Elm;
use FormBuilder\Form;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Queue;
use think\facade\Route;
use think\Model;
use app\common\repositories\system\RelevanceRepository;

/**
 * 商户
 */
class MerchantRepository extends BaseRepository
{

    /**
     * MerchantRepository constructor.
     * @param MerchantDao $dao
     */
    public function __construct(MerchantDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 创建商户，并通过 MerchantCoreService 生成核心字段写入许可。
     *
     * @param array $data 商户创建数据。
     * @return mixed 返回 DAO 创建结果。
     * @throws ValidateException 核心字段写入未授权或业务校验失败时抛出。
     */
    public function create(array $data)
    {
        return app()->make(MerchantCoreService::class)->createMerchant($data, function () use ($data) {
            return $this->dao->create($data);
        });
    }

    /**
     * 更新商户，并通过 MerchantCoreService 生成核心字段写入许可。
     *
     * @param int $id 商户 ID。
     * @param array $data 商户更新数据。
     * @return mixed 返回 DAO 更新结果。
     * @throws ValidateException 核心字段写入未授权或业务校验失败时抛出。
     */
    public function update(int $id, array $data)
    {
        return app()->make(MerchantCoreService::class)->updateMerchant($id, $data, function () use ($id, $data) {
            return $this->dao->update($id, $data);
        });
    }

    /**
     * 增加商户余额。
     *
     * @param int $merId 商户 ID。
     * @param float $num 增加金额。
     * @return mixed 返回 DAO 写入结果。
     * @throws ValidateException 缺少 mer_money 写入许可或业务校验失败时抛出。
     */
    public function addMoney(int $merId, float $num)
    {
        return app()->make(MerchantCoreService::class)->writeMerchant($merId, ['mer_money'], function () use ($merId, $num) {
            return $this->dao->addMoney($merId, $num);
        }, 'merchant_money_add');
    }

    /**
     * 扣减商户余额。
     *
     * @param int $merId 商户 ID。
     * @param float $num 扣减金额。
     * @return mixed 返回 DAO 写入结果。
     * @throws ValidateException 缺少 mer_money 写入许可或业务校验失败时抛出。
     */
    public function subMoney(int $merId, float $num)
    {
        return app()->make(MerchantCoreService::class)->writeMerchant($merId, ['mer_money'], function () use ($merId, $num) {
            return $this->dao->subMoney($merId, $num);
        }, 'merchant_money_sub');
    }

    /**
     * 调整商户可复制商品次数。
     *
     * @param int $merId 商户 ID。
     * @param int $num 调整数量，可为正负数。
     * @return mixed 返回 DAO 写入结果。
     * @throws ValidateException 缺少 copy_product_num 写入许可或业务校验失败时抛出。
     */
    public function changeCopyNum(int $merId, int $num)
    {
        return app()->make(MerchantCoreService::class)->writeMerchant($merId, ['copy_product_num'], function () use ($merId, $num) {
            return $this->dao->changeCopyNum($merId, $num);
        }, 'merchant_copy_num_change');
    }

    /**
     * 清理指定商户类型关联。
     *
     * @param int $typeId 商户类型 ID。
     * @return mixed 返回 DAO 写入结果。
     * @throws ValidateException 缺少 type_id 写入许可或业务校验失败时抛出。
     */
    public function clearTypeId(int $typeId)
    {
        return app()->make(MerchantCoreService::class)->writeMerchant(null, ['type_id'], function () use ($typeId) {
            return $this->dao->clearTypeId($typeId);
        }, 'merchant_type_clear');
    }

    /**
     * 增加商户指定数值字段。
     *
     * @param int $merId 商户 ID。
     * @param int $num 增加数量。
     * @param string $field 字段名。
     * @return mixed 返回 DAO 写入结果。
     * @throws ValidateException 字段属于核心字段但缺少写入许可或业务校验失败时抛出。
     */
    public function addFieldNum(int $merId, int $num, string $field)
    {
        return app()->make(MerchantCoreService::class)->writeMerchant($merId, [$field], function () use ($merId, $num, $field) {
            return $this->dao->addFieldNum($merId, $num, $field);
        }, 'merchant_field_add');
    }

    /**
     * 减少商户指定数值字段。
     *
     * @param int $merId 商户 ID。
     * @param int $num 减少数量。
     * @param string $field 字段名。
     * @return mixed 返回 DAO 写入结果。
     * @throws ValidateException 字段属于核心字段但缺少写入许可或业务校验失败时抛出。
     */
    public function sumFieldNum(int $merId, int $num, string $field)
    {
        return app()->make(MerchantCoreService::class)->writeMerchant($merId, [$field], function () use ($merId, $num, $field) {
            return $this->dao->sumFieldNum($merId, $num, $field);
        }, 'merchant_field_sub');
    }

    /**
     * 按商户类型批量更新保证金配置。
     *
     * @param mixed $typeId 商户类型 ID。
     * @param mixed $margin 保证金金额。
     * @param mixed $is_margin 保证金状态。
     * @return mixed 返回 DAO 写入结果。
     * @throws ValidateException 缺少 is_margin 或 margin 写入许可或业务校验失败时抛出。
     */
    public function updateMargin($typeId, $margin, $is_margin)
    {
        return app()->make(MerchantCoreService::class)->writeMerchant(null, ['is_margin', 'margin'], function () use ($typeId, $margin, $is_margin) {
            return $this->dao->updateMargin($typeId, $margin, $is_margin);
        }, 'merchant_type_margin_update');
    }

    /**
     * 根据条件获取商户列表
     *
     * 本函数用于根据给定的条件从数据库中检索商户列表。它支持分页和条件查询，同时加载与每个商户相关的管理员、类别和类型信息。
     *
     * @param array $where 查询条件数组
     * @param int $page 当前页码
     * @param int $limit 每页记录数
     * @return array 包含商户数量和商户列表的数组
     */
    public function lst(array $where, $page, $limit)
    {
        // 根据条件发起查询
        $query = $this->dao->search($where);

        // 计算满足条件的商户总数
        $count = $query->count($this->dao->getPk());

        // 设置查询的字段，并启用分页，加载关联信息
        $list = $query->page($page, $limit)
                      ->setOption('field', [])
                      ->with([
                          'admin' => function ($query) {
                              // 加载管理员的mer_id和account字段
                              $query->field('mer_id,account');
                          },
                          'merchantCategory', // 加载商户类别信息
                          'merchantType',      // 加载商户类型信息
                          'merchantRegion', // 加载商户区域信息
                          'merchantBusiness' // 加载商户业务信息
                      ])
                      ->field('sort,mer_id,mer_name,real_name,mer_phone,mer_address,mark,status,create_time,is_best,is_trader,type_id,category_id,copy_product_num,export_dump_num,is_margin,margin,ot_margin,mer_avatar,margin_remind_time,mer_banner,region_id,business_id') // 指定查询的字段
                      ->select(); // 执行查询

        // 返回商户总数和商户列表的数组
        return compact('count', 'list');
    }

    /**
     * 统计有效和无效的数据条数。
     *
     * 本函数用于根据特定条件统计数据表中状态为有效和无效的数据项数量。
     * 通过修改条件中的状态值，两次查询以分别获取有效和无效数据的数量。
     *
     * @param array $where 查询条件，本函数将在此条件下添加状态过滤。
     * @return array 返回包含有效（valid）和无效（invalid）数据条数的数组。
     */
    public function count($where)
    {
        // 将查询条件中的状态设置为有效（1），以统计有效数据的数量。
        $where['status'] = 1;
        // 执行查询并计算有效数据的数量。
        $valid = $this->dao->search($where)->count();

        // 将查询条件中的状态设置为无效（0），以统计无效数据的数量。
        $where['status'] = 0;
        // 执行查询并计算无效数据的数量。
        $invalid = $this->dao->search($where)->count();

        // 返回包含有效和无效数据数量的数组。
        return compact('valid', 'invalid');
    }

    /**
     * 创建或编辑商户的表单
     *
     * @param int|null $id 商户的ID，如果为null则是创建新商户，否则是编辑现有商户
     * @param array $formData 商户表单的数据，用于预填充表单字段
     * @return \EasyWeChat\MiniProgram\Element\Form|Form
     */
    public function form(?int $id = null, array $formData = [])
    {
        // 根据$id的值决定创建还是编辑商户，构建相应的路由URL
        $form = Elm::createForm(is_null($id) ? Route::buildUrl('systemMerchantCreate')->build() : Route::buildUrl('systemMerchantUpdate', ['id' => $id])->build());
        $is_margin = 0;
        // 判断是否为保证金商户，是则设置is_margin为1
        if ($formData && $formData['is_margin'] == 10) $is_margin = 1;
        /** @var MerchantCategoryRepository $make */
        $make = app()->make(MerchantCategoryRepository::class);
        // 获取商户类型的所有选项
        $merchantTypeRepository = app()->make(MerchantTypeRepository::class);
        $options = $merchantTypeRepository->getOptions();
        $margin = $merchantTypeRepository->getMargin();

        // 加载系统配置，用于设置直播间和商品的审核状态
        $config = systemConfig(['broadcast_room_type', 'broadcast_goods_type']);

        // 定义表单的验证规则和字段
        $rule = [
            // 商户名称字段
            Elm::input('mer_name', '商户名称：')->placeholder('请输入商户名称')->required('请输入商户名称'),
            // 商户分类字段，通过仓库获取所有选项
            Elm::select('category_id', '商户分类：')->options(function () use ($make) {
                return $make->allOptions();
            })->placeholder('请选择商户分类')->requiredNum('请选择商户分类'),

            // 商户类型字段，根据是否为保证金商户决定是否禁用
            Elm::select('type_id', '店铺类型：')->disabled($is_margin)->options($options)->requiredNum('请选择店铺类型')->col(12)->control($margin)->placeholder('请选择店铺类型'),


            // 商户账号字段，如果$id不为null，则禁用并设置为必填
            Elm::input('mer_account', '商户账号：')->placeholder('请输入商户账号')->required('请输入商户账号')->disabled(!is_null($id))->required(!is_null($id)),
            // 登录密码字段，同上
            Elm::password('mer_password', '登录密码：')->placeholder('请输入登陆密码')->required('请输入登陆密码')->disabled(!is_null($id))->required(!is_null($id)),
            // 商户姓名字段
            Elm::input('real_name', '商户姓名：')->placeholder('请输入商户姓名'),
            // 商户手机号字段
            Elm::input('mer_phone', '商户手机号：')->col(12)->placeholder('请输入商户手机号')->required('请输入商户手机号'),
            // 手续费字段
            Elm::number('commission_rate', '手续费(%)：')->col(12),
            // 商户关键字字段
            Elm::input('mer_keyword', '商户关键字：')->placeholder('请输入商户关键字')->col(12),
            // 商户地址字段
            Elm::input('mer_address', '商户地址：')->placeholder('请输入商户地址'),
            // 微信分账商户号字段
            Elm::input('sub_mchid', '微信分账商户号：')->placeholder('请输入微信分账商户号'),
            // 备注字段
            Elm::textarea('mark', '备注：')->placeholder('请输入备注'),
            // 排序字段
            Elm::number('sort', '排序：', 0)->precision(0)->max(99999),
            // 开启状态字段，如果$id不为null，则设置为隐藏字段，否则为开关控件
            $id ? Elm::hidden('status', 1) : Elm::switches('status', '是否开启：', 1)->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->className('switch-width-double')->activeText('开启')->col(12),
            // 直播间审核状态字段，根据系统配置决定默认值
            Elm::switches('is_bro_room', '直播间审核：', $config['broadcast_room_type'] == 1 ? 0 : 1)->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->className('switch-width-double')->activeText('开启')->col(12),
            // 产品审核状态字段
            Elm::switches('is_audit', '产品审核：', 1)->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->className('switch-width-double')->activeText('开启')->col(12),
            // 直播间商品审核状态字段，根据系统配置决定默认值
            Elm::switches('is_bro_goods', '直播间商品审核：', $config['broadcast_goods_type'] == 1 ? 0 : 1)->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->className('switch-width-double')->activeText('开启')->col(12),
            // 是否推荐字段
            Elm::switches('is_best', '是否推荐：')->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->className('switch-width-double')->activeText('开启')->col(12),
            // 是否自营字段
            Elm::switches('is_trader', '是否自营：')->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->className('switch-width-double')->activeText('开启')->col(12),
        ];

        // 设置表单的验证规则和数据
        $form->setRule($rule);
        return $form->setTitle(is_null($id) ? '添加商户' : '编辑商户')->formData($formData);
    }

    /**
     * 创建商家信息表单
     * 用于生成编辑商家信息的表单界面，包含店铺简介、服务电话、店铺Banner、店铺头像和是否开启店铺等字段。
     *
     * @param array $formData 表单默认数据，用于预填充表单。
     * @return Form|\think\form\Form
     */
    public function merchantForm(array $formData = [])
    {
        // 创建表单对象，并设置表单提交的URL
        $form = Elm::createForm(Route::buildUrl('merchantUpdate')->build());

        // 定义表单规则，包括各种字段的输入类型、占位符、是否必填等设置
        $rule = [
            // 店铺简介输入框
            Elm::textarea('mer_info', '店铺简介：')->placeholder('请输入店铺简介')->required('请输入店铺简介'),
            // 服务电话输入框
            Elm::input('service_phone', '服务电话：')->placeholder('请输入服务电话')->required('请输入服务电话'),
            // 店铺Banner图片选择框
            Elm::frameImage('mer_banner', '店铺Banner(710*200px)：', '/' . config('admin.merchant_prefix') . '/setting/uploadPicture?field=mer_banner&type=1')->icon('el-icon-camera')->modal(['modal' => false])->width('1000px')->height('600px')->props(['footer' => false]),
            // 店铺头像图片选择框
            Elm::frameImage('mer_avatar', '店铺头像(120*120px)：', '/' . config('admin.merchant_prefix') . '/setting/uploadPicture?field=mer_avatar&type=1')->icon('el-icon-camera')->modal(['modal' => false])->width('1000px')->height('600px')->props(['footer' => false]),
            // 是否开启店铺的开关按钮
            Elm::switches('mer_state', '是否开启：', 1)->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->className('switch-width-double')->activeText('开启')->col(12),
        ];

        // 设置表单的规则
        $form->setRule($rule);

        // 设置表单标题，并传入默认数据
        return $form->setTitle('编辑店铺信息')->formData($formData);
    }

    /**
     * 更新表单数据。
     * 此方法用于根据给定的ID获取表单数据，并进行一些处理，如填充商家ID和隐藏密码，然后返回处理后的表单数据。
     *
     * @param int $id 数据库中记录的ID，用于查找和更新表单数据。
     * @return mixed 返回处理后的表单数据，以便在前端展示或进一步处理。
     */
    public function updateForm($id)
    {
        // 通过ID获取数据库中的数据，并转换为数组格式。
        $data = $this->dao->get($id)->toArray();

        // 通过依赖注入获取MerchantAdminRepository实例，用于后续操作。
        /** @var MerchantAdminRepository $make */
        $make = app()->make(MerchantAdminRepository::class);

        // 通过商家账户ID获取商家ID，并将其添加到数据数组中。
        $data['mer_account'] = $make->merIdByAccount($id);

        // 为了保护密码安全，将密码字段的值设置为星号，隐藏真实密码。
        $data['mer_password'] = '***********';

        // 如果分类ID为0，将其字符串化为空字符串，以便在表单中显示为未选择分类。
        if($data['category_id'] == 0){
            $data['category_id'] = '';
        }

        // 如果类型ID为0，将其字符串化为空字符串，以便在表单中显示为未选择类型。
        if($data['type_id'] == 0){
            $data['type_id'] = '';
        }

        // 调用form方法，传入ID和处理后的数据数组，返回处理后的表单。
        return $this->form($id, $data);
    }

    /**
     * 创建商家
     *
     * 本函数用于处理商家的创建流程，包括验证商家名称、手机号、分类存在性以及账号唯一性，
     * 设置商家的保证金和相关费用，创建商家管理员账号，并在数据库事务中完成所有相关操作。
     * 这确保了操作的一致性和原子性。
     *
     * @param array $data 商家创建的数据，包含商家名称、电话、分类ID、管理员账号等信息。
     * @return object 创建的商家对象。
     * @throws ValidateException 如果验证失败，抛出验证异常。
     */
    public function createMerchant(array $data)
    {
        // 检查商家名称是否已存在
        if ($this->fieldExists('mer_name', $data['mer_name']))
            throw new ValidateException('商户名已存在');

        // 验证手机号格式是否正确
        if ($data['mer_phone'] && isPhone($data['mer_phone']))
            throw new ValidateException('请输入正确的手机号');

        // 获取商家分类仓库和商家管理员仓库实例
        $merchantCategoryRepository = app()->make(MerchantCategoryRepository::class);
        $adminRepository = app()->make(MerchantAdminRepository::class);

        // 检查商家分类是否存在
        if (!$data['category_id'] || !$merchantCategoryRepository->exists($data['category_id']))
            throw new ValidateException('商户分类不存在');

        // 检查管理员账号是否已存在
        if ($adminRepository->fieldExists('account', $data['mer_account']))
            throw new ValidateException('账号已存在');

        /** @var MerchantAdminRepository $make */
        $make = app()->make(MerchantAdminRepository::class);

        // 获取商家类型信息，包括保证金和相关费用
        $margin = app()->make(MerchantTypeRepository::class)->get($data['type_id']);
        $data['is_margin'] = $margin['is_margin'] ?? 0;
        $data['margin'] = $margin['margin'] ?? 0;
        $data['ot_margin'] = $margin['margin'] ?? 0;

        // 处理管理员信息，如果存在则保留
        $admin_info = [];
        if(isset($data['admin_info'])){
            $admin_info = $data['admin_info'] ?: [];
            unset($data['admin_info']);
        }

        // 使用数据库事务来确保所有操作的一致性
        return app()->make(MerchantCoreService::class)->createMerchant($data, function () use ($data, $make,$admin_info) {
            return Db::transaction(function () use ($data, $make,$admin_info) {
            // 从数据数组中提取账号和密码，并删除这些字段
            $account = $data['mer_account'];
            $password = $data['mer_password'];
            unset($data['mer_account'], $data['mer_password']);

            // 创建商家主体
            $merchant = $this->dao->create($data);
            // 创建商家管理员账号
            $make->createMerchantAccount($merchant, $account, $password);
            // 创建默认的配送模板
            app()->make(ShippingTemplateRepository::class)->createDefault($merchant->mer_id);
            // 设置默认的商品复制数量
            app()->make(ProductCopyRepository::class)->defaulCopyNum($merchant->mer_id);
            // 处理关联店铺分组
            $storeGroupIds = $data['store_group_ids'] ?? [];
            unset($data['store_group_ids']);
            if($storeGroupIds){
                $relevance = [];
                foreach ($storeGroupIds as $storeGroupId) {
                    $relevance[] = [
                        'left_id' => $storeGroupId,
                        'right_id' => $merchant->mer_id,
                        'type' => RelevanceRepository::STORE_GROUP,
                    ];
                }
                app()->make(RelevanceRepository::class)->insertAll($relevance);
            }

            // 如果存在管理员信息和操作日志更新信息，记录操作日志
            if (!empty($admin_info)) {
                event('create_operate_log', [
                    'category' => OperateLogRepository::PLATFORM_CREATE_MERCHANT,
                    'data' => [
                        'merchant' => $merchant,
                        'admin_info' => $admin_info,
                    ],
                ]);
            }
            // 返回创建的商家对象
            return $merchant;
            });
        });
    }

    /**
     * 根据条件获取商家列表
     *
     * @param array $where 查询条件
     * @param int $page 分页页码
     * @param int $limit 每页数量
     * @param array $userInfo 用户信息
     * @return array 商家列表和总数
     */
    public function getList($where, $page, $limit, $userInfo)
    {
        // 定义需要查询的字段
        $field = 'care_count,is_trader,type_id,mer_id,mer_banner,mini_banner,mer_name,mark,mer_avatar,product_score,service_score,postage_score,sales,status,is_best,create_time,long,lat,is_margin,care_ficti';

        // 默认查询条件：状态为正常，商家状态为开启，未删除
        $where['status'] = 1;
        $where['mer_state'] = 1;
        $where['is_del'] = 0;

        // 处理位置查询条件
        if (isset($where['location'])) {
            $data = @explode(',', (string)$where['location']);
            if (2 != count(array_filter($data ?: []))) {
                unset($where['location']);
            } else {
                $where['location'] = [
                    'lat' => (float)$data[0],
                    'long' => (float)$data[1],
                ];
            }
        }

        // 如果有关键字，记录用户搜索行为
        if ($where['keyword'] !== '') {
            app()->make(UserVisitRepository::class)->searchMerchant($userInfo ? $userInfo['uid'] : 0, $where['keyword']);
        }

        // 构建查询对象，并处理位置查询
        $query = $this->dao->search($where)->with(['type_name']);
        $count = $query->count();
        $status = systemConfig('mer_location') && isset($where['location']);

        // 分页查询商家列表，并处理距离计算
        $list = $query->page($page, $limit)->setOption('field', [])->field($field)->select()
            ->each(function ($item) use ($status, $where) {
                if ($status && $item['lat'] && $item['long'] && isset($where['location']['lat'], $where['location']['long'])) {
                    $distance = getDistance($where['location']['lat'], $where['location']['long'], $item['lat'], $item['long']);
                    if ($distance < 0.9) {
                        $distance = max(bcmul($distance, 1000, 0), 1).'m';
                        if ($distance == '1m') {$distance = '100m以内';}
                    } else {
                        $distance .= 'km';
                    }
                    $item['distance'] = $distance;
                }
                // 根据配送方式处理推荐图片
                $item['recommend'] = isset($where['delivery_way']) ? $item['CityRecommend'] : $item['AllRecommend'];
                $item['recommend'] = getThumbWaterImage( $item['recommend'], ['image'], 'small');
                $item['care_count'] = (int)$item['care_ficti'] + $item['care_count'];
                return $item;
            });

        // 返回商家总数和列表
        return compact('count', 'list');
    }

    public function getDistance(int $id, array $params) : array
    {
        $merchant = $this->dao->get($id);
        if (!$merchant) {
            throw new ValidateException('商户信息不存在，请检查');
        }
        $merLat = $merchant['lat'];
        $merLong = $merchant['long'];
        if (!$merLat || !$merLong) {
            throw new ValidateException('商户未设置位置信息，无法获取距离');
        }
        $distance = getDistance($params['latitude'], $params['longitude'], $merLat, $merLong);
        if ($distance < 0.9) {
            $distance = max(bcmul($distance, 1000, 0), 1).'m';
            if ($distance == '1m') {$distance = '100m以内';}
        } else {
            $distance.= 'km';
        }
        return ['distance' => $distance];
    }

    /**
     * 检查指定ID的实体是否存在。
     *
     * 本函数通过查询数据访问对象（DAO）来确定给定ID的实体是否存在。
     * 它接受一个整数ID作为参数，并返回一个布尔值，表示该ID对应的实体是否存在。
     *
     * @param int $id 要检查的实体的唯一标识符。
     * @return bool 如果实体存在，则返回true；否则返回false。
     */
    public function merExists(int $id)
    {
        // 通过DAO的get方法查询指定ID的实体，返回查询结果。
        return ($this->dao->get($id));
    }

    /**
     * 获取商家详情信息
     *
     * 本函数用于根据商家ID获取商家的详细信息，并对部分敏感信息进行隐藏。
     * 同时，还会根据用户信息判断用户是否关注了该商家。
     *
     * @param int $id 商家ID
     * @param object $userInfo 用户信息对象，包含用户ID
     * @return object 商家详情对象，包含商家信息和用户关注状态
     */
    public function detail($id, $userInfo)
    {
        // 通过API获取商家信息，并隐藏部分敏感字段
        $merchant = $this->dao->apiGetOne($id);
        if(!$merchant) {
            throw new ValidateException('商户信息不存在，请检查');
        }

        $merchant = $merchant->hidden([
            "real_name", "mer_phone", "reg_admin_id", "sort", "is_del", "is_audit", "is_best", "mer_state", "bank", "bank_number", "bank_name", 'update_time',
            'financial_alipay', 'financial_bank', 'financial_wechat', 'financial_type','mer_take_phone'
        ]);

        // 添加虚拟字段，用于后续处理
        $merchant->append(['mer_type_name', 'isset_certificate', 'services_type']);
        $merchant['care'] = false; // 默认用户未关注商家
        $merchant['type_name'] = $merchant['mer_type_name']; // 将商家类型名称赋值给type_name字段
        $merchant['care_count'] = (int)$merchant['care_ficti'] + (int)$merchant['care_count'];
        // 如果提供了用户信息，则检查用户是否关注了该商家
        if ($userInfo)
            $merchant['care'] = $this->getCareByUser($id, $userInfo->uid);
        $merchant['take_time'] = app()->make(MerchantTakeRepository::class)->get($id) + systemConfig(['tx_map_key']);

        return $merchant; // 返回处理后的商家详情信息
    }

    /**
     * 是否关注店铺
     * @Author:Qinii
     * @Date: 2020/5/30
     * @param int $merId
     * @param int $userId
     * @return bool
     */
    public function getCareByUser(int $merId, int $userId)
    {
        if (app()->make(UserRelationRepository::class)->getWhere(['type' => 10, 'type_id' => $merId, 'uid' => $userId]))
            return true;
        return false;
    }

    /**
     * 根据商家ID和搜索条件获取产品列表
     *
     * 本函数旨在为商家提供一种方式，根据特定的搜索条件和分页信息，从数据库中检索他们的产品列表。
     * 它使用了依赖注入来获取ProductRepository实例，并调用其getApiSearch方法来执行实际的查询。
     *
     * @param int $merId 商家ID，用于限定查询的产品属于哪个商家。
     * @param string $where 搜索条件，用于进一步筛选产品。可以包含各种条件，如产品名称、描述等。
     * @param int $page 当前的页码，用于分页查询产品列表。
     * @param int $limit 每页显示的产品数量，用于控制分页查询的结果集大小。
     * @param array $userInfo 用户信息，可能包含用户的权限信息等，用于实现某些基于用户的搜索功能或权限控制。
     * @return array 返回搜索结果，包括产品信息和分页信息等。
     */
    public function productList($merId, $where, $page, $limit, $userInfo)
    {
        // 通过依赖注入获取ProductRepository实例，并调用其getApiSearch方法查询产品列表
        return app()->make(ProductRepository::class)->getApiSearch($merId, $where, $page, $limit, $userInfo);
    }

    /**
     * 获取分类列表
     *
     * 本函数用于根据给定的ID获取分类列表。它通过依赖注入的方式，使用StoreCategoryRepository类来获取数据，
     * 并以API格式返回列表。此功能主要用于前端展示或进一步的数据处理。
     *
     * @param int $id 分类ID或父分类ID，用于获取特定分类下的子分类列表。
     * @return array 返回API格式的分类列表数据。
     */
    public function categoryList(int $id)
    {
        // 通过应用容器创建StoreCategoryRepository实例，并调用其getApiFormatList方法获取分类列表
        return app()->make(StoreCategoryRepository::class)->getApiFormatList($id, 1);
    }

    /**
     * 生成商家微信二维码
     *
     * 本函数用于生成商家的微信二维码图片的URL。首先，它根据商家ID和当前日期生成一个唯一的文件名，
     * 然后尝试从附件库中查找这个文件名的二维码图片信息。如果图片存在但URL无效（例如，图片已移除），则从数据库中删除该图片信息。
     * 如果找不到图片信息，说明需要生成新的二维码。二维码的URL基于商家ID和系统配置的网站URL构建，
     * 并可能通过微信服务进行永久二维码的生成。生成的二维码图片信息将被保存到附件库中，并返回其URL。
     *
     * @param string $merId 商家ID，用于生成唯一二维码文件名和构建二维码URL
     * @return string 二维码图片的URL
     */
    public function wxQrcode($merId)
    {
        $siteUrl = systemConfig('site_url');
        $name = md5('mwx' . $merId . date('Ymd')) . '.jpg';
        $attachmentRepository = app()->make(AttachmentRepository::class);
        $imageInfo = $attachmentRepository->getWhere(['attachment_name' => $name]);

        if (isset($imageInfo['attachment_src']) && strstr($imageInfo['attachment_src'], 'http') !== false && curl_file_exist($imageInfo['attachment_src']) === false) {
            $imageInfo->delete();
            $imageInfo = null;
        }
        if (!$imageInfo) {
            $codeUrl = rtrim($siteUrl, '/') . '/pages/store/home/index?id=' . $merId; //二维码链接
            if (systemConfig('open_wechat_share')) {
                $qrcode = OfficialAccount::foreverQrCode('_scan_url_mer_' . $merId);
                $codeUrl = OfficialAccount::qrCodeUrl($qrcode['ticket']);
            }
            $imageInfo = app()->make(QrcodeService::class)->getQRCodePath($codeUrl, $name);
            if (is_string($imageInfo)) throw new ValidateException('二维码生成失败');

            $imageInfo['dir'] = tidy_url($imageInfo['dir'], null, $siteUrl);

            $attachmentRepository->create(systemConfig('upload_type') ?: 1, -2, $merId, [
                'attachment_category_id' => 0,
                'attachment_name' => $imageInfo['name'],
                'attachment_src' => $imageInfo['dir']
            ]);
            $urlCode = $imageInfo['dir'];
        } else $urlCode = $imageInfo['attachment_src'];
        return $urlCode;
    }

    /**
     * 生成商家小程序二维码
     *
     * 本函数用于生成商家小程序的二维码图片文件名，并通过调用QrcodeService类的方法获取二维码的URL。
     * 二维码的名称基于当天的日期、商家ID和一个固定的字符串生成，以确保每天每个商家的二维码都是唯一的。
     * 生成的二维码指向小程序的特定页面，并携带商家ID作为参数，以便在小程序中识别商家。
     *
     * @param string $merId 商家ID，用于生成唯一二维码和作为小程序页面的参数
     * @return string 生成的二维码的URL
     */
    public function routineQrcode($merId)
    {
        // 生成二维码文件名，基于当天日期、商家ID和一个固定的字符串，确保唯一性，并指定文件类型为jpg
        $name = md5('smrt' . $merId . date('Ymd')) . '.jpg';

        // 调用QrcodeService类的方法获取二维码的URL，并对返回的URL进行整理，确保没有多余的斜杠
        return tidy_url(app()->make(QrcodeService::class)->getRoutineQrcodePath($name, 'pages/store/home/index', 'id=' . $merId,'routine/store'), 0);
    }

    /**
     * 创建复制商品次数的表单
     *
     * 该方法用于生成一个表单，用于修改商品的复制次数。表单中包含复制次数的展示、修改类型的选项以及修改数量的输入框。
     *
     * @param int $id 商品ID，用于获取当前商品的复制次数。
     * @return \think\form\Form 返回创建的表单对象，方便外部调用和进一步操作。
     */
    public function copyForm(int $id)
    {
        // 创建表单对象，并设置表单提交的URL
        $form = Elm::createForm(Route::buildUrl('systemMerchantChangeCopy', ['id' => $id])->build());

        // 设置表单的验证规则
        $form->setRule([
            // 显示复制次数，为只读状态，不可修改
            Elm::input('copy_num', '复制次数：', $this->dao->getCopyNum($id))->disabled(true)->readonly(true),
            // 提供修改类型的选项，包括增加和减少两种类型
            Elm::radio('type', '修改类型：', 1)
                ->setOptions([
                    ['value' => 1, 'label' => '增加'],
                    ['value' => 2, 'label' => '减少'],
                ]),
            // 输入修改数量，必须输入数字，并且是必填项
            Elm::number('num', '修改数量：', 0)->required('请输入修改数量'),
        ]);

        // 设置表单的标题
        return $form->setTitle('修改复制商品次数');
    }

    /**
     * 创建删除商户的表单
     *
     * 本函数用于生成一个包含删除选项的表单，以供用户确认删除操作。表单中包括两种删除方式的选择，
     * 以及对删除操作不可恢复的提示。商户的删除可以通过两种方式执行：仅删除数据或删除数据和资源。
     *
     * @param int $id 商户的唯一标识ID，用于构建删除操作的URL。
     * @return object 返回生成的表单对象，该对象包含删除方式的选择和相关提示信息。
     */
    public function deleteForm($id)
    {
        // 构建删除商户的表单，表单提交的URL由系统路由生成
        $form = Elm::createForm(Route::buildUrl('systemMerchantDelete', ['id' => $id])->build());

        // 设置表单的验证规则，包括删除方式的选择
        $form->setRule([
            // 创建一个单选按钮组，用于选择删除方式
            Elm::radio('type', '删除方式：', 0)->options([
                ['label' => '仅删除数据', 'value' => 0],
                ['label' => '删除数据和资源', 'value' => 1]
            ])->appendRule('suffix', [
                // 在删除方式选项后添加提示信息，说明删除操作的不可逆性
                'type' => 'div',
                'style' => ['color' => '#999999'],
                'domProps' => [
                    'innerHTML' =>'删除后将不可恢复，请谨慎操作！',
                ]
            ]),
        ]);

        // 设置表单的标题为“删除商户”
        return $form->setTitle( '删除商户');
    }

    /**
     * 删除商户
     *
     * 本函数通过开启数据库事务，确保一系列删除操作的原子性。
     * 它首先将商户的删除标记设置为1，表示逻辑删除。
     * 接着，它从管理员列表中删除该商户的管理员账号。
     * 最后，它将清理商户相关的存储任务加入到队列中，以异步方式执行。
     *
     * @param int $id 商户ID
     */
    public function delete($id)
    {
        // 开启数据库事务处理
        Db::transaction(function () use ($id) {
            // 将商户的删除状态设置为已删除（逻辑删除）
            $this->dao->update($id, ['is_del' => 1]);
            // 删除商户管理员账号
            app()->make(MerchantAdminRepository::class)->deleteMer($id);
            // 将清理商户存储的任务推入队列，异步执行
            Queue::push(ClearMerchantStoreJob::class, ['mer_id' => $id]);
        });
    }


    /**
     * 删除商户的图片及原件
     * @param $id
     * @author Qinii
     * @day 2023/5/8
     */
    public function clearAttachment($id)
    {
        $attachment_category_id = app()->make(AttachmentCategoryRepository::class)->getSearch([])
            ->where('mer_id',$id)
            ->column('attachment_category_id');
        $AttachmentRepository = app()->make(AttachmentRepository::class);
        $attachment_id = $AttachmentRepository->getSearch([])
            ->whereIn('attachment_category_id',$attachment_category_id)
            ->column('attachment_id');
        if ($attachment_id) $AttachmentRepository->batchDelete($attachment_id,$id);
    }


    /**
     * 清理删除商户但没有删除的商品数据
     * @author Qinii
     * @day 5/15/21
     */
    public function clearRedundancy()
    {
        $rets = (int)$this->dao->search(['is_del' => 1])->column('mer_id');
        if (empty($rets)) return;
        $productRepository = app()->make(ProductRepository::class);
        $storeCouponRepository = app()->make(StoreCouponRepository::class);
        $storeCouponUserRepository = app()->make(StoreCouponUserRepository::class);
        foreach ($rets as $ret) {
            try {
                $productRepository->clearMerchantProduct($ret);
                $storeCouponRepository->getSearch([])->where('mer_id', $ret)->update(['is_del' => 1, 'status' => 0]);
                $storeCouponUserRepository->getSearch([])->where('mer_id', $ret)->update(['is_fail' => 1, 'status' => 2]);
            } catch (\Exception $exception) {
                throw new ValidateException($exception->getMessage());
            }
        }
    }

    /**
     * 添加冻结资金
     *
     * 该方法用于在特定条件下为商户添加冻结资金。冻结资金是指在交易过程中被暂时锁定的资金，
     * 用于确保商户在交易完成前不会错误地使用这些资金。
     *
     * @param int $merId 商户ID，用于标识哪个商户的资金被冻结
     * @param string $orderType 订单类型，用于区分不同类型的订单，可能的值包括但不限于"order"表示订单冻结
     * @param int $orderId 订单ID，与订单类型配合使用，用于唯一标识订单
     * @param float $money 冻结的资金金额，如果金额小于等于0，则不执行任何操作
     */
    public function addLockMoney(int $merId, string $orderType, int $orderId, float $money)
    {
        // 检查资金金额是否有效，如果小于等于0，则直接返回，不进行后续操作
        if ($money <= 0) return;
        // 检查系统配置，如果启用了商户资金冻结时间，则通过UserBillRepository增加商户的冻结资金记录
        if (systemConfig('mer_lock_time')) {
            // 使用依赖注入的方式获取UserBillRepository实例，并增加商户的冻结资金
            app()->make(UserBillRepository::class)->incBill($merId, 'mer_lock_money', $orderType, [
                'link_id' => ($orderType === 'order' ? 1 : 2) . $orderId, // 根据订单类型组合链接ID
                'mer_id' => $merId, // 商户ID
                'status' => 0, // 资金变动状态，0表示冻结
                'title' => '商户冻结余额', // 资金变动标题
                'number' => $money, // 资金变动金额
                'mark' => '商户冻结余额', // 资金变动备注
                'balance' => 0 // 新的余额，此处设置为0，因为是冻结资金
            ]);
        } else {
            // 如果系统配置未启用商户资金冻结时间，则直接调用dao层方法增加商户的资金
            $this->addMoney($merId, $money);
        }
    }

    /**
     * 根据商家ID和类型检查商家的特定数量限制
     *
     * 本函数用于根据商家的ID和指定的类型，检查该商家在特定方面的数量限制。
     * 这些类型包括复制产品和导出数据等，具体限制数量在商家的配置中设定。
     *
     * @param int $merId 商家的ID，用于查询商家的具体配置
     * @param string $type 类型标识，用于确定检查哪方面的数量限制，如'copy'表示复制产品，'dump'表示导出数据
     * @return int 返回对应类型的数量限制，如果未设置或不适用，则返回0
     */
    public function checkCrmebNum(int $merId, string $type)
    {
        // 通过商家ID查询商家信息
        $merchant = $this->dao->get($merId);
        $number = 0; // 初始化数量为0，用于在未设置或不适用的情况下返回

        // 根据类型进行不同的数量检查
        switch ($type) {
            case 'copy':
                // 如果系统配置允许复制产品，并且商家设置了复制产品数量，则返回商家的复制产品数量
                if (systemConfig('copy_product_status') && $merchant['copy_product_num'])
                    $number = $merchant['copy_product_num'];
                break;
            case 'dump':
                // 如果系统配置不允许导出数据，或者商家未设置导出数据数量，则返回商家的导出数据数量
                if (!systemConfig('crmeb_serve_dump') || !$merchant['export_dump_num'])
                    $number = $merchant['export_dump_num'];
                break;
        }

        return $number; // 返回检查得到的数量限制
    }

    /**
     * 解锁商户冻结资金
     * 当商户的冻结资金满足特定条件时，本函数用于处理资金的解锁操作。它首先检查是否有相应的冻结资金记录，
     * 如果没有，则直接解冻资金；如果有，则通过退款方式处理资金解锁。
     *
     * @param int $merId 商户ID，用于标识商户
     * @param string $orderType 订单类型，用于区分不同类型的订单
     * @param int $orderId 订单ID，与订单类型一起用于查询冻结资金记录
     * @param float $money 解锁的资金金额，如果小于等于0，则不执行任何操作
     */
    public function subLockMoney(int $merId, string $orderType, int $orderId, float $money = 0)
    {
        Log::info('订单退款解冻商户冻结资金1：'.var_export([$merId,$orderType, 'order',
                $orderId, $money],true));
        // 通过依赖注入获取用户账单仓库实例
        $make = app()->make(UserBillRepository::class);
        $link_id = ($orderType === 'order' ? 1 : 2) . $orderId;
        // 查询是否有对应的冻结资金记录
        $bill = $make->search([
            'category' => 'mer_lock_money',
            'type' => $orderType,
            'mer_id' => $merId,
            'link_id' => $link_id,
            'status' => 0
        ])->find();
        // 如果没有对应的冻结资金记录，则直接解冻资金
        if (!$bill) {
            // 检查资金金额是否有效，如果小于等于0，则直接返回，不执行任何操作
            if ($money <= 0) return;
            $merComputedMoney = $make->search([
                'category' => 'mer_computed_money',
                'type' => $orderType,
                'mer_id' => $merId,
                'link_id' => $orderId,
                'status' => 0
            ])->find();
            // 是否有待解冻的资金记录
            if($merComputedMoney) {
                // 更新待解冻的资金记录为已失效，防止重复计算
                $merComputedMoney->status = -1;
                $merComputedMoney->save();
                // 存在待解冻的资金记录，通过退款方式处理资金解锁
                $make->decBill($merId, 'mer_refund_money', $orderType, [
                    'link_id' => $link_id,
                    'mer_id' => $merId,
                    'status' => 1,
                    'title' => '商户冻结余额退款',
                    'number' => $money,
                    'mark' => '商户冻结余额退款',
                    'balance' => 0
                ]);
            } else {
                // 不存在待解冻的资金记录，直接减余额
                $this->subMoney($merId, $money);
            }
        } else {
            // 如果有对应的冻结资金记录，则通过退款方式处理资金解锁
            $make->decBill($merId, 'mer_refund_money', $orderType, [
                'link_id' => $link_id,
                'mer_id' => $merId,
                'status' => 1,
                'title' => '商户冻结余额退款',
                'number' => $money,
                'mark' => '商户冻结余额退款',
                'balance' => 0
            ]);
        }
    }

    /**
     * 计算并处理商户的锁定资金
     *
     * 本函数用于在订单完成时，计算商户的锁定资金，并进行相应的资金处理。
     * 这包括但不限于：解锁预售订单锁定的资金、更新订单锁定资金的状态、增加商户的待解冻余额。
     *
     * @param StoreOrder $order 商户的订单对象，用于获取订单相关信息。
     */
    public function computedLockMoney(StoreOrder $order)
    {
        // 使用数据库事务确保操作的原子性
        Db::transaction(function () use ($order) {
            $money = 0;
            // 创建用户账单仓库实例
            $make = app()->make(UserBillRepository::class);

            // 查询订单锁定的资金信息
            $bill = $make->search(['category' => 'mer_lock_money', 'type' => 'order', 'link_id' => '1' . $order->order_id, 'status' => 0])->find();
            if ($bill) {
                // 计算订单锁定资金的可解冻金额
                $money = bcsub($bill->number, $make->refundMerchantMoney($bill->link_id, $bill->type, $bill->mer_id), 2);
                // 如果存在预售订单，处理预售订单的锁定资金
                if ($order->presellOrder) {
                    // 查询预售订单锁定的资金信息
                    $presellBill = $make->search(['category' => 'mer_lock_money', 'type' => 'presell', 'link_id' => '2' . $order->presellOrder->presell_order_id, 'status' => 0])->find();
                    if ($presellBill) {
                        // 计算预售订单锁定资金的可解冻金额，并累加到总金额
                        $money = bcadd($money, bcsub($presellBill->number, $make->refundMerchantMoney($presellBill->link_id, $presellBill->type, $presellBill->mer_id), 2), 2);
                        // 更新预售订单锁定资金的状态
                        $presellBill->status = 1;
                        $presellBill->save();
                    }
                }
                // 更新订单锁定资金的状态
                $bill->status = -1;
                $bill->save();
            }

            // 如果存在可解冻的金额，增加商户的待解冻余额
            if ($money > 0) {
                // 创建用户账单仓库实例（如果之前未创建）
                $make = app()->make(UserBillRepository::class);
                // 增加商户的待解冻余额
                $make->incBill($order->uid, 'mer_computed_money', 'order', [
                    'link_id' => $order->order_id,
                    'mer_id' => $order->mer_id,
                    'status' => 0,
                    'title' => '商户待解冻余额',
                    'number' => $money,
                    'mark' => '交易完成,商户待解冻余额' . floatval($money) . '元',
                    'balance' => 0
                ]);
            }
        });
    }

    /**
     * 检查商家或类型保证金
     *
     * 本函数用于根据商家ID或类型ID来检查相应的保证金设置。
     * 如果商家有设置保证金，将返回该商家的保证金信息。
     * 如果商家未设置保证金，但其类型设置了保证金，将返回该类型的保证金信息。
     * 如果两者均未设置保证金，则返回默认的保证金信息。
     *
     * @param int $merId 商家ID
     * @param int $typeId 类型ID
     * @return array 包含保证金相关信息的数组，包括是否启用保证金(is_margin)、保证金金额(margin)、原保证金金额(ot_margin)
     */
    public function checkMargin($merId, $typeId)
    {
        // 通过商家ID获取商家信息
        $merchant = $this->dao->get($merId);

        // 默认设置未启用保证金和保证金金额为0
        $is_margin = 0;
        $margin = 0;

        // 如果商家已设置保证金
        if ($merchant['is_margin'] == 10) {
            // 获取并设置商家的保证金金额和其他保证金金额
            $margin = $merchant['margin'];
            $is_margin = $merchant['is_margin'];
            $ot_margin = $merchant['ot_margin'];
        } else {
            // 商家未设置保证金时，尝试根据类型ID获取类型保证金信息
            $marginData = app()->make(MerchantTypeRepository::class)->get($typeId);

            // 如果类型设置了保证金
            if ($marginData) {
                // 获取并设置类型的保证金相关信息
                $is_margin = $marginData['is_margin'];
                $margin = $marginData['margin'];
                $ot_margin = $marginData['margin'];
            }
        }

        // 返回包含保证金相关信息的数组
        return compact('is_margin', 'margin','ot_margin');
    }

    /**
     * 设置商户保证金扣除表单
     * 该方法用于生成一个表单，用于扣除特定商户的保证金。表单中包含商户的基本信息和保证金扣除金额与原因的输入字段。
     *
     * @param int $id 商户ID，用于查询商户信息。
     * @return \think\form\Form 生成的保证金扣除表单对象。
     * @throws ValidateException 如果商户没有保证金可扣，则抛出此异常。
     */
    public function setMarginForm(int $id)
    {
        // 根据商户ID查询商户信息
        $merchant = $this->dao->get($id);
        // 检查商户是否有保证金可扣，如果没有则抛出异常
        if ($merchant->is_margin !== 10) {
            throw new ValidateException('商户无保证金可扣');
        }
        // 创建表单，并设置表单的提交URL
        $form = Elm::createForm(Route::buildUrl('systemMarginSet')->build());
        // 设置表单的验证规则，包括显示商户信息和输入字段
        $form->setRule([
            // 显示商户名称
            [
                'type' => 'span',
                'title' => '商户名称：',
                'native' => false,
                'children' => [(string) $merchant->mer_name]
            ],
            // 显示商户ID
            [
                'type' => 'span',
                'title' => '商户ID：',
                'native' => false,
                'children' => [(string) $merchant->mer_id]
            ],
            // 显示商户保证金额度
            [
                'type' => 'span',
                'title' => '商户保证金额度：',
                'native' => false,
                'children' => [(string) $merchant->ot_margin]
            ],
            // 显示商户剩余保证金
            [
                'type' => 'span',
                'title' => '商户剩余保证金：',
                'native' => false,
                'children' => [(string) $merchant->margin]
            ],
            // 隐藏字段，存储商户ID
            Elm::hidden('mer_id',  $merchant->mer_id),
            // 输入字段，用于输入保证金扣除金额
            Elm::number('number', '保证金扣除金额：', 0)->max($merchant->margin)->precision(2)->required('请输入保证金扣除金额'),
            // 输入字段，用于输入保证金扣除原因
            Elm::text('mark', '保证金扣除原因：')->placeholder('请输入保证金扣除原因')->required('请输入保证金扣除原因'),
        ]);
        // 设置表单标题
        return $form->setTitle('扣除保证金');
    }

    /**
     * 设置商户保证金
     * 该方法用于处理商户保证金的扣除操作。它首先验证商户是否已支付保证金，然后确保扣除金额不小于0，
     * 并检查是否有足够的保证金可供扣除。如果所有验证都通过，则更新商户的保证金余额，并记录操作。
     *
     * @param array $data 包含商户ID、要扣除的保证金数额、操作标记等信息的数据数组
     * @throws ValidateException 如果商户未支付保证金、扣除金额小于0或保证金不足，则抛出验证异常
     */
    public function setMargin($data)
    {
        // 根据商户ID获取商户信息
        $merechant = $this->dao->get($data['mer_id']);

        // 检查商户是否已支付保证金，如果不是则抛出异常
        if ($merechant->is_margin !== 10) {
            throw new ValidateException('商户未支付保证金或已申请退款');
        }

        // 确保要扣除的保证金数额不小于0，小于0则抛出异常
        if ($data['number'] < 0) {
            throw new ValidateException('扣除保证金额不能小于0');
        }

        // 检查商户现有的保证金是否足够扣除，不足则抛出异常
        if (bccomp($merechant->margin, $data['number'], 2) == -1) {
            throw new ValidateException('扣除保证金额不足');
        }

        // 计算扣除保证金后的余额
        $data['balance'] = bcsub($merechant->margin, $data['number'], 2);

        // 为操作标记添加操作者信息
        $data['mark'] =  $data['mark'].'【 操作者：'. request()->adminId().'/'.request()->adminInfo()->real_name .'】';

        // 实例化用户账单仓库
        $userBillRepository = app()->make(UserBillRepository::class);

        // 使用事务处理来确保数据库操作的一致性
        app()->make(MerchantCoreService::class)->writeMerchant((int)$merechant->mer_id, ['margin', 'margin_remind_time', 'status'], function () use ($merechant, $data,$userBillRepository) {
            Db::transaction(function () use ($merechant, $data,$userBillRepository) {
            // 更新商户的保证金余额
            $merechant->margin = $data['balance'];

            // 根据系统配置，更新商户的保证金提醒时间或状态
            if (systemConfig('margin_remind_switch') == 1) {
                $day = systemConfig('margin_remind_day') ?: 0;
                if($day) {
                    $time = strtotime(date('Y-m-d 23:59:59',strtotime("+ $day day",time())));
                    $merechant->margin_remind_time = $time;
                } else {
                    $merechant->status = 0;
                }
            }

            // 保存商户信息
            $merechant->save();

            // 记录保证金操作的账单
            $userBillRepository->bill(0, 'mer_margin', $data['type'], 0, $data);
            });
        });
    }

    /**
     * 修改配送余额
     *
     * 本函数用于减少指定商户的配送余额。在尝试减少之前，它会检查商户的当前配送余额是否足够，
     * 如果不足，则抛出一个异常，提示用户余额不足需要充值。
     * 使用数据库事务来确保操作的原子性，即要么完全执行，要么完全不执行，以维护数据的一致性。
     *
     * @param string $merId 商户ID
     * @param float $number 需要减少的配送余额金额
     * @throws ValidateException 如果商户的配送余额不足，则抛出异常
     */
    public function changeDeliveryBalance($merId,$number)
    {
        // 根据商户ID获取商户对象
        $merechant = $this->dao->get($merId);

        // 比较商户当前配送余额和需要减少的金额，如果小于，则抛出余额不足异常
        if (bccomp($merechant->delivery_balance, $number, 2) == -1) {
            throw new ValidateException('余额不足，请先充值（配送费用：'.$number.'元）');
        }

        // 使用数据库事务来处理配送余额的减少操作
        app()->make(MerchantCoreService::class)->writeMerchant((int)$merechant->mer_id, ['delivery_balance'], function () use ($merechant, $number) {
            Db::transaction(function () use ($merechant, $number) {
            // 减少商户的配送余额
            $merechant->delivery_balance = bcsub($merechant->delivery_balance, $number, 2);
            // 保存更新后的商户信息
            $merechant->save();
            });
        });
    }

    /**
     * 生成本地保证金表单
     * 该方法用于构建一个包含商户保证金相关信息的表单，用于线下缴纳保证金的操作。
     *
     * @param int $id 商户ID，用于查询商户保证金信息。
     * @return \think\form\Form 生成的表单对象，包含商户信息和保证金相关信息。
     *
     * @throws ValidateException 如果商户没有待缴保证金，则抛出验证异常。
     */
    public function localMarginForm(int $id)
    {
        // 根据商户ID查询商户信息
        $merchant = $this->dao->get($id);

        // 检查商户的保证金状态，如果不在待缴状态（1）或已缴纳状态（10），则抛出异常
        if (!in_array($merchant->is_margin,[1,10])) throw new ValidateException('商户无待缴保证金');

        // 根据商户的保证金状态，计算待缴保证金金额
        if ($merchant->is_margin == 10)  {
            // 如果商户已缴纳保证金，计算待缴金额为原保证金金额减去已缴纳金额
            $number = bcsub($merchant->ot_margin,$merchant->margin,2);
            $_number = $merchant->margin;
        } else {
            // 如果商户未缴纳保证金，待缴金额即为保证金金额，已缴纳金额为0
            $number = $merchant->margin;
            $_number = 0;
        }

        // 创建表单对象，并设置表单提交的URL
        $form = Elm::createForm(Route::buildUrl('systemMarginLocalSet',['id' => $id])->build());

        // 添加表单字段，展示商户的基本信息和保证金情况
        $form->setRule([
            [
                'type' => 'span',
                'title' => '商户名称：',
                'native' => false,
                'children' => [(string) $merchant->mer_name]
            ],
            [
                'type' => 'span',
                'title' => '商户ID：',
                'native' => false,
                'children' => [(string) $merchant->mer_id]
            ],
            [
                'type' => 'span',
                'title' => '商户保证金额度：',
                'native' => false,
                'children' => [(string) $merchant->ot_margin]
            ],
            [
                'type' => 'span',
                'title' => '商户剩余保证金：',
                'native' => false,
                'children' => [(string) $_number]
            ],
            [
                'type' => 'span',
                'title' => '待缴保证金金额：',
                'native' => false,
                'children' => [(string) $number]
            ],
            Elm::hidden('number', $number),
            Elm::textarea('mark', '备注：', $merchant->mark),
            Elm::radio('status', '状态：', 0)->options([
                    ['value' => 0, 'label' => '未缴纳'],
                    ['value' => 1, 'label' => '已缴纳']]
            )
        ]);

        // 设置表单标题，并返回表单对象
        return $form->setTitle('线下缴纳保证金');
    }

    /**
     * 设置本地商户的保证金
     * 该方法主要用于处理商户保证金的设置和缴纳操作。当商户的状态发生变化或需要缴纳保证金时，
     * 通过此方法进行相应的数据库操作和业务逻辑处理。
     *
     * @param int $id 商户ID
     * @param array $data 商户保证金相关数据，包括状态、标记和缴纳金额等
     * @throws ValidateException 如果商户不存在或数据验证失败，则抛出验证异常
     */
    public function localMarginSet($id, $data)
    {
        $merchant = $this->dao->get($id);
        if (!$merchant) throw new ValidateException('商户不存在');

        if (!$data['status']) {
            $merchant->mark = $data['mark'];
            $merchant->save();
        } else {
            if (!in_array($merchant->is_margin,[1,10])) throw new ValidateException('商户无待缴保证金');
            if ($data['number'] < 0) throw new ValidateException('缴纳保证金额有误：'.$data['number']);
            $storeOrderRepository =  app()->make(StoreOrderRepository::class);
            $userBillRepository = app()->make(UserBillRepository::class);
            $serveOrderRepository = app()->make(ServeOrderRepository::class);
            $order_sn = $storeOrderRepository->getNewOrderId(StoreOrderRepository::TYPE_SN_SERVER_ORDER);

            $balance = $merchant->is_margin == 1 ? $data['number'] : bcadd($data['number'],$merchant->margin,2);

            $serveOrder = [
                'meal_id' => 0,
                'pay_type'=> ServeOrderRepository::PAY_TYPE_SYS,
                'order_sn'=> $order_sn,
                'pay_price'=>$data['number'],
                'order_info'=> json_encode(['type_id' => 0, 'is_margin' => 10, 'margin' => $data['number'], 'ot_margin' => $balance,]),
                'type'   => ServeOrderRepository::TYPE_MARGIN,
                'status' => 1,
                'mer_id' => $id,
                'pay_time' => date('Y-m-d H:i:s',time())
            ];
            $bill = [
                'title' => '线下补缴保证金',
                'mer_id' => $merchant['mer_id'],
                'number' => $data['number'],
                'mark'=> '操作者：'.request()->adminId().'/'.request()->adminInfo()->real_name,
                'balance'=> $balance,];

            // 商户操作记录
            event('create_operate_log', [
                'category' => OperateLogRepository::PLATFORM_EDIT_MERCHANT_AUDIT_MARGIN,
                'data' => [
                    'merchant' => $merchant,
                    'admin_info' => request()->adminInfo(),
                    'update_infos' => ['status' => 10, 'action' => '线下补缴保证金', 'number' => $data['number']]
                ],
            ]);

            app()->make(MerchantCoreService::class)->writeMerchant((int)$merchant->mer_id, ['margin', 'margin_remind_time', 'is_margin'], function () use ($merchant, $data,$userBillRepository,$bill,$balance,$serveOrder,$serveOrderRepository) {
                Db::transaction(function () use ($merchant, $data,$userBillRepository,$bill,$balance,$serveOrder,$serveOrderRepository) {
                $merchant->margin = $balance;
                $merchant->margin_remind_time = null;
                $merchant->is_margin = 10;
                $merchant->mark = $data['mark'];
                $merchant->save();
                $serveOrderData = $serveOrderRepository->create($serveOrder);
                $bill['link_id'] = $serveOrderData->order_id;
                $userBillRepository->bill(0, 'mer_margin', 'local_margin', 1, $bill);
                });
            });
        }
    }

    /**
     * 修改商家状态
     * 本函数用于根据设定的保证金提醒开关和天数，来检查并调整商家的保证金状态。
     * 如果系统配置的保证金提醒开关未开启，则直接返回true。
     * 否则，将查找保证金低于设定值的商家，并根据提醒时间进行状态调整，如果商家未设置提醒时间或提醒时间已过，则将商家状态设为0。
     */
    public function changeMerchantStatus()
    {
        // 检查系统配置的保证金提醒开关，如果未开启，则不执行任何操作直接返回true
        if (systemConfig('margin_remind_switch') !== '1') return true;

        // 获取系统配置的保证金提醒天数，如果未配置则默认为0
        $day = systemConfig('margin_remind_day') ?: 0;

        // 查询保证金低于10的商家，并且其上次保证金提醒时间在当前时间之后的记录
        $data = $this->dao->search(['margin' => 10])->whereRaw('ot_margin > margin')->select();

        // 遍历查询结果，对每个商家进行状态检查和调整
        foreach ($data as $datum) {
            // 如果商家未设置过保证金提醒时间
            if (is_null($datum->margin_remind_time)) {
                // 如果设置了提醒天数，则计算提醒时间，并设置商家的margin_remind_time
                if($day) {
                    $time = strtotime(date('Y-m-d 23:59:59',strtotime("+ $day day",time())));
                    $this->margin_remind_time = $time;
                } else {
                    // 如果未设置提醒天数，则直接将商家状态设为0
                    $this->status = 0;
                }
            } else {
                // 如果商家已设置过保证金提醒时间，并且提醒时间已过，则将商家状态设为0，并将该商家状态调整的任务加入队列
                if ($datum->margin_remind_time <= time()) {
                    $datum->status = 0;
                    Queue::push(ChangeMerchantStatusJob::class,$datum->mer_id);
                }
            }
            // 保存商家状态的更新
            app()->make(MerchantCoreService::class)->writeMerchant((int)$datum->mer_id, ['margin_remind_time', 'status'], function () use ($datum) {
                $datum->save();
            }, 'merchant_margin_status_auto');
        }
    }

    /**
     * 获取已支付到保证金列表
     *
     * 本函数用于根据指定条件获取已支付到保证金的商户列表。它支持分页和条件查询，返回符合条件的商户数量和列表。
     * 主要用于后台管理界面，展示已支付保证金的商户相关信息。
     *
     * @param array $where 查询条件，用于筛选商户。
     * @param int $page 当前页码，用于分页查询。
     * @param int $limit 每页数量，用于分页查询。
     * @return array 返回包含商户数量和列表的数组。
     */
    public function getPaidToMarginLst($where, $page, $limit)
    {
        // 根据查询条件进行查询
        $query = $this->dao->search($where);

        // 统计符合条件的商户总数
        $count = $query->count($this->dao->getPk());

        // 分页查询并指定查询字段，同时加载关联数据：merchantType和marginOrder
        $list = $query->page($page, $limit)->setOption('field', [])
            ->with(['merchantType','marginOrder'])
            ->field('sort,mer_id,mer_name,real_name,mer_phone,mer_address,mark,status,create_time,is_best,is_trader,type_id,category_id,copy_product_num,export_dump_num,is_margin,margin,ot_margin,mer_avatar,margin_remind_time')->select();

        // 返回商户总数和列表信息
        return compact('count', 'list');
    }

    /**
     * 平台后台获取商户信息
     * @param $id
     * @author Qinii
     * @day 2023/7/1
     */
    public function adminDetail($id)
    {
        $data = $this->dao->getWhere(['mer_id' => $id],'*',['merchantType','merchantCategory','merchantRegion','merchantBusiness'])
            ->append(['store_group_ids'])
            ->toArray();
        $make = app()->make(MerchantAdminRepository::class);
        $data['mer_account'] = $make->merIdByAccount($id);
        $data['mer_password'] = '***********';

        if($data['category_id'] == 0){
            $data['category_id'] = '';
        }
        if($data['type_id'] == 0){
            $data['type_id'] = '';
        }
        $data['mer_certificate'] = merchantConfig($id, 'mer_certificate');
        return $data;
    }

    /**
     * 根据关键字搜索商家信息。
     *
     * 本函数用于查询商家数据库，根据提供的关键字进行搜索。如果未提供关键字，则返回最新的30条商家信息。
     * 主要用于前端展示商家列表或搜索结果。
     *
     * @param string $keyword 搜索关键字，用于匹配商家名称。
     * @param int $status 商家状态，默认为1（有效状态）。本参数目前未在函数内使用，但预留用于未来可能的状态筛选。
     * @return array 返回匹配的商家信息数组，包括商家ID和名称。
     */
    public function mer_select($keyword)
    {
        // 初始化查询
        $query = $this->dao->search([])->where('is_del',0);

        // 如果提供了关键字，则进行模糊搜索
        if ($keyword) {
            $query->whereLike('mer_name',"%{$keyword}%");
        } else {
            // 如果没有提供关键字，限制返回结果的数量为30
            $query->limit(30);
        }

        // 执行查询并返回结果
        $data = $query->order('mer_id DESC')->field('mer_id,mer_name')->select();
        return $data;
    }

    public function careFictiForm($id)
    {
        $formData = $this->dao->get($id)->toArray();
        // 创建表单对象，并设置表单提交的URL
        $form = Elm::createForm(Route::buildUrl('systemMerchantCareFicti', ['id' => $id])->build());
        // 设置表单的验证规则
        $form->setRule([
            // 显示复制次数，为只读状态，不可修改
            Elm::input('care_ficti', '基础关注数：', $formData['care_ficti'])->disabled(true)->readonly(true),
            // 提供修改类型的选项，包括增加和减少两种类型
            Elm::radio('type', '修改类型：', 1)
                ->setOptions([['value' => 1, 'label' => '增加'], ['value' => 2, 'label' => '减少'],]),
            // 输入修改数量，必须输入数字，并且是必填项
            Elm::number('num', '关注人数：', 0)->min(0)->required('请输入关注人数'),
        ]);

        // 设置表单的标题
        return $form->setTitle('修改基础关注数');
    }

    public function careFicti(int $id, array $data)
    {
        $res = $this->dao->get($id);
        $num = ($data['type'] == 1 ? '+' : '-').$data['num'];
        $res->care_ficti = $res->care_ficti + $num;
        $res->save();
    }

    public function getRegion($ids)
    {
        return $this->dao->getSearch([])->whereIn('region_id', $ids)->column('mer_id');
    }

    public function getSubjectRate()
    {
        $config = systemConfig(['open_wx_sub_mch','wechat_service_tool']);
        if (!$config['open_wx_sub_mch'] || !$config['wechat_service_tool'])
            return '';
        $list = $this->dao->search([])->where('applyment_id','>',0)->selcet();
        $service = new Pay('partner');
        foreach ($list as $item) {
            $rateConfig = $service->getRate($item['applyment_id']);
            if (!$rateConfig) continue;
            $max_ratio = $rateConfig['config']['max_ratio'] / 100;
            $rate = 0;
            if ($item['commission_switch']) {
                $rate = $item['commission_rate'];
            } else if (
                isset($item['merchantCategory']['commission_rate']) &&
                $item['merchantCategory']['commission_rate'] > 0
            )
            {
                $rate = bcmul($item['merchantCategory']['commission_rate'], 100, 4);
            }
            Log::info('特约商户分账比例:'.$max_ratio.',系统设置比例：'.$rate);
            if (bccomp($max_ratio,$rate,4) == -1) {
                $item->applyment_switch = 0;
                $item->save();
            } else if (!$item->applyment_switch) {
                $item->applyment_switch = 0;
                $item->save();
            }
        }
    }
}
