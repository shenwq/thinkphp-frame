<?php
declare (strict_types=1);

namespace ffhome\frame\controller;

use jianyan\excel\Excel;
use think\db\BaseQuery;
use think\facade\Cache;
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
     * 创建者ID的字段名称, false表示没有此功能
     * @var string|bool
     */
    protected $createByField = 'create_by';

    /**
     * 创建时间字段名称
     * @var string
     */
    protected $createTimeField = 'create_time';

    /**
     * 修改者ID的字段名称, false表示没有此功能
     * @var string|bool
     */
    protected $updateByField = 'update_by';

    /**
     * 修改者时间字段名称
     * @var string|bool
     */
    protected $updateTimeField = 'update_time';

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
        return $this->fetch($this->setIndexPage());
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
        if ($count == 0) {
            $list = [];
        } else {
            $list = $this->getSearchModel($where)
                ->page($page, $limit)
                ->field($this->getSearchFields())
                ->order($this->getSearchSort())
                ->select()->toArray();
        }
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
            } else if (Str::endsWith($field, '_empty')) {
                $where[] = [$this->convertFieldName($field, '_empty'), $value == 1 ? '=' : '<>', ''];
            } else if (Str::endsWith($field, '_zero')) {
                if ($value == 1) {
                    $where[] = [$this->convertFieldName($field, '_zero'), '=', 0];
                } else {
                    $where[] = [$this->convertFieldName($field, '_zero'), '<>', 0];
                }
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
        return ["{$this->alias}.id" => 'desc'];
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

    /**
     * 新增数据操作
     * @return mixed
     */
    public function add()
    {
        if ($this->request->isAjax()) {
            $this->addOperate();
        }
        return $this->addPage();
    }

    /**
     * 新增数据页面
     * @return mixed
     */
    protected function addPage()
    {
        $this->assignConstant();
        $this->assign('row', $this->setDefaultValueInAddPage([]));
        return $this->fetch($this->setAddPage());
    }

    /**
     * 新增数据页面中，设置数据的默认值，直接向$row数组增加数据即可，如$row['sort']=1000;
     * @param array $row
     * @return array
     */
    protected function setDefaultValueInAddPage(array $row): array
    {
        return $row;
    }

    /**
     * 设置列表数据的模板
     * @return string
     */
    protected function setIndexPage(): string
    {
        return '';
    }

    /**
     * 设置新增数据的模板，默认'edit'，即新增与编辑使用同一模板
     * @return string
     */
    protected function setAddPage(): string
    {
        return 'edit';
    }

    /**
     * 设置修改数据的模板
     * @return string
     */
    protected function setEditPage(): string
    {
        return '';
    }

    /**
     * 新增数据操作
     */
    protected function addOperate()
    {
        $fields = $this->getAddFilterFields();
        if (!empty($fields)) {
            $data = $this->request->only($fields);
        } else {
            $data = $this->request->param();
        }
        Db::transaction(function () use ($data) {
            $this->onBeforeAdd($data);
            $data['id'] = Db::name($this->modelName)->insertGetId($data);
            $this->onAfterAdd($data);
        });
        $this->success($this->getAddSuccessInfo($data));
    }

    /**
     * 新增成功的信息
     * @param $data
     * @return string
     */
    protected function getAddSuccessInfo($data): string
    {
        return lang('common.save_success');
    }

    /**
     * 新增时的字段数组
     * @return array
     */
    protected function getAddFilterFields(): array
    {
        return $this->getFilterFields();
    }

    /**
     * 修改时的字段数组
     * @return array
     */
    protected function getEditFilterFields(): array
    {
        return $this->getFilterFields();
    }

    /**
     * 保存时（含新增、修改）的字段数组
     * @return array
     */
    protected function getFilterFields(): array
    {
        return [];
    }

    /**
     * 新增操作前触发的事件，默认处理数据验证功能
     * @param array $data
     */
    protected function onBeforeAdd(array &$data)
    {
        //增加创建者与创建时间
        if ($this->createByField !== false) {
            $data[$this->createByField] = app('authService')->currentUserId();
            $data[$this->createTimeField] = date('Y-m-d H:i:s');
        }
        if ($this->updateByField !== false) {
            $data[$this->updateByField] = app('authService')->currentUserId();
            $data[$this->updateTimeField] = date('Y-m-d H:i:s');
        }
        $this->onBeforeSave($data);
        $rule = $this->validateRuleInAdd($data);
        $this->validate($data, $rule);
    }

    /**
     * 新增时的验证规则，可以通过数据创建不同的规则，也可以直接验证数据抛出异常
     * @param array $data
     * @return array
     */
    protected function validateRuleInAdd(array $data): array
    {
        return $this->validateRule($data);
    }

    /**
     * 修改时的验证规则，可以通过数据创建不同的规则，也可以直接验证数据抛出异常
     * @param array $data
     * @return array
     */
    protected function validateRuleInEdit(array $data): array
    {
        return $this->validateRule($data);
    }

    /**
     * 新增与修改时共同的验证规则，可以通过数据创建不同的规则，也可以直接验证数据抛出异常
     * @param array $data
     * @return array
     */
    protected function validateRule(array $data): array
    {
        return [];
    }

    /**
     * 新增操作后触发的事件，默认处理清除模型缓存
     * @param array $data
     */
    protected function onAfterAdd(array &$data)
    {
        $this->onAfterSave($data);
    }

    /**
     * 获取模型数据
     * @param int $id
     * @return array
     */
    protected function getModelInfo(int $id): array
    {
        $row = $this->getSearchModel(["{$this->alias}.id" => $id])
            ->field($this->getSearchFields())
            ->find();
        empty($row) && $this->error(lang('common.data_not_exist'));
        return $row;
    }

    /**
     * 修改数据操作
     * @param int $id 修改数据的主键
     * @return mixed
     */
    public function edit(int $id)
    {
        $row = $this->getModelInfo($id);
        if ($this->request->isAjax()) {
            $this->editOperate($row);
        }
        return $this->editPage($row);
    }

    /**
     * 修改数据页面
     * @param array $row 数据库的原始数据
     * @return mixed
     */
    protected function editPage(array $row)
    {
        $this->assignConstant();
        $this->assign('row', $row);
        return $this->fetch($this->setEditPage());
    }

    /**
     * 修改数据操作
     * @param array $row 数据库的原始数据
     */
    protected function editOperate(array $row)
    {
        $fields = $this->getEditFilterFields();
        if (!empty($fields)) {
            $data = $this->request->only($fields);
        } else {
            $data = $this->request->param();
        }
        Db::transaction(function () use ($data, $row) {
            $this->onBeforeEdit($data, $row);
            Db::name($this->modelName)->update($data);
            $this->onAfterEdit($data, $row);
        });
        $this->success($this->getEditSuccessInfo($data));
    }

    /**
     * 修改成功的信息
     * @param $data
     * @return string
     */
    protected function getEditSuccessInfo($data): string
    {
        return lang('common.save_success');
    }

    /**
     * 修改操作前触发的事件，默认处理乐观锁，数据验证功能
     * @param array $data 修改后的数据
     * @param array $row 数据库原有数据
     */
    protected function onBeforeEdit(array &$data, array $row)
    {
        if ($this->updateByField !== false) {
            if (!empty($data[$this->updateTimeField]) && $data[$this->updateTimeField] != $row[$this->updateTimeField]) {
                $this->error(lang('common.data_overdue'));
            }
            //增加修改者与修改时间
            $data[$this->updateByField] = app('authService')->currentUserId();
            $data[$this->updateTimeField] = date('Y-m-d H:i:s');
        }
        $this->onBeforeSave($data, $row);
        $rule = $this->validateRuleInEdit($data);
        $this->validate($data, $rule);
    }

    /**
     * 修改操作后触发的事件，默认处理清除模型缓存
     * @param array $data 修改后的数据
     * @param array $row 数据库原有数据
     */
    protected function onAfterEdit(array &$data, array $row)
    {
        $this->onAfterSave($data, $row);
    }

    protected function onBeforeSave(array &$data, array $row = [])
    {
    }

    protected function onAfterSave(array &$data, array $row = [])
    {
        $this->clearCache();
    }

    /**
     * 删除操作
     * @param array|int $id 删除的主键
     */
    public function delete($id)
    {
        Db::transaction(function () use ($id) {
            $row = $this->onBeforeDelete($id);
            if ($this->deleteField === false) {
                Db::name($this->modelName)->delete($id);
            } else {
                Db::name($this->modelName)->whereIn('id', $id)->update([$this->deleteField => date('Y-m-d H:i:s')]);
            }
            $this->onAfterDelete($id, $row);
        });
        $this->success($this->getDeleteSuccessInfo($id));
    }

    /**
     * 删除成功的信息
     * @param $id
     * @return string
     */
    protected function getDeleteSuccessInfo($id): string
    {
        return lang('common.delete_success');
    }

    /**
     * 删除操作前触发的事件
     * @param array|int $id 删除的主键
     * @return array 将要删除的数据库数据（可选）
     */
    protected function onBeforeDelete($id): array
    {
        return [];
    }

    /**
     * 删除操作后触发的事件
     * @param array|int $id 删除的主键
     * @param array $row 将要删除的数据库数据（可选）
     */
    protected function onAfterDelete($id, array $row)
    {
        $this->clearCache();
    }

    /**
     * 获取多个模型数据，可在onBeforeDelete事件中调用
     * @param array|int $id 主键
     * @return array
     */
    protected function getModelList($id): array
    {
        $row = $this->getSearchModel([["{$this->alias}.id", 'in', $id]])
            ->field($this->getSearchFields())
            ->select()->toArray();
        empty($row) && $this->error(lang('common.data_not_exist'));
        return $row;
    }

    /**
     * 清除缓存，在数据变化后，会自动调用，默认清除该模型下的所有缓存，可重载清除其他缓存
     */
    protected function clearCache()
    {
        Cache::tag($this->modelName)->clear();
    }
}