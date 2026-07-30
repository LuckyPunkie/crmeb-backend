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

namespace app\controller\merchant\system;

use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\system\merchant\MerchantWeworkGroupRepository;
use app\validate\merchant\MerchantWeworkGroupValidate;

/**
 * 商户后台 - 企业微信顾客群配置
 */
class WeworkGroup extends BaseController
{
    protected $repository;

    public function __construct(App $app, MerchantWeworkGroupRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 获取配置（一期默认总店 branch_id=0）
     * GET /mer/wework/group
     */
    public function info()
    {
        $merId = (int)$this->request->merId();
        $branchId = (int)$this->request->param('branch_id', 0);
        $row = $this->repository->getByMerBranch($merId, $branchId);

        return app('json')->success($this->repository->toApiPayload($row));
    }

    /**
     * 保存配置
     * POST /mer/wework/group
     */
    public function save(MerchantWeworkGroupValidate $validate)
    {
        $data = $this->request->params([
            'corp_id',
            'group_name',
            'group_num',
            'group_last_msg',
            'qrcode_url',
            'group_link',
            ['branch_id', 0],
            ['status', 1],
        ]);

        $validate->check($data);

        $merId = (int)$this->request->merId();
        $branchId = (int)($data['branch_id'] ?? 0);
        unset($data['branch_id']);

        $this->repository->saveByMerBranch($merId, $branchId, $data);

        return app('json')->success('企业微信群配置保存成功');
    }
}
