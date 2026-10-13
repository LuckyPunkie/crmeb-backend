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


namespace app\common\repositories\store\broadcast;

use app\common\dao\store\broadcast\BroadcastAssistantDao;
use app\common\repositories\BaseRepository;
use FormBuilder\Exception\FormBuilderException;
use think\facade\Route;
use FormBuilder\Factory\Elm;
use FormBuilder\Form;

class BroadcastAssistantRepository extends BaseRepository
{
    /**
     * @var BroadcastAssistantDao
     */
    protected $dao;

    public function __construct(BroadcastAssistantDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 创建或编辑小助手账号的表单
     *
     * 本函数用于生成一个表单，根据$id$的存在与否决定是创建新的小助手账号还是编辑已有的账号。
     * 如果$id$存在，则表单将用于编辑已有账号，否则用于创建新账号。
     *
     * @param int|null $id 小助手账号的ID，如果为null，则表示创建新账号。
     * @return \EasyWeChat\OfficialAccount\Forms\Form 创建或编辑小助手账号的表单。
     * @throws FormBuilderException 如果指定的账号ID不存在，则抛出异常。
     */
    public function form(?int $id)
    {
        $formData = [];
        // 根据$id$的存在与否决定是创建还是编辑表单
        if ($id) {
            // 编辑模式：构建表单URL并获取指定ID的账号数据
            $form = Elm::createForm(Route::buildUrl('merchantBroadcastAssistantUpdate', ['id' => $id])->build());
            $data = $this->dao->get($id);
            // 检查数据是否存在，如果不存在则抛出异常
            if (!$data) throw new FormBuilderException('数据不存在');
            // 将账号数据转换为数组并存储为表单数据
            $formData = $data->toArray();

        } else {
            // 创建模式：构建表单URL
            $form = Elm::createForm(Route::buildUrl('merchantBroadcastAssistantCreate')->build());
        }

        // 定义表单的验证规则
        $rules = [
            Elm::input('username', '微信号：')->placeholder('请输入微信号')->required('请输入微信号'),
            Elm::input('nickname', '微信昵称：')->placeholder('请输入微信昵称')->required('请输入微信昵称'),
            Elm::input('mark', '备注：')->placeholder('请输入备注'),
        ];
        // 设置表单的验证规则
        $form->setRule($rules);
        // 根据$id$的存在与否设置表单标题，并设置表单的初始数据
        return $form->setTitle(is_null($id) ? '添加小助手账号' : '编辑小助手账号')->formData($formData);
    }

    /**
     * 根据条件获取列表数据
     *
     * 本函数用于根据给定的条件查询数据库，并返回满足条件的数据列表以及总条数。
     * 这对于分页查询非常有用，可以一次性获取当前页的数据以及数据的总页数。
     *
     * @param string $where 查询条件，用于限定查询的数据范围。
     * @param int $page 当前页码，用于指定要返回的页的数据。
     * @param int $limit 每页的数据条数，用于控制每页显示的数据数量。
     * @return array 返回包含 'count' 和 'list' 两个元素的数组，'count' 表示满足条件的总数据条数，'list' 表示当前页的数据列表。
     */
    public function getList($where, int $page,  int $limit)
    {
        // 根据条件获取查询对象
        $query = $this->dao->getSearch($where);

        // 计算满足条件的数据总条数
        $count =  $query->count('*');

        // 获取当前页的数据列表
        $list = $query->page($page, $limit)->select();

        // 返回包含数据总条数和当前页数据列表的数组
        return compact('count','list');
    }

    /**
     * 根据商家ID获取搜索相关的助手昵称和ID
     *
     * 本函数旨在为特定商家ID检索并返回搜索助手的昵称和ID列表。这有助于商家在搜索功能中识别和选择合适的助手。
     *
     * @param int $merId 商家ID，用于限定搜索范围，指定要获取助手信息的商家。
     * @return array 返回一个数组，其中包含助手的昵称和ID，格式为['nickname' => '助手昵称', 'assistant_id' => '助手ID']。
     */
    public function options(int $merId)
    {
        // 通过商家ID查询搜索助手的信息，并仅返回昵称和ID列
        return $this->dao->getSearch(['mer_id' => $merId])->column('nickname','assistant_id');
    }


}
