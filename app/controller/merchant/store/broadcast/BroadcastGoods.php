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


namespace app\controller\merchant\store\broadcast;


use app\common\repositories\store\broadcast\BroadcastGoodsRepository;
use app\validate\merchant\BroadcastGoodsValidate;
use crmeb\basic\BaseController;
use think\App;

class BroadcastGoods extends BaseController
{
    protected $repository;

    public function __construct(App $app, BroadcastGoodsRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 获取列表数据
     * 本函数用于根据请求参数获取指定条件的列表数据，支持分页。
     * @return json 返回包含列表数据的JSON对象，成功时包含数据列表和其它相关信息，失败时包含错误信息。
     */
    public function lst()
    {
        // 解析并获取当前请求的页码和每页数据量
        [$page, $limit] = $this->getPage();

        // 从请求中获取查询参数，包括状态标签、关键词、商家有效性以及广播商品ID
        $where = $this->request->params(['status_tag', 'keyword', 'mer_valid','broadcast_goods_id']);

        // 调用repository中的getList方法获取数据，并返回成功响应的JSON对象
        return app('json')->success($this->repository->getList($this->request->merId(), $where, $page, $limit));
    }

    /**
     * 根据ID获取详情信息
     *
     * 本函数旨在通过提供的ID检索特定资源的详细信息。它首先验证所请求的资源是否存在，
     * 如果存在，则返回该资源的详细信息；如果不存在，则返回一个错误消息。
     *
     * @param int $id 要查询的资源ID
     * @return \Illuminate\Http\JsonResponse 返回一个JSON响应，包含资源的详细信息或错误消息
     */
    public function detail($id)
    {
        // 检查请求的资源是否存在
        if (!$this->repository->merExists($id, $this->request->merId()))
        {
            // 如果资源不存在，则返回一个数据不存在的错误信息
            return app('json')->fail('数据不存在');
        }

        // 如果资源存在，则获取该资源的详细信息，并附加产品信息后返回
        return app('json')->success($this->repository->get($id)->append(['product'])->toArray());
    }

    /**
     * 创建表单
     *
     * 本函数用于生成并返回表单数据。它通过调用repository的createForm方法来创建表单，
     * 然后将表单数据转换为合适的格式，最后以成功响应的形式返回。
     *
     * @return \Illuminate\Http\JsonResponse 表单数据的成功响应
     */
    public function createForm()
    {
        // 使用app助手函数获取JSON响应实例，并将表单数据转换为合适格式后返回
        return app('json')->success(formToData($this->repository->createForm()));
    }

    /**
     * 检查广播商品的参数
     *
     * 本函数用于在创建或更新广播商品时，验证和处理相关的参数。
     * 它首先从请求中提取指定的参数，然后使用验证器对这些参数进行验证。
     * 验证通过后，它会处理产品ID，将其转换为ID值，最后返回处理后的数据。
     *
     * @param BroadcastGoodsValidate $validate 验证器实例，用于验证参数的合法性
     * @return array 包含验证通过的参数的数据数组，其中product_id被转换为ID值
     */
    protected function checkParams(BroadcastGoodsValidate $validate)
    {
        // 从请求中提取'name', 'cover_img', 'product_id', 'price'参数
        $data = $this->request->params(['name', 'cover_img', 'product_id', 'price']);
        // 使用验证器检查提取的参数是否符合规定
        $validate->check($data);
        // 处理product_id，将其从数组形式转换为ID值
        $data['product_id'] = $data['product_id']['id'];
        // 返回处理后的参数数据
        return $data;
    }

    /**
     * 创建广播商品
     *
     * 本函数用于处理广播商品的创建逻辑。它首先通过验证数据的合法性，然后调用仓库接口进行商品创建。
     * 创建成功后，返回一个表示操作成功的JSON响应。
     *
     * @param BroadcastGoodsValidate $validate 数据验证对象，用于校验请求数据的合法性
     * @return \Illuminate\Http\JsonResponse 返回一个表示操作成功的JSON响应
     */
    public function create(BroadcastGoodsValidate $validate)
    {
        // 调用仓库接口，使用当前请求中的商家ID和校验后的参数创建广播商品
        $this->repository->create($this->request->merId(), $this->checkParams($validate));

        // 返回一个表示操作成功的JSON响应
        return app('json')->success('创建成功');
    }

    /**
     * 批量创建直播商品
     *
     * 本函数用于处理批量创建直播商品的请求。它首先从请求中获取商品列表，然后对每个商品进行验证，
     * 最后将商品信息批量创建到直播商品库中。
     *
     * @param BroadcastGoodsValidate $validate 直播商品验证器，用于验证商品信息的合法性。
     * @return json 返回操作结果的JSON对象，成功时包含成功消息，失败时包含错误消息。
     */
    public function batchCreate(BroadcastGoodsValidate $validate)
    {
        // 从请求中获取名为'goods'的参数，如果不存在则默认为空数组
        $goods = $this->request->param('goods', []);
        // 检查商品列表是否为空，如果为空则返回错误信息
        if (!count($goods)) return app('json')->fail('请选中商品');

        // 启用批量验证模式
        $validate->isBatch();
        // 遍历商品列表，对每个商品进行验证
        foreach ($goods as $item) {
            $validate->check((array)$item);
        }
        // 批量创建直播商品，使用当前请求的商家ID作为参数
        $this->repository->batchCreate($this->request->merId(), $goods);
        // 返回成功消息
        return app('json')->success('创建成功');
    }

    /**
     * 根据给定的ID更新表单数据。
     * 此方法首先验证指定的ID是否存在对应的实体，如果存在，则更新表单数据；如果不存在，则返回错误信息。
     * 使用此方法可以确保只有存在的实体才能被更新，从而避免了无效的更新操作。
     *
     * @param int $id 需要更新的表单数据的ID。
     * @return \Illuminate\Http\JsonResponse 更新成功时返回包含更新后数据的JSON响应，更新失败时返回错误信息的JSON响应。
     */
    public function updateForm($id)
    {
        // 检查指定ID的实体是否存在，如果不存在则返回错误信息。
        if (!$this->repository->merExists($id, $this->request->merId()))
            return app('json')->fail('数据不存在');

        // 更新表单数据，并将更新后的数据转换为JSON格式返回。
        return app('json')->success(formToData($this->repository->updateForm($id)));
    }


    /**
     * 更新广播商品信息
     *
     * 本函数用于处理指定广播商品的更新操作。它首先验证传入的数据是否有效，
     * 然后检查指定的商家是否存在，最后更新广播商品的信息。
     *
     * @param int $id 广播商品的ID，用于指定要更新的广播商品。
     * @param BroadcastGoodsValidate $validate 数据验证对象，用于验证传入的更新数据是否有效。
     * @return json 返回一个JSON格式的响应，包含操作的结果信息。
     */
    public function update($id, BroadcastGoodsValidate $validate)
    {
        // 检查商家是否存在，如果商家不存在，则返回错误信息。
        if (!$this->repository->merExists($id, $this->request->merId()))
            return app('json')->fail('数据不存在');

        // 使用验证后的参数更新广播商品信息。
        $this->repository->update($id, $this->checkParams($validate));

        // 更新成功后，返回成功的响应信息。
        return app('json')->success('编辑成功');
    }

    /**
     * 标记方法
     *
     * 本方法用于对特定ID的对象进行标记操作。它首先尝试从请求中获取标记参数，
     * 然后验证指定ID的对象是否存在，并且操作者是否有权限对其进行标记。
     * 如果对象存在且操作者有权限，那么将执行标记操作，并返回成功的响应。
     * 如果对象不存在，则返回一个表示失败的响应。
     *
     * @param int $id 需要被标记的对象的ID
     * @return json 标记操作的结果，成功或失败的响应
     */
    public function mark($id)
    {
        // 将请求中的mark参数转换为字符串类型
        $mark = (string)$this->request->param('mark');

        // 检查指定ID的对象是否存在，并且当前操作者是否有权限对其进行标记
        if (!$this->repository->merExists($id, $this->request->merId()))
            // 如果对象不存在或无权限，则返回一个表示失败的JSON响应
            return app('json')->fail('数据不存在');

        // 对指定ID的对象执行标记操作
        $this->repository->mark($id, $mark);

        // 标记操作成功，返回一个表示成功的JSON响应
        return app('json')->success('修改成功');
    }

    /**
     * 修改商户状态
     *
     * 本函数用于根据请求中的$id修改指定商户的状态。状态通过请求中的is_show参数决定，
     * 默认为0（不显示），如果is_show参数为1，则改为1（显示）。
     *
     * @param int $id 商户的ID
     * @return json 返回一个JSON格式的响应，成功时包含成功消息，失败时包含错误消息。
     */
    public function changeStatus($id)
    {
        // 根据请求中的is_show参数决定商户的显示状态，存在$ishow变量中
        $isShow = $this->request->param('is_show') == 1 ? 1 : 0;

        // 检查商户是否存在，如果不存在，则返回失败消息
        if (!$this->repository->merExists($id, $this->request->merId()))
            return app('json')->fail('数据不存在');

        // 更新商户的显示状态
        $this->repository->isShow($id, $isShow);

        // 返回成功消息
        return app('json')->success('修改成功');
    }

    /**
     * 删除指定ID的实体。
     *
     * 此方法首先验证请求的ID是否存在，如果不存在，则返回一个错误响应。
     * 如果ID存在，它将尝试删除该实体，并返回一个成功响应。
     * 这个方法体现了业务逻辑中对实体进行删除的操作，它封装了验证和删除的细节，
     * 并通过返回标准的JSON响应来与调用者进行交互。
     *
     * @param int $id 要删除的实体的ID。
     * @return \Illuminate\Http\JsonResponse 删除成功时返回成功的JSON响应，删除失败（ID不存在）时返回失败的JSON响应。
     */
    public function delete($id)
    {
        // 检查指定ID的实体是否存在，如果不存在则返回失败响应。
        if (!$this->repository->merExists($id, $this->request->merId()))
            return app('json')->fail('数据不存在');

        // 删除指定ID的实体。
        $this->repository->delete((int)$id);

        // 删除成功，返回成功响应。
        return app('json')->success('删除成功');
    }


}
