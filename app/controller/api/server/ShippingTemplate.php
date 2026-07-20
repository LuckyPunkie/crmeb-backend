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

use think\App;
use crmeb\basic\BaseController;
use think\exception\HttpResponseException;
use app\validate\merchant\ShippingTemplateValidate;
use app\common\repositories\store\service\StoreServiceRepository;
use app\common\repositories\store\shipping\ShippingTemplateRepository;

/**
 * Class ShippingTemplate
 * app\controller\api\server
 * 移动端客服运费模板管理
 */
class ShippingTemplate extends BaseController
{

    protected $merId;
    protected $repository;

    public function __construct(App $app, ShippingTemplateRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
        $this->merId = $this->request->route('merId');
    }

    /**
     * 列表 - 下啦筛选
     * @return \think\response\Json
     * @author Qinii
     * @day 8/24/21
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['type','name']);
        return app('json')->success($this->repository->search($this->merId, $where, $page, $limit));
    }

    /**
     * 列表
     * @return \think\response\Json
     * @author Qinii
     * @day 8/24/21
     */
    public function getList()
    {
        return app('json')->success($this->repository->getList($this->merId));
    }

    /**
     * 创建
     * @param ShippingTemplateValidate $validate
     * @return \think\response\Json
     * @author Qinii
     * @day 8/24/21
     */
    public function create(ShippingTemplateValidate $validate)
    {
        $data = $this->checkParams($validate);
        $data['mer_id'] = $this->merId;
        $this->repository->create($data);
        return app('json')->success('添加成功');
    }

    /**
     * 详情
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     * @day 8/24/21
     */
    public function detail($id)
    {
        if(!$this->repository->merExists($this->merId,$id))
            return app('json')->fail('数据不存在');
        return app('json')->success($this->repository->getOne($id,1));
    }

    /**
     * 编辑
     * @param $id
     * @param ShippingTemplateValidate $validate
     * @return \think\response\Json
     * @author Qinii
     * @day 8/24/21
     */
    public function update($id, ShippingTemplateValidate $validate)
    {
        $data = $this->checkParams($validate);
        if(!$this->repository->merExists($this->merId,$id))
            return app('json')->fail('数据不存在');
        $this->repository->update($id,$data, $this->merId);

        return app('json')->success('编辑成功');
    }

    /**
     * 删除
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     * @day 8/24/21
     */
    public function batchDelete()
    {
        $ids = $this->request->param('ids');
        foreach ($ids as $id) {
            $this->repository->check($this->merId, $id);
        }
        $this->repository->delete($ids);
        return app('json')->success('删除成功');
    }

    /**
     * 参数验证
     * @param ShippingTemplateValidate $validate
     * @return array
     * @author Qinii
     * @day 8/24/21
     */
    public function checkParams(ShippingTemplateValidate $validate)
    {
        $data = $this->request->params(['name','type','appoint','undelivery','region','free','undelives','sort','info']);
        $validate->check($data);
        return $data;
    }
}
