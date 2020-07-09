<?php
require_once('./punit/PunitAssert.php');
require_once('../ChainPDOFactory.php');

class TestChainPDO {

    private static $config = [
        'DB_HOST' => '127.0.0.1',
        'DB_NAME' => 'test2',
        'DB_USERNAME' => 'root',
        'DB_PASSWORD' => '123456'
    ];

    private $chainPdoFactory = null;

    private $db = null;

    public function __construct() {
        $this->chainPdoFactory =  ChainPDOFactory::build(self::$config);
        $this->db = $this->chainPdoFactory->getDb();
    }

}