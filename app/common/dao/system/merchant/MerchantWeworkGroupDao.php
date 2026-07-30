<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------

namespace app\common\dao\system\merchant;

use app\common\dao\BaseDao;
use app\common\model\system\merchant\MerchantWeworkGroup;

class MerchantWeworkGroupDao extends BaseDao
{
    protected function getModel(): string
    {
        return MerchantWeworkGroup::class;
    }
}
