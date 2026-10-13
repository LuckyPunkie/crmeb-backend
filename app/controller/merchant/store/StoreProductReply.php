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


namespace app\controller\merchant\store;


use app\common\repositories\store\product\ProductReplyRepository;
use crmeb\basic\BaseController;
use think\App;

/**
 * StoreProductReply控制器
 * Class StoreProductReply
 * @package app\controller\store
 */
class StoreProductReply extends BaseController
{
    protected $repository;

    /**
     * 构造函数
     *
     * @param App $app 应用实例
     * @param ProductReplyRepository $replyRepository 商品回复仓库实例
     */
    public function __construct(App $app, ProductReplyRepository $replyRepository)
    {
        // 调用父类构造函数
        parent::__construct($app);
        // 设置商品回复仓库实例
        $this->repository = $replyRepository;
    }

    /**
     * 修改商品回复排序
     *
     * @param int $id 商品回复ID
     * @return mixed
     */
    public function changeSort($id)
    {
        // 获取商家ID
        $merId = $this->request->merId();
        // 判断商品回复是否存在
        if (!$this->repository->merExists($merId, $id))
            return app('json')->fail('数据不存在');

        // 获取排序值
        $sort = (int)$this->request->param('sort');

        // 更新商品回复排序
        $this->repository->update($id, compact('sort'));
        // 返回操作结果
        return app('json')->success('修改成功');
    }
}
