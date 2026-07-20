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

namespace app\common\repositories\store\product;

use app\common\repositories\BaseRepository;
use app\common\dao\store\product\ProductResultDao;

/**
 * 商品助力活动
 */
class ProductResultRepository extends BaseRepository
{
    public function __construct(ProductResultDao $dao)
    {
        $this->dao = $dao;
    }

    public function save(int $id, array $data, $produtType = 0)
    {
        $datum = [
            'product_id' => $id,
            'result' => json_encode(['attr' => $data['attr'] ?? [], 'attrValue' => $data['attrValue'] ?? [], 'params' => $data['params'] ?? []], JSON_UNESCAPED_UNICODE),
            'type' => $produtType,
            'change_time' => time(),
        ];
        $res = $this->dao->getWhere(['product_id' => $id, 'type' => $produtType]);
        if ($res) {
            $this->dao->update($res->id,$datum);
        } else {
            $this->dao->create($datum);
        }
        return true;
    }

    public function getSkuPrices()
    {

    }
}
