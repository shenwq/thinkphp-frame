<?php
declare (strict_types=1);

use ffhome\frame\model\BaseModel;
use ffhome\frame\service\AuthService;
use ffhome\frame\service\SystemConfigService;
use think\facade\Db;

// 应用公共文件
if (!function_exists('__url')) {
    /**
     * 构建URL地址
     * @param string $url
     * @param array $vars
     * @param bool $suffix
     * @param bool $domain
     * @return string
     */
    function __url(string $url = '', array $vars = [], $suffix = true, $domain = false): string
    {
        return url($url, $vars, $suffix, $domain)->build();
    }
}

if (!function_exists('pathController')) {
    /**
     * 控制器的路径格式
     * @return string
     */
    function pathController(): string
    {
        $path = request()->controller();
        $arr = explode('.', $path);
        if (count($arr) == 1) {
            return lcfirst($path);
        }
        return $arr[0] . '.' . lcfirst($arr[1]);
    }
}

if (!function_exists('sysConfig')) {
    /**
     * 获取系统配置信息
     * @param string $category
     * @param string|null $name
     * @return array|string
     */
    function sysConfig(string $category, string $name = null)
    {
        return SystemConfigService::config($category, $name);
    }

    /**
     * 获取系统配置信息的值
     * @param string $name
     * @return string
     */
    function sysValue(string $name): string
    {
        return sysConfig('', $name);
    }
}

if (!function_exists('authService')) {
    /**
     * 权限处理类
     * @return AuthService
     */
    function authService()
    {
        return app('authService');
    }
}

if (!function_exists('currentUserId')) {
    /**
     * 当前人员ID
     * @return int
     */
    function currentUserId(): int
    {
        return authService()->currentUserId();
    }
}

if (!function_exists('auth')) {
    /**
     * auth权限验证
     * @param $node
     * @return bool
     */
    function auth(string $node = null): bool
    {
        return authService()->check($node);
    }
}

if (!function_exists('roleList')) {
    /**
     * 获取角色列表
     * @return array
     */
    function roleList(): array
    {
        return Db::name('acl_role')
            ->where('status', BaseModel::ENABLE)
            ->where('type', BaseModel::ENABLE)
            ->order('sort')
            ->column('name', 'id');
    }
}