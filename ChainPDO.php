<?php
class ChainPDO {

    private $index;

    private $config;

    private $pdo;

    public function __construct($index, $config) {
        $this->index = $index;
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

}