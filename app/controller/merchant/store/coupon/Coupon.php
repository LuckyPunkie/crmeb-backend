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


namespace app\controller\merchant\store\coupon;


use app\common\repositories\store\coupon\StoreCouponSendRepository;
use app\validate\merchant\StoreCouponSendValidate;
use crmeb\basic\BaseController;
use app\common\repositories\store\coupon\StoreCouponRepository;
use app\common\repositories\store\coupon\StoreCouponUserRepository;
use app\common\repositories\user\UserRepository;
use app\validate\merchant\StoreCouponValidate;
use FormBuilder\Exception\FormBuilderException;
use think\App;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\exception\ValidateException;

/**
 * Class CouponIssue
 * @package app\controller\merchant\store\coupon
 * @author xaboy
 * @day 2020-05-13
 */
class Coupon extends BaseController
{
    /**
     * @var StoreCouponRepository
     */
    protected $repository;

    /**
     * CouponIssue constructor.
     * @param App $app
     * @param StoreCouponRepository $repository
     */
    public function __construct(App $app, StoreCouponRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 获取优惠券列表
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function lst()
    {
        $where = $this->request->params(['is_full_give', 'status', 'is_give_subscribe', 'coupon_name', 'send_type', 'type']);
        // 获取分页参数
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->repository->getList($this->request->merId(), $where, $page, $limit));
    }


    /**
     * 获取优惠券详情
     *
     * @param int $id 优惠券ID
     * @return \think\response\Json
     */
    public function detail($id)
    {
        // 判断优惠券是否存在
        if (!$this->repository->merExists($this->request->merId(), $id))
            return app('json')->fail('数据不存在');
        // 获取优惠券信息并添加已使用人数和发放人数
        $coupon = $this->repository->get($id)->append(['used_num', 'send_num']);
        // 返回优惠券信息
        return app('json')->success($coupon->toArray());
    }

    /**
     * 创建表单
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function createForm()
    {
        // 调用 formToData 方法将表单转换为数据并返回 JSON 格式的成功响应
        return app('json')->success(formToData($this->repository->form()));
    }

    /**
     * 创建优惠券验证
     *
     * @param StoreCouponValidate $validate 优惠券验证对象
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function create(StoreCouponValidate $validate)
    {
        // 获取商家ID
        $merId = $this->request->merId();
        // 检查参数并过滤
        $data = $this->checkParams($validate);
        // 添加商家ID到数据中
        $data['mer_id'] = $merId;
        // 调用仓库的创建方法
        $this->repository->create($data);
        // 返回成功信息
        return app('json')->success('发布成功');
    }

    /**
     * 检查参数并过滤无效数据
     * @param StoreCouponValidate $validate 优惠券验证器
     * @return array 处理后的参数数组
     * @throws ValidateException 参数校验失败时抛出异常
     */
    public function checkParams(StoreCouponValidate $validate)
    {
        $data = $this->request->params(['use_type', 'title', 'coupon_price', 'use_min_price', 'coupon_type', 'coupon_time', ['use_start_time', []], 'sort', ['status', 0], 'type', ['product_id', []], ['range_date', ''], ['send_type', 0], ['full_reduction', 0], ['is_limited', 0], ['is_timeout', 0], ['total_count', ''], ['status', 0]]);
        // 校验参数
        $validate->check($data);
        // 如果设置了领取时间限制
        if ($data['is_timeout']) {
            // 将有效期限转化为开始时间和结束时间
            [$data['start_time'], $data['end_time']] = $data['range_date'];
            // 如果结束时间小于当前时间，则抛出异常
            if (strtotime($data['end_time']) <= time())
                throw new ValidateException('优惠券领取结束时间不能小于当前');
        }
        // 如果没有设置使用类型，则将最低消费金额设置为0
        if (!$data['use_type']) $data['use_min_price'] = 0;
        unset($data['use_type']);
        if ($data['coupon_type']) {
            if (count(array_filter($data['use_start_time'])) != 2)
                throw new ValidateException('请选择有效期限');
            [$data['use_start_time'], $data['use_end_time']] = $data['use_start_time'];
        } else unset($data['use_start_time']);
        unset($data['range_date']);
        if ($data['is_limited'] == 0) $data['total_count'] = 0;
        if (!in_array($data['type'], [0, 1])) {
            throw new ValidateException('请选择有效的优惠券类型');
        }
        return $data;
    }

    /**
     * 修改状态
     *
     * @param int $id 商品ID
     * @return \think\response\Json
     */
    public function changeStatus($id)
    {
        // 获取请求参数中的状态值，默认为0
        $status = $this->request->param('status', 0) == 1 ? 1 : 0;
        // 判断商品是否存在
        if (!$this->repository->merExists($this->request->merId(), $id))
            return app('json')->fail('数据不存在');
        // 更新商品状态
        $this->repository->update($id, compact('status'));
        // 返回操作结果
        return app('json')->success('修改成功');
    }

    /**
     * 克隆表单
     *
     * @param int $id 表单ID
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function cloneForm($id)
    {
        // 判断表单是否存在
        if (!$this->repository->merExists($this->request->merId(), $id))
            return app('json')->fail('数据不存在');
        // 克隆表单并返回成功响应
        return app('json')->success(formToData($this->repository->cloneCouponForm($id)));
    }

    /**
     * 获取优惠券列表
     *
     * @param StoreCouponUserRepository $repository 优惠券用户仓库
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function issue(StoreCouponUserRepository $repository)
    {
        // 获取分页参数
        [$page, $limit] = $this->getPage();
        // 获取查询条件
        $where = $this->request->params(['username', 'coupon', 'status', 'coupon_id', 'type', 'send_id']);
        $where['mer_id'] = $this->request->merId();
        // 调用优惠券用户仓库的 getList 方法获取优惠券列表
        return app('json')->success($repository->getList($where, $page, $limit));
    }

    /**
     * 选择优惠券列表
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function select()
    {
        // 获取查询参数
        $where = $this->request->params(['coupon_name']);
        // 设置查询条件
        $where['status'] = 1;
        $where['send_type'] = 3;
        // 获取分页参数
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->repository->getList($this->request->merId(), $where, $page, $limit));
    }


    /**
     * 发送优惠券
     *
     * @param StoreCouponSendValidate $validate 优惠券发送验证器
     * @param StoreCouponSendRepository $repository 优惠券发送仓库
     * @return \think\response\Json
     */
    public function send(StoreCouponSendValidate $validate, StoreCouponSendRepository $repository)
    {
        // 获取请求参数
        $data = $this->request->params(['coupon_id', 'mark', 'is_all', 'search', 'uid']);
        // 验证请求参数
        $validate->check($data);
        // 如果不是全部用户并且没有选择用户，则返回错误信息
        if (!$data['is_all'] && !count($data['uid'])) {
            return app('json')->fail('请选择发送用户');
        }
        // 创建优惠券发送记录
        $repository->create($data, $this->request->merId());
        // 返回成功信息
        return app('json')->success('创建成功,正在发送中');
    }

    /**
     * 根据ID删除数据
     *
     * @param int $id 数据ID
     * @return \Illuminate\Http\JsonResponse 返回JSON格式的响应结果
     */
    public function delete($id)
    {
        // 判断数据是否存在
        if (!$this->repository->merExists($this->request->merId(), $id))
            return app('json')->fail('数据不存在');
        // 删除数据
        $this->repository->delete($id);
        // 返回删除成功的响应结果
        return app('json')->success('删除成功');
    }

    /**
     * 获取更新表单数据
     *
     * @param int $id 数据ID
     * @return \Illuminate\Http\JsonResponse 返回JSON格式的响应结果
     */
    public function updateForm($id)
    {
        // 获取更新表单数据并转换为数组形式
        return app('json')->success(formToData($this->repository->updateForm($this->request->merId(), $id)));
    }


    /**
     * 更新指定ID的商品信息
     *
     * @param int $id 商品ID
     * @return \think\response\Json 返回JSON格式的操作结果
     */
    public function update($id)
    {
        // 判断商品是否存在
        if (!$this->repository->merExists($this->request->merId(), $id))
            return app('json')->fail('数据不存在');
        // 获取需要更新的商品信息
        $data = $this->request->params(['title']);
        // 调用商品仓库的更新方法
        $this->repository->update($id, $data);

        // 返回操作成功的JSON格式结果
        return app('json')->success('修改成功');
    }


}
