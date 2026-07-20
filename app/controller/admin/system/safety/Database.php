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

namespace app\controller\admin\system\safety;

use crmeb\exceptions\UploadFailException;
use crmeb\services\MysqlBackupService;
use think\App;
use crmeb\basic\BaseController;
use think\facade\Db;
use think\facade\Env;

/**
 * 数据库备份
 */
class Database extends BaseController
{

    protected $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $config = array(
            'level' => 5,//数据库备份卷大小
            'compress' => 1,//数据库备份文件是否启用压缩 0不压缩 1 压缩
        );
        $this->service = new MysqlBackupService($config);
    }

    /**
     * 获取数据列表
     *
     * 本函数用于封装服务层的数据列表获取操作，并通过JSON格式返回成功响应。
     * 主要用于API接口开发，提供数据列表的获取功能，响应格式化由app('json')工具类处理。
     *
     * @return \Illuminate\Http\JsonResponse 返回一个JSON格式的成功响应，包含数据列表。
     */
    public function lst()
    {
        // 调用app('json')的success方法返回数据列表的成功响应
        return app('json')->success($this->service->dataList());
    }

    /**
     * 获取文件列表信息
     *
     * 本函数通过调用服务层的方法获取文件列表，然后对这些文件信息进行加工，
     * 最后以特定格式返回给前端。加工过程中，文件信息被重新组织以包含更多易读的细节，
     * 如文件大小显示为带'B'的字符串，以及为方便后续处理，添加了'backtime'字段。
     *
     * @return \Illuminate\Http\JsonResponse 文件列表的JSON响应
     */
    public function fileList()
    {
        // 从服务层获取文件列表
        $files = $this->service->fileList();
        $data = [];

        // 遍历文件列表，重新组织数据格式
        foreach ($files as $key => $t) {
            $data[] = [
                'filename' => $t['filename'], // 文件名
                'part' => $t['part'], // 文件部分（如果适用）
                'size' => $t['size'] . 'B', // 文件大小，单位为字节
                'compress' => $t['compress'], // 文件压缩状态
                'backtime' => $key, // 原始数组的键，用作文件的备份时间戳
                'time' => $t['time'], // 文件的时间戳
            ];
        }

        // 返回加工后的文件列表数据
        // krsort($data);//根据时间降序
        return app('json')->success($data);
    }

    /**
     * 获取数据库表的列详情
     * 本函数通过查询information_schema.columns表，来获取指定数据库表的列信息。
     * 这些信息包括列名、列类型、默认值、是否可为空、额外信息（如自增标识）以及列注释。
     *
     * @param string $name 表名
     * @return json 返回查询结果的JSON格式
     */
    public function detail($name)
    {
        // 从环境变量中获取当前使用的数据库名称
        $database = Env::get("database.database");

        // 执行SQL查询，获取指定表的列详细信息
        // SQL语句中拼接了表名和数据库名，以确保查询的准确性
        $result = Db::query("select COLUMN_NAME,COLUMN_TYPE,COLUMN_DEFAULT,IS_NULLABLE,EXTRA,COLUMN_COMMENT from information_schema.columns where table_name = '" . $name . "' and table_schema = '" . $database . "'");

        // 使用JSON工具类将查询结果包装成成功响应的JSON格式返回
        return app('json')->success($result);
    }

    /**
     * 备份指定的数据库表。
     *
     * 此方法接受一个表名的数组，逐个对表进行备份。如果表名不是数组，
     * 则认为是一个错误的情况。对于每个表，首先检查该表是否存在，
     * 如果不存在，则返回错误信息。如果表存在，则尝试进行备份。
     * 如果备份失败，将失败的表名记录下来。最后，根据备份是否全部成功，
     * 返回相应的成功或失败信息。
     *
     * @param array $name 表名的数组。
     * @return \Illuminate\Http\JsonResponse 返回JSON格式的响应，包含备份结果的信息。
     */
    public function backups($name)
    {
        // 初始化用于存储备份失败的表名的变量
        $data = [];
        // 检查$name是否为数组，如果是，逐个处理每个表名
        if (is_array($name)) {
            foreach ($name as $item) {
                // 检查表是否存在，如果不存在，返回错误信息
                if (!$this->detail($item))
                    return app('json')->fail('不存在的表名');
                // 尝试备份表，如果备份失败且不是因为已存在，则将表名添加到$data
                $res = $this->service->backup($item, 0);
                if ($res == false && $res != 0) {
                    $data .= $item . '|';
                }
            }
        }
        // 如果$data不为空，说明有备份失败的表，返回失败信息
        if ($data) return app('json')->fail('备份失败' . $data);
        // 如果$data为空，说明所有备份都成功，返回成功信息
        return app('json')->success('备份成功');
    }

    /**
     * 执行优化操作
     *
     * 本函数旨在调用服务层的优化功能，并返回一个表示优化成功的结果消息。
     * 通过传递优化目标的名称，可以对特定的优化任务进行触发。
     *
     * @param string $name 优化目标的名称。这是调用优化功能所必需的参数，用于指定要执行的优化任务。
     * @return Json 响应优化成功的消息。使用应用的JSON工具类生成成功消息的JSON响应。
     */
    public function optimize($name)
    {
        // 调用服务层的optimize方法，传入优化目标的名称，执行具体的优化操作。
        $this->service->optimize($name);

        // 返回一个表示优化成功的结果消息，使用应用的JSON工具类构建响应。
        return app('json')->success('优化成功');
    }

    /**
     * 修复指定名称的物品
     *
     * 本函数通过遍历传入的物品名称列表，并调用service层的repair方法，逐一尝试修复这些物品。
     * 它的设计目的是用于处理一批物品的修复操作，而不是单个物品的修复。
     *
     * @param array $name 物品名称的数组。每个元素代表一个需要修复的物品的名称。
     * @return string 返回一个表示修复成功的信息。使用了app('json')->success方法来生成标准化的成功响应。
     */
    public function repair($name)
    {
        // 遍历物品名称数组，对每个物品调用service层的修复方法
        foreach ($name as $item) {
            $this->service->repair($item);
        }
        // 返回修复成功的消息
        return app('json')->success('修复成功');
    }

    /**
     * 下载文件的方法
     *
     * 本方法旨在处理文件下载请求。它通过接收请求中的参数，获取对应的文件信息，
     * 并触发文件下载。如果下载过程中出现错误，将捕获异常并返回错误信息。
     *
     * @return mixed 返回文件下载流或错误信息
     */
    public function downloadFile()
    {
        try {
            // 从请求中获取文件名参数，并转换为整型
            $time = intval($this->request->param('feilname'));

            // 通过服务层获取指定时间的文件信息
            $file = $this->service->getFile('time', $time);

            // 解析文件信息，获取文件名
            $fileName = $file[0];

            // 触发文件下载
            return download($fileName, $time);
        } catch (UploadFailException $e) {
            // 捕获上传失败异常，返回下载失败的响应
            return app('json')->fail('下载失败');
        }
    }

    /**
     * 删除文件
     *
     * 本函数用于处理文件删除请求。它首先从请求中获取文件名的参数，
     * 然后调用服务层的方法删除该文件。如果删除成功，它将返回一个表示成功的JSON响应。
     *
     * @return \think\Response 成功删除文件后的JSON响应
     */
    public function deleteFile()
    {
        // 将请求中的文件名参数转换为整数
        $feilname = intval($this->request->param('feilname'));
        // 调用服务层的方法删除文件
        $files = $this->service->delFile($feilname);
        // 返回一个表示删除成功的JSON响应
        return app('json')->success('删除成功');
    }


}
