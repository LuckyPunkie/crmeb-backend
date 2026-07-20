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


use app\common\dao\store\broadcast\BroadcastRoomDao;
use app\common\model\store\broadcast\BroadcastRoom;
use app\common\repositories\BaseRepository;
use crmeb\jobs\SendSmsJob;
use crmeb\services\DownloadImageService;
use crmeb\services\wechat\MiniProgram;
use crmeb\services\SwooleTaskService;
use EasyWeChat\Core\Exceptions\HttpException;
use Exception;
use FormBuilder\Exception\FormBuilderException;
use FormBuilder\Factory\Elm;
use FormBuilder\Form;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Queue;
use think\facade\Route;

/**
 * 直播间
 */
class BroadcastRoomRepository extends BaseRepository
{
    /**
     * @var BroadcastRoomDao
     */
    protected $dao;

    /**
     * BroadcastRoomRepository constructor.
     * @param BroadcastRoomDao $dao
     */
    public function __construct(BroadcastRoomDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 根据条件获取指定商户的数据列表
     *
     * 本函数用于查询数据库中与指定商户相关的数据列表。它支持分页查询，并返回符合条件的数据总数和具体数据列表。
     * 主要用于后台管理或其他需要查询特定商户数据的场景。
     *
     * @param int $merId 商户ID，用于指定查询的商户
     * @param array $where 查询条件数组，用于进一步筛选数据
     * @param int $page 当前页码，用于分页查询
     * @param int $limit 每页数据条数，用于分页查询
     * @return array 返回包含数据总数（count）和数据列表（list）的数组
     */
    public function getList($merId, array $where, $page, $limit)
    {
        // 将指定的商户ID添加到查询条件中
        $where['mer_id'] = $merId;

        // 构建查询语句，根据创建时间降序排序
        $query = $this->dao->search($where)->order('create_time DESC');

        // 计算符合条件的数据总数
        $count = $query->count();

        // 执行分页查询，并获取具体的数据列表
        $list = $query->page($page, $limit)->select();

        // 将数据总数和数据列表打包成数组返回
        return compact('count', 'list');
    }

    /**
     * 获取用户列表
     *
     * 根据给定的条件和分页信息，查询用户列表，包括满足条件的用户数量和用户详细信息。
     * 特别地，关注了直播状态为非关闭的状态，以及对直播时间进行了格式化处理。
     *
     * @param array $where 查询条件，用于筛选用户。
     * @param int $page 当前页码，用于分页查询。
     * @param int $limit 每页显示的用户数量，用于分页查询。
     * @return array 返回包含用户数量和用户列表的数组。
     */
    public function userList(array $where, $page, $limit)
    {
        // 设置显示标签为1的条件，表示只查询显示中的用户
        $where['show_tag'] = 1;

        // 构建查询用户的信息，包括用户自身信息和关联的广播信息
        $query = $this->dao->search($where)->with([
            'broadcast' => function ($query) {
                // 筛选正在销售的广播，并包含关联的商品信息
                $query->where('on_sale', 1);
                $query->with('goods');
            }
        ])
            // 筛选出房间ID大于0，且直播状态不为107（表示直播关闭）的用户
            ->where('room_id', '>', 0)
            ->whereNotIn('live_status', [107])
            // 按照星级、排序和创建时间倒序排列
            ->order('star DESC, sort DESC, create_time DESC');

        // 计算满足条件的用户总数
        $count = $query->count();

        // 分页查询用户信息
        $list = $query->page($page, $limit)->select();

        // 对查询结果中的每个用户，格式化其直播开始时间
        foreach ($list as $item) {
            $item->show_time = date('m/d H:i', strtotime($item->start_time));
        }

        // 返回用户总数和用户列表
        return compact('count', 'list');
    }

    /**
     * 获取管理员列表
     *
     * 根据给定的条件查询管理员信息，并包含关联的商户信息。支持分页查询。
     *
     * @param array $where 查询条件，用于筛选管理员。
     * @param int $page 当前页码，用于分页查询。
     * @param int $limit 每页显示的数量，用于分页查询。
     * @return array 返回包含管理员数量和管理员列表的数组。
     */
    public function adminList(array $where, $page, $limit)
    {
        // 构建查询语句，根据给定条件搜索管理员，并包含关联的商户信息，按特定字段排序
        $query = $this->dao->search($where)
            ->with(['merchant' => function ($query) {
                // 关联查询商户信息，只包含mer_name, mer_id, is_trader字段
                $query->field('mer_name,mer_id,is_trader');
            }])
            ->order('star DESC, BroadcastRoom.sort DESC, BroadcastRoom.create_time DESC');

        // 计算满足条件的管理员总数
        $count = $query->count();

        // 分页查询管理员列表
        $list = $query->page($page, $limit)->select();

        // 返回管理员总数和管理员列表
        return compact('count', 'list');
    }

    /**
     * 创建直播间
     * @return Form
     * @throws FormBuilderException
     * @author xaboy
     * @day 2020/7/29
     */
    public function createForm()
    {
        return Elm::createForm(Route::buildUrl('merchantBroadcastRoomCreate')->build(), [
            Elm::input('name', '直播间名字：')->placeholder('请输入直播间名字')->required('请输入直播间名字'),
            Elm::frameImage('cover_img', '背景图：', '/' . config('admin.merchant_prefix') . '/setting/uploadPicture?field=cover_img&type=1')

                ->info('建议像素1080*1920，大小不超过2M')->icon('el-icon-camera')->modal(['modal' => false])->width('1000px')
                ->height('600px')->props(['footer' => false])->required(true),

            Elm::frameImage('share_img', '分享图：', '/' . config('admin.merchant_prefix') . '/setting/uploadPicture?field=share_img&type=1')
                ->info('建议像素800*640，大小不超过1M')->icon('el-icon-camera')->modal(['modal' => false])
                ->width('1000px')->height('600px')->props(['footer' => false])->required(true),

            Elm::frameImage('feeds_img', '封面图：', '/' . config('admin.merchant_prefix') . '/setting/uploadPicture?field=feeds_img&type=1')
                ->info('建议像素800*800，大小不超过1M')->icon('el-icon-camera')->modal(['modal' => false])->width('1000px')
                ->height('600px')->props(['footer' => false])->required(true),

            Elm::input('anchor_name', '主播昵称：')->required('请输入主播昵称，主播需通过小程序直播认证，否则会提交失败。')->placeholder('请输入主播昵称，主播需通过小程序直播认证，否则会提交失败。'),
            Elm::input('anchor_wechat', '主播微信号：')->required('请输入主播微信号，主播需通过小程序直播认证，否则会提交失败。')->placeholder('请输入主播微信号，主播需通过小程序直播认证，否则会提交失败。'),
            Elm::input('phone', '联系电话')->placeholder('请输入联系电话')->required('请输入联系电话'),
            Elm::dateTimeRange('start_time', '直播时间：')->value([])->required('请选择直播时间'),
            Elm::radio('type', '直播间类型：', 0)->options([['value' => 0, 'label' => '手机直播'], ['value' => 1, 'label' => '推流']]),
            Elm::radio('screen_type', '显示样式：', 0)->options([['value' => 0, 'label' => '竖屏'], ['value' => 1, 'label' => '横屏']]),

            Elm::switches('close_like', '是否开启点赞：', 0)
                ->activeValue(0)->inactiveValue(1)
                ->className('switch-width-double')->activeText('开启')->inactiveText('关闭'),

            Elm::switches('close_goods', '是否开启货架：', 0)
                ->activeValue(0)->inactiveValue(1)
                ->className('switch-width-double')->activeText('开启')->inactiveText('关闭'),

            Elm::switches('close_comment', '是否开启评论：', 0)
                ->activeValue(0)->inactiveValue(1)
                ->className('switch-width-double')->activeText('开启')->inactiveText('关闭'),

            Elm::switches('replay_status', '是否开启回放：', 0)
                ->activeValue(1)->inactiveValue(0)
                ->className('switch-width-double')->activeText('开启')->inactiveText('关闭'),

            Elm::switches('close_share', '是否开启分享：', 0)
                ->activeValue(0)->inactiveValue(1)
                ->className('switch-width-double')->activeText('开启')->inactiveText('关闭'),

            Elm::switches('close_kf', '是否开启客服：', 0)
                ->activeValue(0)->inactiveValue(1)
                ->className('switch-width-double')->activeText('开启')->inactiveText('关闭'),

            Elm::switches('is_feeds_public', '是否开启官方收录：', 1)
                ->activeValue(1)->inactiveValue(0)
                ->className('switch-width-double')->activeText('开启')->inactiveText('关闭'),

        ])->setTitle('创建直播间');
    }

    /**
     * 创建编辑直播间的表单
     *
     * 本函数用于生成一个用于编辑直播间的表单。它首先通过$id$从数据层获取直播间的信息，
     * 然后将开始时间和结束时间组合为一个数组，以便在表单中以特定的方式显示。
     * 最后，它构建并返回一个填充了直播间数据的表单实例，表单的动作是更新直播间信息的路由。
     *
     * @param int $id 直播间的唯一标识符，用于获取直播间的信息。
     * @return Form 生成的表单实例，包含了直播间的信息和编辑操作的动作。
     */
    public function updateForm($id)
    {
        // 通过$id$获取直播间的信息，并转换为数组格式
        $data = $this->dao->get($id)->toArray();

        // 将开始时间和结束时间组合为一个数组，方便在表单中处理
        $data['start_time'] = [$data['start_time'], $data['end_time']];

        // 创建表单，设置表单的动作为更新直播间信息的路由，并填充直播间数据
        // 设置表单标题为“编辑直播间”
        return $this->createForm()->setAction(Route::buildUrl('merchantBroadcastRoomUpdate', compact('id'))->build())->formData($data)->setTitle('编辑直播间');
    }


    /**
     * 创建直播房间
     *
     * @param string $merId 商户ID
     * @param array $data 房间相关数据
     * @return Room|bool 创建的房间对象或操作结果
     *
     * 本函数负责根据提供的商户ID和房间数据创建直播房间。它会根据商户是否是主播房间
     * 来设定房间的状态，并在创建房间后根据房间状态执行不同的后续操作，如设置房间ID
     * 和发送管理员通知。
     */
    public function create($merId, array $data)
    {
        // 根据商户是否是主播房间，设置房间状态
        $data['status'] = request()->merchant()->is_bro_room == 1 ? 0 : 1;
        $data['mer_id'] = $merId;

        // 使用事务处理来确保数据的一致性
        return Db::transaction(function () use ($data) {
            $room = $this->dao->create($data);

            // 如果房间是待审核状态，则进行房间ID的设置和状态更新
            if ($data['status'] == 1) {
                $room->room_id = $this->wxCreate($room);
                $room->status = 2;
                $room->save();
            } else {
                // 如果房间是待审核状态以外的状态，则发送管理员通知
                SwooleTaskService::admin('notice', [
                    'type' => 'new_broadcast',
                    'data' => [
                        'title' => '新直播间申请',
                        'message' => '您有1个新的直播间审核，请及时处理！',
                        'id' => $room->broadcast_room_id
                    ]
                ]);
            }

            return $room;
        });
    }

    /**
     * 更新直播间信息并发送通知。
     *
     * 本函数用于处理直播间信息的更新，并在更新完成后发送管理员通知。它首先通过$merId和$id查询到对应的直播间，
     * 然后更新直播间的状态和其他信息。最后，通过SwooleTaskService发送一条新直播间申请的通知给管理员。
     *
     * @param string $merId 商户ID，用于查询直播间所属的商户。
     * @param int $id 直播间ID，用于查询具体的直播间。
     * @param array $data 包含需要更新的直播间信息的数据数组。
     */
    public function updateRoom($merId, $id, array $data)
    {
        // 设置直播间状态为待审核
        $data['status'] = 0;

        // 根据商户ID和直播间ID查询直播间信息
        $room = $this->dao->getWhere(['mer_id' => $merId, 'broadcast_room_id' => $id]);

        // 更新直播间的信息
        $room->save($data);

        // 发送管理员通知，告知有新的直播间申请待处理
        SwooleTaskService::admin('notice', [
            'type' => 'new_broadcast',
            'data' => [
                'title' => '新直播间申请',
                'message' => '您有1个新的直播间审核，请及时处理！',
                'id' => $room->broadcast_room_id
            ]
        ]);
    }

    /**
     * 创建直播间申请表单
     *
     * 本函数用于生成一个包含直播间申请审核状态的表单。表单中包含一个单选按钮组，
     * 用于选择审核状态（未通过或通过），如果选择未通过，还需要提供未通过的原因。
     *
     * @param int $id 直播间申请的ID，用于构建表单的提交URL。
     * @return Elm|Form
     */
    public function applyForm($id)
    {
        // 构建表单提交的URL，使用紧凑模式传递ID参数
        $url = Route::buildUrl('systemBroadcastRoomApply', compact('id'))->build();

        // 创建表单，设置表单标题为“审核直播间”
        return Elm::createForm($url, [
            // 添加单选按钮组，用于选择审核状态，初始值为通过（1）
            Elm::radio('status', '审核状态：', '1')
                ->options([['value' => '-1', 'label' => '未通过'], ['value' => '1', 'label' => '通过']])
                ->control([
                    // 当选择未通过时，显示文本区域，用于输入未通过的原因
                    ['value' => '-1', 'rule' => [
                        Elm::textarea('msg', '未通过原因：', '信息有误,请完善')
                            ->placeholder('请输入未通过原因')
                            ->required('请输入未通过原因')
                    ]]
                ])
        ])
            ->setTitle('审核直播间');
    }

    /**
     * 处理直播间的申请操作。
     *
     * 根据传入的状态对直播间进行相应的处理，包括修改直播间状态、记录错误信息、创建直播间、发送通知等。
     * 当状态为-1时，表示申请未通过，会记录失败原因；其他状态表示申请通过并进行相应的直播间创建和通知发送操作。
     *
     * @param int $id 直播间ID。
     * @param int $status 直播间状态，用于确定具体的处理流程。
     * @param string $msg 当状态为-1时，用于记录申请未通过的原因。
     */
    public function apply($id, $status, $msg = '')
    {
        // 根据ID获取直播间信息
        $room = $this->dao->get($id);
        // 开启数据库事务，确保一系列操作的原子性
        Db::transaction(function () use ($msg, $status, $room) {
            // 更新直播间状态
            $room->status = $status;
            // 当状态为-1时，记录未通过的原因
            if ($status == -1) {
                $room->error_msg = $msg;
            } else {
                // 通过微信接口创建直播间，并更新直播间ID和状态
                $room_id = $this->wxCreate($room);
                $room->room_id = $room_id;
                $room->status = 2;
                // 如果直播间类型需要，生成并记录推流地址
                if ($room->type) {
                    $path = MiniProgram::getPushUrl($room_id);
                    $room->push_url = $path['pushAddr'];
                }
            }
            // 保存更新后的直播间信息
            $room->save();

            // 发送审核状态通知给商家
            SwooleTaskService::merchant('notice', [
                'type' => 'broadcast_status_' . ($status == -1 ? 'fail' : 'success'),
                'data' => [
                    'title' => '直播间审核通知',
                    'message' => $status == -1 ? '您的直播间审核未通过!' : '您的直播间审核已通过',
                    'id' => $room->broadcast_room_id
                ]
            ], $room->mer_id);

            // 当状态为-1时，将未通过审核的信息推送到短信队列
            if ($status == -1) {
                Queue::push(SendSmsJob::class, [
                    'tempId' => 'BROADCAST_ROOM_FAIL',
                    'id' => $room['broadcast_room_id']
                ]);
            }
        });
    }

    /**
     * 创建微信直播房间
     *
     * @param BroadcastRoom $room 直播房间信息对象
     * @return string 创建的直播房间ID
     * @throws ValidateException 如果房间已经存在，则抛出验证异常
     */
    public function wxCreate(BroadcastRoom $room)
    {
        // 检查房间ID是否存在，如果存在则表示房间已创建，抛出异常
        if ($room['room_id']) {
            throw new ValidateException('直播间已创建');
        }

        // 将BroadcastRoom对象转换为数组
        $room = $room->toArray();

        // 创建图片下载服务实例
        $DownloadImageService = app()->make(DownloadImageService::class);

        // 下载并获取封面图片路径
        $coverImg = './public' . $DownloadImageService->downloadImage($room['cover_img'], 'def', '', 1)['path'];
        // 下载并获取分享图片路径
        $shareImg = './public' . $DownloadImageService->downloadImage($room['share_img'], 'def', '', 1)['path'];
        // 下载并获取Feed流图片路径
        $feedsImg = './public' . $DownloadImageService->downloadImage($room['feeds_img'], 'def', '', 1)['path'];

        // 准备直播房间相关信息
        $data = [
            'name' => $room['name'], // 直播间名称
            'coverImg' => MiniProgram::temporaryUpload($coverImg)->media_id, // 封面图片ID
            'startTime' => strtotime($room['start_time']), // 直播开始时间戳
            'endTime' => strtotime($room['end_time']), // 直播结束时间戳
            'anchorName' => $room['anchor_name'], // 主播姓名
            'anchorWechat' => $room['anchor_wechat'], // 主播微信号
            'shareImg' => MiniProgram::temporaryUpload($shareImg)->media_id, // 分享图片ID
            'feedsImg' => MiniProgram::temporaryUpload($feedsImg)->media_id, // Feed流图片ID
            'type' => $room['type'], // 直播类型
            'closeLike' => $room['close_like'], // 是否关闭点赞
            'closeGoods' => $room['close_goods'], // 是否关闭商品
            'closeComment' => $room['close_comment'], // 是否关闭评论

            'screenType' => $room['screen_type'], // 屏幕类型
            'closeShare' => $room['close_share'], // 是否关闭分享
            'closeKf' => $room['close_kf'], // 是否关闭客服
            'closeReplay' => $room['replay_status'] == 1 ? 0 : 1, // 是否关闭回放
            'isFeedsPublic' => $room['is_feeds_public'] == 1 ? 0 : 1, // 是否关闭Feed流公开
        ];

        // 删除本地临时图片文件
        @unlink($coverImg);
        @unlink($shareImg);
        @unlink($feedsImg);

        try {
            // 创建直播房间，并获取房间ID
            $res = MiniProgram::createLiveRoom($data);
            $roomId = $res['roomId'];
        } catch (Exception $e) {
            // 如果创建失败，抛出验证异常，异常信息为微信返回的错误信息
            throw new ValidateException($e->getMessage());
        }

        // 将发送短信的任务推入队列
        Queue::push(SendSmsJob::class, [
            'tempId' => 'BROADCAST_ROOM_CODE',
            'id' => $room['broadcast_room_id']
        ]);

        // 返回创建的直播房间ID
        return $roomId;
    }

    /**
     * 更新展示状态
     * 根据管理员身份决定更新的是全局展示状态还是商家展示状态
     *
     * @param int $id 数据标识符
     * @param int $isShow 展示状态值，通常为0（不展示）或1（展示）
     * @param bool $admin 管理员身份标记，默认为false，表示非管理员
     * @return bool 更新操作的结果，true表示成功，false表示失败
     */
    public function isShow($id, $isShow, bool $admin = false)
    {
        // 根据管理员身份选择更新的字段，管理员更新is_show字段，非管理员更新is_mer_show字段
        return $this->dao->update($id, [($admin ? 'is_show' : 'is_mer_show') => $isShow]);
    }


    /**
     * 更新记录的标记。
     *
     * 本函数通过调用DAO层的update方法，更新指定ID的记录的mark字段。
     * 主要用于在系统中对特定资源进行标记或状态更新，例如标记一项任务为完成。
     *
     * @param int $id 需要更新的记录的ID。这是一个主键标识，用于精确定位到要更新的数据。
     * @param string $mark 新的标记值。这个值将替换原有记录的mark字段，用于表示资源的最新状态或标记。
     * @return bool 返回更新操作的结果。成功更新时返回true，更新失败则返回false。
     */
    public function mark($id, $mark)
    {
        // 调用DAO层的update方法，传入ID和新的标记值，尝试更新记录。
        return $this->dao->update($id, compact('mark'));
    }

    /**
     * 导出商品到直播间
     * 该方法用于将指定的商品关联到指定的直播间。它首先验证所选商品的有效性，然后检查直播间的状态，
     * 最后将商品与直播间建立关联。
     *
     * @param int $merId 商家ID，用于权限验证和商品查询。
     * @param array $ids 商品ID列表，表示需要导出到直播间的商品ID。
     * @param int $roomId 直播间ID，表示商品将被导出到的直播间。
     * @throws ValidateException 如果验证失败，抛出异常提示用户。
     */
    public function exportGoods($merId, array $ids, $roomId)
    {
        // 实例化直播商品仓库，用于查询商品信息。
        $broadcastGoodsRepository = app()->make(BroadcastGoodsRepository::class);
        // 验证所选商品是否存在并属于该商家。
        if (count($ids) != count($goods = $broadcastGoodsRepository->goodsList($merId, $ids)))
            throw new ValidateException('请选择正确的直播商品');
        // 验证直播间是否存在并处于有效状态。
        if (!$room = $this->dao->validRoom($roomId, $merId))
            throw new ValidateException('直播间状态有误');
        // 实例化直播房间商品仓库，用于查询直播间商品关联信息。
        $broadcastRoomGoodsRepository = app()->make(BroadcastRoomGoodsRepository::class);
        // 获取当前直播间已关联的商品ID列表。
        $goodsId = $broadcastRoomGoodsRepository->goodsId($room->broadcast_room_id);
        $ids = [];
        $data = [];
        // 遍历商品列表，找出未关联到当前直播间的商品，准备建立关联。
        foreach ($goods as $item) {
            if (!in_array($item->broadcast_goods_id, $goodsId)) {
                $data[] = [
                    'broadcast_room_id' => $room->broadcast_room_id,
                    'broadcast_goods_id' => $item->broadcast_goods_id
                ];
                $ids[] = $item->goods_id;
            }
        }
        // 如果没有需要新增关联的商品，则直接返回。
        if (!count($ids)) return;
        // 使用事务确保数据操作的完整性。
        Db::transaction(function () use ($ids, $broadcastRoomGoodsRepository, $goods, $room, $data) {
            // 批量插入新的商品与直播间关联记录。
            $broadcastRoomGoodsRepository->insertAll($data);
            // 调用小程序服务，将商品添加到直播间（注意：这里的代码可能需要根据实际服务位置进行调整）。
            MiniProgram::roomAddGoods($room->room_id, $ids);
        });
    }

    /**
     * 删除导出的商品
     * 该方法用于从直播房间中移除指定的商品。它首先验证商家和直播房间的存在性，然后通过商品ID和房间ID删除相关商品。
     * 如果商家或直播房间不存在，则抛出一个验证异常。
     *
     * @param int $merId 商家ID 用于验证商家是否存在
     * @param int $roomId 直播房间ID 用于验证直播房间是否存在
     * @param int $id 商品ID 用于从直播房间中删除指定商品
     * @throws ValidateException 如果商家或直播房间不存在，则抛出此异常
     */
    public function rmExportGoods($merId, $roomId, $id)
    {
        // 验证商家和直播房间是否存在
        if (!$this->dao->merExists($roomId, $merId))
            throw new ValidateException('直播间不存在');

        // 删除指定房间中的商品
        app()->make(BroadcastRoomGoodsRepository::class)->rmGoods($id, $roomId);
    }

    /**
     * 同步房间状态
     * 该方法用于从小程序广播接口获取房间状态，并更新本地数据库中对应房间的状态。考虑到性能和接口限制，采用分页方式批量获取和更新。
     */
    public function syncRoomStatus()
    {
        // 初始化起始位置和每批处理的数量
        $start = 0;
        $limit = 50;

        // 创建小程序广播客户端（按分页批次获取）
        do {
            // 分批获取房间信息
            $data = MiniProgram::getLiveInfo($start, $limit);
            $start += 50; // 更新起始位置，准备下一批处理

            // 根据获取的房间ID，从本地数据库中批量获取房间详情
            $rooms = $this->getRooms(array_column($data, 'roomid'));

            // 遍历获取的房间信息，对比并更新本地数据库中的房间状态
            foreach ($data as $room) {
                // 如果本地有该房间记录且状态不同，则更新房间状态
                if (isset($rooms[$room['roomid']]) && $room['live_status'] != $rooms[$room['roomid']]['live_status']) {
                    $this->dao->update($rooms[$room['roomid']]['broadcast_room_id'], ['live_status' => $room['live_status']]);
                }
            }
        } while (count($data) >= $limit); // 如果当前批次的数据量达到或超过限制，继续处理下一批
    }

    /**
     * 商家删除操作
     *
     * 本函数用于执行商家的删除操作。删除操作由商家ID标识。
     * 注意：此处的删除操作可能是逻辑删除，即标记为删除，而不是物理删除。
     *
     * @param int $id 商家ID，用于指定需要删除的商家。
     * @return bool 返回删除操作的结果，通常是操作是否成功的布尔值。
     *
     * @throws ValidateException 如果商家状态不正确，则抛出验证异常。
     */
    public function merDelete($id)
    {
        // 通过商家ID执行删除操作
        // 此处注释掉的代码块原本用于检查商家的状态是否允许删除
        // 如果商家状态不正确，则不应该执行删除操作，并抛出异常
        // 在实际执行中，这些检查可能被实现为更复杂的业务逻辑，以确保数据的安全性和一致性

        // 调用DAO层的方法执行商家删除操作
        return $this->dao->merDelete($id);
    }


    /**
     * 关闭直播相关信息的函数
     *
     * 本函数用于处理直播间的关闭操作，包括关闭客服、评论、公开性以及商品上架状态的变更。
     * 在执行操作前，会检查直播间的审核状态及权限，确保只有在允许的情况下才能进行变更。
     * 使用事务确保数据库操作的一致性。
     *
     * @param int $id 直播间ID
     * @param string $type 关闭类型，包括关闭客服（'close_kf'）、关闭评论（'close_comment'）、设置feed是否公开（'is_feeds_public'）和商品上架状态（'on_sale'）
     * @param int $status 新的状态值，用于关闭或开启相关功能
     * @param bool $check 是否检查平台状态，默认为true。如果平台已关闭，则不允许进行修改。
     * @param array $data 当类型为'on_sale'时，需要提供商品相关信息，包括商品ID。
     * @throws ValidateException 当直播间状态不正确、数据不存在或操作权限受限时抛出异常。
     */
    public function closeInfo($id, string $type, int $status, $check = true, $data = [])
    {
        // 根据ID获取直播间信息
        $room = $this->dao->get($id);
        // 检查直播间是否已通过审核
        if ($room->status !== 2) throw new ValidateException('直播间还未审核通过，无法修改');
        // 检查直播间是否存在
        if (!$room) throw new ValidateException('数据不存在');

        // 如果需要检查平台状态且直播间对应类型已关闭，则抛出异常
        if ($check && $room[$type] == -1) {
            throw new ValidateException('平台已关闭，您无法修改');
        }

        // 使用事务处理数据库操作
        Db::transaction(function () use ($room, $id, $type, $status, $data) {
            // 根据类型创建对应的操作客户端
            switch ($type) {
                case 'close_kf':
                    // 关闭客服
                    MiniProgram::closeKf($room->room_id, $status);
                    $room->close_kf = $status;
                    break;
                case 'close_comment':
                    // 关闭评论
                    MiniProgram::banComment($room->room_id, $status);
                    $room->close_comment = $status;
                    break;
                case 'is_feeds_public':
                    // 设置feed公开性
                    MiniProgram::updateFeedPublic($room->room_id, $status);
                    $room->is_feeds_public = $status;
                    break;
                case 'on_sale':
                    // 商品上架状态变更
                    $ret = app()->make(BroadcastRoomGoodsRepository::class)->getWhere([
                        'broadcast_room_id' => $id,
                        'broadcast_goods_id' => $data['goods_id'],
                    ], '*', ['goods']);
                    // 检查商品是否存在
                    if (!isset($ret['goods']['goods_id'])) throw new ValidateException('数据不存在');
                    // 更新商品上架状态
                    $ret->on_sale = $status;
                    $ret->save();
                    // 商品上架或下架
                    MiniProgram::goodsOnsale($room->room_id, $ret['goods']['goods_id'], $status);
                    break;
            }
            // 更新直播间信息
            $room->save();
        });
    }

    /**
     * 创建商家助手编辑表单
     *
     * 该方法用于生成一个用于编辑商家助手的表单。商家助手是在直播间中协助商家进行互动的工具。
     * 表单中包含一个选择字段，用于选择可用的小助手。
     *
     * @param int $id 商家直播间的ID
     * @param int $merId 商家的ID
     * @return \EasyWeChat\Kernel\Messages\Miniprogram|Form
     *
     * @throws ValidateException 如果直播间未通过审核，则抛出异常
     */
    public function assistantForm(int $id, int $merId)
    {
        // 实例化广播助手仓库
        $make = app()->make(BroadcastAssistantRepository::class);
        // 根据ID获取直播间信息
        $get = $this->dao->get($id);
        // 如果直播间的状态不是2（未审核通过），则抛出异常
        if ($get->status !== 2) throw new ValidateException('直播间还未审核通过，无法操作');
        // 获取可选的助手列表
        $data = $make->options($merId);
        // 检查当前商家是否已添加过助手
        $has = $make->intersection($get->assistant_id, $merId);
        // 创建表单，设置表单的URL和标题，并添加选择助手的字段
        return Elm::createForm(Route::buildUrl('merchantBroadcastAddAssistant', compact('id'))->build(),
            [
                Elm::selectMultiple('assistant_id', '小助手：')->options(function () use ($data) {
                    $options = [];
                    // 如果有可用的助手数据，遍历生成选项
                    if ($data) {
                        foreach ($data as $value => $label) {
                            $options[] = compact('value', 'label');
                        }
                    }
                    return $options;
                })
            ])->setTitle('修改小助手');
    }

    /**
     * 编辑助手信息
     *
     * 该方法用于更新指定房间ID下的助手列表。它首先检查新提供的助手ID列表是否与现有列表有差异，
     * 然后根据差异添加或移除助手，并最终更新数据库中的助手ID组合。
     *
     * @param int $id 房间ID
     * @param int $merId 商家ID，用于权限控制或记录操作来源
     * @param array $data 新的助手ID列表
     */
    public function editAssistant(int $id, int $merId, array $data)
    {
        // 实例化广播助手仓库，用于后续的操作
        $make = app()->make(BroadcastAssistantRepository::class);
        // 检查传入的数据是否在数据库中全部存在，用于防止非法数据操作
        $make->existsAll($data, $merId);

        // 使用数据库事务来确保操作的原子性
        Db::transaction(function () use ($id, $data) {
            // 获取当前房间的信息
            $get = $this->dao->get($id);
            // 将现有的助手ID字符串转换为数组
            $old = explode(',', $get->assistant_id);

            // 计算需要移除的助手ID和需要添加的助手ID
            $remove = array_diff($old, $data);
            $add = array_diff($data, $old);

            // 分别调用方法来添加和移除助手
            $this->addAssistant($get->room_id, $add);
            $this->removeAssistant($get->room_id, $remove);

            // 更新房间的助手ID列表，并保存到数据库
            $get->assistant_id = implode(',', $data);
            $get->save();
        });

    }

    /**
     * 从指定的房间中移除助手。
     *
     * 本函数用于处理从特定房间中移除多个助手的操作。它首先通过提供的助手ID查询到相关助手的信息，
     * 然后依次调用接口将这些助手从指定的房间中移除。
     *
     * @param int $roomId 房间ID，指定要从哪个房间移除助手。
     * @param array $ids 助手ID数组，指定要移除的助手的ID列表。
     */
    public function removeAssistant($roomId, array $ids)
    {
        // 实例化广播助手仓库，用于后续查询助手信息。
        $make = app()->make(BroadcastAssistantRepository::class);

        // 根据提供的助手ID查询助手信息。
        $data = $make->getSearch(['assistant_ids' => $ids])->select();

        // 遍历查询到的助手信息，逐个从房间中移除助手。
        foreach ($data as $datum) {
            MiniProgram::removeAssistant($roomId, $datum->username);
        }
    }

    /**
     * 添加助手到直播间
     *
     * 本函数用于将指定的助手用户添加到指定的直播间中。它首先通过助手ID查询用户信息，
     * 然后将这些信息发送给小程序服务端，以在直播间中添加助手。
     *
     * @param int $roomId 直播间ID
     * @param array $ids 助手用户的ID列表
     */
    public function addAssistant($roomId, array $ids)
    {
        // 通过依赖注入获取广播助手仓库实例
        $make = app()->make(BroadcastAssistantRepository::class);

        // 根据助手ID查询用户用户名和昵称
        $data = $make->getSearch(['assistant_ids' => $ids])->column('username,nickname');

        // 调用小程序服务，添加助手到直播间
        MiniProgram::addAssistant($roomId, $data);
    }

    /**
     * 向特定房间的用户推送消息。
     *
     * 本函数旨在向小程序中特定房间的用户推送消息。它首先通过房间ID获取房间信息，
     * 然后分页获取该房间的所有关注者列表，并将消息推送给这些用户。
     *
     * @param int $id 房间的ID，用于定位要推送消息的特定房间。
     * @throws ValidateException 如果获取关注者列表或推送消息时发生错误，则抛出异常。
     */
    public function pushMessage(int $id)
    {
        // 通过房间ID获取房间信息
        $get = $this->dao->get($id);
        // 初始化分页标识
        $page_break = '';

        do {
            // 分页获取关注者列表
            $data = MiniProgram::getFollowers($page_break);
            $restult = [];
            // 检查是否有错误发生，如果有则抛出异常
            if ($data['errcode'] !== 0) throw new ValidateException($data['errmsg']);
            // 遍历关注者列表，筛选出属于指定房间的用户
            foreach ($data['followers'] as $datum) {
                if ($datum['room_id'] == $get->room_id) {
                    $restult[] = $datum['openid'];
                }
            }
            // 如果有符合的用户，则向他们推送消息
            if ($restult) {
                MiniProgram::pushMessage($get->room_id, $restult);
            }
            // 更新分页标识，准备获取下一页数据
            $page_break = $data['page_break'] ?? '';
        } while ($page_break);
    }
}
