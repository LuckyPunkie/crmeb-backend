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

use app\common\dao\store\StoreActivityDao;
use app\common\dao\store\StoreActivityRelatedDao;
use app\common\repositories\BaseRepository;
use app\common\repositories\system\form\FormRepository;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * @mixin StoreActivityRelatedDao
 */
class StoreActivityRelatedRepository extends BaseRepository
{
    const ACTIVITY_TYPE_FORM = 1;

    /**
     * @var StoreActivityRelatedDao
     */
    protected $dao;

    /**
     * StoreActivityDao constructor.
     * @param StoreActivityRelatedDao $dao
     */
    public function __construct(StoreActivityRelatedDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取活动列表
     * 根据给定的条件和分页信息，从数据库中检索活动列表。
     * 这个方法封装了查询逻辑，包括计算总数和获取特定页面的列表项。
     *
     * @param string $where 查询条件，用于筛选活动。
     * @param int $page 当前的页码。
     * @param int $limit 每页显示的活动数量。
     * @return array 返回包含总数和活动列表的数组。
     */
    public function getList($where, $page, $limit)
    {
        // 构建查询，包括关联查询和选择字段。
        $query = $this->dao->search($where)->with([
            'activity' => function ($query) {
                // 对活动查询进行进一步限制，只选择特定的字段，并添加计算字段"time_status"。
                $query->field('activity_id,activity_name,status,activity_type,pic,start_time,end_time')->append(['time_status'], true);
            }
        ]);

        // 计算满足条件的活动总数。
        $count = $query->count();

        // 根据当前页码和每页的限制，获取活动列表。
        // 并按照"id"和"create_time"降序排序。
        $list = $query->page($page, $limit)->order('id DESC,create_time DESC')->select();

        // 返回包含总数和活动列表的数组。
        return compact('count', 'list');
    }


    /**
     * 根据ID和用户ID显示信息
     *
     * 此方法用于根据提供的ID和用户ID检索特定的数据，并确保数据及其相关活动存在。
     * 如果数据或活动不存在，将抛出一个验证异常。
     *
     * @param int $id 数据的唯一标识ID
     * @param int $uid 用户的唯一标识ID
     * @return array 包含数据和活动信息的数组
     * @throws ValidateException 如果数据或活动不存在，则抛出此异常
     */
    public function show($id, $uid)
    {
        // 定义查询条件
        $where['id'] = $id;
        $where['uid'] = $uid;

        // 查询数据及其关联的活动信息，附加时间状态信息
        $data = $this->dao->getSearch($where)
            ->with([
                'activity' => function ($query) {
                    $query->append(['time_status'], true);
                }
            ])
            ->find();

        // 如果数据不存在，则抛出异常
        if (!$data) throw new ValidateException('数据不存在');

        // 从查询结果中提取活动信息
        $form = $data['activity'];

        // 如果活动信息不存在或用户无权查看，则抛出异常
        if (!$form) throw new ValidateException('活动不存在或无法查看');

        // 返回包含数据和活动信息的数组
        return compact('data');
    }

    /**
     *  保存提交信息，增加已提交数量
     * @param array $data
     * @return mixed
     * @author Qinii
     * @day 2023/11/22
     */
    public function save(int $activity_id, array $data)
    {
        return Db::transaction(function () use ($activity_id, $data) {
            $this->dao->create($data);
            app()->make(StoreActivityRepository::class)->incTotal($activity_id);
        });
    }
}
