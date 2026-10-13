<?php
// +----------------------------------------------------------------------
// | CRMEB商品导入命令
// +----------------------------------------------------------------------

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Option;
use think\console\input\Argument;
use think\facade\Db;
use app\common\repositories\store\StoreCategoryRepository;
use app\common\repositories\store\product\ProductRepository;
use app\common\repositories\store\product\ProductUnitRepository;
use app\common\repositories\store\product\ProductCateRepository;

/**
 * 商品导入命令
 * 
 * 使用方法：
 * php think product:import                    # 使用默认JSON文件
 * php think product:import --file=/path/to/import_data.json  # 指定JSON文件
 * php think product:import --batch=50        # 指定每批处理数量
 * php think product:import --mer_id=1        # 指定商户ID
 */
class ProductImport extends Command
{
    // 分类路径到ID的映射
    protected $categoryMap = [];
    
    // 单位名称到ID的映射
    protected $unitMap = [];
    
    // 商户分类名称到ID的映射
    protected $merchantCategoryMap = [];
    
    // 商户ID
    protected $merId = 0;
    
    // 统计信息
    protected $stats = [
        'categories_created' => 0,
        'units_created' => 0,
        'products_created' => 0,
        'products_failed' => 0,
        'errors' => []
    ];

    protected function configure()
    {
        $this->setName('product:import')
            ->setDescription('从JSON文件导入商品数据')
            ->addOption('file', 'f', Option::VALUE_OPTIONAL, 'JSON数据文件路径', root_path('extend/ProductImport/import_data.json'))
            ->addOption('batch', 'b', Option::VALUE_OPTIONAL, '每批处理数量', 100)
            ->addOption('mer_id', 'm', Option::VALUE_OPTIONAL, '商户ID', 0)
            ->addOption('skip-category', null, Option::VALUE_NONE, '跳过分类导入（使用现有分类）')
            ->addOption('skip-unit', null, Option::VALUE_NONE, '跳过单位导入（使用现有单位）');
    }

    protected function execute(Input $input, Output $output)
    {
        $file = $input->getOption('file');
        $batchSize = (int)$input->getOption('batch');
        $this->merId = (int)$input->getOption('mer_id');
        $skipCategory = $input->getOption('skip-category');
        $skipUnit = $input->getOption('skip-unit');

        $output->writeln('========================================');
        $output->writeln('商品导入工具 v1.0');
        $output->writeln('========================================');

        // 检查文件是否存在
        if (!file_exists($file)) {
            $output->error("JSON文件不存在: {$file}");
            $output->writeln("请先运行Python脚本生成数据文件:");
            $output->writeln("  python extend/ProductImport/import_products.py <excel文件>");
            return 1;
        }

        // 读取JSON数据
        $output->writeln("\n[1/5] 读取数据文件...");
        $jsonData = json_decode(file_get_contents($file), true);
        if (!$jsonData) {
            $output->error("JSON文件格式错误");
            return 1;
        }

        $meta = $jsonData['meta'];
        $output->writeln("  - 商品数量: {$meta['total_products']}");
        $output->writeln("  - 平台分类: {$meta['total_platform_categories']}");
        $output->writeln("  - 商户分类: {$meta['total_merchant_categories']}");
        $output->writeln("  - 单位: {$meta['total_units']}");

        // 导入平台分类
        if (!$skipCategory) {
            $output->writeln("\n[2/5] 导入平台分类...");
            $this->importPlatformCategories($jsonData['platform_categories'], $output);
        } else {
            $output->writeln("\n[2/5] 跳过分类导入，加载现有分类映射...");
            $this->loadExistingCategories();
        }
        $output->writeln("  分类映射数量: " . count($this->categoryMap));

        // 导入商户分类
        $output->writeln("\n[3/5] 导入商户分类...");
        $this->importMerchantCategories($jsonData['merchant_categories'], $output);

        // 导入单位
        if (!$skipUnit) {
            $output->writeln("\n[4/5] 导入单位...");
            $this->importUnits($jsonData['units'], $output);
        } else {
            $output->writeln("\n[4/5] 跳过单位导入，加载现有单位映射...");
            $this->loadExistingUnits();
        }

        // 导入商品
        $output->writeln("\n[5/5] 导入商品...");
        $this->importProducts($jsonData['products'], $batchSize, $output);

        // 输出统计信息
        $output->writeln("\n========================================");
        $output->writeln('导入完成！');
        $output->writeln('========================================');
        $output->writeln("平台分类创建: {$this->stats['categories_created']}");
        $output->writeln("单位创建: {$this->stats['units_created']}");
        $output->writeln("商品创建成功: {$this->stats['products_created']}");
        $output->writeln("商品创建失败: {$this->stats['products_failed']}");

        if (!empty($this->stats['errors'])) {
            $output->writeln("\n错误详情:");
            foreach (array_slice($this->stats['errors'], 0, 10) as $error) {
                $output->writeln("  - {$error}");
            }
            if (count($this->stats['errors']) > 10) {
                $output->writeln("  ... 还有 " . (count($this->stats['errors']) - 10) . " 条错误");
            }
        }

        return 0;
    }

    /**
     * 导入平台分类（按层级顺序）
     */
    protected function importPlatformCategories(array $categories, Output $output)
    {
        // 先加载现有分类到映射
        $this->loadExistingCategories();

        // 按层级分组
        $byLevel = [0 => [], 1 => [], 2 => []];
        foreach ($categories as $cat) {
            $level = $cat['level'];
            if (isset($byLevel[$level])) {
                $byLevel[$level][] = $cat;
            }
        }

        // 按层级顺序导入：0级 -> 1级 -> 2级
        foreach ([0, 1, 2] as $level) {
            $output->writeln("  处理 {$level} 级分类 (" . count($byLevel[$level]) . " 个)...");
            
            foreach ($byLevel[$level] as $cat) {
                $pathKey = $cat['path_key'];
                
                // 已存在则跳过
                if (isset($this->categoryMap[$pathKey])) {
                    continue;
                }

                // 获取父级ID
                $pid = 0;
                $path = '/';
                if ($level > 0 && !empty($cat['parent_key'])) {
                    if (isset($this->categoryMap[$cat['parent_key']])) {
                        $parentId = $this->categoryMap[$cat['parent_key']];
                        $pid = $parentId;
                        // 构建path
                        $parentPath = Db::name('store_category')
                            ->where('store_category_id', $parentId)
                            ->value('path');
                        $path = $parentPath . $parentId . '/';
                    }
                }

                // 插入分类
                try {
                    $categoryId = Db::name('store_category')->insertGetId([
                        'cate_name' => $cat['name'],
                        'pid' => $pid,
                        'path' => $path,
                        'level' => $level,
                        'sort' => 0,
                        'is_show' => 1,
                        'mer_id' => 0, // 平台分类
                        'type' => 0,
                        'create_time' => time(),
                    ]);

                    $this->categoryMap[$pathKey] = $categoryId;
                    $this->stats['categories_created']++;
                } catch (\Exception $e) {
                    $output->error("    分类创建失败: {$cat['name']} - " . $e->getMessage());
                }
            }
        }
    }

    /**
     * 导入商户分类
     */
    protected function importMerchantCategories(array $categories, Output $output)
    {
        // 先加载现有商户分类
        $existing = Db::name('store_category')
            ->where('mer_id', $this->merId)
            ->where('level', 0)
            ->column('store_category_id', 'cate_name');
        
        $this->merchantCategoryMap = $existing;

        foreach ($categories as $cat) {
            $name = $cat['name'];
            
            // 已存在则跳过
            if (isset($this->merchantCategoryMap[$name])) {
                continue;
            }

            try {
                $categoryId = Db::name('store_category')->insertGetId([
                    'cate_name' => $name,
                    'pid' => 0,
                    'path' => '/',
                    'level' => 0,
                    'sort' => 0,
                    'is_show' => 1,
                    'mer_id' => $this->merId,
                    'type' => 0,
                    'create_time' => time(),
                ]);

                $this->merchantCategoryMap[$name] = $categoryId;
                $output->writeln("  创建商户分类: {$name} (ID: {$categoryId})");
            } catch (\Exception $e) {
                $output->error("  商户分类创建失败: {$name} - " . $e->getMessage());
            }
        }
    }

    /**
     * 导入单位
     */
    protected function importUnits(array $units, Output $output)
    {
        // 先加载现有单位
        $this->loadExistingUnits();

        foreach ($units as $unit) {
            $name = $unit['name'];
            
            // 已存在则跳过
            if (isset($this->unitMap[$name])) {
                continue;
            }

            try {
                $unitId = Db::name('store_product_unit')->insertGetId([
                    'name' => $name,
                    'create_time' => time(),
                ]);

                $this->unitMap[$name] = $unitId;
                $this->stats['units_created']++;
            } catch (\Exception $e) {
                $output->error("  单位创建失败: {$name} - " . $e->getMessage());
            }
        }
    }

    /**
     * 批量导入商品
     */
    protected function importProducts(array $products, int $batchSize, Output $output)
    {
        $total = count($products);
        $batches = ceil($total / $batchSize);
        
        $productRepository = app()->make(ProductRepository::class);

        for ($i = 0; $i < $batches; $i++) {
            $offset = $i * $batchSize;
            $batch = array_slice($products, $offset, $batchSize);
            
            $output->writeln("  处理批次 " . ($i + 1) . "/{$batches} (商品 " . ($offset + 1) . "-" . ($offset + count($batch)) . ")...");

            foreach ($batch as $productData) {
                try {
                    $this->createProduct($productData, $productRepository);
                    $this->stats['products_created']++;
                } catch (\Exception $e) {
                    $this->stats['products_failed']++;
                    $this->stats['errors'][] = "行 {$productData['row_index']}: {$productData['store_name']} - " . $e->getMessage();
                }
            }

            // 每批次后输出进度
            $output->writeln("    已完成: {$this->stats['products_created']}/{$total}, 失败: {$this->stats['products_failed']}");
        }
    }

    /**
     * 创建单个商品
     */
    protected function createProduct(array $data, ProductRepository $repository)
    {
        // 获取三级分类ID
        $cateId = 0;
        if (!empty($data['platform_category_path'])) {
            $cateId = $this->categoryMap[$data['platform_category_path']] ?? 0;
        }
        
        if (!$cateId) {
            throw new \Exception("未找到分类: {$data['platform_category_path']}");
        }

        // 获取商户分类ID
        $merCateIds = [];
        if (!empty($data['merchant_category_name'])) {
            if (isset($this->merchantCategoryMap[$data['merchant_category_name']])) {
                $merCateIds = [$this->merchantCategoryMap[$data['merchant_category_name']]];
            }
        }

        // 构建商品数据
        $productInput = [
            'mer_id' => $this->merId,
            'store_name' => $data['store_name'],
            'store_info' => mb_substr($data['content'], 0, 200), // 商品简介取详情前200字
            'image' => $data['image'],
            'slider_image' => $data['slider_image'] ? explode(',', $data['slider_image']) : [],
            'cate_id' => $cateId,
            'mer_cate_id' => $merCateIds,
            'unit_name' => $data['unit_name'],
            'spec_type' => $data['spec_type'],
            'is_show' => 1,
            'is_good' => 0,
            'is_gift_bag' => 0,
            'status' => 1, // 直接审核通过
            'mer_status' => 1,
            'temp_id' => 0, // 运费模板
            'delivery_way' => [2], // 快递发货
            'delivery_free' => 1, // 包邮
            'extension_type' => 0,
            'content' => $data['content'],
            'attrValue' => $this->buildAttrValue($data),
            'attr' => [], // 单规格不需要attr
        ];

        // 调用Repository创建商品
        return $repository->create($productInput, 0, 0, 0);
    }

    /**
     * 构建SKU数据
     */
    protected function buildAttrValue(array $data)
    {
        return [
            [
                'detail' => [],
                'price' => $data['price'],
                'ot_price' => $data['ot_price'],
                'cost' => 0,
                'stock' => $data['stock'],
                'sales' => 0,
                'image' => $data['image'],
                'bar_code' => $data['bar_code'],
                'weight' => $data['weight'],
                'volume' => $data['volume'],
                'svip_price' => 0,
                'extension_one' => 0,
                'extension_two' => 0,
            ]
        ];
    }

    /**
     * 加载现有分类映射
     */
    protected function loadExistingCategories()
    {
        $categories = Db::name('store_category')
            ->where('mer_id', 0)
            ->field('store_category_id, cate_name, pid, level, path')
            ->select()
            ->toArray();

        // 构建path_key到ID的映射
        foreach ($categories as $cat) {
            $pathKey = $this->buildPathKey($cat, $categories);
            if ($pathKey) {
                $this->categoryMap[$pathKey] = $cat['store_category_id'];
            }
        }
    }

    /**
     * 构建分类路径key
     */
    protected function buildPathKey($category, $allCategories)
    {
        static $categoryById = null;
        if ($categoryById === null) {
            $categoryById = array_column($allCategories, null, 'store_category_id');
        }

        $names = [$category['cate_name']];
        $current = $category;

        // 向上追溯父级
        while ($current['pid'] > 0) {
            if (!isset($categoryById[$current['pid']])) {
                break;
            }
            $parent = $categoryById[$current['pid']];
            array_unshift($names, $parent['cate_name']);
            $current = $parent;
        }

        return implode('/', $names);
    }

    /**
     * 加载现有单位映射
     */
    protected function loadExistingUnits()
    {
        $units = Db::name('store_product_unit')
            ->column('product_unit_id', 'name');
        
        $this->unitMap = $units;
    }
}
