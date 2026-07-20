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
use app\common\repositories\store\nearby\NearbyShopPackageRepository;
use app\validate\merchant\nearby\NearbyPackageValidate;

/**
 * 商户后台 - 到店套餐管理
 */
class NearbyPackage extends BaseController
{
    protected $repository;

    public function __construct(App $app, NearbyShopPackageRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 套餐列表
     */
    public function lst()
    {
        $merId = $this->request->merId();
        [$page, $limit] = $this->getPage();
        $data = $this->repository->getList($merId, $page, $limit);
        return app('json')->success($data);
    }

    /**
     * 创建套餐
     */
    public function create(NearbyPackageValidate $validate)
    {
        $data = $this->request->params([
            'name', 'image', 'price', 'original_price',
            'discount', 'tags', 'content', 'sort'
        ]);
        $validate->check($data);
        // tags JSON格式校验
        if (!empty($data['tags'])) {
            $tagsDecoded = json_decode($data['tags'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return app('json')->fail('tags 格式错误，需为合法 JSON 字符串');
            }
            // 若JSON解码成功但值为非数组，也视为无效
            if (!is_array($tagsDecoded)) {
                return app('json')->fail('tags 值须为 JSON 数组');
            }
        }
        $data['mer_id'] = $this->request->merId();
        $this->repository->create($data);
        return app('json')->success('套餐添加成功');
    }

    /**
     * 更新套餐
     */
    public function update($id, NearbyPackageValidate $validate)
    {
        $data = $this->request->params([
            'name', 'image', 'price', 'original_price',
            'discount', 'tags', 'content', 'sort', 'is_show'
        ]);
        $validate->check($data);

        // tags JSON格式校验
        if (!empty($data['tags'])) {
            $tagsDecoded = json_decode($data['tags'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return app('json')->fail('tags 格式错误，需为合法 JSON 字符串');
            }
            if (!is_array($tagsDecoded)) {
                return app('json')->fail('tags 值须为 JSON 数组');
            }
        }

        $merId = $this->request->merId();
        if (!$this->repository->merHas($merId, (int)$id, 0)) {
            return app('json')->fail('无权操作此套餐');
        }

        $this->repository->update((int)$id, $data);
        return app('json')->success('套餐更新成功');
    }

    /**
     * 删除套餐
     */
    public function delete($id)
    {
        $merId = $this->request->merId();
        if (!$this->repository->merHas($merId, (int)$id, 0)) {
            return app('json')->fail('无权操作此套餐');
        }

        $this->repository->update((int)$id, ['is_del' => 1]);
        return app('json')->success('套餐删除成功');
    }
}
