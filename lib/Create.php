<?php
class Create
{ 
    private static $instance = NULL;

    //获取单例
    public static function getInstance() {
        if (!isset (self::$instance)) {
            self::$instance = new Create();
        }
        return self::$instance;
    }

	private function __construct(){} 

	public function CreateProject($appname){
        if($this->CreateFolder($appname))
        {
            $this->CreateFolder($appname."/Action");
            $this->CreateFirstAction($appname."/Action");
            $this->CreateFolder($appname."/Service");
            $this->CreateFolder($appname."/Model");
            $this->CreateFolder($appname."/Tpl");
            $this->CreateFolder($appname."/Conf");
            $this->CreateConf($appname."/Conf");
            $this->CreateFolder($appname."/Common");
            $this->CreateFolder($appname."/Data");
            return true;
        }else{
            return false;
        }
	}

    public function CreateFolder($filename){
        if(!file_exists($filename))
        {
            return mkdir($filename, 0777);
        }else{
            return false;
        }
    }

    public function CreateFirstAction($path){
        $str = "<?php
class IndexAction extends Action
{ 
    public function index()
    { 
        echo 'hello!!!';
    }

}";
        $filename = $path."/IndexAction.php";
        file_put_contents($filename, $str);
    }

    public function CreateConf($path){
        $str = "<?php
return array(
    );";
        $filename = $path."/conf.php";
        file_put_contents($filename, $str);
    }
} 
