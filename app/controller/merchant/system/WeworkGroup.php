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
 * 商户后台 - 微信顾客群配置
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
        // 后台需读到关闭状态的配置，便于再次开启
        $row = $this->repository->getByMerBranch($merId, $branchId, false);

        return app('json')->success($this->repository->toApiPayload($row));
    }

    /**
     * 保存配置
     * POST /mer/wework/group
     */
    public function save(MerchantWeworkGroupValidate $validate)
    {
        $data = $this->request->params([
            'group_name',
            'group_last_msg',
            'qrcode_url',
            ['branch_id', 0],
            ['status', 0],
        ]);

        $validate->check($data);

        if ((int)($data['status'] ?? 0) === 1 && trim((string)($data['qrcode_url'] ?? '')) === '') {
            return app('json')->fail('开启微信群时请上传入群二维码');
        }

        $merId = (int)$this->request->merId();
        $branchId = (int)($data['branch_id'] ?? 0);
        unset($data['branch_id']);

        $this->repository->saveByMerBranch($merId, $branchId, $data);

        return app('json')->success('微信群配置保存成功');
    }
}
