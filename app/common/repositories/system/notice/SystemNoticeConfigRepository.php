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


namespace app\common\repositories\system\notice;


use app\common\dao\system\notice\SystemNoticeConfigDao;
use app\common\repositories\BaseRepository;
use crmeb\exceptions\WechatException;
use FormBuilder\Factory\Elm;
use think\exception\ValidateException;
use think\facade\Route;

/**
 * 通知管理
 */
class SystemNoticeConfigRepository extends BaseRepository
{
    public function __construct(SystemNoticeConfigDao $dao)
    {
        $this->dao = $dao;
    }


    /**
     * 根据条件获取列表数据
     *
     * 本函数用于根据给定的条件数组和分页信息，从数据库中检索满足条件的数据列表。
     * 它首先构造一个查询，然后计算满足条件的数据总数，最后根据分页信息和排序条件获取具体的数据。
     *
     * @param array $where 查询条件数组，包含一个或多个条件键值对。
     * @param int $page 当前页码，用于分页查询。
     * @param int $limit 每页的数据条数，用于分页查询。
     * @return array 返回一个包含 'count' 和 'list' 两个元素的数组，'count' 为满足条件的数据总数，'list' 为当前页码的数据列表。
     */
    public function getList(array $where, $page, $limit)
    {
        // 根据条件数组构造查询
        $query = $this->dao->getSearch($where);

        // 计算满足条件的数据总数
        $count = $query->count();

        // 根据当前页码和每页数据条数进行分页查询，并按创建时间升序排序
        $list = $query->page($page, $limit)->order('create_time ASC')->select();

        // 返回包含数据总数和数据列表的数组
        return compact('count', 'list');
    }

    /**
     * 创建或编辑通知配置的表单
     *
     * 该方法用于生成一个包含通知配置信息的表单，根据$id$的存在与否决定是创建新的通知配置还是编辑已有的通知配置。
     * 表单中包括了各种通知方式（站内消息、公众号模板消息、小程序订阅消息、短信消息）的开关控制，以及通知的标题、说明、关键字等信息的输入字段。
     * 通知类型（用户或商户）的选择也被包含在表单中，此外，针对短信、公众号模板和小程序订阅消息，提供了内容输入的文本区域。
     *
     * @param int|null $id 通知配置的ID，如果为NULL，则表示创建新的通知配置；否则，表示编辑已有的通知配置。
     * @return \think\form\Form 返回一个包含通知配置表单的实例。
     * @throws ValidateException 如果根据$id$查询不到已有的通知配置，则抛出验证异常。
     */
    public function form(?int $id)
    {
        // 初始化表单数据数组
        $formData = [];
        // 如果$id$存在
        if ($id) {
            // 通过$id$查询通知配置数据
            $data = $this->dao->get($id);
            // 如果查询不到数据，抛出异常
            if (!$data) throw new ValidateException('数据不存在');
            // 将查询到的数据转换为数组，并赋值给formData
            $formData  = $data->toArray();
            // 创建编辑通知配置的表单，提交地址为更新通知配置的路由URL
            $form = Elm::createForm(Route::buildUrl('systemNoticeConfigUpdate', ['id' => $id])->build());
        } else {
            // 创建新建通知配置的表单，提交地址为创建通知配置的路由URL
            $form = Elm::createForm(Route::buildUrl('systemNoticeConfigCreate')->build());
        }

        // 设置表单的验证规则，包括各种输入字段及其验证要求
        $form->setRule([
            // 通知标题输入框
            Elm::input('notice_title', '通知名称：')->placeholder('请输入通知名称')->required('请输入通知名称'),
            // 通知说明输入框
            Elm::input('notice_info', '通知说明：')->placeholder('请输入通知说明')->required('请输入通知说明'),
            // 通知关键字输入框
            Elm::input('notice_key', '通知KEY：')->placeholder('请输入通知KEY')->required('请输入通知KEY'),
            // 站内消息开关选择器
            Elm::radio('notice_sys', '站内消息：', -1)->options([
                ['value' => 0, 'label' => '关闭'],
                ['value' => 1, 'label' => '开启'],
                ['value' => -1, 'label' => '无'],
            ])->requiredNum('请选择站内消息'),
            // 公众号模板消息开关选择器
            Elm::radio('notice_wechat', '公众号模板消息：', -1)->options([
                ['value' => 0, 'label' => '关闭'],
                ['value' => 1, 'label' => '开启'],
                ['value' => -1, 'label' => '无'],
            ])->requiredNum('请选择公众号模板消息'),
            // 小程序订阅消息开关选择器
            Elm::radio('notice_routine', '小程序订阅消息：', -1)->options([
                ['value' => 0, 'label' => '关闭'],
                ['value' => 1, 'label' => '开启'],
                ['value' => -1, 'label' => '无'],
            ])->requiredNum('请选择小程序订阅消息'),
            // 短信消息开关选择器
            Elm::radio('notice_sms', '短信消息：', -1)->options([
                ['value' => 0, 'label' => '关闭'],
                ['value' => 1, 'label' => '开启'],
                ['value' => -1, 'label' => '无'],
            ])->requiredNum('请选择短信消息'),
            // 通知类型选择器
            Elm::radio('type', '通知类型：', 0)->options([
                ['value' => 0, 'label' => '用户'],
                ['value' => 1, 'label' => '商户'],
            ])->requiredNum('请选择通知类型'),
            // 短信内容输入框
            Elm::textarea('sms_content','短信内容：')->placeholder('请输入短信内容'),
            // 公众号模板内容输入框
            Elm::textarea('wechat_content','公众号模板内容：')->placeholder('请输入公众号模板内容'),
            // 小程序订阅消息内容输入框
            Elm::textarea('routine_content','小程序订阅消息内容：')->placeholder('请输入小程序订阅消息内容'),
        ]);

        // 设置表单标题，根据$id$的存在与否决定是"添加通知"还是"编辑通知"
        // 并将之前准备的formData赋值给表单，用于预填充已有的通知配置数据
        return $form->setTitle(is_null($id) ? '添加通知' : '编辑通知')->formData($formData);
    }

    /**
     * 切换实体的状态。
     *
     * 该方法用于根据给定的ID和字段名，改变特定字段的状态。主要应用于需要动态更新数据状态的场景，
     * 比如启用或禁用某个功能、标记某个项为已读或未读等。
     *
     * @param int $id 主键ID，用于定位特定的实体。
     * @param string $filed 需要改变状态的字段名。
     * @param int $status 新的状态值。通常是一个表示状态的整数，比如0表示禁用，1表示启用。
     * @throws ValidateException 如果字段值为-1，则抛出异常，表示该消息无此通知类型。
     */
    public function swithStatus($id, $filed, $status)
    {
        // 通过主键ID获取实体数据。
        $data = $this->dao->get($id);

        // 检查字段值，如果为-1，则抛出异常，表示该消息无此通知类型。
        if ($data[$filed] == -1) throw  new ValidateException('该消息无此通知类型');

        // 更新字段状态为新的状态值。
        $data->$filed = $status;

        // 保存更新后的数据。
        $data->save();
    }

    /**
     * 根据键获取系统通知的状态
     *
     * 本函数通过与数据库交互，检索特定键对应的系统通知状态。
     * 它被设计为类的一部分，旨在提供关于系统通知状态的信息。
     *
     * @param string $key 键值，用于唯一标识通知状态。
     * @return mixed 返回与键关联的系统通知状态。具体数据类型取决于数据库返回的结果。
     */
    public function getNoticeSys($key)
    {
        // 通过调用DAO层的方法，查询数据库中键为$key的系统通知状态
        return $this->dao->getNoticeStatusByKey($key, 'notice_sys');
    }

    /**
     * 根据键获取通知短信的状态
     *
     * 本函数通过与数据访问对象（DAO）交互，来获取特定键所对应的通知短信状态。
     * 主要用于查询系统中配置的通知方式（如短信、邮件等）是否启用。
     *
     * @param string $key 键值，用于唯一标识特定的通知配置项。
     * @return bool 返回通知短信的状态，启用为true，禁用为false。
     */
    public function getNoticeSms($key)
    {
        // 通过DAO层方法查询并返回指定键对应的短信通知状态
        return $this->dao->getNoticeStatusByKey($key, 'notice_sms');
    }

    /**
     * 根据键值获取微信通知状态
     *
     * 本函数通过调用DAO层的方法，来获取特定键值对应的通知状态，特别针对微信通知。
     * 这里的键值可能是用户ID、订单ID等，用于唯一标识通知的接收方或相关对象。
     * 返回的通知状态可以是开启或关闭，具体取决于数据库中的设置。
     *
     * @param string $key 键值，用于查找通知状态
     * @return bool|null 返回对应键值的微信通知状态，true表示开启，false表示关闭，null表示查询失败或无数据
     */
    public function getNoticeWechat($key)
    {
        // 调用DAO方法，查询并返回键值为$key的微信通知状态
        return $this->dao->getNoticeStatusByKey($key, 'notice_wechat');
    }

    /**
     * 根据键值获取推送通知的常规状态
     *
     * 本函数旨在通过指定的键值查询数据库，以获取特定通知的常规状态。
     * 这里的“常规状态”可能指的是通知的可见性、有效性或其他与通知常规属性相关的状态。
     *
     * @param string $key 键值，用于在数据库中唯一标识通知记录。
     * @return mixed 返回查询结果，可能是通知的状态值，具体取决于数据库查询的结果。
     */
    public function getNoticeRoutine($key)
    {
        // 通过键值和类型查询通知状态，这里的类型固定为'notice_routine'
        return $this->dao->getNoticeStatusByKey($key, 'notice_routine');
    }

    /**
     * 根据键获取短信模板ID。
     *
     * 本函数用于根据给定的键值查询短信模板。如果模板存在且启用了短信通知，
     * 则根据系统配置的短信使用类型返回相应的模板ID：如果使用阿里云短信服务，
     * 则返回阿里云短信模板ID；否则返回默认短信模板ID。
     *
     * @param string $key 模板的唯一标识键。
     * @return string 返回查询到的短信模板ID，如果未找到或未启用短信通知，则返回空字符串。
     */
    public function getSmsTemplate(string $key)
    {
        // 根据键值查询模板信息
        $temp = $this->dao->getWhere(['const_key' => $key]);
        // 检查模板是否存在且启用了短信通知
        if ($temp && $temp['notice_sms'] == 1) {
            // 根据系统配置的短信使用类型返回相应的模板ID
            return systemConfig('sms_use_type') == 2 ? $temp['sms_ali_tempid'] : $temp['sms_tempid'];
        }
        // 如果模板不存在或未启用短信通知，返回空字符串
        return '';
    }

    /**
     * 编辑消息模板ID
     * @param $id
     * @return \FormBuilder\Form
     * @author Qinii
     * @day 6/9/22
     */
    public function changeForm($id)
    {
        $formData = $this->dao->get($id);
        if (!$formData) throw new ValidateException('数据不存在');
        $form = Elm::createForm(Route::buildUrl('systemNoticeConfigSetChangeTempId', ['id' => $id])->build());
        $children = [];
        $value = '';
        if ($formData->notice_sms != -1) {
            $value = 'sms';
            if (systemConfig('sms_use_type') == 2) {
                $sms = [
                    'type' => 'el-tab-pane',
                    'props' => [
                        'label' => '阿里云短信',
                        'name' => 'sms'
                    ],
                    'children' =>[
                        Elm::input('title','通知类型：', $formData->notice_title)->placeholder('请输入通知类型')->disabled(true),
                        Elm::input('info','场景说明：', $formData->notice_info)->disabled(true)->placeholder('请输入场景说明'),
                        Elm::input('sms_ali_tempid','短信模板ID：')->placeholder('请输入短信模板ID'),
                        Elm::input('notice_info','短信说明：')->disabled(true)->placeholder('请输入短信说明'),
                        Elm::textarea('sms_content','短信内容：')->disabled(true),
                        Elm::switches('notice_sms', '是否开启：', 1)->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->className('switch-width-double')->activeText('开启'),
                    ]
                ];
            } else {
                $sms = [
                    'type' => 'el-tab-pane',
                    'props' => [
                        'label' => '一号通短信',
                        'name' => 'sms'
                    ],
                    'children' =>[
                        Elm::input('title','通知类型：', $formData->notice_title)->disabled(true),
                        Elm::input('info','场景说明：', $formData->notice_info)->disabled(true),
                        Elm::input('sms_tempid','短信模板ID：'),
                        Elm::input('notice_info','短信说明：')->disabled(true),
                        Elm::textarea('sms_content','短信内容：')->disabled(true),
                        Elm::switches('notice_sms', '是否开启：', 1)->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->className('switch-width-double')->activeText('开启'),
                    ]
                ];
            }
            $children[] = $sms;
        }
        if ($formData->notice_wechat != -1 ) {
            if (!$value)  $value = 'wechat';
            $children[] = [
                'type' => 'el-tab-pane',
                'props' => [
                    'label' => '模板消息',
                    'name' => 'wechat'
                ],
                'children' =>[
                    Elm::input('title1','通知类型：', $formData->notice_title)->disabled(true),
                    Elm::input('info1','场景说明：', $formData->notice_info)->disabled(true),
                    Elm::input('wechat_tempkey','模板消息编号：', $formData->wechat_tempkey)->disabled(true),
                    Elm::input('wechat_tempid','模板消息ID：', $formData->wechat_tempid),
                    Elm::textarea('wechat_content','模板消息内容：', $formData->wechat_content)->disabled(true),
                    Elm::switches('notice_wechat', '是否开启：', 1)->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->className('switch-width-double')->activeText('开启'),
                ]
            ];
        }
        if ($formData->notice_routine != -1) {
            if (!$value)  $value = 'routine';
            $children[] = [
                'type' => 'el-tab-pane',
                'props' => [
                    'label' => '订阅消息',
                    'name' => 'routine'
                ],
                'children' =>[
                    Elm::input('title2','通知类型：', $formData->notice_title)->disabled(true),
                    Elm::input('info2','场景说明：', $formData->notice_info)->disabled(true),
                    Elm::input('routine_tempkey','订阅消息编号：', $formData->routine_tempkey)->disabled(true),
                    Elm::input('routine_tempid','订阅消息ID：', $formData->routine_tempid),
                    Elm::textarea('routine_content','订阅消息内容：', $formData->routine_content)->disabled(true),
                    Elm::switches('notice_routine', '是否开启：', $formData->notice_routine)->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->className('switch-width-double')->activeText('开启'),
                ]
            ];
        }
        $form->setRule([
            [
                'type' => 'el-tabs',
                'native' => true,
                'props' => [
                    'value' => $value
                ],
                'children' => $children
            ]
        ]);
        return $form->setTitle( '编辑消息模板')->formData($formData->toArray());
    }

    /**
     * 获取模板列表
     * 根据$type的值决定查询微信模板还是小程序模板的信息
     *
     * @param int $type 模板类型，1代表微信模板，0代表小程序模板
     * @return array 返回包含模板列表和总数的数组
     */
    public function getTemplateList($type)
    {
        // 根据$type的值，确定查询微信模板还是小程序模板，并设置相应的查询条件
        if ($type) {
            $where['is_wechat'] = 1;
            $field = 'notice_title,notice_config_id,notice_wechat,wechat_tempkey tempkey,wechat_content content,wechat_tempid tempid,type,kid';
        } else {
            $where['is_routine'] = 1;
            $field = 'notice_title,notice_config_id,notice_routine,routine_tempkey tempkey,routine_content content,routine_tempid tempid,type,kid';
        }

        // 执行查询操作
        $query = $this->dao->search($where);

        // 统计查询结果的总数
        $count = $query->count();

        // 设置查询字段并执行查询，获取模板列表
        $list = $query->setOption('field',[])->field($field)->field($field)->select();

        // 对查询结果中的每个模板内容进行处理，如果存在内容，则按换行符分割
        foreach ($list as &$item) {
            if ($item['content']) {
                $item['content'] = (strpos($item['content'], "\\n") !== false) ? explode("\\n", $item['content']) : explode("\n", $item['content']);
            }
        }

        // 返回包含模板列表和总数的数组
        return compact('list', 'count');
    }
}
