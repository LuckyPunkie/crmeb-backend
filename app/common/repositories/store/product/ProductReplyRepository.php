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


namespace app\common\repositories\store\product;


use app\common\dao\BaseDao;
use app\common\dao\store\product\ProductReplyDao;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\order\StoreOrderProductRepository;
use app\common\repositories\store\order\StoreOrderStatusRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\user\UserBrokerageRepository;
use crmeb\jobs\UpdateProductReplyJob;
use crmeb\services\SwooleTaskService;
use FormBuilder\Exception\FormBuilderException;
use FormBuilder\Factory\Elm;
use FormBuilder\Form;
use think\facade\Cache;
use function Symfony\Component\String\b;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Route;
use think\Model;
use think\facade\Queue;
use app\common\repositories\circle\CircleFinancialRecordRepository;

/**
 * 商品评论
 */
class ProductReplyRepository extends BaseRepository
{
    /**
     * ProductReplyRepository constructor.
     * @param ProductReplyDao $dao
     */
    public function __construct(ProductReplyDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取列表数据
     *
     * 通过传入的条件数组和分页信息，查询数据库中的列表数据。
     * 此方法实现了数据的分页查询，以及根据条件进行数据排序。
     *
     * @param array $where 查询条件数组，用于构建SQL查询的WHERE子句。
     * @param int $page 当前的页码，用于实现分页查询。
     * @param int $limit 每页显示的数据条数，用于控制分页查询的结果数量。
     * @return array 返回包含列表数据和总条数的数组。
     */
    public function getList(array $where, $page, $limit)
    {
        // 构建查询语句，包括搜索条件和排序规则
        $query = $this->dao->searchJoinQuery($where)->order('A.sort DESC');

        // 计算满足条件的数据总条数
        $count = $query->count();

        // 执行分页查询，获取当前页的数据列表
        $list = $query->page($page, $limit)->select();

        // 返回包含数据总条数和当前页数据列表的数组
        return compact('count', 'list');
    }

    /**
     * 获取API列表
     * 根据给定的条件、分页和限制获取API列表。如果系统配置不允许回复，则返回空列表。
     *
     * @param array $where 查询条件
     * @param int $page 当前页码
     * @param int $limit 每页条数
     * @return array 返回包含评价统计和评论列表的数组
     */
    public function getApiList($where, $page, $limit)
    {
        // 检查系统配置是否允许回复，如果不允许，则返回空列表和统计信息
        if (systemConfig('sys_reply_status') === '0') {
            $count = 0;
            $list = [];
        } else {
            // 构建查询条件，包括删除状态、类型条件和排序
            $query = $this->dao->search($where)->where('is_del', 0)
                ->when($where['type'] !== '', function ($query) use ($where) {
                    $query->where($this->switchType($where['type']));
                })
                ->with(['orderProduct' => function ($query) {
                    $query->field('order_product_id,cart_info');
                }])
                ->order('sort DESC,create_time DESC');
            // 计算符合条件的记录总数
            $count = $query->count();
            // 分页查询并处理每条记录，隐藏虚拟字段，处理SKU和昵称
            $list = $query->page($page, $limit)->hidden(['is_virtual'])->select()->each(function ($item) {
                $item['sku'] = $item['orderProduct']['cart_info']['productAttr']['sku'] ?? '';
                if (mb_strlen($item['nickname']) > 1) {
                    $str = mb_substr($item['nickname'],0,1) . '*';
                    if (mb_strlen($item['nickname']) > 2) {
                        $str .= mb_substr($item['nickname'], -1,1);
                    }
                    $item['nickname'] = $str;
                }
                unset($item['orderProduct']);
                return $item;
            });
        }

        // 统计产品评价数量和类型分布
        $product = ['product_id' => $where['product_id'], 'is_del' => 0];
        $stat = [
            'count' => $this->dao->search($product)->count(),
            'best' => $this->dao->search($product)->where($this->switchType('best'))->count(),
            'middle' => $this->dao->search($product)->where($this->switchType('middle'))->count(),
            'negative' => $this->dao->search($product)->where($this->switchType('negative'))->count(),
        ];

        // 计算好评率
        $rate = ($stat['count'] > 0) ? bcdiv($stat['best'], $stat['count'], 2) * 100 . '%' : 100 . '%';
        // 获取产品的详细信息，用于计算评分星星的比例
        $ret = app()->make(ProductRepository::class)->get($where['product_id']);
        // 计算评分星星的比例
        $star = (($ret['rate'] == 0) ? 0 : ($ret['rate'] / 5) * 100) . '%';

        // 返回评价统计信息和评论列表
        return compact('rate', 'star', 'count', 'stat', 'list');
    }

    /**
     * 根据类型切换查询条件
     *
     * 本函数根据传入的类型参数，返回相应的数据库查询条件，用于筛选不同评分范围的数据。
     * 主要用于在数据查询时根据不同的评分标准进行条件过滤。
     *
     * @param string $type 评价类型，支持'best'（优秀）、'middle'（中等）、'negative'（差评）三种类型。
     * @return array 返回对应的查询条件数组，如果没有匹配的类型，则返回空数组。
     */
    public function switchType($type)
    {
        // 初始化查询条件
        $where = [];
        // 根据传入的类型设置查询条件
        switch ($type) {
            case 'best':
                // 优秀评价，评分在4到5之间（包含4和5）
                $where = [['rate', '>=', 4], ['rate', '<=', 5]];
                break;
            case 'middle':
                // 中等评价，评分在2到3之间（不包含4）
                $where = [['rate', '>=', 2], ['rate', '<', 4]];
                break;
            case 'negative':
                // 差评，评分小于2
                $where = [['rate', '<', 2]];
                break;
            default:
                // 默认情况，没有指定类型时，不设置查询条件
                break;
        }
        return $where;
    }

    /**
     * 创建虚拟评价表单
     *
     * 该方法用于生成一个包含多个输入字段和特殊输入类型的表单，用于添加虚拟评价。
     * 表单字段包括产品ID、用户昵称、评价文字、商品分数、服务分数、物流分数、用户头像和评价图片。
     * 根据$productId的有无，决定产品ID字段是隐藏输入还是一个框架图像，用于选择产品。
     *
     * @param int|null $productId 产品ID，如果为NULL，则表示需要一个选择产品的图像框架。
     * @return \Encore\Admin\Widgets\Form|Form
     */
    public function form(?int $productId)
    {
        $rule = [];

        // 根据$productId的值，决定产品ID字段的类型：隐藏输入或图像框架选择。
        if ($productId) {
            $rule[] = Elm::hidden('product_id', [['id' => $productId]]);
        } else {
            $rule[] = Elm::frameImage('product_id', '商品：', '/' . config('admin.admin_prefix') . '/setting/storeProduct?field=product_id')->width('1000px')->height('600px')->props(['srcKey' => 'src'])->icon('el-icon-camera')->modal(['modal' => false]);
        }

        // 添加用户昵称输入字段，必填。
        $rule[] = Elm::input('nickname', '用户昵称：')->placeholder('请输入用户昵称')->required('请输入用户昵称');

        // 添加评价文字的文本区域输入字段，必填。
        $rule[] = Elm::input('comment', '评价文字：')->type('textarea')->placeholder('请输入评价文字')->required('请输入评价文字');

        // 添加商品分数评分输入字段，最大分数为5。
        $rule[] = Elm::rate('product_score', '商品分数：', 5)->max(5)->required('请选择商品分数');

        // 添加服务分数评分输入字段，最大分数为5。
        $rule[] = Elm::rate('service_score', '服务分数：', 5)->max(5)->required('请选择服务分数');

        // 添加物流分数评分输入字段，最大分数为5。
        $rule[] = Elm::rate('postage_score', '物流分数：', 5)->max(5)->required('请选择物流分数');

        // 排序
        $rule[] = Elm::number('sort', '排序：', 1)->max(999)->required('请输入排序');

        // 添加用户头像图像框架选择字段，必填。
        $rule[] = Elm::frameImage('avatar', '用户头像：', '/' . config('admin.admin_prefix') . '/setting/uploadPicture?field=avatar&type=1')
        ->width('1000px')->height('600px')->props(['footer' => false])->icon('el-icon-camera')->modal(['modal' => false])->required('请选择用户头像');

        // 添加评价图片多图像框架选择字段，最多可选择6张图片。
        $rule[] = Elm::frameImages('pics', '评价图片：', '/' . config('admin.admin_prefix') . '/setting/uploadPicture?field=pics&type=2')->maxLength(6)->width('896px')->height('480px')->spin(0)->modal(['modal' => false])->props(['footer' => false]);
        $rule[] = Elm::dateTime('create_time', '评论时间：')->placeholder('请选择评论时间');
        // 构建并返回表单对象，表单提交URL为添加虚拟评价的路由URL，设置表单标题。
        return Elm::createForm(Route::buildUrl('systemProductReplyCreate')->build(), $rule)->setTitle('添加虚拟评价');
    }

    /**
     * 根据回复ID和商家ID生成回复表单
     *
     * 本函数用于构建一个用于回复评价的表单。根据传入的商家ID，决定回复的路由是针对商家产品还是系统产品的回复。
     * 表单主要包括一个文本区域，用于输入回复内容。商家ID为0时表示回复系统产品评价，非0时表示回复商家产品评价。
     *
     * @param int $replyId 回复的ID，用于定位具体的评价回复。
     * @param int $merId 商家ID，用于区分是商家产品评价的回复还是系统产品评价的回复。
     * @return Form|string
     */
    public function replyForm(int $replyId, $merId = 0)
    {
        // 根据$merId的值构建不同的路由URL，用于表单的提交动作
        $url = Route::buildUrl($merId ? 'merchantProductReplyReply' : 'systemProductReplyReply', ['id' => $replyId])->build();

        // 构建表单元素，包括一个必需的文本区域用于输入回复内容
        $form = Elm::createForm($url, [
            Elm::textarea('content', '回复内容：')->placeholder('请输入回复内容')->required('请输入回复内容')
        ]);

        // 设置表单的标题为“评价回复”
        $form->setTitle('评价回复');

        // 返回构建好的表单HTML代码
        return $form;
    }

    /**
     * 创建虚拟的商品sku购买记录
     * @param array $productIds
     * @param array $data
     * @return int
     * @author xaboy
     * @day 2020/5/30
     */
    public function createVirtual(array $productIds, array $data)
    {

        //todo 虚拟产品 sku
        $data['is_virtual'] = 1;
        $data['product_type'] = 0;
        $data['order_product_id'] = 0;
        $data['uid'] = 0;
        $data['rate'] = ($data['product_score'] + $data['service_score'] + $data['postage_score']) / 3;
        $data['pics'] = implode(',', $data['pics']);
        if (!$data['create_time']) unset($data['create_time']);
        $productRepository = app()->make(ProductRepository::class);
        $productIds = $productRepository->intersectionKey($productIds);
        $list = [];
        foreach ($productIds as $productId) {
            $data['product_id'] = $productId;
            $data['mer_id'] = $productRepository->productIdByMerId($productId);
            $list[] = $data;
        }
        $this->dao->insertAll($list);
        foreach ($productIds as $productId) {
            Queue::push(UpdateProductReplyJob::class, $productId);
        }
    }

    /**
     * 获取产品的回复率
     * 通过计算给定产品ID的所有评价中，评分在4到5分之间的评价数量占总评价数量的比例，来得到产品的回复率。
     * 使用缓存来提高数据检索的效率，如果缓存中已有数据，则直接返回，否则计算并存储到缓存中。
     *
     * @param int $productId 产品ID，用于查询产品的评价数据。
     * @return array 返回包含最佳评价数量、回复率和总评价数量的数据数组。
     */
    public function getReplyRate(int $productId)
    {
        // 生成缓存键的唯一标识，确保不同产品的评价率缓存不会相互覆盖
        $cache_unique = md5('reply_rate_' . json_encode([$productId]));

        // 尝试从缓存中获取评价率数据
        $res = Cache::get($cache_unique);
        if ($res) return json_decode($res,true);

        // 查询产品的所有非删除评价记录
        $res = $this->selectWhere(['product_id' => $productId,'is_del' =>0]);

        // 统计评分在4到5分之间的评价数量
        $best = $res->where('rate', '>=', 4)->where('rate', '<=', 5)->count();

        // 统计所有评价的数量
        $count = $res->count();

        // 根据最佳评价数量和总评价数量计算回复率，并保留两位小数
        $rate = '';
        if ($best && $count) $rate = bcdiv($best, $count, 2) * 100 . '%';

        // 将最佳评价数量、回复率和总评价数量打包成数组
        $res = compact('best', 'rate', 'count');

        // 将计算得到的评价率数据存储到缓存中，有效期1500秒
        Cache::set($cache_unique, json_encode($res), 1500);

        // 返回评价率数据数组
        return $res;
    }

    /**
     * 回复订单商品评价
     *
     * 该方法用于处理用户对订单商品的评价回复。它首先验证订单和商品是否存在以及是否已评价，
     * 然后计算综合评分，最后在数据库事务中更新评价状态和订单状态，并触发相关通知和统计。
     *
     * @param array $data 包含评价相关信息的数据数组
     * @throws ValidateException 如果订单不存在或商品已评价，则抛出验证异常
     */
    public function reply(array $data)
    {
        // 实例化订单商品仓库，用于后续获取和更新订单商品信息
        $storeOrderProductRepository = app()->make(StoreOrderProductRepository::class);
        // 根据订单商品ID和用户ID获取订单商品信息
        $orderProduct = $storeOrderProductRepository->userOrderProduct($data['order_product_id'], $data['uid']);
        // 验证订单商品和订单信息是否存在
        if (!$orderProduct || !$orderProduct->orderInfo) {
            throw new ValidateException('订单不存在');
        }
        // 验证该订单商品是否已评价
        if ($orderProduct->is_reply) {
            throw new ValidateException('该商品已评价');
        }
        // 将订单商品ID、唯一标识、商家ID、商品类型等数据填充到评价数据中
        $data['product_id'] = $orderProduct['product_id'];
        $data['unique'] = $orderProduct['cart_info']['productAttr']['unique'];
        $data['mer_id'] = $orderProduct->orderInfo['mer_id'];
        $data['product_type'] = $orderProduct['cart_info']['product']['product_type'];
        // 计算综合评分
        $data['rate'] = ($data['product_score'] + $data['service_score'] + $data['postage_score']) / 3;
        // 使用数据库事务处理评价创建和订单状态更新
        Db::transaction(function () use ($data, $orderProduct, $storeOrderProductRepository) {
            // 创建评价记录
            $this->dao->create($data);
            // 更新订单商品为已评价状态
            $orderProduct->is_reply = 1;
            $orderProduct->save();
            // 检查该订单是否所有商品都已评价，如果是，则更新订单状态为交易完成
            if (!$storeOrderProductRepository->noReplyProductCount($orderProduct->orderInfo->order_id)) {
                $orderProduct->orderInfo->status = 3;
                $orderProduct->orderInfo->save();
                // 记录订单状态变更日志
                //TODO 交易完成
                //订单记录
                $storeOrderStatusRepository = app()->make(StoreOrderStatusRepository::class);
                $orderStatus = [
                    'order_id' => $orderProduct->orderInfo->order_id,
                    'order_sn' => $orderProduct->orderInfo->order_sn,
                    'type' => $storeOrderStatusRepository::TYPE_ORDER,
                    'change_message' => '交易完成',
                    'change_type' => $storeOrderStatusRepository::ORDER_STATUS_OVER,
                ];
                app()->make(CircleFinancialRecordRepository::class)->defrost($orderProduct->orderInfo);
                $storeOrderStatusRepository->createSysLog($orderStatus);
            }
        });
        // 发送商家通知，提示有新的评价
        SwooleTaskService::merchant('notice', [
            'type' => 'reply',
            'data' => [
                'title' => '新评价',
                'message' => '您有一条新的商品评价',
                'id' => $data['product_id']
            ]
        ], $data['mer_id']);
        // 更新用户返佣统计
        app()->make(UserBrokerageRepository::class)->incMemberValue($data['uid'], 'member_reply_num', $data['order_product_id']);
        // 推送更新商品评价任务到队列
        Queue::push(UpdateProductReplyJob::class, $orderProduct->product_id);
    }

}
