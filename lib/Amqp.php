<?php
class Amqp{

    // 全局的配置文件
    protected $_config = null;

    // 连接
    protected $_connection = null;
    // 通道
    protected $_channel = null;
    // 队列
    protected $_queueList = null;

    protected $_lastMsg = null;

    const MESSAGE_PERSISTENT_MODE = 2;
    const DEFAULT_PORT = 5672;
    
    public function __construct($config) {
        $this->_config = $config;

        if(!isset($config['connection']) || !is_array($config['connection'])) {
            throw new Exception("There is no connection config");
        }

        $this->_initConnection($config['connection']);

        if(isset($config['exchange']) && is_array($config['exchange'])) {
            $this->_initExchange($config['exchange']); 
        }
        if(isset($config['queue']) && is_array($config['queue'])) {
            $this->_initQueue($config['queue']);
        }
    }

    protected function _initConnection($config) {
        if(!isset($config['host'])) {
            throw new Exception("connection config host is missing"); 
        }
        $hosts = is_array($config['host']) ? $config['host'] : array($config['host']);
        
        $is_connected = false;
        $connection = null;

        //轮询可用服务器
        foreach ($hosts as $host) {
            $options = array(
                'host' => $host,
                'port' => isset($config['port']) ? $config['port'] : self::DEFAULT_PORT,
                'login' => $config['login'],
                'password' => $config['password'],
                'vhost' => $config['vhost']
            );
            //var_dump($options);die;
            $connection = new AMQPConnection($options);
            try {
                $is_connected = $connection->connect();
                if ($is_connected) {
                    $this->_connection = $connection;
                    break;
                }           
            } catch(Exception $e) {
                Logger::ERR("Amqp", ['errinfo'=>"error:".$e->getMessage()]);
            }
        }

        if (!$is_connected) {
            throw new Exception("Connect to queue service failed");
        }

        //create a channel
        $this->_channel = new AMQPChannel($this->_connection);
    }

    protected function _initExchange($config) {
        //only support 'direct' 'fanout' 'topic', not support 'headers'
        if ($config['type'] != AMQP_EX_TYPE_DIRECT
            && $config['type'] != AMQP_EX_TYPE_FANOUT
            && $config['type'] != AMQP_EX_TYPE_TOPIC) {
            throw new Exception("not support for {$config['type']} exchange type");
        }

        //config setting       
        $exchange = new AMQPExchange($this->_channel);
        $exchange->setName($config['name']);
        $exchange->setType($config['type']);
        $exchange->setFlags(AMQP_DURABLE);

        if (!$exchange->declareExchange()) {
            throw new Exception("Declare the exchange failed");
        }
        $this->_exchange = $exchange;
    }

    /**
    *
    * @param array $config , key can be: name, routing_key, exchange_name, delay
    *   name: string queue name
    *   routing_key: string | array 
    *   exchange_name : string 
    *   delay : int seconds to delay
    *
    * @return
    **/
    protected function _initQueue($config) {
        foreach($config as $cfg) {
            if(!isset($cfg['name']) || !isset($cfg['routing_key']) || !isset($cfg['exchange_name'])) {
                throw new Exception("name/routing_key/exchange_name cannot be empty");
            }
            $queue = new AMQPQueue($this->_channel);

            //config setting
            $queue->setName($cfg['name']);
            $queue->setFlags(AMQP_DURABLE);
            //Returns the message count
            $queue->declareQueue();

            $keys = isset($cfg['routing_key']) ? $cfg['routing_key'] : array();
            $keys = is_array($keys) ? $keys : array($keys);
            foreach($keys as $key) {
                $queue->bind($cfg['exchange_name'], $key);
            }
            
            $this->_queueList[$cfg['name']] = $queue;
        }
    }
    /**
    *
    * @param string $key  routing key
    * @param mixed $value
    * @param int $pri
    * @param int $delay seconds
    * @param int $ttr
    *
    * @return
    *
    **/
    public function push($key, $value, $pri = null, $delay = null, $ttr = null) {
        if(!$this->_exchange instanceof AMQPExchange) {
            throw new Exception("exchange config is empty");
        }

        $value = serialize($value);
        $options = array(
            'delivery_mode' => self::MESSAGE_PERSISTENT_MODE
            );
        $routingKey = $key;
        if($delay > 0) {
            $routingKey = "delay.$key.$delay";
            $exchangeName = $this->_exchange->getName();
            $q = new AMQPQueue($this->_channel);
            $q->setName($routingKey);
            $q->setFlags(AMQP_DURABLE);
            $args = array(
                    'x-dead-letter-exchange' => $exchangeName,
                    'x-dead-letter-routing-key' => $key,
                    'x-message-ttl' => intval($delay * 1000),
                    );
            $q->setArguments($args);
            $q->declareQueue();
            $q->bind($exchangeName, $routingKey);
        }
        return $this->_exchange->publish($value, $routingKey, AMQP_NOPARAM, $options);
    }

    /**
    * pop key from the queue
    *
    * @param string $key  queue name
    *
    * @return array array($key, $value)
    **/
    public function pop($key){
        $this->_lastMsg = null;
        $queue = $this->_queueList && isset($this->_queueList[$key]) ? $this->_queueList[$key] : null;

        if(!$queue) {
            throw new Exception("queue config is empty");
        }

        $msg = $queue->get(AMQP_AUTOACK);

        if(empty($msg)) {
            return false;
        }

        $this->_lastMsg = $msg;

        return array($msg->getRoutingKey(), unserialize($msg->getBody()));
    }

    public function begin() {
        $this->_channel->startTransaction();
    }

    public function commit() {
        $this->_channel->commitTransaction();
    }

    public function rollback() {
        $this->_channel->rollbackTransaction();
    }

    /**
    * 使用阻塞+回调函数的方式来处理消息
    * XXX 请仔细阅读代码中的 ABCDE 5 段注释，了解该函数的消息处理机制
    *
    * @param string   $key queue name
    * @param callable $callback
    * @param array    $options
    *
    * @return
    **/
    public function consume($key, callable  $callback, $options = array()) {
        if(empty($key)) {
            throw new Exception("key cann't be empty:" . __FILE__);
        }

        $times = 0;
        $begin = time();
        $queue = $this->_queueList && isset($this->_queueList[$key]) ? $this->_queueList[$key] : null;

        if($queue === null) {
            throw new Exception("cann't find queue for $key in " . __FILE__);
        }
        
        $queue->consume(function($envelope, $q) use (&$times, $begin, $callback, $options) {
            $errorHandler = isset($options['error_handler']) ? $options['error_handler'] : '';
            $maxTimes = isset($options['max_times']) ? (int) $options['max_times'] : 0;
            $maxTimeLength = isset($options['max_time_length']) ? (int) $options['max_time_length'] : 0;
            $times ++;
            $key = $envelope->getRoutingKey();
            $data = unserialize($envelope->getBody());

            //处理消息次数超过设定，则将消息重新放回队列，退出进程
            if($maxTimes > 0 && $times > $maxTimes) {
                $q->nack($envelope->getDeliveryTag(), AMQP_REQUEUE);
                return false;
            }
            //处理消息时间超过设定，则将消息重新放回队列，退出进程
            if($maxTimeLength > 0 && time() - $begin > $maxTimeLength) {
                $q->nack($envelope->getDeliveryTag(), AMQP_REQUEUE);
                return false;
            }

            try {
                //返回 false, 按照 官方 consume 返回值处理机制， 正常退出，其他情况，不退出
                $result = $callback($key, $data);
                if($result){
                    $q->ack($envelope->getDeliveryTag());
                }else{
                    $q->nack($envelope->getDeliveryTag(), AMQP_REQUEUE);
                }
                return true;
            //明确异常，将消息放回队列，不退出进程
            } catch (Exception $e) {
                $q->nack($envelope->getDeliveryTag(), AMQP_REQUEUE);
                Logger::ERR("AMQP", ['ex'=>$e->getMessage()]);
                return true;
            }
        });
    }
}
