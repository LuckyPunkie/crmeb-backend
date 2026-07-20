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

namespace app\common\repositories\system\serve;

use app\common\dao\system\serve\ServeMealDao;
use app\common\repositories\BaseRepository;
use FormBuilder\Factory\Elm;
use think\exception\ValidateException;
use think\facade\Route;

class ServeMealRepository extends BaseRepository
{
    protected $dao;

    public function __construct(ServeMealDao $dao)
    {
        $this->dao = $dao;
    }


    /**
     * 获取列表数据
     *
     * 根据给定的条件和分页信息，从数据库中检索并返回符合条件的数据列表。
     * 此方法主要用于处理数据的查询和分页，确保只返回未被删除的数据。
     *
     * @param array $where 查询条件，一个包含各种条件的数组。
     * @param int $page 当前页码，用于分页查询。
     * @param int $limit 每页数据的数量，用于分页查询。
     * @return array 返回一个包含 'count' 和 'list' 两个元素的数组，'count' 表示总数据量，'list' 表示当前页的数据列表。
     */
    public function getList(array $where, int $page, int $limit)
    {
        // 默认设置为未删除的数据
        $where['is_del'] = 0;

        // 构建查询对象，并根据创建时间降序排序
        $query = $this->dao->getSearch($where)->order('create_time DESC');

        // 计算符合条件的数据总数
        $count = $query->count();

        // 根据当前页码和每页数据数量，获取数据列表
        $list = $query->page($page, $limit)->select();

        // 返回包含数据总数和数据列表的数组
        return compact('count','list');
    }

    /**
     * 更新表单数据。
     *
     * 本函数用于根据给定的ID获取数据库中的数据，并将其更新到表单中。如果数据不存在，则抛出一个验证异常。
     * 主要用于后台管理界面中，对已存在数据的编辑操作。
     *
     * @param int $id 数据库中记录的ID，用于唯一标识一条数据。
     * @return mixed 返回更新后的表单视图，以便用户进行编辑操作。
     * @throws ValidateException 如果数据不存在，则抛出此异常。
     */
    public function updateForm($id)
    {
        // 通过ID获取数据
        $data = $this->dao->get($id);

        // 检查数据是否存在，如果不存在则抛出异常
        if(!$data)  throw new ValidateException('数据不存在');

        // 将获取到的数据转换为数组格式，方便后续处理
        $data = $data->toArray();

        // 调用form方法，传入ID和数据数组，返回更新后的表单视图
        return $this->form($id,$data);
    }

    /**
     * 创建或编辑套餐表单
     *
     * 本函数用于生成一个套餐管理的表单，该表单可用于创建或编辑套餐。表单包含套餐的基本信息字段，
     * 如套餐名称、套餐类型、价格、数量、状态和排序。根据$id$的存在与否决定是创建新套餐还是编辑已有的套餐。
     *
     * @param int|null $id 套餐的ID，如果为null，则表示创建新套餐；否则，表示编辑现有套餐。
     * @param array $formData 表单的初始数据，用于填充表单字段。
    */
    public function form($id = null, array $formData = [])
    {
        // 判断是否为创建新套餐的操作
        $isCreate = is_null($id);

        // 根据创建或编辑的状态构建表单的提交URL
        $action = Route::buildUrl($isCreate ? 'systemServeMealCreate' : 'systemServeMealUpdate', $isCreate ? [] : compact('id'))->build();

        // 创建表单，设置表单的提交地址和包含的字段
        return Elm::createForm($action, [
            // 套餐名称输入字段
            Elm::input('name', '套餐名称：')->placeholder('请输入套餐名称')->required('请输入套餐名称'),
            // 套餐类型单选字段
            Elm::radio('type', '套餐类型：',1)->options([
                ['value' => 1, 'label' => '一号通商品采集'],
                ['value' => 2, 'label' => '一号通电子面单'],
            ]),
            // 套餐价格输入字段
            Elm::number('price', '价格：')->required('请输入价格'),
            // 套餐数量输入字段
            Elm::number('num', '数量：')->required('请输入数量'),
            // 套餐状态单选字段
            Elm::radio('status', '状态：', 1)->options([
                ['label' => '开启', 'value' => 1],
                ['label' => '关闭', 'value' => 0]
            ]),
            // 套餐排序输入字段
            Elm::number('sort', '排序：')->required('请输入排序')->precision(0)->max(99999),
        ])
        // 设置表单标题
        ->setTitle($isCreate ? '添加套餐' : '编辑套餐')
        // 设置表单的初始数据
        ->formData($formData);
    }

    /**
     * 删除指定ID的数据项。
     *
     * 本函数通过设置数据项的`is_del`字段为1来标记数据为删除状态，而不是物理删除数据。
     * 这种方式可以用于软删除，即在数据库中保留数据记录，但通过标记将其在应用层面上隐藏。
     *
     * @param int $id 数据项的唯一标识ID。
     * @throws ValidateException 如果指定ID的数据项不存在，则抛出异常。
     */
    public function delete($id)
    {
        // 通过ID获取数据项
        $data = $this->dao->get($id);

        // 检查数据项是否存在，如果不存在则抛出异常
        if(!$data) throw new ValidateException('数据不存在');

        // 将数据项的删除标记设置为1，表示该数据项已被删除
        $data->is_del = 1;

        // 更新数据项的删除状态
        $data->save();
    }

    /**
     * 生成二维码
     *
     * 本函数用于根据商家ID和特定数据生成二维码。它首先尝试根据传入的餐品ID从数据库中获取相关数据，
     * 然后根据获取的数据和商家ID组装查询参数，最后执行查询操作。
     *
     * @param int $merId 商家ID，用于指定生成二维码的商家。
     * @param array $data 包含餐品ID和类型的数组，用于指定生成二维码的具体餐品和类型。
     * @throws ValidateException 如果数据不存在，则抛出验证异常。
     */
    public function QrCode(int $merId, array $data)
    {
        // 根据传入的餐品ID尝试获取餐品数据
        $ret = $this->dao->get($data['meal_id']);

        // 如果获取的数据为空，则抛出异常，提示数据不存在
        if(!$data)  throw new ValidateException('数据不存在');

        // 组装查询参数，包括状态、删除标志、商家ID、类型和餐品ID
        $param = [
            'status' => 0,
            'is_del' => 0,
            'mer_id' => $merId,
            'type'   => $data['type'],
            'meal_id'=> $ret['meal_id'],
        ];

        // 根据组装的查询参数执行搜索操作，并调用find方法进行查询
        $this->dao->getSearch($param)->finid();

    }
}
