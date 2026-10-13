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


namespace app\controller\api\store\product;

use app\common\repositories\store\order\StoreOrderProductRepository;
use app\validate\api\ProductReplyValidate;
use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\store\product\ProductReplyRepository as repository;

class StoreReply extends BaseController
{
    /**
     * @var repository
     */
    protected $repository;
    protected $userInfo;

    /**
     * StoreProduct constructor.
     * @param App $app
     * @param repository $repository
     */
    public function __construct(App $app, repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
        $this->userInfo = $this->request->isLogin() ? $this->request->userInfo() : null;
    }

    /**
     * 根据请求参数获取特定类型的产品列表
     *
     * 本函数旨在根据前端请求中的参数，以及指定的产品ID，查询并返回特定类型的产品列表。
     * 具体实现包括获取请求中的分页信息、筛选条件，然后调用仓库层的方法获取数据，并以JSON格式返回。
     *
     * @param int $id 产品ID，用于限定查询的产品范围
     * @return json 返回查询结果的JSON对象，包含产品列表信息
     */
    public function lst($id)
    {
        // 获取请求中的分页信息
        [$page, $limit] = $this->getPage();

        // 构建查询条件，包括产品类型和产品ID
        $where= $this->request->params(['type','']);
        $where['product_id'] = $id;

        // 调用仓库层的getApiList方法获取数据，并返回成功响应的JSON对象
        return app('json')->success($this->repository->getApiList($where, $page, $limit));
    }

    /**
     * 根据订单产品ID获取订单产品详情
     *
     * 本函数旨在通过提供的订单产品ID，检索特定用户的订单产品详情。
     * 它首先验证订单是否存在且未被评价，然后返回订单产品的详细信息。
     * 如果订单不存在或已评价，则返回相应的错误信息。
     *
     * @param int $id 订单产品ID
     * @param StoreOrderProductRepository $orderProductRepository 订单产品仓库对象，用于订单产品的数据操作
     * @return mixed 返回订单产品的详细信息数组，如果订单不存在或已评价，则返回错误信息
     */
    public function product($id, StoreOrderProductRepository $orderProductRepository)
    {
        // 根据订单产品ID和用户ID获取订单产品信息
        $orderProduct = $orderProductRepository->userOrderProduct((int)$id, $this->request->uid());

        // 验证订单产品是否存在及其关联的订单信息
        if (!$orderProduct || !$orderProduct->orderInfo) {
            // 如果订单产品不存在或没有关联的订单信息，则返回“订单不存在”的错误信息
            return app('json')->fail('订单不存在');
        }

        // 检查订单产品是否已评价
        if ($orderProduct->is_reply) {
            // 如果订单产品已评价，则返回“该商品已评价”的错误信息
            return app('json')->fail('该商品已评价');
        }

        // 如果订单产品存在且未被评价，则返回订单产品的详细信息
        return app('json')->success($orderProduct->toArray());
    }

    /**
     * 回复商品评价
     *
     * 本函数用于处理用户对商品的评价回复。它从请求中获取评价相关数据，
     * 验证数据的合法性，然后将用户评价信息保存到数据库。
     *
     * @param int $id 订单产品ID，用于关联评价和具体的产品。
     * @param ProductReplyValidate $validate 评价验证器，用于数据验证。
     * @return json 返回一个表示操作成功或失败的JSON响应。
     */
    public function reply($id, ProductReplyValidate $validate)
    {
        // 从请求中获取评价相关参数
        $data = $this->request->params(['comment', 'product_score', 'service_score', 'postage_score', ['pics', []]]);

        // 使用验证器检查获取的数据是否合法
        $validate->check($data);

        // 获取当前请求的用户信息
        $user = $this->request->userInfo();

        // 将用户ID、订单产品ID、用户昵称和头像添加到评价数据中
        $data['uid'] = $this->request->uid();
        $data['order_product_id'] = (int)$id;
        $data['nickname'] = $user['nickname'];
        $data['avatar'] = $user['avatar'];

        // 将评价数据提交到仓库进行保存
        $this->repository->reply($data);

        // 返回一个表示操作成功的JSON响应
        return app('json')->success('评价成功');
    }

}
