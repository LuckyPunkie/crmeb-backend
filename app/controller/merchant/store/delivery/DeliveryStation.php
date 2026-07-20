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

namespace app\controller\merchant\store\delivery;

use app\common\repositories\system\serve\ServeOrderRepository;
use crmeb\services\DeliverySevices;
use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\delivery\DeliveryStationRepository;
use app\validate\merchant\DeliveryStationValidate;
use think\exception\ValidateException;

class DeliveryStation extends BaseController
{
    protected $repository;

    /**
     * 构造函数
     * @param App $app 应用实例
     * @param DeliveryStationRepository $repository 配送站点仓库实例
     */
    public function __construct(App $app, DeliveryStationRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 获取配送站点列表
     * @return mixed
     */
    public function lst()
    {
        // 获取分页参数
        [$page, $limit] = $this->getPage();
        // 获取查询条件
        $where = $this->request->params(['station_name','contact_name','phone','station_address','status', 'swtich_type']);
        $where['mer_id'] = $this->request->merId();
        // 调用仓库方法获取数据
        $data = $this->repository->merList($where, $page, $limit);
        // 返回成功响应
        return app('json')->success($data);
    }

    /**
     * 获取配送站点类型列表
     * @return mixed
     */
    public function getTypeList()
    {
        // 获取分页参数
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['keyword', 'station_name']);
        $where['mer_id'] = $this->request->merId();
        $where['type'] = systemConfig('delivery_type');
        $where['status'] = 1;
        // 调用仓库方法获取数据
        $data = $this->repository->merList($where, $page, $limit);
        // 返回成功响应
        return app('json')->success($data);
    }

    /**
     * 获取配送站点详情
     * @param int $id 配送站点ID
     * @return mixed
     */
    public function detail($id)
    {
        $data = $this->repository->detail($id, $this->request->merId());
        $data['bind_type'] = 0;
        if($data['type'] == 1 && !$data['username'] && !$data['password']) {
            $data['bind_type'] = 1;
        };

        // 返回成功响应
        return app('json')->success($data);
    }

    /**
     * 创建配送站点
     * @return mixed
     */
    public function create()
    {
        // 校验参数
        $data = $this->checkParams();
        $data['mer_id'] = $this->request->merId();
        // 调用仓库方法保存数据
        $this->repository->save($data);
        return app('json')->success('添加成功');
    }

    /**
     * 更新配送站点信息
     * @param int $id 配送站点ID
     * @return \think\response\Json
     * @throws \app\common\exception\ValidateException
     */
    public function update($id)
    {
        $data = $this->checkParams();
        $this->repository->edit($id, $this->request->merId(), $data);
        return app('json')->success('编辑成功');
    }

    /**
     * 删除配送站点信息
     * @param int $id 配送站点ID
     * @return \think\response\Json
     * @throws \app\common\exception\ValidateException
     */
    public function delete($id)
    {
        $this->repository->destory($id, $this->request->merId());
        return app('json')->success('删除成功');
    }

    /**
     * 切换配送站点状态
     * @param int $id 配送站点ID
     * @return \think\response\Json
     * @throws \app\common\exception\ValidateException
     */
    public function switchWithStatus($id)
    {
        $status = $this->request->param('status') == 1 ? 1 : 0;
        $this->repository->update($id, ['status' => $status]);
        return app('json')->success('修改成功');
    }

    /**
     * 获取配送站点表单信息
     * @param int $id 配送站点ID
     * @return \think\response\Json
     * @throws \app\common\exception\ValidateException
     */
    public function markForm($id)
    {
        return app('json')->success(formToData($this->repository->markForm($id, $this->request->merId())));
    }

    /**
     * 添加或修改配送站点备注信息
     * @param int $id 配送站点ID
     * @return \think\response\Json
     * @throws \app\common\exception\ValidateException
     */
    public function mark($id)
    {
        $data = $this->request->params(['mark']);
        $this->repository->update($id, $data);
        return app('json')->success('备注成功');
    }

    /**
     * 检查参数是否合法
     *
     * @return array 返回检查后的参数数组
     */
    public function checkParams()
    {
        // 从请求中获取参数
        $data = $this->request->params([
            'station_name',
            'business',
            'station_address',
            'lng',
            'lat',
            'contact_name',
            'phone',
            'username',
            'password',
            ['status', 1],
            'city_name',
            'card_number',
            'business_date',
            'business_time_start',
            'business_time_end',
            ['switch_city', 0],
            ['switch_take', 0],
            ['bind_type',0],
            ['origin_shop_id',''],
            'range_type',
            'radius',
            'region',
            'fence',
            'type'
        ]);
        $merId = $this->request->merId();
        // 创建验证器实例
        $make = app()->make(DeliveryStationValidate::class);
        if(!$data['switch_city'] && !$data['switch_take']){
            throw new ValidateException('请选择配送方式');
        }
        if(!$data['lat'] || !$data['lng']){
            throw new ValidateException('经纬度不能为空');
        }
        if(count($data['business_date']) > 7) {
            throw new ValidateException('最多选择7天');
        }
        // 根据配送类型选择不同的验证场景
        $make->check($data);
        // 将经纬度转换为百度坐标系
        // [$data['lng'], $data['lat']] = gcj02ToBd09($data['lng'], $data['lat']);

        $data['region'] = $data['region'] ? json_encode($data['region']) : '';
        $data['fence'] = $data['fence'] ? json_encode($data['fence']) : '';
        $data['business_date'] = $data['business_date'] ? json_encode($data['business_date']) : [];
        // 返回检查后的参数数组
        return $data;
    }

    /**
     * 获取商家列表
     *
     * @return mixed 返回成功响应
     * @throws ValidateException 当同城配送未开启时抛出异常
     */
    public function getBusiness()
    {
        $type = $this->request->param('type');
        if(!$type) {
            throw new ValidateException('配送方式不能为空');
        }
        if(!in_array($type, [DeliverySevices::DELIVERY_TYPE_UU, DeliverySevices::DELIVERY_TYPE_DADA])) {
            throw new ValidateException('配送方式错误');
        }

        // 获取商家列表
        $data = $this->repository->getBusiness($type,$this->request->merId());
        // 返回成功响应
        return app('json')->success($data);
    }

    /**
     * 获取配送选项列表
     *
     * @return mixed 返回成功响应
     */
    public function options()
    {
        // 构造查询条件
        $where = [
            'status' => 1,
            'mer_id' => $this->request->merId(),
            'type' => systemConfig('delivery_type'),
        ];
        // 获取配送选项列表
        return app('json')->success($this->repository->getOptions($where));
    }


    /**
     * 选择商品
     *
     * @return \think\response\Json
     */
    public function select()
    {
        // 构建查询条件
        $where = [
            'mer_id' => $this->request->merId(),
        ];
        // 调用 repository 类的 getOptions 方法获取商品列表并返回 JSON 格式数据
        return app('json')->success($this->repository->getOptions($where));
    }

    /**
     * 获取城市列表
     *
     * @return \think\response\Json
     * @throws \app\common\exception\ValidateException
     */
    public function getCityLst()
    {
        $type = $this->request->param('type');
        if(!$type) {
            throw new ValidateException('配送方式不能为空');
        }
        if(!in_array($type, [DeliverySevices::DELIVERY_TYPE_UU, DeliverySevices::DELIVERY_TYPE_DADA])) {
            throw new ValidateException('配送方式错误');
        }

        // 调用 repository 类的 getCityLst 方法获取城市列表并返回 JSON 格式数据
        return app('json')->success($this->repository->getCityLst($type, $this->request->merId()));
    }


    /**
     * 充值记录
     * @author Qinii
     * @day 2/18/22
     */
    public function payLst()
    {

        // 获取分页参数
        [$page, $limit] = $this->getPage();
        $where = [
            'type' => 20,
            'mer_id' => $this->request->merId(),
            'date' => $this->request->param('date'),
        ];
        $data = app()->make(ServeOrderRepository::class)->getList($where, $page, $limit);
        $data['delivery_balance'] = $this->request->merchant()->delivery_balance;
        // 返回成功响应
        return app('json')->success($data);
    }

    /**
     * 获取二维码
     *
     * @return \Psr\Http\Message\ResponseInterface
     * @throws ValidateException 当同城配送未开启时抛出异常
     */
    public function getQrcode()
    {
        // 判断同城配送是否开启
        // 判断同城配送是否开启
        if (systemConfig('delivery_status') != 1) throw new ValidateException('未开启同城配送');
        // 获取支付类型和支付金额
        $data['pay_type'] = $this->request->param('pay_type', 1);
        $data['price'] = $this->request->param('price', 10);
        // 判断支付金额是否合法
        if (!is_numeric($data['price']) || $data['price'] <= 0)
            return app('json')->fail('支付金额不正确');
        // 调用 ServeOrderRepository 类的 QrCode 方法获取二维码
        $res = app()->make(ServeOrderRepository::class)->QrCode($this->request->merId(), 'delivery', $data);
        // 设置配送余额并返回结果
        $res['delivery_balance'] = $this->request->merchant()->delivery_balance;
        return app('json')->success($res);
    }


}
