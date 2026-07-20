<?php

namespace crmeb\services;

use app\common\repositories\system\config\ConfigValueRepository;
use http\Exception\InvalidArgumentException;
use Swoole\Coroutine\System;
use think\Exception;
use think\exception\ValidateException;
use think\facade\Config;
use think\facade\Db;
use think\facade\Log;

class ImageHostService
{
    private static $_instance;
    //原始路径
    public $origin;
    public $origin_json;
    //替换路径
    public $replace;
    public $replace_json;
    //数据库前缀
    public $db_prefix;
    //数据库名称
    public $success = true;
    public $limit = 10000;
    //需要更换的字段名
    public $fields_str = [
        'image',
        'cover_img',
        'share_img',
        'feeds_img',
        'image_input',
        'pic',
        'mer_avatar',
        'mini_banner',
        'mer_banner',
        'qrcode_url',
        'avatar_img',
        'avatar',
        'attachment_src',
        'brokerage_icon',
        'pics',
        'slider_image',
        'extract_pic',
        'cover_image',
    ];

    // 需要处理的json格式字段
    public $fields_json = [
        'content',
        'images',
        'value',
        'info',
    ];

    //需要处理的数据表名称
    public $range = [
        "article",
        "article_content",
        "broadcast_goods",
        "broadcast_room",
        "community",
        "community_topic",
        "diy",
        "guarantee",
        "member_interests",
        "merchant",
        "merchant_applyments",
        "merchant_intention",
        "routine_qrcode",
        "store_activity",
        "store_category",
        "store_product",
        "store_product_attr_value",
        "store_product_content",
        "store_product_reply",
        "store_service",
        "store_spu",
        "system_attachment",
        "system_config_value",
        "system_group_data",
        "user",
        "user_brokerage",
        "user_extract",
        "wechat_qrcode",
    ];

    public function __construct()
    {
        $this->db_prefix = Config::get('database.connections.' . Config::get('database.default') . '.prefix');
        //$this->db_tables_name = Config::get('database.connections.' . Config::get('database.default') . '.database');
    }

    public static function getInstance()
    {
        if (!self::$_instance instanceof self) {
            self::$_instance = new self;
        }
        return self::$_instance;
    }

    /**
     *  获取需要处理的字段
     * @param $type
     * @return mixed
     * @author Qinii
     * @day 2024/3/8
     */
    public function getFields($type = 'str')
    {
        $type = in_array($type,['str','json']) ? $type : 'str';
        $field = "fields_{$type}";
        return  $this->{$field} ;
    }

    /**
     *  追加处理字段
     * @param array $fields
     * @param $type
     * @author Qinii
     * @day 2024/3/8
     */
    public function setFields(array $fields,$type = 'str')
    {
        $type = in_array($type,['str','json']) ? $type :  'str';
        $field = "fields_{$type}";
        if (!empty($fields)) {
            $this->{$field} = array_unique(array_merge($this->{$fields}, $fields));
        }
    }

    /**
     *  获取处理表名
     * @return string[]
     * @author Qinii
     * @day 2024/3/8
     */
    public function getRange()
    {
        return $this->range;
    }

    /**
     *  追加处理表名
     * @param array $ranges
     * @author Qinii
     * @day 2024/3/8
     */
    public function setRange(array $ranges)
    {
        if (!empty($fields)) {
            $this->range = array_unique(array_merge($this->range, $ranges));
        }
    }

    /**
     *  组合好需要更换的域名
     * @param $origin
     * @param $replace
     * @author Qinii
     * @day 2024/3/7
     */
    public function getHost($origin,$replace)
    {
        try {
            $origin_parse_url = parse_url($origin);
            // 解析站点 URL 的协议 http / https
            $originScheme = $origin_parse_url['scheme'];
            $this->origin = $origin_parse_url['scheme'].'://'.$origin_parse_url['host'];
            // 将站点 URL 中的协议替换为 JSON 格式
            $this->origin_json = str_replace($originScheme . '://', $originScheme . ':\\\/\\\/', $this->origin);

            $replace_parse_url = parse_url($replace);
            // 获取当前 URL 的协议
            $replaceScheme = $replace_parse_url['scheme'];
            $this->replace = $replace_parse_url['scheme'].'://'.$replace_parse_url['host'];
            // 将当前 URL 中的协议替换为 JSON 格式
            $this->replace_json = str_replace($replaceScheme . '://', $replaceScheme . ':\\\/\\\/', $this->replace);
        }catch (Exception $exception) {
            throw new ValidateException('请输入正确的域名');
        }
    }

    protected function getSql($db)
    {
        $table = $this->db_prefix.$db;
        $db_data = Db::table($table)->where([])->limit(1)->select();
        $sql = '';
        if ($db_data && isset($db_data[0])) {
            $db_info = $db_data[0];
            $sql_ = $sql = "UPDATE `{$table}` SET ";
            foreach ($this->getFields() as $field) {
                if (isset($db_info[$field])){
                    $sql .= "`$field` = replace(`$field` ,'$this->origin','$this->replace'),";
                }
            }
            foreach ($this->getFields('json') as $field_) {
                if (isset($db_info[$field_])){
                    $sql .= "`$field_` = replace(`$field_` ,'$this->origin_json','$this->replace_json'),";
                }
            }
            $sql = $sql == $sql_ ? '' : $sql;
        }
        return [$sql, $table];
    }

    public function getDb()
    {
//        $db_key = 'Tables_in_'.$this->db_tables_name;
//        $db = Db::query('show tables');
//        $data = array_column($db,$db_key);
    }

    /**
     *  执行替换操作
     * @param $sql
     * @param string $table
     * @author Qinii
     * @day 2024/3/8
     */
    public function originToReplace($sql, string $table)
    {
        if (!$sql) return true;
        $sql = trim($sql,',');
        return Db::transaction(function () use ($sql,$table) {
            try {
                Db::execute($sql);
                return true;
            } catch (\Throwable $e) {
                $error = [
                    '更换图片地址错误提示【'.$table.'】'.$e->getMessage(),
                    '更换图片地址错误SQL'.$sql
                ];
                Log::error(var_export($error,true));
                $this->success = false;
                return $error;
            }
        });
    }

    /**
     *  运行处理sql
     * @param string $origin
     * @param string $replac
     * @author Qinii
     * @day 2024/3/8
     */
    public function execute(string $origin, string $replace)
    {
        $this->getHost($origin,$replace);
        foreach ($this->getRange() as $db) {
            [$sql, $table] = $this->getSql($db);
            $res = $this->originToReplace($sql, $table);
        }
        app()->make(ConfigValueRepository::class)->syncConfig();
        return $this->success;
    }
}
