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

use app\common\repositories\BaseRepository;
use app\common\repositories\system\config\ConfigValueRepository;
use crmeb\jobs\SendSmsJob;
use crmeb\services\SmsService;
use FormBuilder\Factory\Elm;
use app\common\dao\system\merchant\MerchantIntentionDao;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Queue;
use think\facade\Route;

/**
 * 商户申请
 */
class MerchantIntentionRepository extends BaseRepository
{

    public function __construct(MerchantIntentionDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 根据条件获取商户列表
     *
     * 本函数用于根据给定的条件数组 $where，获取指定页码 $page 和每页数量 $limit 的商户列表。
     * 它首先通过条件搜索查询商户数据，然后计算总记录数，最后分页并排序后返回商户列表。
     *
     * @param array $where 搜索条件数组
     * @param int $page 当前页码
     * @param int $limit 每页的记录数
     * @return array 包含 'count' 和 'list' 两个元素的数组，'count' 为总记录数，'list' 为分页后的商户列表
     */
    public function getList(array $where, $page, $limit)
    {
        // 根据条件搜索商户数据
        $query = $this->dao->search($where);

        // 计算搜索结果的总记录数
        $count = $query->count();

        // 进行分页，并按照创建时间降序和状态升序排序，同时加载关联的商户类别和类型信息
        $list = $query->page($page, $limit)->order('create_time DESC , status ASC')->with(['merchantCategory', 'merchantType'])->select();

        // 返回包含总记录数和分页后的商户列表的数组
        return compact('count', 'list');
    }

    /**
     * 获取意向商家详情
     *
     * 本函数用于根据提供的意向商家ID和可选的用户ID，从数据库中检索对应的意向商家详情。
     * 如果提供了用户ID，则只返回该用户相关的意向商家详情；否则，返回所有与指定意向商家ID相关的详情。
     *
     * @param int $id 意向商家的唯一标识ID
     * @param int|null $uid 可选的用户ID，用于过滤结果，仅返回该用户相关的意向商家详情
     * @return array|null 返回符合条件的意向商家详情数组，如果未找到则返回null
     */
    public function detail($id, ?int $uid)
    {
        // 定义查询条件，初始只包含意向商家ID
        $where = ['mer_intention_id' => $id];

        // 如果提供了用户ID，则将其添加到查询条件中
        if (!is_null($uid)) {
            $where['uid'] = $uid;
        }

        // 执行查询并返回结果
        return $this->dao->search($where)->find();
    }

    /**
     * 更新意向商家的信息。
     *
     * 此方法用于更新意向商家在数据库中的信息。它首先检查该意向商家是否存在以及其状态是否为启用，
     * 如果存在且状态为启用，则抛出一个异常，表示当前状态不能修改。然后，它处理图片数据，
     * 将图片数组转换为以逗号分隔的字符串，并设置默认的状态、失败消息。最后，它调用DAO层的方法
     * 来更新数据库中的记录。
     *
     * @param int $id 意向商家的ID。
     * @param array $data 包含要更新的意向商家信息的数组。
     * @return bool 更新操作的结果，true表示成功，false表示失败。
     * @throws ValidateException 如果意向商家当前状态不允许修改，则抛出此异常。
     */
    public function updateIntention($id, array $data)
    {
        // 检查意向商家是否存在且状态为启用，如果存在则抛出异常
        if ($this->dao->existsWhere(['mer_intention_id' => $id, 'status' => '1']))
            throw new ValidateException('当前状态不能修改');

        // 处理图片数据，将数组转换为以逗号分隔的字符串
        $data['images'] = implode(',', (array)$data['images']);

        // 设置默认的状态和失败消息
        $data['status'] = 0;
        $data['fail_msg'] = '';

        // 调用DAO层的方法更新数据库中的记录
        return $this->dao->update($id, $data);
    }

    /**
     * 标记商家意向的表单生成方法
     *
     * 本方法用于生成一个用于修改商家意向备注的表单。通过给定的商家意向ID，获取当前备注信息，
     * 并构建一个表单以允许用户输入新的备注。表单提交的URL是基于当前商家意向ID动态生成的。
     *
     * @param int $id 商家意向的唯一标识ID，用于获取当前商家意向的备注信息以及构建表单提交的URL。
     * @return \FormBuilder\Form|\Phper6\Elm\Form
     */
    public function markForm($id)
    {
        // 通过商家意向ID获取当前商家意向的备注信息
        $data = $this->dao->get($id);

        // 构建表单提交的URL，使用商家意向ID作为参数
        $form = Elm::createForm(Route::buildUrl('systemMerchantIntentionMark', ['id' => $id])->build());

        // 设置表单的验证规则，这里仅包含一个文本区域用于输入备注信息
        $form->setRule([
            Elm::textarea('mark', '备注：', $data['mark'])->placeholder('请输入备注')->required('请输入备注'),
        ]);

        // 设置表单的标题为“修改备注”
        return $form->setTitle('修改备注');
    }

    /**
     * 创建用于修改商户意向状态的表单
     *
     * 该方法通过Elm库构建一个表单，用于修改商户的意向状态。表单中包含一个选择字段，用于选择审核状态（同意或拒绝）。
     * 根据所选的审核状态，表单会动态显示不同的字段：如果选择同意，则需要选择是否自动创建商户；如果选择拒绝，则需要输入拒绝的原因。
     *
     * @param int $id 商户意向的ID，用于构建表单的提交URL。
     * @return \FormBuilder\Form|\think\form\Form
     */
    public function statusForm($id)
    {
        // 构建表单提交的URL，使用Route类生成带ID的URL
        $form = Elm::createForm(Route::buildUrl('systemMerchantIntentionStatus', ['id' => $id])->build());

        // 设置表单的验证规则
        $form->setRule([
            // 创建一个选择字段，用于选择商户的审核状态
            Elm::select('status', '审核状态：', 1)->options([
                ['value' => 1, 'label' => '同意'],
                ['value' => 2, 'label' => '拒绝'],
            ])->control([
                // 当选择同意时，显示一个选择字段，用于选择是否自动创建商户
                [
                    'value' => 1,
                    'rule' => [
                        Elm::radio('create_mer', '自动创建商户：', 1)->options([
                            ['value' => 1, 'label' => '创建'],
                            ['value' => 2, 'label' => '不创建'],
                        ])
                    ]
                ],
                // 当选择拒绝时，显示一个文本区域，用于输入拒绝的原因
                [
                    'value' => 2,
                    'rule' => [
                        Elm::textarea('fail_msg', '失败原因：', '信息填写有误')->placeholder('请输入失败原因')
                    ]
                ]
            ]),
        ]);

        // 设置表单的标题
        return $form->setTitle('审核状态');
    }

    /**
     * 更新商家意向状态
     * 此函数用于处理商家意向的更新操作，包括状态更改、信息完善等。
     * @param $id 商家意向ID
     * @param $data 更新的数据数组
     * @throws ValidateException 当信息不存在或状态不正确时抛出异常
     */
    public function updateStatus($id, $data)
    {
        // 判断是否创建商家
        $create = $data['create_mer'] == 1;
        // 移除创建商家标记
        unset($data['create_mer']);
        // 根据ID查询商家意向信息
        $intention = $this->search(['mer_intention_id' => $id])->find();
        // 如果商家意向不存在，抛出异常
        if (!$intention)
            throw new ValidateException('信息不存在');
        // 如果商家意向状态已设置，抛出异常
        if ($intention->status)
            throw new ValidateException('状态有误,修改失败');
        // 获取系统配置信息
        $config = systemConfig(['broadcast_room_type', 'broadcast_goods_type']);

        // 获取商家类型信息
        $margin = app()->make(MerchantTypeRepository::class)->get($intention['mer_type_id']);
        // 设置商家意向的保证金相关字段
        $data['is_margin'] = $margin['is_margin'] ?? -1;
        $data['margin'] = $margin['margin'] ?? 0;

        // 初始化商家数据和短信数据数组
        $merData = [];
        $smsData = [];
        // 如果是创建商家
        if ($create == 1) {
            // 处理商家密码
            $password = substr($intention['phone'], -6);
            // 构建商家信息数组
            $merData = [
                'mer_name' => $intention['mer_name'],
                'mer_phone' => $intention['phone'],
                'mer_account' => $intention['phone'],
                'category_id' => $intention['merchant_category_id'],
                'type_id' => $intention['mer_type_id'],
                'real_name' => $intention['name'],
                'region_id' => $intention['circle_id'],
                'business_id' => $intention['business_id'],
                'status' => 1,
                'is_audit' => 1,
                'is_bro_room' => $config['broadcast_room_type'] == 1 ? 0 : 1,
                'is_bro_goods' => $config['broadcast_goods_type'] == 1 ? 0 : 1,
                'mer_password' => $password,
                'is_margin' => $margin['is_margin'] ?? -1,
                'margin' => $margin['margin'] ?? 0,
                'mark' => $margin['margin'] ?? 0,
            ];
            // 设置失败原因为空
            $data['fail_msg'] = '';
            // 构建短信数据数组
            $smsData = [
                'date' => date('m月d日', strtotime($intention->create_time)),
                'mer' => $intention['mer_name'],
                'phone' => $intention['phone'],
                'pwd' => $password ?? '',
                'site_name' => systemConfig('site_name'),
            ];
        }
        // 如果状态更新为2（可能是审核不通过）
        if ($data['status'] == 2) {
            // 设置短信数据
            $smsData = [
                'phone' => $intention['phone'],
                'date' => date('m月d日', strtotime($intention->create_time)),
                'mer' => $intention['mer_name'],
                'site' => systemConfig('site_name'),
            ];
        }

        // 开启数据库事务处理
        Db::transaction(function () use ($config, $intention, $data, $create,$margin,$merData,$smsData) {
            // 如果状态更新为1（审核通过）
            if ($data['status'] == 1) {
                // 如果需要创建商家
                if ($create == 1) {
                    // 创建商家
                    $merchant = app()->make(MerchantRepository::class)->createMerchant($merData);
                    // 保存商家证书信息
                    app()->make(ConfigValueRepository::class)->setFormData(['mer_certificate' => $intention['images']], $merchant->mer_id);
                    // 设置商家ID
                    $data['mer_id'] = $merchant->mer_id;
                    // 添加发送短信任务
                    Queue::push(SendSmsJob::class, ['tempId' => 'APPLY_MER_SUCCESS', 'id' => $smsData]);
                }
            } else {
                // 添加发送短信任务（审核不通过）
                Queue::push(SendSmsJob::class, ['tempId' => 'APPLY_MER_FAIL', 'id' => $smsData]);
            }
            // 保存商家意向信息
            $intention->save($data);
        });
    }

}
