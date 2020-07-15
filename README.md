# chain-pdo
链式 PDO 封装库，支持多数据源/数据库、链式操作、源生语句、事务处理等

## 说明
- 支持多数据库、多数据库
- 支持常用关键字的链式操作，如有需可扩展
- 支持事物
- 支持原生 sql
- 支持仅解析而不执行 sql

## ChainPDOFactory
- 通过 ChainPDOFactory 构建 ChainPDO 对象集合
- 根据数据源配置索引获取 ChainPDO 对象
- 
```php
// $configSingle = [
//     'DB_HOST' => '127.0.0.1',
//     'DB_NAME' => 'test1',
//     'DB_USERNAME' => 'root',
//     'DB_PASSWORD' => '123456'
// ];
// db = ChainPDOFactory::build($configSingle)->getDb();

$configMulti = [
    [
        'DB_HOST' => 'localhost',
        'DB_NAME' => 'test1',
        'DB_USERNAME' => 'root',
        'DB_PASSWORD' => '123456'
    ],[
        'DB_HOST' => '127.0.0.1',
        'DB_NAME' => 'test2',
        'DB_USERNAME' => 'root',
        'DB_PASSWORD' => '123456'
    ]
];

chainPdoFactory =  ChainPDOFactory::build($configMulti);
db1 = chainPdoFactory->getDb();    // default db index 0 - test1
db2 = chainPdoFactory->getDb(1);   // db index 1 - test2
```

## ChainPDO
链式 PDO 封装类

### 原生 sql
- sql($sql)
```php
$sql = "INSERT INTO `user` (`user_name`) VALUES ('zhangsan')";
$insertId = db->sql($sql);
```

### 链式操作
- 链式操作
  - insert($table, $onlyReturnSql = false)
  - delete($table, $onlyReturnSql = false)
  - update($table, $onlyReturnSql = false)
  - select($table, $onlyReturnSql = false)
  - count($table, $keyName = 'id', $onlyReturnSql = false)
- 链式条件
  - distinct()
  - field($field)
  - join($join)
  - where($where)
  - group($group)
  - having($having)
  - order($order)
  - limit($limit)
  - data($dataOrFields, $data = [])
```php

```







