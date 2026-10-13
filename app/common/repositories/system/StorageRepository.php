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


namespace app\common\repositories\system;

use app\common\dao\system\SystemStorageDao;
use app\common\repositories\BaseRepository;
use app\common\repositories\system\config\ConfigValueRepository;
use crmeb\services\UploadService;
use FormBuilder\Factory\Elm;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Route;
use think\queue\command\Retry;

class StorageRepository extends BaseRepository
{
    protected $service;

    public function __construct(SystemStorageDao $dao, UploadService $service)
    {
        $this->dao = $dao;
        $this->service = $service;
    }

    /**
     * 获取服务的类型
     *
     * 本方法通过调用内部服务对象的getType方法，来获取服务的类型。
     * 该方法的存在是为了提供一个统一的接口，用于外部代码获取当前服务的类型信息，
     * 而不需要直接与内部服务对象交互。
     *
     * @return string 返回服务的类型
     */
    public function getType()
    {
        return $this->service->getType();
    }

    /**
     * 获取指定类型的前缀
     *
     * 本方法通过调用服务类的静态方法getPrefix来获取特定类型的数据前缀。
     * 这种设计模式的使用允许灵活地从不同的服务类中获取配置或数据前缀，从而提高了代码的可扩展性和可维护性。
     *
     * @param int $type 类型标识，默认为1。不同的类型标识可以用于获取不同数据或配置的前缀，这取决于服务类的具体实现。
     * @return string 返回指定类型的前缀。具体前缀的格式和含义由服务类的实现决定。
     */
    public function getPrefix($type = 1)
    {
        return $this->service::getPrefix($type);
    }


    /**
     * 获取区域列表
     *
     * 根据给定的条件和分页信息，从数据库中检索区域列表。此方法主要用于支持分页查询，以及根据条件筛选结果。
     *
     * @param array $where 查询条件，包含类型在内的各种过滤条件。
     * @param int $page 当前页码，用于分页查询。
     * @param int $limit 每页显示的记录数，用于分页查询。
     * @return array 返回包含总数和列表数据的数组。
     */
    public function lstRegion(array $where, int $page, int $limit)
    {
        // 根据类型获取前缀，用于构建访问密钥的名称
        $prefix = $this->service::getPrefix($where['type']);
        // 构建访问密钥的完整名称
        $accessKey = $prefix . 'accessKey';
        // 通过系统配置获取访问密钥，并将其添加到查询条件中
        $where['access_key'] = systemConfig($accessKey);
        // 根据条件执行查询
        $query = $this->dao->getSearch($where);
        // 统计查询结果的总数
        $count = $query->count();
        // 获取当前页码的分页数据
        $list = $query->page($page, $limit)->select();
        // 返回包含总数和列表数据的数组
        return compact('count', 'list');
    }

    /**
     * 根据类型获取配置信息
     *
     * 本函数旨在通过指定的类型从数据库中检索相应的配置信息。它尝试从配置表中选取类型匹配、状态启用且未被删除的配置项。
     * 如果找到符合条件的配置，则返回该配置的详细信息；如果未找到，则返回一个空的配置数组。
     *
     * @param int $type 配置的类型标识。用于指定查询哪种类型的配置信息。
     * @return array 包含配置名称、区域、域名和CDN信息的数组。如果未找到匹配的配置，则所有字段均为空字符串。
     */
    public function getConfig(int $type, $accessKey)
    {
        // 初始化一个包含默认空值的配置数组
        $res = ['name' => '', 'region' => '', 'domain' => '', 'cdn' => ''];

        try {
            // 优先读取已启用的空间配置
            $config = $this->dao->getWhere(['access_key' => $accessKey, 'type' => $type, 'status' => 1, 'is_del' => 0]);
            // 兼容历史数据：如果未设置启用空间，则回退到最近创建的可用空间
            if (!$config) {
                $config = $this->dao->getSearch(['access_key' => $accessKey, 'type' => $type, 'is_del' => 0])
                    ->order('status DESC,id DESC')
                    ->find();
            }
            // 如果成功获取到配置信息，则返回该配置的详细数组
            if ($config) {
                return ['name' => $config->name, 'region' => $config->region, 'domain' => $config->domain, 'cdn' => $config->cdn];
            }
        } catch (\Throwable $e) {
            // 捕获并处理任何在尝试获取配置信息过程中抛出的异常
        }

        // 如果未找到匹配的配置或发生异常，则返回初始化的默认配置数组
        return $res;
    }

    /**
     * 根据类型生成存储服务配置表单
     *
     * 本函数用于生成一个表单，该表单根据传入的类型（如腾讯云、京东云等）
     * 动态配置相应的访问密钥（AccessKey和SecretKey）以及其他必要的配置项。
     * 通过这种方式，用户可以方便地配置和管理不同存储服务的访问凭证。
     *
     * @param string $type 存储服务类型，决定表单中需要显示的具体配置项。
     * @return \EasyAdmin\ui\Elm|\FormBuilder\Form
     */
    public function form($type)
    {
        // 通过服务类获取指定类型存储服务的前缀
        $prefix = $this->service::getPrefix($type);
        // 根据前缀和键名构造访问密钥的配置键名
        //获取配置
        //accessKey
        $accessKey = $prefix . 'accessKey';
        //secretKey
        $secretKey = $prefix . 'secretKey';

        // 创建表单对象，并设置表单提交的URL
        $form = Elm::createForm(Route::buildUrl('systemStorageUpdate')->build());

        // 定义表单规则，包括隐藏字段upload_type和必填的AccessKey、SecretKey输入字段
        $rule = [
            Elm::hidden('upload_type', $type),
            Elm::input('accessKey', 'AccessKey：', systemConfig($accessKey))->placeholder('请输入accessKey')->required('请输入accessKey'),
            Elm::input('secretKey', 'SecretKey：', systemConfig($secretKey))->placeholder('请输入secretKey')->required('请输入secretKey'),
        ];
        // 根据类型动态添加特定的配置项，如腾讯云的APPID，京东云的storageRegion
        if ($type == $this->service::STORAGE_TENGXUN) {
            $rule[] = Elm::input('tengxun_appid', 'APPID：', systemConfig('tengxun_appid'))->placeholder('请输入APPID')->required('请输入APPID');
        }
        if ($type == $this->service::STORAGE_JINGDONG) {
            $rule[] = Elm::input('jd_storageRegion', 'storageRegion：', systemConfig('jd_storageRegion'))->placeholder('请输入storageRegion')->required('请输入storageRegion');
        }
        // 设置表单的规则，即定义了表单中显示的字段及其验证规则
        $form->setRule($rule);
        // 设置表单标题
        return $form->setTitle('配置');
    }

    /**
     * 创建区域表单
     * 用于系统中创建云存储区域的表单生成，根据不同的存储类型配置不同的表单字段。
     *
     * @param int $type 存储类型，决定表单的具体配置。
     * @return \EasyWeChat\Kernel\Messages\Form 创建的表单实例。
     */
    public function createRegionForm(int $type)
    {
        // 创建上传服务实例，根据类型配置不同的上传策略
        $upload = UploadService::create($type);
        // 根据类型获取配置前缀，用于拼接配置项的键名
        $prefix = $this->service::getPrefix($type);
        // 拼接accessKey和secretKey的键名，并获取对应的配置值
        $accessKey = $prefix . 'accessKey';
        $secretKey = $prefix . 'secretKey';
        // 从系统配置中获取accessKey、secretKey和tengxun_appid的值
        $config = systemConfig([$accessKey, $secretKey, 'tengxun_appid']);

        // 构建表单提交的URL，并创建表单实例
        $form = Elm::createForm(Route::buildUrl('systemStorageCreateRegion', ['type' => $type])->build());
        // 初始化表单规则配置
        $ruleConfig = [];
        // 如果accessKey未配置，则添加accessKey和secretKey的输入字段
        if (!$config[$accessKey]) {
            $ruleConfig = [
                Elm::input('accessKey', 'AccessKey：', $config[$accessKey])->placeholder('请输入accessKey')->required('请输入accessKey'),
                Elm::input('secretKey', 'SecretKey：', $config[$secretKey])->placeholder('请输入secretKey')->required('请输入secretKey'),
            ];
        }
        // 如果是腾讯云存储类型且未配置appid，则添加appid的输入字段
        if ($type == $this->service::STORAGE_TENGXUN && !$config['tengxun_appid']) {
            $ruleConfig[] = Elm::input('tengxun_appid', 'APPID：')->placeholder('请输入APPID')->required('请输入APPID');
        }

        // 定义表单的基本规则，包括空间名称、区域和读写权限的选择
        $rule = [
            Elm::input('name', '空间名称')->required('请输入空间名称')->min(5),
            Elm::select('region', '空间区域')->options($upload->getRegion())->required('请选择空间区域'),
            Elm::radio('acl', '读写权限', 'public-read')->options([
                ['label' => '公共读(推荐)', 'value' => 'public-read'],
                ['label' => '公共读写', 'value' => 'public-read-write'],
            ])->required('请选择读写权限'),
        ];

        // 将之前的规则配置合并到基本规则中
        $rule = array_merge($ruleConfig, $rule);
        // 设置表单的验证规则
        $form->setRule($rule);
        // 设置表单的标题
        $form->setTitle('添加云空间');
        // 返回构建好的表单实例
        return $form;
    }

    /**
     * 编辑域名表单
     *
     * 本函数用于生成编辑存储空间域名的表单。通过传入的ID，获取相应的存储空间信息，
     * 并基于这些信息构建一个包含域名和CDN域名输入字段的表单。
     *
     * @param int $id 存储空间的ID，用于获取特定存储空间的信息。
     * @return \Phalcon\Forms\Form 表单实例，包含用于编辑域名的输入字段和表单提交的URL。
     */
    public function editDomainForm($id)
    {
        // 根据ID获取存储空间的信息
        $storage = $this->dao->get($id);

        // 构建表单提交的URL
        $formAction = Route::buildUrl('systemStorageUpdateDomain', ['id' => $id])->build();
        // 创建表单实例
        $form = Elm::createForm($formAction);

        // 定义表单规则，包含域名和CDN域名的输入字段
        $rule = [
            Elm::input('domain', '空间域名', $storage['domain']),
            Elm::input('cdn', 'cdn域名', $storage['cdn']),
        ];
        // 设置表单的验证规则
        $form->setRule($rule);

        // 设置表单标题
        return $form->setTitle('配置');
    }

    /**
     * 修改空间域名
     * @param int $id
     * @param string $domain
     * @param array $data
     * @return bool
     * @author Qinii
     * @day 2024/3/13
     */
    public function updateDomain(int $id, string $domain, array $data = [])
    {
        $info = $this->dao->get($id);
        if (!$info) {
            throw new ValidateException('数据不存在');
        }
        if ($info->domain != $domain) {
            $info->domain = $domain;
            $upload = UploadService::create($info->type);
            //是否添加过域名不存在需要绑定域名
            $domainList = $upload->getDomian($info->name, $info->region);
            $domainParse = parse_url($domain);
            if (false === $domainParse) {
                throw new ValidateException('域名输入有误');
            }
            if (!in_array($domainParse['host'], $domainList)) {
                //绑定域名到云储存桶
                $res = $upload->bindDomian($info->name, $domain, $info->region);
                if (false === $res) {
                    throw new ValidateException($upload->getError());
                }
            }
            //七牛云需要通过接口获取cname
            if (2 === ((int)$info->type)) {
                $resDomain = $upload->getDomianInfo($domain);
                $info->cname = $resDomain['cname'] ?? '';
            }
            $info->save();
        }
        if ($info->cdn != $data['cdn']) {
            $info->cdn = $data['cdn'];
            $info->save();
        }
        return true;
    }

    /**
     *  选择使用某个存储空间
     * @param $id
     * @param $info
     * @return mixed|\think\response\Json
     * @author Qinii
     * @day 2024/3/13
     */
    public function status($id, $info)
    {
        //设置跨域规则
        try {
            $upload = UploadService::create($info->type);
            $res = $upload->setBucketCors($info->name, $info->region);
            if (false === $res) {
                return app('json')->fail($upload->getError());
            }
        } catch (\Throwable $e) {
        }
        //修改状态
        return Db::transaction(function () use ($id, $info) {
            $this->dao->getSearch(['type' => $info->type])->update(['status' => 0]);
            $info->status = 1;
            $info->save();
        });
    }

    /**
     *  删除存储空间
     * @param int $id
     * @return bool
     * @author Qinii
     * @day 2024/3/13
     */
    public function deleteRegion(int $id)
    {
        $storageInfo = $this->dao->getSearch(['is_del' => 0, 'id' => $id])->find();

        if (!$storageInfo) {
            throw new ValidateException('数据不存在');
        }
        if ($storageInfo->status) {
            throw new ValidateException('存储空间使用中不能删除');
        }

        try {
            $upload = UploadService::create($storageInfo->type);
            $res = $upload->deleteBucket($storageInfo->name, $storageInfo->region);
            if (false === $res) {
                throw new ValidateException($upload->getError());
            }
        } catch (\Throwable $e) {
            throw new ValidateException($e->getMessage());
        }
        $storageInfo->delete();
        return true;
    }


    /**
     *  添加存储空间
     * @param int $type
     * @param array $data
     * @return bool
     * @author Qinii
     * @day 2024/3/13
     */
    public function createRegion(int $type, array $data, array $params)
    {
        $prefix = $this->service::getPrefix($type);
        $access_key = '';
        if ($params && $params['accessKey']){
            $access_key = $params['accessKey'];
            $secretKey = $params['secretKey'];
            unset($params['accessKey'],$params['secretKey']);
            $params[$prefix.'accessKey'] = $access_key;
            $params[$prefix.'secretKey'] = $secretKey;
            app()->make(ConfigValueRepository::class)->setFormData($params,0);
        }
        $access_key = $access_key ?: systemConfig($prefix . 'accessKey');
        $data['type'] = $type;
        $count = $this->dao->getWhereCount(['name' => $data['name'], 'access_key' => $access_key]);
        if ($count) throw new ValidateException('空间名称已存在');
        $upload = UploadService::create($type);
        $res = $upload->createBucket($data['name'], $data['region'], $data['acl']);
        if (false === $res) {
            throw new ValidateException($upload->getError());
        }

        if ($type === $this->service::STORAGE_ALIYUN) {
            $data['region'] = $this->getReagionHost($type, $data['region']);
        }
        $data['domain'] = $this->getDomain($type, $data['name'], $data['region'], systemConfig('tengxun_appid'));
        if ($type !== $this->service::STORAGE_QINIU) {
            $data['cname'] = $data['domain'];
        }
        $data['access_key'] = $access_key;
        if ($type === $this->service::STORAGE_TENGXUN) {
            $data['name'] = $data['name'].'-'.systemConfig('tengxun_appid');
        }
        $this->dao->create($data);
        $this->setDefualtUse($type,$access_key);
        return true;
    }

    /**
     *  同步存储空间
     * @param $type
     * @return bool
     * @author Qinii
     * @day 2024/3/14
     */
    public function synchRegion(int $type)
    {
        $upload = $this->service::create($type);
        $list = $upload->listbuckets();
        $data = [];
        if ($list) {
            $prefix = $this->service::getPrefix($type);
            $access_key = systemConfig($prefix . 'accessKey');
            $data = $this->{$prefix . 'sync_region'}($access_key, $list);
        }
        if ($data) {
            $this->dao->insertAll($data);
            $this->setDefualtUse($type,$access_key);
        }
        return true;
    }

    public function setDefualtUse(int $type,$access_key)
    {
        $config = $this->dao->getSearch([])->where(['type' => $type,'is_del' => 0,'access_key' => $access_key])->order('status DESC,create_time DESC')->find();
        if (!$config['status']) {
            $config->status = 1;
            $config->save();
        }
    }
    /**
     * 同步存储空间-七牛
     * @param $access_key
     * @param $list
     * @return array
     * @author Qinii
     * @day 2024/3/15
     */
    public function qiniu_sync_region($access_key, $list)
    {
        $data = [];
        $namesArray = [];
        foreach ($list as $item) {
            array_push($namesArray, $item['id']);
            if (!$this->dao->getWhereCount(['name' => $item['id'], 'access_key' => $access_key])) {
                $data[] = [
                    'type' => $this->service::STORAGE_QINIU,
                    'access_key' => $access_key,
                    'name' => $item['id'],
                    'region' => $item['region'],
                    'acl' => $item['private'] == 0 ? 'public-read' : 'private',
                    'status' => 0,
                    'domain' => $this->getDomain($this->service::STORAGE_QINIU,$item['id'],''),
                ];
            }
        }
        $removeList = $this->dao->getSearch([])->where([
            'type' => $this->service::STORAGE_QINIU,
            'access_key' => $access_key
        ])->whereNotIn('name', $namesArray)->delete();
        return $data;
    }

    /**
     * 同步存储空间-阿里
     * @param $access_key
     * @param $list
     * @return array
     * @author Qinii
     * @day 2024/3/15
     */
    public function sync_region($access_key, $list)
    {
        $data = [];
        $type = $this->service::STORAGE_ALIYUN;
        $namesArray = [];
        foreach ($list as $item) {
            array_push($namesArray, $item['name']);
            if (!$this->dao->getWhereCount(['name' => $item['name'], 'access_key' => $access_key])) {
                $region = $this->getReagionHost($type, $item['location']);
                $data[] = [
                    'type' => $type,
                    'access_key' => $access_key,
                    'name' => $item['name'],
                    'region' => $region,
                    'acl' => 'public-read',
                    'domain' => $this->getDomain($type, $item['name'], $region),
                    'status' => 0,
                ];
            }
        }
        $removeList = $this->dao->getSearch([])->where([
            'type' => $type,
            'access_key' => $access_key
        ])->whereNotIn('name', $namesArray)->delete();
        return $data;
    }

    /**
     * 同步存储空间-腾讯
     * @param $access_key
     * @param $list
     * @return array
     * @author Qinii
     * @day 2024/3/15
     */
    public function tengxun_sync_region($access_key, $list)
    {
        if (isset($list['Name'])) {
            $newlist = $list;
            $list = [];
            $list[] = $newlist;
        }
        $data = [];
        $namesArray = [];

        foreach ($list as $item) {
            array_push($namesArray, $item['Name']);
            $res = $this->dao->getWhereCount(['name' => $item['Name'], 'access_key' => $access_key]);
            if (!$res) {
                $data[] = [
                    'type' => $this->service::STORAGE_TENGXUN,
                    'access_key' => $access_key,
                    'name' => $item['Name'],
                    'region' => $item['Location'],
                    'acl' => 'public-read',
                    'status' => 0,
                    'domain' => systemConfig('tengxun_appid') ? $this->getDomain($this->service::STORAGE_TENGXUN, $item['Name'], $item['Location']) : '',
                ];
            }
        }
        $removeList = $this->dao->getSearch([])->where([
            'type' => $this->service::STORAGE_TENGXUN,
            'access_key' => $access_key
        ])->whereNotIn('name', $namesArray)->delete();
        return $data;
    }

    /**
     * 同步存储空间-华为
     * @param $access_key
     * @param $list
     * @return array
     * @author Qinii
     * @day 2024/3/15
     */
    public function obs_sync_region($access_key, $list)
    {
        if (isset($list['Name']) && !empty($list['Name'])) {
            $newlist = $list;
            $list = [];
            $list[] = $newlist;
        }
        $data = [];
        $namesArray = [];
        foreach ($list as $item) {
            array_push($namesArray, $item['Name']);
            if (!$this->dao->getWhereCount(['name' => $item['Name'], 'access_key' => $access_key])) {
                $data[] = [
                    'type' => $this->service::STORAGE_HUAWEI,
                    'access_key' => $access_key,
                    'name' => $item['Name'],
                    'region' => $item['Location'],
                    'acl' => 'public-read',
                    'status' => 0,
                    'domain' => $this->getDomain($this->service::STORAGE_HUAWEI, $item['Name'], $item['Location']),
                ];
            }
        }
        $removeList = $this->dao->getSearch([])->where([
            'type' => $this->service::STORAGE_HUAWEI,
            'access_key' => $access_key
        ])->whereNotIn('name', $namesArray)->delete();
        return $data;
    }

    /**
     * 同步存储空间-京东
     * @param $access_key
     * @param $list
     * @return array
     * @author Qinii
     * @day 2024/3/15
     */
    public function jdoss_sync_region($access_key, $list)
    {
        $list = $list['Buckets'];
        $data = [];
        $namesArray = [];
        $location = explode('.', $list['@metadata']['effectiveUri'])[1] ?? 'cn-north-1';
        foreach ($list as $item) {
            array_push($namesArray, $item['Name']);
            if (!$this->dao->getWhereCount(['name' => $item['Name'], 'access_key' => $access_key])) {
                $data[] = [
                    'type' => $this->service::STORAGE_JINGDONG,
                    'access_key' => $access_key,
                    'name' => $item['Name'],
                    'region' => $location,
                    'acl' => 'public-read',
                    'status' => 0,
                    'domain' => $this->getDomain($this->service::STORAGE_JINGDONG, $item['Name'], $location),
                ];
            }
        }

        $removeList = $this->dao->getSearch([])->where([
            'type' => $this->service::STORAGE_JINGDONG,
            'access_key' => $access_key
        ])->whereNotIn('name', $namesArray)->delete();
        return $data;
    }

    /**
     * 同步存储空间-天翼
     * @param $access_key
     * @param $list
     * @return array
     * @author Qinii
     * @day 2024/3/15
     */
    public function ctoss_sync_region($access_key, $list)
    {
        if (isset($list['Name'])) {
            $newlist = $list;
            $list = [];
            $list[] = $newlist;
        }
        $namesArray = [];
        $data = [];
        foreach ($list as $item) {
            array_push($namesArray, $item['Name']);
            if (!$this->dao->getWhereCount(['name' => $item['Name'], 'access_key' => $access_key])) {
                $data[] = [
                    'type' => $this->service::STORAGE_TIANYI,
                    'access_key' => $access_key,
                    'name' => $item['Name'],
                    'region' => $item['Location'],
                    'acl' => 'public-read',
                    'status' => 0,
                    'domain' => $this->getDomain($this->service::STORAGE_TIANYI, $item['Name'], $item['Location']),
                ];
            }
        }

        $removeList = $this->dao->getSearch([])->where([
            'type' => $this->service::STORAGE_JINGDONG,
            'access_key' => $access_key
        ])->whereNotIn('name', $namesArray)->delete();
        return $data;
    }

    /**
     * 同步存储空间-UC
     * @param $access_key
     * @param $list
     * @return bool
     * @author Qinii
     * @day 2024/3/15
     */
    public function uc_sync_region($access_key, $list)
    {
        $data = [];
        $namesArray = [];
        if($list && !empty($list)){
            foreach ($list as $item){
                array_push($namesArray, $item['BucketName']);
                if (!$this->dao->getWhereCount(['name' => $item['BucketName'], 'access_key' => $access_key])) {
                    $data[] = [
                        'type' => $this->service::STORAGE_UCLOUD,
                        'access_key' => $access_key,
                        'name' => $item['BucketName'],
                        'region' => $item['Region'],
                        'acl' => $item['Type'],
                        'status' => 0,
                        'domain' => $this->getDomain($this->service::STORAGE_UCLOUD, $item['BucketName'], $item['Region']),
                    ];
                }

            }
        }
        $removeList = $this->dao->getSearch([])->where([
            'type' => $this->service::STORAGE_UCLOUD,
            'access_key' => $access_key
        ])->whereNotIn('name', $namesArray)->delete();
        return $data;
    }

    /**
     * 根据存储服务类型和提供的信息构造存储服务的域名。
     *
     * 本函数用于根据不同的存储服务类型（如阿里云、腾讯云等），构造对应服务的域名。
     * 通过传入存储服务的类型、名称、区域和可选的应用程序ID，生成用于访问存储服务的URL。
     *
     * @param int $type 存储服务的类型，对应于服务常量中的定义（如ALIYUN、TENGXUN等）。
     * @param string $name 存储服务的名称，用于构成域名的一部分。
     * @param string $reagion 存储服务所在的区域，用于构成域名的一部分。
     * @param string $appid 应用程序的ID，对于某些存储服务（如腾讯云），可以用于区分不同的应用程序实例。
     * @return string 返回构造的存储服务域名。
     */
    public function getDomain(int $type, string $name, string $reagion, string $appid = '')
    {
        $domainName = '';
        // 根据存储服务类型选择不同的域名构造方式
        switch ($type) {
            case $this->service::STORAGE_QINIU:
                $domianList = $this->service::create($this->service::STORAGE_QINIU)->getDomian($name);
                $domainName = $domianList[count($domianList) - 1] ?? '';
                break;
            case  $this->service::STORAGE_ALIYUN:
                // 阿里云对象存储域名格式
                $domainName = 'https://' . $name . '.' . $reagion;
                break;
            case $this->service::STORAGE_TENGXUN:
                // 腾讯云对象存储域名格式，支持appid作为域名的一部分
                $domainName = 'https://' . $name . ($appid ? '-' . $appid : '') . '.cos.' . $reagion . '.myqcloud.com';
                break;
            case  $this->service::STORAGE_JINGDONG:
                // 京东云对象存储域名格式
                $domainName = 'https://' . $name . '.s3.' . $reagion . '.jdcloud-oss.com';
                break;
            case  $this->service::STORAGE_HUAWEI:
                // 华为云对象存储域名格式
                $domainName = 'https://' . $name . '.obs.' . $reagion . '.myhuaweicloud.com';
                break;
            case  $this->service::STORAGE_TIANYI:
                // 天翼云对象存储域名格式
                $domainName = 'https://' . $name . '.obs.' . $reagion . '.ctyun.cn';
                break;
            case  $this->service::STORAGE_UCLOUD:
                // 优刻得对象存储域名格式
                $domainName = 'https://' . $name .'.' . $reagion .'.ufileos.com';
                break;

        }
        return $domainName;
    }

    /**
     * 根据上传类型和区域名称获取对应的区域主机地址。
     *
     * 本函数旨在通过上传服务获取特定类型上传所对应的区域主机列表，
     * 然后在这些列表中查找包含指定区域名称的主机地址，最后返回该地址。
     * 如果找不到匹配的主机地址，则返回空字符串。
     *
     * @param int $type 上传类型，用于确定上传服务和对应的区域列表。
     * @param string $reagion 指定的区域名称，用于在区域列表中查找匹配的主机地址。
     * @return string 匹配的主机地址，如果找不到则返回空字符串。
     */
    public function getReagionHost(int $type, string $reagion)
    {
        // 创建上传服务实例，参数$type用于指定上传类型。
        $upload = UploadService::create($type);
        // 通过上传服务实例获取区域列表。
        $reagionList = $upload->getRegion();
        // 遍历区域列表，查找包含指定区域名称的主机地址。
        foreach ($reagionList as $item) {
            // 如果当前项的值包含指定的区域名称，则返回该主机地址。
            if (strstr($item['value'], $reagion) !== false) {
                return $item['value'];
            }
        }
        // 如果遍历完毕没有找到匹配的主机地址，则返回空字符串。
        return '';
    }

    /**
     * 获取指定类型的域名列表
     *
     * 本函数用于查询并返回指定上传类型对应的域名列表。如果未指定类型，则根据系统配置的默认上传类型获取。
     * 主要用于支持多域名配置下的文件上传服务，使得可以灵活切换不同的域名进行文件访问。
     *
     * @param int|null $type 上传类型标识，null表示使用系统默认上传类型。
     * @return array 域名列表，如果没有找到任何域名则返回空数组。
     */
    public function domains(?int $type)
    {
        // 如果未指定上传类型，则使用系统配置的默认上传类型
        $type = $type ?: (systemConfig('upload_type') ?: 1);

        // 查询数据库，获取指定类型且未被删除的域名列表
        $domain = $this->dao->getSearch([])->where(['type' => $type,'is_del' => 0])->where('domain','>',0)->column('domain');

        // 返回查询结果，如果结果为空则返回空数组
        return $domain ?: [];
    }
}
