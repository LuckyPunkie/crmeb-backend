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

use app\common\dao\store\StoreSeckillTimeDao;
use app\common\repositories\BaseRepository;
use FormBuilder\Factory\Elm;
use think\facade\Route;

class StoreSeckillTimeRepository extends BaseRepository
{
    /**
     * @var StoreSeckillDao
     */
    protected $dao;

    /**
     * StoreSeckillTimeRepository constructor.
     * @param StoreSeckillDao $dao
     */
    public function __construct(StoreSeckillTimeDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 根据条件获取列表数据
     *
     * 本函数用于根据给定的条件数组 $where，从数据库中检索数据列表。
     * 它支持分页查询，每页的数据数量由 $limit 参数指定，查询的页码由 $page 参数指定。
     * 函数返回一个包含两个元素的数组，第一个元素是数据总数 $count，第二个元素是当前页的数据列表 $list。
     *
     * @param array $where 查询条件数组
     * @param int $page 查询的页码
     * @param int $limit 每页的数据数量
     * @return array 返回包含 'count' 和 'list' 两个元素的数组
     */
    public function getList(array $where, int $page, int $limit)
    {
        // 根据条件查询数据
        $query = $this->dao->search($where);

        // 统计满足条件的数据总数
        $count = $query->count();

        // 获取当前页的数据列表
        $list = $query->page($page, $limit)->select();

        // 返回数据总数和当前页的数据列表
        return compact('count', 'list');
    }

    /**
     * 根据活动ID选择秒杀活动商品列表
     *
     * @param int $activeId 秒杀活动的ID
     * @return array 秒杀活动商品列表
     *
     * 此方法用于根据提供的活动ID来获取相应的秒杀活动商品列表。
     * 如果未提供活动ID，则获取所有处于激活状态的秒杀活动商品。
     * 如果提供了活动ID且该活动有效，那么将获取该活动关联的所有商品。
     */
    public function select($activeId)
    {
        $list = [];
        // 当未指定活动ID时，查询所有处于激活状态的秒杀活动商品
        if(!$activeId){
            $query = $this->dao->search(['status' => 1]);
            $list = $query->select();
        }else{
            // 指定了活动ID时，获取该秒杀活动的信息
            $seckillActive = app()->make(StoreSeckillActiveRepository::class)->get($activeId);
            // 如果秒杀活动存在且设置了秒杀时间ID，查询该秒杀活动的商品列表
            if($seckillActive && isset($seckillActive['seckill_time_ids'])){
                $list = $this->dao->getSearch([])->whereIn('seckill_time_id',$seckillActive['seckill_time_ids'])->select();
            }
        }

        return $list;
    }

    /**
     * 创建或编辑秒杀配置表单
     *
     * 本函数用于生成一个包含各种输入字段的表单，用于创建或编辑秒杀活动的配置信息。
     * 表单字段包括标题、开始时间、结束时间、状态和图片等。根据传入的$id$参数决定是创建新记录还是编辑已有的记录。
     *
     * @param int|null $id 秒杀配置的ID，如果为null，则表示创建新记录；否则，表示编辑已有的记录。
     * @param array $formData 表单的初始数据，用于填充表单字段的值。
     * @return \FormBuilder\Form|\think\form\Form
     */
    public function form(?int $id = null ,array $formData = [])
    {
        // 根据$id$的值决定表单的提交URL，如果是新建，则提交到创建URL；如果是编辑，则提交到更新URL。
        $form = Elm::createForm(is_null($id) ? Route::buildUrl('systemSeckillConfigCreate')->build() : Route::buildUrl('systemSeckillConfigUpdate', ['id' => $id])->build());

        // 设置表单的验证规则，包括各种输入字段的类型、必填项、占位符等。
        $form->setRule([
            // 设置标题输入字段的规则。
            Elm::input('title','场次名称：')->placeholder('请输入场次名称')->required('请输入场次名称'),
            // 设置开始时间选择字段的规则。
            Elm::select('start_time','开始时间：')->placeholder('请选择')->options($this->dao->getTime(1))->requiredNum('请选择开始时间'),
            // 设置结束时间选择字段的规则。
            Elm::select('end_time','结束时间：')->placeholder('请选择')->options($this->dao->getTime(0))->requiredNum('请选择结束时间'),
            // 设置状态开关字段的规则。
            Elm::switches('status','是否启用：')->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->className('switch-width-double')->activeText('开启'),
            // 设置图片上传字段的规则，包括图片预览、上传配置等。
            Elm::frameImage('pic', '图片：', '/' . config('admin.admin_prefix') . '/setting/uploadPicture?field=pic&type=1')->width('1000px')->height('600px')->spin(0)->icon('el-icon-camera')->modal(['modal' => false])->props(['footer' => false])->appendRule('suffix', [
                'type' => 'guidancePop',
                'props' => [
                    'info' => '此图片将展示在移动端秒杀商品列表上方，(建议尺寸：710*300px)',
                ]
            ]),
        ]);

        // 设置表单的标题和初始数据，然后返回表单对象。
        return $form->setTitle(is_null($id) ? '添加'  : '编辑')->formData($formData);
    }


    /**
     * 更新表单数据
     * 该方法用于根据给定的ID获取数据库中的记录，并使用这些数据来构建一个表单。这在更新现有记录的信息时非常有用。
     * @param int $id 表单记录的唯一标识符。这个ID用于从数据库中检索特定的记录。
     * @return mixed 返回一个表单实例，该实例使用从数据库中检索到的数据进行了填充。这个表单可以用于显示当前数据或进行数据更新操作。
     */
    public function updateForm($id)
    {
        // 根据$id获取数据库中的记录，并转换为数组格式，然后使用这个数据来创建一个新的表单实例
        return $this->form($id,$this->dao->get($id)->toArray());
    }


    /**
     *  所选时间段是否重叠
     * @param $where
     * @return bool
     * @author Qinii
     * @day 2020-07-31
     */
    public function checkTime(array $where,?int $id)
    {
        if(!$this->dao->valStartTime($where['start_time'],$id) && !$this->dao->valEndTime($where['end_time'],$id) && !$this->dao->valAllTime($where,$id)) return true;
        return false;
    }

    /**
     *  APi秒杀时间列表
     * @return array
     * @author Qinii
     * @day 2020-08-11
     */
    public function selectTime()
    {
        $seckillTimeIndex = 0;
        $_h = date('H',time());
        $query = $this->dao->search(['status' => 1]);
        $list = $query->select();
        $seckillEndTime = time();
        $seckillTime = [];
        foreach($list as $k => $item){
            $item['stop'] = strtotime((date('Y-m-d ',time()).$item['end_time'].':00:00'));
            if($item['end_time'] <= $_h) {
                $item['pc_status'] = 0;
                $item['state'] = '已结束';
            }
            if($item['start_time'] > $_h ) {
                $item['pc_status'] = 2;
                $item['state'] = '待开始';
            }
            if($item['start_time'] <= $_h && $_h < $item['end_time']){
                $item['pc_status'] = 1;
                $item['state'] = '抢购中';
                $seckillTimeIndex = $k;
                $seckillEndTime = strtotime((date('Y-m-d ',time()).$item['end_time'].':00:00'));
                $item['stop_time'] = date('Y-m-d H:i:s', $seckillEndTime);
            }
            $seckillTime[$k] = $item;
        }
        return  compact('seckillTime','seckillTimeIndex','seckillEndTime');
    }

    /**
     *  获取某个时间是否有开启秒杀活动
     * @param array $where
     * @return mixed
     * @author Qinii
     * @day 2020-08-19
     */
    public function getBginTime(array $where)
    {
        if(empty($where) || ($where['start_time'] == '' || $where['end_time'] == '')){
            $where['start_time'] = date('H',time());
            $where['end_time'] = date('H',time()) + 1;
        }
        $where['status'] = 1;
        return $this->dao->search($where)->find();
    }
}
