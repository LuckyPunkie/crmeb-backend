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

namespace app\controller\merchant\store\shipping;

use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\store\shipping\ShippingTemplateUndeliveRepository as repository;

class ShippingTemplateUndelive extends BaseController
{
    protected $repository;

    /**
     * ShippingTemplateUndelive constructor.
     * @param App $app
     * @param repository $repository
     */
    public function __construct(App $app, repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 根据ID删除数据
     *
     * @param int $id 数据ID
     * @return \think\response\Json 返回JSON格式的响应结果
     */
    public function delete($id)
    {
        if (!$this->repository->merExists($this->request->merId(), $id))
            return app('json')->fail('数据不存在');
        // 删除数据
        $this->repository->delete($id);
        return app('json')->success('删除成功');
    }


}
