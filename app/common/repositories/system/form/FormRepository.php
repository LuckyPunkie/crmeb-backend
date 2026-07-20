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


namespace app\common\repositories\system\form;


use app\common\dao\system\form\FormDao;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\product\ProductRepository;
use crmeb\services\ExcelService;
use think\exception\ValidateException;
use think\facade\Cache;

/**
 * 表单
 */
class FormRepository extends BaseRepository
{
    public function __construct(FormDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 根据条件获取列表数据
     *
     * 本函数用于根据给定的条件数组和分页信息，从数据库中检索并返回列表数据。
     * 它首先构造一个查询条件，然后计算符合条件的记录总数，最后根据分页信息和隐藏字段要求，
     * 获取指定页码的列表数据。
     *
     * @param array $where 查询条件数组
     * @param int $page 当前页码
     * @param int $limit 每页显示的记录数
     * @return array 包含总数和列表数据的数组
     */
    public function getList(array $where, $page, $limit)
    {
        // 根据条件数组进行查询
        $query = $this->dao->search($where);

        // 计算符合条件的记录总数
        $count = $query->count();

        // 获取指定页码的列表数据，隐藏'form_keys'字段
        $list = $query->page($page, $limit)->hidden(['form_keys'])->select();

        // 返回包含总数和列表数据的数组
        return compact('count', 'list');
    }

    /**
     * 获取表单的字段及验证属性
     * @param $formId
     * @return array
     * @author Qinii
     * @day 2023/10/8
     */
    public function getFormKeys(int $formId, $merId = null)
    {
        $where = ['form_id' => $formId, 'status' => 1, 'is_del' => 0];
        if ($merId) $where['mer_id'] = $merId;
        $form_info = $this->dao->getSearch($where)->field('form_keys,value')->find();
        if (!$form_info) throw new ValidateException('表单信息不存在');
        $data = [];
        $res = $form_info['form_keys'];
        foreach ($res as $item) {
            $data[] = $item->key;
            $val[$item->key] = [
                'label' => $item->label,
                'val' => $item->val,
                'type'=> $item->type
            ];
        }
        $form = json_encode($form_info['form_keys'], JSON_UNESCAPED_UNICODE);
        $form_value = json_encode($form_info['value'], JSON_UNESCAPED_UNICODE);
        return compact('form', 'data', 'val', 'form_value');
    }

    /**
     * 导出表格数据。
     * 根据给定的查询条件、页码和每页数量，以及商家ID，从缓存中获取或生成表格的头部和数据信息，
     * 然后调用Excel服务类来生成并返回表格数据。
     *
     * @param array $where 查询条件数组，包含链接ID等信息。
     * @param int $page 当前页码。
     * @param int $limit 每页的数据数量。
     * @param int $merId 商家ID。
     * @return mixed 返回生成的表格数据。
     */
    public function excel(array $where, int $page, int $limit, int $merId)
    {
        // 根据链接ID和商家ID生成缓存键名
        $cahce_key = 'form_headers_' . $where['link_id'] . '_' . $merId;

        // 尝试从缓存中获取头部和信息数组，如果缓存不存在则进行初始化
        if (![$header, $info] = Cache::get($cahce_key)) {
            // 初始化头部数组，包含基础字段
            $header = ['活动ID', '活动名称', '用户ID', '昵称', '手机号码'];

            // 根据链接ID和商家ID获取表单配置
            $keys = $this->getFormKeys($where['link_id'], $merId);
            // 解析表单配置，获取所有字段标签和键名
            $form = json_decode($keys['form']);
            foreach ($form as $key) {
                $header[] = $key->label; // 将字段标签添加到头部数组
                $info[] = $key->key; // 将字段键名添加到信息数组
            }
            // 添加创建时间字段到头部数组
            $header[] = '创建时间';

            // 将头部和信息数组存入缓存，缓存有效期为15分钟
            Cache::set($cahce_key, [$header, $info], 60 * 15);
        }

        // 调用Excel服务类，传入头部、信息数组和其他查询条件，生成并返回表格数据
        return app()->make(ExcelService::class)->userForm($header, $info, $where, $page, $limit);
    }


    /**
     * 删除表单及其关联商品信息。
     *
     * 本函数主要用于删除指定ID的表单，并根据需要删除该表单关联的商品信息。
     * 这里的"表单"是抽象的概念，可以指代任何需要删除的实体，比如数据库中的记录。
     * 如果传入的$mer_id大于0，则表示需要删除与该表单关联的商品信息。
     *
     * @param int $id 表单的唯一标识ID，用于指定要删除的表单。
     * @param int $mer_id 商家ID，用于指定要删除的表单关联的商品信息。如果为0，则不删除关联商品信息。
     */
    public function delete(int $id, int $mer_id = 0)
    {
        // 删除指定ID的表单
        // 删除表单
        $this->dao->delete($id);
        // 如果传入的$mer_id大于0，且存在关联商品，则删除这些关联商品
        if ($mer_id) {
            // 删除所有表单关联商品
            app()->make(ProductRepository::class)->deleteProductFormByFormId($id, $mer_id);
        }
    }
}
