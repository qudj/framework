<?php

class myMedoo extends medoo
{
    public function batchInsert($table, $datas) {
        if (!isset($datas[0])) {
            $datas = array($datas);
        }
        $all_values = array();
        foreach ($datas as $data) {
            $values = array();
            foreach ($data as $key => $value) {
                switch (gettype($value)) {
                    case 'NULL':
                        $values[] = 'NULL';
                        break;
                    case 'array':
                        preg_match("/\(JSON\)\s*([\w]+)/i", $key, $column_match);
                        $values[] = isset($column_match[0]) ? $this->quote(json_encode($value)) : $this->quote(serialize($value));
                        break;
                    case 'boolean':
                        $values[] = ($value ? '1' : '0');
                        break;
                    case 'integer':
                    case 'double':
                    case 'string':
                        $values[] = $this->fn_quote($key, $value);
                        break;
                }
            }
            $all_values[] = '(' . join(', ', $values) . ')';
        }
        
        $sql = join(",", $all_values);
        $columns = array_map(array($this, "column_quote"), array_keys($datas[0]));
        $result = $this->exec('INSERT INTO "' . $table . '" (' . implode(', ', $columns) . ') VALUES ' . $sql);
        if ($result === FALSE) {
            return FALSE;
        }
        $lastId = $this->pdo->lastInsertId();
        $lastIds = array();
        for ($i = 0, $count = count($datas); $i < $count; $i++) {
            $lastIds[] = $lastId + $i;
        }
        
        return $lastIds;
    }

    public function query($query) {
        array_push($this->logs, $query);
        try {
            return $this->pdo->query($query);
        } catch (\Exception $e) {
            Logger::ERR("DBERROR", ['sql'=>$query, 'ex'=>$e->getMessage()]);
            return FALSE;
        }
    }
    
    public function exec($query) {
        array_push($this->logs, $query);
        try {
            return $this->pdo->exec($query);
        } catch (\Exception $e) {
            Logger::ERR("DBERROR", ['sql'=>$query, 'ex'=>$e->getMessage()]);
            return FALSE;
        }
    }
}
