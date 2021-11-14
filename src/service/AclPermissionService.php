<?php
declare (strict_types=1);

namespace ffhome\frame\service;

use ffhome\common\util\CommonUtil;
use ffhome\frame\model\BaseModel;
use think\facade\Cache;
use think\facade\Db;

/**
 * 权限相关处理
 */
class AclPermissionService
{
    const C_MENU = 1;
    const C_HOME = 3;

    const P_ROOT = 0;

    const NAME = 'acl_permission';

    public function getHomeInfo()
    {
        $data = Cache::get('home_info');
        if (empty($data)) {
            $data = Db::name(self::NAME)->field('title,icon,href')
                ->where('catalog', self::C_HOME)
                ->where('status', BaseModel::ENABLE)
                ->find();
            Cache::tag(self::NAME)->set('home_info', $data);
        }
        return $data;
    }

    public function getMenuTree($userId)
    {
        if (empty($userId)) {
            return [];
        }
        $list = Db::name(self::NAME)->alias('p')
            ->join('acl_role_permission rp', 'rp.permission_id=p.id')
            ->join('acl_role r', 'r.id=rp.role_id')
            ->join('acl_user_role ur', 'ur.role_id=r.id')
            ->field('p.id, p.pid, p.title, p.href, p.target, p.icon')
            ->where([
                'ur.user_id' => $userId,
                'r.status' => BaseModel::ENABLE,
                'p.status' => BaseModel::ENABLE,
                'p.catalog' => self::C_MENU])
            ->group('p.id')->order('p.sort')->select()->toArray();
        return CommonUtil::getTree($list, self::P_ROOT);
    }
}