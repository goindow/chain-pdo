<?php
require_once('./punit/PunitAssert.php');
require_once('../ChainPDOFactory.php');

class TestChainPDOFactory {

    private static $config = [
        [
            'DB_HOST' => 'localhost',
            'DB_NAME' => 'test1',
            'DB_USERNAME' => 'root',
            'DB_PASSWORD' => '123456'
        ],[
            'DB_HOST' => '127.0.0.1',
            'DB_NAME' => 'test2',
            'DB_USERNAME' => 'root',
            'DB_PASSWORD' => '123456'
        ]
    ];

    private $chainPdoFactory = null;

    public function __construct() { $this->chainPdoFactory =  ChainPDOFactory::build(self::$config); }

    public function testBuild() {
        PunitAssert::assertInstanceof($this->chainPdoFactory, 'ChainPdoFactory');
    }

    public function testGetDbs() {
        $dbs = $this->chainPdoFactory->getDbs();
        PunitAssert::assertEquals(count($dbs), count(self::$config));
    }

    public function testGetDdDefault() {
        $db = $this->chainPdoFactory->getDb();
        PunitAssert::assertInstanceof($db, 'ChainPDO');
        PunitAssert::assertEquals($db->getConfig()['DB_NAME'], self::$config[0]['DB_NAME']);
    }

    public function testGetDdByIndex() {
        $db = $this->chainPdoFactory->getDb(1);
        PunitAssert::assertInstanceof($db, 'ChainPDO');
        PunitAssert::assertEquals($db->getConfig()['DB_NAME'], self::$config[1]['DB_NAME']);
    }

}