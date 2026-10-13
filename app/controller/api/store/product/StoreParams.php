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

use app\common\repositories\store\parameter\ParameterValueRepository;
use app\common\repositories\store\product\ProductRepository;
use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\store\parameter\ParameterTemplateRepository;

class StoreParams extends BaseController
{
    /**
     * @var ParameterTemplateRepository
     */
    protected $repository;

    /**
     * ParameterTemplateRepository constructor.
     * @param App $app
     * @param ParameterTemplateRepository $repository
     */
    public function __construct(App $app, ParameterTemplateRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }


    /**
     * @Author:Qinii
     * @Date: 2020/5/28
     * @return mixed
     */
    public function select()
    {
        $where = $this->request->params(['keyword', 'cate_id','mer_id','mer_cate_id','cate_pid']);
        $isPc = $this->request->param('is_pc',false);
        if (
            $where['keyword'] == '' &&
            $where['cate_id'] == '' &&
            $where['mer_cate_id'] == '' &&
            $where['cate_pid'] == ''
        ) {
            return app('json')->success(['count' => 0,'list' => []]);
        }
        $data = $this->repository->getApiList($where,$isPc);
        return app('json')->success($data);
    }

    /**
     * 根据参数规格ID获取参数值
     * @param $id
     * @param ParameterValueRepository $parameterValueRepository
     * @return \think\response\Json
     * @author Qinii
     * @day 2023/10/21
     */
    public function getValue($id, ParameterValueRepository $parameterValueRepository)
    {
        $data = $parameterValueRepository->getOptions(['parameter_id' => $id]);
        return app('json')->success($data);
    }

}
