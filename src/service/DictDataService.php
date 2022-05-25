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
     * @param array $where 查找根节点条件
     * @param string $field 列表的字段信息
     * @return array
     */
    public static function getAll($where = [], string $field = 'name,value,clazz')
    {
        return CacheUtil::get('dictDataList_' . json_encode($where) . '_' . $field, function () use ($where, $field) {
            $ret = [];
            $parentInfo = self::getByParent(self::P_ROOT, '', $where);
            foreach ($parentInfo as $p) {
                $info = self::getByParent($p['id'], $field);
                $ret[$p['name']] = $info;
            }
            return $ret;
        }, self::NAME);
    }

    private static function getByParent($parentId, string $field = 'name,value,clazz', $where = [])
    {
        if ($parentId == self::P_ROOT) {
            $field = 'id,name';
        }

        $w = [['parent_id', '=', $parentId], ['used', '=', 'Y']];
        $where = array_merge($w, $where);
        return Db::name(self::NAME)
            ->field($field)
            ->where($where)
            ->order('sort')
            ->select()
            ->toArray();
    }

    /**
     * 按名称取出配置信息
     * @param string $parentName 列表名称
     * @param string $field 查询字段
     * @return array
     */
    public static function getByParentName(string $parentName, string $field = 'd.name,d.value,d.clazz')
    {
        if (empty($parentName)) {
            return [];
        }
        return CacheUtil::get("dictDataListByParentName{$parentName}_{$field}", function () use ($parentName, $field) {
            return Db::name(self::NAME)->alias('d')
                ->leftJoin(self::NAME . ' p', 'd.parent_id=p.id')
                ->field($field)
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