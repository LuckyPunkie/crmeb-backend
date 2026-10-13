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

use think\facade\Db;
use crmeb\traits\CategoresRepository;
use think\exception\ValidateException;
use app\common\repositories\BaseRepository;
use app\common\dao\system\merchant\StoreGroupDao;
use app\common\repositories\system\RelevanceRepository;

class StoreGroupRepository extends BaseRepository
{
    use CategoresRepository;

    const LIST_FIELDS = 'store_group_id,pid,name,sort,status,diy_temp_id,level,positioning_status,longitude,latitude';

    public function __construct(StoreGroupDao $dao)
    {
        $this->dao = $dao;
    }
    /**
     * 获取组织列表
     *
     * @param array $where
     * @param array $with
     * @return void
     */
    public function getList(array $where, array $with = [])
    {
        $query = $this->dao->search($where)
            ->when(!empty($with), function ($query) use ($with) {
                $query->with($with);
            });

        $count = $query->count();
        $list = formatCategory($query->field(self::LIST_FIELDS)->append(['merchant', 'merchant_count'], true)->select()->toArray(), $this->dao->getPk());

        return compact('list', 'count');
    }
    /**
     * 创建组织
     *
     * @param array $data
     * @return void
     */
    public function create(array $data)
    {
        if ($data['pid'] && !$this->dao->exists($data['pid'])) {
            throw new ValidateException('上级不存在!');
        }

        if ($data['pid'] && ($this->getLevelById($data['pid']) >= $this->dao->getMaxLevel())) {
            throw new ValidateException('最多' . $this->dao->getMaxLevel() . '级!');
        }
        $data[$this->dao->getPath()] = $this->getPathById($data[$this->dao->getParentId()]);
        $data[$this->dao->getLevel()] = $this->getLevelById($data[$this->dao->getParentId()]);

        return Db::transaction(function () use ($data) {
            // 创建分组
            $res = $this->dao->create($data);
            // 写入关联商户
            $merchantIds = $data['merchant_ids'];
            unset($data['merchant_ids']);
            app()->make(RelevanceRepository::class)->createMany(
                $res->store_group_id,
                $merchantIds,
                RelevanceRepository::STORE_GROUP
            );

            return true;
        });
    }
    /**
     * 更新组织
     *
     * @param integer $id
     * @param array $data
     * @return void
     */
    public function update(int $id, array $data)
    {
        $info = $this->dao->get($id);
        if (!$info) {
            throw new ValidateException('记录不存在!');
        }
        if ($data['pid'] && !$this->dao->exists($data['pid'])) {
            throw new ValidateException('上级不存在!');
        }
        if ($data['pid'] && ($this->getLevelById($data['pid']) >= $this->dao->getMaxLevel())) {
            throw new ValidateException('最多' . $this->dao->getMaxLevel() . '级!');
        }
        if ($this->dao->checkChangeToChild($id, $data['pid'])) {
            throw new ValidateException('无法修改到当前分类到子集，请先修改子类');
        }
        if (!$this->checkChildLevel($id, $data['pid'])) {
            throw new ValidateException('子类超过最高等级限制，请先修改子类');
        }

        $data[$this->dao->getPath()] = $this->getPathById($data[$this->dao->getParentId()]);
        $data[$this->dao->getLevel()] = $this->getLevelById($data[$this->dao->getParentId()]);
        $data['update_time'] = date('Y-m-d H:i:s');

        return Db::transaction(function () use ($id, $data) {
            $relevanceRepository = app()->make(RelevanceRepository::class);
            // 删除关联商户
            $relevanceRepository->batchDelete($id, RelevanceRepository::STORE_GROUP);
            // 写入关联商户
            $merchantIds = $data['merchant_ids'];
            unset($data['merchant_ids']);
            $relevanceRepository->createMany($id, $merchantIds, RelevanceRepository::STORE_GROUP);
            // 更新分组信息
            $this->dao->update($id, $data);

            return true;
        });
    }
    /**
     * 删除组织
     *
     * @param integer $id
     * @return void
     */
    public function delete(int $id)
    {
        $info = $this->dao->get($id);
        if (!$info) {
            throw new ValidateException('记录不存在!');
        }
        if ($this->dao->hasChild($id)) {
            throw new ValidateException('该代理存在子级，请先处理子级');
        }

        return $this->dao->delete($id);
    }
    /**
     * 切换组织状态
     *
     * @param integer $id
     * @param array $data
     * @return void
     */
    public function switch(int $id, array $data)
    {
        $info = $this->dao->get($id);
        if (!$info) {
            throw new ValidateException('记录不存在!');
        }

        $info->status = $data['status'];

        // 开启事物
        return Db::transaction(function () use ($id, $data, $info) {
            $info->save();
            // 切换子分类状态
            if($info->level < 2) {
                $this->dao->search(['path' => $id])->update(['status' => $data['status']]);
            }

            return true;
        });
    }
    /**
     * 设置首页模板
     *
     * @param integer $id
     * @param array $data
     * @return void
     */
    public function setTemplate(int $id, array $data)
    {
        $info = $this->dao->get($id);
        if (!$info) {
            throw new ValidateException('记录不存在!');
        }

        $info->diy_temp_id = $data['diy_temp_id'];

        return $info->save();
    }
    /**
     * 获取分组下关联的店铺列表
     *
     * @param integer $id
     * @param integer $page
     * @param integer $limit
     * @param array $where
     * @return void
     */
    public function stores($id, $page, $limit, $where)
    {
        $info = $this->dao->get($id);
        if (!$info) {
            throw new ValidateException('记录不存在!');
        }

        $where['mer_id'] = app()->make(RelevanceRepository::class)->getRightIds($id, RelevanceRepository::STORE_GROUP);
        if(empty($where['mer_id'])){
            return [];
        }

        $query = app()->make(MerchantRepository::class)->search($where);
        $count = $query->count();
        $list = $query->page($page, $limit)->field('mer_id, mer_name, real_name ,mer_phone, region_id,status,mer_state')->select();

        return compact('list', 'count');
    }
    /**
     * 移动端获取推荐分组列表
     *
     * @param array $params
     * @return void
     */
    public function recommendList(array $params)
    {
        $where = [
            'status' => 1,
            'positioning_status' => 1
        ];
        $list = $this->search($where)->field(self::LIST_FIELDS)->select()->toArray();
        // 取距离最近的前6个商圈
        $data = [];
        foreach ($list as &$item) {
            if (count($data) >= 6) {
                break;
            }

            $distance = getDistance($params['latitude'], $params['longitude'], $item['latitude'], $item['longitude']);
            $item['distanceM'] = $distance;
            // 距离单位转换
            if ($distance < 0.9) {
                $distance = max(bcmul($distance, 1000, 0), 1) . 'm';
                if ($distance == '1m') {
                    $distance = '100m以内';
                }
            } else {
                $distance .= 'km';
            }
            $item['distance'] = $distance;

            $data[] = $item;
        }

        // 距离排序
        usort($data, function ($a, $b) {
            return ($a['distanceM'] ?? 0) <=> ($b['distanceM'] ?? 0);
        });

        return $data;
    }
 }
