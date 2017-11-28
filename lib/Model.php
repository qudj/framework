<?php
class Model
{ 
    protected $_medoo;
    protected $_db = 'test';
    protected $_table = 'test';
    protected $_primary_key = 'id';
    
    public function __construct(){}
    
    public function find($id, $forceMaster = false) {
        $this->_medoo = DBConnection::getConnection($this->_db, $forceMaster);
        return $this->_medoo->get($this->_table, '*', [$this->_primary_key => $id]);
    }
    
    public function fetchRow($where, $fields = '*', $forceMaster = false) {
        $this->_medoo = DBConnection::getConnection($this->_db, $forceMaster);
        return $this->_medoo->get($this->_table, $fields, $where);
    }
    
    public function fetchAll($where, $fields = '*', $forceMaster = false) {
        $this->_medoo = DBConnection::getConnection($this->_db, $forceMaster);
        return $this->_medoo->select($this->_table, $fields, $where);
    }
    
    public function insert($data) {
        $this->_medoo = DBConnection::getConnection($this->_db, true);
        return $this->_medoo->insert($this->_table, $data);
    }
    
    public function batchInsert($datas) {
        $this->_medoo = DBConnection::getConnection($this->_db, true);
        return $this->_medoo->batchInsert($this->_table, $datas);
    }
    
    public function update($data, $where) {
        $this->_medoo = DBConnection::getConnection($this->_db, true);
        return $this->_medoo->update($this->_table, $data, $where);
    }
    
    public function delete($where) {
        $this->_medoo = DBConnection::getConnection($this->_db, true);
        return $this->_medoo->delete($this->_table, $where);
    }
    
    public function query($sql, $forceMaster = false) {
        $this->_medoo = DBConnection::getConnection($this->_db, $forceMaster);
        return $this->_medoo->query($sql);
    }
    
    public function lastQuery() {
        return $this->_medoo->last_query();
    }
    
    public function begin() {
        if (empty($this->_medoo)) {
            $this->_medoo = DBConnection::getConnection($this->_db, true);
        }
        $this->_medoo->pdo->beginTransaction();
    }
    
    public function commit() {
        if (empty($this->_medoo)) {
            $this->_medoo = DBConnection::getConnection($this->_db, true);
        }
        if ( ! empty($this->_medoo) && $this->_medoo->pdo->inTransaction()) {
            $this->_medoo->pdo->commit();
        }
    }
    
    public function rollback() {
        if (empty($this->_medoo)) {
            $this->_medoo = DBConnection::getConnection($this->_db, true);
        }
        if ( ! empty($this->_medoo) && $this->_medoo->pdo->inTransaction()) {
            $this->_medoo->pdo->rollback();
        }
    }
    
    public function getErrors(){
        return $this->_medoo->error();
    }
    
    public function count($where, $forceMaster = false){
        $this->_medoo = DBConnection::getConnection($this->_db, $forceMaster);
        return $this->_medoo->count($this->_table,$where);
    }
    
    public function getPrimaryKeyField(){
        return $this->_primary_key;
    }

    protected function getFields(){
        $return =array();
        $fields = $this->query('show columns from '.$this->_table);
        if($fields){
            foreach($fields->fetchAll() as $field){
                $return[$field['Field']] = (strpos($field['Type'],'int') !== false)?'int': 'string';
            }
        }
        return $return;
    }

    public function getTableName(){
        return $this->_table;
    }    

} 
