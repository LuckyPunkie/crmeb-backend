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

declare (strict_types=1);

namespace app\command;

use think\Exception;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Option;

class createTool extends Command
{
    public $config;
    public $log = '创建记录:'.PHP_EOL;
    protected function configure()
    {
        // 指令配置
        $this->setName('createTool')
            ->addArgument('table', Option::VALUE_REQUIRED, '表名称')
            ->addOption('path', 'p',Option::VALUE_REQUIRED, '创建路径,不填则创建在最外层')
            ->addOption('controller',  'c',Option::VALUE_OPTIONAL,"可选参数,需要创建的控制器:admin,mer,api,pc,ser")
            ->addOption('key', 'k',Option::VALUE_REQUIRED, '主键名称')
            ->setDescription('创建一个新的数据表全部模块:model,dao,repository,controller');
    }

    protected function execute(Input $input, Output $output)
    {
        $options['table'] = $input->getArgument('table');
        if (!preg_match('/^[a-z_]+$/', $options['table'])){
            $output->error('表名称格式不正确:仅包含小写字母和下划线');
            $output->error('例: php think createTool table_name -p system/merchant -c admin -k id');
            return;
        }
        $options['path'] = $input->getOption('path');
        $options['controller'] = null;
        if ($controller = $input->getOption('controller')) {
            $options['controller'] = explode(',',$controller);
        }
        $options['key'] = $input->getOption('key');
        $output->writeln('开始执行');
        $output->writeln('');
        $this->create($options);
        $output->writeln($this->log);
        $output->writeln('执行完成');
    }

    public function create($config)
    {
        $table = $config['table'];
        $prefix = env('database.prefix', '');
        $len = strlen($prefix);
        $_prefix = substr($table,0,$len);
        if ($prefix == $_prefix) {
            $table = substr($table,$len);
        }
        $config['table'] = $table;
        $class_name = str_replace(' ', '', ucwords(str_replace('_', ' ', $table)));
        $config['class_name'] = $class_name;
        $path = explode('/', $config['path']);
        $config['namespace_path']  = implode('\\',$path);
        $this->config = $config;

        ////生成model
        $this->createModel();

        //生成dao
        $this->createDao();

        //生成repository
        $this->createRepository();

        if ($this->config['controller']) {
            foreach ($this->config['controller'] as $c) {
                $this->createController($c);
            }
        }
    }

    public function createModel()
    {
        $file_path = app_path().'common/model/'.$this->config['path'];
        $file_name = $this->config['class_name'].'.php';
        $id = $this->config['key'] ?: $this->config['table'].'_id';
        $content = $this->getStart();
        $content .= "namespace app\common\model\\{$this->config['namespace_path']};
        
use app\common\model\BaseModel;

class {$this->config['class_name']} extends BaseModel
{
    public static function tablePk(): ?string
    {
        return '{$id}';
    }
    
    public static function tableName(): string
    {
        return '{$this->config['table']}';
    }
}
";
        $this->createFile($file_path, $file_name,$content);
    }

    public function createDao()
    {
        $file_path = app_path().'common/dao/'.$this->config['path'];
        $file_name = $this->config['class_name'].'Dao.php';
        $content = $this->getStart();
        $content .= "namespace app\common\dao\\{$this->config['namespace_path']};

use app\common\dao\BaseDao;
use app\common\model\\{$this->config['namespace_path']}\\{$this->config['class_name']};

class {$this->config['class_name']}Dao extends BaseDao
{

    protected function getModel(): string
    {
        return {$this->config['class_name']}::class;
    }
}";
        $this->createFile($file_path, $file_name, $content);
    }

    public function createRepository()
    {
        $file_path = app_path().'common/repositories/'.$this->config['path'];
        $file_name = $this->config['class_name'].'Repository.php';

        $content = $this->getStart();
        $content .= "namespace app\common\\repositories\\{$this->config['namespace_path']};

use app\common\\repositories\BaseRepository;
use app\common\\dao\\{$this->config['namespace_path']}\\{$this->config['class_name']}Dao;

class {$this->config['class_name']}Repository extends BaseRepository
{
    public function __construct({$this->config['class_name']}Dao \$dao)
    {
        \$this->dao = \$dao;
    }
  
 }";
        $this->createFile($file_path, $file_name, $content);
    }

    public function createController($controller)
    {
        switch ($controller) {
            case "admin":
                $contr = 'admin';
                break;
            case "mer":
                $contr = 'merchant';
                break;
            case "api":
                $contr = 'api';
                break;
            case "pc":
                $contr = 'pc';
                break;
            case "ser":
                $contr = 'service';
                break;
            default:
                throw new Exception('控制器类型错误');
        }
        $file_path = app_path().'controller/'.$contr.'/'.$this->config['path'];
        $file_name = $this->config['class_name'].'.php';

        $content = $this->getStart();
        $content .= "namespace app\controller\\{$contr}\\{$this->config['namespace_path']};

use think\App;
use crmeb\basic\BaseController;
use app\common\\repositories\\{$this->config['namespace_path']}\\{$this->config['class_name']}Repository;

class {$this->config['class_name']} extends BaseController
{
    protected \$repository;

    public function __construct(App \$app, {$this->config['class_name']}Repository \$repository)
    {
        parent::__construct(\$app);
        \$this->repository = \$repository;
    }
}";
        $this->createFile($file_path, $file_name, $content);
    }

    public function createFile($path,$name, $content)
    {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        $file_path = $path.'/'.$name;
        if (file_exists($file_path)) {
            throw new \Exception('文件已存在:'.$file_path);
        }
        try{
            file_put_contents($file_path, $content);
            $this->log .= $file_path. PHP_EOL;
            return [true, '创建成功'];
        }catch (\Exception $exception) {
            throw new Exception($exception->getMessage());
        }
    }

    public function getStart()
    {
        $time = date('Y',time());
        $content = '<?php'.PHP_EOL.PHP_EOL;
        $content .= "// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016-2026 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------
".PHP_EOL;
        return $content;
    }
}
