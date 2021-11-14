<?php
declare (strict_types=1);

namespace ffhome\frame\service;

use think\facade\Cache;
use think\facade\Db;

/**
 * 数据字典相关处理
 */
class DictDataService
{
    const NAME = 'dict_data';

    const P_ROOT = 0;

    /**
     * 取出全部配置信息
     * @return array
     */
    public function getAll()
    {
        $cacheName = 'dictDataList';
        $ret = Cache::get($cacheName);
        if (empty($ret)) {
            $ret = [];
            $parentInfo = $this->getByParent(self::P_ROOT);
            foreach ($parentInfo as $p) {
                $info = $this->getByParent($p['id']);
                $ret[$p['name']] = $info;
            }
            Cache::tag(self::NAME)->set($cacheName, $ret);
        }
        return $ret;
    }

    private function getByParent($parentId)
    {
        if ($parentId == self::P_ROOT) {
            $field = 'id,name';
        } else {
            $field = 'name,value,clazz';
        }

        return Db::name(self::NAME)
            ->field($field)
            ->where([['parent_id', '=', $parentId], ['used', '=', 'Y']])
            ->order('sort')
            ->select()
            ->toArray();
    }

    /**
     * 按名称取出配置信息
     * @param string $parentName
     * @return array
     */
    public function getByParentName(string $parentName)
    {
        if (empty($parentName)) {
            return [];
        }
        $cacheName = "dictDataListByParentName{$parentName}";
        $list = Cache::get($cacheName);
        if (empty($list)) {
            $list = Db::name(self::NAME)->alias('d')
                ->leftJoin(self::NAME . ' p', 'd.parent_id=p.id')
                ->field('d.name,d.value,d.clazz')
                ->where([['p.name', '=', $parentName], ['d.used', '=', 'Y']])
                ->order('sort')
                ->select()
                ->toArray();
            Cache::tag(self::NAME)->set($cacheName, $list);
        }
        return $list;
    }
}