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


namespace app\controller\admin\system\form;


use app\common\repositories\store\StoreActivityRelatedRepository;
use app\common\repositories\system\form\FormRepository;
use app\common\repositories\system\notice\SystemNoticeRepository;
use app\validate\admin\SystemNoticeValidate;
use crmeb\basic\BaseController;
use crmeb\services\ExcelService;
use think\App;
use think\Exception;
use think\exception\ValidateException;

/**
 * 系统表单
 */
class Form extends BaseController
{
    /**
     * @var FormRepository
     */
    protected $repository;

    /**
     * SystemNotice constructor.
     * @param App $app
     * @param FormRepository $repository
     */
    public function __construct(App $app, FormRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 列表
     * @return \think\response\Json
     * @author Qinii
     */
    public function lst()
    {
        $where = $this->request->params(['keyword', 'date']);
        [$page, $limit] = $this->getPage();
        $where['is_del'] = 0;
        $where['mer_id'] = $this->request->merId();
        return app('json')->success($this->repository->getList($where, $page, $limit));
    }

    /**
     * 详情
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function detail($id)
    {
        $data = $this->repository->get($id);
        if (!$data || $data['mer_id'] != $this->request->merId())
            return app('json')->fail('数据不存在');
        return app('json')->success($data);
    }

    /**
     * 添加
     * @return \think\response\Json
     * @author Qinii
     */
    public function create()
    {
        $data = $this->checkParams();
        $this->repository->create($data);
        return app('json')->success('添加成功');
    }

    /**
     * 编辑
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function update($id)
    {
        if (!$this->repository->merHas($this->request->merId(), $id))
            return app('json')->fail('数据不存在');
        $data = $this->checkParams();
        $data['update_time'] = date('Y-m-d H:i:s', time());
        $this->repository->update($id, $data);
        return app('json')->success('编辑成功');
    }

    /**
     * 删除
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function delete($id)
    {
        $mer_id = $this->request->merId();
        if (!$this->repository->merHas($mer_id, $id))
            return app('json')->fail('数据不存在');
        $this->repository->delete($id, $mer_id);
        return app('json')->success('删除成功');
    }

    /**
     *  字段验证
     * @return array
     * @author Qinii
     * @day 2023/10/7
     */
    public function checkParams()
    {
        $data = $this->request->params(['name', 'value', ['status', 1]]);
        $data['mer_id'] = $this->request->merId();
        if (!$data['value']) throw new ValidateException('请选择表单组件');
        $i = 0;
        foreach ($data['value'] as &$value) {
            if (!isset($value['key']) || !$value['key']) {
                $value['key'] = $value['name'] . '_' . time() . $i;
            }
            try {
                $keys[] = [
                    'key' => $value['key'],
                    'label' => $value['titleConfig']['value'],
                    'val' => $value['titleShow']['val'],
                    'type' => $value['name']
                ];
            } catch (Exception $exception) {
                throw new ValidateException('表单组件异常');
            }
            $i++;
        }
        $data['name'] = $data['name'] ?: '系统表单';
        $data['form_keys'] = json_encode($keys, JSON_UNESCAPED_UNICODE);
        $data['value'] = json_encode($data['value'], JSON_UNESCAPED_UNICODE);
        return $data;
    }

    /**
     *  筛选列表
     * @return \think\response\Json
     * @author Qinii
     * @day 2023/10/9
     */
    public function select()
    {
        $where = [
            'status' => 1,
            'mer_id' => $this->request->merId(),
            'is_del' => 0,
        ];
        $data = $this->repository->search($where)->field('form_id,name')->select();
        return app('json')->success($data);
    }

    /**
     * 表单的结构类型获取
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     * @day 2023/10/9
     */
    public function info($id)
    {
        $mer_id = $this->request->param('mer_id', 0);
        if (empty($mer_id)) {
            $mer_id = $this->request->merId();
        }
        $data = $this->repository->getSearch(['form_id' => $id, 'mer_id' => $mer_id])->value('form_keys');
        if (!$data)
            return app('json')->success([]);
        return app('json')->success(json_decode($data));
    }

    /**
     *  用户提交的表单记录信息
     * @param $id
     * @param StoreActivityRelatedRepository $storeActivityRelatedRepository
     * @return \think\response\Json
     * @author Qinii
     * @day 2023/10/9
     */
    public function formUserList($id, StoreActivityRelatedRepository $storeActivityRelatedRepository)
    {
        [$page, $limit] = $this->getPage();
        $where['create_time'] = $this->request->param('date', '');
        $where['link_id'] = $id;
        $where['activity_type'] = $storeActivityRelatedRepository::ACTIVITY_TYPE_FORM;
        $header = $this->repository->getFormKeys($id, $this->request->merId());
        $data = $storeActivityRelatedRepository->getList($where, $page, $limit);
        $data['header'] = json_decode($header['form']);
        return app('json')->success($data);
    }

    /**
     *  导出用户提交的信息
     * @param StoreActivityRelatedRepository $storeActivityRelatedRepository
     * @return \think\response\Json
     * @author Qinii
     * @day 2023/10/9
     */
    public function excel(StoreActivityRelatedRepository $storeActivityRelatedRepository)
    {
        [$page, $limit] = $this->getPage();
        $where['link_id'] = $this->request->param('id', 0);
        $where['create_time'] = $this->request->param('date', 0);
        if (!$where['link_id']) return app('json')->fail('请选择表单');
        $where['activity_type'] = $storeActivityRelatedRepository::ACTIVITY_TYPE_FORM;
        $data = $this->repository->excel($where, $page, $limit, $this->request->merId());
        return app('json')->success($data);
    }
}
