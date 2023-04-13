<?php
declare (strict_types=1);

namespace ffhome\frame\service;

use think\facade\Config;
use think\facade\Db;

/**
 * 系统删除表
 */
class SystemDeleteService
{
    /**
     * 表前缀
     * @var string
     */
    protected $tablePrefix;

    /**
     * 表后缀
     * @var string
     */
    protected $tableSuffix;

    /**
     * 表名
     * @var string
     */
    protected $tableName;

    /**
     * 构造方法
     */
    public function __construct()
    {
        $this->tablePrefix = Config::get('database.connections.mysql.prefix');
        $this->tableSuffix = date('Ym', time());
        $this->tableName = "{$this->tablePrefix}system_delete_{$this->tableSuffix}";
        return $this;
    }


    /**
     * 保存数据
     * @param $data
     * @return bool|string
     */
    public function save($data)
    {
        $this->detectTable();
        Db::startTrans();
        try {
            Db::table($this->tableName)->insert($data);
            Db::commit();
        } catch (\Exception $e) {
            return $e->getMessage();
        }
        return true;
    }

    /**
     * 检测数据表
     * @return bool
     */
    protected function detectTable()
    {
        $cacheTable = cache("delete_table_{$this->tableName}");
        if (empty($cacheTable)) {
            $check = Db::query("show tables like '{$this->tableName}'");
            if (empty($check)) {
                $sql = $this->getCreateSql();
                Db::execute($sql);
            }
            cache("delete_table_{$this->tableName}", true);
        }
        return true;
    }

    /**
     * 根据后缀获取创建表的sql
     * @return string
     */
    protected function getCreateSql()
    {
        return <<<EOT
CREATE TABLE `{$this->tableName}` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `table_name` varchar(50) NOT NULL DEFAULT '' COMMENT '表名',
  `outer_id` bigint unsigned NOT NULL DEFAULT 0 COMMENT '外部ID',
  `user_id` bigint unsigned NOT NULL DEFAULT 0 COMMENT '操作人ID',
  `create_time` datetime NOT NULL DEFAULT '1900-01-01 00:00:00' COMMENT '操作时间',
  `content` longtext NOT NULL COMMENT '内容',
  PRIMARY KEY (`id`)
) COMMENT='后台操作日志表 - {$this->tableSuffix}';
EOT;
    }
}