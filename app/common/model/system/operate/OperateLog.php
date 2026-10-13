<?php

namespace app\common\model\system\operate;

use app\common\model\BaseModel;
use app\common\repositories\system\operate\OperateLogRepository;

class OperateLog extends BaseModel
{

    public static function tablePk(): string
    {
        return 'operate_log_id';
    }


    public static function tableName(): string
    {
        return 'operate_log';
    }

    public function getActionAttr($value, $data)
    {
        if (!empty($value)) {
            return OperateLogRepository::getActionName($value);
        }
    }

    public function getRelevanceTypeAttr($value, $data)
    {
        if (!empty($value)) {
            return OperateLogRepository::getRelevanceTypeName($value);
        }
    }

    public function getTypeAttr($value, $data)
    {
        if (!empty($value)) {
            return OperateLogRepository::getTypeName($value);
        }
    }

    public function getCategoryAttr($value, $data)
    {
        if (!empty($value)) {
            return OperateLogRepository::getCategoryName($value);
        }
    }

    public function getCategoryNameAttr($value, $data)
    {
        if (empty($data['title'])) {
            return OperateLogRepository::getActionName($data['action']) . ':' . OperateLogRepository::getCategoryName($data['category']);
        } else {
            return $data['title'];
        }
    }

    public function getOperatorNicknameAttr($value, $data)
    {
        return $value . '/ID:' . $data['operator_uid'];
    }
}
