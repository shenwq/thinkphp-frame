<?php
declare (strict_types=1);

namespace ffhome\frame\service;

use ffhome\frame\util\CacheUtil;
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
    public static function getAll()
    {
        return CacheUtil::get('dictDataList', function () {
            $ret = [];
            $parentInfo = self::getByParent(self::P_ROOT);
            foreach ($parentInfo as $p) {
                $info = self::getByParent($p['id']);
                $ret[$p['name']] = $info;
            }
            return $ret;
        }, self::NAME);
    }

    private static function getByParent($parentId)
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
    public static function getByParentName(string $parentName)
    {
        if (empty($parentName)) {
            return [];
        }
        return CacheUtil::get("dictDataListByParentName{$parentName}", function () use ($parentName) {
            return Db::name(self::NAME)->alias('d')
                ->leftJoin(self::NAME . ' p', 'd.parent_id=p.id')
                ->field('d.name,d.value,d.clazz')
                ->where([['p.name', '=', $parentName], ['d.used', '=', 'Y']])
                ->order('d.sort')
                ->select()
                ->toArray();
        }, self::NAME);
    }

    /**
     * 将列表指定的值转成名称
     * @param int|string $value
     * @param string $parentName
     * @return string
     */
    public static function getNameByValue($value, string $parentName)
    {
        $list = self::getByParentName($parentName);
        foreach ($list as $vo) {
            if ($vo['value'] == $value) {
                return $vo['name'];
            }
        }
        return '';
    }
}