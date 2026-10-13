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

use think\facade\Log;
use app\common\repositories\BaseRepository;
use app\common\repositories\system\attachment\AttachmentCategoryRepository;
use app\common\repositories\system\attachment\AttachmentRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use crmeb\services\CopyProductService;
use crmeb\services\CrmebServeServices;
use crmeb\services\DownloadImageService;
use Exception;
use think\exception\ValidateException;
use app\common\dao\store\product\ProductCopyDao;
use think\facade\Cache;
use think\facade\Db;

class ProductCopyRepository extends BaseRepository
{
    protected $host = ['taobao', 'tmall', 'jd', 'pinduoduo', 'suning', 'yangkeduo','1688'];
    protected $AttachmentCategoryName = '远程下载';
    protected $dao;
    protected $updateImage = ['image', 'slider_image'];
    protected $AttachmentCategoryPath = 'copy';
    /**
     * ProductRepository constructor.
     * @param dao $dao
     */
    public function __construct(ProductCopyDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 根据URL和商家ID获取产品信息
     * 该方法首先尝试从缓存中获取产品信息，如果缓存存在则直接返回，以提高效率。
     * 如果缓存不存在，则根据商家是否有使用平台的采集次数来决定使用哪种方式获取产品信息。
     * 如果商家有可用的采集次数，且系统配置允许使用API采集，则直接调用API获取产品信息。
     * 如果商家有可用的采集次数，但系统配置不允许使用API采集，则通过服务类进行商品信息的复制操作。
     * 如果商家没有可用的采集次数，则使用商家的一号通服务进行商品信息的复制操作。
     * 获取到产品信息后，将其处理并存入缓存中，以供后续请求使用。
     * 如果产品信息获取失败，抛出异常提示用户。
     * 如果使用了平台的采集次数，记录相应的消耗。
     *
     * @param string $url 商品的URL
     * @param int $merId 商家的ID
     * @return array 返回产品信息
     * @throws ValidateException 如果获取产品信息失败或接口返回错误信息，则抛出异常
     */
    public function getProduct($url,$merId)
    {
        // 构建缓存键名，用于存储和检索产品信息
        $key = $merId.'_url_'.json_encode($url);
        // 尝试从缓存中获取产品信息，如果存在则直接返回
        if ($result= Cache::get($key)) return json_decode($result,true);

        // 检查商家是否有可用的采集次数
        $number = app()->make(MerchantRepository::class)->checkCrmebNum($merId,'copy');

        // 如果商家有可用的采集次数
        //如果有此处使用平台的采集方式
        if ($number) {
            // 检查系统配置，决定是否使用API采集产品信息
            if (systemConfig('copy_product_status') == 1) {
                $resultData = $this->useApi($url);
            } else {
                // 使用服务类进行商品信息的复制操作
                $service =  app()->make(CrmebServeServices::class,[0]);
                $resultData['data'] =$service->copy()->goods($url);
                $resultData['status'] = true;
            }
        } else { // 使用商家的一号通服务进行商品信息的复制操作
            // 使用商家的一号通服务进行商品信息的复制操作
            $service =  app()->make(CrmebServeServices::class,[$merId]);
            $resultData['data'] =$service->copy()->goods($url);
            $resultData['status'] = true;
        }

        // 如果成功获取到产品信息
        if ($resultData['status'] && $resultData['status']) {
            // 处理产品信息并存入缓存中
            $result = $this->getParamsData($resultData['data']);
            Cache::set($key,json_encode($result), 60 * 60 * 2);
        } else {
            // 如果获取产品信息失败，抛出异常
            if (isset($resultData['msg'])) throw  new ValidateException('接口错误信息:'.$resultData['msg']);
            throw  new ValidateException('采集失败，请更换链接重试！');
        }

        // 如果使用了平台的采集次数，记录采集次数的消耗
        //如果用的是平台购买，这里需要扣除次数
        if ($number) {
            $this->add(['type' => 'copy', 'num' => -1, 'info' => $url , 'mer_id' => $merId, 'message' => '采集商品',], $merId);
        }

        // 返回产品信息
        return $result;
    }


    /**
     *  99api采集
     * @param $url
     * @return array
     * @author Qinii
     * @day 2022/11/11
     */
    public function useApi($url)
    {
        $apikey = systemConfig('copy_product_apikey');
        if (!$apikey) throw new ValidateException('请前往平台后台-设置-第三方接口-配置接口密钥');
        $url_arr = parse_url($url);
        if (isset($url_arr['host'])) {
            foreach ($this->host as $name) {
                if (strpos($url_arr['host'], $name) !== false) {
                    $type = $name;
                }
            }
        }
        $type = ($type == 'pinduoduo' || $type == 'yangkeduo') ? 'pdd' : $type;
        try{
            switch ($type) {
                case 'taobao':
                case 'tmall':
                    $params = [];
                    if (isset($url_arr['query']) && $url_arr['query']) {
                        $queryParts = explode('&', $url_arr['query']);
                        foreach ($queryParts as $param) {
                            $item = explode('=', $param);
                            if (isset($item[0]) && $item[1]) $params[$item[0]] = $item[1];
                        }
                    }
                    $id = $params['id'] ?? '';
                    break;
                case 'jd':
                    $params = [];
                    if (isset($url_arr['path']) && $url_arr['path']) {
                        $path = str_replace('.html', '', $url_arr['path']);
                        $params = explode('/', $path);
                    }
                    $id = $params[1] ?? '';
                    break;
                case 'pdd':
                    $params = [];
                    if (isset($url_arr['query']) && $url_arr['query']) {
                        $queryParts = explode('&', $url_arr['query']);
                        foreach ($queryParts as $param) {
                            $item = explode('=', $param);
                            if (isset($item[0]) && $item[1]) $params[$item[0]] = $item[1];
                        }
                    }
                    $id = $params['goods_id'] ?? '';
                    break;
                case 'suning':
                    $params = [];
                    if (isset($url_arr['path']) && $url_arr['path']) {
                        $path = str_replace('.html', '', $url_arr['path']);
                        $params = explode('/', $path);
                    }
                    $id = $params[2] ?? '';
                    $shopid = $params[1] ?? '';
                    break;
                case '1688':
                    $params = [];
                    if (isset($url_arr['query']) && $url_arr['query']) {
                        $path = str_replace('.html', '', $url_arr['path']);
                        $params = explode('/', $path);
                    }
                    $id = $params[2] ?? '';
                    $shopid = $params[1] ?? '';
                    $type = 'alibaba';
                    break;

            }
        }catch (Exception $exception){
            throw new ValidateException('url有误');
        }
        $result = CopyProductService::getInfo($type, ['itemid' => $id, 'shopid' => $shopid ?? ''], $apikey);
        return $result;
    }

    /**
     *  整理参数
     * @param $data
     * @return array
     * @author Qinii
     * @day 2022/11/11
     *
     */
    public function getParamsData($data)
    {
        if(!is_array($data['slider_image'])) $data['slider_image'] = json_decode($data['slider_image']);
        $params = ProductRepository::CREATE_PARAMS;
        foreach ($params as $param) {
            if (is_array($param)) {
                $res[$param[0]] = $param[1];
            } else {
                $res[$param] = $data[$param] ?? '';
            }
//            if (in_array($param,$this->updateImage)) {
//                $res[$param] = $this->getImageByUrl($data[$param]);
//            }
        }
        $attr = $data['items'] ?? $data['info']['attr'];
        foreach ($attr as &$v) {
            $v['detail'] = array_map(function($item){
                return ['pic' => '','value' => $item];
            },$v['detail']);
        }
        $value = $data['info']['value'] ?? [];
        foreach ($value as $i => &$item) {
            if ($i == 1) $item['is_default_select'] = 1;
            $item['attr_arr'] = array_values($item['detail']);
            $item['is_show'] = 1;
        }
        $res['spec_type'] = count($attr) ? '1' : '0';
        $res['is_show'] = 1;
        $res['content'] = $data['description'];
        $res['is_copy'] = 1;
//        $res1['value'] = app()->make(ProductRepository::class)->isFormatAttr($attr,0,$value);
        $res['attr'] = $attr;
        $res['attrValue'] = $value;
        return $res;
    }

    /**
     *  处理需要下载的图片
     * @param $id
     * @param $data
     * @author Qinii
     * @day 2023/4/13
     */
    public function downloadImage($id, $data)
    {
        foreach ($data as $key => $param) {
            if (in_array($key,$this->updateImage)) {
                $res[$key] = $this->getImageByUrl($param, $data['mer_id'] ?? 0, $data['admin_id'] ?? 0);
            }
        }
        $data['content'] = $this->getDescriptionImage($data);
        $productRepository = app()->make(ProductRepository::class);
        $spuRepository = app()->make(SpuRepository::class);

        try{
            if(!empty($res)){
                if(!empty($res['slider_image'])){
                    $res['slider_image'] = is_array($res['slider_image']) ? implode(',', $res['slider_image']) : '';
                }
                $productRepository->update($id, $res);
            }
            if (!empty($data['content'])) {
                app()->make(ProductContentRepository::class)->clearAttr($id, $data['type'] ?? 0);
                $productRepository->createContent($id, ['content' => $data['content'], 'type' => $data['type'] ?? 0]);
            }
            if (isset($res['image']) && !empty($res['image'])) {
                $spuRepository->getSearch([])->where('product_id',$id)->update(['image' => $res['image']]);
            }
            $this->getAttrValueImage($id,$data['mer_id'] ?? 0, $data['admin_id'] ?? 0);
        }catch (Exception $exception) {
            Log::error('商品采集图片错误：'.$exception->getMessage());
        }
    }

    public function getAttrValueImage($id, $merId, $adminId)
    {
        $productAttrValueRepository = app()->make(ProductAttrValueRepository::class);
        $attrValueList = $productAttrValueRepository->search(['product_id' => $id])->select();
        foreach ($attrValueList as $item) {
            $image = $this->getImageByUrl($item['image'], $merId, $adminId);
            $item->image = $image;
            $item->save();
        }
    }

    /**
     * 替换详情页的图片地址
     * @param $images
     * @param $html
     * @return mixed|string|string[]|null
     * @author Qinii
     * @day 2022/11/11
     */
    public function getDescriptionImage($data)
    {
        $html = $data['content'];
        preg_match_all('#<img.*?src="([^"]*)"[^>]*>#i', $html, $match);
        if (isset($match[1])) {
            foreach ($match[1] as $item) {
                $uploadValue = $this->getImageByUrl($item, $data['mer_id'] ?? 0, $data['admin_id'] ?? 0);
                //下载成功更新数据库
                if ($uploadValue) {
                    //替换图片
                    $html = str_replace($item, $uploadValue, $html);
                } else {
                    //替换掉没有下载下来的图片
                    $html = preg_replace('#<img.*?src="' . $item . '"*>#i', '', $html);
                }
            }
        }
        return $html;
    }

    /**
     * 根据url下载图片
     * @param $data
     * @return array|mixed|string
     * @author Qinii
     * @day 2022/11/11
     */
    public function getImageByUrl($data, $merId = 0, $adminId = 0)
    {
        if (!$data) return '';
        $category = app()->make(AttachmentCategoryRepository::class)->findOrCreate([
            'attachment_category_enname' => $this->AttachmentCategoryPath,
            'attachment_category_name' => $this->AttachmentCategoryName,
            'mer_id' => $merId,
            'pid' => 0,
        ]);
        $make = app()->make(AttachmentRepository::class);
        $serve = app()->make(DownloadImageService::class);
        $type = (int)systemConfig('upload_type') ?: 1;

        if (is_array($data)) {
            foreach ($data as $datum) {
                $arcurl = is_int(strpos($datum, 'http')) ? $datum : 'http://' . ltrim($datum, '\//');
                $image = $serve->downloadImage($arcurl, $this->AttachmentCategoryPath);
                $dir = $image['path'];
                if ($type == 1 && strpos($image['path'], '//') !== 1) {
                    $dir = rtrim(systemConfig('site_url'), '/') . $image['path'];
                }
                $data = [
                    'attachment_category_id' => $category->attachment_category_id,
                    'attachment_name' => $image['name'],
                    'attachment_src' => $dir
                ];
                $make->create($type, $merId, $adminId, $data);
                $res[] = $dir;
            }
        } else {
            $arcurl = is_int(strpos($data, 'http')) ? $data : 'http://' . ltrim($data, '\//');
            $image = $serve->downloadImage($arcurl, $this->AttachmentCategoryPath);

            $dir = $image['path'];
            if ($type == 1 && strpos($image['path'], '//') !== 1) {
                $dir = rtrim(systemConfig('site_url'), '/') . $image['path'];
            }

            $data = [
                'attachment_category_id' => $category->attachment_category_id,
                'attachment_name' => $image['name'],
                'attachment_src' => $dir
            ];
            $make->create($type, $merId, $adminId, $data);
            $res = $dir;
        }
        return $res;
    }


    /**
     * 添加记录并修改数据
     * @param $data
     * @param $merId
     * @author Qinii
     * @day 2020-08-06
     */
    public function add($data,$merId)
    {
        $make = app()->make(MerchantRepository::class);
        $getOne = $make->get($merId);
        switch ($data['type']) {
            case 'mer_dump':
                //nobreak;
            case 'pay_dump':
                $field = 'export_dump_num';
                break;
            case 'sys':
                //nobreak;
                //nobreak;
            case 'pay_copy':
                //nobreak;
            case 'copy':
                //nobreak;
                $field = 'copy_product_num';
                break;
            default:
                $field = 'copy_product_num';
                break;
        }


        $number = $getOne[$field] + $data['num'];
        $arr = [
            'type'  => $data['type'],
            'num'   => $data['num'],
            'info'   => $data['info']??'' ,
            'mer_id'=> $merId,
            'message' => $data['message'] ?? '',
            'number' => ($number < 0) ? 0 : $number,
        ];
        Db::transaction(function()use($arr,$make,$field){
            $this->dao->create($arr);
            if ($arr['num'] < 0) {
                $make->sumFieldNum($arr['mer_id'],$arr['num'],$field);
            } else {
                $make->addFieldNum($arr['mer_id'],$arr['num'],$field);
            }
        });
    }

    /**
     * 默认赠送复制次数
     * @param $merId
     * @author Qinii
     * @day 2020-08-06
     */
    public function defaulCopyNum($merId)
    {
        if(systemConfig('copy_product_status') && systemConfig('copy_product_defaul')){
            $data = [
                'type' => 'sys',
                'num' => systemConfig('copy_product_defaul'),
                'message' => '赠送次数',
            ];
            $this->add($data,$merId);
        }
    }

    /**
     * 根据条件获取分页列表数据
     *
     * 本函数用于根据给定的条件数组 $where，从数据库中检索满足条件的数据列表。
     * 它支持分页查询，每页的数据数量由 $limit 参数指定，查询的页码由 $page 参数指定。
     * 查询结果包括两部分：总记录数 $count 和满足条件的分页数据列表 $list。
     *
     * @param array $where 查询条件数组
     * @param int $page 查询的页码
     * @param int $limit 每页的数据数量
     * @return array 返回包含 'count' 和 'list' 两个元素的数组，'count' 为总记录数，'list' 为数据列表
     */
    public function getList(array $where,int $page, int $limit)
    {
        // 初始化查询，根据 $where 条件搜索，并加载 'merchant' 关联数据，只获取 'mer_id' 和 'mer_name' 字段
        $query = $this->dao->search($where)->with([
            'merchant' => function ($query) {
                // 限定只查询 'merchant' 表中的 'mer_id' 和 'mer_name' 两个字段
                return $query->field('mer_id,mer_name');
            }
        ]);

        // 计算满足条件的总记录数
        $count = $query->count();

        // 执行分页查询，获取当前页的数据显示
        $list = $query->page($page,$limit)->select();

        // 将总记录数和分页数据列表打包成数组返回
        return compact('count','list');
    }
}
