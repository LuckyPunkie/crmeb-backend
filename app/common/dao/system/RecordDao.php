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


namespace app\common\dao\system;

use app\common\dao\BaseDao;
use app\common\model\system\Record;

class RecordDao extends BaseDao
{

    protected function getModel(): string
    {
        return Record::class;
    }

    /**
     *  根据类型自增
     * @param string $type
     * @param int $linkId
     * @param $num
     */
    public function incType(string $type, int $linkId, $num = 1, $data = [])
    {
        $data = [
            'uid' => $data['uid'] ?? 0,
            'title' => $data['title'] ?? $type,
            'type' => $type,
            'link_id' => $linkId
        ];
        $res = $this->findOrCreate($data);
        $res->num = $res['num'] + $num;
        $res->save();
    }

    /**
     *  根据类型自减
     * @param string $type
     * @param int $linkId
     * @param $num
     */
    public function decType(string $type, int $linkId, $num = 1)
    {
        $this->getSearch(['type' => $type])->where('link_id', $linkId)->where('num','>=',1)->dec('num', $num)->update();
    }
}
