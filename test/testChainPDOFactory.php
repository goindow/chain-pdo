<?php
    require_once("../ChainPDOFactory.php");

    $config1 = [
        'DB_HOST' => 'localhost',
        'DB_NAME' => 'hxcq',
        'DB_USERNAME' => 'root',
        'DB_PASSWORD' => '123456'
    ];

    $config2 = [
        [
            'DB_HOST' => 'localhost',
            'DB_NAME' => 'test_mybatis',
            'DB_USERNAME' => 'root',
            'DB_PASSWORD' => '123456'
        ],[
            'DB_HOST' => 'localhost',
            'DB_NAME' => 'hxcq',
            'DB_USERNAME' => 'root',
            'DB_PASSWORD' => '123456'
        ]
    ];

    $pdoFactory = ChainPDOFactory::build($config2);
    $dbs = $pdoFactory->getDbs(); 
    var_dump($dbs);

    echo "\n";

    $db = $pdoFactory->getDb(0);
    var_dump($db);