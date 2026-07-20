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


namespace app\common\repositories\user;


use app\common\dao\user\FeedbackDao;
use app\common\repositories\BaseRepository;
use FormBuilder\Factory\Elm;
use think\exception\ValidateException;
use think\facade\Route;

/**
 * Class FeedbackRepository
 * @package app\common\repositories\user
 * @author xaboy
 * @day 2020/5/28
 * @mixin FeedbackDao
 */
class FeedbackRepository extends BaseRepository
{
    /**
     * FeedbackRepository constructor.
     * @param FeedbackDao $dao
     */
    public function __construct(FeedbackDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 根据条件获取反馈列表
     *
     * 本函数用于根据给定的条件数组 $where，以及分页信息 $page 和每页的条数 $limit，
     * 从数据库中查询并返回反馈列表。列表包含反馈的数量 $count 和反馈项的详细信息 $list。
     *
     * @param array $where 查询条件数组
     * @param int $page 当前页码
     * @param int $limit 每页的条数
     * @return array 包含反馈数量和反馈列表的数组
     */
    public function getList(array $where, $page, $limit)
    {
        // 初始化查询
        $query = $this->dao->search($where)
            ->with(['type' => function($query) {
                // 关联查询反馈类型，只获取feedback_category_id和cate_name两个字段
                $query->field('feedback_category_id,cate_name');
            }]);

        // 计算反馈总数
        $count = $query->count();

        // 分页查询并处理反馈列表中的images字段
        $list = $query->page($page, $limit)
            ->withAttr('images', function($val) {
                // 如果images字段不为空，则将其解析为数组，否则返回空数组
                return $val ? json_decode($val, true) : [];
            })
            ->select();

        // 返回包含反馈总数和反馈列表的数组
        return compact('count', 'list');
    }

    /**
     * 根据ID获取反馈信息详情
     *
     * 本函数通过ID检索反馈信息的具体数据，并进一步获取该反馈类型及其父类型的名称，
     * 以便在返回的数据中以更直观的方式显示反馈的分类信息。
     *
     * @param int $id 反馈信息的唯一标识ID
     * @return array 包含反馈信息详情及类型和父类型名称的数据数组
     */
    public function get($id)
    {
        // 通过ID获取反馈信息的基本数据
        $data = $this->dao->getWhere([$this->dao->getPk() => $id]);

        // 获取反馈信息所属类型的名称
        $type = app()->make(FeedBackCategoryRepository::class)->getWhere(['feedback_category_id' => $data['type']]);

        // 获取反馈信息所属类型的父亲类型的名称
        if($type) {
            $parent = app()->make(FeedBackCategoryRepository::class)->getWhere(['feedback_category_id' => $type['pid']]);
        }

        // 将类型和父类型的名称替换原有的ID值，以提供更直观的类型信息
        $data['type'] = $type['cate_name'] ?? '';
        $data['category'] = $parent['cate_name'] ?? '';

        return $data;
    }


    /**
     * 准备回复表单的数据和结构。
     *
     * 此方法用于生成回复用户反馈的表单，通过给定的反馈ID获取反馈数据，并构建相应的表单用于回复。
     * 如果反馈数据不存在或该反馈已经回复过，则抛出异常。
     *
     * @param int $id 反馈数据的ID。
     * @return Form|\FormBuilder\Form
     *@throws ValidateException 如果反馈数据不存在或已回复，则抛出异常。
     */
    public function replyForm($id)
    {
        // 通过ID获取反馈数据
        $formData = $this->dao->get($id);
        // 检查反馈数据是否存在，如果不存在则抛出异常
        if (!$formData) throw new ValidateException('数据不存在');
        // 检查反馈状态，如果已回复则抛出异常
        if ($formData->status == 1) throw new ValidateException('该问题已回复过了');

        // 创建表单对象，并设置表单提交的URL
        $form = Elm::createForm(Route::buildUrl('systemUserFeedBackReply',['id' => $id])->build());
        // 设置表单的验证规则，包括一个文本区域用于输入回复内容
        $form->setRule([
            Elm::textarea('reply', '回复内容：')->placeholder('请输入回复内容')->required('请输入回复内容'),
        ]);

        // 设置表单标题，并加载反馈数据作为表单的默认数据
        return $form->setTitle('回复用户')->formData($formData->toArray());
    }

}
