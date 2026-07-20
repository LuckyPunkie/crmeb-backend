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

namespace app\common\repositories\store\shipping;

use app\common\repositories\BaseRepository;
use app\common\dao\store\shipping\ExpressDao as dao;
use crmeb\services\CrmebServeServices;
use FormBuilder\Factory\Elm;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Route;

/**
 * 快递公司
 */
class ExpressRepository extends BaseRepository
{

    /**
     * ExpressRepository constructor.
     * @param dao $dao
     */
    public function __construct(dao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 检查指定名称是否已存在
     *
     * 本函数通过调用DAO层的方法来查询数据库中是否存在指定的名称。这在需要确保数据唯一性或需要进行数据验证的场景下非常有用。
     * 例如，在注册新用户时，需要检查用户名是否已被占用；或者在添加新商品时，需要确认商品名是否已存在。
     *
     * @param string $value 待检查的名称值
     * @param int|null $id 如果需要，可以根据特定ID进行排除检查（例如，检查其他用户是否已使用了这个名称，但不包括当前用户自己）
     * @return bool 返回true表示名称已存在，返回false表示名称不存在或查询失败
     */
    public function nameExists(string $value, ?int $id)
    {
        // 调用DAO层的方法来检查名称字段是否存在指定的值
        return $this->dao->merFieldExists('name', $value, null, $id);
    }

    /**
     * 检查指定的代码是否已存在于数据库中。
     *
     * 此方法通过调用DAO层的相应方法来查询数据库，确定给定的代码值是否已存在于指定的字段中。
     * 主要用于数据验证和唯一性检查，以确保新插入的数据不会与现有数据重复。
     *
     * @param string $value 待检查的代码值。
     * @param int|null $id 如果需要，可以根据特定ID限制查询范围。
     * @return bool 返回true表示代码已存在，返回false表示代码不存在。
     */
    public function codeExists(string $value, ?int $id)
    {
        // 调用DAO层的方法来检查代码是否存在
        return $this->dao->merFieldExists('code', $value, null, $id);
    }

    /**
     * 检查字段是否存在
     *
     * 本函数通过调用DAO对象的merFieldExists方法，来判断给定的值是否在数据库中对应的字段中存在。
     * 主要用于验证操作前的数据完整性或唯一性约束，确保操作的有效性和数据的准确性。
     *
     * @param int $value 需要检查的存在性的字段值
     * @return bool 返回字段是否存在，存在返回true，不存在返回false
     */
    public function fieldExists(int $value)
    {
        // 调用DAO方法检查字段是否存在
        return $this->dao->merFieldExists($this->dao->getPk(), $value);
    }

    /**
     * 根据条件搜索数据并分页返回结果。
     *
     * 本函数用于根据提供的条件数组$where进行数据搜索，然后按照指定的页码$page和每页数据量$limit进行分页。
     * 返回包含数据列表和总条数的数组。
     *
     * @param array $where 搜索条件数组，包含需要的查询条件。
     * @param int $page 当前页码，用于指定从哪一页开始获取数据。
     * @param int $limit 每页的数据条数，用于指定每页显示多少条数据。
     * @return array 返回包含 'count' 和 'list' 两个元素的数组，'count' 为数据总条数，'list' 为当前页的数据列表。
     */
    public function search(array $where, int $page, int $limit, $merId = 0)
    {
        // 构建查询语句，根据$where条件进行搜索，并按照'is_show'降序，'sort'降序，'id'升序进行排序。
        $query = $this->dao->search($where)->order('is_show DESC,sort DESC,id ASC');

        // 计算满足条件的数据总条数。
        $count = $query->count();

        // 根据当前页码和每页数据量，从查询结果中获取当前页的数据列表。
        $list = $query->page($page, $limit)->select();

        if($merId) {
            foreach ($list as &$item) {
                $item['mer_status'] = 0;
                if(in_array($merId, explode(',',$item['open_mer']))) {
                    $item['mer_status'] = 1;
                }

                unset($item['open_mer']);
            }
        }

        // 将数据总条数和当前页的数据列表组合成数组返回。
        return compact('count', 'list');
    }

    public function changeMerStatus($id, $merStatus, $merId = 0)
    {
        $info = $this->dao->get($id);

        if(!$info) {
            throw new ValidateException('快递公司不存在');
        }
        $openMerArray = explode(',', $info['open_mer']);
        empty($openMerArray[0]) ? $openMerArray = [] : $openMerArray;

        if($merStatus) {
            if(in_array($merId, $openMerArray)) {
                throw new ValidateException('已开启，请勿重复开启');
            }

            $openMerArray[] = $merId;
            $openMerArray = array_unique($openMerArray);
        } else {
            $key = array_search($merId, $openMerArray);
            if($key === false) {
                throw new ValidateException('未开启，请勿重复关闭');
            }
            unset($openMerArray[$key]);
        }
        $openMer = implode(',', $openMerArray);
        $res = $info->save(['open_mer' => $openMer]);
        if(!$res) {
            throw new ValidateException('修改失败');
        }

        // 根据ID获取快递公司信息
        return true;
    }

    /**
     * 创建或编辑快递公司的表单
     *
     * 本函数用于生成添加或编辑快递公司时所需的表单界面。
     * 通过传入不同的参数，决定是创建新的快递公司还是编辑已有的快递公司。
     * 表单包含了快递公司名称、编码、是否显示以及排序等必填字段。
     *
     * @param int $merId 商家ID，当前未在函数中使用，预留参数
     * @param int|null $id 快递公司的ID，如果为null，则表示创建新的快递公司；否则，表示编辑已有的快递公司
     * @param array $formData 表单的初始数据，用于填充表单字段
     * @return mixed 返回生成的表单对象
     */
    public function form(int $merId, ?int $id = null, array $formData = [])
    {
        // 根据$id的值决定生成创建还是更新快递公司的表单URL
        $url = is_null($id) ? Route::buildUrl('systemExpressCreate')->build() : Route::buildUrl('systemExpressUpdate', ['id' => $id])->build();
        // 创建表单实例，并设置表单的提交URL
        $form = Elm::createForm($url);

        // 设置表单的验证规则，包括公司名称、编码、是否显示和排序字段
        $form->setRule([
            Elm::input('name', '公司名称：')->placeholder('请输入快递公司名称')->required('请输入快递公司名称'),
            Elm::input('code', '公司编码：')->placeholder('请输入快递公司编码')->required('请输入快递公司编码'),
            Elm::switches('is_show', '是否显示：', 1)->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->className('switch-width-double')->activeText('开启'),
            Elm::number('sort', '排序：', 0)->precision(0)->max(99999),
        ]);

        // 根据$id的值设置表单标题，并传入formData初始化表单字段
        return $form->setTitle(is_null($id) ? '添加快递公司' : '编辑快递公司')->formData($formData);
    }

    /**
     * 更新表单数据的方法
     *
     * 本方法用于根据给定的商户ID和表单ID来获取当前表单数据，并准备更新操作。
     * 它首先从数据访问对象（DAO）中检索指定ID和商户ID的表单数据，然后将这些数据传递给
     * form方法，用于更新表单的显示或处理。
     *
     * @param int|null $merId 商户ID，用于区分不同商户的数据，如果为null，则表示操作的是全局数据。
     * @param int $id 表单的唯一标识ID，用于定位具体的表单数据。
     * @return array 返回一个包含表单数据的数组，供更新表单使用。
     */
    public function updateForm(?int $merId, $id)
    {
        // 通过DAO获取指定ID和merId的表单数据，并转换为数组形式
        // 这里使用$this->dao->get($id, $merId)来获取表单数据，
        // 然后使用toArray()方法将其转换为数组，以便在form方法中使用。
        return $this->form($merId, $id, $this->dao->get($id, $merId)->toArray());
    }

    /**
     * 切换状态函数
     * 本函数用于通过指定的ID和数据集来更新数据库中相应记录的状态。
     * 主要应用于需要进行状态切换的场景，如启用/禁用用户、文章等。
     *
     * @param int $id 需要更新状态的记录的ID
     * @param array $data 包含状态更新数据的数据集，通常应包含状态字段
     * @return bool 更新操作的结果，成功返回true，失败返回false
     */
    public function switchStatus($id, $data)
    {
        // 调用DAO层的update方法，执行状态更新操作
        return $this->dao->update($id, $data);
    }

    /**
     * 获取选项数据
     *
     * 本函数用于查询数据库中所有is_show字段为1的记录，并返回这些记录的name和code字段。
     * name字段被作为标签显示，code字段被作为值使用。返回的数据格式为数组，方便后续处理。
     *
     * @return array 返回一个数组，数组中的每个元素包含label和value属性，分别对应数据库中的name和code字段。
     */
    public function options($merId = 0)
    {
        return $this->dao->options($merId, ['is_show' => 1], 'name label,code value');
    }



    /**
     * 从一号通同步数据
     * @author Qinii
     * @day 7/23/21
     */
    public function syncExportAll()
    {
        $services = app()->make(CrmebServeServices::class);
        $result = $services->express()->express();
        $has = $this->dao->search([])->find();
        if (!is_null($has)) {
            $arr = [];
            foreach ($result['data'] as $datum) {
                $res = $this->dao->getWhere(['code' => $datum['code']]);
                if ($res) {
                    $res->name = $datum['name'];
                    $res->mark = $datum['mark'];
                    $res->partner_id = $datum['partner_id'];
                    $res->partner_key = $datum['partner_key'];
                    $res->partner_name = $datum['partner_name'];
                    $res->check_man = $datum['check_man'];
                    $res->is_code = $datum['is_code'];
                    $res->net = $datum['net'];
                    $res->save();
                } else {
                    $arr[] = [
                        'code' => $datum['code'],
                        'name' => $datum['name'],
                        'mark' => $datum['mark'],
                        'partner_id' => $datum['partner_id'],
                        'partner_key' => $datum['partner_key'],
                        'partner_name' => $datum['partner_name'],
                        'check_man' => $datum['check_man'],
                        'is_code' => $datum['is_code'],
                        'net' => $datum['net'],
                        'sort' => 0,
                    ];
                }
            }
        } else {
            $data = $result['data'];
            $arr = array_map(function ($datum){
                return [
                    'code' => $datum['code'],
                    'name' => $datum['name'],
                    'mark' => $datum['mark'],
                    'partner_id' => $datum['partner_id'],
                    'partner_key' => $datum['partner_key'],
                    'partner_name' => $datum['partner_name'],
                    'check_man' => $datum['check_man'],
                    'is_code' => $datum['is_code'],
                    'net' => $datum['net'],
                    'sort' => 0,
                ];
            },$data);
        }
        $this->dao->insertAll($arr);
    }

    /**
     * 合作伙伴表单生成方法
     * 用于根据传递的ID和商家ID生成合作伙伴的编辑表单
     * 主要用于编辑月结账号相关信息。
     *
     * @param int $id 快递公司的ID
     * @param int $merId 商家的ID
     * @return \EasyWeChat\Kernel\Messages\Form 生成的表单对象
     * @throws ValidateException 当数据表中无需要编辑的信息时抛出异常
     */
    public function partnerForm($id, $merId)
    {
        // 根据商家ID和快递公司ID构建查询条件
        $where = ['mer_id' => $merId, 'express_id' => $id];

        // 根据ID获取表单数据
        $formData = $this->dao->get($id);

        // 判断表单数据中是否包含任何需要编辑的信息，如果没有则抛出异常
        if (!$formData['partner_id'] && !$formData['partner_key'] && !$formData['check_man'] && !$formData['partner_name'] && !$formData['is_code'] && !$formData['net'])
            throw new ValidateException('无需月结账号');

        // 根据查询条件获取合作伙伴的信息
        $res = app()->make(ExpressPartnerRepository::class)->getSearch($where)->find();

        // 构建表单URL
        $form = Elm::createForm(Route::buildUrl('merchantExpressPratnerUpdate', ['id' => $id])->build());

        // 根据表单数据中相应的标志位，生成相应的表单字段
        if ($formData['partner_id'] == 1)
            $field[] = Elm::input('account', '月结账号：', $res['account'] ?? '')->placeholder('请输入月结账号');
        if ($formData['partner_key'] == 1)
            $field[] = Elm::input('key', '月结密码：', $res['key'] ?? '')->placeholder('请输入月结密码');
        if ($formData['net'] == 1)
            $field[] = Elm::input('net_name', '取件网点：', $res['net_name'] ?? '')->placeholder('请输入取件网点');
        if ($formData['check_man'] == 1)
            $field[] = Elm::input('check_man', '承载快递员名称：', $res['check_man'] ?? '')->placeholder('请输入承载快递员名称');
        if ($formData['partner_name'] == 1)
            $field[] = Elm::input('partner_name', '客户账户名称：', $res['partner_name'] ?? '')->placeholder('请输入客户账户名称');
        if ($formData['is_code'] == 1)
            $field[] = Elm::input('code', '承载编号：', $res['code'] ?? '')->placeholder('请输入承载编号');

        // 添加启用状态的单选框
        $field[] = Elm::radio('status', '是否启用：', $res['status'] ?? 1)->options([['value' => 0, 'label' => '隐藏'], ['value' => 1, 'label' => '启用']]);

        // 设置表单字段规则
        $form->setRule($field);

        // 设置表单标题和初始数据
        return $form->setTitle('编辑月结账号')->formData($formData->toArray());
    }

    /**
     * 添加月结账号
     * @param array $data
     * @author Qinii
     * @day 7/23/21
     */
    public function updatePartne(array $data)
    {
        Db::transaction(function () use ($data) {
            $make = app()->make(ExpressPartnerRepository::class);
            $where = [
                'express_id' => $data['express_id'],
                'mer_id' => $data['mer_id']
            ];
            $getData = $make->getSearch($where)->find();
            if ($getData) {
                $make->update($getData['id'], $data);
            } else {
                $make->create($data);
            }
        });
    }
}
