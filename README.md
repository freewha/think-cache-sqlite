# ThinkPHP 8.0 SQLite 缓存驱动 纯 AI   生成

这是一个为 ThinkPHP 8.0 开发的轻量级 SQLite 缓存驱动。它非常适合在没有 Redis 或 Memcached 的虚拟主机、小型项目或本地开发环境中使用。

## 特性

*   **完全兼容**：支持 ThinkPHP 8.0 缓存契约及 PSR-16 (Simple Cache) 标准。
*   **零配置启动**：只需一个本地文件即可运行，无需安装复杂的缓存服务端。
*   **自动清理 (GC)**：内置概率触发的垃圾回收机制，自动清理过期数据，防止数据库文件无限膨胀。
*   **高性能**：利用 SQLite 索引优化查询，支持惰性删除。
*   **类型安全**：针对 PHP 8.x 进行了优化，处理了严格的方法签名匹配。

## 安装步骤

### 1. 放置驱动文件

将 `Sqlite.php` 文件放置到你的项目目录中：
`app/driver/cache/Sqlite.php`

### 2. 配置缓存

打开项目配置文件 `config/cache.php`，在 `stores` 数组中添加 `sqlite` 配置：

```php
return [
    // 默认缓存驱动
    'default' => 'sqlite',

    // 缓存连接参数
    'stores'  => [
        // ... 其他驱动
        
        'sqlite' => [
            // 驱动类型（对应驱动类的命名空间）
            'type'           => \app\driver\cache\Sqlite::class,
            // 缓存保存目录（默认 runtime/cache/cache.db）
            'path'           => '',
            // 缓存前缀
            'prefix'         => '',
            // 默认缓存有效期 0为永久
            'expire'         => 0,
            // 数据库表名
            'table'          => 'cache',
            // 垃圾回收概率 (1/100)
            'gc_probability' => 100,
        ],
    ],
];
```

## 使用方法

你可以像使用原生驱动（如 File 或 Redis）一样使用它：

```php
use think\facade\Cache;

// 写入缓存
Cache::set('name', 'thinkphp', 3600);

// 读取缓存
$name = Cache::get('name');

// 判断缓存是否存在
if (Cache::has('name')) {
    // ...
}

// 自增/自减
Cache::inc('score', 1);
Cache::dec('score', 1);

// 删除缓存
Cache::rm('name');

// 清除所有缓存
Cache::clear();

// 使用标签
Cache::tag('tag_name')->set('name1', 'value1');
Cache::tag('tag_name')->clear();
```

## 高级说明

### 自动清理机制 (GC)
为了防止 SQLite 数据库文件因为过期数据而不断增大，驱动内置了垃圾回收机制：
*   **触发时机**：在实例化缓存驱动时（通常是每个请求第一次调用缓存时）。
*   **触发概率**：由 `gc_probability` 配置项决定。默认值为 `100`，表示平均每 100 次请求会执行一次全表过期数据清理。
*   **性能影响**：清理操作使用了 `expire` 字段索引，速度极快，对用户几乎无感知。

### 数据库结构
驱动会自动在指定的 `.db` 文件中创建如下结构的表：
*   `key` (TEXT PRIMARY KEY): 缓存键名。
*   `value` (TEXT): 序列化后的缓存数据。
*   `expire` (INTEGER): 过期时间戳（带索引）。

## 运行环境要求
*   PHP >= 8.0
*   ThinkPHP >= 8.0
*   PHP 开启 `pdo_sqlite` 扩展

## 许可证
MIT License
