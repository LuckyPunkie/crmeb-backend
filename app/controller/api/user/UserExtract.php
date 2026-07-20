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

namespace app\controller\api\user;

use crmeb\basic\BaseController;
use app\common\repositories\system\groupData\GroupDataRepository;
use think\App;
use app\validate\api\UserExtractValidate as validate;
use app\common\repositories\user\UserExtractRepository as repository;

class UserExtract extends BaseController
{
    /**
     * @var repository
     */
    public $repository;

    /**
     * UserExtract constructor.
     * @param App $app
     * @param repository $repository
     */
    public function __construct(App $app,repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 提现记录
     * @return \think\response\Json
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/10
     */
    public function lst()
    {
        [$page,$limit] = $this->getPage();
        $where = $this->request->params(['status']);
        [$start,$stop]= $this->request->params(['start','stop'],true);
        $where['date'] = $start&&$stop ? date('Y/m/d',$start).'-'.date('Y/m/d',$stop) : '';
        $where['uid'] = $this->request->uid();
        return app('json')->success($this->repository->getList($where,$page,$limit));
    }
    /**
     * 提现记录详情
     *
     * @param $id
     * @return void
     */
    public function detail($id)
    {
        if(!$id) return app('json')->fail('参数错误');

        $info = $this->repository->get($id);
        if(!$info) {
            return app('json')->fail('提现记录不存在');
        }

        return app('json')->success($info);
    }

    /**
     * 用户提现
     * @param validate $validate
     * @return \think\response\Json
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/10
     */
    public function create(validate $validate)
    {
        $data = $this->checkParams($validate);
        $user = $this->request->userInfo();
        $this->repository->create($user,$data);
        return app('json')->success($data['extract_type'] == $this->repository::EXTRACT_TYPE_YUE ? '已提现至余额' : '申请已提交');
    }

    /**
     * 验证数据
     * @param validate $validate
     * @return array|mixed|string|string[]
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/10
     */
    public function checkParams(validate $validate)
    {
        $data = $this->request->params(['extract_type','bank_code','bank_address','alipay_code','wechat','extract_pic','extract_price','real_name','bank_name']);
        $validate->check($data);
        return $data;
    }

    /**
     * 银行列表
     * @return \think\response\Json
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/10
     */
    public function bankLst()
    {
        [$page,$limit] = $this->getPage();
        $data = app()->make(GroupDataRepository::class)->groupData('bank_list',0,$page,100);
        return app('json')->success($data);
    }

    /**
     * 历史银行数据
     * @return \think\response\Json
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/10
     */
    public function historyBank()
    {
        $data = $this->repository->getHistoryBank($this->request->userInfo()->uid);
        return app('json')->success($data ?? []);
    }


}
