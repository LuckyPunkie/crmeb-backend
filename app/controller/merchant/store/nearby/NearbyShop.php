<?php


namespace app\controller\merchant\store\nearby;

use think\App;
use think\facade\Db;
use crmeb\basic\BaseController;
use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\store\nearby\NearbyShopCategoryRepository;
use app\validate\merchant\nearby\NearbyShopConfigValidate;

/**
 * 商户后台 - 附近好店设置
 */
class NearbyShop extends BaseController
{
    protected $repository;

    /** @var string[] 附近好店相关商户表字段 */
    protected $nearbyFields = [
        'nearby_wechat',
        'nearby_fan_group_img',
        'hero_images',
        'nearby_is_show',
        'nearby_category_id',
        'nearby_latitude',
        'nearby_longitude',
        'nearby_avg_price',
        'nearby_business_hours',
        'nearby_announcement',
        'nearby_tags',
    ];

    /** @var array|null */
    protected static $merchantColumns;

    public function __construct(App $app, MerchantRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 获取 eb_merchant 表实际存在的列名
     */
    protected function getMerchantColumns(): array
    {
        if (self::$merchantColumns !== null) {
            return self::$merchantColumns;
        }

        $table = Db::name('merchant')->getTable();
        $rows = Db::query(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        );

        self::$merchantColumns = array_column($rows, 'COLUMN_NAME');

        return self::$merchantColumns;
    }

    protected function hasMerchantColumn(string $column): bool
    {
        return in_array($column, $this->getMerchantColumns(), true);
    }

    /**
     * 只保留表中真实存在的字段，避免 schema 缓存与新增列不一致
     */
    protected function pickExistingColumns(array $data): array
    {
        $columns = array_flip($this->getMerchantColumns());

        return array_filter(
            $data,
            static function ($value, $key) use ($columns) {
                return isset($columns[$key]);
            },
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * 读取商户附近好店字段
     */
    protected function getNearbyMerchant(int $merId): array
    {
        $row = Db::name('merchant')->strict(false)->where('mer_id', $merId)->find() ?: [];
        $result = [];

        foreach ($this->nearbyFields as $field) {
            if ($this->hasMerchantColumn($field) && array_key_exists($field, $row)) {
                $result[$field] = $row[$field];
            }
        }

        return $result;
    }

    /**
     * 更新商户附近好店字段
     */
    protected function updateNearbyMerchant(int $merId, array $data): void
    {
        $data = $this->pickExistingColumns($data);
        if (!$data) {
            return;
        }

        Db::name('merchant')->strict(false)->where('mer_id', $merId)->update($data);
    }

    /**
     * 获取当前附近好店设置
     */
    public function config()
    {
        $merId = $this->request->merId();
        $merchant = $this->getNearbyMerchant($merId);

        $data = [
            'wechat' => $merchant['nearby_wechat'] ?? '',
            'fan_group_img' => $merchant['nearby_fan_group_img'] ?? '',
            'hero_images' => !empty($merchant['hero_images']) ? json_decode($merchant['hero_images'], true) : [],
            'nearby_is_show' => $merchant['nearby_is_show'] ?? 1,
            'nearby_category_id' => $merchant['nearby_category_id'] ?? 0,
            'nearby_latitude' => $merchant['nearby_latitude'] ?? null,
            'nearby_longitude' => $merchant['nearby_longitude'] ?? null,
            'nearby_avg_price' => $merchant['nearby_avg_price'] ?? 0,
            'nearby_business_hours' => $merchant['nearby_business_hours'] ?? '',
            'nearby_announcement' => $merchant['nearby_announcement'] ?? '',
            'nearby_tags' => $merchant['nearby_tags'] ?? '',
        ];

        if (!is_array($data['hero_images'])) {
            $data['hero_images'] = [];
        }

        return app('json')->success($data);
    }

    /**
     * 保存附近好店设置
     */
    public function saveConfig(NearbyShopConfigValidate $validate)
    {
        $data = $this->request->params([
            'wechat',
            'fan_group_img',
            'hero_images',
            'nearby_is_show',
            'nearby_category_id',
            'nearby_latitude',
            'nearby_longitude',
            'nearby_avg_price',
            'nearby_business_hours',
            'nearby_announcement',
            'nearby_tags',
        ]);

        $validate->check($data);

        if (!empty($data['hero_images']) && is_array($data['hero_images'])) {
            foreach ($data['hero_images'] as $url) {
                if (!is_string($url) || $url === '') {
                    return app('json')->fail('hero_images 中包含非法图片地址');
                }
            }
        }

        if (isset($data['hero_images']) && is_array($data['hero_images'])) {
            $data['hero_images'] = json_encode($data['hero_images'], JSON_UNESCAPED_UNICODE);
        }

        if (isset($data['wechat'])) {
            $data['nearby_wechat'] = $data['wechat'];
            unset($data['wechat']);
        }
        if (isset($data['fan_group_img'])) {
            $data['nearby_fan_group_img'] = $data['fan_group_img'];
            unset($data['fan_group_img']);
        }

        $merId = $this->request->merId();
        $this->updateNearbyMerchant($merId, $data);

        return app('json')->success('设置保存成功');
    }

    /**
     * 附近好店分类树（商户后台表单使用）
     */
    public function categoryTree(NearbyShopCategoryRepository $repository)
    {
        return app('json')->success($repository->getTree());
    }
}
