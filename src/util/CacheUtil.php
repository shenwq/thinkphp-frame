<?php
declare (strict_types=1);

namespace ffhome\frame\util;

use think\facade\Cache;

class CacheUtil
{
    /**
     * 从缓存中取出对应数据
     * @param string $name 缓存的键名
     * @param \Closure $fn 缓存找不到时调用，返回数据放到缓存中
     * @param string $tag 根据tag批量清除缓存处理
     * @param int $expire 有效时间（秒）
     * @return mixed 从缓存中取出的值
     */
    public static function get(string $name, \Closure $fn, string $tag = 'common', int $expire = null)
    {
        $data = Cache::get($name);
        if (empty($data)) {
            $data = $fn();
            Cache::tag($tag)->set($name, $data, $expire);
        }
        return $data;
    }
}