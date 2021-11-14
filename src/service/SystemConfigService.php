<?php
declare (strict_types=1);

namespace ffhome\frame\service;

use \think\facade\Cache;
use \think\facade\Db;

/**
 * 系统配置相关处理
 */
class SystemConfigService extends Singleton
{
    const NAME = 'system_config';

    /**
     * 获取系统配置信息
     * @param string $category
     * @param string|null $name
     * @return array|string
     */
    public function config(string $category, string $name = null)
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
     * 获取知道配置信息的值
     * @param string $name
     * @return string
     */
    public function value(string $name)
    {
        return $this->config('', $name);
    }
}