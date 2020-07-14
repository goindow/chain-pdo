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

    /**
     * 去重
     */
    public function distinct() { $this->options['distinct'] = true; return $this; }

    /**
     * 字段
     *
     * @param  $field  array|string
     */
    public function field($field) { $this->options['field'] = $field; return $this; }

    /**
     * 关联
     *
     * @param  $join  string
     */
    public function join($join) { $this->options['join'] = trim($join); return $this; }

    /**
     * 条件
     *
     * @param  $where  array|string，array 只支持简单的等于比较，其他情况需使用 string 传递 where 条件
     *   
     */
    public function where($where) { $this->options['where'] = $where; return $this; }

    public function group($group) { $this->options['group'] = $group; return $this; }

    public function having($having) { $this->options['having'] = $having; return $this; }

    /**
     * 排序
     *
     * @param  $order  string
     */
    public function order($order) { $this->options['order'] = trim($order); return $this; }

    /**
     * 分页
     *
     * @param  $limit  string|int
     *
     * DELETE 语句传递的必须是 int，只支持 'Limit n'，不支持 'Limit offset,n'，否则 SQL 报语法错误
     */
    public function limit($limit) { $this->options['limit'] = $limit; return $this; }

    /**
     * 数据
     *
     * @param  $dataOrFields  array
     * @param  $data          array
     */
    public function data($dataOrFields, $data = []) { 
        $this->options['data'] = [
            "dataOrFields" => $dataOrFields,
            "data" => $data
        ];
        return $this;
    }

    /*************************    链式 CURD    *************************/

    /**
     * 增 - 单行/批量插入
     *
     * @param  $table          string  表名
     * @param  $onlyReturnSql  bool    true - 只解析返回 sql，并不执行
     */
    public function insert($table, $onlyReturnSql = false) {
        $sql = "INSERT INTO {$table} " . $this->parseDataForInsert();
        return $onlyReturnSql ? $this->cleanAndReturnSql($sql) : $this->execute($sql);
    }

    /**
     * 删
     *
     * @param  $table          string  表名
     * @param  $onlyReturnSql  bool    true - 只解析返回 sql，并不执行
     */
    public function delete($table, $onlyReturnSql = false) {
        $sql = "DELETE FROM {$table} " . $this->parseWhere()
                                       . $this->parseOrder()
                                       . $this->parseLimitForUD();
        return $onlyReturnSql ? $this->cleanAndReturnSql($sql) : $this->execute($sql);
    }

    /**
     * 改
     *
     * @param  $table          string  表名
     * @param  $onlyReturnSql  bool    true - 只解析返回 sql，并不执行
     */
    public function update($table, $onlyReturnSql = false) {
        $sql = "UPDATE {$table} SET " . $this->parseDataForUpdate()
                                      . $this->parseWhere()
                                      . $this->parseOrder()
                                      . $this->parseLimitForUD();
        return $onlyReturnSql ? $this->cleanAndReturnSql($sql) : $this->execute($sql);
    }

    /**
     * 查
     *
     * @param  $table          string  表名
     * @param  $onlyReturnSql  bool    true - 只解析返回 sql，并不执行
     */
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

    /**
     * 统计
     *
     * @param  $table          string  表名
     * @param  $keyName        string  count 字段，默认 id
     * @param  $onlyReturnSql  bool    true - 只解析返回 sql，并不执行
     */
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
        $this->ensure();
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
        $this->ensure();
        return $this->stmt->fetchAll(constant("PDO::FETCH_ASSOC"));
    }

    /************************　　链式解析　  ************************/

    private function parseJoin() { if (!empty($this->options['join'])) return ' ' . $this->options['join']; }

    /**
     * limit 解析 - !(UPDATE/DELETE)
     */
    private function parseLimit() { if (!empty($this->options['limit'])) return ' LIMIT ' . trim($this->options['limit']); }

    /**
     * limit 解析 - UPDATE/DELETE
     * 
     * UPDATE/DELETE 语句必须是 int，只支持 'Limit n'，不支持 'Limit offset,n'，否则 SQL 报语法错误
     */
    private function parseLimitForUD() { 
        if (empty($this->options['limit'])) return;
        if (!is_int($this->options['limit'])) throw new Exception('Limit type error, must be an integer.');
        return ' LIMIT ' . trim($this->options['limit']);
    }

    private function parseOrder() { if (!empty($this->options['order'])) return ' ORDER BY ' . $this->options['order']; }

    private function parseHaving() { if (!empty($this->options['having'])) return ' HAVING ' . $this->options['having']; }

    private function parseDistinct() { if (!empty($this->options['distinct'])) return "DISTINCT "; }


    private function parseField() {
        if (empty($this->options['field'])) return "* FROM ";
        if (is_array($this->options['field'])) array_walk($this->options['field'], [$this, "addSpecialChar"]);
        if (is_string($this->options['field'])) {
            $this->options['field'] = explode(',', $this->options['field']);
            array_walk($this->options['field'], [$this, "addSpecialChar"]);
        }
        return implode(',', $this->options['field']) . " FROM ";
    }

    private function parseWhere() {
        if (empty($this->options['where'])) return;
        if (is_string($this->options['where'])) return ' WHERE ' . $this->options['where'];
        if (is_array($this->options['where']) && self::is_assoc($this->options['where'])) {
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
     * data 解析 - UPDATE
     */
    private function parseDataForUpdate() {
        if (empty($this->options['data']['dataOrFields'])) throw new Exception('Data missing first parameter.');
        $this->ensureArray($this->options['data']['dataOrFields'], 'the first');
        $this->ensureAssocArray($this->options['data']['dataOrFields'], 'the first');
        $data = [];
        foreach ($this->options['data']['dataOrFields'] as $k => $v) array_push($data, $this->addSpecialChar($k) . ' = "'  .$v . '"');
        return implode(',', $data);
    }

    /**
     * data 解析 - INSERT
     */
    private function parseDataForInsert() {
        if (empty($this->options['data']['dataOrFields'])) throw new Exception('Data missing first parameter.');
        $this->ensureArray($this->options['data']['dataOrFields'], 'the first');
        return empty($this->options['data']['data']) ? $this->parseDataForInsertSingle() : $this->parseDataForInsertMulti();
    }

    /**
     * data 解析 - 单行 INSERT
     */
    private function parseDataForInsertSingle() {
        $this->ensureAssocArray($this->options['data']['dataOrFields'], 'the first');
        $keys = array_keys($this->options['data']['dataOrFields']);
        array_walk($keys, [$this, "addSpecialChar"]);
        return ' (' . implode(',', $keys) .') VALUES ("' . implode('","', array_values($this->options['data']['dataOrFields'])) . '")';
    }

    /**
     * data 解析 - 批量 INSERT
     */
    private function parseDataForInsertMulti() {
        $this->ensureNormalArray($this->options['data']['dataOrFields'], 'the first');
        $this->ensureArray($this->options['data']['data'], 'the second');
        $this->ensureNormalArray($this->options['data']['data'], 'the second');
        $keys = $this->options['data']['dataOrFields'];
        array_walk($keys, [$this, "addSpecialChar"]);
        $sql = ' (' . implode(',', $keys) .') VALUES ';
        foreach ($this->options['data']['data'] as $k => $v) {
            $this->ensureNormalArray($v, 'the element of the second');
            if ($k == 0) { $sql .= '("' . implode('","', $v) . '")'; continue; }
            $sql .= ',("' . implode('","', $v) . '")';
        }
        return $sql;
    }

    /************************    异常检查    *************************/

    /**
     * 确保无错误
     */    
    private function ensure() {
        $obj = $this->stmt ? $this->stmt : $this->pdo;
        $error = $obj->errorInfo();
        if ($error[0] != '00000') throw new Exception("SQL_STATE: {$error[0]}, ERROR_INFO: {$error[2]}, SQL: {$this->sql}.");
    }

    /**
     * 确保是数组
     */
    private function ensureArray($array, $location) {
        if (!is_array($array)) throw new Exception("Data type error, {$location} parameter must be an array."); 
    }

    /**
     * 确保是普通数组
     */
    private function ensureNormalArray($array, $location) { 
        if (self::is_assoc($array)) throw new Exception("Data type error, {$location} parameter must be an normal array."); 
    }

    /**
     * 确保是关联数组
     */
    private function ensureAssocArray($array, $location) {
        if (!self::is_assoc($array)) throw new Exception("Data type error, {$location} parameter must be an associative array."); 
    }

    /************************    其他    *************************/

    /**
     * 判断是否是关联数组
     */
    public static function is_assoc($array) { return array_diff_assoc(array_keys($array), range(0, count($array) - 1)) ? true : false; }

    /**
     * 判断是否是 INSERT 语句
     */
    private function is_insert($sql) { return strtoupper(substr($sql, 0, 6)) === 'INSERT'; }

    /**
     * 判断是否是 SELECT 语句
     */
    private function is_select($sql) { return strtoupper(substr($sql, 0, 6)) === 'SELECT'; }

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
     * 清理属性，并返回 sql
     */
    private function cleanAndReturnSql($sql) { $this->clean(); return $sql; }

    /**
     * 清理属性
     */
    private function clean() { $this->sql = ''; $this->stmt = null; $this->options = []; }

}