<?php

namespace app\common\dao\system\merchant;

use app\common\dao\BaseDao;
use app\common\model\system\merchant\MerchantLabel;

class MerchantLabelDao extends BaseDao
{
    protected function getModel(): string
    {
        return MerchantLabel::class;
    }

    public function lst(array $where, int $page, int $limit): array
    {
        $query = MerchantLabel::getDB();
        if (!empty($where['label_name'])) {
            $query->whereLike('label_name', '%' . $where['label_name'] . '%');
        }
        $count = $query->count();
        $list  = $query->page($page, $limit)->order('id', 'desc')->select()->toArray();
        return compact('count', 'list');
    }

    public function getAll(): array
    {
        return MerchantLabel::getDB()->order('id', 'asc')->select()->toArray();
    }
}
