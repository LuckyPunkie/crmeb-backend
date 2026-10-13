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
namespace app\common\repositories\store\product;

use app\common\dao\store\product\ProductTakeDao;
use app\common\dao\store\product\ProductUnitDao;
use app\common\repositories\BaseRepository;
use FormBuilder\Factory\Elm;
use think\exception\ValidateException;
use think\facade\Route;

class ProductUnitRepository extends BaseRepository
{
    protected $dao;

    public function __construct(ProductUnitDao $dao)
    {
        $this->dao = $dao;
    }


    /**
     * 创建表单
     * @param int $mer_id
     * @return \FormBuilder\Form
     * @throws \FormBuilder\Exception\FormBuilderException
     *
     * @date 2023/10/16
     * @author yyw
     */
    public function createForm(int $mer_id)
    {
        $form = Elm::createForm(Route::buildUrl('merchantStoreProductUnitCreate')->build());
        $form->setRule([
            Elm::hidden('mer_id', $mer_id),
            Elm::input('value', '单位名称：')->placeholder('请输入单位名称')->required('请输入单位名称'),
            Elm::number('sort', '排序：', 0)->precision(0)->max(99999),
        ]);

        return $form->setTitle('添加商品单位');
    }


    /**
     * 创建
     * @param int $mer_id
     * @param array $data
     * @return \app\common\dao\BaseDao|\think\Model
     *
     * @date 2023/10/16
     * @author yyw
     */
    public function create(int $mer_id, array $data = [])
    {
        if (!$this->check($data['value'], $mer_id))
            throw new ValidateException('名称重复');
        $new_data = [
            'mer_id' => $mer_id,
            'value' => $data['value'],
            'sort' => $data['sort'],
        ];
        return $this->dao->create($new_data);
    }

    /**
     * 编辑表单
     * @param int $unit_id
     * @param int $mer_id
     * @return \FormBuilder\Form
     * @throws \FormBuilder\Exception\FormBuilderException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     *
     * @date 2023/10/16
     * @author yyw
     */
    public function updateForm(int $unit_id, int $mer_id)
    {
        if (!$info = $this->dao->getWhere(['is_del' => 0, 'mer_id' => $mer_id, $this->dao->getPk() => $unit_id])) {
            throw new ValidateException('数据不存在');
        }
        $info = $info->toArray();
        $form = Elm::createForm(Route::buildUrl('merchantStoreProductUnitUpdate', ['id' => $unit_id])->build());
        $form->setRule([
            Elm::hidden('mer_id', $mer_id),
            Elm::input('value', '单位名称：')->placeholder('请输入单位名称')->required('请输入单位名称'),
            Elm::number('sort', '排序：', 0)->precision(0)->max(99999),
        ]);

        return $form->setTitle('编辑商品单位')->formData($info);
    }

    /**
     * 编辑
     * @param int $unit_id
     * @param int $mer_id
     * @param $data
     * @return int
     * @throws \think\db\exception\DbException
     *
     * @date 2023/10/16
     * @author yyw
     */
    public function update(int $unit_id, int $mer_id, $data = [])
    {
        if (!$this->check($data['value'], $mer_id, $unit_id))
            throw new ValidateException('名称重复');
        if (!$this->dao->existsWhere(['is_del' => 0, 'mer_id' => $mer_id, $this->dao->getPk() => $unit_id])) {
            throw new ValidateException('数据不存在');
        }
        $new_data = [
            'mer_id' => $mer_id,
            'value' => $data['value'],
            'sort' => $data['sort'],
        ];
        return $this->dao->update($unit_id, $new_data);

    }

    /**
     * 列表
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     *
     * @date 2023/10/16
     * @author yyw
     */
    public function list(array $where = [], int $page = 1, int $limit = 10)
    {
        $where['status'] = 1;
        $where['is_del'] = 0;
        $query = $this->dao->search($where);

        $count = $query->count();
        $list = $query->page($page, $limit)->select()->toArray();

        return compact('count', 'list');
    }

    /**
     * 下拉列表
     * @param int $mer_id
     * @return array
     *
     * @date 2023/10/16
     * @author yyw
     */
    public function getSelectList(int $mer_id)
    {
        $where['mer_id'] = $mer_id;
        $where['status'] = 1;
        $where['is_del'] = 0;
        return $this->dao->search($where)->column('product_unit_id as value,value as label');
    }

    /**
     * 删除
     * @param int $unit_id
     * @param int $mer_id
     * @return int
     *
     * @date 2023/10/16
     * @author yyw
     */
    public function delete(int $unit_id, int $mer_id)
    {
        if (!$this->dao->existsWhere(['is_del' => 0, 'mer_id' => $mer_id, $this->dao->getPk() => $unit_id])) {
            throw new ValidateException('数据不存在');
        }

        return $this->dao->update($unit_id, ['is_del' => 1]);
    }

    /**
     * 是否重名
     * @param string $name
     * @param int $merId
     * @param null $id
     * @return bool
     * @author Qinii
     * @day 9/6/21
     */
    public function check(string $value, int $merId, $id = null)
    {
        $where['value'] = $value;
        $where['mer_id'] = $merId;
        $where['is_del'] = 0;
        $data = $this->dao->getWhere($where);
        if ($data) {
            if (!$id) return false;
            if ($id != $data['product_unit_id']) return false;
        }
        return true;
    }
}
