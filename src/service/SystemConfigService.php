<?php
declare (strict_types=1);

namespace ffhome\frame\service;

use think\facade\Cache;
use think\facade\Db;

/**
 * 系统配置相关处理
 */
class SystemConfigService
{
    const NAME = 'system_config';

    /**
     * 获取系统配置信息
     * @param string $category
     * @param string|null $name
     * @return array|string
     */
    public static function config(string $category, string $name = null)
    {
        $key = self::NAME;
        $value = empty($name) ? Cache::get("{$key}_{$category}") : Cache::get("{$key}__{$name}");
        if (empty($value)) {
            if (!empty($name)) {
                $value = Db::name($key)->where('name', $name)->value('value');
                Cache::tag($key)->set("{$key}__{$name}", $value);
            } else {
                $value = Db::name($key)->where('category', $category)->order('sort')
                    ->column('value', 'name');
                Cache::tag($key)->set("{$key}_{$category}", $value);
            }
        }
        return $value;
    }

    /**
     * 获取系统配置信息的值
     * @param string $name
     * @return string
     */
    public static function value(string $name): string
    {
        return self::config('', $name);
    }

    /**
     * 修改配置档信息，只更新自己的缓存，不会更新组的缓存
     * @param string $name
     * @param $value
     * @throws \think\db\exception\DbException
     */
    public static function setValue(string $name, $value)
    {
        $key = self::NAME;
        Db::name($key)->where('name', $name)->update(['value' => $value]);
        Cache::tag($key)->set("{$key}__{$name}", $value);
    }
}