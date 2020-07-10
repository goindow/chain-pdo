<?php
class ChainPDO {

    // 数据库配置
    private $config = [];
    // PDO 对象
    private $pdo = null;
    // PDOStatement 对象
    private $stmt = null;
    // 链式操作条件集合
    private $options = [];
    // 最后/待执行的 sql 语句
    private $sql = '';

    public function __construct($config) {
        $this->config = $config;
        try {
            $this->pdo = new PDO(
                $this->config['DB_DSN'], 
                $this->config['DB_USERNAME'], 
                $this->config['DB_PASSWORD'], 
                $this->config['DB_OPTIONS']
            );
            $this->pdo->exec('SET NAMES ' . $this->config['DB_CHAR']);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function getPdo() { return $this->pdo; }

    public function getSql() { return $this->sql; }

    public function getConfig() { return $this->config; }

    /*************************    事务    *************************/

    public function beginTransaction() { $this->pdo->beginTransaction(); }

    public function commit() { $this->pdo->commit(); }

    public function rollBack() { $this->pdo->rollBack(); }
    
    /*************************    链式条件    *************************/

    public function distinct() { $this->options['distinct'] = true; return $this; }

    public function field($field) { $this->options['field'] = $field; return $this; }

    public function join($join) { $this->options['join'] = trim($join); return $this; }

    public function where($where) { $this->options['where'] = $where; return $this; }

    public function group($group) { $this->options['group'] = $group; return $this; }

    public function having($having) { $this->options['having'] = $having; return $this; }

    public function order($order) { $this->options['order'] = trim($order); return $this; }

    public function limit($limit) { $this->options['limit'] = trim($limit); return $this; }

    public function data($dataOrFields, $data = []) { $this->options['data'] = [ "dataOrFields" => $dataOrFields, "data" => $data]; return $this; }

    /*************************    链式 CURD    *************************/

    /**
     * 插入 - 支持单条插入（关联数组）和批量插入（普通数组，元素为关联数组）
     *
     * @param onlyReturnSql 为 true 时，只解析返回 sql，并不执行，下同
     */
    public function insert($table, $onlyReturnSql = false) {
        $sql = "INSERT INTO {$table} " . $this->parseDataForInsert();
        // cleanAndReturnSql($sql)，由于属性的清理工作是放在 execute/query 方法中，当 $onlyReturnSql = true 时未调用
        return $onlyReturnSql ? $this->cleanAndReturnSql($sql) : $this->execute($sql);
    }

    public function delete($table, $onlyReturnSql = false) {
        $sql = "DELETE FROM {$table} " . $this->parseWhere()
                                       . $this->parseOrder()
                                       . $this->parseLimit();
        return $onlyReturnSql ? $this->cleanAndReturnSql($sql) : $this->execute($sql);
    }

    public function update($table, $onlyReturnSql = false) {
        $sql = "UPDATE {$table} SET " . $this->parseDataForUpdate()
                                      . $this->parseWhere()
                                      . $this->parseOrder()
                                      . $this->parseLimit();
        return $onlyReturnSql ? $this->cleanAndReturnSql($sql) : $this->execute($sql);
    }

    public function select($table, $onlyReturnSql = false) {
        $sql = "SELECT " . $this->parseDistinct()
                         . $this->parseField() . $table
                         . $this->parseJoin()
                         . $this->parseWhere()
                         . $this->parseGroup()
                         . $this->parseHaving()
                         . $this->parseOrder()
                         . $this->parseLimit();
        return $onlyReturnSql ? $this->cleanAndReturnSql($sql) : $this->query($sql);
    }

    public function count($table, $keyName = 'id', $onlyReturnSql = false) {
        $sql = "SELECT " . $this->parseDistinct()
                         . " count({$keyName}) FROM {$table}"
                         . $this->parseJoin()
                         . $this->parseWhere();
        return $onlyReturnSql ? $this->cleanAndReturnSql($sql) : $this->query($sql)[0]["count({$keyName})"];
    }

    /*************************    原生 CURD    *************************/
    
    public function sql($sql) { return $this->is_select($sql) ? $this->query($sql) : $this->execute($sql); }

    /*************************    PDO CURD    *************************/

    /**
     * 增删改
     *
     * INSERT 视情况返回
     *  1.如果是批量插入，返回插入行数（int）
     *  2.如果是单行插入，且能获取到插入 id，返回插入 id（string）
     *  3.如果是单行插入，不能获取到插入 id，返回插入行数（int）
     * UPDATE、DELETE 返回影响行数
     */
    private function execute($sql) {
        $this->clean();
        $this->sql = trim($sql);
        $result = $this->pdo->exec($this->sql);
        $this->hasError();
        return $this->is_insert($this->sql) ? $result > 1 ? $result : $this->pdo->lastInsertId() : $result;
    }

    /**
     * 查
     */
    private function query($sql) {
        $this->clean();
        $this->sql = trim($sql);
        $this->stmt = $this->pdo->prepare($this->sql);
        $this->stmt->execute();
        $this->hasError();
        return $this->stmt->fetchAll(constant("PDO::FETCH_ASSOC"));
    }

    /************************　　链式解析　  ************************/

    private function parseJoin() { if (!empty($this->options['join'])) return ' ' . $this->options['join']; }

    private function parseLimit() { if (!empty($this->options['limit'])) return ' LIMIT ' . $this->options['limit']; }

    private function parseOrder() { if (!empty($this->options['order'])) return ' ORDER BY ' . $this->options['order']; }

    private function parseHaving() { if (!empty($this->options['having'])) return ' HAVING ' . $this->options['having']; }

    private function parseDistinct() { if (!empty($this->options['distinct'])) return "DISTINCT "; }


    private function parseField() {
        if (empty($this->options['field'])) return "* FROM ";
        if (is_array($this->options['field'])) array_walk($this->options['field'], [$this, "addSpecialChar"]);
        if (is_string($this->options['field'])) {
            $this->options['field'] = explode(',', $this->options['field']);
            // array_walk(array, function, [data]) 对 array 中的每个元素应用 function，如果成功则返回 TRUE，否则返回 FALSE
            array_walk($this->options['field'], [$this, "addSpecialChar"]);
        }
        return implode(',', $this->options['field']) . " FROM ";
    }

    private function parseWhere() {
        if (empty($this->options['where'])) return ;
        if (is_string($this->options['where'])) return ' WHERE ' . $this->options['where'];
        if (is_array($this->options['where'])) {
            $where = [];
            foreach ($this->options['where'] as $k => $v) array_push($where, $this->addSpecialChar($k) . ' = "' . $v . '"'); 
            return ' WHERE ' . implode(' AND ', $where);
        }
    }

    private function parseGroup() {
        if (empty($this->options['group'])) return;
        if (is_string($this->options['group'])) return ' GROUP BY ' . $this->options['group'];
        if (is_array($this->options['group'])) return ' GROUP BY ' . implode(',', $this->options['group']);
    }

    /**
     * UPDATE data 解析
     */
    private function parseDataForUpdate() {
        if (empty($this->options['data']['dataOrFields']) || !is_array($this->options['data']['dataOrFields'])) {
            throw new Exception('Missing data or type error.');
        }
        $data = [];
        foreach ($this->options['data']['dataOrFields'] as $k => $v) array_push($data, $this->addSpecialChar($k) . ' = "'  .$v . '"');
        return implode(',', $data);
    }

    /**
     * INSERT data 解析
     */
    private function parseDataForInsert() {
        if (empty($this->options['data']['dataOrFields']) || !is_array($this->options['data']['dataOrFields'])) {
            throw new Exception('Missing data or type error.');
        }
        if (empty($this->options['data']['data'])) {    // 单行插入
            $keys = array_keys($this->options['data']['dataOrFields']);
            $values = array_values($this->options['data']['dataOrFields']);
            array_walk($keys, [$this, "addSpecialChar"]);
            $sql = ' (' . implode(',', $keys) .') VALUES ("' . implode('","', $values) . '")';
        } else {    // 批量插入
            $keys = $this->options['data']['dataOrFields'];
            $values = $this->options['data']['data'];
            array_walk($keys, [$this, "addSpecialChar"]);
            $sql = ' (' . implode(',', $keys) .') VALUES ';
            foreach ($values as $k => $v) {
                if ($k == 0) { $sql .= '("' . implode('","', $v) . '")'; continue; }
                $sql .= ',("' . implode('","', $v) . '")';
            }
        }
        return $sql;
    }

    /************************    其他    *************************/

    /**
     * 反引号字段，防止 SQL 关键字冲突
     */
    private function addSpecialChar(&$value) {
        if ($value === '*' || strpos($value, '.') !== false || strpos($value, '`') !== false || strpos($value, '(') !== false || strpos($value, ' as ') !== false) {
            // do nothing
        } elseif (strpos($value, '`') === false) {
            $value = '`' . trim($value) . '`';
        }
        return $value;
    }

    /**
     * 检查执行结果
     */    
    private function hasError() {
        $obj = $this->stmt ? $this->stmt : $this->pdo;
        $error = $obj->errorInfo();
        if ($error[0] != '00000') throw new Exception("SQL_STATE: {$error[0]}, ERROR_INFO: {$error[2]}, SQL: {$this->sql}.");
    }

    private function cleanAndReturnSql($sql) { $this->clean(); return $sql; }

    private function clean() { $this->sql = ''; $this->stmt = null; $this->options = []; }

    private function is_insert($sql) { return strtoupper(substr($sql, 0, 6)) === 'INSERT'; }

    private function is_select($sql) { return strtoupper(substr($sql, 0, 6)) === 'SELECT'; }

    /**
     * 判断是否是关联数组
     */
    public static function is_assoc($array) { return array_diff_assoc(array_keys($array), range(0, count($array) - 1)) ? true : false; }

}