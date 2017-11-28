<?php
$___global_config = array(
        'indexaction' => 'IndexAction',
        'indexmethod' => 'index',

		//数据库设置
        'database' => array(
            "test" => array(
                "master" => array(
    				'charset' => 'utf8',
    				'port' => '3306',
    				'server' => 'localhost',
    				'username' => 'root',
    				'password' => '123456',
                ),
                "slave" => array(
    				'charset' => 'utf8',
    				'port' => '3306',
    				'server' => ['localhost', '127.0.0.1'],
    				'username' => 'root',
    				'password' => '123456',
                 ),
             ),
        ),
    
        'redis'=>array(
            'host' => ['127.0.0.1'],
        ),
    
        "rabbitmq" => array(
            'host' => array(
                '127.0.0.1',
                'localhost',
            ),
            'port' => 5672,
            'login' => 'root',
            'password' => '123456',
        ),
	);

return $___global_config;