<?php
declare (strict_types=1);

namespace ffhome\frame\service;

use ffhome\frame\model\BaseModel;
use ffhome\frame\util\CacheUtil;
use ffhome\util\JwtAuth;
use think\db\BaseQuery;
use think\facade\Db;

/**
 * 权限验证服务
 * 在provider.php文件中增加'authService' => AuthService::class,配置
 */
class AuthService
{
    const NAME = 'authority';
    protected $sessionField = 'u.id,u.username,u.nick_name,u.avatar';

    /**
     * 当前用户ID
     * @return int
     */
    public function currentUserId(): int
    {
        $userId = session(config('app.sess_user') . '.id');
        if (empty($userId)) {
            $token = cookie('token');
            if (!empty($token)) {
                $userId = JwtAuth::verifyToken($token, SystemConfigService::value('token_key'));
                if (!empty($userId)) {
                    $userId = $userId['id'];
                    $user = $this->getUserById($userId);
                    $this->addInfoToSession($user);
                    session(config('app.sess_user'), $user);
                }
            }
            if (empty($userId)) {
                $userId = 0;
            }
        }
        return $userId;
    }

    /**
     * 当前用户信息
     * @return array
     */
    public function currentUser(): array
    {
        $user = session(config('app.sess_user'));
        if (empty($user)) {
            $this->currentUserId();
            $user = session(config('app.sess_user'));
            if (empty($user)) $user = [];
        }
        return $user;
    }

    public function addInfoToSession(array &$info)
    {
    }

    private function getUserDb(): BaseQuery
    {
        return Db::name('acl_user')->alias('u');
    }

    public function getUserByUserName(string $username)
    {
        return $this->getUserDb()
            ->field($this->sessionField . ',u.password,u.login_num')
            ->where(['u.username' => $username, 'u.status' => BaseModel::ENABLE])
            ->whereNull('u.delete_time')
            ->find();
    }

    private function getUserById(int $id)
    {
        return $this->getUserDb()
            ->field($this->sessionField)
            ->where(['u.id' => $id, 'u.status' => BaseModel::ENABLE])
            ->whereNull('u.delete_time')
            ->find();
    }

    /**
     * 当前节点
     * @return string
     */
    public function currentNode(): string
    {
        return request()->controller() . '/' . request()->action();
    }

    /**
     * 权限判断
     * @return bool
     */
    public function check(string $node): bool
    {
        $userId = $this->currentUserId();
        $perms = $this->getPermsByUserId($userId);
        return in_array($node, $perms);
    }

    private function getPermsByUserId(int $userId): array
    {
        if (empty($userId)) {
            return [];
        }
        return CacheUtil::get('auth_code_' . $userId, function () use ($userId) {
            $perms = Db::name('acl_permission')->alias('p')
                ->join('acl_role_permission rp', 'rp.permission_id=p.id')
                ->join('acl_role r', 'r.id=rp.role_id')
                ->join('acl_user_role ur', 'ur.role_id=r.id')
                ->distinct(true)->field('p.perms')
                ->where([
                    ['ur.user_id', '=', $userId],
                    ['r.status', '=', BaseModel::ENABLE],
                    ['p.status', '=', BaseModel::ENABLE],
                    ['p.perms', '<>', ''],
                ])->column('p.perms');
            //将权限分离并去重
            $perms = array_unique(explode(',', implode(',', $perms)));
            return $perms;
        }, self::NAME);
    }

    public function init(): array
    {
        $userId = $this->currentUserId();
        return CacheUtil::get('auth_init_' . $userId, function () use ($userId) {
            $data = [
                'logoInfo' => [
                    'title' => SystemConfigService::value('logo_title'),
                    'image' => SystemConfigService::value('logo_image'),
                    'href' => url('index/index')->build(),
                ],
                'homeInfo' => AclPermissionService::getHomeInfo(),
                'menuInfo' => AclPermissionService::getMenuTree($userId),
                'typeList' => DictDataService::getAll(),
            ];
            return $data;
        }, self::NAME);
    }
}