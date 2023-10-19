<?php
declare (strict_types=1);

namespace ffhome\frame\model;

use ffhome\frame\util\CacheUtil;
use think\facade\Db;

class AclUser extends BaseModel
{
    const MODEL_NAME = 'acl_user';

    public static function getListByRoleId($roleId)
    {
        return CacheUtil::get("userListByRoleId_{$roleId}", function () use ($roleId) {
            return Db::name(self::MODEL_NAME)->alias('u')
                ->leftJoin(AclUserRole::MODEL_NAME . ' ur', 'ur.user_id=u.id')
                ->where([
                    ['ur.role_id', '=', $roleId],
                    ['u.status', '=', self::ENABLE],
                    ['u.delete_time', 'exp', Db::raw('is null')],
                ])
                ->order(['id' => 'desc'])
                ->column('u.nickname', 'u.id');
        }, self::MODEL_NAME);
    }
}