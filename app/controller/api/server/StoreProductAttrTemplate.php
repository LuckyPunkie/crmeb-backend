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
namespace app\controller\api\server;

use crmeb\basic\BaseController;
use app\common\repositories\store\service\StoreServiceRepository;
use app\validate\merchant\StoreAttrTemplateValidate;
use think\App;
use app\common\repositories\store\StoreAttrTemplateRepository;
use think\exception\HttpResponseException;

/**
 * Class StoreProductAttrTemplate
 * app\controller\api\server
 * 移动客服 - 商品属性模板
 */
class StoreProductAttrTemplate extends BaseController
{
    protected $merId;
    protected $repository;

    public function __construct(App $app, StoreAttrTemplateRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
        $this->merId = $this->request->route('merId');
    }

    /**
     * 列表
     * @return \think\response\Json
     * @author Qinii
     * @day 8/24/21
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['keyword']);
        $data = $this->repository->getList($this->merId, $where, $page, $limit);

        return app('json')->success($data);
    }

    /**
     * 获取所有属性模板
     * @return \think\response\Json
     * @author Qinii
     * @day 8/24/21
     */
    public function getlist()
    {
        return app('json')->success($this->repository->list($this->merId));
    }

    /**
     *  创建
     * @param StoreAttrTemplateValidate $validate
     * @return \think\response\Json
     * @author Qinii
     */
    public function create(StoreAttrTemplateValidate $validate)
    {
        $data = $this->checkParams($validate);
        $data['mer_id'] = $this->merId;
        $this->repository->create($data);

        return app('json')->success('添加成功');
    }

    /**
     * 编辑
     * @param $id
     * @param StoreAttrTemplateValidate $validate
     * @return \think\response\Json
     * @author Qinii
     */
    public function update($id, StoreAttrTemplateValidate $validate)
    {
        $merId = $this->merId;
        if (!$this->repository->merExists($merId, $id))
            return app('json')->fail('数据不存在');
        $data = $this->checkParams($validate);
        $data['mer_id'] = $merId;
        $this->repository->update($id, $data);

        return app('json')->success('编辑成功');
    }

    /**
     * 详情
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function detail($id)
    {
        if (!$this->repository->merExists($this->merId, $id))
            return app('json')->fail('数据不存在');
        return app('json')->success($this->repository->get($id,$this->merId));
    }

    /**
     * 批量删除
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function batchDelete()
    {
        $ids = $this->request->param('ids');
        $merId = $this->merId;
        foreach ($ids as $id){
            if (!$this->repository->merExists($merId, $id))
                return app('json')->fail('ID:'.$id.' 不存在');
        }
        $this->repository->delete($ids, $merId);

        return app('json')->success('删除成功');
    }

    /**
     *  参数验证
     * @param StoreAttrTemplateValidate $validate
     * @return array|mixed|string|string[]
     * @author Qinii
     */
    public function checkParams(StoreAttrTemplateValidate $validate)
    {
        $data = $this->request->params(['template_name', ['template_value', []]]);
        $validate->check($data);
        return $data;
    }


}
