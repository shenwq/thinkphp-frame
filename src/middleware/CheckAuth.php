<?php
declare (strict_types=1);

namespace ffhome\frame\middleware;

use Closure;
use ffhome\frame\traits\JumpTrait;
use ffhome\frame\service\AuthService;
use think\Request;

/**
 * 检测用户登录和节点权限
 */
class CheckAuth
{
    use JumpTrait;

    public function handle(Request $request, Closure $next)
    {
        $authConfig = config('auth');
        $authService = new AuthService();
        $currentNode = $authService->currentNode();
        // 验证登录
        if (in_array($currentNode, $authConfig['no_login'])) {
            return $next($request);
        }

        $userId = $authService->currentUserId();
        // 验证登录
        if (empty($userId)) {
            $this->error(lang('common.login_first'), [], url('admin/login/index')->build());
        }
        // 验证权限
        if (in_array($currentNode, $authConfig['no_auth'])) {
            return $next($request);
        }

        // 验证权限
        if (!$authService->check($currentNode)) {
            trace(lang('common.not_auth') . $currentNode);
            $this->error(lang('common.not_auth'));
        }

        return $next($request);
    }
}