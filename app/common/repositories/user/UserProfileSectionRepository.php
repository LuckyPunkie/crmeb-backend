<?php

namespace app\common\repositories\user;

use app\common\dao\user\UserProfileSectionDao as dao;
use app\common\repositories\BaseRepository;
use think\exception\ValidateException;

/**
 * 交友资料版块配置
 * - kind=fields：可配置字段版块，字段通过 eb_user_profile_field.section = section_key 归属
 * - 其余 kind 为 App 页面内置版块，子项（items）只能改名称 / 开关，清单以 DEFAULT_ITEMS 为准
 * 版块或子项隐藏后：App 交友资料编辑页不显示，个人主页也不展示
 */
class UserProfileSectionRepository extends BaseRepository
{
    protected $dao;

    public const KIND_FIELDS = 'fields';

    public const KINDS = ['fields', 'photos', 'about', 'hope', 'hobby', 'privacy', 'cert'];

    /** 内置版块的子项清单（key => 默认名称）；新增子项只需改这里，库里旧配置会自动补齐 */
    public const DEFAULT_ITEMS = [
        'hope' => [
            'hope_age_min' => '希望年龄下限',
            'hope_age_max' => '希望年龄上限',
            'hope_height_min' => '希望身高下限',
            'hope_height_max' => '希望身高上限',
            'hope_education' => '希望学历',
            'hope_cities' => '希望所在地',
            'hope_text' => '补充说明',
        ],
        'privacy' => [
            'impression_wall' => '印象墙',
        ],
        'cert' => [
            'cert_identity' => '实名认证',
            'cert_education' => '学历认证',
            'cert_work' => '工作认证',
            'cert_income' => '收入认证',
            'cert_car' => '车产认证',
            'cert_house' => '房产认证',
        ],
    ];

    public function __construct(dao $dao)
    {
        $this->dao = $dao;
    }

    /** 后台：全部版块（含隐藏） */
    public function lst(): array
    {
        $list = array_map([$this, 'formatRow'], $this->dao->getList());
        return ['list' => $list, 'count' => count($list)];
    }

    /**
     * App / 个人主页：只下发显示中的版块，子项只留显示中的
     * 内置版块的子项全被关掉时，前端按「整块隐藏」处理
     */
    public function publicConfig(): array
    {
        $out = [];
        foreach ($this->dao->getList() as $row) {
            $row = $this->formatRow($row);
            if (!$row['is_show']) {
                continue;
            }
            $items = [];
            foreach ($row['items'] as $item) {
                if ($item['is_show']) {
                    $items[] = ['key' => $item['key'], 'title' => $item['title']];
                }
            }
            $out[] = [
                'key' => $row['section_key'],
                'title' => $row['title'],
                'kind' => $row['kind'],
                'items' => $items,
            ];
        }
        return $out;
    }

    /**
     * 字段版块的 key
     * @param bool $onlyShown true 只要显示中的（用于过滤字段），false 全部（用于校验字段归属）
     */
    public function fieldSectionKeys(bool $onlyShown): array
    {
        $keys = [];
        foreach ($this->dao->getList() as $row) {
            if (($row['kind'] ?? '') !== self::KIND_FIELDS) {
                continue;
            }
            if ($onlyShown && !(int)($row['is_show'] ?? 0)) {
                continue;
            }
            $keys[] = (string)$row['section_key'];
        }
        return $keys;
    }

    public function create(array $data): void
    {
        $title = $this->normalizeTitle($data['title'] ?? '');
        do {
            $key = 's_' . substr(md5(uniqid('', true)), 0, 8);
        } while ($this->dao->existsKey($key));
        $maxSort = 0;
        foreach ($this->dao->getList() as $row) {
            $maxSort = max($maxSort, (int)$row['sort']);
        }
        $this->dao->create([
            'section_key' => $key,
            'title' => $title,
            'kind' => self::KIND_FIELDS,
            'items' => '[]',
            'is_show' => isset($data['is_show']) ? ((int)$data['is_show'] ? 1 : 0) : 1,
            'is_system' => 0,
            'sort' => $maxSort + 10,
        ]);
    }

    /** 改名称 / 开关 / 子项（子项只认 DEFAULT_ITEMS 里的 key，只改 title 和 is_show） */
    public function update(int $id, array $data): void
    {
        $row = $this->dao->get($id);
        if (!$row) {
            throw new ValidateException('版块不存在');
        }
        $row = $this->formatRow(is_array($row) ? $row : $row->toArray());
        $payload = [];
        if (array_key_exists('title', $data) && $data['title'] !== null && $data['title'] !== '') {
            $payload['title'] = $this->normalizeTitle($data['title']);
        }
        if (array_key_exists('is_show', $data) && $data['is_show'] !== null && $data['is_show'] !== '') {
            $payload['is_show'] = (int)$data['is_show'] ? 1 : 0;
        }
        if (isset(self::DEFAULT_ITEMS[$row['kind']]) && is_array($data['items'] ?? null)) {
            $patch = [];
            foreach ($data['items'] as $item) {
                if (is_array($item) && isset($item['key'])) {
                    $patch[(string)$item['key']] = $item;
                }
            }
            $items = [];
            foreach ($row['items'] as $item) {
                $p = $patch[$item['key']] ?? [];
                $title = trim((string)($p['title'] ?? ''));
                $items[] = [
                    'key' => $item['key'],
                    'title' => $title !== '' ? mb_substr($title, 0, 32) : $item['title'],
                    'is_show' => isset($p['is_show']) ? ((int)$p['is_show'] ? 1 : 0) : $item['is_show'],
                ];
            }
            $payload['items'] = json_encode($items, JSON_UNESCAPED_UNICODE);
        }
        if ($payload) {
            $this->dao->update($id, $payload);
        }
    }

    public function delete(int $id): void
    {
        $row = $this->dao->get($id);
        if (!$row) {
            throw new ValidateException('版块不存在');
        }
        $row = is_array($row) ? $row : $row->toArray();
        if ((int)($row['is_system'] ?? 0) === 1) {
            throw new ValidateException('预置版块不可删除，可隐藏');
        }
        $fieldCount = \think\facade\Db::name('user_profile_field')->where('section', $row['section_key'])->count();
        if ($fieldCount > 0) {
            throw new ValidateException('版块里还有 ' . $fieldCount . ' 个字段，请先移到其他版块或删除');
        }
        $this->dao->delete($id);
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

    protected function formatRow(array $row): array
    {
        $kind = in_array($row['kind'] ?? '', self::KINDS, true) ? $row['kind'] : self::KIND_FIELDS;
        $row['kind'] = $kind;
        $row['id'] = (int)$row['id'];
        $row['is_show'] = (int)($row['is_show'] ?? 1);
        $row['is_system'] = (int)($row['is_system'] ?? 0);
        $row['sort'] = (int)($row['sort'] ?? 0);
        $row['items'] = $this->mergeItems($kind, $row['items'] ?? '[]');
        return $row;
    }

    /** 按 DEFAULT_ITEMS 的顺序输出：库里有配置用库里的，缺的用默认，多余的丢弃 */
    protected function mergeItems(string $kind, $raw): array
    {
        $defaults = self::DEFAULT_ITEMS[$kind] ?? [];
        if (!$defaults) {
            return [];
        }
        $saved = is_string($raw) ? json_decode($raw, true) : $raw;
        $map = [];
        foreach (is_array($saved) ? $saved : [] as $item) {
            if (is_array($item) && isset($item['key'])) {
                $map[(string)$item['key']] = $item;
            }
        }
        $items = [];
        foreach ($defaults as $key => $title) {
            $s = $map[$key] ?? [];
            $t = trim((string)($s['title'] ?? ''));
            $items[] = [
                'key' => $key,
                'title' => $t !== '' ? $t : $title,
                'default_title' => $title,
                'is_show' => isset($s['is_show']) ? (int)$s['is_show'] : 1,
            ];
        }
        return $items;
    }

    protected function normalizeTitle($title): string
    {
        $title = trim((string)$title);
        if ($title === '') {
            throw new ValidateException('请填写版块名称');
        }
        return mb_substr($title, 0, 32);
    }
}
