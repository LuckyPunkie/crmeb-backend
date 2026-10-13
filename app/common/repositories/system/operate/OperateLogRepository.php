<?php

namespace app\common\repositories\system\operate;

use app\common\dao\system\operate\OperateLogDao;
use app\common\repositories\BaseRepository;
use app\common\repositories\system\auth\RoleRepository;
use app\Request;
use think\exception\ValidateException;
use function Symfony\Component\String\s;

class OperateLogRepository extends BaseRepository
{
    /**
     * 操作端
     */
    const PLATFORM_OPERATE = 1;   // 平台操作
    const MERCHANT_OPERATE = 2;   // 商户操作

    const RELEVANCE_PRODUCT = 'product';    // 操作商品
    const RELEVANCE_MERCHANT = 'merchant';  // 操作商户
    const RELEVANCE_MERCHANT_TYPE = 'merchant_type';  // 操作商户类别


    const ACTION_CREATE = 'create';  // 创建操作
    const ACTION_EDIT = 'edit';  // 编辑操作
    const ACTION_DELETE = 'delete';  // 删除操作

    /**
     * 商户端商品操作类型
     */

    // 商品添加
    const MERCHANT_CREATE_PRODUCT = 'create_product';
    // 编辑商品
    const MERCHANT_EDIT_PRODUCT = 'edit_product';
    // 价格增加
    const MERCHANT_INC_PRODUCT_PRICE = 'inc_product_price';
    // 价格减少
    const MERCHANT_DEC_PRODUCT_PRICE = 'dec_product_price';
    // 库存增
    const MERCHANT_INC_PRODUCT_STOCK = 'inc_product_stock';
    // 库存减
    const MERCHANT_DEC_PRODUCT_STOCK = 'dec_product_stock';
    // 商品上架
    const MERCHANT_EDIT_PRODUCT_ON_SALE = 'edit_product_on_sale';
    // 商品下架
    const MERCHANT_EDIT_PRODUCT_OFF_SALE = 'edit_product_off_sale';

    const MERCHANT_EDIT_AUDIT_STATUS = 'edit_audit_status';

    /**
     * 商户端商户操作类型
     */


    /**
     * 平台端商户操作类型
     */
    //  商户创建
    const PLATFORM_CREATE_MERCHANT = 'create_merchant';
    //  编辑商户
    const PLATFORM_EDIT_MERCHANT = 'edit_merchant';
    // 编辑:手续费变化
    const PLATFORM_EDIT_MERCHANT_COMMISSION = 'edit_merchant_commission';
    // 编辑:审核权限变化
    const PLATFORM_EDIT_MERCHANT_AUDIT_AUTH = 'edit_merchant_audit_auth';
    // 编辑:店铺开启关闭
    const PLATFORM_EDIT_MERCHANT_AUDIT_STATUS = 'edit_merchant_audit_status';
    // 编辑:店铺保证金变动
    const PLATFORM_EDIT_MERCHANT_AUDIT_MARGIN = 'edit_merchant_audit_margin';

    /**
     * 平台端商品操作类型
     */
    // 商品审核通过
    const PLATFORM_AUDIT_PRODUCT_PASS = 'audit_product_pass';
    // 商品审核未通过
    const PLATFORM_AUDIT_PRODUCT_REFUSE = 'audit_product_refuse';
    // 平台强制下架
    const PLATFORM_AUDIT_PRODUCT_OFF_SALE = 'audit_product_refuse_off_sale';
    // 商品显示
    const PLATFORM_EDIT_PRODUCT_SHOW = 'edit_product_show';
    // 商品关闭
    const PLATFORM_EDIT_PRODUCT_HIDE = 'edit_product_hide';

    protected $mer_id = 0;
    protected $title = '';
    protected $relevance_id;
    protected $relevance_type;
    protected $relevance_title;
    protected $type;
    protected $category;
    protected $action;
    protected $operator_uid;
    protected $operator_nickname;
    protected $operator_role_id;
    protected $operator_role_nickname;
    protected $mark;

    /**
     * 管理员用户信息
     * @var
     */
    protected $admin_info;


    /**
     * CacheRepository constructor.
     * @param OperateLogDao $dao
     */
    public function __construct(OperateLogDao $dao)
    {
        $this->dao = $dao;
    }


    /**
     * 获取类型列表
     * @param string $type
     * @return \string[][]
     *
     * @date 2023/10/16
     * @author yyw
     */
    public function getCategoryList(string $type)
    {
        $operate_list = [
            self::PLATFORM_OPERATE => [
                ['label' => '商品添加', 'value' => self::MERCHANT_CREATE_PRODUCT],
                ['label' => '价格增加', 'value' => self::MERCHANT_INC_PRODUCT_PRICE],
                ['label' => '价格减少', 'value' => self::MERCHANT_DEC_PRODUCT_PRICE],
                ['label' => '库存增', 'value' => self::MERCHANT_INC_PRODUCT_STOCK],
                ['label' => '库存减', 'value' => self::MERCHANT_DEC_PRODUCT_STOCK],
                ['label' => '商品上架', 'value' => self::MERCHANT_EDIT_PRODUCT_ON_SALE],
                ['label' => '商品下架', 'value' => self::MERCHANT_EDIT_PRODUCT_OFF_SALE],
                ['label' => '店铺开启关闭', 'value' => self::MERCHANT_EDIT_AUDIT_STATUS],
            ],
            self::MERCHANT_OPERATE => [
                ['label' => '商户创建', 'value' => self::PLATFORM_CREATE_MERCHANT],
                ['label' => '编辑:手续费变化', 'value' => self::PLATFORM_EDIT_MERCHANT_COMMISSION],
                ['label' => '编辑:审核权限变化', 'value' => self::PLATFORM_EDIT_MERCHANT_AUDIT_AUTH],
                ['label' => '编辑:店铺开启关闭', 'value' => self::PLATFORM_EDIT_MERCHANT_AUDIT_STATUS],
                ['label' => '编辑:店铺保证金变动', 'value' => self::PLATFORM_EDIT_MERCHANT_AUDIT_MARGIN],
                ['label' => '商品审核通过', 'value' => self::PLATFORM_AUDIT_PRODUCT_PASS],
                ['label' => '商品审核未通过', 'value' => self::PLATFORM_AUDIT_PRODUCT_REFUSE],
                ['label' => '商品强制下架', 'value' => self::PLATFORM_AUDIT_PRODUCT_OFF_SALE],
                ['label' => '商品显示', 'value' => self::PLATFORM_EDIT_PRODUCT_SHOW],
                ['label' => '商品关闭', 'value' => self::PLATFORM_EDIT_PRODUCT_HIDE],
            ],
        ];

        return $operate_list[$type];
    }

    /**
     * 获取类型
     * @param string $category
     * @return int
     *
     * @date 2023/10/16
     * @author yyw
     */
    public function getCategory(string $category)
    {
        if (in_array($category, [
            self::MERCHANT_CREATE_PRODUCT,
            self::MERCHANT_EDIT_PRODUCT,
            self::MERCHANT_INC_PRODUCT_PRICE,
            self::MERCHANT_DEC_PRODUCT_PRICE,
            self::MERCHANT_INC_PRODUCT_STOCK,
            self::MERCHANT_DEC_PRODUCT_STOCK,
            self::MERCHANT_EDIT_PRODUCT_ON_SALE,
            self::MERCHANT_EDIT_PRODUCT_OFF_SALE,
            self::MERCHANT_EDIT_AUDIT_STATUS,
        ])) {
            return self::MERCHANT_OPERATE;
        }
        if (in_array($category, [
            self::PLATFORM_CREATE_MERCHANT,
            self::PLATFORM_EDIT_MERCHANT,
            self::PLATFORM_EDIT_MERCHANT_COMMISSION,
            self::PLATFORM_EDIT_MERCHANT_AUDIT_AUTH,
            self::PLATFORM_EDIT_MERCHANT_AUDIT_STATUS,
            self::PLATFORM_EDIT_MERCHANT_AUDIT_MARGIN,
            self::PLATFORM_AUDIT_PRODUCT_PASS,
            self::PLATFORM_AUDIT_PRODUCT_REFUSE,
            self::PLATFORM_AUDIT_PRODUCT_OFF_SALE,
            self::PLATFORM_EDIT_PRODUCT_SHOW,
            self::PLATFORM_EDIT_PRODUCT_HIDE,
        ])) {
            return self::PLATFORM_OPERATE;
        }
        throw new ValidateException('操作类型异常');
    }

    /**
     * 操作类型转换
     * @param string $type
     * @return string
     *
     * @date 2023/10/16
     * @author yyw
     */
    public static function getTypeName(string $type)
    {
        $type_list = [
            self::PLATFORM_OPERATE => '平台操作',
            self::MERCHANT_OPERATE => '商户操作',
        ];

        return $type_list[$type] ?? '异常操作平台类型';
    }

    /**
     * 被操作类型转换
     * @param string $relevance_type
     * @return string
     *
     * @date 2023/10/16
     * @author yyw
     */
    public static function getRelevanceTypeName(string $relevance_type)
    {
        $relevance_type_list = [
            self::RELEVANCE_MERCHANT => '商户',
            self::RELEVANCE_PRODUCT => '商品',
            self::RELEVANCE_MERCHANT_TYPE => '店铺类型',
        ];
        return $relevance_type_list[$relevance_type] ?? '异常关联类型';
    }

    /**
     * 创建方法转换
     * @param string $action
     * @return string
     *
     * @date 2023/10/16
     * @author yyw
     */
    public static function getActionName(string $action)
    {
        $action_list = [
            self::ACTION_CREATE => '创建',
            self::ACTION_EDIT => '编辑',
            self::ACTION_DELETE => '删除',
        ];

        return $action_list[$action] ?? '异常操作类型';
    }

    /**
     * 类别转换
     * @param string $category
     * @return string
     *
     * @date 2023/10/16
     * @author yyw
     */
    public static function getCategoryName(string $category)
    {
        $category_list = [
            self::MERCHANT_CREATE_PRODUCT => '商品添加',
            self::MERCHANT_EDIT_PRODUCT => '编辑商品',
            self::MERCHANT_INC_PRODUCT_PRICE => '价格增加',
            self::MERCHANT_DEC_PRODUCT_PRICE => '价格减少',
            self::MERCHANT_INC_PRODUCT_STOCK => '库存增',
            self::MERCHANT_DEC_PRODUCT_STOCK => '库存减',
            self::MERCHANT_EDIT_PRODUCT_ON_SALE => '商品上架',
            self::MERCHANT_EDIT_PRODUCT_OFF_SALE => '商品下架',
            self::MERCHANT_EDIT_AUDIT_STATUS => '店铺开启关闭',
            self::PLATFORM_CREATE_MERCHANT => '商户创建',
            self::PLATFORM_EDIT_MERCHANT => '编辑商户',
            self::PLATFORM_EDIT_MERCHANT_COMMISSION => '手续费变化',
            self::PLATFORM_EDIT_MERCHANT_AUDIT_AUTH => '审核权限变化',
            self::PLATFORM_EDIT_MERCHANT_AUDIT_STATUS => '店铺开启关闭',
            self::PLATFORM_EDIT_MERCHANT_AUDIT_MARGIN => '店铺保证金变动',
            self::PLATFORM_AUDIT_PRODUCT_PASS => '商品审核通过',
            self::PLATFORM_AUDIT_PRODUCT_REFUSE => '商品审核未通过',
            self::PLATFORM_AUDIT_PRODUCT_OFF_SALE => '商品强制下架',
            self::PLATFORM_EDIT_PRODUCT_SHOW => '商品显示',
            self::PLATFORM_EDIT_PRODUCT_HIDE => '商品关闭',
        ];

        return $category_list[$category] ?? '异常类型';
    }


    /**
     * 创建日志（入口）
     * @param string $type
     * @param int $category
     * @param array $data
     * @param int $mer_id
     *
     * @date 2023/10/11
     * @author yyw
     */
    public function recordLog(string $category, array $data = [], int $mer_id = 0)
    {
        if (!in_array($category, [
            self::MERCHANT_CREATE_PRODUCT,
            self::MERCHANT_EDIT_PRODUCT,
            self::MERCHANT_INC_PRODUCT_PRICE,
            self::MERCHANT_DEC_PRODUCT_PRICE,
            self::MERCHANT_INC_PRODUCT_STOCK,
            self::MERCHANT_DEC_PRODUCT_STOCK,
            self::MERCHANT_EDIT_PRODUCT_ON_SALE,
            self::MERCHANT_EDIT_PRODUCT_OFF_SALE,
            self::MERCHANT_EDIT_AUDIT_STATUS,
            self::PLATFORM_CREATE_MERCHANT,
            self::PLATFORM_EDIT_MERCHANT,
            self::PLATFORM_EDIT_MERCHANT_COMMISSION,
            self::PLATFORM_EDIT_MERCHANT_AUDIT_AUTH,
            self::PLATFORM_EDIT_MERCHANT_AUDIT_STATUS,
            self::PLATFORM_EDIT_MERCHANT_AUDIT_MARGIN,
            self::PLATFORM_AUDIT_PRODUCT_PASS,
            self::PLATFORM_AUDIT_PRODUCT_REFUSE,
            self::PLATFORM_AUDIT_PRODUCT_OFF_SALE,
            self::PLATFORM_EDIT_PRODUCT_SHOW,
            self::PLATFORM_EDIT_PRODUCT_HIDE,
        ])) {
            throw new ValidateException('操作类型异常');
        }
        // 赋值属性
        $this->type = $this->getCategory($category);
        $this->category = $category;
        if ($mer_id) $this->mer_id = $mer_id;
        $this->processLogCategory($data);
        return true;
    }

    /**
     * 设置商品
     * @param $product
     * @return $this
     *
     * @date 2023/10/16
     * @author yyw
     */
    public function setProductInfo($product)
    {
        $this->relevance_type = self::RELEVANCE_PRODUCT;
        $this->relevance_id = $product['product_id'];
        $this->relevance_title = $product['store_name'] ?? '未知商品';
        $this->mer_id = $product['mer_id'];
        if ($this->type == self::PLATFORM_OPERATE) {
            $this->mer_id = 0;
        }

        return $this;
    }

    /**
     * 设置商户
     * @param $merchant
     * @return $this
     *
     * @date 2023/10/16
     * @author yyw
     */
    public function setMerchantInfo($merchant)
    {
        $this->relevance_type = self::RELEVANCE_MERCHANT;
        $this->relevance_id = $merchant['mer_id'];
        $this->relevance_title = $merchant['mer_name'] ?? '未知商户';
        $this->mer_id = $merchant['mer_id'];
        if ($this->type == self::PLATFORM_OPERATE) {
            $this->mer_id = 0;
        }

        return $this;
    }

    /**
     * 设置商家类型信息
     *
     * 本方法用于为当前对象设置与商家类型相关的属性，包括类型ID、类型名称和关联的ID等。
     * 这些信息通常用于后续的商家类型检索或分类操作。
     *
     * @param array $merchant_type 商家类型数据数组
     * @param int $merchant_type['mer_type_id'] 商家类型的ID
     * @param string $merchant_type['type_name'] 商家类型的名称
     *
     * @return object 返回当前对象，支持链式调用
     */
    public function setMerchantTypeInfo($merchant_type)
    {
        // 设置相关类型为商家类型
        $this->relevance_type = self::RELEVANCE_MERCHANT_TYPE;
        // 设置商家类型ID
        $this->relevance_id = $merchant_type['mer_type_id'];
        // 设置商家类型名称，如果未提供，则默认为'未知店铺类别'
        $this->relevance_title = $merchant_type['type_name'] ?? '未知店铺类别';
        // 初始化商家ID为0，表示尚未关联具体商家
        $this->mer_id = 0;

        // 返回当前对象，支持链式调用
        return $this;
    }


    /**
     * 设置操作员信息
     * @param $admin_info
     * @return $this
     *
     * @date 2023/10/13
     * @author yyw
     */
    protected function setOperatorInfo($admin_info)
    {
        if (empty($admin_info)) {
            throw new ValidateException('操作员信息为空');
        }
        if ($this->type == self::PLATFORM_OPERATE) {
            $this->operator_uid = $admin_info['admin_id'];
        } else {
            $this->operator_uid = $admin_info['merchant_admin_id'];
        }
        $this->operator_nickname = empty($admin_info['real_name']) ? $admin_info['account'] : $admin_info['real_name'];

        /** @var RoleRepository $roleRepository */
        $roleRepository = app()->make(RoleRepository::class);
        // 查找角色
        if (!empty($admin_info['roles'])) {
            $roles_list = $roleRepository->getRolesListByIds($admin_info['roles']);
            $role_nickname = '';
            foreach ($roles_list as $role) {
                $role_nickname .= $role['role_name'] . ',';
            }
            $this->operator_role_id = implode(',', $admin_info['roles']);
            $this->operator_role_nickname = $role_nickname ? rtrim($role_nickname, ',') : '超级管理员';
        }
        return $this;
    }

    /**
     * 处理日志
     * @param array $data
     * @return bool
     *
     * @date 2023/10/16
     * @author yyw
     */
    protected function processLogCategory(array $data = [])
    {
        $this->setOperatorInfo($data['admin_info']);
        switch ($this->category) {
            case self::MERCHANT_CREATE_PRODUCT:
                $this->createProductLog($data);
                break;
            case self::MERCHANT_EDIT_PRODUCT:
                $this->editProductLog($data);
                break;
            case self::MERCHANT_EDIT_PRODUCT_ON_SALE:
                $this->editProductOnSaleLog($data);
                break;
            case self::MERCHANT_EDIT_PRODUCT_OFF_SALE:
                $this->editProductOffSaleLog($data);
                break;
            case self::PLATFORM_CREATE_MERCHANT:
                $this->createMerchantLog($data);
                break;
            case self::PLATFORM_EDIT_MERCHANT:
                $this->editMerchantLog($data);
                break;
            case self::MERCHANT_EDIT_AUDIT_STATUS:
            case self::PLATFORM_EDIT_MERCHANT_AUDIT_STATUS:
                $this->editMerchantAuditStatusLog($data);
                break;
            case self::PLATFORM_EDIT_MERCHANT_AUDIT_MARGIN:
                $this->editMerchantAuditMarginLog($data);
                break;
            case self::PLATFORM_AUDIT_PRODUCT_PASS:
                $this->auditProductPassLog($data);
                break;
            case self::PLATFORM_AUDIT_PRODUCT_REFUSE:
                $this->auditProductRefuseLog($data);
                break;
            case self::PLATFORM_AUDIT_PRODUCT_OFF_SALE:
                $this->auditProductRefuseOffSale($data);
                break;
            case self::PLATFORM_EDIT_PRODUCT_SHOW:
                $this->editProductShowLog($data);
                break;
            case self::PLATFORM_EDIT_PRODUCT_HIDE:
                $this->editProductHideLog($data);
                break;
            default:
                throw new ValidateException('类型异常');
        }
        // 设置标题
        if (empty($this->title)) {
            $this->title = self::getActionName($this->action) . '：' . self::getCategoryName($this->category);
        }

        return true;
    }

    /**
     * 创建商品
     * @param array $data
     * @return \app\common\dao\BaseDao|\think\Model
     *
     * @date 2023/10/13
     * @author yyw
     */
    public function createProductLog(array $data)
    {
        $this->setProductInfo($data['product']);
        $this->action = self::ACTION_CREATE;
        $this->mark = $this->buildLogMark('创建了');
        return $this->createLog();
    }

    /**
     * 编辑商品
     * @param array $data
     * @return array
     *
     * @date 2023/10/16
     * @author yyw
     */
    public function editProductLog(array $data)
    {
        $this->setProductInfo($data['product']);
        if (empty($data['update_infos'])) {
            throw new ValidateException('数据异常，商品编辑日志记录失败');
        }
        $this->action = self::ACTION_EDIT;

        $update_infos = $data['update_infos'];
        $res = [];
        foreach ($update_infos as $key => $item) {
            if (empty($item)) {
                continue;
            }
            $this->category = $key;
            $this->mark = $this->buildLogMark('编辑了', '-' . $item);
            $res[] = $this->createLog();
        }
        return $res;
    }

    /**
     * 商品上架
     * @param array $data
     * @return \app\common\dao\BaseDao|\think\Model
     *
     * @date 2023/10/16
     * @author yyw
     */
    public function editProductOnSaleLog(array $data)
    {
        $this->setProductInfo($data['product']);
        $this->action = self::ACTION_EDIT;
        $this->mark = $this->buildLogMark('上架了');
        return $this->createLog();
    }

    /**
     * 商品下架
     * @param array $data
     * @return \app\common\dao\BaseDao|\think\Model
     *
     * @date 2023/10/16
     * @author yyw
     */
    public function editProductOffSaleLog(array $data)
    {
        $this->setProductInfo($data['product']);
        $this->action = self::ACTION_EDIT;
        $this->mark = $this->buildLogMark('下架了');
        return $this->createLog();
    }

    /**
     * 创建商户
     * @param array $data
     * @return \app\common\dao\BaseDao|\think\Model
     *
     * @date 2023/10/16
     * @author yyw
     */
    public function createMerchantLog(array $data)
    {
        $this->setMerchantInfo($data['merchant']);
        $this->action = self::ACTION_CREATE;
        $this->mark = $this->buildLogMark('创建了');
        return $this->createLog();
    }

    /**
     * 商户编辑日志记录
     * @param array $data
     * @return bool
     *
     * @date 2023/10/13
     * @author yyw
     */
    public function editMerchantLog(array $data)
    {
        $merchant = $data['merchant'] ?? [];
        $update_infos = $data['update_infos'] ?? [];
        if (empty($merchant) || empty($update_infos)) {
            throw new ValidateException('参数异常');
        }
        $this->setMerchantInfo($merchant);
        $this->action = self::ACTION_EDIT;
        // 对比参数  判断商户修改了什么
        if ($merchant['commission_switch'] != $update_infos['commission_switch'] || $merchant['commission_rate'] != $update_infos['commission_rate']) {
            $this->category = self::PLATFORM_EDIT_MERCHANT_COMMISSION;
            $mark = '商户手续费单独设置开关';
            if ($merchant['commission_rate'] != $update_infos['commission_rate']) {
                $mark .= '商户手续费单独设置为' . $update_infos['commission_rate'];
            }
            $this->mark = $this->buildLogMark(($update_infos['commission_switch'] == 1 ? '打开了' : '关闭了'), $mark);
            $this->createLog();
        }

        if ($merchant['is_audit'] != $update_infos['is_audit'] || $merchant['is_bro_room'] != $update_infos['is_bro_room'] || $merchant['is_bro_goods'] != $update_infos['is_bro_goods']) {
            $this->category = self::PLATFORM_EDIT_MERCHANT_AUDIT_AUTH;
            $this->mark = '';
            if ($merchant['is_audit'] != $update_infos['is_audit']) {
                $this->mark .= $this->buildLogMark(($update_infos['is_audit'] == 1 ? '打开了' : '关闭了'), '商品审核');
            }
            if ($merchant['is_bro_room'] != $update_infos['is_bro_room']) {
                $this->mark .= $this->buildLogMark(($update_infos['is_bro_room'] == 1 ? '打开了' : '关闭了'), '直播间审核');
            }
            if ($merchant['is_bro_goods'] != $update_infos['is_bro_goods']) {
                $this->mark .= $this->buildLogMark(($update_infos['is_bro_goods'] == 1 ? '打开了' : '关闭了'), '直播间商品审核');
            }

            $this->createLog();
        }

        if ($merchant['status'] != $update_infos['status']) {
            $this->category = self::PLATFORM_EDIT_MERCHANT_AUDIT_STATUS;
            $this->mark = $this->buildLogMark(($update_infos['status'] == 1 ? '打开了' : '关闭了'), '商户');
            $this->createLog();
        }

        return true;
    }

    /**
     * 编辑商户状态
     * @param array $data
     * @return true
     *
     * @date 2023/10/16
     * @author yyw
     */
    public function editMerchantAuditStatusLog(array $data)
    {
        $merchant = $data['merchant'] ?? [];
        $update_infos = $data['update_infos'] ?? [];
        if (empty($merchant) || empty($update_infos)) {
            throw new ValidateException('参数异常');
        }
        $this->setMerchantInfo($merchant);
        $this->action = self::ACTION_EDIT;

        $this->title = $update_infos['status'] == 1 ? '开启' : '关闭';
        $this->mark = $this->buildLogMark(($update_infos['status'] == 1 ? '打开了' : '关闭了'), '商户');
        $this->createLog();
        return true;
    }

    /**
     * 编辑:店铺保证金变动
     * @param array $data
     * @return \app\common\dao\BaseDao|false|\think\Model
     *
     * @date 2023/10/13
     * @author yyw
     */
    public function editMerchantAuditMarginLog(array $data)
    {
        $merchant = $data['merchant'] ?? [];
        $update_infos = $data['update_infos'] ?? [];
        if (empty($merchant) || empty($update_infos)) {
            throw new ValidateException('参数异常');
        }
        $this->setMerchantInfo($merchant);
        $this->action = self::ACTION_EDIT;
        $this->mark = $this->buildLogMark('编辑了', $update_infos['action'] . ':' . $update_infos['number']);
        return $this->createLog();
    }

    /**
     * 商品审核通过
     * @param array $data
     * @return \app\common\dao\BaseDao|\think\Model
     *
     * @date 2023/10/16
     * @author yyw
     */
    public function auditProductPassLog(array $data)
    {
        $this->setProductInfo($data['product']);
        $this->action = self::ACTION_EDIT;
        $this->mark = $this->buildLogMark('审核通过了');
        return $this->createLog();
    }

    /**
     * 商品审核拒绝
     * @param array $data
     * @return \app\common\dao\BaseDao|\think\Model
     *
     * @date 2023/10/16
     * @author yyw
     */
    public function auditProductRefuseLog(array $data)
    {
        $this->setProductInfo($data['product']);
        $this->action = self::ACTION_EDIT;
        $this->mark = $this->buildLogMark('审核拒绝了');
        return $this->createLog();
    }

    /**
     * 商品审核拒绝
     * @param array $data
     * @return \app\common\dao\BaseDao|\think\Model
     *
     * @date 2023/10/16
     * @author yyw
     */
    public function auditProductRefuseOffSale(array $data)
    {
        $this->setProductInfo($data['product']);
        $this->action = self::ACTION_EDIT;
        $this->mark = $this->buildLogMark('强制下架');
        return $this->createLog();
    }

    /**
     * 商品显示
     * @param array $data
     * @return \app\common\dao\BaseDao|\think\Model
     *
     * @date 2023/10/16
     * @author yyw
     */
    public function editProductShowLog(array $data)
    {
        $this->setProductInfo($data['product']);
        $this->action = self::ACTION_EDIT;
        $this->mark = $this->buildLogMark('开启了');
        return $this->createLog();
    }

    /**
     * 商品隐藏
     * @param array $data
     * @return \app\common\dao\BaseDao|\think\Model
     *
     * @date 2023/10/16
     * @author yyw
     */
    public function editProductHideLog(array $data)
    {
        $this->setProductInfo($data['product']);
        $this->action = self::ACTION_EDIT;
        $this->mark = $this->buildLogMark('关闭了');
        return $this->createLog();
    }

    /**
     * 构建日志标记
     * @param string $action_desc
     * @return string
     */
    protected function buildLogMark(string $action_desc, string $title = ''): string
    {
        $title = $title ?: self::getRelevanceTypeName($this->relevance_type);
        return "{$this->operator_nickname}(ID:{$this->operator_uid}){$action_desc}{$this->relevance_title}(ID:{$this->relevance_id}){$title}";
    }

    /**
     * 创建日志
     * @return \app\common\dao\BaseDao|\think\Model
     *
     * @date 2023/10/16
     * @author yyw
     */
    protected function createLog()
    {
        $this->mark = mb_substr($this->mark, 0, 1990);
        $save_data = [
            'mer_id' => $this->mer_id,
            "title" => $this->title,
            'relevance_id' => $this->relevance_id,
            'relevance_title' => $this->relevance_title,
            'relevance_type' => $this->relevance_type,
            'type' => $this->type,
            'category' => $this->category,
            'action' => $this->action,
            'operator_role_id' => $this->operator_role_id,
            'operator_role_nickname' => $this->operator_role_nickname,
            'operator_uid' => $this->operator_uid,
            'operator_nickname' => $this->operator_nickname,
            'mark' => $this->mark,
        ];
        return $this->dao->create($save_data);
    }

    /**
     * 获取日志
     * @param array $where
     * @param $page
     * @param $limit
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     *
     * @date 2023/10/11
     * @author yyw
     */
    public function lst(array $where, $page, $limit, int $mer_id = 0)
    {
        if ($mer_id) $where['mer_Id'] = $mer_id;
        $query = $this->dao->search($where)->append(['category_name'], true);
        $count = $query->count();
        $list = $query->page($page, $limit)->order('create_time DESC')->select()->toArray();

        return compact('count', 'list');
    }

}
