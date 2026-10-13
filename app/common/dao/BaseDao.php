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


namespace app\common\dao;


use app\common\model\BaseModel;
use Carbon\Carbon;
use crmeb\services\crud\CrudFormEnum;
use think\Collection;
use think\db\Builder;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\db\Query;
use think\Model;
use crmeb\services\crud\CrudOperatorEnum;

/**
 * Class BaseDao
 * @package app\common\dao
 * @author xaboy
 * @day 2020-03-30
 */
abstract class BaseDao
{
    /**
     * @return BaseModel
     * @author xaboy
     * @day 2020-03-30
     */
    abstract protected function getModel(): string;


    /**
     * @return string
     * @author xaboy
     * @day 2020-03-30
     */
    public function getPk()
    {
        return ($this->getModel())::tablePk();
    }

    /**
     * @param int $id
     * @return bool
     * @author xaboy
     * @day 2020-03-27
     */
    public function exists(int $id)
    {
        return $this->fieldExists($this->getPk(), $id);
    }

    public function merInExists(int $merId, $ids)
    {
        $pk = ($this->getModel())::getDB()->where('mer_id',$merId)->where($this->getPk(),'in',$ids)->column($this->getPk());
        $ids = is_array($ids) ? $ids : explode(',',$ids);
        sort($ids);
        sort($pk);
        return $ids == $pk;
    }

    /**
     * @param array $where
     * @return BaseModel
     */
    public function query(array $where):Query
    {
        return ($this->getModel())::getInstance()->where($where);
    }

    /**
     * @param $field
     * @param $value
     * @param int|null $except
     * @return bool
     * @author xaboy
     * @day 2020-03-30
     */
    public function fieldExists($field, $value, ?int $except = null): bool
    {
        $query = ($this->getModel())::getDB()->where($field, $value);
        if (!is_null($except)) $query->where($this->getPk(), '<>', $except);
        return $query->count() > 0;
    }

    /**
     * @param array $data
     * @return self|Model
     * @author xaboy
     * @day 2020-03-27
     */
    public function create(array $data)
    {
        return ($this->getModel())::create($data);
    }


    /**
     * @param int $id
     * @param array $data
     * @return int
     * @throws DbException
     * @author xaboy
     * @day 2020-03-27
     */
    public function update(int $id, array $data)
    {
        return ($this->getModel())::getDB()->where($this->getPk(), $id)->update($data);
    }

    /**
     * @param array $ids
     * @param array $data
     * @return int
     * @throws DbException
     * @author xaboy
     * @day 2020/6/8
     */
    public function updates(array $ids, array $data)
    {
        return ($this->getModel())::getDB()->whereIn($this->getPk(), $ids)->update($data);
    }


    /**
     * @param int $id
     * @return int
     * @throws DbException
     * @author xaboy
     * @day 2020-03-27
     */
    public function delete(int $id)
    {
        return ($this->getModel())::getDB()->where($this->getPk(), $id)->delete();
    }


    /**
     * @param int $id
     * @return array|Model|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-03-27
     */
    public function get($id)
    {
        return ($this->getModel())::getInstance()->find($id);
    }

    /**
     * @param array $where
     * @param string $field
     * @return array|Model|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020/6/1
     */
    public function getWhere(array $where, string $field = '*', array $with = [])
    {
        return ($this->getModel())::getInstance()->where($where)->when($with, function ($query) use ($with) {
            $query->with($with);
        })->field($field)->find();
    }

    /**
     * @param array $where
     * @param string $field
     * @return Collection
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020/6/1
     */
    public function selectWhere(array $where, string $field = '*')
    {
        return ($this->getModel())::getInstance()->where($where)->field($field)->select();
    }

    /**
     * @param int $id
     * @param array $with
     * @return array|Model|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-03-27
     */
    public function getWith(int $id, $with = [])
    {
        return ($this->getModel())::getInstance()->with($with)->find($id);
    }


    /**
     * @param array $data
     * @return int
     * @author xaboy
     * @day 2020/6/8
     */
    public function insertAll(array $data)
    {
        return ($this->getModel())::getDB()->insertAll($data);
    }

    /**
     *  通过条件判断是否存在
     * @param array $where
     * @author Qinii
     * @day 2020-06-13
     */
    public function getWhereCount(array $where)
    {
        $query = $this->getModel()::getDB();
        foreach ($where as $field => $value) {
            if (is_array($value)) {
                if (is_string($value[0])) {
                    $query->where($field, $value[0], $value[1] ?? null);
                } else {
                    $query->whereIn($field, $value);
                }
            } else {
                $query->where($field, $value);
            }
        }
        return $query->count();
    }

    public function existsWhere($where)
    {
        return ($this->getModel())::getDB()->where($where)->count() > 0;
    }

    /**
     *  查询,如果不存在就创建
     * @Author:Qinii
     * @Date: 2020/9/8
     * @param array $where
     * @return array|Model|null
     */
    public function findOrCreate(array $where)
    {
       $res = ($this->getModel()::getDB())->where($where)->find();
       if(!$res)$res = $this->getModel()::create($where);
       return $res;
    }

    /**
     *  搜索
     * @param $where
     * @return BaseModel
     * @author Qinii
     * @day 2020-10-16
     */
    public function getSearch(array $where)
    {
        foreach ($where as $key => $item) {
                if ($item !== '') {
                $keyArray[] = $key;
                $whereArr[$key] = $item;
            }
        }
        if(empty($keyArray)){
            return ($this->getModel())::getDB();
        }else{
            return ($this->getModel())::withSearch($keyArray, $whereArr);
        }
    }

    /**
     *  自增
     * @param array $id
     * @param string $field
     * @param int $num
     * @return mixed
     * @author Qinii
     * @day 1/11/21
     */
    public function incField(int $id, string $field , $num = 1)
    {
        return ($this->getModel()::getDB())->where($this->getPk(),$id)->inc($field,$num)->update();
    }

    /**
     *  自减
     * @param array $id
     * @param string $field
     * @param int $num
     * @return mixed
     * @author Qinii
     * @day 1/11/21
     */
    public function decField(int $id, string $field , $num = 1)
    {
        return ($this->getModel()::getDB())
            ->where($this->getPk(),$id)
            ->where($field, '>=' ,$num)
            ->dec($field,$num)->update();
    }

    public function merHas(int $merId, int $id, ?int $isDel = 0)
    {
        return ($this->getModel()::getDB())->where($this->getPk(), $id)->where('mer_id', $merId)
                ->when(!is_null($isDel), function($query) use($isDel) {
                    $query->where('is_del', $isDel);
                })->count($this->getPk()) > 0;
    }

    public function viewSearch(array $viewSearch = [], $query = null, string $defaulAlias = '')
    {
        if (is_null($query)) {
            $query = $this->getModel();
        }
        if(empty($viewSearch) || !isset($viewSearch[0]['boolean'])) return $query;
        $logic = $viewSearch[0]['boolean'] == 'and' ? 'where' : 'whereOr';
        $query = $query->where(function (Query $query) use ($viewSearch, $logic, $defaulAlias) {
            foreach ($viewSearch as $search) {
                if (empty($search['field_name']) || empty($search['operator'])) {
                    continue;
                }
                if (!isset($search['value'])) {
                    $search['value'] = '';
                }
                if (strstr($search['field_name'], '.') !== false) {
                    $fieldName = $search['field_name'];
                } else {
                    $alias     = $item['alias'] ?? $defaulAlias;
                    $fieldName = ($alias ? $alias . '.' : '') . $search['field_name'];
                }
                $query->{$logic}(function(Query  $query) use($search, $fieldName){
                    switch ($search['operator']) {
                        case CrudOperatorEnum::OPERATOR_IN :
                            if (isset($search['form_value'])) {
                                switch ($search['form_value']) {
                                    case CrudFormEnum::FORM_INPUT:
                                    case CrudFormEnum::FORM_TEXTAREA:
                                        if (is_array($search['value'])) {
                                            $search['value'] = json_encode($search['value']);
                                        }
                                        $query = $query->where($fieldName, 'LIKE', '%' . $search['value'] . '%');
                                        break;
                                    case CrudFormEnum::FORM_TAG:
                                    case CrudFormEnum::FORM_CHECKBOX:
                                    case CrudFormEnum::FORM_CASCADER_ADDRESS:
                                        $tags  = is_array($search['value']) ? $search['value'] : explode(',', $search['value']);
                                        $query = $query->where(function ($query) use ($tags, $fieldName) {
                                            foreach ($tags as $i => $tag) {
                                                if ($i) {
                                                    $query->whereOr($fieldName, 'like', '%/' . $tag . '/%');
                                                } else {
                                                    $query->where($fieldName, 'like', '%/' . $tag . '/%');
                                                }
                                            }
                                        });
                                        break;
                                    case CrudFormEnum::FORM_CASCADER_RADIO:
                                        $query = $query->where(function ($query) use ($search, $fieldName) {
                                            foreach ($search['value'] as $i => $val) {
                                                $val = implode('/', $val);
                                                if ($i) {
                                                    $query->whereOr($fieldName, 'like', '%/' . $val . '/%');
                                                } else {
                                                    $query->where($fieldName, 'like', '%/' . $val . '/%');
                                                }
                                            }
                                        });
                                        break;
                                    default:
                                        $query = $query->whereIn($fieldName, is_array($search['value']) ? $search['value'] : explode(',',$search['value']));
                                        break;
                                }
                            } else {
                                $query = $query->whereIn($fieldName, is_array($search['value']) ? $search['value'] : [$search['value']]);
                            }
                            break;
                        case CrudOperatorEnum::OPERATOR_NOT_IN:
                            if (isset($search['form_value'])) {
                                switch ($search['form_value']) {
                                    case CrudFormEnum::FORM_INPUT:
                                    case CrudFormEnum::FORM_TEXTAREA:
                                        if (is_array($search['value'])) {
                                            $search['value'] = json_encode($search['value']);
                                        }
                                        $query = $query->whereNot($fieldName, 'LIKE', '%' . $search['value'] . '%');
                                        break;
                                    case CrudFormEnum::FORM_CASCADER_RADIO:
                                        $query = $query->whereNot(function ($query) use ($search, $fieldName) {
                                            foreach ($search['value'] as $i => $val) {
                                                $val = implode('/', $val);
                                                if ($i) {
                                                    $query->whereOr($fieldName, 'like', '%/' . $val . '/%');
                                                } else {
                                                    $query->where($fieldName, 'like', '%/' . $val . '/%');
                                                }
                                            }
                                        });
                                        break;
                                    case CrudFormEnum::FORM_TAG:
                                    case CrudFormEnum::FORM_CHECKBOX:
                                    case CrudFormEnum::FORM_CASCADER_ADDRESS:
                                        $tags  = is_array($search['value']) ? $search['value'] : explode(',', $search['value']);
                                        $query = $query->whereNot(function ($query) use ($tags, $fieldName) {
                                            foreach ($tags as $i => $tag) {
                                                if ($i) {
                                                    $query->whereOr($fieldName, 'like', '%/' . $tag . '/%');
                                                } else {
                                                    $query->where($fieldName, 'like', '%/' . $tag . '/%');
                                                }
                                            }
                                        });
                                        break;
                                    default:
                                        $query = $query->whereIn($fieldName, is_array($search['value']) ? $search['value'] : [$search['value']]);
                                        break;
                                }
                            } else {
                                $query = $query->whereNotIn($fieldName, is_array($search['value']) ? $search['value'] : [$search['value']]);
                            }
                            break;
                        case CrudOperatorEnum::OPERATOR_EQ:
                            switch ($search['form_value']) {
                                case CrudFormEnum::FORM_DATE_TIME_PICKER:
                                    $query = $query->whereDay($fieldName,$search['value']);
                                    break;
                                default:
                                    $query = $query->where($fieldName, $search['value']);
                                    break;
                            }
                            break;
                        case CrudOperatorEnum::OPERATOR_GT:
                            switch ($search['form_value']) {
                                case CrudFormEnum::FORM_DATE_TIME_PICKER:
                                    $query = $query->whereTime($fieldName,'>', $search['value'].'23:59:59');
                                    break;
                                default:
                                    $query = $query->where($fieldName,'>', $search['value']);
                                    break;
                            }
                            break;
                        case CrudOperatorEnum::OPERATOR_GT_EQ:
                            switch ($search['form_value']) {
                                case CrudFormEnum::FORM_DATE_TIME_PICKER:
                                    $query = $query->whereTime($fieldName,'>=', $search['value']);
                                    break;
                                default:
                                    $query = $query->where($fieldName, '>=', $search['value']);
                                    break;
                            }
                            break;
                        case CrudOperatorEnum::OPERATOR_LT:
                            switch ($search['form_value']) {
                                case CrudFormEnum::FORM_DATE_TIME_PICKER:
                                    $query = $query->whereTime($fieldName,'<', $search['value']);
                                    break;
                                default:
                                    $query = $query->where($fieldName, '<', $search['value']);
                                    break;
                            }
                            break;
                        case CrudOperatorEnum::OPERATOR_LT_EQ:
                            switch ($search['form_value']) {
                                case CrudFormEnum::FORM_DATE_TIME_PICKER:
                                    $query = $query->whereTime($fieldName,'<=', $search['value']. ' 23:59:59');
                                    break;
                                default:
                                    $query = $query->where($fieldName, '<=', $search['value']);
                                    break;
                            }
                            break;
                        case CrudOperatorEnum::OPERATOR_NOT_EQ:
                            switch ($search['form_value']) {
                                case CrudFormEnum::FORM_DATE_TIME_PICKER:
                                    $query = $query->whereNotBetweenTime($fieldName, $search['value'], $search['value'].' 23:59:59');
                                    break;
                                default:
                                    $query = $query->where($fieldName, '<>', $search['value']);
                                    break;
                            }
                            break;
                        case CrudOperatorEnum::OPERATOR_IS_EMPTY:
                            $query = $query->whereNull($fieldName);
                            break;
                        case CrudOperatorEnum::OPERATOR_NOT_EMPTY:
                            $query = $query->whereNotNull($fieldName);
                            break;
                        case CrudOperatorEnum::OPERATOR_BT:
                            switch ($search['form_value']) {
                                case CrudFormEnum::FORM_DATE_TIME_PICKER:
                                    [$startTime,$endTime] = explode('-', $search['value']);
                                    $query = $query->whereBetweenTime($fieldName, $startTime,$endTime.' 23:59:59');
                                    break;
                                default:
                                    $query = $query->whereBetween($fieldName, $search['value']);
                                    break;
                            }
                            break;
                        case CrudOperatorEnum::OPERATOR_N_DAY:
                            $query = $query->whereTime($fieldName, '<', Carbon::today()->subDays((int)$search['value'])->toDateTimeString());
                            break;
                        case CrudOperatorEnum::OPERATOR_LAST_DAY:

                            $query = $query->whereBetweenTime($fieldName, Carbon::today()->subDays((int)$search['value'])->toDateTimeString(), Carbon::today()->toDateTimeString());
                            break;
                        case CrudOperatorEnum::OPERATOR_NEXT_DAY:
                            $query = $query->whereBetweenTime($fieldName, Carbon::today()->toDateTimeString(), Carbon::today()->addDays((int)$search['value'])->toDateTimeString());
                            break;
                        case CrudOperatorEnum::OPERATOR_TO_DAY:
                            $query = $query->whereTime($fieldName, Carbon::today()->toDateString());
                            break;
                        case CrudOperatorEnum::OPERATOR_WEEK:
                            $query = $query->whereWeek($fieldName);
                            break;
                        case CrudOperatorEnum::OPERATOR_MONTH:
                            $query = $query->whereMonth($fieldName);
                            break;
                        case CrudOperatorEnum::OPERATOR_QUARTER:
                            $query = $query->whereBetweenTime($fieldName, Carbon::today()->startOfQuarter()->toDateTimeString(), Carbon::today()->endOfQuarter()->toDateTimeString());
                            break;
                    }
                });
            }
        });
        return $query;
    }

}
