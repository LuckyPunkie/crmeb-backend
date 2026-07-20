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


use app\common\dao\store\broadcast\BroadcastGoodsDao;
use app\common\repositories\BaseRepository;
use crmeb\jobs\ApplyBroadcastGoodsJob;
use crmeb\services\DownloadImageService;
use crmeb\services\wechat\MiniProgram;
use crmeb\services\SwooleTaskService;
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
use think\Model;

/**
 * 直播商品
 */
class BroadcastGoodsRepository extends BaseRepository
{
    /**
     * @var BroadcastGoodsDao
     */
    protected $dao;

    public function __construct(BroadcastGoodsDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 根据条件获取商户列表
     *
     * 本函数用于查询特定条件下的商户信息，并包含其相关产品信息。
     * 参数$merId用于限定查询的商户ID，$where为额外的查询条件数组，$page和$limit用于分页。
     * 返回包含商户数量和商户列表的信息。
     *
     * @param int $merId 商户ID，用于限定查询的商户
     * @param array $where 查询条件数组，用于进一步筛选商户
     * @param int $page 当前页码，用于分页查询
     * @param int $limit 每页数量，用于分页查询
     * @return array 返回包含商户数量和商户列表的数组
     */
    public function getList($merId, array $where, $page, $limit)
    {
        // 将商户ID添加到查询条件中
        $where['mer_id'] = $merId;

        // 构建查询语句，包括查询条件、关联查询产品信息和按创建时间降序排序
        $query = $this->dao->search($where)->with('product')->order('create_time DESC');

        // 计算满足条件的商户总数
        $count = $query->count();

        // 根据当前页码和每页数量进行分页查询，并获取商户列表
        $list = $query->page($page, $limit)->select();

        // 返回包含商户总数和商户列表的数组
        return compact('count', 'list');
    }

    /**
     * 获取管理员列表
     *
     * 此函数用于根据条件查询管理员列表，并支持分页。它结合了数据的检索、统计和分页功能。
     * 参数包括查询条件、页码和每页的数量。
     *
     * @param array $where 查询条件，以数组形式传递，用于定制查询的特定条件。
     * @param int $page 当前页码，用于分页查询。
     * @param int $limit 每页的数量，用于分页查询。
     * @return array 返回包含管理员数量和管理员列表的数组。
     */
    public function adminList(array $where, $page, $limit)
    {
        // 构建查询语句，包括搜索条件、关联加载和排序规则
        $query = $this->dao->search($where)
            ->with([
                'merchant' => function ($query) {
                    // 加载商家信息，只包含特定字段
                    $query->field('mer_name,mer_id,is_trader');
                },
                'product' // 加载关联的产品信息
            ])
            ->order('sort DESC,BroadcastGoods.create_time DESC');

        // 统计满足条件的管理员总数
        $count = $query->count();

        // 根据页码和每页的数量进行分页查询，并获取管理员列表
        $list = $query->page($page, $limit)->select();

        // 返回管理员总数和管理员列表的数组
        return compact('count', 'list');
    }

    /**
     * 创建直播商品表单
     *
     * 该方法用于生成创建直播商品的表单，表单包含商品相关信息的输入字段。
     * 支持通过传递 formData 参数来预填充表单数据。
     *
     * @param array $formData 表单数据数组，用于预填充表单字段。
     * @return Form|\think\response\View
     */
    public function createForm(array $formData = [])
    {
        if (isset($formData['product_id'])) {
            $formData['product_id'] = [
                'id' => $formData['product_id'],
                'src' => $formData['cover_img']
            ];
        }
        return Elm::createForm((isset($formData['broadcast_goods_id']) ? Route::buildUrl('merchantBroadcastGoodsUpdate', ['id' => $formData['broadcast_goods_id']]) : Route::buildUrl('merchantBroadcastGoodsCreate'))->build(), [
            Elm::frameImage('product_id', '商品：', '/' . config('admin.merchant_prefix') . '/setting/storeProduct?field=product_id')->width('1000px')->height('600px')->props(['srcKey' => 'src'])->icon('el-icon-camera')->modal(['modal' => false])->appendValidate(Elm::validateObject()->message('请选择商品')),
            Elm::input('name', '商品名称：')->placeholder('请输入商品名称')->required('请输入商品名称'),
            Elm::frameImage('cover_img', '商品图：', '/' . config('admin.merchant_prefix') . '/setting/uploadPicture?field=cover_img&type=1')
                ->info('图片尺寸最大像素 300*300')->icon('el-icon-camera')->modal(['modal' => false])->width('1000px')->height
                ('480px')->props(['footer' => false])->required('请上传商品图'),
            Elm::number('price', '价格：')->min(0.01)->info('该价格只做展示,不会影响原商品价格')->required('请输入价格'),
        ])->setTitle('创建直播商品')->formData($formData);
    }

    /**
     * 创建并返回一个用于更新直播商品的表单。
     *
     * 此方法通过指定的ID获取现有的直播商品数据，并基于这些数据创建一个表单，
     * 用于编辑直播商品的信息。表单的标题设置为“编辑直播商品”，明确表单的目的。
     *
     * @param int $id 直播商品的唯一标识ID，用于获取特定的直播商品数据。
     * @return Form 一个配置好的表单实例，可用于前端展示和数据提交。
     */
    public function updateForm($id)
    {
        // 根据$id从数据库中获取指定的直播商品对象
        $liveProduct = $this->dao->get($id);
        // 将直播商品对象转换为数组，作为表单数据的来源
        $liveProductArray = $liveProduct->toArray();

        // 创建表单，并设置表单的标题为“编辑直播商品”
        return $this->createForm($liveProductArray)->setTitle('编辑直播商品');
    }

    /**
     * 创建商品
     *
     * 本函数用于处理商品的创建流程，包括数据准备和事务处理。
     * 根据传入的商家ID和商品数据，首先设定商品的状态，然后在数据库事务中执行商品的创建操作。
     * 根据商品的状态，可能需要进一步调用微信接口创建商品，并更新数据库中的商品信息。
     * 如果商品状态为非上架状态，则通过任务服务发送新商品审核通知。
     *
     * @param string $merId 商家ID
     * @param array $data 商品数据，包含各种与商品相关的信息
     * @return object 创建后的商品对象，包含商品的各种信息
     */
    public function create($merId, array $data)
    {
        // 根据商家商品属性决定商品状态，0为待上架，1为已上架
        $data['status'] = request()->merchant()->is_bro_goods == 1 ? 0 : 1;
        $data['mer_id'] = $merId;

        // 使用数据库事务处理来确保数据的一致性
        return Db::transaction(function () use ($data) {
            // 创建商品
            $goods = $this->dao->create($data);

            // 如果商品状态为已上架，则调用微信接口创建商品
            if ($data['status'] == 1) {
                $res = $this->wxCreate($goods);
                // 更新商品ID和审核ID，来自于微信接口的返回
                $goods->goods_id = $res->goodsId;
                $goods->audit_id = $res->auditId;
                $goods->save();
            } else {
                // 如果商品状态为待上架，则发送新商品审核通知
                SwooleTaskService::admin('notice', [
                    'type' => 'new_goods',
                    'data' => [
                        'title' => '新直播商品申请',
                        'message' => '您有1个新的直播商品审核，请及时处理！',
                        'id' => $goods->broadcast_goods_id
                    ]
                ]);
            }

            // 返回创建后的商品对象
            return $goods;
        });
    }

    /**
     * 批量创建直播商品。
     *
     * 该方法用于处理批量创建直播商品的逻辑。根据商家商品的属性（是否为直播商品），决定商品的初始状态，并在创建商品后执行相应的后续操作。
     * - 如果商品是直播商品，则不推送审核通知，而是将其加入消息队列进行后续处理。
     * - 如果商品不是直播商品，则向管理员发送新直播商品审核的通知。
     *
     * @param string $merId 商家ID，用于标识商品所属的商家。
     * @param array $goodsList 商品列表，包含待创建的多个商品的信息。
     */
    public function batchCreate($merId, array $goodsList)
    {
        // 根据商家商品属性决定商品的初始状态，0表示待审核，1表示已通过。
        $status = request()->merchant()->is_bro_goods == 1 ? 0 : 1;

        // 在数据库事务中处理商品的批量创建，确保数据的一致性。
        $ids = Db::transaction(function () use ($goodsList, $status, $merId) {
            $ids = [];
            foreach ($goodsList as $goods) {
                // 设置商品状态和商家ID，并保存商品。
                $goods['status'] = $status;
                $goods['mer_id'] = $merId;
                $ids[] = $this->dao->create($goods)->broadcast_goods_id;
            }
            return $ids;
        });

        // 遍历创建的商品ID列表，根据商品状态执行不同的后续操作。
        foreach ($ids as $id) {
            if ($status == 1) {
                // 如果商品状态为已通过，将商品ID推入消息队列，进行后续的直播商品处理。
                Queue::push(ApplyBroadcastGoodsJob::class, $id);
            } else {
                // 如果商品状态为待审核，向管理员发送新直播商品审核的通知。
                SwooleTaskService::admin('notice', [
                    'type' => 'new_goods',
                    'data' => [
                        'title' => '新直播商品申请',
                        'message' => '您有1个新的直播商品审核，请及时处理！',
                        'id' => $id
                    ]
                ]);
            }
        }
    }

    /**
     * 更新商品信息。
     * 根据商品当前状态和新状态的不同，执行不同的逻辑，包括更新数据库、创建或更新微信商品、发送通知等。
     *
     * @param int $id 商品ID。
     * @param array $data 商品更新数据。
     * @return object 更新后的商品对象。
     */
    public function update($id, array $data)
    {
        // 根据ID获取商品对象
        $goods = $this->dao->get($id);
        // 获取当前商家是否为代理商家的状态，用于后续判断商品状态的逻辑
        $status = request()->merchant()->is_bro_goods == 1 ? 0 : 1;

        // 如果商品状态为待审核
        if ($goods->status == 0) {
            // 更新商品数据
            $goods->save($data);
            // 如果当前商家不是代理商家
            if ($status == 1) {
                // 如果商品已有ID，说明之前已创建过微信商品，此时只需更新微信商品信息
                if ($goods->goods_id) {
                    $this->wxUpdate($goods->goods_id, $data);
                } else {
                    // 如果商品没有ID，说明是新创建的商品，需要先创建微信商品，并更新商品对象的ID和审核ID
                    $res = $this->wxCreate($goods);
                    $goods->goods_id = $res->goodsId;
                    $goods->audit_id = $res->auditId;
                }
                // 保存更新后的商品信息
                $goods->save();
            } else {
                // 如果当前商家是代理商家，发送新直播商品申请的通知
                SwooleTaskService::admin('notice', [
                    'type' => 'new_goods',
                    'data' => [
                        'title' => '新直播商品申请',
                        'message' => '您有1个新的直播商品审核，请及时处理！',
                        'id' => $goods->broadcast_goods_id
                    ]
                ]);
            }
        } else {
            // 如果商品状态不是待审核
            // 如果当前商家不是代理商家且商品已有微信商品ID，则更新微信商品信息
            if ($status == 1 && $goods->goods_id) {
                $this->wxUpdate($goods->goods_id, $data);
            }
            // 设置商品状态和错误信息为空，准备更新到数据库
            $data['status'] = $status;
            $data['error_msg'] = '';
            // 执行状态变更操作
            $this->change($id, $data);
        }
        // 返回更新后的商品对象
        return $goods;
    }


    /**
     * 根据ID更新数据条目。
     *
     * 本函数通过调用DAO层的update方法，来更新指定ID的数据项。它接收一个ID和一个数据数组，
     * 数据数组包含了需要更新的新值。此函数为业务逻辑层提供了一个接口，以安全和正确的方式更新数据库中的数据。
     *
     * @param int $id 需要更新的数据项的唯一标识符。
     * @param array $data 包含新数据值的数组，这些值将用于更新数据项。
     * @return bool 返回更新操作的结果，通常为TRUE表示更新成功，FALSE表示更新失败。
     */
    public function change($id, array $data)
    {
        return $this->dao->update($id, $data);
    }

    /**
     * 创建审核直播商品的表单
     *
     * 本函数用于生成一个包含审核状态选择和未通过原因输入的表单，用于对直播商品进行审核操作。
     * 表单提交的URL由当前路由系统生成，确保了表单提交的路径正确性。
     * 表单中通过单选按钮选择审核状态，如果选择未通过，则需要输入未通过的原因。
     *
     * @param int $id 直播商品的ID，用于构建表单提交的URL。
     * @return Elm|Form
     */
    public function applyForm($id)
    {
        // 构建表单提交的URL
        $url = Route::buildUrl('systemBroadcastGoodsApply', compact('id'))->build();

        // 创建表单，设置表单标题和提交URL
        return Elm::createForm($url, [
            // 创建审核状态的单选按钮组
            Elm::radio('status', '审核状态：', 1)->options([['value' => -1, 'label' => '未通过'], ['value' => 1, 'label' => '通过']])->control([
                // 当选择未通过状态时，显示未通过原因的文本区域
                ['value' => -1, 'rule' => [
                    Elm::textarea('msg', '未通过原因：', '信息有误,请完善')->placeholder('请输入未通过原因')->required('请输入未通过原因')
                ]]
            ]),
        ])->setTitle('审核直播商品');
    }

    /**
     * 处理商品申请操作。
     *
     * 根据给定的商品ID和状态，对商品进行审核操作。如果状态为-1，表示审核未通过，记录错误信息；
     * 其他状态表示审核通过，并进行相应的商品创建或更新操作。在整个操作过程中，使用数据库事务
     * 来确保数据的一致性。同时，通过SwooleTaskService发送通知，告知商户商品的审核结果。
     *
     * @param int $id 商品ID。
     * @param int $status 商品的审核状态。-1表示未通过，其他状态表示通过。
     * @param string $msg 审核未通过时的错误信息。
     */
    public function apply($id, $status, $msg = '')
    {
        // 根据ID获取商品信息
        $goods = $this->dao->get($id);
        // 使用数据库事务处理来确保操作的原子性
        Db::transaction(function () use ($msg, $status, $goods) {
            // 设置商品的状态
            $goods->status = $status;
            // 如果状态为-1（审核未通过），则记录错误信息
            if ($status == -1) {
                $goods->error_msg = $msg;
            } else {
                // 如果商品有ID，表示商品已存在，进行更新操作
                if ($goods->goods_id) {
                    $this->wxUpdate($goods->goods_id, $goods);
                } else {
                    // 如果商品没有ID，表示是新商品，进行创建操作
                    $res = $this->wxCreate($goods);
                    // 更新商品ID和审计ID
                    $goods->goods_id = $res->goodsId;
                    $goods->audit_id = $res->auditId;
                }
                // 设置商品状态为已通过
                $goods->status = 1;
            }
            // 保存商品信息
            $goods->save();
            // 发送通知给商户，告知商品审核结果
            SwooleTaskService::merchant('notice', [
                'type' => 'goods_status_' . ($status == -1 ? 'fail' : 'success'),
                'data' => [
                    'title' => '直播商品审核通知',
                    'message' => $status == -1 ? '您的直播商品审核未通过!' : '您的直播商品审核已通过',
                    'id' => $goods->broadcast_goods_id
                ]
            ], $goods->mer_id);
        });
    }

    /**
     * 在微信小程序中创建商品
     *
     * 此方法用于在微信小程序中创建一个新的商品。它首先检查商品ID是否存在，如果存在，则抛出一个异常，
     * 表明商品已经创建。然后，它准备商品信息，包括从商品封面图片下载服务中获取图片路径，
     * 并使用微信小程序服务上传该图片。最后，它尝试在微信小程序中创建商品，并在失败时抛出异常。
     *
     * @param Goods $goods 商品对象，包含商品的相关信息。
     * @throws ValidateException 如果商品ID已存在，则抛出此异常。
     * @return array 创建的商品信息。
     */
    public function wxCreate($goods)
    {
        // 检查商品ID是否存在，如果存在则抛出异常
        if ($goods['goods_id']) {
            throw new ValidateException('商品已创建');
        }

        // 将商品对象转换为数组
        $goods = $goods->toArray();

        // 下载商品封面图片并获取其路径
        $path = './public' . app()->make(DownloadImageService::class)->downloadImage($goods['cover_img'],'def','',1)['path'];

        // 准备创建商品所需的数据
        $data = [
            'name' => $goods['name'],
            'priceType' => 1,
            'price' => floatval($goods['price']),
            'url' => 'pages/goods_details/index?source=1:' . $goods['broadcast_goods_id'] . ':' . $goods['product_id'] . '&id=' . $goods['product_id'],
            'coverImgUrl' => MiniProgram::temporaryUpload($path)->media_id,
        ];

        // 删除本地临时图片文件
        @unlink($path);

        try {
            // 在微信小程序中创建商品并返回创建的商品信息
            $res = MiniProgram::addGoods($data['coverImgUrl'], $data['name'], $data['priceType'], $data['url'], (string)$data['price']);
            return (object)['goodsId' => $res['goodsId'], 'auditId' => $res['auditId']];
        } catch (Exception $e) {
            // 如果创建失败，则抛出异常，异常信息为微信小程序返回的错误信息
            throw new ValidateException($e->getMessage());
        }
    }

    /**
     * 更新微信小程序中的商品信息
     *
     * 本函数通过接收商品ID和更新数据，来更新微信小程序中的商品信息。它首先下载商品的封面图片，
     * 然后上传到微信小程序的素材管理中，获取到图片的media_id后，使用该media_id和其他商品信息
     * 更新微信小程序中的商品。
     *
     * @param int $id 商品ID，用于确定要更新的商品
     * @param array $data 包含商品更新信息的数据数组，必须包含'name', 'price', 'cover_img'字段
     * @return mixed 返回微信小程序商品更新后的结果
     * @throws ValidateException 如果更新过程中出现异常，则抛出验证异常
     */
    public function wxUpdate($id,$data)
    {
        // 创建微信小程序服务对象（新驱动）
        // 下载商品封面图片并获取本地文件路径
        $path = './public' . app()->make(DownloadImageService::class)->downloadImage($data['cover_img'],'def','',1)['path'];

        // 上传图片，获取 media_id
        $coverImgUrl = MiniProgram::temporaryUpload($path)->media_id;

        // 删除本地临时图片文件
        @unlink($path);

        try {
            // 尝试更新微信小程序中的商品信息，并返回更新结果
            return MiniProgram::updateGoods($id, $coverImgUrl, $data['name'], 1, '', (string)floatval($data['price']));
        } catch (Exception $e) {
            // 如果更新过程中出现异常，抛出验证异常，并传递异常信息
            throw new ValidateException($e->getMessage());
        }
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
     * 删除商品
     *
     * 本函数用于从数据库中删除指定ID的商品，并同时从小程序的直播间中移除该商品。
     * 它首先尝试从数据库中获取商品信息，如果商品存在，则尝试从小程序的直播间中删除该商品。
     * 接着，它会从应用的直播房间商品仓库中删除该商品，并最后从数据库中彻底删除该商品的信息。
     *
     * @param int $id 商品的唯一标识ID
     */
    public function delete($id)
    {
        // 从数据库中获取指定ID的商品信息
        $goods = $this->dao->get($id);

        // 检查商品是否存在，如果存在则从小程序的直播间中删除该商品
        if ($goods->goods_id) {
            MiniProgram::deleteGoods($goods->goods_id);
        }

        // 从应用的直播房间商品仓库中删除该商品
        app()->make(BroadcastRoomGoodsRepository::class)->deleteGoods($id);

        // 从数据库中删除该商品的信息
        $this->dao->delete($id);
    }

    /**
     * 同步商品审核状态
     * 该方法用于从小程序端获取商品的审核状态，并更新到数据库中，确保数据的一致性。
     * 主要处理商品的审核状态变更，以及根据审核状态更新商品的可用性。
     */
    public function syncGoodStatus()
    {
        // 获取所有商品的ID
        $goodsIds = $this->dao->goodsStatusAll();

        // 如果商品ID为空，则直接返回，不进行后续操作
        if (!count($goodsIds)) return;

        // 调用小程序服务类，获取小程序中商品仓库的数据
        $res = MiniProgram::getGoodsWarehouse(array_keys($goodsIds));

        // 遍历获取的商品数据
        foreach ($res['goods'] as $item) {
            // 检查商品ID在获取的商品ID列表中是否存在，并且审核状态是否发生变化
            if (isset($goodsIds[$item['goods_id']]) && $item['audit_status'] != $goodsIds[$item['goods_id']]) {
                // 准备要更新的数据
                $data = ['audit_status' => $item['audit_status']];

                // 如果审核状态为2（审核中）或3（审核未通过），则更新商品的状态，并设置相应的错误信息
                if (in_array($item['audit_status'], [2, 3])) {
                    $data['status'] = $item['audit_status'] == 3 ? -1 : 2;
                    if (-1 == $data['status']) {
                        $data['error_msg'] = '微信审核未通过';
                    }
                }

                // 更新商品的审核状态
                // 同步商品审核状态
                $this->dao->updateGoods($item['goods_id'], $data);
            }
        }
    }
}
