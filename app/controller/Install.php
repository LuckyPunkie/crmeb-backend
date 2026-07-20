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


namespace app\controller;

use crmeb\basic\BaseController;
use Redis;
use think\App;
use think\Exception;
use think\exception\ValidateException;
use think\facade\Config;
use think\facade\View;
use think\Request;
use think\Response;
use Throwable;
use ZipArchive;
use ZipStream\ZipStream;

class Install //extends BaseController
{

    /**
     * Request实例
     * @var Request
     */
    protected $request;

    /**
     * 应用实例
     * @var App
     */
    protected $app;
    /**
     * sql文件
     * @var App
     */
    public $sqlFile = 'crmeb_merchant.sql';
    /**
     * 配置文件
     * @var App
     */
    public $configFile = '.env';
    public $env;
    public $installHost;
    public $_url;
    const DEMO_SQL_TABLE = [
        'eb_system_admin',
        'eb_system_menu',
        'eb_system_role',
        'eb_system_group',
        'eb_system_group_data',
        'eb_express',
        'eb_system_config',
        'eb_system_config_classify',
        'eb_system_config_value',
        'eb_template_message',
        'system_notice_config',
        'eb_city_area',
        'eb_diy',
        'eb_page_category',
        'eb_page_link',
        'eb_system_notice_config',
        'eb_member_interests',
        'eb_user_brokerage'
    ];

    public function __construct(App $app)
    {
        if (file_exists(__DIR__ . '/../../install/install.lock')) {
            throw new ValidateException('你已经安装过该系统，如果想重新安装，请先删除install目录下的 install.lock 文件，然后再安装。');
        }
        if (!file_exists(__DIR__ . '/../../install/' . $this->sqlFile)) {
            throw new ValidateException('缺少数据库文件："install/' . $this->sqlFile . '"');
        }
        if (!file_exists(__DIR__ . '/../../install/' . $this->configFile)) {
            throw new ValidateException('缺少配置文件："install/' . $this->configFile . '"');
        }
        $this->app = $app;
        $this->request = $this->app->request;
        $this->env = [];
        $this->installHost = $this->request->domain();
        $hostArr = explode('//', $this->installHost);
        $this->_url = $hostArr[1];
    }

    /**
     * 1 开始安装
     * @return string
     * @author Qinii
     * @day 2020-07-16
     */
    public function begin()
    {
        return View::fetch('/install/step1');
    }

    /**
     * 2 环境检测
     * @return string
     * @author Qinii
     * @day 2020-07-16
     */
    public function environment()
    {
        $phpv = @ phpversion();
        $os = PHP_OS;
        $tmp = function_exists('gd_info') ? gd_info() : array();
        $max_execution_time = ini_get('max_execution_time');
        $allow_reference = (ini_get('allow_call_time_pass_reference') ? '<font color=green>[√]On</font>' : '<font color=red>[×]Off</font>');
        $allow_url_fopen = (ini_get('allow_url_fopen') ? '<font color=green>[√]On</font>' : '<font color=red>[×]Off</font>');


        $err = 0;
        $passOne = $passTwo = 'yes';
        if (!ini_get('safe_mode')) {
            $safe_mode = '<img class="yes" src="images/install/yes.png" alt="对"> 启用';
        } else {
            $safe_mode = '<img class="no" src="images/install/warring.png" alt="错"> 禁用';
            $err++;
            $passOne = 'no';
        }
        if (empty($tmp['GD Version'])) {
            $gd = '<img class="no" src="images/install/warring.png" alt="错"> 禁用';
            $err++;
            $passOne = 'no';
        } else {
            $gd = '<img class="yes" src="images/install/yes.png" alt="对"> ' . $tmp['GD Version'];
        }
        if (function_exists('mysqli_connect')) {
            $mysql = '<img class="yes" src="images/install/yes.png" alt="对"> 已安装';
        } else {
            $mysql = '<img class="no" src="images/install/warring.png" alt="错"> 请安装mysqli扩展';
            $err++;
            $passOne = 'no';
        }
        if (ini_get('file_uploads')) {
            $uploadSize = '<img class="yes" src="/install/images/install/yes.png" alt="对"> ' . ini_get('upload_max_filesize');
        } else {
            $uploadSize = '<img class="no" src="images/install/warring.png" alt="错"> 禁止上传';
            $err++;
            $passOne = 'no';
        }
        if (function_exists('session_start')) {
            $session = '<img class="yes" src="/install/images/install/yes.png" alt="对"> 启用';
        } else {
            $session = '<img class="no" src="images/install/warring.png" alt="错"> 禁用';
            $err++;
            $passOne = 'no';
        }
        if (extension_loaded('zip')) {
            $zip = '<img class="yes" src="/install/images/install/yes.png" alt="对"> 启用';
        } else {
            $zip = '<img class="no" src="images/install/warring.png" alt="错"> 禁用';
            $err++;
            $passOne = 'no';
        }
        if (extension_loaded(('redis'))) {
            $redis = '<img class="yes" src="/install/images/install/yes.png" alt="对"> 启用';
        } else {
            $redis = '<img class="no" src="images/install/warring.png" alt="错"> 禁用';
            $err++;
            $passOne = 'no';
        }
        if (extension_loaded('swoole')) {
            $swoole = '<img class="yes" src="/install/images/install/yes.png" alt="对"> 启用';
        } else {
            $swoole = '<img class="no" src="images/install/warring.png" alt="错"> 禁用';
            $err++;
            $passOne = 'no';
        }
        if (extension_loaded('swoole_loader')) {
            $swooleCompiler = '<img class="yes" src="/install/images/install/yes.png" alt="对"> 启用';
        } else {
            $swooleCompiler = '<img class="no" src="images/install/warring.png" alt="错"><a href="/install/loader" target="_blank" style="color: #ff0000;"> 请安装swoole_loader扩展</a>';
            $err++;
            $passOne = 'no';
        }
        if (function_exists('curl_init')) {
            $curl = '<img class="yes" src="/install/images/install/yes.png" alt="对"> 启用';
        } else {
            $curl = '<img class="no" src="images/install/warring.png" alt="错"> 禁用';
            $err++;
            $passOne = 'no';
        }
        if (function_exists('bcadd')) {
            $bcmath = '<img class="yes" src="/install/images/install/yes.png" alt="对"> 启用';
        } else {
            $bcmath = '<img class="no" src="images/install/warring.png" alt="错"> 禁用';
            $err++;
            $passOne = 'no';
        }
        if (function_exists('openssl_encrypt')) {
            $openssl = '<img class="yes" src="/install/images/install/yes.png" alt="对"> 启用';
        } else {
            $openssl = '<img class="no" src="images/install/warring.png" alt="错"> 禁用';
            $err++;
            $passOne = 'no';
        }
        if (function_exists('finfo_open')) {
            $finfo_open = '<img class="yes" src="/install/images/install/yes.png" alt="对"> 启用';
        } else {
            $finfo_open = '<img class="no" src="images/install/warring.png" alt="错"> 禁用';
            $err++;
            $passOne = 'no';
        }
        $folder = array(
            'public/install',
            'public/uploads',
            'runtime',
            '.env',
        );
        //必须开启函数
        if (function_exists('file_put_contents')) {
            $file_put_contents = '<img class="yes" src="/install/images/install/yes.png" alt="对"> 启用';
        } else {
            $file_put_contents = '<img class="no" src="images/install/warring.png" alt="错"> 禁用';
            $err++;
            $passTwo = 'no';
        }
        if (function_exists('imagettftext')) {
            $imagettftext = '<img class="yes" src="/install/images/install/yes.png" alt="对"> 启用';
        } else {
            $imagettftext = '<img class="no" src="images/install/warring.png" alt="错"> 禁用';
            $err++;
            $passTwo = 'no';
        }
        if (function_exists('pcntl_alarm')) {
            $pcntl_alarm = '<img class="yes" src="/install/images/install/yes.png" alt="对"> 启用';
        } else {
            $pcntl_alarm = '<img class="no" src="images/install/warring.png" alt="错"> 禁用';
            $err++;
            $passTwo = 'no';
        }
        if (function_exists('proc_open')) {
            $proc_open = '<img class="yes" src="/install/images/install/yes.png" alt="对"> 启用';
        } else {
            $proc_open = '<img class="no" src="images/install/warring.png" alt="错"> 禁用';
            $err++;
            $passTwo = 'no';
        }
        if (function_exists('pcntl_signal')) {
            $pcntl_signal = '<img class="yes" src="/install/images/install/yes.png" alt="对"> 启用';
        } else {
            $pcntl_signal = '<img class="no" src="images/install/warring.png" alt="错"> 禁用';
            $err++;
            $passTwo = 'no';
        }
        View::assign([
            'max_execution_time' => $max_execution_time,
            'allow_reference' => $allow_reference,
            'swooleCompiler' => $swooleCompiler,
            'phpv' => $phpv,
            'allow_url_fopen' => $allow_url_fopen,
            'safe_mode' => $safe_mode,
            'gd' => $gd,
            'mysql' => $mysql,
            'uploadSize' => $uploadSize,
            'redis' => $redis,
            'session' => $session,
            'swoole' => $swoole,
            'curl' => $curl,
            'bcmath' => $bcmath,
            'openssl' => $openssl,
            'finfo_open' => $finfo_open,
            'file_put_contents' => $file_put_contents,
            'imagettftext' => $imagettftext,
            'folder' => $folder,
            'zip' => $zip,
            'proc_open' => $proc_open,
            'pcntl_alarm' => $pcntl_alarm,
            'pcntl_signal' => $pcntl_signal,
            'passOne' => $passOne,
            'passTwo' => $passTwo,
        ]);
        return View::fetch('/install/step2', ['err' => $err]);
    }

    /**
     * 3 数据库填写表单
     * @return string
     * @author Qinii
     * @day 2020-07-15
     */
    public function databases()
    {
        return View::fetch('/install/step3');
    }

    /**
     * 4 安装数据库
     * @return string
     * @author Qinii
     * @day 2020-07-16
     */
    public function create()
    {
        $data = $this->request->params(['dbhost', 'dbport', 'dbname', 'dbuser', 'dbpw', ['dbprefix', 'eb_'], 'manager', 'manager_pwd', ['rbhost', '127.0.0.1'], ['rbport', 6379], 'rbpw', ['rbselect', 0], 'demo']);
        $this->checkInstallData($data);
        $mysql = $this->checkDatabsaces($data);
        if ($mysql !== 1) throw new ValidateException('数据库链接失败' . $mysql);
        return View::fetch('/install/step4', ['data' => $data]);
    }

    /**
     * 5 安装完成
     * @return string
     * @author Qinii
     * @day 2020-07-16
     */
    public function end()
    {
        $ip = $this->get_client_ip();
        $server = $this->request->server();
        $host = $server['HTTP_HOST'];
        $version = get_code_version('未知');
        $this->installlog();
        @touch(__DIR__ . '/../../install/install.lock');
        $this->unzip();
        return View::fetch('/install/step5', [
            'host' => $host,
            'ip' => $ip,
            'version' => $version,
            'merchant' => Config::get('admin.merchant_prefix'),
            'system' => Config::get('admin.admin_prefix'),
            'uid' => Config::get('notice.sms.ADMIN_ORDER_UID'),
        ]);
    }

    /**
     * 链接数据库 读取sql
     * @param $n
     * @return array
     * @author Qinii
     * @day 2020-07-16
     */
    public function perform($n)
    {
        $data = $this->request->param();
        $counts = 0;
        try {
            $this->checkInstallData($data);
            $dbName = strtolower(trim($data['dbname']));
            $conn = @mysqli_connect($data['dbhost'], $data['dbuser'], $data['dbpw'], NULL, $data['dbport']);
            if (mysqli_connect_errno()) {
                throw new ValidateException("连接数据库失败!" . mysqli_connect_error());
            }
            if (!mysqli_select_db($conn, $dbName)) {
                //创建数据时同时设置编码
                if (!mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS `" . $dbName . "` DEFAULT CHARACTER SET utf8;")) {
                    throw new ValidateException('数据库 ' . $dbName . ' 不存在，也没权限创建新的数据库！');
                }
            }
            mysqli_select_db($conn, $dbName);
            mysqli_query($conn, 'SET NAMES utf8;');
            //读取数据文件
            $sqldata = file_get_contents(__DIR__ . '/../../install/' . $this->sqlFile);
            $sqldata = str_replace('mer1.crmeb.net', $this->_url, $sqldata);
            $sqldata = str_replace('mer.crmeb.net', $this->_url, $sqldata);
            $sqlFormat = $this->sql_split($sqldata, $data['dbprefix']);
            $counts = count($sqlFormat);
            if ($n <= $counts && isset($sqlFormat[$n])) {
                $sql = trim($sqlFormat[$n]);
                $message = $this->install($sql, $conn, $data['dbprefix']);
                $n++;
            } else {
                if ($data['demo'] == '') $this->clear($conn, $data['dbprefix']);
                $this->setConfig($data);

                // 插入订单时间线配置
                $this->insertOrderAuthTimeConfig($conn, $data['dbprefix']);

                $message = $this->setDatabase($conn, $data);
                $n = 999999;
            }
        } catch (Throwable $e) {
            return [
                'n' => -1,
                'count' => $counts,
                'message' => $e->getMessage(),
                'time' => date('Y-m-d H:i:s')
            ];
        }
        return [
            'n' => $n,
            'count' => $counts,
            'msg' => $message,
            'time' => date('Y-m-d H:i:s')
        ];
    }


    /**
     * 执行数据库文件安装
     * @param $sql
     * @param $conn
     * @param $dbPrefix
     * @return string
     * @author Qinii
     * @day 2020-07-16
     */
    public function install($sql, $conn, $dbPrefix)
    {
        // 建表
        if (strstr($sql, 'CREATE TABLE')) {
            preg_match('/CREATE TABLE `eb_([^ ]*)`/is', $sql, $matches);
            if (empty($matches[1])) {
                throw new ValidateException('SQL建表语句格式异常，无法识别表名');
            }
            $table = $dbPrefix . $matches[1];
            if (!$this->isValidIdentifier($table)) {
                throw new ValidateException('数据表名格式错误：' . $table);
            }
            mysqli_query($conn, "DROP TABLE IF EXISTS `{$table}`");
            $sql = str_replace('`eb_', '`' . $dbPrefix, $sql);//替换表前缀
            $ret = mysqli_query($conn, $sql);
            if ($ret) {
                $message = '创建数据表[' . $table . ']完成!';
            } else {
                throw new ValidateException('创建数据表[' . $table . ']失败!失败原因：' . mysqli_error($conn));
            }

        } //插入数据
        else {
            $message = '';
            if (trim($sql) !== '') {
                $sql = str_replace('`eb_', '`' . $dbPrefix, $sql);//替换表前缀
                $ret = mysqli_query($conn, $sql);
                $sql = htmlspecialchars($sql);
                $msg = substr($sql, 0, 30);
                if ($ret) {
                    $message = '执行 [' . $msg . '...]成功!' . date('Y-m-d H:i:s');
                } else {
                    throw new ValidateException('执行[' . $msg . '..]失败!失败原因：' . mysqli_error($conn));
                }
            }
        }
        return $message;
    }

    /**
     * 清除测试数据
     * @param $conn
     * @param $dbPrefix
     * @author Qinii
     * @day 2020-07-16
     */
    public function clear($conn, $dbPrefix)
    {
        $result = mysqli_query($conn, "show tables");
        $tables = mysqli_fetch_all($result);//参数MYSQL_ASSOC、MYSQLI_NUM、MYSQLI_BOTH规定产生数组类型
        $bl_table = self::DEMO_SQL_TABLE;
        foreach ($bl_table as $k => $v) {
            $bl_table[$k] = str_replace('eb_', $dbPrefix, $v);
        }

        foreach ($tables as $key => $val) {
            if (!in_array($val[0], $bl_table)) {
                mysqli_query($conn, "truncate table `" . str_replace('`', '``', $val[0]) . "`");
            }
        }
    }

    /**
     * 创建.env文件
     * @param $data
     * @author Qinii
     * @day 2020-07-16
     */
    public function setConfig($data)
    {
        //读取配置文件，并替换真实配置数据1
        $strConfig = file_get_contents(__DIR__ . '/../../install/' . $this->configFile);
        //'dbhost', 'dbport', 'dbname', 'dbuser', 'dbpw', 'dbprefix', 'manager', 'manager_pwd', ['rbhost', '127.0.0.1'], ['rbport', 6379], 'rbpw', ['rbselect', 0]
        $strConfig = str_replace('#DB_HOST#', $data['dbhost'], $strConfig);
        $strConfig = str_replace('#DB_NAME#', $data['dbname'], $strConfig);
        $strConfig = str_replace('#DB_USER#', $data['dbuser'], $strConfig);
        $strConfig = str_replace('#DB_PWD#', $data['dbpw'], $strConfig);
        $strConfig = str_replace('#DB_PORT#', $data['dbport'], $strConfig);
        $strConfig = str_replace('#DB_PREFIX#', $data['dbprefix'], $strConfig);
        $strConfig = str_replace('#DB_CHARSET#', 'utf8', $strConfig);
        //redis数据库信息
        $strConfig = str_replace('#RB_HOST#', $data['rbhost'], $strConfig);
        $strConfig = str_replace('#RB_PORT#', $data['rbport'], $strConfig);
        $strConfig = str_replace('#RB_PWD#', $data['rbpw'], $strConfig);
        $strConfig = str_replace('#RB_SELECT#', $data['rbselect'], $strConfig);
        $strConfig = str_replace('#APP_KEY#', md5(time() . random_int(10000000, 99999999)), $strConfig);
        $envFile = __DIR__ . '/../../.env';
        if (file_put_contents($envFile, $strConfig, LOCK_EX) === false) {
            throw new ValidateException('配置文件写入失败，请检查根目录 .env 文件权限');
        }
        @chmod($envFile, 0644);
    }

    /**
     * 修改后台管理员用户
     * @param $conn
     * @param $data
     * @return string
     * @author Qinii
     * @day 2020-07-16
     */
    public function setDatabase($conn, $data)
    {
        $time = date('Y-m-d H:i:s');
        $ip = $this->get_client_ip();
        $ip = empty($ip) ? "0.0.0.0" : $ip;
        $password = password_hash(trim($data['manager_pwd']), PASSWORD_BCRYPT);
        $adminTable = $data['dbprefix'] . 'system_admin';
        if (!$this->isValidIdentifier($adminTable)) {
            throw new ValidateException('管理员表名格式错误：' . $adminTable);
        }
        mysqli_query($conn, "truncate table `{$adminTable}`");
        $manager = mysqli_real_escape_string($conn, trim($data['manager']));
        $password = mysqli_real_escape_string($conn, $password);
        $ip = mysqli_real_escape_string($conn, $ip);
        $addadminsql = "INSERT INTO `{$adminTable}` (`admin_id`, `account`, `pwd`, `real_name`, `roles`, `last_ip`, `last_time`, `create_time`, `login_count`, `level`, `status`, `is_del`) VALUES
(1, '" . $manager . "', '" . $password . "', '', '1', '" . $ip . "','$time' , '$time', 0, 0, 1, 0)";
        $res = mysqli_query($conn, $addadminsql);
        if ($res) {
            $message = '添加管理员成功，成功写入配置文件，安装完成．';
        } else {
            throw new ValidateException('添加管理员失败，失败原因：' . mysqli_error($conn));
        }
        return $message;
    }

    /**
     * 插入订单时间线配置
     * @param $conn
     * @param string $dbPrefix
     * @return void
     */
    protected function insertOrderAuthTimeConfig($conn, string $dbPrefix)
    {
        $configTable = $dbPrefix . 'system_config';
        $configValueTable = $dbPrefix . 'system_config_value';
        $configKey = 'order_auth_time';

        // 检查配置是否已存在
        $checkSql = "SELECT `config_id` FROM `{$configTable}` WHERE `config_key` = '{$configKey}' LIMIT 1";
        $result = mysqli_query($conn, $checkSql);
        if ($result && mysqli_num_rows($result) > 0) {
            return; // 配置已存在，跳过
        }

        $time = date('Y-m-d H:i:s');

        // 插入配置项
        $insertConfigSql = "INSERT INTO `{$configTable}` (`config_classify_id`, `config_name`, `config_key`, `config_type`, `config_rule`, `config_props`, `required`, `info`, `sort`, `user_type`, `status`, `create_time`, `linked_status`, `linked_id`, `linked_value`) VALUES (78, '订单相关的时间线', '{$configKey}', 'input', '', '', 0, '订单相关的时间线。', 5, 0, 1, '{$time}', 0, 0, 0)";
        mysqli_query($conn, $insertConfigSql);

        // 插入配置值（默认值为空）
        $insertValueSql = "INSERT INTO `{$configValueTable}` (`config_key`, `value`, `mer_id`, `create_time`) VALUES ('{$configKey}', '', 0, '{$time}')";
        mysqli_query($conn, $insertValueSql);
    }

    /**
     * 检测数据库链接是否成功以及版本
     * @param $data
     * @return false|int|string
     * @author Qinii
     * @day 2020-07-16
     */
    public function checkDatabsaces($data)
    {
        $this->checkInstallData($data, false);
        $dbName = strtolower(trim($data['dbname']));
        $conn = @mysqli_connect($data['dbhost'], $data['dbuser'], $data['dbpw'], NULL, $data['dbport']);
        if (mysqli_connect_errno()) return 0;
        $result = mysqli_query($conn, "SELECT @@global.sql_mode");
        $result = $result->fetch_array();
        $version = mysqli_get_server_info($conn);
        if ($version < 5.6) return (json_encode(-4));

        if (strstr($result[0], 'STRICT_TRANS_TABLES') || strstr($result[0], 'STRICT_ALL_TABLES') || strstr($result[0], 'TRADITIONAL') || strstr($result[0], 'ANSI'))
            return ($version < 8.0) ? -1 : -2;

        $result = mysqli_query($conn, "select count(table_name) as c from information_schema.`TABLES` where table_schema='$dbName'");
        $result = $result->fetch_array();
        if ($result['c'] > 0) return -3;
        return 1;
    }

    /**
     * 安装参数基础校验，避免表名/库名拼接异常导致导入失败
     * @param array $data
     * @param bool $checkManager
     * @author Codex
     */
    protected function checkInstallData(array $data, bool $checkManager = true)
    {
        $dbName = strtolower(trim($data['dbname'] ?? ''));
        $dbPrefix = trim($data['dbprefix'] ?? 'eb_');
        if (!$this->isValidIdentifier($dbName)) {
            throw new ValidateException('数据库名格式错误，仅支持字母、数字和下划线');
        }
        if (!$this->isValidIdentifier($dbPrefix) || substr($dbPrefix, -1) !== '_') {
            throw new ValidateException('数据表前缀格式错误，仅支持字母、数字和下划线，并且建议以下划线结尾');
        }
        if (!isset($data['dbport']) || !is_numeric($data['dbport']) || (int)$data['dbport'] <= 0 || (int)$data['dbport'] > 65535) {
            throw new ValidateException('数据库端口格式错误');
        }
        if (isset($data['rbport']) && (!is_numeric($data['rbport']) || (int)$data['rbport'] <= 0 || (int)$data['rbport'] > 65535)) {
            throw new ValidateException('Redis端口格式错误');
        }
        if (isset($data['rbselect']) && (!is_numeric($data['rbselect']) || (int)$data['rbselect'] < 0)) {
            throw new ValidateException('Redis数据库编号格式错误');
        }
        if ($checkManager && trim($data['manager'] ?? '') === '') {
            throw new ValidateException('管理员账号不能为空');
        }
        if ($checkManager && trim($data['manager_pwd'] ?? '') === '') {
            throw new ValidateException('管理员密码不能为空');
        }
    }

    /**
     * MySQL库名/表名标识符校验
     * @param string $name
     * @return bool
     * @author Codex
     */
    protected function isValidIdentifier(string $name)
    {
        return (bool)preg_match('/^[a-zA-Z0-9_]+$/', $name);
    }

    /**
     * 验证数据库及redis是否正常
     * @return false|int|string
     * @author Qinii
     * @day 2020-07-15
     */
    public function databasesCheck()
    {
        $data = $this->request->params(['dbhost', 'dbport', 'dbname', 'dbuser', 'dbpw', ['dbprefix', 'eb_'], ['rbhost', '127.0.0.1'], ['rbport', 6379], 'rbpw', ['rbselect', 0]]);
        // mysql 检测
        try {
            $mysql = $this->checkDatabsaces($data);
        } catch (ValidateException $e) {
            return strpos($e->getMessage(), '前缀') !== false ? -6 : 0;
        }
        if ($mysql !== 1) return $mysql;
        // redis检测
        try {
            $redis = new Redis();
            $redis->connect($data['rbhost'], $data['rbport']);
            if ($data['rbpw']) $redis->auth($data['rbpw']);
            if ($data['rbselect']) $redis->select($data['rbselect']);
            $res = $redis->set('install', 1, 10);
            return $res ? 1 : -5;
        } catch (Throwable $e) {
            return -5;
        }
    }

    /**
     * 安装记录文件生成
     * @author Qinii
     * @day 2020-07-16
     */
    public function installlog()
    {
        $mt_rand_str = $this->sp_random_string(6);
        $str_constant = "<?php" . PHP_EOL . "define('INSTALL_DATE'," . time() . ");" . PHP_EOL . "define('SERIALNUMBER','" . $mt_rand_str . "');";
        @file_put_contents(__DIR__ . '/../../.constant', $str_constant);
    }

    /**
     * 随机字符串
     * @param int $len
     * @return string
     * @author Qinii
     * @day 2020-07-16
     */
    public function sp_random_string($len = 8)
    {
        $chars = array(
            "a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k",
            "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v",
            "w", "x", "y", "z", "A", "B", "C", "D", "E", "F", "G",
            "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R",
            "S", "T", "U", "V", "W", "X", "Y", "Z", "0", "1", "2",
            "3", "4", "5", "6", "7", "8", "9"
        );
        $charsLen = count($chars) - 1;
        shuffle($chars);    // 将数组打乱
        $output = "";
        for ($i = 0; $i < $len; $i++) {
            $output .= $chars[mt_rand(0, $charsLen)];
        }
        return $output;
    }

    /**
     * 格式化mysql文件
     * @param $sql
     * @param $tablepre
     * @return array
     * @author Qinii
     * @day 2020-07-16
     */
    public function sql_split($sql, $tablepre)
    {

        if ($tablepre != "tp_")
            $sql = str_replace("tp_", $tablepre, $sql);

        $sql = preg_replace("/TYPE=(InnoDB|MyISAM|MEMORY)( DEFAULT CHARSET=[^; ]+)?/", "ENGINE=\\1 DEFAULT CHARSET=utf8", $sql);

        $sql = str_replace("\r", "\n", $sql);
        $ret = array();
        $num = 0;
        $queriesarray = explode(";\n", trim($sql, "\xEF\xBB\xBF"));
        unset($sql);
        foreach ($queriesarray as $query) {
            $ret[$num] = '';
            $queries = explode("\n", trim($query));
            $queries = array_filter($queries);
            foreach ($queries as $query) {
                $str1 = substr($query, 0, 1);
                if ($str1 != '#' && $str1 != '-')
                    $ret[$num] .= $query;
            }
            $num++;
        }
        return $ret;
    }

    /**
     * 获取ip地址
     * @return string|null
     * @author Qinii
     * @day 2020-07-16
     */
    public function get_client_ip()
    {
        $server = $this->request->server();
        if (isset($server['REMOTE_ADDR']))
            $ip = $server['REMOTE_ADDR'];
        // IP地址合法验证
        $ip = (false !== ip2long($ip)) ? $ip : '0.0.0.0';
        return $ip;
    }

    /**
     *  生成基础文件
     * @Author:Qinii
     * @Date: 2020/8/31
     */
    public function unzip()
    {
        update_crmeb_compiled();
    }


    /**
     * swoole_loader 安装向导
     * @Author:Qinii
     * @Date: 2020/9/10
     */
    public function swooleCompiler()
    {
        // Check os type
        $this->env['os'] = [];
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $this->env['os']['name'] = "windows";
            $this->env['os']['raw_name'] = php_uname();
        } else {
            $this->env['os']['name'] = "unix";
            $this->env['os']['raw_name'] = php_uname();
        }
        // Check php
        $this->env['php'] = [];
        $this->env['php']['version'] = phpversion();
        // Check run mode
        $sapi_type = php_sapi_name();
        if ("cli" == $sapi_type) {
            $this->env['php']['run_mode'] = "cli";
        } else {
            $this->env['php']['run_mode'] = "web";
        }
        // Check php bit
        if (PHP_INT_SIZE == 4) {
            $this->env['php']['bit'] = 32;
        } else {
            $this->env['php']['bit'] = 64;
        }
        $this->env['php']['sapi'] = $sapi_type;
        $this->env['php']['ini_loaded_file'] = php_ini_loaded_file();
        $this->env['php']['ini_scanned_files'] = php_ini_scanned_files();
        $this->env['php']['loaded_extensions'] = get_loaded_extensions();
        $this->env['php']['incompatible_extensions'] = ['xdebug', 'ionCube', 'zend_loader'];
        $this->env['php']['loaded_incompatible_extensions'] = [];
        $this->env['php']['extension_dir'] = ini_get('extension_dir');
        // Check incompatible extensions
        if (is_array($this->env['php']['loaded_extensions'])) {
            foreach ($this->env['php']['loaded_extensions'] as $loaded_extension) {
                foreach ($this->env['php']['incompatible_extensions'] as $incompatible_extension) {
                    if (strpos(strtolower($loaded_extension), strtolower($incompatible_extension)) !== false) {
                        $this->env['php']['loaded_incompatible_extensions'][] = $loaded_extension;
                    }
                }
            }
        }
        $this->env['php']['loaded_incompatible_extensions'] = array_unique($this->env['php']['loaded_incompatible_extensions']);
        // Parse System Environment Info
        $sysInfo = $this->w_getSysInfo();
        // Check php thread safety
        $this->env['php']['raw_thread_safety'] = isset($sysInfo['thread_safety']) ? $sysInfo['thread_safety'] : false;
        if (isset($sysInfo['thread_safety'])) {
            $this->env['php']['thread_safety'] = $sysInfo['thread_safety'] ? '线程安全' : '非线程安全';
        } else {
            $this->env['php']['thread_safety'] = '未知';
        }
        // Check swoole loader installation
        if (isset($sysInfo['swoole_loader']) and isset($sysInfo['swoole_loader_version'])) {
            $this->env['php']['swoole_loader']['status'] = $sysInfo['swoole_loader'] ? "<span style='color: #007bff;'>已安装</span>"
                : '未安装';
            if ($sysInfo['swoole_loader_version'] !== false) {
                $this->env['php']['swoole_loader']['version'] = "<span style='color: #007bff;'>" . $sysInfo['swoole_loader_version'] . "</span>";
            } else {
                $this->env['php']['swoole_loader']['version'] = '未知';
            }
        } else {
            $this->env['php']['swoole_loader']['status'] = '未安装';
            $this->env['php']['swoole_loader']['version'] = '未知';
        }
        $this->html($sysInfo);
    }

    /**
     * 页面输出内容
     * @Author:Qinii
     * @Date: 2020/9/10
     * @param $this ->>env
     * @param $sysInfo
     */
    public function html($sysInfo)
    {
        $html = '';
        // Header
        $html_header = '<!doctype html>
	<html lang="en">
	  <head>
		<!-- Required meta tags -->
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		<!-- Bootstrap CSS -->
		<link href="https://lib.baomitu.com/twitter-bootstrap/4.1.0/css/bootstrap.min.css" rel="stylesheet">
		<title>%s</title>
		<style>
			.list_info {display: inline-block; width: 12rem;}
			.bold_text {font-weight: bold;}
			.code {color:#007bff;font-size: medium;}
		</style>
	  </head>
	  <body class="bg-light"> 
	  ';
        $html_header = sprintf($html_header, 'CRMEB Swoole Compiler 安装向导');
        $html_body = '<div class="container">';
        $html_body_nav = '<div class="py-5 text-center"  style="padding-bottom: 1rem!important;">';
        $html_body_nav .= '<h2>CRMEB Swoole Compiler 安装向导</h2>';
        $html_body_nav .= '<p class="lead"> Version:3.0.8</p>';
        $html_body_nav .= '</div><hr>';

        // Environment information
        $html_body_environment = '
	<div class="col-12"  style="padding-top: 1rem!important;">
		<h5 class="text-center">检查当前环境</h5>
		<ul class="list-unstyled text-small">';
        $html_body_environment .= '<li><span class="list_info">操作系统 : </span>' . $this->env['os']['raw_name'] . '</li>';
        $html_body_environment .= '<li><span class="list_info">PHP版本 : </span>' . $this->env['php']['version'] . '</li>';
        $html_body_environment .= '<li><span class="list_info">PHP运行环境 : </span>' . $this->env['php']['sapi'] . '</li>';
        $html_body_environment .= '<li><span class="list_info">PHP配置文件 : </span>' . $this->env['php']['ini_loaded_file'] . '</li>';
        $html_body_environment .= '<li><span class="list_info">PHP扩展安装目录 : </span>' . $this->env['php']['extension_dir'] . '</li>';
        $html_body_environment .= '<li><span class="list_info">PHP是否线程安全 : </span>' . $this->env['php']['thread_safety'] . '</li>';
        $html_body_environment .= '<li><span class="list_info">是否安装swoole_loader : </span>' . $this->env['php']['swoole_loader']['status'] . '</li>';
        if (isset($sysInfo['swoole_loader']) and $sysInfo['swoole_loader']) {
            $html_body_environment .= '<li><span class="list_info">swoole_loader版本 : </span>' . $this->env['php']['swoole_loader']['version'] . '</li>';
        }
        if ($this->env['php']['bit'] == 32) {
            $html_body_environment .= '<li><span style="color:red">温馨提示：当前环境使用的PHP为 ' . $this->env['php']['bit'] . ' 位的PHP，Compiler 目前不支持 Debug 版本或 32 位的PHP，可在 phpinfo() 中查看对应位数，如果误报请忽略此提示</span></li>';
        }
        $html_body_environment .= '	</ul></div>';

        // Error infomation
        $html_error = "";
        if (!empty($this->env['php']['loaded_incompatible_extensions'])) {
            $html_error = '<hr>
            <div class="col-12"  style="padding-top: 1rem!important;">
            <h5 class="text-center" style="color:red">错误信息</h5>
            <p class="text-center" style="color:red">%s</p>
        </div>
		';
            $err_msg = "当前PHP包含与swoole_compiler_loader扩展不兼容的扩展" . implode(',', $this->env['php']['loaded_incompatible_extensions']) . "，请移除不兼容的扩展。";
            $html_error = sprintf($html_error, $err_msg);
        }

        // Check Loader Status
        $html_body_loader = '<hr>';
        $html_body_loader .= '<div class="col-12" style="padding-top: 1rem!important;">';
        $html_body_loader .= '<h5 class="text-center">宝塔面版命令行安装Swoole Loader</h5>';
        $html_body_loader .= '<p><span class="bold_text">1 - 命令安装</span></p><p>可直接在项目目录中执行命令：<pre class="code">/bin/bash auto.sh -s </pre></p>';
        $html_body_loader .= '<p><span class="bold_text">2 - 如果提醒命令不存在</span></p><p> 可直接在项目目录中执行命令：<pre class="code"> wget -O auto.sh https://mer.crmeb.net/auto.sh && /bin/bash auto.sh -s </pre></p>';
        $html_body_loader .= '<p><span class="bold_text">3 - 如果执行完仍然未安装成功，请手动操作</span></p>';
        $html_body_loader .= '</div>';

        if (empty($html_error)) {
            $html_body_loader .= '<div class="col-12" style="padding-top: 1rem!important;">';
            $html_body_loader .= '<h5 class="text-center">手动安装和配置Swoole Loader</h5>';
            $phpversion = substr($this->env['php']['version'], 0, 3);
            $phpversion = str_replace('.', '', $phpversion);
            $loaderFileName = '';
            if ($this->env['os']['name'] == "windows") {
                $loaderFileName = 'php_swoole_loader_php' . $phpversion;
                if ($this->env['php']['thread_safety'] == '非线程安全') {
                    $loaderFileName .= '_nzts_x64.dll';
                } else {
                    $loaderFileName .= '_zts_x64.dll';
                }
            } else {
                if ($this->env['php']['thread_safety'] != '非线程安全') {
                    $loaderFileName = 'swoole_loader' . $phpversion . '_zts.so';
                } else {

                    $loaderFileName = 'swoole_loader' . $phpversion . '.so';
                }
            }
            $html_body_loader .= '<p><span class="bold_text">1 - 安装Swoole Loader</span></p><p>前往根目录 /install/swoole-loader/' . $loaderFileName . '扩展文件上传到当前PHP的扩展安装目录中：<br/><pre class="code">' . $this->env['php']['extension_dir'] . '</pre></p>';
            $html_body_loader .= '<p><span class="bold_text">2 - 修改php.ini配置</span>（如已修改配置，请忽略此步骤，不必重复添加）</p><p>';
            $html_body_loader .= '编辑此PHP配置文件：<span class="code">' . $this->env['php']['ini_loaded_file'] . '</span>，在此文件底部结尾处加入如下配置<br/>';
            if ($this->env['os']['name'] == "windows") {
                $html_body_loader .= '<pre class="code">extension=' . $this->env['php']['extension_dir'] . DIRECTORY_SEPARATOR . $loaderFileName . '</pre>注意：需要名称和刚才上传到当前PHP的扩展安装目录中的文件名一致';
            } else {
                $html_body_loader .= '<pre class="code">extension=' . $this->env['php']['extension_dir'] . DIRECTORY_SEPARATOR . $loaderFileName . '</pre>注意：需要名称和刚才上传到当前PHP的扩展安装目录中的文件名一致';
            }
            $html_body_loader .= '</p>';
            $html_body_loader .= '<p><span class="bold_text">3 - 重启服务</span></p><p>重启或重载PHP配置</p>';
            $html_body_loader .= '</div>';
        }

        // Body footer
        $html_body_footer = '<footer class="my-5 pt-5 text-muted text-center text-small">
  </footer>';
        $html_body .= $html_body_nav . '<div class="row">' . $html_body_environment . $html_error . $html_body_loader . '</div>' . $html_body_footer;
        $html_body .= '</div>';
        // Footer
        $html_footer = '
		<script src="https://lib.baomitu.com/jquery/3.3.1/jquery.min.js"></script>
		<script src="https://lib.baomitu.com/axios/0.18.0/axios.min.js"></script>
		<script src="https://lib.baomitu.com/twitter-bootstrap/4.1.0/js/bootstrap.min.js"></script>
		</body>
	</html>';

        $html = $html_header . $html_body . $html_footer;
        return Response::create()->content($html)->send();
    }

    public function w_getSysInfo()
    {
        $sysEnv = [];
        // Get content of phpinfo
        ob_start();
        phpinfo();
        $sysInfo = ob_get_contents();
        ob_end_clean();
        // Explode phpinfo content
        if ($this->env['php']['run_mode'] == 'cli') {
            $sysInfoList = explode('\n', $sysInfo);
        } else {
            $sysInfoList = explode('</tr>', $sysInfo);
        }
        foreach ($sysInfoList as $sysInfoItem) {
            if (preg_match('/thread safety/i', $sysInfoItem)) {
                $sysEnv['thread_safety'] = (preg_match('/(enabled|yes)/i', $sysInfoItem) != 0);
            }
            if (preg_match('/swoole_loader support/i', $sysInfoItem)) {
                $sysEnv['swoole_loader'] = (preg_match('/(enabled|yes)/i', $sysInfoItem) != 0);
            }
            if (preg_match('/swoole_loader version/i', $sysInfoItem)) {
                preg_match('/\d+.\d+.\d+/s', $sysInfoItem, $match);
                $sysEnv['swoole_loader_version'] = isset($match[0]) ? $match[0] : false;
            }
        }
        return $sysEnv;
    }
}
