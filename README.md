# chain-pdo
链式 PDO 封装库，支持多数据源、数据库、链式操作、源生 sql、事务处理等

## ChainPDOFactory
- 通过 ChainPDOFactory 构建 ChainPDO 对象集合，根据数据源配置索引获取 ChainPDO 对象
- 数据源配置项
  - DB_TYPE，数据源类型，默认 mysql
  - DB_CHAR，字符集，默认 utf8
  - DB_PORT，端口地址，默认 3306
  - DB_HOST，主机地址
  - DB_NAME，数据库名
  - DB_USERNAME，用户名
  - DB_PASSWORD，密码
  - DB_OPTIONS，PDO 实例化参数
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
### 链式条件
- distinct()
```php
db->distinct()->select('user');
```
- field($field)
```php
db->field(['id', 'user_name'])->select('user');    // array

db->field('id, user_name')->select('user');        // string
```
- join($join)
```php
db->field(['o.id', 'o.price', 'o.status', 'u.id as user_id', 'u.user_name'])
  ->join('left join user as u on u.id = o.user_id')
  ->select('`order` as o');
```
- where($where)
```php
db->where(['user_name' => 'lisi'])->select('user');    // array

db->where("user_name like '%an%'")->select('user');    // string
```
- group($group)
```php
db->field(['user_id', 'status', 'sum(price) as price'])
  ->group(['user_id', 'status'])
  ->select('order');    // array

db->field(['user_id', 'status', 'sum(price) as price'])
  ->group('user_id,status')
  ->select('order');    // string
```
- having($having)
```php
db->field(['user_id', 'status', 'sum(price) as price'])
  ->group(['user_id', 'status'])
  ->having(['price' => 100])
  ->select('order');    // array

db->field(['user_id', 'status', 'sum(price) as price'])
  ->group(['user_id', 'status'])
  ->having('price >= 200')
  ->select('order');    // string
```
- order($order)
```php
db->order('id desc')->select('user');
```
- limit($limit)
  - UPDATE/DELETE 语句必须是整数(或整数字符串)，只支持 'Limit n'，不支持 'Limit offset,n'，否则 SQL 报语法错误
```
// UPDATE/DELETE，进能传递整数
db->order('id desc')->limit(1)->delete('user')
db->data(['user_name' => 'zhaoliu'])->limit(1)->update('user')

// SELECT
db->limit('3,1')->select('user');
```
  - data($dataOrFields, $data = [])
```php
// UPDATE
db->data(['user_name' => 'zhaoliu'])->update('user');

// INSERT 单行插入
db->data(['user_name' => 'zhangsan'])->insert('user');

// INSERT 批量插入
$fields = ['id', 'user_name'];
$users = [[1, 'zhangsan'], [2, 'lisi'], [3, 'wangwu']];
db->data($fields, $users)->insert('user');
```
### 链式 CURD
- insert($table, $onlyReturnSql = false)
  - 支持的链式条件
    - data($dataOrFields, $data = [])
```php
// INSERT 单行插入
db->data(['user_name' => 'zhangsan'])->insert('user');

// INSERT 批量插入
$fields = ['id', 'user_name'];
$users = [[1, 'zhangsan'], [2, 'lisi'], [3, 'wangwu']];
db->data($fields, $users)->insert('user');
```
- delete($table, $onlyReturnSql = false)
  - 支持的链式条件
    - where($where)
    - order($order)
    - limit($limit)
```php
db->order('id desc')->limit(1)->delete('user')
```
- update($table, $onlyReturnSql = false)
  - 支持的链式条件
    - data($dataOrFields, [])
    - where($where)
    - order($order)
    - limit($limit)
```php
db->data(['user_name' => 'zhaoliu'])->update('user');
```
- select($table, $onlyReturnSql = false)
  - 支持的链式条件
    - distinct()
    - field($field)
    - join($join)
    - where($where)
    - group($group)
    - having($having)
    - order($order)
    - limit($limit)
    - data($dataOrFields, [])
```php
db->where(['user_name' => 'lisi'])->select('user');
```
- count($table, $keyName = 'id', $onlyReturnSql = false)
  - 支持的链式条件
```php
db->where(['user_name' => 'lisi'])->count('user');

db->where(['user_name' => 'lisi'])->count('user', 'unique_code');
```
### 原生 sql
- sql($sql)
```php
$sql = "INSERT INTO `user` (`user_name`) VALUES ('zhangsan')";
$insertId = db->sql($sql);
```
### 事务
- beginTransaction()
- commit()
- rollback()
```php
db->beginTransaction();
try {
    $sqlInsert1 = "INSERT INTO `user` (`user_name`) VALUES ('zhangsan')";
    db->sql($sqlInsert1);

    $sqlInsert2 = "INSERT INTO `user` (`user_name`) VALUES ('lisi')";
    db->sql($sqlInsert2);
    
    db->commit();    
} catch (Exception $e) {
    db->rollback();
} 
```




