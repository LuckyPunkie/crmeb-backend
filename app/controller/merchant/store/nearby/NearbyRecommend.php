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

namespace app\controller\merchant\store\nearby;

use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\store\nearby\NearbyShopRecommendRepository;
use app\validate\merchant\nearby\NearbyRecommendValidate;

/**
 * 商户后台 - 推荐菜管理
 */
class NearbyRecommend extends BaseController
{
    protected $repository;

    public function __construct(App $app, NearbyShopRecommendRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 推荐菜列表
     */
    public function lst()
    {
        $merId = $this->request->merId();
        [$page, $limit] = $this->getPage();
        $data = $this->repository->getList($merId, $page, $limit);
        return app('json')->success($data);
    }

    /**
     * 创建推荐菜
     */
    public function create(NearbyRecommendValidate $validate)
    {
        $data = $this->request->params(['name', 'image', 'mention_count', 'like_count', 'tag', 'sort']);
        $validate->check($data);
        $data['mer_id'] = $this->request->merId();
        $this->repository->create($data);
        return app('json')->success('推荐菜添加成功');
    }

    /**
     * 更新推荐菜
     */
    public function update($id, NearbyRecommendValidate $validate)
    {
        $data = $this->request->params(['name', 'image', 'mention_count', 'like_count', 'tag', 'sort', 'is_show']);
        $validate->check($data);

        $merId = $this->request->merId();
        if (!$this->repository->merHas($merId, (int)$id, 0)) {
            return app('json')->fail('无权操作此推荐菜');
        }

        $this->repository->update((int)$id, $data);
        return app('json')->success('推荐菜更新成功');
    }

    /**
     * 删除推荐菜
     */
    public function delete($id)
    {
        $merId = $this->request->merId();
        if (!$this->repository->merHas($merId, (int)$id, 0)) {
            return app('json')->fail('无权操作此推荐菜');
        }

        $this->repository->update((int)$id, ['is_del' => 1]);
        return app('json')->success('推荐菜删除成功');
    }
}
