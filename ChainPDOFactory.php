<?php
// +------------------------------------------------------------------------------------
// | PDODB [PDO封装库，支持多数据库，链式操作，源生语句，事务处理]
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
// +------------------------------------------------------------------------------------
require_once("ChainPDO.php");

class ChainPDOFactory {

    private static $instance = null;

    // 默认配置项
    private static $defaultConfig = [
        'DB_TYPE' => 'mysql',
        'DB_CHAR' => 'utf8',
        'DB_PORT' => '3306',
        'DB_HOST' => '',
        'DB_NAME' => '',
        'DB_USERNAME' => '',
        'DB_PASSWORD' => '',
        'DB_OPTIONS' => [],
    ];

    // 数据库配置集合
    private $dbsConfig = [];

    // ChainPDO 对象集合
    private $dbs = [];

    /**
     * 获取所有的数据库
     */
    public function getDbs() { return $this->dbs; }

    /**
     * 获取数据库
     */
    public function getDb($dbIndex) {
        if (empty($this->dbs[$dbIndex])) throw new Exception("Database[{$dbIndex}] not found.");
        return $this->dbs[$dbIndex];
    }
    
    public static function build($dbsConfig) {
        if (empty(self::$instance)) self::$instance = new self($dbsConfig);
        return self::$instance;
    }

    private function __construct($dbsConfig) {
        if (!class_exists("PDO")) throw new Exception('PDO extension not found.');
        $this->adapter($dbsConfig);
    }

    /**
     * 多数据库适配器
     */
    private function adapter($dbsConfig) {
        // 单数据库
        if ($this->is_assoc($dbsConfig)) { $this->connector(0, $dbsConfig); return; }
        // 多数据库
        foreach ($dbsConfig as $dbIndex => $dbConfig) { $this->connector($dbIndex, $dbConfig); }
    }

    /**
     * 数据库连接器
     */
    private function connector($dbIndex, $dbConfig) {
        $this->dbsConfig[$dbIndex] = $this->configure($dbConfig);
        $this->dbs[$dbIndex] = $this->connect($dbIndex);
    }

    /**
     * 配置数据库
     */
    private function configure($dbConfig) {
        if (empty($dbConfig['DB_NAME'])) throw new Exception('DB_NAME not configured.');
        $config = array_merge(self::$defaultConfig, $dbConfig);
        $config['DB_DSN'] = $config['DB_TYPE'] . ':host=' 
                            . $config['DB_HOST'] . ';port=' 
                            . $config['DB_PORT'] . ';dbname=' 
                            . $config['DB_NAME'];
        return $config;
    }

    /**
     * 连接数据库
     */
    private function connect($dbIndex) { return new ChainPDO($dbIndex, $this->dbsConfig[$dbIndex]); }

    /**
     * 判断是否是关联数组
     */
    private function is_assoc($array) { return array_diff_assoc(array_keys($array), range(0, count($array) - 1)) ? true : false; }

    private function __clone() {}

    public function __destruct() { $this->dbs = null; }
    
}