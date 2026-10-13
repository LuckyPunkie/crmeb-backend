<?php

namespace app\common\repositories\system;

use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\AddShortUrlResponseBody\data;
use app\common\repositories\BaseRepository;
use app\common\repositories\community\CommunityRepository;
use app\common\repositories\store\broadcast\BroadcastGoodsRepository;
use app\common\repositories\store\broadcast\BroadcastRoomRepository;
use app\common\repositories\system\notice\SystemNoticeLogRepository;
use app\common\repositories\store\order\{StoreOrderProductRepository,
    StoreOrderReceiptRepository,
    StoreOrderRepository,
    StoreRefundOrderRepository
};
use app\common\repositories\store\product\{ProductAssistRepository,
    ProductGroupRepository,
    ProductPresellRepository,
    ProductReplyRepository,
    ProductRepository
};
use app\common\repositories\system\financial\FinancialRepository;
use app\common\repositories\system\merchant\{
    MerchantIntentionRepository,
    MerchantRepository
};
use app\common\repositories\user\{
    FeedbackRepository,
    UserExtractRepository
};
use think\facade\Cache;
use think\facade\Config;

class CountRepository extends BaseRepository
{

    const CACHE_KEY = 'sys_count';
    protected $is_cache = true;

    protected $cache_method = [
        'getMerchantSalesTop',
        'getMerchantSalesPriceTop',
        'getMerchantProductSalesPriceTop'
    ];

    public $admin_prefix = '';
    public $merchant_prefix = '';

    public function __construct()
    {
        $this->admin_prefix = Config::get('admin.admin_prefix');
        $this->merchant_prefix = Config::get('admin.merchant_prefix');
    }
    /**
     * @param $type
     * @param $callback
     * @return mixed
     * @throws \Exception
     *
     * @date 2023/10/21
     * @author yyw
     */
    protected function cache($type, $callback)
    {
        $method = explode('_', $type)[0];
        if (!$this->is_cache || !in_array($method, $this->cache_method) || env('APP_DEBUG', false)) {
            return $callback();
        }
        $res = Cache::get(self::CACHE_KEY . '_' . $type);
        if ($res) {
            return json_decode($res, true);
        } else {
            $res = $callback();
            Cache::set(self::CACHE_KEY . '_' . $type, json_encode($res), $res['ttl'] ??  300);
            return $res;
        }
    }

    /**
     * 删除为空数组项
     * @param array $data
     * @return array
     *
     * @date 2023/10/25
     * @author yyw
     */
    public function deleteArrayEmpty(array $data): array
    {
        foreach ($data as $key => $item) {
            if (empty($item)) {
                unset($data[$key]);
            }
        }
        return array_values($data);
    }

    /**
     * 获取平台首页统计
     * @return array
     *
     * @date 2023/10/21
     * @author yyw
     */
    public function getAdminCount()
    {
        return $this->deleteArrayEmpty(array_merge(
            $this->getAuditProductCount(),
//            $this->getAuditActiveProductCount(),
            $this->getAuditDistributionInfo(null),
            $this->getAuditMerchantCount(),
            $this->getAuditExtractCount(),
            $this->getAuditFinancialCount(),
            $this->getAuditCommunityCount(),
            $this->getAuditRefundOrderCount(),
            $this->getAuditFeedbackCount(),
            $this->getIntegralOrderShipInfo()
        ));
    }

    /**
     * 获取平台代办
     * @return array
     *
     * @date 2023/10/25
     * @author yyw
     */
    public function getAdminTodo()
    {
        return $this->deleteArrayEmpty(array_merge(
            $this->getAuditProductCount('todo'),
            $this->getAuditDistributionInfo(null, 'todo'),
            $this->getAuditExtractCount('todo'),
            $this->getAuditFinancialCount('todo'),
            $this->getAuditMerchantCount('todo'),
            $this->getAuditCommunityCount('todo'),
            $this->getAuditFeedbackCount('todo'),
//            $this->getAuditActiveProductCount('todo'),
            $this->getAuditLiveRoomCount('todo'),
            $this->getAuditLiveProdouctCount('todo'),
            $this->getIntegralOrderShipInfo('todo')
        ));
    }


    /**
     * 获取去待审核的普通商品数量
     * @return mixed
     *
     * @date 2023/10/21
     * @author yyw
     */
    public function getAuditProductCount(string $type = 'count')
    {
        return $this->cache(__FUNCTION__, function () use ($type) {
            $productRepository = app()->make(ProductRepository::class);
            $normal_count = $productRepository->search(null, $productRepository->switchType(6, null, 0))->count();
            $seckill_count = $productRepository->search(null, $productRepository->switchType(6, null, 1))->count();
            $group_count = app()->make(ProductGroupRepository::class)->search(['product_status' => 0])->count();
            $presell_count = app()->make(ProductPresellRepository::class)->search(['product_status' => 0])->count();
            $assist_count = app()->make(ProductAssistRepository::class)->search(['product_status' => 0])->count();
            $normal_path = '/'.$this->admin_prefix.'/product/examine';
            $seckill_path = '/'.$this->admin_prefix.'/marketing/seckill/list';
            $group_path = '/'.$this->admin_prefix.'/marketing/combination/combination_goods?product_status=0';
            $presell_path = '/'.$this->admin_prefix.'/marketing/presell/list?product_status=0';
            $assist_path = '/'.$this->admin_prefix.'/marketing/assist/goods_list?product_status=0';
            $data = [];
            switch ($type) {
                case 'count':
                    $children = [];
                    if ($normal_count > 0) {
                        $children[] = [
                            'title' => '普通商品',
                            'path' => $normal_path,
                            'count' => $normal_count,
                            'message' => '普通商品(' . $normal_count . ')',
                        ];
                    }
                    if ($seckill_count > 0) {
                        $children[] = [
                            'title' => '秒杀商品',
                            'path' => $seckill_path,
                            'count' => $seckill_count,
                            'message' => '秒杀商品(' . $seckill_count . ')',
                        ];
                    }
                    if ($group_count > 0) {
                        $children[] = [
                            'title' => '拼团商品',
                            'path' => $group_path,
                            'count' => $group_count,
                            'message' => '拼团商品(' . $group_count . ')',
                        ];
                    }
                    if ($presell_count > 0) {
                        $children[] = [
                            'title' => '预售商品',
                            'path' => $presell_path,
                            'count' => $presell_count,
                            'message' => '预售商品(' . $presell_count . ')',
                        ];
                    }
                    if ($assist_count > 0) {
                        $children[] = [
                            'title' => '助力商品',
                            'path' => $assist_path,
                            'count' => $assist_count,
                            'message' => '助力商品(' . $assist_count . ')',
                        ];
                    }
                    $count = 0;
                    foreach ($children as $child) {
                        $count += $child['count'];
                    }
                    $data[] = [
                        'title' => '待审核商品',
                        'icon' => 'iconputongshangpin',
                        'path' => '/',
                        'count' => $count,
                        'children' => $children
                    ];
                    break;
                case 'todo':
                    if ($normal_count > 0) {
                        $data[] = [
                            'title' => '待审核普通商品提醒',
                            'path' => $normal_path,
                            'message' => '您有' . $normal_count . '个商品待审核'
                        ];
                    }
                    if ($seckill_count > 0) {
                        $data[] = [
                            'title' => '待审核秒杀商品提醒',
                            'path' => $seckill_path,
                            'message' => '您有' . $seckill_count . '个秒杀商品待审核'
                        ];
                    }
                    if ($group_count > 0) {
                        $data[] = [
                            'title' => '待审核拼团商品提醒',
                            'path' => $group_path,
                            'message' => '您有' . $group_count . '个拼团商品待审核'
                        ];
                    }
                    if ($assist_count > 0) {
                        $data[] = [
                            'title' => '待审核助力商品提醒',
                            'path' => $assist_path,
                            'message' => '您有' . $assist_count . '个助力商品待审核'
                        ];
                    }
                    if ($presell_count > 0) {
                        $data[] = [
                            'title' => '待审核预售商品提醒',
                            'path' => $presell_path,
                            'message' => '您有' . $presell_count . '个预售商品待审核'
                        ];
                    }
                    break;
            }
            return $data;
        });
    }

    /**
     * 获取活动商品待审核数量
     * @return array
     *
     * @date 2023/10/21
     * @author yyw
     */
    public function getAuditActiveProductCount(string $type = 'count')
    {
        return $this->cache(__FUNCTION__, function () use ($type) {
            $productRepository = app()->make(ProductRepository::class);
            $seckill_count = $productRepository->search(null, $productRepository->switchType(6, null, 1))->count();
            $group_count = app()->make(ProductGroupRepository::class)->search(['product_status' => 0])->count();
            $presell_count = app()->make(ProductPresellRepository::class)->search(['product_status' => 0])->count();
            $assist_count = app()->make(ProductAssistRepository::class)->search(['product_status' => 0])->count();

            $seckill_path = '/'.$this->admin_prefix.'/marketing/seckill/list';
            $group_path = '/'.$this->admin_prefix.'/marketing/combination/combination_goods?product_status=0';
            $presell_path = '/'.$this->admin_prefix.'/marketing/presell/list?product_status=0';
            $assist_path = '/'.$this->admin_prefix.'/marketing/assist/goods_list?product_status=0';
            $data = [];
            switch ($type) {
                case 'count':
                    $children = [];
                    if ($seckill_count > 0) {
                        $children[] = [
                            'title' => '秒杀',
                            'path' => $seckill_path,
                            'count' => $seckill_count,
                            'message' => '秒杀(' . $seckill_count . ')',
                        ];
                    }
                    if ($group_count > 0) {
                        $children[] = [
                            'title' => '拼团',
                            'path' => $group_path,
                            'count' => $group_count,
                            'message' => '拼团(' . $group_count . ')',
                        ];
                    }
                    if ($presell_count > 0) {
                        $children[] = [
                            'title' => '预售',
                            'path' => $presell_path,
                            'count' => $presell_count,
                            'message' => '预售(' . $presell_count . ')',
                        ];
                    }
                    if ($assist_count > 0) {
                        $children[] = [
                            'title' => '助力',
                            'path' => $assist_path,
                            'count' => $assist_count,
                            'message' => '助力(' . $assist_count . ')',
                        ];
                    }
                    $count = 0;
                    foreach ($children as $child) {
                        $count += $child['count'];
                    }
                    $data[] = [
                        'title' => '待审核活动商品',
                        'icon' => 'iconhuodongshangpin',
                        'path' => '/',
                        'count' => $count,
                        'children' => $children
                    ];
                    break;
                case 'todo':
                    if ($seckill_count > 0) {
                        $data[] = [
                            'title' => '待审核秒杀商品提醒',
                            'path' => $seckill_path,
                            'message' => '您有' . $seckill_count . '个秒杀商品待审核'
                        ];
                    }
                    if ($group_count > 0) {
                        $data[] = [
                            'title' => '待审核拼团商品提醒',
                            'path' => $group_path,
                            'message' => '您有' . $group_count . '个拼团商品待审核'
                        ];
                    }
                    if ($assist_count > 0) {
                        $data[] = [
                            'title' => '待审核助力商品提醒',
                            'path' => $assist_path,
                            'message' => '您有' . $assist_count . '个助力商品待审核'
                        ];
                    }
                    if ($presell_count > 0) {
                        $data[] = [
                            'title' => '待审核预售商品提醒',
                            'path' => $presell_path,
                            'message' => '您有' . $presell_count . '个预售商品待审核'
                        ];
                    }
                    break;
            }
            return $data;
        });

    }

    /**
     * 获取分销商品待审核数量
     * @param int|null $mer_id
     * @param string $type
     * @return mixed
     * @throws \Exception
     *
     * @date 2023/10/25
     * @author yyw
     */
    public function getAuditDistributionInfo(?int $mer_id, string $type = 'count')
    {
        return $this->cache(__FUNCTION__, function () use ($mer_id, $type) {
            $productRepository = app()->make(ProductRepository::class);
            $count = $productRepository->search($mer_id, $productRepository->switchType(6, null, 10))->count();
            $path = '/'.$this->admin_prefix.'/promoter/gift?type=6';
            if ($mer_id) {
                $path = '/'.$this->merchant_prefix.'/product/list?type=6&is_gift_bag=1';
            }
            $data = [];

            switch ($type) {
                case 'count':
                    $data[] = [
                        'title' => empty($mer_id) ? '待审核分销礼包' : '审核未通过分销礼包',
                        'icon' => 'icondaihexiao-fenxiaolibao',
                        'path' => $path,
                        'count' => $count,
                        'children' => []
                    ];
                    break;
                case 'todo':
                    if ($count > 0) {
                        $data[] = [
                            'title' => empty($mer_id) ? '待审核分销礼包提醒' : '分销礼包审核未通过提醒',
                            'path' => $path,
                            'message' => '您有' . $count . (empty($mer_id) ? '个分销礼包待审核' : '个分销礼包审核未通过')
                        ];
                    }
                    break;
            }
            return $data;
        });
    }

    /**
     * 获取商户审核
     * @return array
     *
     * @date 2023/10/21
     * @author yyw
     */
    public function getAuditMerchantCount(string $type = 'count')
    {
        return $this->cache(__FUNCTION__, function () use ($type) {
            $merchantIntentionRepository = app()->make(MerchantIntentionRepository::class);
            $count = $merchantIntentionRepository->search(['status' => 0])->count();
            $path = '/'.$this->admin_prefix.'/'.$this->merchant_prefix.'/application';
            $data = [];

            switch ($type) {
                case 'count':
                    $data[] = [
                        'title' => '待审核商户入驻',
                        'icon' => 'icondaishenhe-shanghuruzhu',
                        'path' => $path,
                        'count' => $count,
                        'children' => []
                    ];
                    break;
                case 'todo':
                    if ($count > 0) {
                        $data[] = [
                            'title' => '待审核商户入驻提醒',
                            'path' => $path,
                            'message' => '您有' . $count . '个商户入驻待审核'
                        ];
                    }
                    break;
            }
            return $data;
        });
    }

    /**
     * 获取提现待审核
     * @return array
     *
     * @date 2023/10/21
     * @author yyw
     */
    public function getAuditExtractCount(string $type = 'count')
    {
        return $this->cache(__FUNCTION__, function () use ($type) {
            $userExtractRepository = app()->make(UserExtractRepository::class);
            $count = $userExtractRepository->search(['status' => 0])->count();
            $path = '/'.$this->admin_prefix.'/accounts/extract';
            $data = [];

            switch ($type) {
                case 'count':
                    $data[] = [
                        'title' => '待审核提现',
                        'icon' => 'icondaishenhe-tixian',
                        'path' => $path,
                        'count' => $count,
                        'children' => []
                    ];
                    break;
                case 'todo':
                    if ($count > 0) {
                        $data[] = [
                            'title' => '待审核提现提醒',
                            'path' => $path,
                            'message' => '您有' . $count . '个提现待审核'
                        ];
                    }
                    break;
            }
            return $data;
        });
    }

    /**
     * 获取转账待审核
     * @return array
     *
     * @date 2023/10/21
     * @author yyw
     */
    public function getAuditFinancialCount(string $type = 'count')
    {
        return $this->cache(__FUNCTION__, function () use ($type) {
            $financialRepository = app()->make(FinancialRepository::class);
            $count = $financialRepository->search(['status' => 0])->count();
            $path = '/'.$this->admin_prefix.'/accounts/transferRecord';
            $data = [];

            switch ($type) {
                case 'count':
                    $data[] = [
                        'title' => '待审核转账',
                        'icon' => 'icondaishenhe-zhuanzhang',
                        'path' => $path,
                        'count' => $count,
                        'children' => []
                    ];
                    break;
                case 'todo':
                    if ($count > 0) {
                        $data[] = [
                            'title' => '待审核转账提醒',
                            'path' => $path,
                            'message' => '您有' . $count . '个转账待审核'
                        ];
                    }

                    break;
            }
            return $data;
        });
    }

    /**
     * 待审核社区内容
     * @return array
     *
     * @date 2023/10/21
     * @author yyw
     */
    public function getAuditCommunityCount(string $type = 'count')
    {
        return $this->cache(__FUNCTION__, function () use ($type) {
            $communityRepository = app()->make(CommunityRepository::class);
            $count = $communityRepository->search(['status' => 0,'is_del' => 0])->count();
            $path = '/'.$this->admin_prefix.'/community/list';
            $data = [];

            switch ($type) {
                case 'count':
                    $data[] = [
                        'title' => '待审核社区内容',
                        'icon' => 'icondaishenhe-shequneirong',
                        'path' => $path,
                        'count' => $count,
                        'children' => []
                    ];
                    break;
                case 'todo':
                    if ($count > 0) {
                        $data[] = [
                            'title' => '待审核社区内容提醒',
                            'path' => $path,
                            'message' => '您有' . $count . '个社区内容待审核'
                        ];
                    }
                    break;
            }
            return $data;
        });
    }

    /**
     * 待退款订单
     * @return array
     *
     * @date 2023/10/21
     * @author yyw
     */
    public function getAuditRefundOrderCount(string $type = 'count')
    {
        return $this->cache(__FUNCTION__, function () use ($type) {
            $storeRefundOrderRepository = app()->make(StoreRefundOrderRepository::class);
            $count = $storeRefundOrderRepository->search(['status' => 0])->count();
            $path = '/'.$this->admin_prefix.'/order/refund';
            $data = [];

            switch ($type) {
                case 'count':
                    $data[] = [
                        'title' => '待退款订单',
                        'icon' => 'icondaituikuan',
                        'path' => $path,
                        'count' => $count,
                        'children' => []
                    ];
                    break;
                case 'todo':
                    if ($count > 0) {
                        $data[] = [
                            'title' => '待退款订单提醒',
                            'path' => $path,
                            'message' => '您有' . $count . '个待退款订单待审核'
                        ];
                    }
                    break;
            }
            return $data;
        });
    }

    /**
     * 待处理用户反馈
     * @return array
     *
     * @date 2023/10/21
     * @author yyw
     */
    public function getAuditFeedbackCount(string $type = 'count')
    {
        return $this->cache(__FUNCTION__, function () use ($type) {
            $feedbackRepository = app()->make(FeedbackRepository::class);
            $count = $feedbackRepository->search(['status' => 0])->count();
            $path = '/'.$this->admin_prefix.'/feedback/list';
            $data = [];

            switch ($type) {
                case 'count':
                    $data[] = [
                        'title' => '待处理用户反馈',
                        'icon' => 'icondaichuli-yonghufankui',
                        'path' => $path,
                        'count' => $count,
                        'children' => []
                    ];
                    break;
                case 'todo':
                    if ($count > 0) {
                        $data[] = [
                            'title' => '待处理用户反馈提醒',
                            'path' => $path,
                            'message' => '您有' . $count . '个用户反馈待处理'
                        ];
                    }
                    break;
            }
            return $data;
        });
    }

    /**
     * 待处理直播间审核
     * @return array
     *
     * @date 2023/10/21
     * @author yyw
     */
    public function getAuditLiveRoomCount(string $type = 'count')
    {
        return $this->cache(__FUNCTION__, function () use ($type) {
            $count = app()->make(BroadcastRoomRepository::class)->search(['status_tag' => 0])->count();
            $path = '/'.$this->admin_prefix.'/marketing/studio/list';
            $data = [];

            switch ($type) {
                case 'count':
                    $data[] = [
                        'title' => '待审核直播间',
                        'icon' => '',
                        'path' => $path,
                        'count' => $count,
                        'children' => []
                    ];
                    break;
                case 'todo':
                    if ($count > 0) {
                        $data[] = [
                            'title' => '待审核直播间提醒',
                            'path' => $path,
                            'message' => '您有' . $count . '个直播间待审核'
                        ];
                    }
                    break;
            }
            return $data;
        });
    }

    /**
     * 待处理直播商品审核
     * @return array
     *
     * @date 2023/10/21
     * @author yyw
     */
    public function getAuditLiveProdouctCount(string $type = 'count')
    {
        return $this->cache(__FUNCTION__, function () use ($type) {
            $count = app()->make(BroadcastGoodsRepository::class)->search(['status_tag' => 0])->count();
            $path = '/'.$this->admin_prefix.'/marketing/broadcast/list';
            $data = [];

            switch ($type) {
                case 'count':
                    $data[] = [
                        'title' => '待审核直播商品',
                        'icon' => '',
                        'path' => $path,
                        'count' => $count,
                        'children' => []
                    ];
                    break;
                case 'todo':
                    if ($count > 0) {
                        $data[] = [
                            'title' => '待审核直播商品提醒',
                            'path' => $path,
                            'message' => '您有' . $count . '个直播商品待审核'
                        ];
                    }
                    break;
            }
            return $data;
        });
    }

    /**
     * 待发货积分订单订单
     * @param string $type
     * @return mixed
     * @throws \Exception
     *
     * @date 2023/11/02
     * @author yyw
     */
    public function getIntegralOrderShipInfo(string $type = 'count')
    {
        return $this->cache(__FUNCTION__, function () use ($type) {
            $storeOrderRepository = app()->make(StoreOrderRepository::class);
            $stay_delivery_count = $storeOrderRepository->searchAll(['status' => 0, 'activity_type' => 20], 0, 1)->count();

            $stay_delivery_path = '/'.$this->admin_prefix.'/marketing/integral/orderList?status=0';
            $data = [];

            switch ($type) {
                case 'count':
                    $data[] = [
                        'title' => '待发货积分订单',
                        'icon' => 'icondaifahuo-jifen',
                        'path' => $stay_delivery_path,
                        'count' => $stay_delivery_count,
                        'children' => []
                    ];
                    break;
                case 'todo':
                    if ($stay_delivery_count) {
                        $data[] = [
                            'title' => '待发货积分订单提醒',
                            'path' => $stay_delivery_path,
                            'message' => '您有' . $stay_delivery_count . '个待发货的积分订单'
                        ];
                    }
                    break;
            }
            return $data;
        });
    }

    /**
     * 获取商户排行榜
     * @param string $date
     * @param string $type
     * @param string $sort
     * @return mixed
     * @throws \Exception
     */
    public function getMerchantTop(string $date, string $type = 'sales', string $sort = 'desc')
    {
        return $this->cache(__FUNCTION__, function () use ($date, $type, $sort) {
            $storeOrderRepository = app()->make(StoreOrderRepository::class);
            $list = $storeOrderRepository->getMerchantTop($date, $type, $sort);
            foreach ($list as &$item) {
                $item['mer_name'] = $item['merchant']['mer_name'] ?? '未知商户';
                $item['mer_avatar'] = $item['merchant']['mer_avatar'] ?? rtrim(systemConfig('site_url'), '/') . '/static/images/mer_logo.png';
                $item['care_count'] = $item['merchant']['care_count'] ?? 0;
                unset($item['merchant']);
            }
            return compact('list');
        });
    }

    /**
     * 获取商户首页统计
     * @param int $mer_id
     * @return array
     * @throws \Exception
     */
    public function getMerchantCount(int $mer_id)
    {
        return $this->deleteArrayEmpty(array_merge(
            $this->getMerchantOrderShipInfo($mer_id),
            $this->getMerchantRefuseProductInfo($mer_id),
//            $this->getMerchantActivityProductInfo($mer_id),
            $this->getMerchantPendWriteOffOrderInfo($mer_id),
            $this->getMerchantAlertProductInfo($mer_id),
            $this->getMerchantSellOutProductInfo($mer_id),
            $this->getMerchantRefundOrderInfo($mer_id),
            $this->getMerchantOrderReceiptInfo($mer_id),
            $this->getMerchantSellProductInfo($mer_id),
            $this->getMerchantStayReviewsInfo($mer_id)
//            $this->getAuditDistributionInfo($mer_id)
        ));
    }

    /**
     * 获取商户小铃铛代办
     * @param int $mer_id
     * @return array
     * @throws \Exception
     */
    public function getMerchantTodo(int $mer_id)
    {
        return $this->deleteArrayEmpty(array_merge(
            $this->getMerchantOrderShipInfo($mer_id, 'todo'),
            $this->getMerchantRefuseProductInfo($mer_id, 'todo'),
//            $this->getMerchantActivityProductInfo($mer_id, 'todo'),
            $this->getMerchantPendWriteOffOrderInfo($mer_id, 'todo'),
            $this->getMerchantAlertProductInfo($mer_id, 'todo'),
            $this->getMerchantSellOutProductInfo($mer_id, 'todo'),
            $this->getMerchantRefundOrderInfo($mer_id, 'todo'),
            $this->getMerchantOrderReceiptInfo($mer_id, 'todo'),
            $this->getMerchantStayReviewsInfo($mer_id, 'todo')
//            $this->getAuditDistributionInfo($mer_id, 'todo')
        ));
    }

    /**
     * 出售商品
     * @param int $mer_id
     * @param string $type
     * @return mixed
     * @throws \Exception
     *
     * @date 2023/11/02
     * @author yyw
     */
    public function getMerchantSellProductInfo(int $mer_id, string $type = 'count')
    {
        return $this->cache(__FUNCTION__, function () use ($mer_id, $type) {
            $productRepository = app()->make(ProductRepository::class);
            $data = [];
            $productType = 0;

            $sell_count = $productRepository->search($mer_id, $productRepository->switchType(1, $mer_id, $productType))->count();
            $sell_path = '/'.$this->merchant_prefix.'/product/list?type=1';

            switch ($type) {
                case 'count':
                    $data[] = [
                        'title' => '出售商品',
                        'icon' => 'iconzaishoushangpin',
                        'path' => $sell_path,
                        'count' => $sell_count,
                        'children' => []
                    ];
                    break;
                case 'todo':
                    break;
            }

            return $data;
        });
    }

    /**
     * 售罄商品
     * @param int $mer_id
     * @param string $type
     * @return mixed
     * @throws \Exception
     *
     * @date 2023/11/02
     * @author yyw
     */
    public function getMerchantSellOutProductInfo(int $mer_id, string $type = 'count')
    {
        return $this->cache(__FUNCTION__, function () use ($mer_id, $type) {
            $productRepository = app()->make(ProductRepository::class);
            $data = [];
            $productType = 0;

            $sell_out_count = $productRepository->search($mer_id, $productRepository->switchType(3, $mer_id, $productType))->count();
            $sell_out_path = '/'.$this->merchant_prefix.'/product/list?type=3';

            switch ($type) {
                case 'count':
                    $data[] = [
                        'title' => '售罄商品',
                        'icon' => 'iconshouqingshangpin',
                        'path' => $sell_out_path,
                        'count' => $sell_out_count,
                        'children' => []
                    ];
                    break;
                case 'todo':
                    if ($sell_out_count > 0) {
                        $data[] = [
                            'title' => '已售罄商品提醒',
                            'path' => $sell_out_path,
                            'message' => '您有' . $sell_out_count . '个商品已售罄'
                        ];
                    }
                    break;
            }

            return $data;
        });
    }

    /**
     * 警戒库存商品
     * @param int $mer_id
     * @param string $type
     * @return mixed
     * @throws \Exception
     *
     * @date 2023/11/02
     * @author yyw
     */
    public function getMerchantAlertProductInfo(int $mer_id, string $type = 'count')
    {
        return $this->cache(__FUNCTION__, function () use ($mer_id, $type) {
            $productRepository = app()->make(ProductRepository::class);
            $data = [];
            $productType = 0;

            $alert_count = $productRepository->search($mer_id, $productRepository->switchType(4, $mer_id, $productType))->count();
            $alert_path = '/'.$this->merchant_prefix.'/product/list?type=4';

            switch ($type) {
                case 'count':
                    $data[] = [
                        'title' => '警戒库存商品',
                        'icon' => 'iconjingjiekucun',
                        'path' => $alert_path,
                        'count' => $alert_count,
                        'children' => []
                    ];
                    break;
                case 'todo':
                    if ($alert_count) {
                        $data[] = [
                            'title' => '警戒库存商品提醒',
                            'path' => $alert_path,
                            'message' => '您有' . $alert_count . '个商品已售罄'
                        ];
                    }
                    break;
            }
            return $data;
        });
    }

    /**
     * 审核未通过普通商品
     * @param int $mer_id
     * @param string $type
     * @return array
     *
     * @date 2023/10/25
     * @author yyw
     */
    public function getMerchantRefuseProductInfo(int $mer_id, string $type = 'count')
    {
        return $this->cache(__FUNCTION__, function () use ($mer_id, $type) {
            $productRepository = app()->make(ProductRepository::class);
            $data = [];

            // 普通商品
            $refuse_count = $productRepository->search($mer_id, $productRepository->switchType(7, $mer_id, 0))->count();
            // 审核未通过活动商品
            $seckill_count = $productRepository->search($mer_id, $productRepository->switchType(7, null, 1))->count();
            $group_count = app()->make(ProductGroupRepository::class)->search(['mer_id' => $mer_id, 'product_status' => -1])->count();
            $presell_count = app()->make(ProductPresellRepository::class)->search(['mer_id' => $mer_id, 'product_status' => -1])->count();
            $assist_count = app()->make(ProductAssistRepository::class)->search(['mer_id' => $mer_id, 'product_status' => -1])->count();
            $live_room_count = app()->make(BroadcastRoomRepository::class)->search(['mer_id' => $mer_id, 'status_tag' => -1])->count();
            $live_goods_count = app()->make(BroadcastGoodsRepository::class)->search(['mer_id' => $mer_id, 'status_tag' => -1])->count();
            // 分销礼包
            $distribution_count = $productRepository->search($mer_id, $productRepository->switchType(6, null, 10))->count();

            $refuse_path = '/'.$this->merchant_prefix.'/product/list?type=7';

            $seckill_path = '/'.$this->merchant_prefix.'/marketing/seckill/product/list?type=-1';
            $group_path = '/'.$this->merchant_prefix.'/marketing/combination/combination_goods?product_status=-1';
            $presell_path = '/'.$this->merchant_prefix.'/marketing/presell/list?product_status=-1';
            $assist_path = '/'.$this->merchant_prefix.'/marketing/assist/list?product_status=-1';
            $live_room_path = '/'.$this->merchant_prefix.'/marketing/studio/list?status_tag=-1';
            $live_goods_path = '/'.$this->merchant_prefix.'/marketing/broadcast/list?status_tag=-1';

            $distribution_path = '/'.$this->merchant_prefix.'/product/list?type=6&is_gift_bag=1';
            $status_path = '/'.$this->merchant_prefix.'/product/list?type=7';

            switch ($type) {
                case 'count':
                    $children = [];
                    if ($refuse_count > 0) {
                        $children[] = [
                            'title' => '普通商品',
                            'path' => $refuse_path,
                            'count' => $refuse_count,
                            'message' => '普通商品(' . $refuse_count . ')',
                        ];
                    }
                    if ($seckill_count > 0) {
                        $children[] = [
                            'title' => '秒杀',
                            'path' => $seckill_path,
                            'count' => $seckill_count,
                            'message' => '秒杀(' . $seckill_count . ')',
                        ];
                    }
                    if ($group_count > 0) {
                        $children[] = [
                            'title' => '拼团',
                            'path' => $group_path,
                            'count' => $group_count,
                            'message' => '拼团(' . $group_count . ')',
                        ];
                    }
                    if ($assist_count > 0) {
                        $children[] = [
                            'title' => '助力',
                            'path' => $assist_path,
                            'count' => $assist_count,
                            'message' => '助力(' . $assist_count . ')',
                        ];
                    }
                    if ($presell_count > 0) {
                        $children[] = [
                            'title' => '预售',
                            'path' => $presell_path,
                            'count' => $presell_count,
                            'message' => '预售(' . $presell_count . ')',
                        ];
                    }
                    if ($live_room_count > 0) {
                        $children[] = [
                            'title' => '直播间',
                            'path' => $live_room_path,
                            'count' => $live_room_count,
                            'message' => '直播间(' . $live_room_count . ')',
                        ];
                    }
                    if ($live_goods_count > 0) {
                        $children[] = [
                            'title' => '直播间商品',
                            'path' => $live_goods_path,
                            'count' => $live_goods_count,
                            'message' => '直播间商品(' . $live_goods_count . ')',
                        ];
                    }
                    if ($distribution_count > 0) {
                        $children[] = [
                            'title' => '分销礼包商品',
                            'path' => $distribution_path,
                            'count' => $distribution_count,
                            'message' => '分销礼包商品(' . $distribution_count . ')',
                        ];
                    }

                    $count = 0;
                    foreach ($children as $child) {
                        $count += $child['count'];
                    }

                    $data[] = [
                        'title' => '审核未通过商品',
                        'icon' => 'iconputongshangpin',
                        'path' => $status_path,
                        'count' => $count,
                        'children' => $children
                    ];
                    break;
                case 'todo':
                    if ($refuse_count > 0) {
                        $data[] = [
                            'title' => '普通商品审核未通过提醒',
                            'path' => $refuse_path,
                            'message' => '您有' . $refuse_count . '个普通商品审核未通过'
                        ];
                    }
                    if ($seckill_count > 0) {
                        $data[] = [
                            'title' => '秒杀商品审核未通过提醒',
                            'path' => $seckill_path,
                            'message' => '您有' . $seckill_count . '个秒杀商品审核未通过'
                        ];
                    }
                    if ($group_count > 0) {
                        $data[] = [
                            'title' => '拼团商品审核未通过提醒',
                            'path' => $group_path,
                            'message' => '您有' . $group_count . '个拼团商品审核未通过'
                        ];
                    }
                    if ($assist_count > 0) {
                        $data[] = [
                            'title' => '助力商品审核未通过提醒',
                            'path' => $assist_path,
                            'message' => '您有' . $assist_count . '个助力商品审核未通过'
                        ];
                    }
                    if ($presell_count > 0) {
                        $data[] = [
                            'title' => '预售商品审核未通过提醒',
                            'path' => $presell_path,
                            'message' => '您有' . $presell_count . '个预售商品审核未通过'
                        ];
                    }
                    if ($live_room_count > 0) {
                        $data[] = [
                            'title' => '直播商品审核未通过提醒',
                            'path' => $live_room_path,
                            'message' => '您有' . $live_room_count . '个直播商品审核未通过'
                        ];
                    }
                    if ($live_goods_count > 0) {
                        $data[] = [
                            'title' => '直播间审核未通过提醒',
                            'path' => $live_goods_path,
                            'message' => '您有' . $live_goods_count . '个直播间审核未通过'
                        ];
                    }
                    if ($distribution_count > 0) {
                        $data[] = [
                            'title' => '分销礼包审核未通过提醒',
                            'path' => $distribution_path,
                            'message' => '您有' . $distribution_count . '个分销礼包审核未通过'
                        ];
                    }

                    break;
            }
            return $data;
        });
    }

    public function getMerchantActivityProductInfo(int $mer_id, string $type = 'count')
    {
        return $this->cache(__FUNCTION__, function () use ($mer_id, $type) {
            $productRepository = app()->make(ProductRepository::class);
            $seckill_count = $productRepository->search($mer_id, $productRepository->switchType(7, null, 1))->count();
            $group_count = app()->make(ProductGroupRepository::class)->search(['mer_id' => $mer_id, 'product_status' => -1])->count();
            $presell_count = app()->make(ProductPresellRepository::class)->search(['mer_id' => $mer_id, 'product_status' => -1])->count();
            $assist_count = app()->make(ProductAssistRepository::class)->search(['mer_id' => $mer_id, 'product_status' => -1])->count();

            $live_room_count = app()->make(BroadcastRoomRepository::class)->search(['mer_id' => $mer_id, 'status_tag' => -1])->count();
            $live_goods_count = app()->make(BroadcastGoodsRepository::class)->search(['mer_id' => $mer_id, 'status_tag' => -1])->count();

            $seckill_path = '/'.$this->merchant_prefix.'/marketing/seckill/list?type=-1';
            $group_path = '/'.$this->merchant_prefix.'/marketing/combination/combination_goods?product_status=-1';
            $presell_path = '/'.$this->merchant_prefix.'/marketing/presell/list?product_status=-1';
            $assist_path = '/'.$this->merchant_prefix.'/marketing/assist/list?product_status=-1';
            $live_room_path = '/'.$this->merchant_prefix.'/marketing/studio/list?status_tag=-1';
            $live_goods_path = '/'.$this->merchant_prefix.'/marketing/broadcast/list?status_tag=-1';
            $data = [];
            switch ($type) {
                case 'count':
                    $children = [];
                    if ($seckill_count > 0) {
                        $children[] = [
                            'title' => '秒杀',
                            'path' => $seckill_path,
                            'count' => $seckill_count,
                            'message' => '秒杀(' . $seckill_count . ')',
                        ];
                    }
                    if ($group_count > 0) {
                        $children[] = [
                            'title' => '拼团',
                            'path' => $group_path,
                            'count' => $group_count,
                            'message' => '拼团(' . $group_count . ')',
                        ];
                    }
                    if ($assist_count > 0) {
                        $children[] = [
                            'title' => '助力',
                            'path' => $assist_path,
                            'count' => $assist_count,
                            'message' => '助力(' . $assist_count . ')',
                        ];
                    }
                    if ($presell_count > 0) {
                        $children[] = [
                            'title' => '预售',
                            'path' => $presell_path,
                            'count' => $presell_count,
                            'message' => '预售(' . $presell_count . ')',
                        ];
                    }
                    if ($live_room_count > 0) {
                        $children[] = [
                            'title' => '直播间',
                            'path' => $live_room_path,
                            'count' => $live_room_count,
                            'message' => '直播间(' . $live_room_count . ')',
                        ];
                    }
                    if ($live_goods_count > 0) {
                        $children[] = [
                            'title' => '直播间商品',
                            'path' => $live_goods_path,
                            'count' => $live_goods_count,
                            'message' => '直播间商品(' . $live_goods_count . ')',
                        ];
                    }
                    $count = 0;
                    foreach ($children as $child) {
                        $count += $child['count'];
                    }
                    $data[] = [
                        'title' => '审核未通过活动商品',
                        'icon' => 'iconhuodongshangpin',
                        'path' => '/',
                        'count' => $count,
                        'children' => $children
                    ];
                    break;
                case 'todo':
                    if ($seckill_count > 0) {
                        $data[] = [
                            'title' => '秒杀商品审核未通过提醒',
                            'path' => $seckill_path,
                            'message' => '您有' . $seckill_count . '个秒杀商品审核未通过'
                        ];
                    }
                    if ($group_count > 0) {
                        $data[] = [
                            'title' => '拼团商品审核未通过提醒',
                            'path' => $group_path,
                            'message' => '您有' . $group_count . '个拼团商品审核未通过'
                        ];
                    }
                    if ($assist_count > 0) {
                        $data[] = [
                            'title' => '助力商品审核未通过提醒',
                            'path' => $assist_path,
                            'message' => '您有' . $assist_count . '个助力商品审核未通过'
                        ];
                    }
                    if ($presell_count > 0) {
                        $data[] = [
                            'title' => '预售商品审核未通过提醒',
                            'path' => $presell_path,
                            'message' => '您有' . $presell_count . '个预售商品审核未通过'
                        ];
                    }
                    if ($live_room_count > 0) {
                        $data[] = [
                            'title' => '直播商品审核未通过提醒',
                            'path' => $live_room_path,
                            'message' => '您有' . $live_room_count . '个直播商品审核未通过'
                        ];
                    }
                    if ($live_goods_count > 0) {
                        $data[] = [
                            'title' => '直播间审核未通过提醒',
                            'path' => $live_goods_path,
                            'message' => '您有' . $live_goods_count . '个直播间审核未通过'
                        ];
                    }
                    break;
            }
            return $data;
        });
    }

    /**
     * 待发货订单
     * @param int $mer_id
     * @param string $type
     * @return mixed
     * @throws \Exception
     *
     * @date 2023/11/02
     * @author yyw
     */
    public function getMerchantOrderShipInfo(int $mer_id, string $type = 'count')
    {
        return $this->cache(__FUNCTION__, function () use ($mer_id, $type) {
            $storeOrderRepository = app()->make(StoreOrderRepository::class);
            $stay_delivery_count = $storeOrderRepository->search(['mer_id' => $mer_id])->where($storeOrderRepository->getOrderType(2))->count();

            $stay_delivery_path = '/'.$this->merchant_prefix.'/order/list?status=2';
            $data = [];

            switch ($type) {
                case 'count':
                    $data[] = [
                        'title' => '待发货订单',
                        'icon' => 'icondaifahuo2',
                        'path' => $stay_delivery_path,
                        'count' => $stay_delivery_count,
                        'children' => []
                    ];
                    break;
                case 'todo':
                    if ($stay_delivery_count) {
                        $data[] = [
                            'title' => '待发货订单提醒',
                            'path' => $stay_delivery_path,
                            'message' => '您有' . $stay_delivery_count . '个待发货的订单'
                        ];
                    }
                    break;
            }
            return $data;
        });
    }

    /**
     * 待核销订单
     * @param int $mer_id
     * @param string $type
     * @return mixed
     * @throws \Exception
     *
     * @date 2023/11/02
     * @author yyw
     */
    public function getMerchantPendWriteOffOrderInfo(int $mer_id, string $type = 'count')
    {
        return $this->cache(__FUNCTION__, function () use ($mer_id, $type) {
            $storeOrderRepository = app()->make(StoreOrderRepository::class);
            $stay_cancel_count = $storeOrderRepository->search(['mer_id' => $mer_id, 'order_type' => 1, 'status' => 0, 'paid' => 1])->count();
            $stay_cancel_path = '/'.$this->merchant_prefix.'/order/list?order_type=1&type=1';
            $data = [];
            switch ($type) {
                case 'count':
                    $data[] = [
                        'title' => '待核销订单',
                        'icon' => 'icondaihexiao',
                        'path' => $stay_cancel_path,
                        'count' => $stay_cancel_count,
                        'children' => []
                    ];
                    break;
                case 'todo':
                    if ($stay_cancel_count > 0) {
                        $data[] = [
                            'title' => '待核销订单提醒',
                            'path' => $stay_cancel_path,
                            'message' => '您有' . $stay_cancel_count . '个待核销的订单'
                        ];
                    }
                    break;
            }
            return $data;
        });
    }

    /**
     * 待退款订单
     * @param int $mer_id
     * @param string $type
     * @return mixed
     * @throws \Exception
     *
     * @date 2023/10/25
     * @author yyw
     */
    public function getMerchantRefundOrderInfo(int $mer_id, string $type = 'count')
    {
        return $this->cache(__FUNCTION__, function () use ($mer_id, $type) {
            $storeRefundOrderRepository = app()->make(StoreRefundOrderRepository::class);
            $stay_refund_count = $storeRefundOrderRepository->search(['mer_id' => $mer_id, 'status' => 0])->count();

            $stay_refund_path = '/'.$this->merchant_prefix.'/order/refund?status=0';
            $data = [];

            switch ($type) {
                case 'count':
                    $data[] = [
                        'title' => '待退款订单',
                        'icon' => 'icondaituikuan',
                        'path' => $stay_refund_path,
                        'count' => $stay_refund_count,
                        'children' => []
                    ];
                    break;
                case 'todo':
                    if ($stay_refund_count > 0) {
                        $data[] = [
                            'title' => '待退款订单提醒',
                            'path' => $stay_refund_path,
                            'message' => '您有' . $stay_refund_count . '个待退款的订单'
                        ];
                    }

                    break;
            }
            return $data;
        });
    }


    /**
     * 获取商户商品评论代办
     * @param int $mer_id
     * @param string $type
     * @return mixed
     * @throws \Exception
     *
     * @date 2023/10/25
     * @author yyw
     */
    public function getMerchantStayReviewsInfo(int $mer_id, string $type = 'count')
    {
        return $this->cache(__FUNCTION__, function () use ($mer_id, $type) {
            $productRepository = app()->make(ProductReplyRepository::class);
            $count = $productRepository->searchJoinQuery(['mer_id' => $mer_id, 'is_reply' => 0, 'is_del' => 0])->count();
            $path = '/'.$this->merchant_prefix.'/product/reviews?is_reply=0';
            $data = [];

            switch ($type) {
                case 'count':
                    $data[] = [
                        'title' => '待回复评论',
                        'icon' => 'icondaihuifu',
                        'path' => $path,
                        'count' => $count,
                        'children' => []
                    ];
                    break;
                case 'todo':
                    if ($count > 0) {
                        $data[] = [
                            'title' => '评论回复提醒',
                            'path' => $path,
                            'message' => '您有' . $count . '个评论待回复'
                        ];
                    }
                    break;
            }

            return $data;
        });
    }

    /**
     * 获取开发票订单信息
     * @param int $mer_id
     * @param string $type
     * @return mixed
     * @throws \Exception
     *
     * @date 2023/10/25
     * @author yyw
     */
    public function getMerchantOrderReceiptInfo(int $mer_id, string $type = 'count')
    {
        return $this->cache(__FUNCTION__, function () use ($mer_id, $type) {
            $storeOrderReceiptRepository = app()->make(StoreOrderReceiptRepository::class);
            $count = $storeOrderReceiptRepository->search(['mer_id' => $mer_id, 'status' => 0])->count();
            $path = '/'.$this->merchant_prefix.'/order/invoice?status=0';
            $data = [];

            switch ($type) {
                case 'count':
                    $data[] = [
                        'title' => '待开发票',
                        'icon' => 'icondaikaipiao',
                        'path' => $path,
                        'count' => $count,
                        'children' => []
                    ];
                    break;
                case 'todo':
                    if ($count > 0) {
                        $data[] = [
                            'title' => '待开票订单提醒',
                            'path' => $path,
                            'message' => '您有' . $count . '个待开票的订单'
                        ];
                    }
                    break;
            }

            return $data;
        });
    }

    public function getMerchantNoticeInfo(int $mer_id, string $type = 'count')
    {
        return $this->cache(__FUNCTION__, function () use ($mer_id, $type) {
            $systemNoticeLogRepository = app()->make(SystemNoticeLogRepository::class);
            $count = $systemNoticeLogRepository->search(['mer_id' => $mer_id, 'is_read' => 0, 'is_del' => 0])->count();
            $path = '/'.$this->merchant_prefix.'/station/notice?is_read=0';
            $data = [];

            switch ($type) {
                case 'count':
                    if ($count > 0) {
                        $data[] = [
                            'title' => '平台公告',
                            'icon' => '',
                            'path' => $path,
                            'count' => $count,
                            'children' => []
                        ];
                    }
                    break;
                case 'todo':
                    if ($count > 0) {
                        $data[] = [
                            'title' => '平台公告',
                            'path' => $path,
                            'message' => '您有' . $count . '条未读消息'
                        ];
                    }
                    break;
            }

            return $data;
        });
    }

    /**
     * 获取商户商品销售金额排行
     * @param string $date
     * @return array
     *
     * @date 2023/10/21
     * @author yyw
     */
    public function getMerchantProductSalesPriceTop(int $mer_id, string $date)
    {
        return $this->cache(__FUNCTION__ . '_' . $mer_id . '_' . $date, function () use ($mer_id, $date) {
            $storeOrderProductRepository = app()->make(StoreOrderProductRepository::class);
            $list = $storeOrderProductRepository->getProductRate($mer_id, $date);
            return compact('list');
        });
    }
}
