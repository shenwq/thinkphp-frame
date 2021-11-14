<?php
declare (strict_types=1);

namespace ffhome\frame\service;

/**
 * 单例父类
 */
class Singleton
{
    /**
     * 当前实例对象
     * @var object
     */
    protected static $instance;

    /**
     * 获取对象实例
     * @return $this|object
     */
    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new static();
        }
        return self::$instance;
    }
}