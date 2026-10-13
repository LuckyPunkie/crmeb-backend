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


namespace app\controller\merchant\system;

use crmeb\services\UploadService;
use crmeb\services\wechat\Payment;
use app\validate\merchant\MerchantApplymentsValidate;
use think\App;
use think\facade\Config;
use think\facade\Queue;
use crmeb\basic\BaseController;
use app\validate\merchant\MerchantApplymentsSubjectValidate;
use app\common\repositories\system\merchant\MerchantApplymentsRepository;

class MerchantApplyments extends BaseController
{
    /**
     * @var MerchantRepository
     */
    protected $repository;

    /**
     * Merchant constructor.
     * @param App $app
     * @param MerchantRepository $repository
     */
    public function __construct(App $app, MerchantApplymentsRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    public function index()
    {
        $config = systemConfig(['open_wx_sub_mch','wechat_service_tool']);
        if (!$config['open_wx_sub_mch'])
            return app('json')->fail('未开启子商户入驻');
        $info = [];
        if ($config['wechat_service_tool'])
            $info = $this->repository->info();
        return app('json')->success(compact('config', 'info'));
    }



    /**
     * 创建申请
     * @param MerchantApplymentsValidate $validate
     * @return \think\response\Json
     * @author Qinii
     * @day 6/22/21
     */
    public function create()
    {
        if(!systemConfig('open_wx_sub_mch'))
            return app('json')->fail('未开启子商户入驻');

        $type = $this->request->param('type',0);
        if ($type) {
            $data = $this->checkParamsSubject();
        } else {
            $data = $this->checkParams();
        }

        $this->repository->create($data, $this->request->merId(), $type);

        return app('json')->success('申请提交成功');
    }


    /**
     * 获取商家详情
     *
     * @return \think\response\Json
     */
    public function detail()
    {
        // 获取商家ID
        $merId = $this->request->merId();
        // 调用仓库方法获取商家详情
        $data = $this->repository->detail($merId);
        // 添加开通微信子商户配置项
        $data['open_wx_sub_mch'] = systemConfig('open_wx_sub_mch');
        // 返回JSON格式的成功响应
        return app('json')->success($data);
    }

    public function update($id)
    {
        if (!systemConfig('open_wx_sub_mch')) return app('json')->fail('未开启子商户入驻');
        // 校验参数
        $type = $this->request->param('type',0);
        if ($type) {
            $data = $this->checkParamsSubject();
        } else {
            $data = $this->checkParams();
        }
        // 删除ID字段
        unset($data['id']);
        $this->repository->edit($id, $data);

        return app('json')->success('编辑提交成功');
    }

    /**
     * 查询商家状态
     *
     * @return \think\response\Json
     */
    public function check()
    {
        $mer_id = $this->request->merId();
        $config = systemConfig(['open_wx_sub_mch','wechat_service_tool']);
        $ret = $this->repository->search(['mer_id' => $mer_id, 'type' => $config['wechat_service_tool']]);
        if (!$ret)return app('json')->fail('数据不存在');
        $this->repository->check($ret);
        return app('json')->success('查询状态已更新');
    }


    /**
     * 上传图片
     * @param string $field 上传图片的字段名
     * @return \think\response\Json
     */
    public function uploadImage($field)
    {
        // 获取上传的文件
        $file = $this->request->file($field);
        // 获取水印参数
        $water = $this->request->param('water');
        // 如果没有上传文件则返回错误信息
        if (!$file) return app('json')->fail('请上传图片');
        // 如果上传的是数组则只取第一个元素
        $file = is_array($file) ? $file[0] : $file;
        // 验证上传的文件是否符合要求
        validate(["$field|图片" => [
            'fileSize' => config('upload.filesize'),
            'fileExt' => 'jpg,jpeg,png,bmp,gif',
            'fileMime' => 'image/jpeg,image/png,image/gif',
            function ($file) {
                $ext = $file->extension();
                if ($ext != strtolower($file->extension())) {
                    return '图片后缀必须为小写';
                }
                return true;
            }
        ]])->check([$field => $file]);

        // 调用仓库的上传图片方法
        $res = $this->repository->uploadImage($field, $water);

        // 返回上传结果
        return app('json')->success($res);
    }

    public function uploadVideo($field)
    {
        $file = $this->request->file('file');
        if (!$file) return app('json')->fail('请上传视频');
        validate(["file|视频" => [
            'fileSize' => config('upload.filesize'),
            'fileExt' => 'avi,wmv,mpeg,mp4,mov,mkv,flv,f4v,m4v,rmvb',
            'fileMime' => 'video/x-msvideo,video/x-ms-wmv,video/mpeg,video/mp4,video/quicktime,video/x-matroska,video/x-flv,video/mp4,video/x-m4v,application/vnd.rn-realmedia-vbr',
        ]])->check(['file' => $file]);

        // 调用仓库的上传图片方法
        $res = $this->repository->uploadVideo('file');

        // 返回上传结果
        return app('json')->success($res);
    }


    /**
     * 校验参数
     * @param MerchantApplymentsValidate $validate 验证器实例
     * @return array 校验通过的参数数组
     */
    public function checkParams()
    {
        //'organization_cert_info',
        // 获取需要校验的参数
        $data = $this->request->params([
            'organization_type', 'business_license_info', 'id_doc_type', 'id_card_info', 'id_doc_info', 'need_account_info', 'account_info', 'contact_info', 'sales_scene_info', 'merchant_shortname', 'qualifications', 'business_addition_pics', 'business_addition_desc'
        ]);

        // 根据不同的身份证类型删除对应的参数
        if ($data['id_doc_type'] == 1) {
            unset($data['id_doc_info']);
        } else {
            unset($data['id_card_info']);
        }

        // 如果机构类型为2401或2500则删除商业营业执照和组织机构代码证
        if (in_array($data['organization_type'], ['2401', '2500'])) {
            unset($data['business_license_info']);
        }

        if (isset($data['qualifications']) && !$data['qualifications']) unset($data['qualifications']);

        if (isset($data['business_addition_pics']) && !$data['business_addition_pics']) unset($data['business_addition_pics']);
        if ($data['organization_type'] !== 2 && isset($data['id_card_info']['id_card_address'])) {
            unset($data['id_card_info']['id_card_address']);
        }
        app()->make(MerchantApplymentsValidate::class)->check($data);
        return $data;
    }


    public function checkParamsSubject()
    {
        $data =$this->request->params(['contact_info','subject_info','business_info','settlement_info','bank_account_info','addition_info']);
        app()->make(MerchantApplymentsSubjectValidate::class)->check($data);

        return $data;
    }

    public function banks()
    {
        $type = $this->request->param('type',0);
        $limit = $this->request->param('limit',100);
        $offset = $this->request->param('offset',0);
        $list = Payment::instance()->subjectClient()->getBanks($type, (int)$offset, (int)$limit);
        return app('json')->success($list);
    }

    public function branches()
    {
        $bank_alias_code = $this->request->param('bank_alias_code');
        $city_code = $this->request->param('city_code');
        $limit = $this->request->param('limit',100);
        $offset = $this->request->param('offset',0);
        $list = Payment::instance()->subjectClient()->branches($bank_alias_code,$city_code,$offset,$limit);
        return app('json')->success($list);
    }

    public function areas()
    {
        $province_code = $this->request->param('province_code','');
        $list = Payment::instance()->subjectClient()->areas($province_code);
        return app('json')->success($list['data'] ?? []);
    }

}
