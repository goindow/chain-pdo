<?php
class ChainPDO {

    private $config;

    private $pdo;

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

}