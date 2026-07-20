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

use app\common\dao\store\product\ProductLabelDao;
use app\common\repositories\BaseRepository;
use FormBuilder\Factory\Elm;
use think\exception\ValidateException;
use think\facade\Route;

/**
 * 商品标签
 */
class ProductLabelRepository extends BaseRepository
{
    protected $dao;

    public function __construct(ProductLabelDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 列表
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     * @author Qinii
     * @day 8/17/21
     */
    public function getList(array $where, int $page, int $limit)
    {
        $where['is_del'] = 0;
        $query = $this->dao->getSearch($where)->order('sort DESC,create_time DESC');
        $count = $query->count();
        $list = $query->page($page, $limit)->select();
        return compact('count', 'list');
    }

    /**
     * 添加form
     * @param int|null $id
     * @param string $route
     * @param array $formData
     * @return \FormBuilder\Form
     * @author Qinii
     * @day 8/17/21
     */
    public function form(?int $id, string $route, array $formData = [])
    {
        $form = Elm::createForm(is_null($id) ? Route::buildUrl($route)->build() : Route::buildUrl($route, ['id' => $id])->build());
        $form->setRule([
            Elm::input('label_name', '标签名称：')->placeholder('请输入标签名称')->required('请输入标签名称'),
            Elm::input('info', '说明：')->placeholder('请输入说明'),
            Elm::number('sort', '排序：', 0)->precision(0)->max(99999),
            Elm::switches('status', '是否显示：', 1)->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->className('switch-width-double')->activeText('开启'),
        ]);
        return $form->setTitle(is_null($id) ? '添加标签' : '编辑标签')->formData($formData);
    }


    /**
     * 编辑form
     * @param int $id
     * @param string $route
     * @param int $merId
     * @return \FormBuilder\Form
     * @author Qinii
     * @day 8/17/21
     */
    public function updateForm(int $id, string $route, int $merId = 0)
    {
        $data = $this->dao->getWhere(['product_label_id' => $id, 'mer_id' => $merId]);
        if (!$data) throw new ValidateException('数据不存在');
        return $this->form($id, $route, $data->toArray());
    }

    /**
     * 根据商家ID获取商品标签选项
     *
     * 本函数用于查询指定商家ID下的可用商品标签信息。它通过筛选条件来限制查询结果，
     * 仅包括状态正常且未被删除的标签。查询结果按照排序值和创建时间倒序排列，
     * 并以数组形式返回标签的ID和名称。
     *
     * @param int $merId 商家ID，用于限定查询的商家范围
     * @return array 返回包含商品标签ID和名称的数组
     */
    public function getOptions(int $merId)
    {
        // 定义查询条件，包括商家ID、状态正常和未被删除
        $where = [
            'mer_id' => $merId,
            'status' => 1,
            'is_del' => 0
        ];

        // 调用DAO层的getSearch方法查询符合条件的商品标签，选择字段为product_label_id和label_name，
        // 按照sort和create_time降序排列，并将查询结果转换为数组返回
        return $this->dao->getSearch($where)
                         ->field('product_label_id id,label_name name')
                         ->order('sort DESC,create_time DESC')
                         ->select()
                         ->toArray();
    }

    /**
     * 检查给定的标签ID是否存在于数据库中。
     * 此方法用于验证传入的标签ID是否对应于指定商家在数据库中有记录。
     * 如果传入的数据不是数组，则将其视为以逗号分隔的字符串并转换为数组。
     * 如果任何一个标签ID不存在于数据库中，则抛出一个验证异常。
     *
     * @param int $merId 商家ID，用于限定查询的范围。
     * @param mixed $data 标签ID的数组或以逗号分隔的字符串。
     * @return bool 如果所有标签ID都存在，则返回true。
     * @throws ValidateException 如果任何一个标签ID不存在，则抛出异常。
     */
    public function checkHas($merId, $data)
    {
        // 检查传入的数据是否为空
        if (!empty($data)) {
            // 如果$data不是数组，则将其转换为数组
            if (!is_array($data)) {
                $data = explode(',', $data);
            }
            // 遍历数据数组中的每个标签ID
            foreach ($data as $item) {
                // 根据标签ID和商家ID查询数据库
                $data = $this->dao->getSearch(['product_label_id' => $item, 'mer_id' => $merId])->find();
                // 如果查询结果为空，则表示该标签ID不存在，抛出异常
                if (!$data) {
                    throw new ValidateException('标签ID：' . $item . '，不存在');
                }
            }
        }
        // 如果所有标签ID都存在，则返回true
        return true;
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
    public function check(string $name, int $merId, $id = null)
    {
        $where['label_name'] = $name;
        $where['mer_id'] = $merId;
        $where['is_del'] = 0;
        $data = $this->dao->getWhere($where);
        if ($data) {
            if (!$id) return false;
            if ($id != $data['product_label_id']) return false;
        }
        return true;
    }

    /**
     * 根据标签ID获取标签名称
     *
     * 本函数通过传入的标签ID，查询标签名称。它使用了DAO模式来执行数据库查询，
     * 以此获取与标签ID对应的标签名称。如果查询结果存在，则返回标签名称，
     * 否则返回空字符串。
     *
     * @param int $label_id 标签的唯一标识ID
     * @return string 返回查询到的标签名称，如果未查询到则返回空字符串
     */
    public function getLabelName($label_id)
    {
        // 根据标签ID查询标签名称，返回查询结果中label_name列的值，如果不存在则返回空字符串
        return $this->dao->query([$this->dao->getPk() => $label_id])->value('label_name') ?? '';
    }


}
