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

namespace app\common\repositories\store\parameter;

use app\common\repositories\BaseRepository;
use app\common\dao\store\parameter\ParameterProductDao;

class ParameterProductRepository extends BaseRepository
{
    /**
     * @var ParameterProductDao
     */
    protected $dao;


    /**
     * ParameterRepository constructor.
     * @param ParameterProductDao $dao
     */
    public function __construct(ParameterProductDao  $dao)
    {
        $this->dao = $dao;
    }


}

