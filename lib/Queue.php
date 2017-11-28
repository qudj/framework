<?php
/**
* 所有真正发送队列的逻辑请放到该类中
**/

class Queue {
    protected static $_instanceList;
    
    /**
    * 获取 RabbitMQ 实例
    * 
    **/
    public static function getRabbitMQInstance($vhost, $exchange, $queue, $routingKeys) {
        $routingKey = is_array($routingKeys) ? join(",", $routingKeys) : $routingKeys;
        $key = "$vhost-$exchange-$queue-$routingKey";

        if(self::$_instanceList && isset(self::$_instanceList[$key])) {
            return self::$_instanceList[$key];
        }

        $connection = C('rabbitmq');
        $connection['vhost'] = $vhost;
        $config = array(
                'connection' => $connection,
                'exchange' => array(
                    'name' => $exchange,
                    'type' => AMQP_EX_TYPE_DIRECT,
                    'flag' => AMQP_DURABLE,
                ),  
                'queue' => array(
                    array(
                        'name' => $queue,
                        'exchange_name' => $exchange,
                        'routing_key' => $routingKeys,
                        )   
                    )   
                );  

        $instance = new Amqp($config);
        self::$_instanceList[$key] = $instance;
        return $instance;
    }   

    public static function getRabbitMQFanoutInstance($vhost, $exchange, $queue, $routingKeys) {
        $routingKey = is_array($routingKeys) ? join(",", $routingKeys) : $routingKeys;
        $key = "$vhost-$exchange-$queue-$routingKey";

        if(self::$_instanceList && isset(self::$_instanceList[$key])) {
            return self::$_instanceList[$key];
        }

        $connection = C('rabbitmq');
        $connection['vhost'] = $vhost;
        $config = array(
                'connection' => $connection,
                'exchange' => array(
                    'name' => $exchange,
                    'type' => AMQP_EX_TYPE_FANOUT,
                    'flag' => AMQP_DURABLE,
                ),
                'queue' => array(
                    array(
                        'name' => $queue,
                        'exchange_name' => $exchange,
                        'routing_key' => $routingKeys,
                        )
                    )
                );

        $instance = new Amqp($config);
        self::$_instanceList[$key] = $instance;
        return $instance;
    }
}
