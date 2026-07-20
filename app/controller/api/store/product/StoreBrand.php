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

use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\store\StoreBrandRepository as repository;

class StoreBrand extends BaseController
{
    /**
     * @var repository
     */
    protected $repository;

    /**
     * StoreBrand constructor.
     * @param App $app
     * @param repository $repository
     */
    public function __construct(App $app, repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 查询类别列表
     * 本函数通过接收前端发送的请求参数，获取搜索条件，然后调用repository中的方法，
     * 根据这些条件查询类别的信息。搜索条件可能包括关键字、类别ID、商户ID、商户类别ID和父类别ID。
     *
     * @return json
     * 返回查询结果的JSON对象，包含搜索到的类别信息。使用app('json')->success()方法封装返回数据，
     * 以便于统一处理响应格式。
     */
    public function lst()
    {
        // 从请求中获取搜索参数
        $where = $this->request->params(['keyword', 'cate_id','mer_id','mer_cate_id','pid']);
        // 调用repository的getCategorySearch方法进行类别搜索，并返回搜索结果
        return app('json')->success($this->repository->getCategorySearch($where));
    }


}
