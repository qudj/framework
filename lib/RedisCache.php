<?php
class RedisCache{
    
    const DEFAULT_PORT = 6379;
    const DEFAULT_TIMEOUT = 3;    
    
    private $_server_list = array();
    // 延迟排序，因为可能会执行多次addServer
    private $_layze_sorted = FALSE;
        
    protected static $_instanceList;
    
    //保存上次对应的服务器链接失败的时间
    protected static $_lastFailTimeList;

    //获取 Redis 的实例
    protected function __construct() {
        $config = C('redis');
        $hosts = $config['host'];
        foreach($hosts as $v){
            $this->addServer($v);
        }
    }
    
    public static function getInstance() {
        static $_instance = NULL;
        if ( empty( $_instance ) ) {
            $_instance = new static();
        }
        return $_instance;
    }    
    
    //一致性hash，根据key值获取存储service
    protected function _connect($key, &$index) {
        $config = C('redis');
        $index = $this->find($key);
        $host = $index;
        $h = explode(":", $host);
        $port = count($h) > 1 && is_numeric($h[1]) ? $h[1] : self::DEFAULT_PORT;
        $host = "{$h[0]}:$port";
        if(isset(self::$_instanceList[$host])) {
            $instance = self::$_instanceList[$host];
            if($instance !== false || time() - self::$_lastFailTimeList[$host] < $this->_retryInterval) {
                return $instance;
            }
        }
        $timeout = isset($config['timeout']) ? (int) $config['timeout'] : self::DEFAULT_TIMEOUT;
    
        $persistent = isset($config['persistent']) ? $config['persistent'] : 0;
        $password = isset($config['password']) ? $config['password'] : '';
        $redis = new Redis();
        try {
            if($persistent) {
                if(!$redis->pconnect($h[0], $port, $timeout, null, 100)) {
                    throw new Exception("pConnect to redis $host:$port failed:" . $redis->getLastError());
                }
            } else {
                if(!$redis->connect($h[0], $port, $timeout, null, 100)) {
                    throw new Exception("Connect to redis $host:$port failed:" . $redis->getLastError());
                }
            }
            //$redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_IGBINARY);
            if($password != '' && method_exists($redis, "auth")) {
                $redis->auth($password);
            }
        } catch (Exception $e) {
            Logger::ERR("Redis", "Connect to $host:$port failed: " . $e->getMessage());
            self::$_instanceList[$host] = false;
            self::$_lastFailTimeList[$host] = time();
            $redis = null;
            return false;
        }
        self::$_instanceList[$host] = $redis;
        return $redis;
    }
    
    //hash 字符串转数字
    protected function myHash($str) {
        $hash = 0;
        $s    = md5($str);
        $seed = 5;
        $len  = 32;
        for ($i = 0; $i < $len; $i++) {
            $hash = ($hash << $seed) + $hash + ord($s{$i});
        }
        return $hash & 0x7FFFFFFF;
    }
    
    //一致性hash， 添加服务器节点
    protected function addServer($server) {
        $hash = $this->myHash($server);
        $this->_layze_sorted = FALSE;
    
        if (!isset($this->_server_list[$hash])) {
            $this->_server_list[$hash] = $server;
        }
    }
    
    //寻找节点所在服务器
    protected function find($key) {
        // 排序
        if (!$this->_layze_sorted) {
            asort($this->_server_list);
            $this->_layze_sorted = TRUE;
        }
    
        $hash = $this->myHash($key);
        $len  = sizeof($this->_server_list);
        if ($len == 0) {
            return FALSE;
        }
    
        $keys   = array_keys($this->_server_list);
        $values = array_values($this->_server_list);
    
        // 如果不在区间内，则返回最后一个server
        if ($hash <= $keys[0] || $hash >= $keys[$len - 1]) {
            return $values[$len - 1];
        }
    
        foreach ($keys as $key=>$pos) {
            $next_pos = NULL;
            if (isset($keys[$key + 1]))
            {
                $next_pos = $keys[$key + 1];
            }
             
            if (is_null($next_pos)) {
                return $values[$key];
            }
    
            // 区间判断
            if ($hash >= $pos && $hash <= $next_pos) {
                return $values[$key];
            }
        }
    }

    
    /**
     *
     * @param string $key
     * @param mixed  $value
     * @param mixed  $expire  如果 $expire 为数字, 则认为是 TTL, 0 表示永不失效,  否则需要是一个数组
     *         参考  https://github.com/nicolasff/phpredis#set
     *
     * @return bool
     **/
    public function set($key, $value, $expire = 3600) {
        $index = '';
        $instance = $this->_connect($key, $index);
        if($instance === false) {
            return false;
        }
        return $instance->set($key, $value, $expire);
    }
        
    /**
     * @param string $key
     * @return mixed
     **/
    public function get($key) {
        $index = '';
        $instance = $this->_connect($key, $index);
    
        if($instance === false) {
            return false;
        }
    
        return $instance->get($key);
    }
    
    /**
     * @param mixed $key
     * @return 返回删除的记录数
     **/
    public function delete($key) {
        $keys = is_array($key) ? $key : array($key);
        $keyMapping = array();
        $instanceMapping = array();
        foreach($keys as $k) {
            $index = 0;
            $instance = $this->_connect($k, $index);
            if($instance === false) {
                continue;
            }
            $instanceMapping[$index] = $instance;
            if(isset($keyMapping[$index])) {
                $keyMapping[$index][] = $k;
            } else {
                $keyMapping[$index] = array($k);
            }
        }
    
        $ret = 0;
        foreach($instanceMapping as $k => $instance) {
            $ret += $instance->delete($keyMapping[$k]);
        }
        return $ret;
    }
    
    //key不存在则设置
    public function setNx($key, $value) {
        $index = '';
        $instance = $this->_connect($key, $index);
    
        if($instance === false) {
            return false;
        }
    
        return $instance->setNx($key, $value);
    }    
    
    public function getSet($key, $value) {
        $index = '';
        $instance = $this->_connect($key, $index);
    
        if ($instance === false) {
            return false;
        }
    
        return $instance->getSet($key, $value);
    }
    
    /**
     * @param array $data
     * @param int   $expire  如果指定了过期时间，实际该方法为遍历所有的 $key 一个一个设定的
     * @return array 返回成功保存的 $key 数组
     **/
    public function mset(array $data, $expire = 0) {
        $keyMapping = array();
        $instanceMapping = array();
        foreach($data as $key => $value) {
            $index = '';
            $instance = $this->_connect($key, $index);
            if($instance === false) {
                continue;
            }
            $instanceMapping[$index] = $instance;
            if(isset($keyMapping[$index])) {
                $keyMapping[$index][$key] = $value;
            } else {
                $keyMapping[$index] = array($key => $value);
            }
        }
    
        $ret = array();
        foreach($instanceMapping as $index => $instance) {
            if($expire == 0) {
                if($instance->mset($keyMapping[$index])) {
                    $ret += array_keys($keyMapping[$index]);
                }
            } else {
                foreach($keyMapping[$index] as $key => $value) {
                    if($instance->set($key, $value, $expire)) {
                        $ret[] = $key;
                    }
                }
            }
        }
        return $ret;
    }
    
    /**
     * @param array $keys
     * @return array
     **/
    public function mget(array $keys) {
        $keyMapping = array();
        $instanceMapping = array();
        foreach($keys as $key) {
            $index = '';
            $instance = $this->_connect($key, $index);
            if($instance === false) {
                continue;
            }
            $instanceMapping[$index] = $instance;
            if(isset($keyMapping[$index])) {
                $keyMapping[$index][] =  $key;
            } else {
                $keyMapping[$index] = array($key);
            }
        }
    
        $result = array();
        foreach($instanceMapping as $index => $instance) {
            $ret = $instance->mget($keyMapping[$index]);
            foreach($keyMapping[$index] as $k => $v) {
                $result[$v] = $ret[$k];
            }
        }
    
        return $result;
    }
    
    /**
     * @param $key
     * @return array|bool
     */
    public function hgetall($key) {
        $index = '';
        $instance = $this->_connect($key, $index);
    
        if($instance === false) {
            return false;
        }
    
        return $instance->hgetall($key);
    }
    
    public function hget($key, $field) {
        $index = '';
        $instance = $this->_connect($key, $index);
    
        if($instance === false) {
            return false;
        }
    
        return $instance->hget($key, $field);
    }
    
    /**
     * @param $key
     * @param $field
     * @param $value
     * @return bool|int
     */
    public function hset($key, $field, $value) {
        $index = '';
        $instance = $this->_connect($key, $index);
    
        if($instance === false) {
            return false;
        }
    
        return $instance->hset($key, $field, $value);
    }
    
    /**
     * @param $key
     * @param $field
     * @return bool
     */
    public function hexists($key, $field) {
        $index = '';
        $instance = $this->_connect($key, $index);
    
        if($instance === false) {
            return false;
        }
    
        return $instance->hexists($key, $field);
    }
    
    /**
     * @param $key
     * @return array|bool
     */
    public function hkeys($key) {
        $index = '';
        $instance = $this->_connect($key, $index);
    
        if($instance === false) {
            return false;
        }
    
        return $instance->hkeys($key);
    }
    
    /**
     * @param $key
     * @param $field
     * @return bool|int
     */
    public function hdel($key, $field) {
        $index = '';
        $instance = $this->_connect($key, $index);
    
        if($instance === false) {
            return false;
        }
    
        return $instance->hdel($key, $field);
    }
    
    /**
     * @param $key
     * @param $field_values
     * @return bool
     */
    public function hmset($key, $field_values) {
        $index = '';
        $instance = $this->_connect($key, $index);
    
        if($instance === false) {
            return false;
        }
    
        return $instance->hmset($key, $field_values);
    }
    
    /**
     * @param $key
     * @param $fields
     * @return array|bool
     */
    public function hmget($key, $fields) {
        $index = '';
        $instance = $this->_connect($key, $index);
    
        if($instance === false) {
            return false;
        }
    
        return $instance->hmget($key, $fields);
    }
    
    /**
     * @param $key
     * @return bool|int
     */
    public function incr($key) {
        $index = '';
        $instance = $this->_connect($key, $index);
    
        if($instance === false) {
            return false;
        }
    
        return $instance->incr($key);
    }
    
    /**
     * @param $key
     * @return bool|int
     */
    public function decr($key) {
        $index = '';
        $instance = $this->_connect($key, $index);
    
        if($instance === false) {
            return false;
        }
    
        return $instance->decr($key);
    }
    
    /**
     * @param $key
     * @return bool|int
     */
    public function ttl($key) {
        $index = '';
        $instance = $this->_connect($key, $index);
    
        if($instance === false) {
            return false;
        }
    
        return $instance->ttl($key);
    }
    
    /**
     * @param $key
     * @return bool
     */
    public function exists($key) {
        $index = '';
        $instance = $this->_connect($key, $index);
    
        if($instance === false) {
            return false;
        }
    
        return $instance->exists($key);
    }
    
    /**
     * @param $key
     * @param $seconds
     * @return bool
     */
    public function expire($key, $seconds) {
        $index = '';
        $instance = $this->_connect($key, $index);
    
        if($instance === false) {
            return false;
        }
    
        return $instance->expire($key, $seconds);
    }
    
    /**
     * @param $key
     * @param $value
     * @return bool|int
     */
    public function lpush($key, $value) {
        $index = '';
        $instance = $this->_connect($key, $index);
    
        if($instance === false) {
            return false;
        }
    
        return $instance->lpush($key, $value);
    }
    
    /**
     * @param $key
     * @param $value
     * @return bool|string
     */
    public function lpop($key) {
        $index = '';
        $instance = $this->_connect($key, $index);
    
        if($instance === false) {
            return false;
        }
    
        return $instance->lpop($key);
    }
    
    /**
     * @param $key
     * @param $idx
     * @return bool|String
     */
    public function lindex($key, $idx) {
        $index = '';
        $instance = $this->_connect($key, $index);
    
        if($instance === false) {
            return false;
        }
    
        return $instance->lindex($key, $idx);
    }
    
    /**
     * @param $key
     * @return bool|int
     */
    public function llen($key) {
        $index = '';
        $instance = $this->_connect($key, $index);
    
        if($instance === false) {
            return false;
        }
    
        return $instance->llen($key);
    }
    
    /**
     * @param $key
     * @param $start
     * @param $end
     * @return array|bool
     */
    public function lrange($key, $start, $end) {
        $index = '';
        $instance = $this->_connect($key, $index);
    
        if($instance === false) {
            return false;
        }
    
        return $instance->lrange($key, $start, $end);
    }
    
    /**
     * @param $key
     * @param $cound
     * @param $value
     * @return bool|int
     */
    public function lrem($key, $cound, $value) {
        $index = '';
        $instance = $this->_connect($key, $index);
    
        if($instance === false) {
            return false;
        }
    
        return $instance->lrem($key, $cound, $value);
    }
    
    /**
     * @param $key
     * @param $start
     * @param $end
     * @return bool|string
     */
    public function rpop($key) {
        $index = '';
        $instance = $this->_connect($key, $index);
    
        if($instance === false) {
            return false;
        }
    
        return $instance->rpop($key);
    }
    
    /**
     * @param $key
     * @param $start
     * @param $end
     * @return bool|int
     */
    public function rpush($key, $value) {
        $index = '';
        $instance = $this->_connect($key, $index);
    
        if($instance === false) {
            return false;
        }
    
        return $instance->rpush($key, $value);
    }
    
    /**
     * @param $key
     * @param $member
     * @return bool|int
     */
    public function sadd($key, $member) {
        $index = '';
        $instance = $this->_connect($key, $index);
    
        if($instance === false) {
            return false;
        }
    
        return $instance->sadd($key, $member);
    }
    
    /**
     * @param $key
     * @return bool|int
     */
    public function scard($key) {
        $index = '';
        $instance = $this->_connect($key, $index);
    
        if($instance === false) {
            return false;
        }
    
        return $instance->scard($key);
    }
    
    /**
     * @param $key
     * @param $member
     * @return bool
     */
    public function sismember($key, $member) {
        $index = '';
        $instance = $this->_connect($key, $index);
    
        if($instance === false) {
            return false;
        }
    
        return $instance->sismember($key, $member);
    }
    
    /**
     * @param $key
     * @return array|bool
     */
    public function smembers($key) {
        $index = '';
        $instance = $this->_connect($key, $index);
    
        if($instance === false) {
            return false;
        }
    
        return $instance->smembers($key);
    }
    
    /**
     * @param $key
     * @return bool|string
     */
    public function spop($key) {
        $index = '';
        $instance = $this->_connect($key, $index);
    
        if($instance === false) {
            return false;
        }
    
        return $instance->spop($key);
    }
    
    /**
     * @param $key
     * @return bool|string
     */
    public function srandmember($key) {
        $index = '';
        $instance = $this->_connect($key, $index);
    
        if($instance === false) {
            return false;
        }
    
        return $instance->srandmember($key);
    }
    
    /**
     * @param $key
     * @param $member
     * @return bool|int
     */
    public function srem($key, $member) {
        $index = '';
        $instance = $this->_connect($key, $index);
    
        if($instance === false) {
            return false;
        }
    
        return $instance->srem($key, $member);
    }
    
    /**
     * @param $key
     * @param $score
     * @param $member
     * @return bool|int
     */
    public function zadd($key, $score, $member) {
        $index = '';
        $instance = $this->_connect($key, $index);
    
        if($instance === false) {
            return false;
        }
    
        return $instance->zadd($key, $score, $member);
    }
    
    /**
     * @param $key
     * @return bool|int
     */
    public function zcard($key) {
        $index = '';
        $instance = $this->_connect($key, $index);
    
        if($instance === false) {
            return false;
        }
    
        return $instance->zcard($key);
    }
    
    /**
     * @param $key
     * @param $min
     * @param $max
     * @return bool|int
     */
    public function zcount($key, $min, $max) {
        $index = '';
        $instance = $this->_connect($key, $index);
    
        if($instance === false) {
            return false;
        }
    
        return $instance->zcount($key, $min, $max);
    }
    
    /**
     * @param $key
     * @param $increment
     * @param $member
     * @return bool|float
     */
    public function zincrby($key, $increment, $member) {
        $index = '';
        $instance = $this->_connect($key, $index);
    
        if($instance === false) {
            return false;
        }
    
        return $instance->zincrby($key, $increment, $member);
    }
    
    /**
     * @param $key
     * @param $start
     * @param $stop
     * @return array|bool
     */
    public function zrange($key, $start, $stop) {
        $index = '';
        $instance = $this->_connect($key, $index);
    
        if($instance === false) {
            return false;
        }
    
        return $instance->zrange($key, $start, $stop);
    }
    
    /**
     * @param $key
     * @param $min
     * @param $max
     * @return array|bool
     */
    public function zrangebyscore($key, $min, $max) {
        $index = '';
        $instance = $this->_connect($key, $index);
    
        if($instance === false) {
            return false;
        }
    
        return $instance->zrangebyscore($key, $min, $max);
    }
    
    /**
     * @param $key
     * @param $member
     * @return bool|int
     */
    public function zrank($key, $member) {
        $index = '';
        $instance = $this->_connect($key, $index);
    
        if($instance === false) {
            return false;
        }
    
        return $instance->zrank($key, $member);
    }
    
    /**
     * @param $key
     * @param $member
     * @return bool|int
     */
    public function zrem($key, $member) {
        $index = '';
        $instance = $this->_connect($key, $index);
    
        if($instance === false) {
            return false;
        }
    
        return $instance->zrem($key, $member);
    }
    
    /**
     * @param $key
     * @param $min
     * @param $max
     * @return bool|int
     */
    public function zremrangebyscore($key, $min, $max) {
        $index = '';
        $instance = $this->_connect($key, $index);
    
        if($instance === false) {
            return false;
        }
    
        return $instance->zremrangebyscore($key, $min, $max);
    }
    
    /**
     * @param $key
     * @param $member
     * @return bool|float
     */
    public function zscore($key, $member) {
        $index = '';
        $instance = $this->_connect($key, $index);
    
        if($instance === false) {
            return false;
        }
    
        return $instance->zscore($key, $member);
    }
    
    /**
     * @param $key
     * @param $cursor
     * @return bool
     */
    public function zscan($key, $cursor) {
        $index = '';
        $instance = $this->_connect($key, $index);
    
        if($instance === false) {
            return false;
        }
    
        return $instance->zscan($key, $cursor);
    }
    
    /**
     * @param $instance
     * @return mixed
     */
    public function multi($instance) {
        return $instance->multi();
    }
    
    /**
     * @param $instance
     * @param $password
     * @return bool
     */
    public function auth($instance, $password) {
        if($instance === false) {
            return false;
        }
    
        return $instance->auth($password);
    }
    
    /**
     * @param $instance
     * @param $message
     * @return bool
     */
    public function recho($instance, $message) {
        if($instance === false) {
            return false;
        }
    
        return $instance->echo($message);
    }
    
    /**
     * @param $instance
     * @return bool
     */
    public function ping($instance) {
        if($instance === false) {
            return false;
        }
    
        return $instance->ping();
    }
    
    /**
     * @param $instance
     * @return bool
     */
    public function quit($instance) {
        if($instance === false) {
            return false;
        }
    
        return $instance->quit();
    }
    
    /**
     * @param $instance
     * @param $index
     * @return bool
     */
    public function select($instance, $index) {
        if($instance === false) {
            return false;
        }
    
        return true;
    }      
}
