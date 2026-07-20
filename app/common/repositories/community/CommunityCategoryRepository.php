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

namespace app\common\repositories\community;

use app\common\dao\community\CommunityCategoryDao;
use app\common\repositories\BaseRepository;
use app\controller\api\community\CommunityTopic;

use FormBuilder\Factory\Elm;
use think\exception\ValidateException;
use think\facade\Route;

/**
 * 社区分类
 */
class CommunityCategoryRepository extends BaseRepository
{
    /**
     * @var CommunityCategoryDao
     */
    protected $dao;

    /**
     * CommunityCategoryRepository constructor.
     * @param CommunityCategoryDao $dao
     */
    public function __construct(CommunityCategoryDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 创建/编辑表单
     * @param int|null $id
     * @return \FormBuilder\Form
     * @author Qinii
     */
    public function form(?int $id)
    {
        $formData = [];
        if (!$id) {
            $form = Elm::createForm(Route::buildUrl('systemCommunityCategoryCreate')->build());
        } else {
            $formData = $this->dao->get($id)->toArray();
            $form = Elm::createForm(Route::buildUrl('systemCommunityCategoryUpdate', ['id' => $id])->build());
        }

        $form->setRule([
            Elm::input('cate_name', '分类名称：')->placeholder('请输入分类名称')->required('请输入分类名称'),
            Elm::switches('is_show', '是否显示：', 1)->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->className('switch-width-double')->activeText('开启'),
            Elm::number('sort', '排序：')->precision(0)->max(99999),
        ]);
        return $form->setTitle(is_null($id) ? '添加分类' : '编辑分类')->formData($formData);
    }

    /**
     * 删除特定ID的分类。
     *
     * 此方法用于删除一个分类，但在删除之前，它会检查该分类下是否还有未被删除的数据。
     * 如果存在未删除的数据，则抛出一个验证异常，防止数据丢失或错误的删除操作。
     * 这种做法保证了数据的完整性，并且提供了更安全的分类管理机制。
     *
     * @param int $id 分类的唯一标识ID。
     * @throws ValidateException 如果该分类下存在未删除的数据，则抛出此异常。
     */
    public function delete(int $id)
    {
        // 通过依赖注入的方式获取社区主题仓库实例
        $make = app()->make(CommunityTopicRepository::class);
        // 检查该分类下是否存在未删除的数据
        if ($make->getWhereCount(['category_id' => $id, 'is_del' => 0])) {
            throw new ValidateException('该分类下存在数据');
        }
        // 如果没有未删除的数据，则执行删除操作
        $this->dao->delete($id);
    }

    /**
     * 根据条件获取列表数据
     *
     * 本函数用于根据给定的条件数组 $where，从数据库中检索满足条件的数据列表。
     * 它支持分页查询，每页的数据数量由 $limit 参数指定，查询结果将返回当前页的数据列表以及总数据量。
     *
     * @param array $where 查询条件数组，用于指定数据库查询的条件。
     * @param int $page 当前页码，用于指定要返回的数据页。
     * @param int $limit 每页的数据数量，用于指定每页返回的数据条数。
     * @return array 返回包含 'count' 和 'list' 两个元素的数组，'count' 表示总数据量，'list' 表示当前页的数据列表。
     */
    public function getList(array $where, int $page, int $limit)
    {
        // 根据条件构造查询语句，并按排序字段排序
        $query = $this->dao->getSearch($where)->order('sort DESC,category_id DESC');

        // 计算满足条件的总数据量
        $count = $query->count();

        // 执行分页查询，并返回当前页的数据列表
        $list = $query->page($page, $limit)->select();

        // 将总数据量和当前页的数据列表一起返回
        return compact('count','list');
    }

    /**
     * 下拉筛选
     * @return array
     * @author Qinii
     */
    public function options()
    {
        $data =  $this->dao->getSearch(['is_show' => 1])->order('sort DESC,category_id DESC')
            ->field('category_id as value,cate_name as label')->select();
        if ($data) $res = $data->toArray();
        return $res;
    }

    /**
     *  所有列表
     * @return array
     * @author Qinii
     */
    public function getApiList()
    {
        $res = $this->dao->getSearch(['is_show' => 1])->order('sort DESC')
            ->setOption('filed',[])->field('category_id,cate_name')->with(['children'])->order('sort DESC,category_id DESC')->select();
        $list = [];
        if ($res) $list = $res->toArray();
//        $hot = app()->make(CommunityTopicRepository::class)->getHotList();
//        $data[] = [
//            'category_id' => 0,
//            "cate_name" => "推荐",
//            "children"  => $hot['list']
//        ];
//        return array_merge($data,$list);
        return $list;
    }


    /**
     * 清除移动的缓存 - 已弃用
     * @return void
     * @author Qinii
     */
    public function clearCahe()
    {
        // CacheService::clearByTag(0, CacheService::TAG_TOPIC);
    }
}

