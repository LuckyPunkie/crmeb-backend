<?php



namespace app\controller\api\taoke;



use app\common\repositories\taoke\CommissionRepository;

use crmeb\basic\BaseController;

use crmeb\services\taoke\DingDanXiaService;

use crmeb\services\taoke\JdOfficialService;

use crmeb\services\taoke\JuTuiKeService;

use crmeb\services\taoke\PddOfficialService;

use crmeb\services\taoke\TaobaoOfficialService;

use crmeb\services\taoke\TaokeUserTrackService;

use crmeb\services\taoke\VipOfficialService;

use think\App;

use think\facade\Log;



/**

 * 淘宝客订单API接口（联盟订单，非商城 store_order）

 */

class Order extends BaseController

{

    protected JuTuiKeService $jutuikeService;



    protected DingDanXiaService $dingdanxiaService;



    protected CommissionRepository $commissionRepository;



    protected TaobaoOfficialService $taobaoOfficial;



    protected PddOfficialService $pddOfficial;



    protected JdOfficialService $jdOfficial;



    protected VipOfficialService $vipOfficial;



    public function __construct(

        App $app,

        JuTuiKeService $jutuikeService,

        DingDanXiaService $dingdanxiaService,

        CommissionRepository $commissionRepository,

        TaobaoOfficialService $taobaoOfficial,

        PddOfficialService $pddOfficial,

        JdOfficialService $jdOfficial,

        VipOfficialService $vipOfficial

    ) {

        parent::__construct($app);

        $this->jutuikeService = $jutuikeService;

        $this->dingdanxiaService = $dingdanxiaService;

        $this->commissionRepository = $commissionRepository;

        $this->taobaoOfficial = $taobaoOfficial;

        $this->pddOfficial = $pddOfficial;

        $this->jdOfficial = $jdOfficial;

        $this->vipOfficial = $vipOfficial;

    }



    public function jutuikeOrder()

    {

        $uid = $this->orderListUid();

        if ($uid <= 0) {

            return app('json')->fail('请先登录后查看返利订单');

        }

        $page = (int) $this->request->post('page', 1);

        $limit = (int) $this->request->post('pageSize', 20);

        $start_time = $this->scalarPost('start_time');

        $status = $this->scalarPost('status');

        $end_time = $this->scalarPost('end_time');

        $order_sn = $this->scalarPost('order_sn');

        if ($start_time === '' || $end_time === '') {

            [$start_time, $end_time] = $this->defaultDateTimeRange(24);

        }

        try {

            $result = $this->jutuikeService->getJutuikeOrder($start_time, $end_time, $status, $order_sn, $page, $limit);

            $result = $this->filterNestedOrderPayload('jutuike', $result, $uid);



            return app('json')->success($result);

        } catch (\Exception $e) {

            Log::error('聚推客订单获取失败', [

                'error' => $e->getMessage(),

                'trace' => $e->getTraceAsString(),

            ]);



            return app('json')->fail('获取活动订单失败: ' . $e->getMessage());

        }

    }



    public function taobaoOrder()

    {

        $uid = $this->orderListUid();

        if ($uid <= 0) {

            return app('json')->fail('请先登录后查看返利订单');

        }

        $page = (int) $this->request->post('page_no', $this->request->post('page', 1));

        $limit = (int) $this->request->post('page_size', $this->request->post('pageSize', 20));

        $queryType = (int) $this->request->post('query_type', 1);

        [$start_time, $end_time] = $this->resolveDateTimeRange(24);

        $extra = [

            'position_index' => $this->scalarPost('position_index'),

            'jump_type' => $this->scalarPost('jump_type'),

            'order_scene' => $this->scalarPost('order_scene'),

        ];

        try {

            $raw = $this->taobaoOfficial->orderDetailsGet(

                $start_time,

                $end_time,

                600,

                max(1, $page),

                max(1, min(100, $limit)),

                $queryType > 0 ? $queryType : 1,

                $extra

            );

            $parsed = $this->taobaoOfficial->parseOrderList($raw);

            $parsed['list'] = TaokeUserTrackService::filterOrderRows('taobao', $parsed['list'], $uid);

            if (isset($raw['error_response'])) {

                Log::warning('淘宝联盟订单 API 错误', [

                    'error' => json_encode($raw['error_response'], JSON_UNESCAPED_UNICODE),

                ]);

            }



            return app('json')->success([

                'list' => $parsed['list'],

                'has_next' => $parsed['has_next'],

                'position_index' => $parsed['position_index'],

                '_source' => 'official',

            ]);

        } catch (\Exception $e) {

            Log::error('淘宝联盟订单获取失败', ['error' => $e->getMessage()]);



            return app('json')->fail('获取订单失败: ' . $e->getMessage());

        }

    }



    public function pddOrder()

    {

        $uid = $this->orderListUid();

        if ($uid <= 0) {

            return app('json')->fail('请先登录后查看返利订单');

        }

        $page = (int) $this->request->post('page', 1);

        $limit = (int) $this->request->post('page_size', $this->request->post('pageSize', 20));

        [$startSec, $endSec] = $this->resolveUnixRange(24 * 3600);

        try {

            $bundle = $this->pddOfficial->fetchOrderListIncrement($startSec, $endSec, $page, $limit);

            if (isset($bundle['raw']['error_response'])) {

                Log::warning('拼多多联盟订单 API 错误', [

                    'error' => json_encode($bundle['raw']['error_response'], JSON_UNESCAPED_UNICODE),

                ]);

            }



            $list = TaokeUserTrackService::filterOrderRows('pdd', $bundle['list'], $uid);



            return app('json')->success([

                'list' => $list,

                '_source' => 'official',

            ]);

        } catch (\Exception $e) {

            Log::error('拼多多联盟订单获取失败', ['error' => $e->getMessage()]);



            return app('json')->fail('获取订单失败: ' . $e->getMessage());

        }

    }



    public function pddOrderDetail()

    {

        $uid = $this->orderListUid();

        if ($uid <= 0) {

            return app('json')->fail('请先登录后查看返利订单');

        }

        $order_sn = $this->scalarPost('order_sn');

        if ($order_sn === '') {

            return app('json')->fail('缺少 order_sn');

        }

        try {

            [$startSec, $endSec] = $this->resolveUnixRange(24 * 3600);

            $bundle = $this->pddOfficial->fetchOrderListIncrement($startSec, $endSec, 1, 100);

            foreach ($bundle['list'] as $row) {

                if (!is_array($row)) {

                    continue;

                }

                if ((string) ($row['order_sn'] ?? '') === $order_sn) {

                    if (!TaokeUserTrackService::orderBelongsToUser('pdd', $row, $uid)) {

                        return app('json')->fail('未找到订单');

                    }

                    return app('json')->success($row);

                }

            }



            return app('json')->fail('未找到订单（请缩小/调整查询时间后重试）');

        } catch (\Exception $e) {

            Log::error('拼多多订单详情失败', ['error' => $e->getMessage()]);



            return app('json')->fail('获取订单失败: ' . $e->getMessage());

        }

    }



    public function jdOrder()

    {

        $uid = $this->orderListUid();

        if ($uid <= 0) {

            return app('json')->fail('请先登录后查看返利订单');

        }

        $page = (int) $this->request->post('pageIndex', $this->request->post('page', 1));

        $limit = (int) $this->request->post('pageSize', 20);

        $timeType = (int) $this->request->post('type', 1);

        $orderId = $this->scalarPost('orderId');

        [$startMs, $endMs] = $this->resolveMillisRange(3600);

        try {

            if ($orderId !== '') {

                $raw = $this->jdOfficial->orderRowQuery($startMs, $endMs, 1, 1, $timeType);

            } else {

                $raw = $this->jdOfficial->orderRowQuery($startMs, $endMs, max(1, $page), max(1, min(500, $limit)), $timeType);

            }

            $list = $this->jdOfficial->parseOrderRowList($raw);

            if ($orderId !== '') {

                $list = array_values(array_filter($list, static function ($row) use ($orderId) {

                    return is_array($row) && (string) ($row['orderId'] ?? '') === $orderId;

                }));

            }

            $list = TaokeUserTrackService::filterOrderRows('jd', $list, $uid);



            return app('json')->success([

                'list' => $list,

                '_source' => 'official',

            ]);

        } catch (\Exception $e) {

            Log::error('京东联盟订单获取失败', ['error' => $e->getMessage()]);



            return app('json')->fail('获取订单失败: ' . $e->getMessage());

        }

    }



    public function vipOrder()

    {

        $uid = $this->orderListUid();

        if ($uid <= 0) {

            return app('json')->fail('请先登录后查看返利订单');

        }

        $page = (int) $this->request->post('page', 1);

        $limit = (int) $this->request->post('pageSize', 20);

        [$startMs, $endMs] = $this->resolveMillisRange(3600);

        try {

            $bundle = $this->vipOfficial->fetchOrderList($startMs, $endMs, $page, $limit);

            $list = TaokeUserTrackService::filterOrderRows('vip', $bundle['list'], $uid);



            return app('json')->success([

                'list' => $list,

                'total' => count($list),

                '_source' => 'official',

            ]);

        } catch (\Exception $e) {

            Log::error('唯品会联盟订单获取失败', ['error' => $e->getMessage()]);



            return app('json')->fail('获取订单失败: ' . $e->getMessage());

        }

    }



    public function vipOrderDetail()

    {

        $uid = $this->orderListUid();

        if ($uid <= 0) {

            return app('json')->fail('请先登录后查看返利订单');

        }

        $orderSn = $this->scalarPost('orderSn');

        if ($orderSn === '') {

            return app('json')->fail('缺少 orderSn');

        }

        try {

            [$startMs, $endMs] = $this->resolveMillisRange(3600);

            $bundle = $this->vipOfficial->fetchOrderList($startMs, $endMs, 1, 100);

            foreach ($bundle['list'] as $row) {

                if (is_array($row) && (string) ($row['orderSn'] ?? '') === $orderSn) {

                    if (!TaokeUserTrackService::orderBelongsToUser('vip', $row, $uid)) {

                        return app('json')->fail('未找到订单');

                    }

                    return app('json')->success($row);

                }

            }



            return app('json')->fail('未找到订单（请缩小/调整查询时间后重试）');

        } catch (\Exception $e) {

            Log::error('唯品会订单详情失败', ['error' => $e->getMessage()]);



            return app('json')->fail('获取详情失败: ' . $e->getMessage());

        }

    }



    /**

     * @return array{0: string, 1: string} [start, end] Y-m-d H:i:s

     */

    protected function resolveDateTimeRange(int $maxSeconds): array

    {

        $start = $this->scalarPost('start_time');

        $end = $this->scalarPost('end_time');

        if ($start === '' || $end === '') {

            return $this->defaultDateTimeRange((int) ceil($maxSeconds / 3600));

        }

        $startTs = strtotime(str_replace('/', '-', $start)) ?: 0;

        $endTs = strtotime(str_replace('/', '-', $end)) ?: 0;

        if ($startTs <= 0 || $endTs <= 0 || $startTs > $endTs) {

            return $this->defaultDateTimeRange((int) ceil($maxSeconds / 3600));

        }

        if ($endTs - $startTs > $maxSeconds) {

            $startTs = $endTs - $maxSeconds;

        }



        return [date('Y-m-d H:i:s', $startTs), date('Y-m-d H:i:s', $endTs)];

    }



    /**

     * @return array{0: int, 1: int} unix seconds

     */

    protected function resolveUnixRange(int $maxSeconds): array

    {

        $start = (int) $this->request->post('start_update_time', 0);

        $end = (int) $this->request->post('end_update_time', 0);

        if ($start <= 0 || $end <= 0) {

            $end = time();

            $start = $end - min($maxSeconds, 24 * 3600);

        }

        if ($start > $end) {

            [$start, $end] = [$end, $start];

        }

        if ($end - $start > $maxSeconds) {

            $start = $end - $maxSeconds;

        }



        return [$start, $end];

    }



    /**

     * @return array{0: int, 1: int} milliseconds

     */

    protected function resolveMillisRange(int $maxSeconds): array

    {

        $startMs = (int) $this->request->post('orderTimeStart', $this->request->post('startTime', 0));

        $endMs = (int) $this->request->post('orderTimeEnd', $this->request->post('endTime', 0));

        if ($startMs <= 0 || $endMs <= 0) {

            $endMs = (int) round(microtime(true) * 1000);

            $startMs = $endMs - min($maxSeconds, 3600) * 1000;

        }

        if ($startMs > $endMs) {

            [$startMs, $endMs] = [$endMs, $startMs];

        }

        $maxMs = $maxSeconds * 1000;

        if ($endMs - $startMs > $maxMs) {

            $startMs = $endMs - $maxMs;

        }



        return [$startMs, $endMs];

    }



    /**

     * @return array{0: string, 1: string}

     */

    protected function defaultDateTimeRange(int $hours = 24): array

    {

        $hours = max(1, min(24, $hours));

        $end = time();

        $start = $end - $hours * 3600;



        return [date('Y-m-d H:i:s', $start), date('Y-m-d H:i:s', $end)];

    }



    protected function scalarPost(string $key, string $default = ''): string

    {

        $val = $this->request->post($key, $default);

        if (is_array($val)) {

            $val = $val[0] ?? $default;

        }



        return trim((string) $val);

    }



    protected function orderListUid(): int

    {

        if (!$this->request->isLogin()) {

            return 0;

        }

        return max(0, (int) $this->request->uid());

    }



    /**

     * @param mixed $payload

     * @return mixed

     */

    protected function filterNestedOrderPayload(string $platform, $payload, int $uid)

    {

        if (!is_array($payload)) {

            return $payload;

        }

        foreach (['data', 'list', 'orderList'] as $key) {

            if (isset($payload[$key]) && is_array($payload[$key])) {

                $payload[$key] = TaokeUserTrackService::filterOrderRows($platform, $payload[$key], $uid);

            }

        }

        if (isset($payload[0]) && is_array($payload[0])) {

            return TaokeUserTrackService::filterOrderRows($platform, $payload, $uid);

        }



        return $payload;

    }

}


