<?php
// +------------------------------------------------------------------------------------
// | PDODB		[PDO封装库，支持多种数据库，链式操作，源生语句，事务处理]
// +------------------------------------------------------------------------------------
// | Time: 2015-10-23
// +------------------------------------------------------------------------------------
// | Author: hyb <76788424@qq.com>
// +------------------------------------------------------------------------------------
// | Tips: 开启 php_pdo、php_pdo_mysql 扩展，用什么数据库就开启什么扩展
// +------------------------------------------------------------------------------------
// | Public Function List:
// | 1.源生支持：query、execute
// | 2.对象支持：insert、delete、update、select、count
// | 3.链式支持：distinct、field、join、where、group、having、order、limit
// | 4.事务支持：beginTransaction、commit、rollBack、inTransaction
// | 5.其他支持：quote、showTableInfo、showTables、getLastSql、getDbVersion、isConnected
// +-----------------------------------------------------------------------------------
class PDODB{

    //单例
    private static $instance = null;
    //配置
    private static $config = [
        'DB_TYPE' => 'mysql',
        'DB_HOST' => 'localhost',
        'DB_PORT' => '3306',
        'DB_CHAR' => 'utf8',
        'DB_PCONN' => false,
        'DB_USER' => '',
        'DB_PWD' => '',
        'DB_NAME' => '',
        'DB_PARAMS' => [],
    ];

    //PDO链接
    private $link = null;
    //PDOStatement对象
    private $stmt = null;
    //最后执行的sql语句
    private $lastSql = null;
    //数据库版本信息
    private $dbVersion = null;
    //数据库是否链接成功
    private $connected = false;
    //链式操作，条件数组
    private $options = [];

    /**
     * 构造方法
     * PDODB constructor.
     * @throws Exception
     */
    private function __construct()
    {
        if (!class_exists("PDO")) throw new Exception('PDO extension not open');
        try{
            $this->link = new PDO(self::$config['DB_DSN'], self::$config['DB_USER'], self::$config['DB_PWD'], self::$config['DB_PARAMS']);
        } catch (PDOException $e) {
            throw new Exception($e->getMessage());
        }
        if (!$this->link) {
            throw new Exception('PDO connection failed');
        }
        $this->link->exec('SET NAMES ' . self::$config['DB_CHAR']);
        $this->dbVersion = $this->link->getAttribute(constant("PDO::ATTR_SERVER_VERSION"));
        $this->connected = true;
    }

    /**
     * 单例
     * @param $dbConfig
     * @return null|PDODB
     */
    public static function getInstance($dbConfig)
    {
        if (empty(self::$instance)) {
            if (self::_checkConfig($dbConfig)) {
                self::$instance = new self();
            }
        }
        return self::$instance;
    }

    /****************************************************************/
    /************************　　　　　　　　*************************/
    /************************　链式操作支持  *************************/
    /************************　　　　　　　　*************************/
    /****************************************************************/

    /************************　　链式函数　  *************************/

    /**
     * 增
     * @param $table
     * @return bool|int|string
     */
    public function insert($table)
    {
        $sql = "INSERT INTO {$table} " . $this->_parseDataForInsert();
        return $this->execute($sql);
    }

    /**
     * 删
     * @param $table
     * @return bool|int|string
     */
    public function delete($table)
    {
        $sql = "DELETE FROM {$table} " . $this->_parseWhere()
                                       . $this->_parseOrder()
                                       . $this->_parseLimit();
        return $this->execute($sql);
    }

    /**
     * 改
     * @param $table
     * @return bool|int|string
     */
    public function update($table)
    {
        $sql = "UPDATE {$table} SET " . $this->_parseDataForUpdate()
                                      . $this->_parseWhere()
                                      . $this->_parseOrder()
                                      . $this->_parseLimit();
        return $this->execute($sql);
    }

    /**
     * 查
     * @param $table
     * @return mixed
     */
    public function select($table)
    {
        $sql = "SELECT " . $this->_parseDistinct()
                         . $this->_parseField() . $table
                         . $this->_parseJoin()
                         . $this->_parseWhere()
                         . $this->_parseGroup()
                         . $this->_parseHaving()
                         . $this->_parseOrder()
                         . $this->_parseLimit();
        return $this->query($sql);
    }

    /**
     * 统计
     * @param $table
     * @param string $keyName
     * @return mixed
     */
    public function count($table, $keyName = 'id')
    {
        $sql = "SELECT " . $this->_parseDistinct()
                         . " count({$keyName}) FROM {$table}"
                         . $this->_parseJoin()
                         . $this->_parseWhere();
        $data = $this->query($sql);
        return $data[0]["count({$keyName})"];
    }

    /************************　　链式字段　  ************************/

    /**
     * 去重
     * @return null
     */
    public function distinct()
    {
        $this->options['distinct'] = true;
        return self::$instance;
    }

    /**
     * 字段
     * @param $field
     * @return null
     */
    public function field($field)
    {
        $this->options['field'] = $field;
        return self::$instance;
    }

    /**
     * 关联查询
     * @param $join
     * @return null
     */
    public function join($join)
    {
        $this->options['join'] = trim($join);
        return self::$instance;
    }

    /**
     * 条件
     * @param $where
     * @return null
     */
    public function where($where)
    {
        $this->options['where'] = $where;
        return self::$instance;
    }

    /**
     * 分组
     * @param $group
     * @return null
     */
    public function group($group)
    {
        $this->options['group'] = $group;
        return self::$instance;
    }

    /**
     * 分组条件
     * @param $having
     * @return null
     */
    public function having($having)
    {
        $this->options['having'] = $having;
        return self::$instance;
    }

    /**
     * 排序
     * @param $order
     * @return null
     */
    public function order($order)
    {
        $this->options['order'] = trim($order);
        return self::$instance;
    }

    /**
     * 分页
     * @param $limit
     * @return null
     */
    public function limit($limit)
    {
        $this->options['limit'] = trim($limit);
        return self::$instance;
    }

    /**
     * 数据
     * @param $data
     * @return null
     */
    public function data($data)
    {
        $this->options['data'] = $data;
        return self::$instance;
    }

    /************************　　链式解析　  ************************/

    /**
     * update操作data解析
     * @return string
     * @throws Exception
     */
    private function _parseDataForUpdate()
    {
        if (empty($this->options['data']) || !is_array($this->options['data'])) {
            throw new Exception('data must be an array');
        }
        $arr = [];
        foreach ($this->options['data'] as $k => $v) {
            $arr[] = $this->addSpecialChar($k) . ' = "'  .$v . '"';
        }
        return implode(',', $arr);
    }

    /**
     * insert操作data解析
     * @return string
     * @throws Exception
     */
    private function _parseDataForInsert()
    {
        if (empty($this->options['data']) || !is_array($this->options['data'])) {
            throw new Exception('data must be an array');
        }
        $keys = array_keys($this->options['data']);
        array_walk($keys, [self::$instance, "addSpecialChar"]);
        $values = array_values($this->options['data']);
        return ' (' . implode(',', $keys) .') VALUES ("' . implode('","' ,$values) . '")';
    }

    /**
     * limit解析
     * @return string
     */
    private function _parseLimit()
    {
        if (!empty($this->options['limit'])) {
            return ' LIMIT ' . $this->options['limit'];
        }
    }

    /**
     * order解析
     * @return string
     */
    private function _parseOrder()
    {
        if (!empty($this->options['order'])) {
            return ' ORDER BY ' . $this->options['order'];
        }
    }

    /**
     * having解析
     * @return string
     */
    private function _parseHaving()
    {
        if (!empty($this->options['having'])) {
            return ' HAVING ' . $this->options['having'];
        }
    }

    /**
     * group解析
     * @return string
     */
    private function _parseGroup()
    {
        if (!empty($this->options['group'])) {
            if (is_string($this->options['group'])) {
                return ' GROUP BY ' . $this->options['group'];
            } elseif (is_array($this->options['group'])){
                return ' GROUP BY ' . implode(',', $this->options['group']);
            }
        }
    }

    /**
     * where解析
     * @return string
     */
    private function _parseWhere()
    {
        if (!empty($this->options['where'])) {
            if (is_string($this->options['where'])) {
                return ' WHERE ' . $this->options['where'];
            } elseif (is_array($this->options['where'])) {
                $arr = [];
                foreach ($this->options['where'] as $k => $v) {
                    $arr[] = $this->addSpecialChar($k) . ' = "' . $v . '"';
                }
                return ' WHERE ' . implode(' AND ', $arr);
            }
        }
    }

    /**
     * join解析
     * @return string
     */
    private function _parseJoin()
    {
        if (!empty($this->options['join'])) {
            return ' ' . $this->options['join'];
        }
    }

    /**
     * field解析
     * @return string
     */
    private function _parseField()
    {
        if (!empty($this->options['field'])) {
            if (is_array($this->options['field'])) {
                //array_walk(array,function,[data]) 函数对数组中的每个元素应用回调函数。如果成功则返回 TRUE，否则返回 FALSE
                array_walk($this->options['field'], [self::$instance, "addSpecialChar"]);
            } elseif (is_string($this->options['field'])) {
                $this->options['field'] = explode(',', $this->options['field']);
                array_walk($this->options['field'], [self::$instance, "addSpecialChar"]);
            }
            return implode(',', $this->options['field']) . " FROM ";
        } else {
            return "* FROM ";
        }
    }

    /**
     * 反引号字段，防止SQL关键字冲突
     * @param $value
     * @return string
     */
    private function addSpecialChar(&$value)
    {
        if ($value === '*' || strpos($value, '.') !== false || strpos($value, '`') !== false || strpos($value, '(') !== false || strpos($value, ' as ') !== false) {
            //不用做处理
        } elseif (strpos($value, '`') === false) {
            $value = '`' . trim($value) . '`';
        }
        return $value;
    }

    /**
     * distinct解析
     * @return string
     */
    private function _parseDistinct()
    {
        if (!empty($this->options['distinct'])) {
            return "DISTINCT ";
        }
    }

    /****************************************************************/
    /************************　　　　　　　　************************/
    /************************　源生SQL支持　 ************************/
    /************************　　　　　　　　************************/
    /****************************************************************/

    /**
     * 源生增删改
     * @param string $sql
     * @return bool|int|string
     */
    public function execute($sql = '')
    {
        return self::_execute($sql);
    }

    /**
     * 源生查
     * @param string $sql
     * @return mixed
     */
    public function query($sql = '')
    {
        if(self::_query($sql)){
            return $this->stmt->fetchAll(constant("PDO::FETCH_ASSOC"));
        }
        throw new Exception('query failed');
    }

    /****************************************************************/
    /************************　　　　　　　　*************************/
    /************************　　事务支持　　*************************/
    /************************　　　　　　　　*************************/
    /****************************************************************/
    /*	$pdo = PDODB::getInstance($config);							*/
    /*	$pdo::beginTransaction();									*/
    /*	//var_dump($pdo::inTransaction()); //检测是否在事务中，true  */
    /*	try{														*/
    /*		$res1 = $pdo::execute($sql1);							*/
    /*		if(empty($res1)) throw new PDOException("操作失败1");	*/
    /*		$res2 = $pdo::execute($sql2);							*/
    /*		if(empty($res2)) throw new PDOException("操作失败2");	*/
    /*		$pdo::commit();											*/
    /*		echo "success";											*/
    /*	}catch(PDOException $e){									*/
    /*		$pdo::rollBack();										*/
    /*		echo $e->getMessage();									*/
    /*	}															*/
    /****************************************************************/

    /**
     * 开启事务
     */
    public function beginTransaction()
    {
        //开启事务的时候关闭自动提交
        $this->link->setAttribute(PDO::ATTR_AUTOCOMMIT, 0);
        $this->link->beginTransaction();
    }

    /**
     * 提交事务
     */
    public function commit()
    {
        $this->link->commit();
        //完成事务的时候开启自动提交
        $this->link->setAttribute(PDO::ATTR_AUTOCOMMIT, 1);
    }

    /**
     * 事务回滚
     */
    public function rollBack()
    {
        $this->link->rollBack();
        //回滚事务的时候开启自动提交
        $this->link->setAttribute(PDO::ATTR_AUTOCOMMIT, 1);
    }

    /**
     * 检测是否在事务内
     * @return bool
     */
    public function inTransaction()
    {
        return $this->link->inTransaction();
    }

    /****************************************************************/
    /************************　　　　　　　　*************************/
    /************************　　其它函数　　*************************/
    /************************　　　　　　　　*************************/
    /****************************************************************/

    /**
     * 获取表字段信息
     * @param $table
     * @return mixed
     */
    public function showTableInfo($table)
    {
        return $this->query("SHOW COLUMNS FROM {$table}");
    }

    /**
     * 获取数据库表信息
     * @return array
     */
    public function showTables()
    {
        $tables = [];
        $res = $this->query("SHOW TABLES");
        if (!empty($res)) {
            foreach ($res as $v) {
                $tables[] = current($v); //current()数组当前指针所指元素的值
            }
        }
        return $tables;
    }

    /**
     * 防注入，返回带引号的字符串，过滤特殊字符
     * @param $str
     * @return string
     */
    public function quote($str)
    {
        return trim($this->link->quote($str), "'");
    }

    /**
     * 获取最近一次执行的SQL
     * @return null
     */
    public function getLastSql()
    {
        return $this->lastSql;
    }

    /**
     * 获取数据库版本
     * @return mixed|null
     */
    public function getDbVersion()
    {
        return $this->dbVersion;
    }

    /**
     * 连接与否
     * @return bool
     */
    public function isConnected()
    {
        return $this->connected;
    }

    /****************************************************************/
    /************************　　　　　　　　*************************/
    /************************　　原始CURD　　*************************/
    /************************　　　　　　　　*************************/
    /****************************************************************/

    /**
     * 原始增删改
     * @param string $sql
     * @return bool|int|string
     */
    private function _execute($sql = '')
    {
        $this->_flush();
        $this->lastSql = trim($sql);
        $res = $this->link->exec($this->lastSql);
        $this->_checkError();
        //insert操作返回最后插入的AUTO_INCREMENT
        if (strtoupper(substr($this->lastSql, 0, 6)) == 'INSERT') {
            return $this->link->lastInsertId();
        }
        //update、delete操作返回影响行数
        return $res;
    }

    /**
     * 原始查
     * @param string $sql
     * @return bool
     */
    private function _query($sql = '')
    {
        $this->_flush();
        $this->lastSql = trim($sql);
        $this->stmt = $this->link->prepare($this->lastSql);
        $res = $this->stmt->execute();
        $this->_checkError();
        return $res;
    }

    /****************************************************************/
    /************************　　　　　　　　*************************/
    /************************　　工具函数　　*************************/
    /************************　　　　　　　　*************************/
    /****************************************************************/

    /**
     * 配置文件检测
     * @param array $dbConfig
     * @return bool
     * @throws Exception
     */
    private static function _checkConfig($dbConfig = [])
    {
        self::$config = array_merge(self::$config, $dbConfig);
        if (empty(self::$config['DB_USER'])) throw new Exception('DB_USER not configured');
        if (empty(self::$config['DB_PWD'])) throw new Exception('DB_PWD not configured');
        if (empty(self::$config['DB_NAME'])) throw new Exception('DB_NAME not configured');
        self::$config['DB_DSN'] = self::$config['DB_TYPE'] . ':host='
                                . self::$config['DB_HOST'] . ';port='
                                . self::$config['DB_PORT'] . ';dbname='
                                . self::$config['DB_NAME'];
        //其他链接属性设置
        if (empty(self::$config['DB_PARAMS'])) {
            //长连接
            if (!empty(self::$config['DB_PCONN'])) {
                self::$config['DB_PARAMS'][constant("PDO::ATTR_PERSISTENT")] = true;
            }
            //todo:其他链接属性扩展
        }
        return true;
    }

    /**
     * 是否有错误
     * @throws Exception
     */
    private function _checkError()
    {
        $obj = empty($this->stmt) ? $this->link : $this->stmt;
        $error = $obj->errorInfo();
        if ($error[0] != '00000') {
            $message = 'SQL_STATE : ' . $error[0] . '<br/>ERROR_INFO : ' . $error[2] . '<br/>ERROR_SQL : ' . $this->lastSql;
            throw new Exception($message);
        }
    }

    /**
     * 释放资源
     */
    private function _flush()
    {
        if (!empty($this->stmt)) $this->_free();
        if (!empty($this->options)) $this->_clean();
    }

    /**
     * 释放结果集
     */
    private function _free()
    {
        $this->stmt = null;
    }

    /**
     * 释放条件集
     */
    private function _clean()
    {
        $this->options = [];
    }

    /**
     * 防克隆
     */
    private function __clone()
    {
        //防止clone函数克隆对象，破坏单例模式
    }

    /**
     * 析构
     */
    public function __destruct()
    {
        $this->link = null;
    }

}