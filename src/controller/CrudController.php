<?php
declare (strict_types=1);

namespace ffhome\frame\controller;

use jianyan\excel\Excel;
use think\db\BaseQuery;
use think\facade\Db;
use think\helper\Str;

abstract class CrudController extends BaseController
{
    /**
     * 当前模型名称
     * @var string
     */
    protected $modelName;

    /**
     * 当前模型别名
     * @var string
     */
    protected $alias;

    /**
     * 软删除的字段名称, false表示没有软删除功能
     * @var string|bool
     */
    protected $deleteField = false;

    /**
     * 模板布局, false取消
     * @var string|bool
     */
    protected $layout = 'layout/default';

    /**
     * @var int 默认每页记录数
     */
    protected $defaultPageSize = 15;

    /**
     * 初始化方法
     */
    protected function initialize()
    {
        parent::initialize();
        $this->layout && $this->app->view->engine()->layout($this->layout);
    }

    /**
     * 模板变量赋值
     * @param string|array $name 模板变量
     * @param mixed $value 变量值
     * @return mixed
     */
    public function assign($name, $value = null)
    {
        return $this->app->view->assign($name, $value);
    }

    /**
     * 解析和获取模板内容 用于输出
     * @param string $template
     * @param array $vars
     * @return mixed
     */
    public function fetch($template = '', $vars = [])
    {
        return $this->app->view->fetch($template, $vars);
    }

    /**
     * 重写验证规则
     * @param array $data
     * @param array|string $validate
     * @param array $message
     * @param bool $batch
     * @return array|bool|string|true
     */
    public function validate(array $data, $validate, array $message = [], bool $batch = false)
    {
        try {
            parent::validate($data, $validate, $message, $batch);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
        return true;
    }

    /**
     * 定义常量，列表及编辑页面自动调用
     */
    protected function assignConstant()
    {
    }

    /**
     * 列表调用入口，一般无须扩展
     * @return mixed|\think\response\Json
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            return $this->indexOperate();
        }
        $this->assignConstant();
        $param = $this->request->param();
        return $this->indexPage($param);
    }

    /**
     * 列表页面，可扩展此方法，修改$param向列表页面中做调整
     * @param $param
     * @return mixed
     */
    protected function indexPage($param)
    {
        $this->assign('param', $param);
        return $this->fetch();
    }

    /**
     * 实际的列表查询功能
     * @return \think\response\Json
     */
    protected function indexOperate()
    {
        $param = $this->request->param();
        $page = isset($param['page']) && !empty($param['page']) ? intval($param['page']) : 1;
        $limit = isset($param['limit']) && !empty($param['limit']) ? intval($param['limit']) : $this->defaultPageSize;
        $where = $this->buildWhere($param);
        $count = $this->getSearchModel($where)->count();
        $list = $this->getSearchModel($where)
            ->page($page, $limit)
            ->field($this->getSearchFields())
            ->order($this->getSearchSort())
            ->select()->toArray();
        return $this->successPage($count, $list);
    }

    /**
     * 将接收的查询参数转换成ThinkPHP可识别的查询条件，可以扩展此方法自定义规则
     * @param array $param
     * @return array
     */
    protected function buildWhere(array $param): array
    {
        $where = [];
        foreach ($param as $field => $value) {
            if ($value == '') {
                continue;
            }
            if (Str::endsWith($field, '_like')) {
                $where[] = [$this->convertFieldName($field, '_like'), 'LIKE', "%{$value}%"];
            } else if (Str::endsWith($field, '_eq')) {
                $where[] = [$this->convertFieldName($field, '_eq'), '=', $value];
            } else if (Str::endsWith($field, '_ne')) {
                $where[] = [$this->convertFieldName($field, '_ne'), '<>', $value];
            } else if (Str::endsWith($field, '_lt')) {
                $where[] = [$this->convertFieldName($field, '_lt'), '<', $value];
            } else if (Str::endsWith($field, '_time_le')) {
                $where[] = [$this->convertFieldName($field, '_le'), '<', date('Y-m-d', strtotime($value . '+1 day'))];
            } else if (Str::endsWith($field, '_le')) {
                $where[] = [$this->convertFieldName($field, '_le'), '<=', $value];
            } else if (Str::endsWith($field, '_gt')) {
                $where[] = [$this->convertFieldName($field, '_gt'), '>', $value];
            } else if (Str::endsWith($field, '_ge')) {
                $where[] = [$this->convertFieldName($field, '_ge'), '>=', $value];
            } else if (Str::endsWith($field, '_in')) {
                $where[] = [$this->convertFieldName($field, '_in'), 'in', $value];
            } else if (Str::endsWith($field, '_find_in_set')) {
                $where[] = [$this->convertFieldName($field, '_find_in_set'), 'find in set', $value];
            } else if (Str::endsWith($field, '_null')) {
                $where[] = [$this->convertFieldName($field, '_null'), 'exp', Db::raw($value == 1 ? 'is null' : 'is not null')];
            } else if (Str::endsWith($field, '_range')) {
                $f = $this->convertFieldName($field, '_range');
                [$beginTime, $endTime] = explode(' - ', $value);
                $where[] = [$f, '>=', $beginTime];
                $where[] = [$f, '<=', $endTime];
            } else if (Str::endsWith($field, '_or')) {
                $f = explode('_or_', Str::substr($field, 0, Str::length($field) - Str::length('_or')));
                $p = [];
                foreach ($f as $name) {
                    $p[$name] = $value;
                }
                $w = $this->buildWhere($p);
                $condition = [];
                foreach ($w as $it) {
                    // TODO:此次只处理基础的条件语句，复杂的后期再增加
                    $condition[] = "{$it[0]} {$it[1]} '{$it[2]}'";
                }
                $where[] = Db::raw(implode(' or ', $condition));
            }
        }
        return $where;
    }

    /**
     * 字段名转换函数，给子类处理查询字段使用，不需要扩展
     * @param string $field
     * @param string $op
     * @return string
     */
    protected function convertFieldName(string $field, string $op): string
    {
        $pos = strpos($field, '_');
        if ($pos !== false) {
            $field[$pos] = '.';
        }
        $field = Str::substr($field, 0, Str::length($field) - Str::length($op));
        return $field;
    }

    /**
     * 获取基本的查询模型，通常扩展次方法增加关联表处理
     * @return \think\db\BaseQuery
     */
    protected function getBaseModel(): BaseQuery
    {
        return Db::name($this->modelName)->alias($this->alias);
    }

    /**
     * 获取查询模型
     * @return \think\db\BaseQuery
     */
    protected function getSearchModel($where): BaseQuery
    {
        $model = $this->getBaseModel();
        $model = $model->where($where);
        if ($this->deleteField !== false) {
            $model = $model->whereNull("{$this->alias}.{$this->deleteField}");
        }
        return $model;
    }

    protected function getSearchFields(): string
    {
        return '*';
    }

    protected function getSearchSort(): array
    {
        $order = $this->request->get('order', '');
        if (empty($order)) {
            return $this->getSearchDefaultSort();
        }
        $field = $this->request->get('field', '');
        return [$field => $order];
    }

    /**
     * 列表默认排序方式，可重写定义排序方式
     * @return array
     */
    protected function getSearchDefaultSort(): array
    {
        return ['id' => 'desc'];
    }

    /**
     * 导出Excel入口
     * @return bool
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function export()
    {
        $header = $this->getExportHeader();
        if (empty($header)) {
            $this->error('请后台设置好导出的表头信息');
        }
        $where = $this->buildWhere($this->request->param());
        $list = $this->getSearchModel($where)
            ->limit(100000)
            ->field($this->getSearchFields())
            ->order($this->getSearchSort())
            ->select()->toArray();
        $fileName = date('YmdHis');
        return Excel::exportData($list, $header, $fileName, 'xlsx');
    }

    /**
     * 导出Excel配置函数
     * 如  return [[lang('common.id'), 'id'], [lang('log.username'), 'username']];
     * @return array|null
     */
    protected function getExportHeader()
    {
        return null;
    }
}