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

use app\common\repositories\store\PriceRuleRepository;
use app\common\repositories\store\product\ProductAttrRepository;
use app\common\repositories\store\product\ProductAttrValueRepository;
use app\common\repositories\store\product\SpuRepository;
use app\common\repositories\store\StoreCategoryRepository;
use app\common\repositories\system\groupData\GroupDataRepository;
use app\common\repositories\user\UserMerchantRepository;
use app\common\repositories\user\UserVisitRepository;
use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\store\product\ProductRepository as repository;
use think\facade\Cache;
use app\common\repositories\system\CacheRepository;

class StoreProduct extends BaseController
{
    use BindSpreadTrait;

    /**
     * @var repository
     */
    protected $repository;
    protected $userInfo = null;

    /**
     * StoreProduct constructor.
     * @param App $app
     * @param repository $repository
     */
    public function __construct(App $app, repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
        $this->userInfo = $this->request->isLogin() ? $this->request->userInfo() : null;
    }


    /**
     * 列出搜索结果
     *
     * 本函数用于根据用户请求的参数，从仓库中获取搜索结果的列表。搜索结果可以根据关键字、类别、价格范围、品牌、评级等条件进行筛选。
     * 接口返回搜索结果的分页数据。
     *
     * @return \think\response\Json 成功时返回的JSON格式数据，包含搜索结果信息。
     */
    public function lst()
    {
        // 解包分页信息
        [$page, $limit] = $this->getPage();

        // 获取请求中的搜索参数
        $where = $this->request->params(['keyword', 'cate_id', 'order', 'price_on', 'price_off', 'brand_id', 'pid', 'star']);

        // 从仓库中根据搜索条件获取搜索结果的分页数据
        $data = $this->repository->getApiSearch(null, $where, $page, $limit, $this->userInfo);

        // 返回成功的JSON响应，包含搜索结果数据
        return app('json')->success($data);
    }

    /**
     * 获取商品详情
     *
     * 本函数用于根据提供的ID获取商品的详细信息。如果商品不存在或已下架，则将商品状态设置为下架并返回相应信息。
     * 如果用户已登录，更新用户在商家处的最后活动时间。
     *
     * @param int $id 商品ID
     * @return json 返回商品详情数据或错误信息
     */
    public function detail($id)
    {
        $this->bindSpread($this->userInfo);
        //通过ID和用户信息获取商品详情
        $data = $this->repository->detail((int)$id, $this->userInfo);
        // 如果商品不存在，则将商品状态设置为下架并返回错误信息
        if (!$data) {
            app()->make(SpuRepository::class)->changeStatus($id, 0);
            return app('json')->fail('商品已下架');
        }
        // 如果用户已登录，更新用户在该商品所属商家的最后活动时间
        if ($this->request->isLogin()) {
            app()->make(UserMerchantRepository::class)->updateLastTime($this->request->uid(), $data['mer_id']);
        }

        foreach ($data['activity_labels'] as $key => $item) {
            if(!$item['is_show']) unset($data['activity_labels'][$key]);
        }
        $data['activity_labels'] = array_values($data['activity_labels']);

        // 返回商品详情数据
        return app('json')->success($data);
    }

    /**
     * 根据产品ID展示产品信息。
     *
     * 本函数用于获取指定产品ID的产品展示信息。如果用户已登录，将结合用户的ID来获取个性化展示信息。
     * 未登录用户将获取默认的展示信息。通过依赖注入的方式，使用JSON工具类来返回处理结果。
     *
     * @param int $id 产品ID，用于查询特定产品信息。
     * @return json 返回包含产品展示信息的JSON对象。
     */
    public function show($id)
    {
        // 检查用户是否已登录，已登录则获取用户ID，否则设置为0
        $uid = $this->request->isLogin() ? $this->request->uid() : 0;

        // 从仓库中获取指定ID的产品展示信息，如果用户已登录，则结合用户ID来获取信息
        $data = $this->repository->getProductShow($id, [], null, $uid);

        // 使用JSON工具类返回获取的产品展示信息
        return app('json')->success($data);
    }

    /**
     * 根据产品ID获取产品属性值
     *
     * 本函数通过产品ID获取相应产品的属性及其值，包括产品属性的详细信息和属性值信息。
     * 这对于展示产品详细信息或进行产品管理操作时非常有用。
     *
     * @param int $id 产品ID
     * @return json 返回包含产品属性和属性值的数据
     */
    public function getAttrValue($id)
    {
        // 创建产品属性仓库实例
        $productAttrRepository = app()->make(ProductAttrRepository::class);
        // 创建产品属性值仓库实例
        $productAttrValueRepository = app()->make(ProductAttrValueRepository::class);

        // 查询指定产品ID的所有属性信息
        $attr = $productAttrRepository->getSearch([])->where('product_id', $id)->select();

        // 处理属性信息，获取详细属性数据
        $data['attr'] = $this->repository->detailAttr($attr);

        // 查询指定产品ID的所有属性值信息
        $data['attrValue'] = $productAttrValueRepository->getSearch([])->where('product_id', $id)->select();

        // 返回处理后的数据，以JSON格式响应
        return app('json')->success($data);
    }

    /**
     * 获取推荐列表
     *
     * 本函数用于获取用户推荐内容的列表。它利用分页机制，返回指定页码和每页数量的推荐内容。
     * 推荐内容是基于用户信息和特定算法生成的，旨在为用户提供个性化的内容推荐。
     *
     * @return \Illuminate\Http\JsonResponse 推荐内容的JSON响应，包含成功状态和推荐数据。
     */
    public function recommendList()
    {
        // 解包获取当前请求的页码和每页数量
        [$page, $limit] = $this->getPage();

        // 调用repository中的recommend方法获取推荐内容，并包装在成功的JSON响应中返回
        return app('json')->success($this->repository->recommend($this->userInfo, null, $page, $limit));
    }

    /**
     * 生成商品二维码
     *
     * 本函数用于根据请求参数生成特定类型的商品二维码。支持常规商品二维码和小程序商品二维码。
     * 通过请求中的$id$获取商品信息，并根据请求中的类型参数决定生成哪种类型的二维码。
     * 如果商品不存在或请求参数有误，将返回错误信息。成功生成二维码后，返回二维码的链接。
     *
     * @param int $id 商品ID，用于查询商品信息。
     * @return json 返回包含二维码链接的JSON对象，如果生成失败则返回错误信息。
     */
    public function qrcode($id)
    {
        // 将ID转换为整数类型，确保数据类型的一致性和安全性
        $id = (int)$id;

        // 获取请求中的类型参数，以及默认的产品类型参数
        $param = $this->request->params(['type', ['product_type', 0]]);

        // 将产品类型参数转换为整数类型
        $param['product_type'] = (int)$param['product_type'];

        // 检查ID和产品类型是否有效，如果无效则返回错误信息
        if (!$id || !$product = $this->repository->existsProduct($id, $param['product_type']))
            return app('json')->fail('商品不存在');

        // 根据请求中的类型参数决定生成哪种类型的二维码
        if ($param['type'] == 'routine') {
            // 生成小程序商品二维码
            $url = $this->repository->routineQrCode($id, $param['product_type'], $this->userInfo);
        } else {
            // 生成常规商品二维码
            $url = $this->repository->wxQrCode($id, $param['product_type'], $this->userInfo);
        }

        // 检查二维码生成是否成功，如果失败则返回错误信息
        if (!$url) return app('json')->fail('二维码生成失败');

        // 返回生成的二维码链接
        return app('json')->success(compact('url'));
    }

    /**
     * 获取礼包列表
     *
     * 本方法用于获取符合特定条件的礼包列表。首先，它会检查系统配置是否启用了扩展功能，
     * 如果未开启，则返回一个表示失败的JSON响应，提示活动未开启。然后，它获取当前的分页信息，
     * 并构造一个查询条件。最后，它使用这些条件来获取礼包列表，并返回一个表示成功的JSON响应，
     * 其中包含礼包列表数据。
     *
     * @return \Illuminate\Http\JsonResponse 返回一个JSON响应，包含礼包列表数据或错误信息。
     */
    public function getBagList()
    {
        // 检查系统配置，如果扩展功能未开启，则返回失败的JSON响应
        if (!systemConfig('extension_status')) return app('json')->fail('活动未开启');

        // 获取当前的分页信息
        [$page, $limit] = $this->getPage();

        // 构造查询条件
        $where = $this->repository->bagShow();

        // 使用查询条件和分页信息获取礼包列表，并返回成功的JSON响应
        return app('json')->success($this->repository->getBagList($where, $page, $limit));
    }

    /**
     * 获取推荐商品包
     *
     * 本函数用于查询并返回被标记为最佳的商品包信息。它首先从仓库层获取所有展示中的商品包条件，
     * 然后特定地筛选出标记为最佳的商品包，并附加商家信息返回。
     *
     * @return \Illuminate\Http\JsonResponse 推荐商品包的信息，包括商家信息。
     */
    public function getBagrecomm()
    {
        // 从仓库层获取所有展示中的商品包的条件
        $where = $this->repository->bagShow();
        // 将条件限定为只包含标记为最佳的商品包
        $where['is_best'] = 1;
        // 返回查询结果，附加商家信息，并以JSON格式响应
        return app('json')->success($this->repository->selectWhere($where)->append(['merchant']));
    }

    /**
     * 获取推广员礼包说明
     *
     * 本函数用于获取推广员礼包的详细说明信息，包括推广员计划的介绍和配置数据。
     * 如果系统配置中推广员功能未开启，则返回错误信息。否则，从系统配置中获取推广员计划的介绍文本，
     * 并通过GroupDataRepository获取推广员配置的相关数据，最后将这些信息封装成JSON格式返回。
     *
     * @return \Illuminate\Http\JsonResponse 返回包含推广员计划说明和配置数据的JSON对象。
     */
    public function getBagExplain()
    {
        // 检查推广员功能是否开启，如果没有开启，则返回错误信息
        if (!systemConfig('extension_status')) return app('json')->fail('活动未开启');


        // 组装返回的数据，包括推广员计划的介绍和配置数据
        $data = [
            'explain' => app()->make(CacheRepository::class)->getResult('promoter_explain'),
            'data' => app()->make(GroupDataRepository::class)->groupData('promoter_config', 0),
        ];

        // 返回包含推广员计划说明和配置数据的JSON对象
        return app('json')->success($data);
    }

    /**
     * 获取热门商品
     *
     * 本函数用于根据指定的热门类型，获取相应的热门商品列表。此列表是基于用户的浏览或购买行为进行热门程度排序的。
     * 主要用于在前端展示热门推荐商品，以引导用户浏览或购买。
     *
     * @param string $type 热门类型标识，用于区分不同类型的热门商品。例如，可以是'bestseller'表示畅销商品，'new'表示新品等。
     * @return \Illuminate\Http\JsonResponse 返回一个JSON响应，其中包含获取的热门商品列表及其相关信息。
     */
    public function hot($type)
    {
        // 分解并获取当前请求的页码和每页数量
        [$page, $limit] = $this->getPage();

        // 调用repository的getApiSearch方法获取热门商品列表，并包装成成功响应返回
        return app('json')->success($this->repository->getApiSearch(null, ['hot_type' => $type, 'is_gift_bag' => 0, 'is_used' => 1], $page, $limit, $this->userInfo));
    }

    /**
     * 根据模板ID保证获取模板信息
     *
     * 本函数旨在通过指定的模板ID，从数据库中检索对应的保证模板信息。
     * 它确保了只返回状态为有效的模板数据。
     *
     * @param int $id 保证模板的唯一标识ID
     * @return \Illuminate\Http\JsonResponse 成功获取数据时返回的JSON响应，包含模板信息
     */
    public function guaranteeTemplate($id)
    {
        // 定义查询条件，确保只查询状态为有效的模板
        $where = [
            'guarantee_template_id' => $id,
            'status' => 1,
        ];

        // 通过仓库接口查询并获取满足条件的保证模板数据
        $data = $this->repository->GuaranteeTemplate($where);

        // 返回成功的JSON响应，包含查询到的模板数据
        return app('json')->success($data);
    }

    /**
     * 设置增加领取数量
     *
     * 本函数用于处理增加领取数量的请求。它首先从请求中获取必要的参数，
     * 然后检查用户是否已绑定手机号（当类型为1时），最后调用仓库接口增加领取数量。
     * 如果用户未绑定手机号且类型为1，函数将返回错误消息；否则，返回成功消息。
     *
     * @return \think\response\Json 成功时返回订阅成功的Json响应，失败时返回错误的Json响应。
     */
    public function setIncreaseTake()
    {
        // 从请求中获取产品ID、唯一标识和类型
        $product_id = $this->request->param('product_id/d');
        $unique = $this->request->param('unique/s');
        $type = $this->request->param('type/d');

        // 检查类型为1时用户是否已绑定手机号
        if ($type == 1 && !$this->userInfo['phone']) {
            return app('json')->fail('请先绑定手机号');
        }

        // 调用仓库接口增加领取数量
        $this->repository->increaseTake($this->request->uid(), $unique, $type, $product_id);

        // 返回订阅成功的响应
        return app('json')->success('订阅成功');
    }

    /**
     * 预览数据。
     * 该方法用于获取预览数据，支持通过键名或ID进行获取。如果通过键名获取，首先尝试从缓存中读取数据，
     * 然后删除该缓存条目，以防止重复使用过期数据。如果通过ID获取，直接从数据仓库中检索数据。
     *
     * @return \think\response\Json 成功时返回数据，失败时返回错误信息。
     * @throws \Exception 如果发生任何异常，将返回异常信息。
     */
    public function preview()
    {
        try {
            // 从请求中获取必要的参数：键名、ID和产品类型。
            $param = $this->request->params(['key', 'id', 'product_type']);
            $data = [];
            // 如果提供了键名参数，尝试从缓存中获取数据并删除该缓存项。
            if ($param['key']) {
                $data = Cache::get($param['key']);
                Cache::delete($param['key']);
            // 如果提供了ID参数，从数据仓库中获取预览数据。
            } elseif ($param['id']) {
                $data = $this->repository->getPreview($param);
            }
            // 如果没有获取到数据，返回错误信息。
            if (!$data) return app('json')->fail('数据不存在');
        } catch (\Exception $e) {
            // 如果捕获到异常，返回异常信息。
            return app('json')->fail($e->getMessage());
        }

        // 如果一切正常，返回获取到的数据。
        return app('json')->success($data);
    }


    /**
     * 根据分类ID获取价格规则
     * 该方法用于根据给定的分类ID查询并返回相应的价格规则。如果规则存在缓存，则直接从缓存中获取；
     * 否则，通过查询数据库获取规则，并将结果缓存起来。
     *
     * @param int $id 分类ID
     * @return json 返回价格规则的JSON格式数据；如果规则不存在，则返回错误信息。
     */
    public function priceRule($id)
    {
        // 生成缓存的唯一标识
        $cache_unique = md5('get_product_rule_' . $id);
        // 尝试从缓存中获取规则数据
        $res = Cache::get($cache_unique);
        // 如果缓存中没有规则数据
        if (!$res) {
            // 查询分类路径
            $path = app()->make(StoreCategoryRepository::class)->query(['store_category_id' => $id, 'mer_id' => 0])->value('path');
            // 如果路径存在且不为根路径
            if ($path && $path !== '/') {
                // 将分类路径和当前分类ID分解为数组
                $ids = explode('/', trim($path, '/'));
                // 将当前分类ID添加到数组中
                $ids[] = $id;
            } else {
                // 如果路径不存在或为根路径，直接使用当前分类ID
                $ids[] = $id;
            }
            // 查询有效的价格规则，按排序降序，规则ID降序
            $rule = app()->make(PriceRuleRepository::class)->search(['cate_id' => $ids, 'is_show' => 1])
                ->order('sort DESC,rule_id DESC')->find();
            // 如果查询到规则
            if ($rule) {
                // 将规则数据转为JSON字符串并缓存，缓存有效期为1小时
                $res = json_encode($rule->toArray());
                Cache::tag('get_product')->set($cache_unique, $res, 3600);
            }
        }
        // 如果有缓存数据或查询到规则
        if ($res) {
            // 返回成功的JSON响应，包含规则数据
            return app('json')->success(json_decode($res, true));
        }
        // 如果没有缓存数据且查询不到规则，返回失败的JSON响应
        return app('json')->fail('规则不存在');
    }

    /**
     * 热门搜索第一个列表
     * @return \think\response\Json
     * @author Qinii
     * @day 2023/10/23
     */
    public function getHotList()
    {
        /**
         * 热门搜索第一列
         * 根据搜索记录查前10
         * 根据搜索记录查询出前10的商品
         */
        $keyword = app()->make(UserVisitRepository::class)->getHotList();
        $data = $this->repository->getHotSearchList(compact('keyword'));
        return app('json')->success($data);
    }

    /**
     * 获取热门分类排行榜
     * 本函数通过查询配置和数据库，获取指定数量的热门分类及其对应的SPU列表。
     * 主要用于展示热门商品分类，引导用户浏览或购买。
     *
     * @param SpuRepository $spuRepository SPU仓库对象，用于查询热门SPU。
     * @return json 返回包含热门分类及其SPU列表的数据。
     */
    public function getHotTop(SpuRepository $spuRepository)
    {
        // 获取请求参数中的limit值，用于限制返回的分类数量，默认为10。
        $limit = $this->request->param('limit/d', 10);

        // 从系统配置中获取热门排行榜开关和级别。
        $hot = systemConfig(['hot_ranking_switch', 'hot_ranking_lv']);

        // 如果热门排行榜开关关闭，则直接返回空数据。
        if (!$hot['hot_ranking_switch']) return app('json')->success([]);

        // 根据配置的级别查询符合条件的分类，限制结果数量并按排序降序和创建时间降序排列。
        $cateId = app()->make(StoreCategoryRepository::class)->getSearch([
            'level' => $hot['hot_ranking_lv'],
            'mer_id' => 0,
            'is_show' => 1,
            'type' => 0
        ])->limit($limit)->order('sort DESC,create_time DESC')->column('cate_name,store_category_id');

        // 初始化数据数组，用于存储最终的分类及其SPU列表。
        $data = [];

        // 遍历分类列表，为每个分类查询其热门SPU。
        foreach ($cateId as $cate) {
            // 获取该分类的热门SPU列表。
            $list = $spuRepository->getHotRanking($cate['store_category_id'], $limit);

            // 如果有热点SPU数据，则添加到结果数据数组中。
            if ($list) {
                $data[] = [
                    'cate_id' => $cate['store_category_id'] ?? 0,
                    'cate_name' => $cate['cate_name'] ?? '总榜',
                    'list' => $list,
                ];
            }
        }

        // 返回最终的热门分类及其SPU列表数据。
        return app('json')->success($data);
    }

    /**
     * 获取店铺商品推荐
     * @param $id
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getGoodList($id)
    {
        $uid = $this->request->isLogin() ? $this->request->uid() : 0 ;
        return app('json')->success($this->repository->getGoodList($id, $uid));
    }

    /**
     *  商品大图推荐列表
     * @return void
     * @author Qinii
     */
    public function cateHotList()
    {
        /**
         * 当第一个商品的商户，只查询当前商品，
         * 同时在次请求，商品分类ID，返回通分类的商品列表
         * 1. 商品 id
         * 2. 商品的三级分类
         * 3. 商品的一级分类
         */
        [$page, $limit] = $this->getPage();
        $params = $this->request->params(['product_id','cate_id','cate_pid',['filter','']]);
        $merId = $this->request->param('mer_id/d' , 0);
        $not = $params['product_id'];
        if ($params['cate_id']) {
            $where['cate_id'] = $params['cate_id'];
        } else if ($params['cate_pid']) {
            $where['cate_pid'] = $params['cate_pid'];
        } else {
            $where['product_id'] = $params['product_id'];
            $not = 0;
        }
        $data = $this->repository->cateHotList($where, $merId, $page, $limit, $this->userInfo,$not);

        if($params['product_id'] && $params['cate_id']) {
            foreach ($data['list'] as $key => $item) {
                if($item['product_id'] == $params['product_id']) {
                    unset($data['list'][$key]);
                }
            }
            $data['list'] = array_values($data['list']);
        }

        return app('json')->success($data);
    }

    /**
     *  根据商品的 spuId，获取商品的规格信息
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function getSpec($id)
    {
        $data = $this->repository->getSpec($id, $this->userInfo);
        return app('json')->success($data);
    }
    /**
     * 推荐商品列表
     *
     * @return void
     */
    public function recommendProduct()
    {
        $params = $this->request->params([['recommend_num', 1], 'product_id']);
        if(!$params['product_id']) {
            return app('json')->fail('商品ID不能为空');
        }
        if(!isset($params['recommend_num'])) {
            return app('json')->fail('推荐数量不能为空');
        }

        $data = $this->repository->recommendProduct($params);
        return app('json')->success($data);
    }
}
