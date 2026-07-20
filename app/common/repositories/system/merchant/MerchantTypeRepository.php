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


use app\common\dao\system\merchant\MerchantTypeDao;
use app\common\repositories\BaseRepository;
use app\common\repositories\system\auth\MenuRepository;
use app\common\repositories\system\RelevanceRepository;
use FormBuilder\Factory\Elm;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Route;

/**
 * @mixin MerchantTypeDao
 */
class MerchantTypeRepository extends BaseRepository
{
    public function __construct(MerchantTypeDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取商家列表
     *
     * 根据指定的页码和每页数量，从数据库中检索商家信息。
     * 包括商家的认证信息，并以商家类型ID降序排列。
     *
     * @param integer $page 当前页码
     * @param integer $limit 每页的商家数量
     * @return array 包含商家总数和商家列表的数组
     */
    public function getList($page, $limit)
    {
        // 初始化查询，包括关联的认证信息
        $query = $this->dao->search()->with(['auth']);

        // 计算商家总数
        $count = $query->count();

        // 分页查询，并按商家类型ID降序排列，附加商户数量信息
        $list = $query->page($page, $limit)->order('mer_type_id DESC')->select()->append(['merchant_count']);

        // 处理每个商家的认证信息，将其ID列表存储在auth_ids字段中
        foreach ($list as $item){
            $item['auth_ids'] = array_column($item['auth']->toArray(), 'right_id');
            unset($item['auth']);
        }

        // 返回商家总数和商家列表
        return compact('count', 'list');
    }


    /**
     * 获取选择列表
     *
     * 本方法用于查询并返回特定表中的类型名称和类型ID组成的列表。这通常用于前端表单的选择框填充，
     * 提供了一个简单的方法来获取和呈现这类数据。
     *
     * @return array 返回一个包含类型ID和类型名称的数组
     */
    public function getSelect()
    {
        // 初始化查询，指定查询的字段为mer_type_id和type_name，这里使用了空数组作为搜索条件的占位符
        $query = $this->search([])->field('mer_type_id,type_name');

        // 执行查询并转换结果为数组格式，然后返回这个数组
        return $query->select()->toArray();
    }

    /**
     * 删除操作，用于删除指定ID的数据，并清理相关的关联数据。
     *
     * @param int $id 需要删除的数据的ID。
     * @return bool 删除操作的结果，true表示成功，false表示失败。
     */
    public function delete(int $id)
    {
        // 使用事务处理来确保删除操作和相关清理操作的原子性。
        return Db::transaction(function () use ($id) {
            // 删除指定ID的数据。
            $this->dao->delete($id);
            // 清理与该ID关联的商家类型ID。
            app()->make(MerchantRepository::class)->clearTypeId($id);
            // 批量删除与该ID相关的认证关联数据。
            app()->make(RelevanceRepository::class)->batchDelete($id, RelevanceRepository::TYPE_MERCHANT_AUTH);
        });
    }


    /**
     * 创建新的权限类型并关联权限。
     *
     * 本函数用于处理新增权限类型的操作，包括插入新的权限类型数据以及建立该类型与具体权限的关联关系。
     * 使用事务确保数据操作的原子性。
     *
     * @param array $data 包含权限类型信息和授权ID的数据数组。
     * @return \think\Model 新增的权限类型的模型对象。
     */
    public function create(array $data)
    {
        // 使用数据库事务处理来确保操作的完整性
        return Db::transaction(function () use ($data) {
            // 过滤并去重auth数组中的授权ID
            $auth = array_filter(array_unique($data['auth']));
            // 从$data中移除'auth'键，因为它已经不再需要
            unset($data['auth']);
            // 调用DAO层创建新的权限类型
            $type = $this->dao->create($data);
            // 初始化一个数组，用于存储后续要插入的关联数据
            $inserts = [];
            // 遍历授权ID数组，构建关联数据数组
            foreach ($auth as $id) {
                $inserts[] = [
                    'left_id' => $type->mer_type_id, // 新增权限类型的ID
                    'right_id' => (int)$id, // 当前授权ID
                    'type' => RelevanceRepository::TYPE_MERCHANT_AUTH // 关联类型常量
                ];
            }
            // 使用依赖注入创建RelevanceRepository实例，并插入所有关联数据
            app()->make(RelevanceRepository::class)->insertAll($inserts);
            // 返回新增的权限类型对象
            return $type;
        });
    }


    /**
     * 更新商户信息并同步更新权限关系。
     *
     * 本函数通过事务处理方式，确保更新商户信息和关联权限的操作一致性。
     * 具体步骤包括：
     * 1. 更新商户的基本信息；
     * 2. 删除旧的商户权限关联；
     * 3. 添加新的商户权限关联；
     * 4. 更新商户的保证金状态。
     *
     * @param int $id 商户ID。
     * @param array $data 商户更新的数据，包括基本信息和权限信息。
     * @return bool 更新操作是否成功的标志。
     */
    public function update(int $id, array $data)
    {
        return Db::transaction(function () use ($id, $data) {
            // 过滤并去重权限数组
            $auth = array_filter(array_unique($data['auth']));

            // 从更新数据中移除权限信息
            unset($data['auth']);

            // 准备新的权限关联数据
            $inserts = [];
            foreach ($auth as $aid) {
                $inserts[] = [
                    'left_id' => $id,
                    'right_id' => (int)$aid,
                    'type' => RelevanceRepository::TYPE_MERCHANT_AUTH
                ];
            }

            // 设置更新时间
            $data['update_time'] = date('Y-m-d H:i:s',time());

            // 更新商户基本信息
            $this->dao->update($id, $data);

            // 实例化关联仓库，用于处理权限关联的增删操作
            $make = app()->make(RelevanceRepository::class);

            // 删除旧的权限关联
            $make->batchDelete($id, RelevanceRepository::TYPE_MERCHANT_AUTH);

            // 插入新的权限关联
            $make->insertAll($inserts);

            // 更新商户的保证金状态
            // 更新未交保证金的商户
            app()->make(MerchantRepository::class)->updateMargin($id, $data['margin'], $data['is_margin']);
        });
    }


    /**
     * 根据$id$标记表单
     *
     * 本函数用于生成一个用于修改备注信息的表单。通过传入的$id$，获取相应的数据，
     * 并基于这些数据构建一个表单，允许用户修改备注信息。
     *
     * @param int $id 数据的唯一标识符
     * @return Elm 表单对象，用于渲染修改备注的表单
     * @throws ValidateException 如果根据$id$未能找到相关数据，则抛出异常
     */
    public function markForm($id)
    {
        // 根据$id$获取数据
        $data = $this->dao->get($id);
        // 如果数据不存在，则抛出异常
        if (!$data)  throw new ValidateException('数据不存在');

        // 创建表单对象，并设置表单提交的URL
        $form = Elm::createForm(Route::buildUrl('systemMerchantTypeMark', ['id' => $id])->build());

        // 设置表单的验证规则，包括一个文本输入框用于修改备注信息
        $form->setRule([
            Elm::text('mark', '备注：', $data['mark'])->placeholder('请输入备注')->required('请输入备注'),
        ]);

        // 设置表单的标题
        return $form->setTitle('修改备注');
    }

    /**
     * 标记数据为特定状态。
     *
     * 本函数用于更新指定ID的数据项的状态。在更新之前，它会检查该数据项是否存在，
     * 如果不存在，则抛出一个异常。这个方法确保了只有存在的数据才能被更新，避免了
     * 对不存在数据的更新操作，从而维护了数据的一致性和完整性。
     *
     * @param int $id 数据项的唯一标识符。用于指定要更新哪个数据项。
     * @param array $data 包含要更新到数据项的新状态的数据数组。
     * @throws ValidateException 如果指定ID的数据项不存在，则抛出此异常。
     */
    public function mark($id, $data)
    {
        // 检查指定ID的数据项是否存在
        if (!$this->dao->getWhereCount([$this->dao->getPk() => $id]))
            // 如果数据项不存在，则抛出异常
            throw new ValidateException('数据不存在');

        // 更新数据项的状态
        $this->dao->update($id, $data);
    }

    /**
     * 获取商户详情
     *
     * 该方法通过指定的ID检索商户信息及其授权权限。如果数据不存在，则抛出验证异常。
     * 商户的授权权限通过权限ID数组进行表示，并进一步转换为权限路径的选项列表。
     *
     * @param int $id 商户类型ID
     * @return array 商户详情，包括基本信息和授权权限选项
     * @throws ValidateException 如果商户数据不存在
     */
    public function detail($id)
    {
        // 搜索指定ID的商户信息，并加载其授权信息，同时附加商户数量
        $find = $this->dao->search(['mer_type_id' => $id])->with(['auth'])->find()->append(['merchant_count']);

        // 如果找不到数据，则抛出异常
        if (!$find)    throw new ValidateException('数据不存在');

        // 提取授权信息中的权利ID
        $ids = array_column($find['auth']->toArray(), 'right_id');
        // 移除授权信息对象，避免直接暴露内部结构
        unset($find['auth']);
        // 将权利ID数组附加到商户详情中，以'auth_ids'键存储
        $find['auth_ids'] = $ids;

        // 初始化选项数组
        $options = [];
        // 如果存在授权ID，进一步获取授权路径和选项
        if ($ids) {
            // 获取所有权限选项，包括路径信息，传入当前授权ID数组进行过滤
            $paths = app()->make(MenuRepository::class)->getAllOptions(1, true,compact('ids'),'path');
            // 合并权限路径中的ID，以获取完整的授权ID集合
            foreach ($paths as $id => $path) {
                $ids = array_merge($ids, explode('/', trim($path, '/')));
                array_push($ids, $id);
            }
            // 根据完整的授权ID集合，获取所有的权限选项
            $auth = app()->make(MenuRepository::class)->getAllOptions(1, true, compact('ids'));
            // 格式化权限选项为树状结构，以'menu_name'作为节点标识
            $options = formatTree($auth, 'menu_name');
        }
        // 将格式化的权限选项附加到商户详情中
        $find['options'] = $options;

        // 返回商户详情，包括基本信息和授权权限选项
        return $find;
    }

}
