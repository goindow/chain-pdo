<?php
require_once('./punit/PunitAssert.php');
require_once('../ChainPDOFactory.php');

class TestChainPDO {

    private static $config = [
        'DB_HOST' => '127.0.0.1',
        'DB_NAME' => 'test1',
        'DB_USERNAME' => 'root',
        'DB_PASSWORD' => '123456'
    ];

    private $chainPdoFactory = null;

    private $db = null;

    public function __construct() {
        $this->chainPdoFactory =  ChainPDOFactory::build(self::$config);
        $this->db = $this->chainPdoFactory->getDb();
    }

    public function before() { $this->db->sql("DELETE FROM `user`"); }

    public function testSql_singleInsert() {
        $sql = "INSERT INTO `user` (user_name) VALUES ('zhangsan')";
        $insertId = $this->db->sql($sql);
        PunitAssert::assertString($insertId);
    }

    public function testSql_multiInsert() {
        $sql = "INSERT INTO `user` (user_name) VALUES ('lisi'),('wangwu')";
        $lineCount = $this->db->sql($sql);
        PunitAssert::assertInt($lineCount);
        PunitAssert::assertStrictEquals($lineCount, 2);
    }

    public function testSql_update() {
        $sqlInsert = "INSERT INTO `user` (user_name) VALUES ('zhangsan')";
        $insertId = $this->db->sql($sqlInsert);
        $sqlUpdate = "UPDATE `user` SET user_name = 'lisi ' WHERE id = {$insertId}";
        $lineCount = $this->db->sql($sqlUpdate);
        PunitAssert::assertEquals($lineCount, 1);
    }

    public function testSql_delete() {
        $sqlInsert = "INSERT INTO `user` (user_name) VALUES ('zhangsan')";
        $insertId = $this->db->sql($sqlInsert);
        $sqlDelete = "DELETE FROM `user` WHERE id = {$insertId}";
        $lineCount = $this->db->sql($sqlDelete);
        PunitAssert::assertEquals($lineCount, 1);
    }

    public function testSql_select() {
        $sqlInsertMulti = "INSERT INTO `user` (user_name) VALUES ('zhangsan'),('lisi'),('wangwu')";
        $this->db->sql($sqlInsertMulti);
        $sqlSelect = "SELECT id FROM `user`";
        $users = $this->db->sql($sqlSelect);
        PunitAssert::assertEquals(count($users), 3);
    }

    // todo: before() & after() 

}