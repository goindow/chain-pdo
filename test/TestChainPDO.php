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

    /************************    原生    ************************/

    public function testSql_insert_single() {
        $sql = "INSERT INTO user (`user_name`) VALUES ('zhangsan')";
        $insertId = $this->db->sql($sql);
        PunitAssert::assertString($insertId);
    }

    public function testSql_insert_multi() {
        $sql = "INSERT INTO user (`user_name`) VALUES ('lisi'),('wangwu')";
        $lineCount = $this->db->sql($sql);
        PunitAssert::assertInt($lineCount);
        PunitAssert::assertStrictEquals($lineCount, 2);
    }

    public function testSql_update() {
        $sqlInsert = "INSERT INTO user (`user_name`) VALUES ('zhangsan')";
        $insertId = $this->db->sql($sqlInsert);
        $sqlUpdate = "UPDATE user SET user_name = 'lisi ' WHERE id = {$insertId}";
        $lineCount = $this->db->sql($sqlUpdate);
        PunitAssert::assertEquals($lineCount, 1);
    }

    public function testSql_delete() {
        $sqlInsert = "INSERT INTO user (`user_name`) VALUES ('zhangsan')";
        $insertId = $this->db->sql($sqlInsert);
        $sqlDelete = "DELETE FROM user WHERE id = {$insertId}";
        $lineCount = $this->db->sql($sqlDelete);
        PunitAssert::assertEquals($lineCount, 1);
    }

    public function testSql_select() {
        $sqlInsertMulti = "INSERT INTO user (`user_name`) VALUES ('zhangsan'),('lisi'),('wangwu')";
        $this->db->sql($sqlInsertMulti);
        $sqlSelect = "SELECT id FROM user";
        $users = $this->db->sql($sqlSelect);
        PunitAssert::assertEquals(count($users), 3);
    }

    /************************    事务    ************************/

    public function testBeginTransaction_fail() {
        $this->db->beginTransaction();
        try {
            $sqlInsert1 = "INSERT INTO user (`user_name`) VALUES ('zhangsan')";
            $this->db->sql($sqlInsert1);
            $sqlInsert2 = "INSERT INTO no_exists (`not_exists`) VALUES ('no_exists')";
            $this->db->sql($sqlInsert2);
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            $sqlSelect = "SELECT id FROM user";
            $users = $this->db->sql($sqlSelect);
            PunitAssert::assertEquals(count($users), 0);
        } 
    }

    public function testBeginTransaction() {
        $this->db->beginTransaction();
        try {
            $sqlInsert1 = "INSERT INTO user (`user_name`) VALUES ('zhangsan')";
            $this->db->sql($sqlInsert1);
            $sqlInsert2 = "INSERT INTO user (`user_name`) VALUES ('lisi')";
            $this->db->sql($sqlInsert2);
            $this->db->commit();
            $sqlSelect = "SELECT id FROM user";
            $users = $this->db->sql($sqlSelect);
            PunitAssert::assertEquals(count($users), 2);
        } catch (Exception $e) {
            $this->db->rollBack();
        } 
    }

    /************************    链式 INSERT   ************************/

    public function testInsert_onlyReturnSql() {
        $user = ['user_name' => 'zhangsan'];
        $sql = $this->db->data($user)->insert('user', true);
        PunitAssert::assertStrictEquals($sql, 'INSERT INTO user  (`user_name`) VALUES ("zhangsan")');
    }

    public function testInsert_fail_param1Missing() {
        try {
            $result = $this->db->insert('user');
        } catch (Exception $e) {
            PunitAssert::assertStrictEquals($e->getMessage(), 'Data missing first parameter.');
        }
    }

    public function testInsert_fail_param1TypeError_array() {
        try {
            $result = $this->db->data("zhangsan")->insert('user');
        } catch (Exception $e) {
            PunitAssert::assertStrictEquals($e->getMessage(), 'Data type error, the first parameter must be an array.');
        }
    }

    public function testInsert_single_fail_param1TypeError_assocArray() {
        try {
            $result = $this->db->data([['user_name' => 'zhangsan']])->insert('user');
        } catch (Exception $e) {
            PunitAssert::assertStrictEquals($e->getMessage(), 'Data type error, the first parameter must be an associative array.');
        }
    }

    public function testInsert_single() {
        $user = [
            'user_name' => 'zhangsan'
        ];
        $result = $this->db->data($user)->insert('user');
        PunitAssert::assertGt($result, 0);
    }

    public function testInsert_multi_fail_dataParam1TypeError_normalArray() {
        try {
            $result = $this->db->data(['user_nmae' => 'zhangsan'], ['zhangsan', 'lisi'])->insert('user');
        } catch (Exception $e) {
            PunitAssert::assertStrictEquals($e->getMessage(), 'Data type error, the first parameter must be an normal array.');
        }
    }

    public function testInsert_multi_fail_dataParam2TypeError_array() {
        try {
            $result = $this->db->data(['id', 'user_name'], "zhangshan")->insert('user');
        } catch (Exception $e) {
            PunitAssert::assertStrictEquals($e->getMessage(), 'Data type error, the second parameter must be an array.');
        }
    }

    public function testInsert_multi_fail_dataParam2TypeError_normalArray() {
        try {
            $result = $this->db->data(['id', 'user_name'], ['user_name' => 'zhgangsan'])->insert('user');
        } catch (Exception $e) {
            PunitAssert::assertStrictEquals($e->getMessage(), 'Data type error, the second parameter must be an normal array.');
        }
    }

    public function testInsert_multi_fail_dataParam2ElementsTypeError_normalArray() {
        try {
            $result = $this->db->data(['id', 'user_name'], [['id' => 1, 'username' => 'zhangsan']])->insert('user');
        } catch (Exception $e) {
            PunitAssert::assertStrictEquals($e->getMessage(), 'Data type error, the element of the second parameter must be an normal array.');
        }
    }

    public function testInsert_multi() {
        $fields = ['id', 'user_name'];
        $users = [
            [1, 'zhangsan'],
            [2, 'lisi'],
            [3, 'wangwu']
        ];
        $result = $this->db->data($fields, $users)->insert('user');
        PunitAssert::assertEquals($result, 3);
    }

    /************************    链式 DELETE   ************************/

    public function testDelete_withWhereArray() {
        $this->db->data(['user_name'], [['zhangsan'], ['lisi'], ['wangwu']])->insert('user');
        $result = $this->db->where(['user_name' => 'lisi'])->delete('user');
        PunitAssert::assertStrictEquals($result, 1);
    }

    public function testDelete_withWhereString() {
        $this->db->data(['user_name'], [['zhangsan'], ['lisi'], ['wangwu']])->insert('user');
        $result = $this->db->where("user_name in ('zhangsan', 'wangwu')")->delete('user');
        PunitAssert::assertStrictEquals($result, 2);
    }

    public function testDelete_withLimit_fail_limitTypeError() {
        $this->db->data(['user_name'], [['zhangsan'], ['lisi'], ['wangwu']])->insert('user');
        try {
            $result = $this->db->limit('2, 1')->delete('user');
        } catch (Exception $e) {
            PunitAssert::assertStrictEquals($e->getMessage(), 'Limit type error, must be an integer.');
        }
    }

    public function testDelete_withLimit() {
        $this->db->data(['user_name'], [['zhangsan'], ['lisi'], ['wangwu']])->insert('user');
        $result = $this->db->limit(1)->delete('user');  // 'zhangsan' was deleted
        PunitAssert::assertStrictEquals($result, 1);
    }

    public function testDelete_withOrder() {
        $this->db->data(['user_name'], [['zhangsan'], ['lisi'], ['wangwu']])->insert('user');
        $result = $this->db->order('id desc')->limit(1)->delete('user');
        PunitAssert::assertStrictEquals($result, 1); // 'wangwu' was deleter
        // todo: assert 'wangwu' was deleted
    }

    public function testDelete() {
        $this->db->data(['user_name'], [['zhangsan'], ['lisi'], ['wangwu']])->insert('user');
        $result = $this->db->delete('user');
        PunitAssert::assertStrictEquals($result, 3);
    }

}

/*$case = new TestChainPDO();
$case->before();
$case-> testInsert_multi();*/


