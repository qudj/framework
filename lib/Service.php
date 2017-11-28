<?php
class Service
{ 
    public function __construct($name='',$tablePrefix='',$connection='') {

        //$this->_initialize();

        if(!empty($name)) {
            if(strpos($name,'.')) {
                //list($this->dbName,$this->name) = explode('.',$name);
            }else{
                //$this->name   =  $name;
            }
        }elseif(empty($this->name)){
            //$this->name =   $this->getModelName();
        }

        if(is_null($tablePrefix)) {// 前缀为Null表示没有前缀
            //$this->tablePrefix = '';
        }elseif('' != $tablePrefix) {
            //$this->tablePrefix = $tablePrefix;
        }else{
            //$this->tablePrefix = $this->tablePrefix?$this->tablePrefix:C('DB_PREFIX');
        }

        //$this->db(0,empty($this->connection)?$connection:$this->connection);
    }
} 
