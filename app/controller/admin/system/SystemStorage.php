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
namespace app\controller\admin\system;

use app\common\repositories\system\config\ConfigClassifyRepository;
use app\common\repositories\system\config\ConfigRepository;
use app\common\repositories\system\config\ConfigValueRepository;
use app\common\repositories\system\StorageRepository;
use crmeb\basic\BaseController;
use crmeb\exceptions\AdminException;
use crmeb\services\UploadService;
use think\App;

class SystemStorage extends BaseController
{

    protected $repository;

    public function __construct(App $app, StorageRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     *  获得所有的云存储类型
     * @return \think\response\Json
     * @author Qinii
     * @day 2024/3/9
     */
    public function typeList()
    {
        $data = $this->repository->getType();
        return app('json')->success($data);
    }

    /**
     * 获取存储配置信息
     * @return \think\response\Json
     * @author Qinii
     * @day 2024/3/9
     */
    public function getConfig()
    {
        $config = [
            "upload_type", "thumb_big_width", "thumb_big_height", "thumb_mid_width", "thumb_mid_height", "thumb_small_width", "thumb_small_height", "image_watermark_status", "watermark_type", "watermark_image", "watermark_text", "watermark_position", "watermark_text_size", "watermark_opacity", "watermark_text_color", "watermark_rotate", "watermark_text_angle", "watermark_x", "watermark_y","image_thumb_status"
        ];
        $data = systemConfig($config);
        return app('json')->success($data);
    }

    /**
     * 保存存储配置信息
     * @param ConfigValueRepository $configValueRepository
     * @return \think\response\Json
     * @author Qinii
     * @day 2024/3/9
     */
    public function setConfig(ConfigValueRepository $configValueRepository)
    {
        $config = [
            ["upload_type",1], "thumb_big_width", "thumb_big_height", "thumb_mid_width", "thumb_mid_height", "thumb_small_width", "thumb_small_height", "image_watermark_status", "watermark_type", "watermark_image", "watermark_text", "watermark_position", "watermark_text_size", "watermark_opacity", "watermark_text_color", "watermark_rotate", "watermark_text_angle", "watermark_x", "watermark_y","image_thumb_status",
        ];
        $data = $this->request->params($config);
        if(!isset($data['upload_type']) || !$data['upload_type']){
             return app('json')->fail('参数错误');
        }

        if(isset($data['image_watermark_status']) && $data['image_watermark_status']){
            if(!isset($data['watermark_type']) || !$data['watermark_type']){
                 return app('json')->fail('参数错误');
            }
            if(!isset($data['watermark_opacity']) || $data['watermark_opacity'] < 0){
                $data['watermark_opacity'] = 0;
            }

        }
        $configValueRepository->setFormData($data, 0);
        return app('json')->success('设置成功');
    }

    /**
     * 填写各云存储accessKey secretKey的表单
     * @param $type
     * @return \think\response\Json
     * @author Qinii
     * @day 2024/3/9
     */
    public function form($type)
    {
        $form = $this->repository->form($type);
        return app('json')->success(formToData($form));
    }


    /**
     * 各云存储accessKey secretKey
     * @param ConfigValueRepository $configValueRepository
     * @return \think\response\Json
     * @author Qinii
     * @day 2024/3/9
     */
    public function setForm(ConfigValueRepository $configValueRepository)
    {
        $type = $this->request->param('upload_type');
        $prefix = $this->repository->getPrefix($type);

        $paramNameArray = [
            'accessKey', 'secretKey'
        ];
        if ($type == UploadService::STORAGE_TENGXUN) {
            $paramNameArray[] = 'tengxun_appid';
        }
        if ($type == UploadService::STORAGE_JINGDONG) {
            $paramNameArray[] = 'jd_storageRegion';
        }
        $params = $this->request->params($paramNameArray);

        if (!isset($params['accessKey']) || !$params['accessKey'] || !isset($params['secretKey']) || !$params['secretKey']) {
            return app('json')->fail('参数错误');
        }
        $accessKey = $params['accessKey'];
        $secretKey = $params['secretKey'];
        unset($params['accessKey'],$params['secretKey']);
        $params[$prefix . 'accessKey'] = $accessKey;
        $params[$prefix . 'secretKey'] = $secretKey;

        $configValueRepository->setFormData($params, 0);
        return app('json')->success('提交成功');
    }

    /**
     * 同步存储空间
     * @param $type
     * @return \think\response\Json
     * @author Qinii
     * @day 2024/3/14
     */
    public function sync($type)
    {
        try {
            $this->repository->synchRegion((int)$type);
        }catch (\Exception $e){
            return app('json')->fail('同步存储空间失败，'.$e->getMessage());
        }
        return app('json')->success('同步成功');

    }

    /**
     * 存储空间列表
     * @param $type
     * @return \think\response\Json
     * @author Qinii
     * @day 2024/3/14
     */
    public function lstRegion($type)
    {
        [$page, $limit] = $this->getPage();
        $data = $this->repository->lstRegion(['type' => $type], $page, $limit);
        return app('json')->success($data);
    }

    /**
     *  创建存储空间表单
     * @param $type
     * @return \think\response\Json
     * @author Qinii
     * @day 2024/3/14
     */
    public function createRegionForm($type)
    {
        $form = $this->repository->createRegionForm($type);
        return app('json')->success(formToData($form));
    }

    /**
     *  创建存储空间
     * @param $type
     * @return \think\response\Json
     * @author Qinii
     * @day 2024/3/14
     */
    public function createRegion($type)
    {
        $param = ['name', 'region', 'acl'];
        $data = $this->request->params($param);
        $paramNameArray = [
            'accessKey', 'secretKey'
        ];
        if ($type == UploadService::STORAGE_TENGXUN) {
            $paramNameArray[] = 'tengxun_appid';
        }
        if ($type == UploadService::STORAGE_JINGDONG) {
            $paramNameArray[] = 'jd_storageRegion';
        }
        $params = $this->request->params($paramNameArray);
        if ($type == UploadService::STORAGE_TENGXUN) {
            if (!$params['tengxun_appid'] && !systemConfig('tengxun_appid')) {
                return app('json')->fail('请填写APPDID');
            }
        }
        try {
            $this->repository->createRegion((int)$type, $data,$params);
        }catch (\Exception $e){
            return app('json')->fail('创建存储空间失败，'.$e->getMessage());
        }
        return app('json')->success('添加成功');
    }

    /**
     * 修改空间域名表单
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     * @day 2024/3/14
     */
    public function editDomainForm($id)
    {
        $form = $this->repository->editDomainForm($id);
        return app('json')->success(formToData($form));
    }

    /**
     * 修改空间域名
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     * @day 2024/3/14
     */
    public function editDomain($id)
    {
        $domain = $this->request->post('domain', '');
        $cdn = $this->request->post('cdn', '');
        if (!$domain) {
            return app('json')->fail('空间域名');
        }
        if (strstr($domain, 'https://') === false && strstr($domain, 'http://') === false) {
            return app('json')->fail('请输入正确域名：http/https');
        }
        $this->repository->updateDomain($id, $domain, ['cdn' => $cdn]);
        return app('json')->success('修改成功');
    }

    /**
     * 选择使用存储空间
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     * @day 2024/3/14
     */
    public function swtichStatus($id)
    {
        $info = $this->repository->get($id);
        if (!$info) return app('json')->fail('数据不存在');
        if (!$info->domain) {
            return app('json')->fail('未配置空间域名');
        }
        $this->repository->status($id, $info);
        return app('json')->success('修改成功');
    }

    /**
     * 删除存储空间
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     * @day 2024/3/14
     */
    public function deleteRegion($id)
    {
        if ($this->repository->deleteRegion($id)) {
            return app('json')->success('删除成功');
        } else {
            return app('json')->fail('删除失败');
        }
    }
}
