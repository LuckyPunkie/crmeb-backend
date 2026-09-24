<?php

namespace app\common\repositories\user;

use app\common\dao\user\UserProfileFieldDao as dao;
use app\common\repositories\BaseRepository;
use think\exception\ValidateException;

class UserProfileFieldRepository extends BaseRepository
{
    protected $dao;

    public const TYPES = ['text', 'radio', 'checkbox', 'year', 'height', 'weight', 'region', 'number'];

    public function __construct(dao $dao)
    {
        $this->dao = $dao;
    }

    /** 后台「手机号」字段键；取值仅 eb_user.phone */
    public const ACCOUNT_PHONE_FIELD_KEY = 'user_phone';

    /** 后台「真实姓名」字段键；取值/保存同步 eb_user.real_name */
    public const REAL_NAME_FIELD_KEY = 'real_name';

    public function lst(array $where = []): array
    {
        $list = $this->dao->getList($where);
        foreach ($list as &$row) {
            $row = $this->formatRow($row);
        }
        unset($row);
        return ['list' => $list, 'count' => count($list)];
    }

    public function visibleFields(): array
    {
        return $this->enabledFields();
    }

    /** 已上架字段：个人资料编辑页（含手机号，按后台 sort） */
    public function enabledFields(): array
    {
        $list = $this->dao->getEnabledList();
        foreach ($list as &$row) {
            $row = $this->formatRow($row);
        }
        unset($row);
        return $list;
    }

    /** 已上架且主页展示：个人主页「个人信息」 */
    public function displayFields(): array
    {
        $list = $this->dao->getDisplayList();
        foreach ($list as &$row) {
            $row = $this->formatRow($row);
        }
        unset($row);
        return $list;
    }

    protected function filterProfileEditExcluded(array $list): array
    {
        return array_values($list);
    }

    /** @deprecated */
    protected function filterDeprecatedFields(array $list): array
    {
        return array_values($list);
    }

    public function create(array $data): void
    {
        $payload = $this->normalizeInput($data, true);
        if ($this->dao->existsKey($payload['field_key'])) {
            throw new ValidateException('字段键已存在');
        }
        if (($payload['field_key'] ?? '') === self::ACCOUNT_PHONE_FIELD_KEY) {
            throw new ValidateException('手机号字段为系统预置，请编辑已有项');
        }
        if (($payload['field_key'] ?? '') === self::REAL_NAME_FIELD_KEY) {
            throw new ValidateException('真实姓名字段为系统预置，请编辑已有项');
        }
        $payload['is_system'] = 0;
        // 新增一律排到当前列表最后
        $payload['sort'] = $this->nextSort();
        $this->dao->create($payload);
    }

    protected function nextSort(): int
    {
        $list = $this->dao->getList();
        $max = 0;
        foreach ($list as $row) {
            $s = (int)($row['sort'] ?? 0);
            if ($s > $max) {
                $max = $s;
            }
        }
        return $max + 10;
    }

    public function update(int $id, array $data): void
    {
        $row = $this->dao->get($id);
        if (!$row) {
            throw new ValidateException('字段不存在');
        }
        $rowArr = is_array($row) ? $row : $row->toArray();
        $payload = $this->normalizeInput($data, false, $rowArr);
        // 系统预置字段不允许改 field_key / bind_column / type / is_system
        // 改 type 会破坏与数据库列 & 后端保存/查询逻辑的耦合（如 int 列改 checkbox 会数据丢失）
        if ((int)($rowArr['is_system'] ?? 0) === 1) {
            unset($payload['field_key'], $payload['bind_column'], $payload['type'], $payload['is_system']);
        } else {
            if (isset($payload['field_key']) && $this->dao->existsKey($payload['field_key'], $id)) {
                throw new ValidateException('字段键已存在');
            }
        }
        $this->dao->update($id, $payload);
    }

    public function delete(int $id): void
    {
        $row = $this->dao->get($id);
        if (!$row) {
            throw new ValidateException('字段不存在');
        }
        $rowArr = is_array($row) ? $row : $row->toArray();
        if ((int)($rowArr['is_system'] ?? 0) === 1) {
            throw new ValidateException('系统预置字段不可删除，可下架或关闭主页展示');
        }
        $this->dao->delete($id);
    }

    public function setShow(int $id, int $isShow): void
    {
        $row = $this->dao->get($id);
        if (!$row) {
            throw new ValidateException('字段不存在');
        }
        $this->dao->update($id, ['is_show' => $isShow ? 1 : 0]);
    }

    public function setStatus(int $id, int $status): void
    {
        $row = $this->dao->get($id);
        if (!$row) {
            throw new ValidateException('字段不存在');
        }
        $this->dao->update($id, ['status' => $status ? 1 : 0]);
    }

    public function saveSort(array $ids): void
    {
        $sort = 10;
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id <= 0) {
                continue;
            }
            $this->dao->update($id, ['sort' => $sort]);
            $sort += 10;
        }
    }

    /**
     * 将字段元数据与用户取值合并（含 bind_column / extra_fields）
     */
    public function attachValues(array $fields, array $profile, array $context = []): array
    {
        $accountPhone = trim((string)($context['account_phone'] ?? ''));
        $accountRealName = trim((string)($context['account_real_name'] ?? ''));
        $extra = $profile['extra_fields'] ?? [];
        if (is_string($extra)) {
            $decoded = json_decode($extra, true);
            $extra = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($extra)) {
            $extra = [];
        }

        foreach ($fields as &$field) {
            $key = $field['field_key'];
            $bind = (string)($field['bind_column'] ?? '');
            if ($key === self::ACCOUNT_PHONE_FIELD_KEY || $key === 'contact_phone') {
                $field['value'] = $accountPhone;
            } elseif ($key === self::REAL_NAME_FIELD_KEY) {
                $field['value'] = $accountRealName !== '' ? $accountRealName : (string)($extra[$key] ?? '');
            } elseif ($bind !== '' && array_key_exists($bind, $profile)) {
                $field['value'] = $profile[$bind];
            } elseif (array_key_exists($key, $extra)) {
                $field['value'] = $extra[$key];
            } else {
                $field['value'] = $field['type'] === 'checkbox' ? [] : '';
            }
            if ($field['type'] === 'checkbox' && !is_array($field['value'])) {
                $raw = $field['value'];
                if (is_string($raw) && $raw !== '') {
                    // 有 bind_column 的多选字段用 CSV 存整数 id（如 dating_purpose="1,2"）
                    if ($bind !== '' && preg_match('/^\d+(,\d+)*$/', $raw)) {
                        $opts = is_array($field['options'] ?? null) ? $field['options'] : [];
                        $ids = array_filter(array_map('intval', explode(',', $raw)));
                        $labels = [];
                        foreach ($ids as $id) {
                            if (isset($opts[$id - 1])) $labels[] = $opts[$id - 1];
                        }
                        $field['value'] = $labels;
                    } else {
                        $decoded = json_decode($raw, true);
                        $field['value'] = is_array($decoded) ? $decoded : [$raw];
                    }
                } else {
                    $field['value'] = [];
                }
            }
        }
        unset($field);
        return $fields;
    }

    protected function formatRow(array $row): array
    {
        $opts = $row['options'] ?? [];
        if (is_string($opts)) {
            $decoded = json_decode($opts, true);
            $opts = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($opts)) {
            $opts = [];
        }
        $row['options'] = array_values($opts);
        $row['is_show'] = (int)($row['is_show'] ?? 0);
        $row['status'] = (int)($row['status'] ?? 1);
        $row['is_system'] = (int)($row['is_system'] ?? 0);
        $row['sort'] = (int)($row['sort'] ?? 0);
        $row['icon'] = (string)($row['icon'] ?? '');
        $row['bind_column'] = (string)($row['bind_column'] ?? '');
        $row['placeholder'] = (string)($row['placeholder'] ?? '');
        return $row;
    }

    protected function normalizeInput(array $data, bool $isCreate, array $exist = []): array
    {
        $title = trim((string)($data['title'] ?? ($exist['title'] ?? '')));
        if ($title === '') {
            throw new ValidateException('请填写字段文案');
        }

        $type = (string)($data['type'] ?? ($exist['type'] ?? 'text'));
        if (!in_array($type, self::TYPES, true)) {
            throw new ValidateException('组件类型不支持');
        }

        $fieldKey = trim((string)($data['field_key'] ?? ($exist['field_key'] ?? '')));
        if ($isCreate) {
            if ($fieldKey === '') {
                $fieldKey = $this->makeFieldKey($title);
            }
            $fieldKey = strtolower($fieldKey);
            if (!preg_match('/^[a-z][a-z0-9_]{1,62}$/', $fieldKey)) {
                throw new ValidateException('字段键需以字母开头，仅含小写字母数字下划线');
            }
        } elseif ($fieldKey !== '') {
            $fieldKey = strtolower($fieldKey);
            if (!preg_match('/^[a-z][a-z0-9_]{1,62}$/', $fieldKey)) {
                throw new ValidateException('字段键需以字母开头，仅含小写字母数字下划线');
            }
        }

        $options = $data['options'] ?? ($exist['options'] ?? []);
        if (is_string($options)) {
            $decoded = json_decode($options, true);
            $options = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($options)) {
            $options = [];
        }
        $options = array_values(array_filter(array_map(function ($v) {
            return trim((string)$v);
        }, $options), function ($v) {
            return $v !== '';
        }));
        if (in_array($type, ['radio', 'checkbox'], true) && !$options) {
            throw new ValidateException('单选/多选请至少配置一个选项');
        }

        $payload = [
            'title' => mb_substr($title, 0, 64),
            'icon' => mb_substr(trim((string)($data['icon'] ?? ($exist['icon'] ?? ''))), 0, 512),
            'type' => $type,
            'options' => json_encode($options, JSON_UNESCAPED_UNICODE),
            'placeholder' => mb_substr(trim((string)($data['placeholder'] ?? ($exist['placeholder'] ?? ''))), 0, 128),
            'is_show' => isset($data['is_show']) ? ((int)$data['is_show'] ? 1 : 0) : (int)($exist['is_show'] ?? 1),
            'status' => isset($data['status']) ? ((int)$data['status'] ? 1 : 0) : (int)($exist['status'] ?? 1),
            'sort' => isset($data['sort']) ? (int)$data['sort'] : (int)($exist['sort'] ?? 100),
            'bind_column' => mb_substr(trim((string)($data['bind_column'] ?? ($exist['bind_column'] ?? ''))), 0, 64),
        ];
        if ($isCreate || $fieldKey !== '') {
            $payload['field_key'] = $fieldKey;
        }
        return $payload;
    }

    protected function makeFieldKey(string $title): string
    {
        $base = 'f_' . substr(md5($title . microtime(true)), 0, 10);
        return $base;
    }
}
