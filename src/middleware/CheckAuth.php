<?php
declare (strict_types=1);

namespace ffhome\frame\middleware;

use Closure;
use ffhome\frame\traits\JumpTrait;
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
        $authService = authService();
        $currentNode = $authService->currentNode();
        // 验证直接登录页面
        if (in_array($currentNode, $authConfig['no_login'])) {
            $this->jumpDirect($currentNode);
            return $next($request);
        }

        $userId = $authService->currentUserId();
        // 验证用户登录
        if (empty($userId)) {
            $this->error(lang('common.login_first'), [], url('admin/login/index')->build());
        }
        // 验证不需要权限页面
        if (in_array($currentNode, $authConfig['no_auth'])) {
            $this->jumpNotNeedAuth($currentNode, $userId);
            return $next($request);
        }

        // 验证页面权限
        if (!$authService->check($currentNode)) {
            $this->errorNotAuth($currentNode, $userId);
            $this->error(lang('common.not_auth'));
        }

        $this->jump($currentNode, $userId);
        return $next($request);
    }

    /**
     * 直接跳转接口
     * @param $currentNode
     */
    protected function jumpDirect($currentNode)
    {
    }

    /**
     * 跳转不需要权限接口
     * @param $currentNode
     * @param $userId
     */
    protected function jumpNotNeedAuth($currentNode, $userId)
    {
    }

    /**
     * 验证权限后正常跳转接口
     * @param $currentNode
     * @param $userId
     */
    protected function jump($currentNode, $userId)
    {
    }

    /**
     * 验证用户页面权限错误后的接口
     * @param $currentNode
     * @param $userId
     */
    protected function errorNotAuth($currentNode, $userId)
    {
        trace(lang('common.not_auth') . $currentNode . ':' . $userId);
    }
}