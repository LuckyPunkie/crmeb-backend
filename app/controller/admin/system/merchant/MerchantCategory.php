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


namespace app\controller\admin\system\merchant;


use crmeb\basic\BaseController;
use app\common\repositories\system\merchant\MerchantCategoryRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\validate\admin\MerchantCategoryValidate;
use FormBuilder\Exception\FormBuilderException;
use think\App;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 商户分类
 */
class MerchantCategory extends BaseController
{
    /**
     * @var MerchantCategoryRepository
     */
    protected $repository;

    /**
     * MerchantCategory constructor.
     * @param App $app
     * @param MerchantCategoryRepository $repository
     */
    public function __construct(App $app, MerchantCategoryRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 列表
     * @return mixed
     * @throws DbException
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-05-06
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->repository->getList([], $page, $limit));
    }

    /**
     * 获取所有分类
     * @return mixed
     * @author xaboy
     * @day 2020-05-06
     */
    public function getOptions()
    {
        return app('json')->success($this->repository->allOptions());
    }

    /**
     * 验证参数
     * @param MerchantCategoryValidate $validate
     * @return array
     * @author xaboy
     * @day 2020-05-06
     */
    /**
     * 分类树（admin 前端两级展示用）
     */
    public function tree()
    {
        return app('json')->success($this->repository->getTree());
    }

    public function checkParams(MerchantCategoryValidate $validate)
    {
        $data = $this->request->params(['category_name', ['commission_rate', 0], ['pid', 0], ['sort', 0]]);
        $validate->check($data);
        $data['commission_rate'] = bcdiv($data['commission_rate'], 100, 4);
        return $data;
    }

    public function create(MerchantCategoryValidate $validate)
    {
        $data = $this->checkParams($validate);
        $this->repository->create($data);
        app('cache')->delete('nearby_category_tree');
        return app('json')->success('添加成功');
    }

    public function update($id, MerchantCategoryValidate $validate)
    {
        $data = $this->checkParams($validate);
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        $this->repository->update($id, $data);
        app('cache')->delete('nearby_category_tree');
        return app('json')->success('编辑成功');
    }

    public function delete($id, MerchantRepository $merchantRepository)
    {
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        if ($merchantRepository->fieldExists('category_id', $id))
            return app('json')->fail('存在商户,无法删除');
        // 同时删除子分类
        \app\common\model\system\merchant\MerchantCategory::getDB()
            ->where('pid', $id)->delete();
        $this->repository->delete($id);
        app('cache')->delete('nearby_category_tree');
        return app('json')->success('删除成功');
    }
}
