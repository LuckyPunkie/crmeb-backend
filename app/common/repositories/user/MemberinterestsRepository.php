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


use app\common\dao\user\MemberInterestsDao;
use app\common\dao\user\UserBrokerageDao;
use app\common\repositories\BaseRepository;
use FormBuilder\Factory\Elm;
use think\exception\ValidateException;
use think\facade\Route;

/**
 * @mixin UserBrokerageDao
 */
class MemberinterestsRepository extends BaseRepository
{

    const TYPE_FREE = 1;
    //付费会员
    const TYPE_SVIP = 2;

    const HAS_TYPE_PRICE = 1;

    const HAS_TYPE_SIGN = 2;

    const HAS_TYPE_PAY = 3;

    const HAS_TYPE_SERVICE = 4;

    const HAS_TYPE_MEMBER = 5;

    const HAS_TYPE_COUPON = 6;

    //签到收益
    const INTERESTS_TYPE = [
        1 => ['label'=> '会员特价', 'msg' => ''],
        2 => ['label'=> '签到返利' , 'msg' => '积分倍数' ],
        3 => ['label'=> '消费返利' , 'msg' => '积分倍数' ],
        4 => ['label'=> '专属客服' , 'msg' => '' ],
        5 => ['label'=> '经验翻倍' , 'msg' => '经验翻倍' ],
        6 => ['label'=> '会员优惠券', 'msg' => ''],
    ];

    public function __construct(MemberInterestsDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 根据条件获取列表数据
     *
     * 本函数用于根据给定的条件数组和分页信息，从数据库中检索并返回列表数据。
     * 它首先构造一个查询，然后获取符合条件的数据总数，最后根据页码和每页的限制数量，
     * 获取对应页的数据列表。这个函数对于实现分页查询非常有用。
     *
     * @param array $where 查询条件数组，包含需要匹配的字段及其值。
     * @param int $page 当前页码，用于计算查询的起始位置。
     * @param int $limit 每页显示的数据条数，用于限制查询的结果数量。
     * @return array 返回一个包含 'count' 和 'list' 两个元素的数组，'count' 表示总数据量，'list' 表示当前页的数据列表。
     */
    public function getList(array $where, int $page, int $limit)
    {
        // 根据传入的条件数组构造查询
        $query = $this->dao->getSearch($where);

        // 计算符合条件的数据总数
        $count = $query->count();

        // 根据当前页码和每页限制的数量，从查询中获取数据列表
        $list = $query->page($page, $limit)->select();

        // 将数据总数和数据列表一起返回
        return compact('count', 'list');
    }

    /**
     * 获取VIP兴趣值
     *
     * 本函数用于查询并返回满足特定条件的VIP兴趣值。兴趣值由数据表中的'value'字段提供。
     * 如果查询结果不存在，则默认返回0。
     *
     * @param int $has_type 查询条件之一，用于筛选具有特定类型的记录。
     * @return float 返回查询到的最大兴趣值，如果不存在则为0。
     */
    public function getSvipInterestVal($has_type)
    {
        // 根据条件查询数据表中状态为1，类型为2，且has_type符合指定条件的记录的最大value值
        // 如果查询结果不存在，则默认返回0
        return max(((float)$this->dao->query(['status' => 1])->where('has_type', $has_type)->where('type', 2)->value('value')) ?: 0, 0);
    }

    /**
     * 创建或编辑会员权益表单
     *
     * @param int|null $id 权益ID，如果提供，则为编辑模式；否则为新增模式
     * @param string $type 权益类型，定义了权益的分类
     * @return \EasyWeChat\MiniProgram\Forms\Form 创建或编辑权益的表单对象
     *
     * 此方法根据$id的存在与否决定是创建新的会员权益还是编辑已有的权益。
     * 它使用了EasyWeChat的表单构建工具来简化表单的创建过程，并且动态地根据数据库中的数据来填充表单，
     * 以便用户可以方便地编辑或创建新的会员权益。
     */
    public function form(?int $id = null, $type = self::TYPE_FREE)
    {
        $formData = [];
        // 根据$id来决定是编辑模式还是新增模式
        if ($id) {
            // 在编辑模式下，尝试获取指定ID的权益数据
            $data = $this->dao->get($id);
            // 如果数据不存在，则抛出异常
            if (!$data) throw new ValidateException('数据不存在');
            // 构建表单的提交URL，用于更新权益信息
            $form = Elm::createForm(Route::buildUrl('systemUserMemberInterestsUpdate', ['id' => $id])->build());
            // 将获取到的权益数据转换为数组，并存储到formData中
            $formData = $data->toArray();
        } else {
            // 在新增模式下，构建表单的提交URL，用于创建新的权益信息
            $form = Elm::createForm(Route::buildUrl('systemUserMemberInterestsCreate')->build());
        }
        // 定义表单的验证规则和字段
        $rules = [
            // 创建名称输入字段，并设置必要的验证规则
            Elm::input('name', '权益名称：')->placeholder('请输入权益名称')->required('请输入权益名称'),
            // 创建简介输入字段，并设置必要的验证规则
            Elm::input('info', '权益简介：')->placeholder('请输入权益简介')->required('请输入权益简介'),
            // 创建图标选择字段，使用框架内置的图片上传组件，并设置初始值和相关样式
            Elm::frameImage('pic', '图标：', '/' . config('admin.admin_prefix') . '/setting/uploadPicture?field=pic&type=1')
                ->value($formData['pic'] ?? '')
                ->modal(['modal' => false])
                ->icon('el-icon-camera')
                ->width('1000px')
                ->height('600px'),
            // 创建会员级别选择字段，并动态地从数据库加载可选值
            Elm::select('brokerage_level', '会员级别：')->options(function () use($type){
                $options = app()->make(UserBrokerageRepository::class)->options(['type' => $type])->toArray();
                return $options;
            })->placeholder('请选择会员级别'),
        ];
        // 将定义的规则应用到表单中
        $form->setRule($rules);
        // 设置表单标题，并根据$id的存在与否决定标题文本
        return $form->setTitle(is_null($id) ? '添加权益' : '编辑权益')->formData($formData);
    }

    /**
     * 根据类型和级别获取兴趣点列表
     *
     * 本函数旨在根据提供的类型和级别参数，从数据库中检索兴趣点（或称兴趣列表）。
     * 具体来说，它将根据类型参数的不同，返回全部兴趣点或仅返回已启用的兴趣点。
     * 当类型为免费类型时，会忽略级别参数；否则，仅返回级别大于等于提供的级别参数的兴趣点。
     *
     * @param int $type 兴趣点的类型，用于筛选兴趣点列表。可能的值包括免费类型和其他类型。
     * @param int $level 兴趣点的级别，用于进一步筛选兴趣点列表。仅当类型不为免费类型时生效。
     * @return array 返回符合条件的兴趣点列表，每个兴趣点包含状态和其他相关信息。
     */
    public function getInterestsByLevel(int $type, $level = 0)
    {
        // 当类型为免费类型时
        if ($type == self::TYPE_FREE) {
            // 获取所有免费类型的兴趣点
            $list = $this->dao->getSearch(['type' => $type])->select();
            // 遍历列表，设置默认状态为0（未启用）
            foreach ($list as $item) {
                $item['status'] = 0;
                // 如果兴趣点的级别小于等于提供的级别，则将其状态设置为1（启用）
                if ($item['brokerage_level'] <= $level) {
                    $item['status'] = 1;
                }
            }
        } else {
            // 当类型非免费类型时，获取已启用的兴趣点
            $list = $this->dao->getSearch(['type' => $type,'status' => 1])->select();
        }
        // 返回符合条件的兴趣点列表
        return $list;
    }

    /**
     * 创建SVIP会员权益表单
     * 该方法用于生成编辑会员权益的表单界面，通过传入会员权益ID，获取相应的数据，并构建相应的表单字段。
     * @param int $id 会员权益ID，用于查询特定的会员权益数据。
     * @return mixed 返回生成的表单对象，用于在前端展示编辑表单。
     * @throws ValidateException 如果查询的数据不存在，则抛出验证异常。
     */
    public function svipForm(int $id)
    {
        // 根据ID查询会员权益数据
        $data = $this->dao->get($id);
        // 如果数据不存在，则抛出异常
        if (!$data) throw new ValidateException('数据不存在');

        // 创建表单对象，并设置表单提交的URL
        $form = Elm::createForm(Route::buildUrl('systemUserSvipInterestsUpdate', ['id' => $id])->build());

        // 将查询到的数据转换为数组格式，方便后续表单数据的填充
        $formData = $data->toArray();

        // 定义表单规则，包括各个字段的生成及其配置
        $rules = [
            // 生成权益类型选择字段，为下拉列表形式，选项固定，且不可更改
            Elm::select('has_type', '权益名称：')->options(function(){
                foreach (self::INTERESTS_TYPE as $k => $v) {
                    $res[] = ['value' => $k, 'label' => $v['label']];
                }
                return $res;
            })->disabled(true),
            // 生成展示名称输入字段，必填
            Elm::input('name', '展示名称：')->required('请输入展示名称'),
            // 生成权益简介输入字段，必填
            Elm::input('info', '权益简介：')->required('请输入权益简介'),
            // 生成未开通图标上传框架，使用iframe嵌入式上传，必填
            Elm::frameImage('pic', '未开通图标：', '/' . config('admin.admin_prefix') . '/setting/uploadPicture?field=pic&type=1')
                ->value($formData['pic'] ?? '')->required('请上传未开通图标')
                ->modal(['modal' => false])
                ->icon('el-icon-camera')
                ->width('1000px')
                ->height('600px'),
            // 生成已开通图标上传框架，使用iframe嵌入式上传，必填
            Elm::frameImage('on_pic', '已开通图标：', '/' . config('admin.admin_prefix') . '/setting/uploadPicture?field=on_pic&type=1')
                ->value($formData['on_pic'] ?? '')->required('请上传已开通图标')
                ->modal(['modal' => false])
                ->icon('el-icon-camera')
                ->width('1000px')
                ->height('600px'),
            // 生成跳转内部链接输入字段
            Elm::input('link', '跳转内部链接：'),
        ];

        // 根据权益类型，动态添加价值输入字段
        $msg = self::INTERESTS_TYPE[$formData['has_type']]['msg'];
        if ($msg) $rules[] = Elm::number('value',$msg,0);

        // 设置表单规则
        $form->setRule($rules);

        // 设置表单标题，并填充已有的表单数据
        return $form->setTitle('编辑会员权益')->formData($formData);
    }
}
