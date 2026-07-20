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


namespace app\validate\merchant;


use think\Validate;

class MerchantApplymentsSubjectValidate extends Validate
{
    protected $failException = true;

    protected $rule = [
        'subject_info|主体资料' => 'require|Array|validateSubjectInfo',
        'contact_info|超级管理员信息' => 'require|validateContactInfo',
        'business_info|经营资料' => 'require|validateBusinessInfo',
        'settlement_info|结算规则' => 'require|validateSettlementInfo',
        'bank_account_info|结算银行账户' => 'require|validateBankAccountInfo'
    ];

    protected  function validateContactInfo($item)
    {
        if (!$item['contact_type'] || !in_array($item['contact_type'],['LEGAL','SUPER'])) return $this->error('超级管理员类型');
        if (!$item['contact_name']) return $this->error('超级管理员姓名');
        if (!$item['contact_type'] == 'SUPER') {
            if (!$item['contact_id_doc_type']) return $this->error('超级管理员证件类型');
            if (!$item['contact_id_number']) return $this->error('超级管理员身份证件号码');
            if (!$item['contact_id_doc_copy']) return $this->error('超级管理员证件正面照片');
            if (!$item['contact_id_doc_copy_back']) return $this->error('超级管理员证件反面照片');
            if (!$item['contact_period_begin']) return $this->error('超级管理员证件有效期开始时间');
            if (!$item['contact_period_end']) return $this->error('超级管理员证件有效期结束时间');
        }
        if (!$item['mobile_phone']) return $this->error('超级管理员联系手机号码');
        if (!$item['contact_email']) return $this->error('超级管理员联系邮箱');
        return true;
    }

    protected  function validateSubjectInfo($item)
    {
        if (in_array($item['subject_type'],['SUBJECT_TYPE_INDIVIDUAL','SUBJECT_TYPE_ENTERPRISE'])) {
            if (empty($item['business_license_info']))return $this->error('营业执照信息');
            if (!$item['business_license_info']['license_copy'])return $this->error('营业执照照片');
            if (!$item['business_license_info']['license_number'])return $this->error('注册号/统一社会信用代码');
            if (!$item['business_license_info']['merchant_name'])return $this->error('商户名称');
            if (!$item['business_license_info']['legal_person'])return $this->error('个体户经营者/法定代表人姓名');
        } else {
            if (empty($item['certificate_info'])) return $this->error('组织机构代码信息');
            if (!$item['certificate_info']['cert_copy']) return $this->error('登记证书照片');
            if (!$item['certificate_info']['cert_type']) return $this->error('登记证书类型');
            if (!$item['certificate_info']['cert_number']) return $this->error('证书号');
            if (!$item['certificate_info']['merchant_name']) return $this->error('商户名称');
            if (!$item['certificate_info']['company_address']) return $this->error('注册地址');
            if (!$item['certificate_info']['legal_person']) return $this->error('法定代表人');
            if (!$item['certificate_info']['period_begin']) return $this->error('有效期限开始日期');
            if (!$item['certificate_info']['period_end']) return $this->error('有效期限结束日期');
        }

        if ($item['finance_institution']) {
            if (empty($item['finance_institution_info'])) return  $this->error('金融机构许可证信息');
            if (!$item['finance_institution_info']['finance_type']) return $this->error('金融机构类型');
            if (!$item['finance_institution_info']['finance_license_pics']) return $this->error('金融机构许可证图片');
        }
        if (empty($item['identity_info'])) return $this->error('经营者/法定代表人身份证件');
        if ($item['identity_info']['id_holder_type'] == 'LEGAL'){
            if (!$item['identity_info']['id_doc_type']) return $this->error('证件类型');
            if (
                $item['subject_type'] == 'SUBJECT_TYPE_GOVERNMENT' &&
                $item['identity_info']['id_doc_type'] != 'IDENTIFICATION_TYPE_IDCARD'
            ) return '政府机关仅支持中国大陆居民-身份证类型';

            //当证件持有人类型为经营者/法定代表人且证件类型为“身份证”时填写。
            if ($item['identity_info']['id_doc_type'] == 'IDENTIFICATION_TYPE_IDCARD') {
                if (!$item['identity_info']['id_card_info']) return $this->error('身份证信息');
                if (!$item['identity_info']['id_card_info']['id_card_copy']) return $this->error('身份证人像面照片');
                if (!$item['identity_info']['id_card_info']['id_card_national']) return $this->error('身份证国徽面照片');
                if (!$item['identity_info']['id_card_info']['id_card_name']) return $this->error('身份证姓名');
                if (!$item['identity_info']['id_card_info']['id_card_number']) return $this->error('身份证号码');
                if (!$item['identity_info']['id_card_info']['card_period_begin']) return $this->error('身份证有效期开始时间');
                if (!$item['identity_info']['id_card_info']['card_period_end']) return $this->error('身份证有效期结束时间');
            } else { //当证件持有人类型为经营者/法定代表人且证件类型不为“身份证”时填写。
                if (!$item['identity_info']['id_doc_info']) return $this->error('其他类型证件信息');
                if (!$item['identity_info']['id_doc_info']['id_doc_copy']) return $this->error('证件正面照片');
                if (!$item['identity_info']['id_doc_info']['id_doc_copy_back']) return $this->error('证件反面照片');
                if (!$item['identity_info']['id_doc_info']['id_doc_name']) return $this->error('证件姓名');
                if (!$item['identity_info']['id_doc_info']['id_doc_number']) return $this->error('证件号码');
                if (!$item['identity_info']['id_doc_info']['doc_period_begin']) return $this->error('证件有效期开始时间');
                if (!$item['identity_info']['id_doc_info']['doc_period_end']) return $this->error('证件有效期结束时间');
            }
        } else {
            if (!$item['identity_info']['authorize_letter_copy']) return $this->error('法定代表人说明函');
        }
        return true;
    }

    protected function validateBusinessInfo($item)
    {
        if (!$item['merchant_shortname']) return $this->error('商户简称');
        if (!$item['service_phone']) return $this->error('客服电话');
        if (!isset($item['sales_info'])) return $this->error('经营场景');
        if (!$item['sales_info']['sales_scenes_type']) return $this->error('经营场景类型');
        return true;
    }


    protected function   validateSettlementInfo($item)
    {
        if (!$item['settlement_id']) return $this->error('入驻结算规则ID');
        if (!$item['qualification_type']) return $this->error('所属行业');
        return true;
    }

    protected function validateBankAccountInfo($item,$rule,$data)
    {
        if(!$item['bank_account_type']) return $this->error('银行账号类型');
        if(!$item['account_name']) return $this->error('开户名称');
        if(!$item['account_bank']) return $this->error('开户银行');
        if(!$item['account_number']) return $this->error('银行账号');
        return true;
    }

    protected function error($msg)
    {
        return '请填写' . $msg;
    }
}
