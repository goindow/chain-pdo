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

    public function before() {
        $this->db->delete('user');
        $this->db->delete('order');
    }

    /************************    原生    ************************/

    public function testSql_insert_single() {
        $sql = "INSERT INTO `user` (`user_name`) VALUES ('zhangsan')";
        $insertId = $this->db->sql($sql);
        PunitAssert::assertString($insertId);
    }

    public function testSql_insert_multi() {
        $sql = "INSERT INTO `user` (`user_name`) VALUES ('lisi'),('wangwu')";
        $lineCount = $this->db->sql($sql);
        PunitAssert::assertInt($lineCount);
        PunitAssert::assertEquals($lineCount, 2);
    }

    public function testSql_update() {
        $sqlInsert = "INSERT INTO `user` (`user_name`) VALUES ('zhangsan')";
        $insertId = $this->db->sql($sqlInsert);
        $sqlUpdate = "UPDATE `user` SET `user_name`='lisi' WHERE `id`={$insertId}";
        $lineCount = $this->db->sql($sqlUpdate);
        PunitAssert::assertEquals($lineCount, 1);
    }

    public function testSql_delete() {
        $sqlInsert = "INSERT INTO `user` (`user_name`) VALUES ('zhangsan')";
        $insertId = $this->db->sql($sqlInsert);
        $sqlDelete = "DELETE FROM `user` WHERE id={$insertId}";
        $lineCount = $this->db->sql($sqlDelete);
        PunitAssert::assertEquals($lineCount, 1);
    }

    public function testSql_select() {
        $sqlInsertMulti = "INSERT INTO `user` (`user_name`) VALUES ('zhangsan'),('lisi'),('wangwu')";
        $this->db->sql($sqlInsertMulti);
        $sqlSelect = "SELECT `id` FROM `user`";
        $users = $this->db->sql($sqlSelect);
        PunitAssert::assertEquals(count($users), 3);
    }

    /************************    事务    ************************/

    public function testBeginTransaction_fail() {
        $this->db->beginTransaction();
        try {
            $sqlInsert1 = "INSERT INTO `user` (`user_name`) VALUES ('zhangsan')";
            $this->db->sql($sqlInsert1);
            $sqlInsert2 = "INSERT INTO `no_exists` (`not_exists`) VALUES ('no_exists')";
            $this->db->sql($sqlInsert2);
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollback();
            $sqlSelect = "SELECT `id` FROM `user`";
            $users = $this->db->sql($sqlSelect);
            PunitAssert::assertEquals(count($users), 0);
        } 
    }

    public function testBeginTransaction() {
        $this->db->beginTransaction();
        try {
            $sqlInsert1 = "INSERT INTO `user` (`user_name`) VALUES ('zhangsan')";
            $this->db->sql($sqlInsert1);
            $sqlInsert2 = "INSERT INTO `user` (`user_name`) VALUES ('lisi')";
            $this->db->sql($sqlInsert2);
            $this->db->commit();
            $sqlSelect = "SELECT `id` FROM `user`";
            $users = $this->db->sql($sqlSelect);
            PunitAssert::assertEquals(count($users), 2);
        } catch (Exception $e) {
            $this->db->rollback();
        } 
    }

    /************************    链式 INSERT   ************************/

    public function testInsert_onlyReturnSql() {
        $user = ['user_name' => 'zhangsan'];
        $sql = $this->db->data($user)->insert('user', true);
        PunitAssert::assertEquals($sql, 'INSERT INTO `user` (`user_name`) VALUES ("zhangsan")');
    }

    public function testInsert_fail_dataParam1Missing() {
        try {
            $result = $this->db->insert('user');
        } catch (Exception $e) {
            PunitAssert::assertEquals($e->getMessage(), 'Data missing first parameter.');
        }
    }

    public function testInsert_fail_dataParam1TypeError_array() {
        try {
            $result = $this->db->data("zhangsan")->insert('user');
        } catch (Exception $e) {
            PunitAssert::assertEquals($e->getMessage(), 'Data type error, the first parameter must be an array.');
        }
    }

    public function testInsert_single_fail_dataParam1TypeError_assocArray() {
        try {
            $result = $this->db->data([['user_name' => 'zhangsan']])->insert('user');
        } catch (Exception $e) {
            PunitAssert::assertEquals($e->getMessage(), 'Data type error, the first parameter must be an associative array.');
        }
    }

    public function testInsert_single() {
        $result = $this->db->data(['user_name' => 'zhangsan'])->insert('user');
        PunitAssert::assertGt($result, 0);
    }

    public function testInsert_multi_fail_dataParam1TypeError_normalArray() {
        try {
            $result = $this->db->data(['user_nmae' => 'zhangsan'], ['zhangsan', 'lisi'])->insert('user');
        } catch (Exception $e) {
            PunitAssert::assertEquals($e->getMessage(), 'Data type error, the first parameter must be an normal array.');
        }
    }

    public function testInsert_multi_fail_dataParam2TypeError_array() {
        try {
            $result = $this->db->data(['id', 'user_name'], "zhangshan")->insert('user');
        } catch (Exception $e) {
            PunitAssert::assertEquals($e->getMessage(), 'Data type error, the second parameter must be an array.');
        }
    }

    public function testInsert_multi_fail_dataParam2TypeError_normalArray() {
        try {
            $result = $this->db->data(['id', 'user_name'], ['user_name' => 'zhgangsan'])->insert('user');
        } catch (Exception $e) {
            PunitAssert::assertEquals($e->getMessage(), 'Data type error, the second parameter must be an normal array.');
        }
    }

    public function testInsert_multi_fail_dataParam2ElementsTypeError_normalArray() {
        try {
            $result = $this->db->data(['id', 'user_name'], [['id' => 1, 'username' => 'zhangsan']])->insert('user');
        } catch (Exception $e) {
            PunitAssert::assertEquals($e->getMessage(), 'Data type error, the element of the second parameter must be an normal array.');
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
        PunitAssert::assertEquals($result, 1);
    }

    public function testDelete_withWhereString() {
        $this->db->data(['user_name'], [['zhangsan'], ['lisi'], ['wangwu']])->insert('user');
        $result = $this->db->where("`user_name` in ('zhangsan', 'wangwu')")->delete('user');
        PunitAssert::assertEquals($result, 2);
    }

    public function testDelete_withLimit_fail_limitTypeError() {
        $this->db->data(['user_name'], [['zhangsan'], ['lisi'], ['wangwu']])->insert('user');
        try {
            $result = $this->db->limit('2,1')->delete('user');
        } catch (Exception $e) {
            PunitAssert::assertEquals($e->getMessage(), 'Limit type error, must be an integer.');
        }
    }

    public function testDelete_withLimit() {
        $this->db->data(['user_name'], [['zhangsan'], ['lisi'], ['wangwu']])->insert('user');
        $result = $this->db->limit(1)->delete('user');  // 'zhangsan' was deleted
        PunitAssert::assertEquals($result, 1);
    }

    public function testDelete_withOrder() {
        $this->db->data(['user_name'], [['zhangsan'], ['lisi'], ['wangwu']])->insert('user');
        $result = $this->db->order('id desc')->limit(1)->delete('user');    // 'wangwu' was deleted
        PunitAssert::assertEquals($result, 1);
        // todo: assert 'wangwu' was deleted
    }

    public function testDelete() {
        $this->db->data(['user_name'], [['zhangsan'], ['lisi'], ['wangwu']])->insert('user');
        $result = $this->db->delete('user');
        PunitAssert::assertEquals($result, 3);
    }

    /************************    链式 UPDATE   ************************/

    public function testUpdate_fail_dataMissing() {
        try {
            $result = $this->db->update('user');
        } catch (Exception $e) {
            PunitAssert::assertEquals($e->getMessage(), 'Data missing first parameter.');
        }
    }

    public function testUpdate_fail_dataTypeError_array() {
        try {
            $result = $this->db->data("zhangsan")->update('user');
        } catch (Exception $e) {
            PunitAssert::assertEquals($e->getMessage(), 'Data type error, the first parameter must be an array.');
        }
    }
    public function testUpdate_withWhereArray() {
        $this->db->data(['user_name'], [['zhangsan'], ['lisi'], ['lisi']])->insert('user');
        $result = $this->db->where(['user_name' => 'lisi'])->data(['user_name' => 'zhaoliu'])->update('user');
        PunitAssert::assertEquals($result, 2);
    }

    public function testUpdate_withWhereString() {
        $this->db->data(['user_name'], [['zhangsan'], ['lisi'], ['wangwu']])->insert('user');
        $result = $this->db->where("`user_name`='zhangsan'")->data(['user_name' => 'zhaoliu'])->update('user');
        PunitAssert::assertEquals($result, 1);
    }

    public function testUpdate_withLimit_fail_limitTypeError() {
        $this->db->data(['user_name'], [['zhangsan'], ['lisi'], ['wangwu']])->insert('user');
        try {
            $result = $this->db->data(['user_name' => 'zhaoliu'])->limit('2,1')->update('user');
        } catch (Exception $e) {
            PunitAssert::assertEquals($e->getMessage(), 'Limit type error, must be an integer.');
        }
    }

    public function testUpdate_withLimit() {
        $this->db->data(['user_name'], [['zhangsan'], ['lisi'], ['wangwu']])->insert('user');
        $result = $this->db->data(['user_name' => 'zhaoliu'])->limit(1)->update('user');    // 'zhangsan' was updated
        PunitAssert::assertEquals($result, 1);
    }

    public function testUpdate_withOrder() {
        $this->db->data(['user_name'], [['zhangsan'], ['lisi'], ['wangwu']])->insert('user');
        $result = $this->db->data(['user_name' => 'zhaoliu'])->order('id desc')->limit(1)->update('user');    // 'wangwu' was updated
        PunitAssert::assertEquals($result, 1);
        // todo: assert 'wangwu' was updated
    }

    public function testUpdate() {
        $this->db->data(['user_name'], [['zhangsan'], ['lisi'], ['wangwu']])->insert('user');
        $result = $this->db->data(['user_name' => 'zhaoliu'])->update('user');
        PunitAssert::assertEquals($result, 3);
    }

    /************************    链式 SELECT   ************************/

    public function testSelect_withDistinct() {
        $this->db->data(['user_name'], [['zhangsan'], ['lisi'], ['lisi']])->insert('user');
        $result = $this->db->field('user_name')->distinct()->select('user');
        PunitAssert::assertEquals(count($result), 2);
    }

    public function testSelect_withLimit() {
        $this->db->data(['user_name'], [['zhangsan'], ['lisi'], ['wangwu'], ['zhaoliu']])->insert('user');
        $result = $this->db->limit('3,1')->select('user');    // 'zhaoliu' was selected
        PunitAssert::assertEquals(count($result), 1);
        PunitAssert::assertEquals($result[0]['user_name'], 'zhaoliu');
    }

    public function testSelect_withOrder() {
        $this->db->data(['user_name'], [['zhangsan'], ['lisi'], ['wangwu']])->insert('user');
        $result = $this->db->order('id desc')->limit(1)->select('user');    // 'wangwu' was selected
        PunitAssert::assertEquals(count($result), 1);
        PunitAssert::assertEquals($result[0]['user_name'], 'wangwu');
    }

    public function testSelect_withFieldArray() {
        $this->db->data(['user_name'], [['zhangsan'], ['lisi'], ['wangwu']])->insert('user');
        $result = $this->db->field(['id'])->select('user');
        PunitAssert::assertEquals(count(array_keys($result[0])), 1);
        PunitAssert::assertEquals(array_keys($result[0])[0], 'id');
    }

    public function testSelect_withFieldString() {
        $this->db->data(['user_name'], [['zhangsan'], ['lisi'], ['wangwu']])->insert('user');
        $result = $this->db->field('`user_name`')->select('user');
        PunitAssert::assertEquals(count(array_keys($result[0])), 1);
        PunitAssert::assertEquals(array_keys($result[0])[0], 'user_name');
    }

    public function testSelect_withWhereArray() {
        $this->db->data(['user_name'], [['zhangsan'], ['lisi'], ['lisi']])->insert('user');
        $result = $this->db->where(['user_name' => 'lisi'])->select('user');
        PunitAssert::assertEquals(count($result), 2);
    }

    public function testSelect_withWhereString() {
        $this->db->data(['user_name'], [['zhangsan'], ['lisi'], ['wangwu']])->insert('user');
        $result = $this->db->where("`user_name` like '%an%'")->select('user');
        PunitAssert::assertEquals(count($result), 2);
    }

    public function testSelect_withGroupArray() {
        $this->db->data(['user_id', 'price', 'status'], [[1, 100, 1], [1, 100, 2], [1, 100, 2], [2, 100, 1]])->insert('order');
        $result = $this->db->field(['user_id', 'status', 'sum(price) as price'])
                           ->group(['user_id', 'status'])
                           ->select('order');
        PunitAssert::assertEquals(count($result), 3);
        PunitAssert::assertEquals($result[0]['price'], 100);
        PunitAssert::assertEquals($result[1]['price'], 200);
        PunitAssert::assertEquals($result[2]['price'], 100);
    }

    public function testSelect_withGroupString() {
        $this->db->data(['user_id', 'price', 'status'], [[1, 100, 1], [1, 100, 2], [1, 100, 2], [2, 100, 1]])->insert('order');
        $result = $this->db->field(['user_id', 'status', 'sum(price) as price'])
                           ->group('user_id,status')
                           ->select('order');
        PunitAssert::assertEquals(count($result), 3);
        PunitAssert::assertEquals($result[0]['price'], 100);
        PunitAssert::assertEquals($result[1]['price'], 200);
        PunitAssert::assertEquals($result[2]['price'], 100);
    }

    public function testSelect_withHavingArray() {
        $this->db->data(['user_id', 'price', 'status'], [[1, 100, 1], [1, 100, 2], [1, 100, 2], [2, 100, 1]])->insert('order');
        $result = $this->db->field(['user_id', 'status', 'sum(price) as price'])
                           ->group(['user_id', 'status'])
                           ->having(['price' => 100])
                           ->select('order');
        PunitAssert::assertEquals(count($result), 2);
        PunitAssert::assertEquals($result[0]['user_id'], 1);
        PunitAssert::assertEquals($result[1]['user_id'], 2);
    }

    public function testSelect_withHavingString() {
        $this->db->data(['user_id', 'price', 'status'], [[1, 100, 1], [1, 100, 2], [1, 100, 2], [2, 100, 1]])->insert('order');
        $result = $this->db->field(['user_id', 'status', 'sum(price) as price'])
                           ->group(['user_id', 'status'])
                           ->having('price >= 200')
                           ->select('order');
        PunitAssert::assertEquals(count($result), 1);
        PunitAssert::assertEquals($result[0]['status'], 2);
    }

    public function testSelect_withJoin() {
        $who = ['user_name' => 'zhangsan'];
        $this->db->beginTransaction();
        try {
            $result = $this->db->data($who)->insert('user');
            if ($result <= 0) throw new Exception();
            $user = $this->db->where($who)->select('user');
            if (empty($user)) throw new Exception();
            $result = $this->db->data(['user_id' => $user[0]['id'], 'price' => 100, 'status' => 1])->insert('order');
            if ($result <= 0) throw new Exception();
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollback(); 
            throw new Exception();
        }
        // SELECT o.id,o.price,o.status,u.id as user_id,u.user_name FROM `order` as o left join user as u on u.id = o.user_id
        $result = $this->db->field(['o.id', 'o.price', 'o.status', 'u.id as user_id', 'u.user_name'])
                           ->join('left join user as u on u.id = o.user_id')
                           ->select('`order` as o');
        PunitAssert::assertEquals(count($result), 1);
        PunitAssert::assertEquals($result[0]['user_id'], $user[0]['id']); 
    }

}