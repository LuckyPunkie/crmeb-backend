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
namespace app\controller\merchant\store\product;

use app\common\repositories\store\product\ProductCdkeyRepository;
use app\validate\merchant\ProductCdkeyValidate;
use crmeb\basic\BaseController;
use think\App;
use think\exception\ValidateException;

/**
 * Class CdkeyLibrary
 * app\controller\merchant\store\product
 * 卡密信息
 */
class ProductCdkey extends BaseController
{
    protected  $repository ;

    /**
     * ProductGroup constructor.
     * @param App $app
     * @param ProductCdkeyRepository $repository
     */
    public function __construct(App $app ,ProductCdkeyRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }


    /**
     * 列表
     * @return \think\response\Json
     * @author Qinii
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['library_id','status']);
        $where['mer_id'] = $this->request->merId();
        if (!$where['library_id']) return app('json')->fail('参数错误');
        $data = $this->repository->getList($where,$page,$limit);
        return app('json')->success($data);
    }


    /**
     * 添加表单
     * @return \think\response\Json
     * @author Qinii
     */
    public function create()
    {
        $data = $this->request->params(['csList','library_id']);
        app()->make(ProductCdkeyValidate::class)->check($data);
        $this->repository->save($data,$this->request->merId());
        return app('json')->success('添加成功');
    }


    /**
     * 修改
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function update($id)
    {
        $data = $this->request->params(['key','pwd']);
        $data['mer_id'] = $this->request->merId();
        $this->repository->edit($id,$data);
        return app('json')->success('修改成功');
    }

    /**
     * 删除
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function delete($id)
    {
        $this->repository->dostory($id,$this->request->merId());
        return app('json')->success('删除成功');
    }

    /**
     *  批量删除
     * @return void
     * @author Qinii
     */
    public function batchDelete()
    {
        $data = $this->request->params(['ids']);

        $this->repository->batchDelete($data['ids'],$this->request->merId());
        return app('json')->success('删除成功');
    }
}
