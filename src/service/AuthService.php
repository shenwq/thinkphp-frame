<?php
declare (strict_types=1);

namespace ffhome\frame\service;

use ffhome\frame\model\BaseModel;
use ffhome\util\JwtAuth;
use think\facade\Cache;
use think\facade\Db;

/**
 * 权限验证服务
 */
class AuthService
{
    const NAME = 'authority';
    private $sessionField = 'u.id,u.username,u.nick_name,u.avatar';

    /**
     * 当前用户ID
     * @return string
     */
    public function currentUserId()
    {
        $userId = session(config('app.sess_user') . '.id');
        if (empty($userId)) {
            $token = cookie('token');
            if (!empty($token)) {
                $userId = JwtAuth::verifyToken($token, (new SystemConfigService())->value('token_key'));
                if (!empty($userId)) {
                    $userId = $userId['id'];
                    $user = $this->getUserById($userId);
                    session(config('app.sess_user'), $user);
                }
            }
            if (empty($userId)) {
                $userId = '0';
            }
        }
        return $userId;
    }

    private function getUserDb()
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

    private function getUserById(string $id)
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
    public function currentNode()
    {
        return request()->controller() . '/' . request()->action();
    }

    /**
     * 权限判断
     * @return bool
     */
    public function check(string $node)
    {
        $userId = $this->currentUserId();
        $perms = $this->getPermsByUserId($userId);
        return in_array($node, $perms);
    }

    private function getPermsByUserId(string $userId)
    {
        if (empty($userId)) {
            return [];
        }
        $perms = Cache::get('auth_code_' . $userId);
        if (empty($perms)) {
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
            Cache::tag(self::NAME)->set('auth_code_' . $userId, $perms);
        }
        return $perms;
    }

    public function init()
    {

        $userId = $this->currentUserId();
        $data = Cache::get('auth_init_' . $userId);
        if (empty($data)) {
            $config = new SystemConfigService();
            $permission = new AclPermissionService();
            $data = [
                'logoInfo' => [
                    'title' => $config->value('logo_title'),
                    'image' => $config->value('logo_image'),
                    'href' => url('index/index')->build(),
                ],
                'homeInfo' => $permission->getHomeInfo(),
                'menuInfo' => $permission->getMenuTree($userId),
                'typeList' => (new DictDataService())->getAll(),
            ];
            Cache::tag(self::NAME)->set('auth_init_' . $userId, $data);
        }
        return $data;
    }
}