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
        } catch (PDOException $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function getConfig() { return $this->config; }

    /**********************   原生 SQL   ***********************/    
    
    /**
     * 原生支持
     * INSERT 返回情况如下
     *  1.如果是批量插入，返回插入行数（int）
     *  2.如果是单行插入，且能获取到插入 id，返回插入 id（string）
     *  3.如果是单行插入，不能获取到插入 id，返回插入行数（int）
     * UPDATE、DELETE 返回影响行数
     */
    public function sql($sql) { return $this->is_select($sql) ? $this->query($sql) : $this->execute($sql); }

    /************************   PDO   *************************/

    /**
     * 增删改
     */
    private function execute($sql) {
        $this->clean();
        $this->sql = trim($sql);
        $result = $this->pdo->exec($this->sql);
        $this->hasError();
        // UPDATE、DELETE 返回影响行数，INSERT 视情况返回
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


    /**
     * 检查执行结果
     */    
    private function hasError() {
        $obj = $this->stmt ? $this->stmt : $this->pdo;
        $error = $obj->errorInfo();
        if ($error[0] != '00000') throw new Exception("SQL_STATE: {$error[0]}, ERROR_INFO: {$error[2]}, SQL: {$this->sql}.");
    }

    private function is_insert($sql) { return strtoupper(substr($sql, 0, 6)) === 'INSERT'; }

    private function is_select($sql) { return strtoupper(substr($sql, 0, 6)) === 'SELECT'; }

    /**
     * 清理属性
     */
    private function clean() { 
        $this->sql = '';
        $this->stmt = null;
        $this->options = [];
    }

}