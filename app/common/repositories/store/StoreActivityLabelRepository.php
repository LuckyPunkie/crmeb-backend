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

namespace app\common\repositories\store;

use think\exception\ValidateException;
use app\common\repositories\BaseRepository;
use app\common\dao\store\StoreActivityLabelDao;

class StoreActivityLabelRepository extends BaseRepository
{
    public function __construct(StoreActivityLabelDao $dao)
    {
        $this->dao = $dao;
    }

    public function getList($where, $page, $limit)
    {
        $query = $this->dao->getSearch($where)->with([
            'cate' => function ($query) {
                $query->field('id,name');
            }
        ])->order('sort desc,id desc');
        $cout = $query->count();
        $list = $query->page($page, $limit)->select();
        return compact('cout', 'list');

    }

    public function detail($id)
    {
        $res = $this->dao->getSearch(['id' => $id])->with(['cate'])->find();
        if (!$res)
            throw new ValidateException('数据不存在');

        return $res;
    }

    public function select($where)
    {
        return $this->dao->getSearch($where)->where('label_cate','<>',1)->order('sort desc,id desc')->column('id,label_name');
    }
 }
