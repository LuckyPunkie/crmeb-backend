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

namespace app\common\repositories\system\merchant;

use app\common\dao\system\merchant\MerchantAppymentsDao;
use app\common\model\system\merchant\MerchantApplyments;
use app\common\repositories\BaseRepository;
use crmeb\jobs\SendSmsJob;
use crmeb\services\ImageWaterMarkService;
use crmeb\services\SmsService;
use crmeb\services\UploadService;
use crmeb\services\wechat\Payment;
use crmeb\services\YunxinSmsService;
use FormBuilder\Factory\Elm;
use app\common\repositories\system\attachment\AttachmentRepository;
use function Symfony\Component\String\b;
use think\db\concern\Transaction;
use think\Exception;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Queue;
use think\facade\Route;

/**
 * 商户分账账户申请
 */
class MerchantApplymentsRepository extends BaseRepository
{

    /**
     *  这部分是平台收付通部分定义
     */
    const IDCARD = 'IDENTIFICATION_TYPE_MAINLAND_IDCARD';   //：中国大陆居民-身份证
    const PASSPORT = 'IDENTIFICATION_TYPE_OVERSEA_PASSPORT';  //：其他国家或地区居民-护照
    const HONGKONG = 'IDENTIFICATION_TYPE_HONGKONG';          //：中国香港居民–来往内地通行证
    const MACAO = 'IDENTIFICATION_TYPE_MACAO';             //：中国澳门居民–来往内地通行证
    const TAIWAN = 'IDENTIFICATION_TYPE_TAIWAN';            //：中国台湾居民–来往大陆通行证
    const FOREIGN_RESIDENT = 'IDENTIFICATION_TYPE_FOREIGN_RESIDENT';            //：外国人居留证
    const MACAO_RESIDENT = 'IDENTIFICATION_TYPE_HONGKONG_MACAO_RESIDENT';            //：港澳居民证
    const TAIWAN_RESIDENT = 'IDENTIFICATION_TYPE_TAIWAN_RESIDENT';            //：台湾居民证


    /**
     *  这部分是服务商部分定义
     */
    const INDIVIDUAL = 'SUBJECT_TYPE_INDIVIDUAL';       //个体户
    const ENTERPRISE = 'SUBJECT_TYPE_ENTERPRISE';       //企业
    const GOVERNMENT = 'SUBJECT_TYPE_GOVERNMENT';       //政府机关
    const INSTITUTIONS = 'SUBJECT_TYPE_INSTITUTIONS';   //事业单位
    const OTHERS = 'SUBJECT_TYPE_OTHERS';               //社会组织


    //经营者/法定代表人
    const CONTACT_LEGAL = 'LEGAL';
    //经办人
    const CONTACT_SUPER = 'SUPER';

    //中国大陆居民-身份证
    const IDENTIFICATION_IDCARD = 'IDENTIFICATION_TYPE_IDCARD';
    //其他国家或地区居民-护照
    const IDENTIFICATION_OVERSEA_PASSPORT = 'IDENTIFICATION_TYPE_OVERSEA_PASSPORT';
    // 中国香港居民-来往内地通行证
    const IDENTIFICATION_HONGKONG_PASSPORT = 'IDENTIFICATION_TYPE_HONGKONG_PASSPORT';
    //中国澳门居民-来往内地通行证
    const IDENTIFICATION_MACAO_PASSPORT = 'IDENTIFICATION_TYPE_MACAO_PASSPORT';
    //中国台湾居民-来往大陆通行证
    const IDENTIFICATION_TAIWAN_PASSPORT = 'IDENTIFICATION_TYPE_TAIWAN_PASSPORT';
    //外国人居留证
    const IDENTIFICATION_FOREIGN_RESIDENT = 'IDENTIFICATION_TYPE_FOREIGN_RESIDENT';
    //港澳居民居住证
    const IDENTIFICATION_HONGKONG_MACAO_RESIDENT = 'IDENTIFICATION_TYPE_HONGKONG_MACAO_RESIDENT';
    //台湾居民居住证
    const IDENTIFICATION_TAIWAN_RESIDENT = 'IDENTIFICATION_TYPE_TAIWAN_RESIDENT';




    public function __construct(MerchantAppymentsDao $dao)
    {
        $this->dao = $dao;
    }

    public function info()
    {
        return [
            ["value " => self::INDIVIDUAL,   'label' => '个体户'],
            ["value " => self::ENTERPRISE,   'label' => '企业'],
            ["value " => self::GOVERNMENT,   'label' => '政府机关'],
            ["value " => self::INSTITUTIONS, 'label' => '事业单位'],
            ["value " => self::OTHERS,       'label' => '社会组织'],
        ];
    }

    public function get4sub()
    {
        $info = [
            //超级管理员信息
            "contact_info" => [
                "contact_type" => []
            ],
            //主体资料
            "subject_info" => [
                "subject_type" => [
                    ["value " => self::INDIVIDUAL,   'label' => '个体户'],
                    ["value " => self::ENTERPRISE,   'label' => '企业'],
                    ["value " => self::GOVERNMENT,   'label' => '政府机关'],
                    ["value " => self::INSTITUTIONS, 'label' => '事业单位'],
                    ["value " => self::OTHERS,       'label' => '社会组织'],
                ],
            ],
            //经营资料
            "business_info" => [],
            //结算规则
            "settlement_info" => [],
            //结算银行账户
            "bank_account_info" => [],
        ];
    }

    /**
     * 申请
     * @param array $data
     * @param $merId
     * @return mixed
     * @author Qinii
     * @day 6/23/21
     */
    public function create(array $data, $merId, $type)
    {
        $count = $this->dao->getSearch(['mer_id' => $merId,'type' => $type])->count('*');
        if($count) throw new ValidateException('此商户已存在申请信息');
        $out_request_no = $this->getOutRequestNo($merId);
        $ret['out_request_no'] = $out_request_no;
        if ($type){
            $ret['mer_name'] = $data['business_info']['merchant_shortname'];
            $data['business_code'] = $out_request_no;
        }  else {
            $ret['mer_name'] = $data['merchant_shortname'];
            $data['out_request_no'] = $out_request_no;
        }
        $ret['info'] = json_encode($data,JSON_UNESCAPED_UNICODE);
        $ret['mer_id'] = $merId;
        $ret['type'] = $type;
        $this->dao->create($ret);
    }

    /**
     * 整理请求数据
     * @param $info
     * @return mixed
     * @author Qinii
     * @day 6/24/21
     */
    public function sltData($info)
    {
        foreach ($info as $key => $value){
            if(is_object($value)){
                $value = (array)$value;
            }
            $data[$key] = $value;
        }
        if (isset($data['need_account_info'])) unset($data['need_account_info']);
        $data['id_doc_type'] = $this->getIdDocType($data['id_doc_type']);

        //营业执照
        if(isset($data['business_license_info'])){
            if(isset($data['business_license_info']['business_license_copy'])) {
                $business_license_copy = $data['business_license_info']['business_license_copy']->media_id;
                unset($data['business_license_info']['business_license_copy']);
                $data['business_license_info']['business_license_copy'] = $business_license_copy;
            }
            if(isset($data['business_license_info']['business_time'])){
                $organization_time = json_encode($data['business_license_info']['business_time'],JSON_UNESCAPED_UNICODE);
                $data['business_license_info']['business_time'] = $organization_time;
            }
        }

        //组织机构代码
        if(isset($data['organization_cert_info'])){
            if(isset($data['organization_cert_info']['organization_copy'])) {
                $organization_copy = $data['organization_cert_info']['organization_copy']->media_id;
                unset($data['organization_cert_info']['organization_copy']);
                $data['organization_cert_info']['organization_copy'] = $organization_copy;
            }
            if(isset($data['organization_cert_info']['organization_time'])){
                $organization_time = json_encode($data['organization_cert_info']['organization_time'],JSON_UNESCAPED_UNICODE);
                $data['organization_cert_info']['organization_time'] = $organization_time;
            }
        }

        //身份证
        if(isset($data['id_card_info'])){
            if(isset($data['id_card_info']['id_card_copy'])) {
                $id_card_copy = $data['id_card_info']['id_card_copy']->media_id;
                unset($data['id_card_info']['id_card_copy']);
                $data['id_card_info']['id_card_copy'] = $id_card_copy;
            }
            if(isset($data['id_card_info']['id_card_national'])) {
                $id_card_national = $data['id_card_info']['id_card_national']->media_id;
                unset($data['id_card_info']['id_card_national']);
                $data['id_card_info']['id_card_national'] = $id_card_national;
            }
        }

        //银行
        if(isset($data['account_info'])) {
            if(is_array($data['account_info']['bank_address_code'])){
                $bank_address_code = (string)$data['account_info']['bank_address_code'][2];
                unset($data['account_info']['bank_address_code']);
                $data['account_info']['bank_address_code'] = (string)$bank_address_code;
            }
            $data['account_info']['bank_account_type'] = (string)$data['account_info']['bank_account_type'];
        }

        //管理员
        if(isset($data['contact_info'])) {
            $data['contact_info']['contact_type'] = (string)$data['contact_info']['contact_type'];
            if ($data['contact_info']['contact_type'] == 65) {
                unset(
                    $data['contact_info']['contact_id_doc_copy'],
                    $data['contact_info']['contact_id_doc_copy_back'],
                    $data['contact_info']['contact_id_doc_period_begin'],
                    $data['contact_info']['contact_id_doc_period_end'],
                    $data['contact_info']['business_authorization_letter'],
                    $data['contact_info']['contact_id_doc_type']
                );
            }
            if(isset($data['contact_info']['contact_id_doc_type']))
            {
                $data['contact_info']['contact_id_doc_type'] = $this->getIdDocType($data['contact_info']['contact_id_doc_type']);
            }
            if(isset($data['contact_info']['contact_id_doc_copy'])) {
                $contact_id_doc_copy = $data['contact_info']['contact_id_doc_copy']->media_id;
                unset($data['contact_info']['contact_id_doc_copy']);
                $data['contact_info']['contact_id_doc_copy'] = $contact_id_doc_copy;
            }

            if(isset($data['contact_info']['contact_id_doc_copy_back'])) {
                $contact_id_doc_copy_back = $data['contact_info']['contact_id_doc_copy_back']->media_id;
                unset($data['contact_info']['contact_id_doc_copy_back']);
                $data['contact_info']['contact_id_doc_copy_back'] = $contact_id_doc_copy_back;
            }
            if(isset($data['contact_info']['business_authorization_letter'])) {
                $business_authorization_letter = $data['contact_info']['business_authorization_letter']->media_id;
                unset($data['contact_info']['business_authorization_letter']);
                $data['contact_info']['business_authorization_letter'] = $business_authorization_letter;
            }
        }

        //其他证件
        if(isset($data['id_doc_info'])){
            $doc_ = json_encode($data['id_doc_info']['doc_period_end'],JSON_UNESCAPED_UNICODE);
            $data['id_doc_info']['doc_period_end'] = $doc_;

            if(isset($data['id_doc_info']['id_doc_copy'])) {
                $id_doc_copy = $data['id_doc_info']['id_doc_copy']->media_id;
                unset($data['id_doc_info']['id_doc_copy']);
                $data['id_doc_info']['id_doc_copy'] = $id_doc_copy;
            }
            if(isset($data['id_doc_info']['id_doc_copy_back'])) {
                $id_doc_copy_back = $data['id_doc_info']['id_doc_copy_back']->media_id;
                unset($data['id_doc_info']['id_doc_copy_back']);
                $data['id_doc_info']['id_doc_copy_back'] = $id_doc_copy_back;
            }
        }

        //店铺信息
        if(isset($data['sales_scene_info']['store_qr_code']) && $data['sales_scene_info']['store_qr_code']){
            $store_qr_code= $data['sales_scene_info']['store_qr_code']->media_id;
            unset($data['sales_scene_info']['store_qr_code']);
            $data['sales_scene_info']['store_qr_code'] = $store_qr_code;
        }

        //特殊资质
        if(isset($data['qualifications']) && !empty($data['qualifications'])){
            $qualifications = [];
            foreach ($data['qualifications'] as $item){
                $qualifications[] = $item->media_id;
            }
            unset($data['qualifications']);
            $data['qualifications'] = json_encode($qualifications,JSON_UNESCAPED_UNICODE);
        }

        //补充材料
        if(isset($data['business_addition_pics']) && !empty($data['business_addition_pics'])){
            $business_addition_pics = [];
            foreach ($data['business_addition_pics'] as $item){
                $business_addition_pics[] =  $item->media_id;
            }
            unset($data['business_addition_pics']);
            $data['business_addition_pics'] =  json_encode($business_addition_pics,JSON_UNESCAPED_UNICODE);
        }
        $data['organization_type'] = (string)$data['organization_type'];
        return $data;
    }

    public function subjectData($info)
    {
        foreach ($info as $key => $value){
            if(is_object($value)){
                $value = (array)$value;
            }
            $data[$key] = $value;
        }
        //超级管理员
        if (isset($data['contact_info'])) {
            if ($data['contact_info']['contact_type'] == 'LEGAL') {
                $contact_info = [
                    "contact_type" => 'LEGAL',
                    'contact_name' => $data['contact_info']['contact_name'],
                    'mobile_phone' => $data['contact_info']['mobile_phone'],
                    'contact_email' => $data['contact_info']['contact_email'],
                ];
                unset($data['contact_info']);
                $data['contact_info'] = $contact_info;
            } else {
                if (isset($data['contact_info']['contact_id_doc_copy'])) {
                    $data['contact_info']['contact_id_doc_copy'] = $this->extractMediaId($data['contact_info']['contact_id_doc_copy']);
                }
                if (isset($data['contact_info']['contact_id_doc_copy_back'])) {
                    $data['contact_info']['contact_id_doc_copy_back'] = $this->extractMediaId($data['contact_info']['contact_id_doc_copy_back']);
                }
            }
        }
        //主体资料
        if (isset($data['subject_info'])) {
            if (isset($data['subject_info']['business_license_info'])) {
                if ($data['subject_info']['business_license_info']->period_longterm) {
                    unset($data['subject_info']['business_license_info']->period_longterm);
                    $data['subject_info']['business_license_info']->period_end = '长期';
                }
                $data['subject_info']['business_license_info']->license_copy = $this->extractMediaId($data['subject_info']['business_license_info']->license_copy);
            }
            if (in_array($data['subject_info']['subject_type'],['SUBJECT_TYPE_GOVERNMENT',
                'SUBJECT_TYPE_INSTITUTIONS','SUBJECT_TYPE_OTHERS'])) {
                if (isset($data['subject_info']['certificate_info'])) {
                    $data['subject_info']['certificate_info']->cert_copy = $this->extractMediaId
                    ($data['subject_info']['certificate_info']->cert_copy);
                }
            } else {
                unset($data['subject_info']['certificate_info']);
            }

            if ($data['subject_info']['subject_type'] != 'SUBJECT_TYPE_ENTERPRISE') {
                unset($data['subject_info']['ubo_info_list']);
            }

            if (isset($data['subject_info']['certificate_letter_copy'])) {
                $data['subject_info']['certificate_letter_copy'] = $this->extractMediaId($data['subject_info']['certificate_letter_copy']);
            }

            if (isset($data['subject_info']['identity_info'])) {
                if (isset($data['subject_info']['identity_info']->id_card_info)) {
                    $card_info = &$data['subject_info']['identity_info']->id_card_info;
                    if (!$card_info->id_card_copy){
                        unset($data['subject_info']['identity_info']->id_card_info);
                    } else {
                        if ($card_info->card_period_longterm) {
                            unset($card_info->card_period_longterm);
                            $card_info->card_period_end = '长期';
                        }
                        $card_info->id_card_copy = $this->extractMediaId($card_info->id_card_copy);
                        $card_info->id_card_national = $this->extractMediaId($card_info->id_card_national);
                    }
                }
                if (isset($data['subject_info']['identity_info']->id_doc_info)) {
                    $doc_info = &$data['subject_info']['identity_info']->id_doc_info;
                    if (!$doc_info->id_doc_copy){
                        unset($data['subject_info']['identity_info']->id_doc_info);
                    } else {
                        if ($card_info->doc_period_longterm) {
                            unset($card_info->doc_period_longterm);
                            $card_info->doc_period_end = '长期';
                        }
                        $doc_info->id_doc_copy = $this->extractMediaId($doc_info->id_doc_copy);
                        $doc_info->id_doc_copy_back = $this->extractMediaId($doc_info->id_doc_copy_back);
                    }
                }
                if (isset($data['subject_info']['identity_info']->authorize_letter_copy)) {
                    $data['subject_info']['identity_info']->authorize_letter_copy = (string)$data['subject_info']['identity_info']['authorize_letter_copy'];
                }
            }


            $data['subject_info']['finance_institution'] = $data['subject_info']['finance_institution'] ? true : false;
            if (!$data['subject_info']['finance_institution']) {
                unset($data['subject_info']['finance_institution_info']);
            }

            if(isset($data['subject_info']['ubo_info_list']) && $data['subject_info']['ubo_info_list']) {
                foreach ($data['subject_info']['ubo_info_list'] as &$ubo) {
                    if(isset($ubo->ubo_id_doc_copy)) {
                        $ubo->ubo_id_doc_copy = $this->extractMediaId($ubo->ubo_id_doc_copy);
                    }
                    if (isset($ubo->ubo_id_doc_copy_back)) {
                        $ubo->ubo_id_doc_copy_back = $this->extractMediaId($ubo->ubo_id_doc_copy_back);
                    }
                }
            }
        }
        if (isset($data['business_info'])) {
            if (isset($data['business_info']['sales_info'])) {
                $sales_scenes_type = $data['business_info']['sales_info']->sales_scenes_type;
                $type = [
                    "SALES_SCENES_STORE"    => "biz_store_info",       //线下场所
                    "SALES_SCENES_MP"       => "mp_info",              //服务号与公众号
                    "SALES_SCENES_WEB"      => "web_info",             //互联网网站
                    "SALES_SCENES_APP"      => "app_info",             //App
                    "SALES_SCENES_WEWORK"   =>"wework_info",           //企业微信
                    "SALES_SCENES_MINI_PROGRAM"=> "mini_program_info", //小程序
                ];
                foreach ($type as $item => $value) {
                    if (!in_array($item,$sales_scenes_type)){
                        unset($data['business_info']['sales_info']->$value);
                    }
                }

                if (isset($data['business_info']['sales_info']->biz_store_info)) {
                    $biz = &$data['business_info']['sales_info']->biz_store_info;
                    $biz->store_entrance_pic = $this->extractMediaId($biz->store_entrance_pic);
                    $biz->indoor_pic = $this->extractMediaId($biz->indoor_pic);
                }
                if (isset($data['business_info']['sales_info']->mp_info)) {
                    $biz = &$data['business_info']['sales_info']->mp_info;
                    $biz->mp_pics = $this->extractMediaId($biz->mp_pics);
                }

                if (isset($data['business_info']['sales_info']->mini_program_info)) {
                    $biz = &$data['business_info']['sales_info']->mini_program_info;
                    $biz->mini_program_pics = $this->extractMediaId($biz->mini_program_pics);
                }

                if (isset($data['business_info']['sales_info']->app_info)) {
                    $biz = &$data['business_info']['sales_info']->app_info;
                    $biz->app_pics = $this->extractMediaId($biz->app_pics);
                }

                if (isset($data['business_info']['sales_info']->web_info)) {
                    $biz = &$data['business_info']['sales_info']->web_info;
                    $biz->web_pics = $this->extractMediaId($biz->web_pics ?? []);
                }

                if (isset($data['business_info']['sales_info']->wework_info)) {
                    $biz = &$data['business_info']['sales_info']->wework_info;
                    $biz->wework_pics = $this->extractMediaId($biz->wework_pics ?? []);
                }
            }
        }

        if(isset($data['settlement_info']['qualifications'])) {
            $data['settlement_info']['qualifications']= $this->extractMediaId($data['settlement_info']['qualifications']);
        }

        if (isset($data['addition_info'])) {
            $data['addition_info']['legal_person_commitment'] = $this->extractMediaId($data['addition_info']['legal_person_commitment']);
            $data['addition_info']['legal_person_video'] = $this->extractMediaId($data['addition_info']['legal_person_video']);
            if ($data['addition_info']['business_addition_pics']) {
                $data['addition_info']['business_addition_pics'] = $this->extractMediaId($data['addition_info']['business_addition_pics']);
            } else {
                $data['addition_info']['business_addition_pics'] = [];
            }
        }
        return $data;
    }

    private function extractMediaId($mediaObject)
    {
        if ($mediaObject instanceof \stdClass && isset($mediaObject->media_id)) {
            return (string)$mediaObject->media_id;
        }

        if (is_array($mediaObject)) {
            $media_id = array_column($mediaObject,'media_id');
            return $media_id;
        }
        return '';
    }

    private function subjectClient()
    {
        return Payment::instance()->subjectClient();
    }

    private function merchantClient()
    {
        return Payment::instance()->merchantClient();
    }

    /**
     * 生成申请单
     * @param $merId
     * @return string
     * @author Qinii
     * @day 6/24/21
     */
    public function getOutRequestNo($merId)
    {

        list($msec, $sec) = explode(' ', microtime());
        $msectime = number_format((floatval($msec) + floatval($sec)) * 1000, 0, '', '');
        $key = 'MERCHANT' . $merId . '_' . $msectime . mt_rand(10000, max(intval($msec * 10000) + 10000, 98369));

        do{
            $ret = $this->dao->getSearch(['out_request_no' => $key])->count();
        }while($ret);

        return $key;
    }

    /**
     * 详情
     * @param $id
     * @param $merId
     * @return array|\think\Model|null
     * @author Qinii
     * @day 6/22/21
     */
    public function detail(int $merId)
    {
        $tool = systemConfig('wechat_service_tool');
        $where = [
            'mer_id' => $merId,
            'type' => $tool ? 1 : 0,
        ];
        $data = $this->dao->getSearch($where)->find();
        if(!$data) return [];
        $data['info'] = json_decode($data['info']);
        return $data;
    }

    /**
     * 编辑
     * @param $id
     * @param $data
     * @author Qinii
     * @day 6/22/21
     */
    public function edit($id, $data)
    {
        //申请状态: 0.平台未提交，-1.平台驳回，10.平台提交审核中，11.需用户操作 ，20.已完成，30.已冻结，40.驳回
        $get = $this->dao->get($id);
        if(!$get) throw new ValidateException('数据不存在');
        if(!in_array($get['status'],[-1,0,40])) throw new ValidateException('数据当前状态不可编辑');
        $out_request_no = $this->getOutRequestNo($get->mer_id);
        if ($get->type) {
            $data['business_code'] = $out_request_no;
        } else {
            $data['out_request_no'] = $out_request_no;
        }
        $ret['info'] = json_encode($data,JSON_UNESCAPED_UNICODE);
        $ret['status'] = 0;
        $ret['message'] = '';
        $ret['out_request_no'] = $out_request_no;
        $ret['applyment_id'] = '';
        $this->dao->update($id,$ret);
    }

    /**
     * 查询申请状态
     * @param $id
     * @author Qinii
     * @day 6/23/21
     */
    public function check(MerchantApplyments $ret)
    {
        if($ret['status'] < 10) throw new ValidateException('平台审核中...');
        if($ret['status'] == 20) throw new ValidateException('申请已完成，请勿重复查询');
        if ($ret['type']) {
            $result = $this->getSubject($ret);
        } else {
            $result = $this->getApplication($ret);
        }
        if($result) {
            $this->sendSms($ret,$result['status']);
            return Db::transaction(function () use ($ret, $result) {
                if (isset($result['sub_mchid'])) $this->profitsharingAdd($ret,$result);
                $this->dao->update($ret->mer_applyments_id, $result);
            });
        }else{
            return ;
        }
    }

    public function getSubject($ret)
    {
        $client = $this->subjectClient();
        $data = null;
        try{
            $data = $client->getApplicationById($ret->applyment_id);
        }catch (\Exception $exception) {
        }
        if(!$data){
            $data = $client->getApplicationByNo($ret->out_request_no);
            if($data){
                $ret->applyment_id = $data['applyment_id'];
                $ret->save();
            }
        }
        if ($data)
            return $this->subjectStatus($data);

        return false;
    }

    public function subjectStatus($data)
    {
        /**
         * APPLYMENT_STATE_EDITTING:（编辑中）提交申请发生错误导致，请尝试重新提交。
         * APPLYMENT_STATE_AUDITING:（审核中）申请单正在审核中，超级管理员用微信打开“签约链接”，完成绑定微信号后，申请单进度将通过微信公众号通知超级管理员，引导完成后续步骤。
         * APPLYMENT_STATE_REJECTED:（已驳回）请按照驳回原因修改申请资料，超级管理员用微信打开“签约链接”，完成绑定微信号，后续申请单进度将通过微信公众号通知超级管理员。
         * APPLYMENT_STATE_TO_BE_CONFIRMED:（待账户验证）请超级管理员使用微信打开返回的“签约链接”，根据页面指引完成账户验证
         * APPLYMENT_STATE_TO_BE_SIGNED:（待签约）请超级管理员使用微信打开返回的“签约链接”，根据页面指引完成签约。
         * APPLYMENT_STATE_SIGNING:（开通权限中）系统开通相关权限中，请耐心等待。
         * APPLYMENT_STATE_FINISHED:（已完成）商户入驻申请已完成。
         * APPLYMENT_STATE_CANCELED:（已作废）申请单已被撤销。
         */
        //申请状态: 0.平台未提交，-1.平台驳回，10.平台提交审核中，11.需用户操作 ，20.已完成，30.已冻结，40.驳回
        $result = [];
        $status = 0;
        $message = '';
        switch ($data['applyment_state']) {
            case 'APPLYMENT_STATE_REJECTED':
                foreach ($data['audit_detail'] as $datum){
                    $message .= '参数名称:'.$datum['param_name'].PHP_EOL;
                    $message .= '驳回原因:'.$datum['reject_reason'].PHP_EOL;
                }
                $status = 40;
                break;
            case 'APPLYMENT_STATE_TO_BE_CONFIRMED':
                $message = $data['sign_url'];
                $status = 1;
                break;
            case 'APPLYMENT_STATE_AUDITING':
                $message = $data['sign_url'];
                $status = 11;
                break;
            case 'APPLYMENT_STATE_TO_BE_SIGNED':
                $message = $data['sign_url'];
                $status = 11;
                break;
            case 'APPLYMENT_STATE_SIGNING':
                $message = $data['sign_url'];
                $status = 11;
                break;
            case 'APPLYMENT_STATE_FINISHED':
                $result['sub_mchid'] = $data['sub_mchid'];
                $status = 20;
                break;
            case 'APPLYMENT_STATE_CANCELED':
                $message = $data['applyment_state_msg'];
                $status = 40;
                break;
        }
        $result['status'] = $status;
        return $result;
    }

    public function getApplication($ret)
    {
        $client = $this->merchantClient();
        $data = null;
        try{
            $data = $client->getApplicationById($ret->applyment_id);
        }catch (\Exception $exception){
        }
        if(!$data){
            $data = $client->getApplicationByNo($ret->out_request_no);
            if($data){
                $ret->applyment_id = $data['applyment_id'];
                $ret->save();
            }
        }
        if ($data)
            return $this->getApplymentState($data);
        return false;
    }

    /**
     * 添加分账商户
     * @param MerchantApplyments $ret
     * @param $result
     * @author Qinii
     * @day 6/24/21
     */
    public function profitsharingAdd(MerchantApplyments $ret,$result)
    {
        $data = [];
        if ($ret['type']) {
            $data = ['applyment_id' => $result['sub_mchid']];
        } else {
            $profitsharing = [
                "type"    => 'MERCHANT_ID',
                "account" =>  $result['sub_mchid'],
                "relation_type" => "PLATFORM"
            ];
            $res = $this->merchantClient()->profitsharingAdd($profitsharing);
            if(isset($res['account'])) {
                $data = ['sub_mchid' => $res['account'] ];
            }
        }
        $merchantRepository = app()->make(MerchantRepository::class);
        $merchantRepository->update($ret->mer_id, $data);
    }

    /**
     * 查询返回的状态整理
     * @param $data
     * @return array
     * @author Qinii
     * @day 6/23/21
     */
    public function getApplymentState($data)
    {
        //CHECKING：资料校验中
        //ACCOUNT_NEED_VERIFY：待账户验证
        //AUDITING：审核中
        //REJECTED：已驳回
        //NEED_SIGN：待签约
        //FINISH：完成
        //FROZEN：已冻结
        $result = [];
        $message = '';
        $status = 10;
        switch (($data['applyment_state']))
        {
            //申请状态: 0.平台未提交，-1.平台驳回，10.平台提交审核中，11.需用户操作 ，20.已完成，30.已冻结，40.驳回
            case 'ACCOUNT_NEED_VERIFY':
                $status = 11;
                if(isset($data['account_validation'])){
                    $ret = $data['account_validation'];
                    $message = '通过申请银行账号向以下信息汇款完成验证,'.PHP_EOL;
                    $message .= '收款方信息：'.PHP_EOL;
                    $message .= "汇款金额：".$ret['pay_amount'].'（单位：分）;'.PHP_EOL;
                    $message .= "收款卡号：".$ret['destination_account_number'].';'.PHP_EOL;
                    $message .= "收款户名：".$ret['destination_account_name'].';'.PHP_EOL;
                    $message .= "开户银行：".$ret['destination_account_bank'].';'.PHP_EOL;
                    $message .= "省市信息：".$ret['city'].';'.PHP_EOL;
                    $message .= "备注信息：".$ret['remark'].';'.PHP_EOL;
                    $message .= "汇款截止时间：".$ret['deadline'].'。';
                }
                if(isset($data['legal_validation_url'])){
                    $message = '商户法人通过此链接完成验证：'.$data['legal_validation_url'];
                }
                break;
            case 'REJECTED':
                $message = '';
                foreach ($data['audit_detail'] as $datum){
                    $message .= '参数名称:'.$datum['param_name'].PHP_EOL;
                    $message .= '驳回原因:'.$datum['reject_reason'].PHP_EOL;
                }
                $status = 40;
                break;
            case 'NEED_SIGN':
                $status = 11;
                $message = $data['sign_url'];
                break;
            case 'FINISH':
                $result['sub_mchid'] = $data['sub_mchid'];
                $status = 20;
                $message = '完成';
                break;
            case 'FROZEN':
                $status = 30;
                break;
            default:
                break;
        }
        $result['status'] = $status;
        $result['message'] = $message;
        return $result;
    }

    /**
     * 上传图片
     * @param $field
     * @return array
     * @author Qinii
     * @day 6/21/21
     */
    public function uploadImage($field,$water)
    {
        $upload = UploadService::create(1);
        $info = $upload->to('def')->move($field);
        if ($info === false) throw new ValidateException($upload->getError());
        $res = $upload->getUploadInfo();
        $res['path'] = app()->getRootPath().'public'.($res['dir']);
        $res['dir'] = tidy_url($res['dir']);
        $ret = [];
        if($res['path']) {
            $item = $this->merchantClient()->upload($res['path'], $res['name'] ?? basename($res['path']));
            $ret[] = [
                'dir' => $res['dir'],
                'media_id' => $item['media_id'] ?? '',
            ];
        }
        if(!$water) app()->make(ImageWaterMarkService::class)->run($res['path']);
        return $ret;
    }

    public function uploadVideo($field)
    {
        $upload = UploadService::create(1);
        $data = $upload->to('subject')->move($field);
        if ($data === false)
            throw new  ValidateException($upload->getError());
        $res = $upload->getUploadInfo();
        $res['path'] = app()->getRootPath().'public'.($res['dir']);
        $res['dir'] = tidy_url($res['dir']);
        if($res['path']) {
            $item = $this->merchantClient()->videoUpload($res['path'], $res['name'] ?? basename($res['path']));
            return [[
                'dir' => $res['dir'],
                'media_id' => $item['media_id'] ?? '',
            ]];
        }
        return false;
    }

    /**
     * 列表
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     * @author Qinii
     * @day 6/24/21
     */
    public function getList(array $where, int $page, int $limit)
    {
        $query = $this->dao->getSearch($where)->with(['merchant' => function($query){
            $query->field('mer_id,mer_name');
        }])->order('create_time DESC');
        $count = $query->count();
        $list = $query->page($page,$limit)->select();
        return compact('count','list');
    }



    /**
     * 审核操作
     * @param int $id
     * @param array $data
     * @author Qinii
     * @day 6/23/21
     */
    public function switchWithStatus(int $id,array $data)
    {
        $ret = $this->dao->get($id);
        if(!$ret) throw new ValidateException('数据不存在');
        if($ret['status'] !== 0) throw new ValidateException('请勿重复审核');

        if($data['status'] == 10){
            if ($ret['type']) {
                $info = $this->subjectData(json_decode($ret['info']));
                $services = $this->subjectClient();
            } else {
                $info = $this->sltData(json_decode($ret['info']));
                $services = $this->merchantClient();
            }
            Db::transaction(function() use($services,$id,$info){
                $services->submitApplication($info);
                $this->dao->update($id,['status' => 10]);
            });
        }
        if($data['status'] == -1) {
            $this->dao->update($id,$data);
            $this->sendSms($ret,-1);
        }

        return ;
    }

    /**
     * 发送短信
     * @param MerchantApplyments $ret
     * @param $type
     * @author Qinii
     * @day 7/9/21
     */
    public function sendSms(MerchantApplyments $ret,$type)
    {
        if(!systemConfig('applyments_sms')) return ;
        $info = json_decode($ret['info']);
        switch ($type)
        {
            case -1:
                $tmp = 'APPLYMENTS_FAIL';
                break;
            case 11:
                $tmp = 'APPLYMENTS_SIGN';
                break;
            case 20:
                $tmp = 'APPLYMENTS_SUCCESS';
                break;
            case 40:
                $tmp = 'APPLYMENTS_FAIL';
                break;
            default:
                return ;
                break;
        }
        Queue::push(SendSmsJob::class,['tempId' => $tmp, 'id' => ['phone'=> $info->contact_info->mobile_phone, 'mer_name' => $info->merchant_shortname]]);
    }

    /**
     * 查询商户的分账信息
     * @param $merId
     * @return mixed
     * @author Qinii
     * @day 6/24/21
     */
    public function getMerchant($merId)
    {
        $data = app()->make(MerchantRepository::class)->get($merId);
        if(!$data) throw new ValidateException('数据不存在');
        if(!$data['sub_mchid']) throw new ValidateException('该商户不是分账商户');
        $ret = $this->merchantClient()->getSubMerchant($data['sub_mchid']);

        return $ret;
    }

    /**
     * 备注
     * @param $id
     * @return \FormBuilder\Form
     * @author Qinii
     * @day 7/5/21
     */
    public function markForm($id)
    {
        $data = $this->dao->get($id);
        $form = Elm::createForm(Route::buildUrl('systemMerchantApplymentsMarrkSave', ['id' => $id])->build());
        $form->setRule([
            Elm::text('mark', '备注：', $data['mark'])->placeholder('请输入备注')->required('请输入备注'),
        ]);
        return $form->setTitle('修改备注');
    }

    /**
     * 经营者/法人证件类型
     * @param $key
     * @return array|mixed
     * @author Qinii
     * @day 6/22/21
     */
    public function getIdDocType($key)
    {
        $data = [
            1 => self::IDCARD,
            2 => self::PASSPORT,
            3 => self::HONGKONG,
            4 => self::MACAO,
            5 => self::TAIWAN,
            6 => self::FOREIGN_RESIDENT,
            7 => self::MACAO_RESIDENT,
            8 => self::TAIWAN_RESIDENT,
        ];
        if($key) return $data[$key];
        return $data;
    }
}
