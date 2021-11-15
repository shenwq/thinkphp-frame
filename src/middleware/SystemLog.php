<?php
declare (strict_types=1);

namespace ffhome\frame\middleware;

use ffhome\common\util\CommonUtil;
use ffhome\frame\service\AuthService;
use ffhome\frame\service\SystemLogService;
use think\Request;

/**
 * 系统操作日志中间件
 */
class SystemLog
{
    /**
     * 敏感信息字段，日志记录时需要加密
     * @var array
     */
    protected $sensitiveParams = [
        'password',
        'password_again',
    ];

    public function handle(Request $request, \Closure $next)
    {
        if ($request->isAjax()) {
            $method = strtolower($request->method());
            if (in_array($method, ['get', 'post', 'put', 'delete'])) {
                $url = $request->url();
                $ip = CommonUtil::getRealIp();
                $params = $request->param();
                $fingerprint = cookie('fingerprint');
                if (isset($params['s'])) {
                    unset($params['s']);
                }
                foreach ($params as $key => $val) {
                    in_array($key, $this->sensitiveParams) && $params[$key] = CommonUtil::password($val);
                }
                $data = [
                    'user_id' => (new AuthService())->currentUserId(),
                    'url' => $url,
                    'method' => $method,
                    'ip' => $ip,
                    'content' => json_encode($params, JSON_UNESCAPED_UNICODE),
                    'useragent' => $_SERVER['HTTP_USER_AGENT'],
                    'create_time' => date('Y-m-d H:i:s'),
                ];
                if (!empty($fingerprint)) {
                    $data['fingerprint'] = $fingerprint;
                }
                (new SystemLogService())->save($data);
            }
        }
        return $next($request);
    }
}