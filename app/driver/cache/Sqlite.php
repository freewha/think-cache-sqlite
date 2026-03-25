<?php
namespace app\driver\cache;

use think\cache\Driver;

/**
 * SQLite 缓存驱动 - 增加自动清理功能
 */
class Sqlite extends Driver
{
    protected $options = [
        'path'          => '',
        'prefix'        => '',
        'expire'        => 0,
        'table'         => 'cache',
        'gc_probability' => 100, // 垃圾回收概率，1/100
    ];

    protected $handler;

    public function __construct(array $options = [])
    {
        if (!empty($options)) {
            $this->options = array_merge($this->options, $options);
        }

        if (empty($this->options['path'])) {
            $this->options['path'] = runtime_path() . 'cache/cache.db';
        }

        $dir = dirname($this->options['path']);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->handler = new \PDO('sqlite:' . $this->options['path']);
        $this->handler->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        
        $this->initTable();

        // 概率触发垃圾回收
        if (mt_rand(1, (int)$this->options['gc_probability']) == 1) {
            $this->gc();
        }
    }

    protected function initTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->options['table']} (
            key TEXT PRIMARY KEY,
            value TEXT,
            expire INTEGER
        )";
        $this->handler->exec($sql);
        $this->handler->exec("CREATE INDEX IF NOT EXISTS idx_expire ON {$this->options['table']}(expire)");
    }

    /**
     * 垃圾回收：清理所有已过期的缓存
     */
    public function gc()
    {
        try {
            $sql = "DELETE FROM {$this->options['table']} WHERE expire > 0 AND expire < :now";
            $stmt = $this->handler->prepare($sql);
            $stmt->execute([':now' => time()]);
        } catch (\Exception $e) {
            // 避免 GC 报错影响正常业务
        }
    }

    public function has($name): bool
    {
        $key = $this->getCacheKey($name);
        $sql = "SELECT expire FROM {$this->options['table']} WHERE key = :key LIMIT 1";
        $stmt = $this->handler->prepare($sql);
        $stmt->execute([':key' => $key]);
        $expire = $stmt->fetchColumn();

        if ($expire !== false && ($expire == 0 || time() < $expire)) {
            return true;
        }

        $this->rm($name);
        return false;
    }

    public function get($name, $default = null): mixed
    {
        $key = $this->getCacheKey($name);
        $sql = "SELECT value, expire FROM {$this->options['table']} WHERE key = :key LIMIT 1";
        $stmt = $this->handler->prepare($sql);
        $stmt->execute([':key' => $key]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($result) {
            if ($result['expire'] == 0 || time() < $result['expire']) {
                return unserialize($result['value']);
            }
            $this->rm($name);
        }

        return $default;
    }

    public function set($name, $value, $expire = null): bool
    {
        if (is_null($expire)) {
            $expire = $this->options['expire'];
        }

        if ($expire instanceof \DateTime) {
            $expire = $expire->getTimestamp();
        } else {
            $expire = $expire > 0 ? time() + $expire : 0;
        }

        $key   = $this->getCacheKey($name);
        $value = serialize($value);

        $sql = "INSERT OR REPLACE INTO {$this->options['table']} (key, value, expire) VALUES (:key, :value, :expire)";
        $stmt = $this->handler->prepare($sql);
        return $stmt->execute([
            ':key'    => $key,
            ':value'  => $value,
            ':expire' => $expire,
        ]);
    }

    public function delete($name): bool
    {
        return $this->rm($name);
    }

    public function rm($name): bool
    {
        $key = $this->getCacheKey($name);
        $sql = "DELETE FROM {$this->options['table']} WHERE key = :key";
        $stmt = $this->handler->prepare($sql);
        return $stmt->execute([':key' => $key]);
    }

    public function clear(): bool
    {
        $sql = "DELETE FROM {$this->options['table']}";
        return $this->handler->exec($sql) !== false;
    }

    public function clearTag($keys)
    {
        foreach ($keys as $key) {
            $this->rm($key);
        }
    }

    public function inc($name, $step = 1)
    {
        $value = $this->get($name, 0) + $step;
        return $this->set($name, $value) ? $value : false;
    }

    public function dec($name, $step = 1)
    {
        $value = $this->get($name, 0) - $step;
        return $this->set($name, $value) ? $value : false;
    }
}
