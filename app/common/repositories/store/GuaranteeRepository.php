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
namespace app\common\repositories\store;

use app\common\dao\store\GuaranteeDao;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\product\ProductRepository;
use FormBuilder\Factory\Elm;
use think\facade\Route;

/**
 * 保障服务
 */
class GuaranteeRepository extends BaseRepository
{
    /**
     * @var GuaranteeDao
     */
    protected $dao;


    /**
     * GuaranteeRepository constructor.
     * @param GuaranteeDao $dao
     */
    public function __construct(GuaranteeDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 平台列表
     * @param $where
     * @param $page
     * @param $limit
     * @return array
     * @author Qinii
     * @day 5/17/21
     */
    public function getList($where, $page, $limit)
    {
        $query = $this->dao->getSearch($where)->order('sort DESC');
        $count = $query->count();
        $list = $query->page($page, $limit)->select();
        return compact('count', 'list');
    }

    /**
     * 根据条件查询保证信息列表
     *
     * 本函数通过调用DAO层的方法，根据传入的条件数组查询保证信息的相关数据。
     * 查询的字段包括保证ID、保证名称、保证信息和保证图片，结果按排序降序返回。
     *
     * @param array $where 查询条件数组
     * @return array 查询到的保证信息列表
     */
    public function select(array $where)
    {
        // 调用DAO方法进行查询，指定查询字段，排序方式，并返回查询结果
        $list = $this->dao->getSearch($where)
                          ->field('guarantee_id,guarantee_name,guarantee_info,image')
                          ->order('sort DESC')
                          ->select();
        return $list;
    }

    /**
     * 添加form
     * @param int|null $id
     * @param array $formData
     * @return \FormBuilder\Form
     * @author Qinii
     * @day 5/17/21
     */
    public function form(?int $id, array $formData = [])
    {
        $form = Elm::createForm(is_null($id) ? Route::buildUrl('systemGuaranteeCreate')->build() : Route::buildUrl('systemGuaranteeUpdate', ['id' => $id])->build());
        $form->setRule([
            Elm::input('guarantee_name', '服务条款：')->placeholder('请输入服务条款')->required('请输入服务条款'),
            Elm::textarea('guarantee_info', '内容描述：')->autosize([
                'minRows' => 1000,
            ])->placeholder('请输入内容描述')->required('请输入内容描述'),
            Elm::frameImage('image', '条款图标：', '/' . config('admin.admin_prefix') . '/setting/uploadPicture?field=image&type=1')->value($formData['image'] ?? '')->icon('el-icon-camera')->modal(['modal' => false])->width('1000px')->height('600px')->required('请选择条款图标')->appendRule('suffix', [
                'type' => 'div',
                'style' => ['color' => '#999999'],
                'domProps' => [
                    'innerHTML' => '建议尺寸：100*100px',
                ],
            ]),
            Elm::switches('status', '是否显示：', 1)->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->className('switch-width-double')->activeText('开启'),
            Elm::number('sort', '排序：', 0)->precision(0)->max(99999),
        ]);
        return $form->setTitle(is_null($id) ? '添加服务条款' : '编辑服务条款')->formData($formData);
    }

    /**
     * 编辑form
     * @param $id
     * @return \FormBuilder\Form
     * @author Qinii
     * @day 5/17/21
     */
    public function updateForm($id)
    {
        $ret = $this->dao->get($id);
        return $this->form($id, $ret->toArray());
    }

    /**
     * 获取详情
     * @param $id
     * @return array|\think\Model|null
     * @author Qinii
     * @day 5/17/21
     */
    public function get($id)
    {
        $where = [
            $this->dao->getPk() => $id,
            'is_del' => 0,
        ];
        $ret = $this->dao->getWhere($where);
        return $ret;
    }

    /**
     * 统计并更新保障服务的商家和商品数量。
     * 此方法用于查询所有有效的保障服务，然后分别统计每个保障服务所覆盖的商家数量和商品数量。
     * 最后，它将更新这些统计信息到对应的保障服务记录中。
     *
     * @return void 无返回值
     */
    public function countGuarantee()
    {
        /**
         * 查询所有状态为启用且未被删除的保障服务ID。
         * 这里的注释解释了查询的目的和条件，即只考虑启用中且未被删除的保障服务。
         */
        /**
         * 获取所有条款
         * 计算商户数量
         * 计算商品数量
         */
        $ret = $this->dao->getSearch(['status' => 1, 'is_del' => 0])->column($this->dao->getPk());

        // 实例化保障服务值 repository 和商品 repository，用于后续的查询和统计。
        $make = app()->make(GuaranteeValueRepository::class);
        $makeProduct = app()->make(ProductRepository::class);

        // 如果查询到保障服务ID，则遍历每个保障服务进行统计更新。
        if ($ret) {
            foreach ($ret as $k => $v) {
                $item = [];

                // 统计每个保障服务覆盖的商家数量。
                $item['mer_count'] = $make->getSearch(['guarantee_id' => $v])->group('mer_id')->count('*');

                // 统计每个保障服务使用的模板ID，用于后续的商品数量统计。
                $template = $make->getSearch(['guarantee_id' => $v])->group('guarantee_template_id')->column('guarantee_template_id');

                // 统计每个保障服务覆盖的商品数量。
                $item['product_cout'] = $makeProduct->getSearch(['guarantee_template_id' => $template])->count('*');

                // 更新统计信息的时间戳。
                $item['update_time'] = date('Y-m-d H:i:s', time());

                // 更新保障服务记录的统计信息。
                $this->dao->update($v, $item);
            }
        }
        return;
    }


}
