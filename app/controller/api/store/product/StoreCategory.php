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

use think\App;
use think\facade\Cache;
use crmeb\basic\BaseController;
use app\common\repositories\store\StoreCategoryRepository as repository;

class StoreCategory extends BaseController
{
    protected $repository;

    /**
     * ProductCategory constructor.
     * @param App $app
     * @param repository $repository
     */
    public function __construct(App $app, repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 获取热门列表并以特定格式返回
     * 此方法主要用于从仓库中获取热门数据，并对其进行格式化处理，以便前端以特定的结构展示。
     * @return \Illuminate\Http\JsonResponse 返回格式化后的热门列表数据
     */
    public function lst()
    {
        $prefix = env('QUEUE_NAME','merchant').'_categorylist';
        $cachekey=$prefix.'_list';
        $res = Cache::get($cachekey);
        if ($res) {
            $categorylist = json_decode($res, true);
            $data= $categorylist;
            return app('json')->success($data);
        }
        // 获取热门数据，参数0表示获取所有热门数据
        $data = $this->repository->getHot(0);
        // 获取列表数据，并指定参数0和1，用于特定的API数据格式化
        $list = $this->repository->getApiFormatList(0,1);
        // 初始化用于存储处理后数据的数组
        $ret =[];
        // 遍历列表数据，对其中的嵌套数据进行处理
        foreach ($list as $key => $value) {
            // 检查当前项是否包含子项
            if (isset($value['children'])) {
                $level = [];
                // 对当前项的子项进行遍历
                foreach ($value['children'] as $child) {
                    // 检查子项是否还包含子项，用于多级嵌套处理
                    if (isset($child['children'])) {
                        $level[] = $child;
                    }
                }
                // 如果当前项的子项经过处理后不为空，则将处理后的子项添加到结果数组中
                if (isset($level) && !empty($level)) {
                    $value['children'] = $level;
                    $ret[] = $value;
                }
            }
        }
        // 将处理后的数据合并到原始热门数据中，并返回
        $data['list'] = $ret;
        Cache::tag('categorylist')->set($cachekey, json_encode($data), 30);
        return app('json')->success($data);
    }


    /**
     * 获取指定父级ID下的子级数据
     *
     * 本函数通过接收请求中的父级ID参数，然后调用仓库中的方法来获取对应父级ID下的子级数据。
     * 最后，使用JSON响应助手来构造并返回成功响应，其中包含获取到的子级数据。
     *
     * @return \think\Response 返回一个包含成功获取的子级数据的JSON响应
     */
    public function children()
    {
        // 将请求中的pid参数转换为整数类型
        $pid = (int)$this->request->param('pid');

        // 构造并返回一个成功的JSON响应，其中包含仓库中查询到的指定父级ID下的子级数据
        return app('json')->success($this->repository->children($pid));
    }


    /**
     * 获取热门分类排名信息
     *
     * 本函数用于查询并返回热门分类排名列表。排名列表基于商品的热度（销售量、浏览量等）
     * 和创建时间进行排序。通过调用repository中的getSearch方法来实现查询，
     * 查询条件包括分类级别、商家ID、是否显示以及类型等。
     *
     * @return \think\response\Json 返回一个包含热门分类排名数据的JSON响应。
     */
    public function cateHotRanking()
    {
        // 根据系统配置的热门排名级别、默认商家ID、显示状态和类型进行查询
        // 查询结果按照排序值降序和创建时间降序排列
        $data = $this->repository->getSearch([
            'level' => systemConfig('hot_ranking_lv') ?:0,
            'mer_id' => 0,
            'is_show' => 1,
            'type' => 0
        ])->order('sort DESC,create_time DESC')->select();

        // 返回查询结果的JSON响应，响应状态为成功
        return app('json')->success($data);
    }
}
