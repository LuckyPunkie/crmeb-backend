"""
生成「我们的库 → 官方 v4.2」升级 SQL（可重复执行）。

    python gen_upgrade_v42.py <官方v4.0 install.sql> <官方v4.2 install.sql> <我们的库 mysqldump.sql> <输出.sql>

思路：不直接用官方 backup/update_4_2.sql（那是 4.1→4.2，且会整行覆盖通知模板、
写死菜单 ID），而是：
  - 表结构：以官方 v4.2 安装 SQL 为准，对比我们库的实际结构，只补缺的表 / 字段，不删不改已有的
  - 内置数据（菜单 / 配置 / 通知 / 链接等）：对比官方 v4.0 与 v4.2，得出官方的增删改，
    再按我们库的现状决定：官方新增 → 插入（ID 被占用则不指定 ID）；
    官方修改 / 删除 → 只有我们没改过（仍等于 v4.0）才执行，SQL 里带上 v4.0 原值作为条件
  - 新菜单权限：上级菜单已授权给哪个商户类型 / 角色，新菜单同样授权；新的一级菜单授权给所有商户类型
每次彩排或上线前，用最新的正式库 dump 重新生成一次。
"""
import re
import sys
from collections import defaultdict

sys.path.insert(0, __file__.rsplit('/', 1)[0] if '/' in __file__ else __file__.rsplit('\\', 1)[0])
import sqldata  # noqa: E402

inst40, inst42, ours_dump, out_path = sys.argv[1:5]
c40, r40, cr40 = sqldata.parse(inst40)
c42, r42, cr42 = sqldata.parse(inst42)
co, ro, cro = sqldata.parse(ours_dump)

IGN = {'create_time', 'update_time', 'add_time'}
out = []
report = []


def q(v):
    if v is None:
        return 'NULL'
    return "'" + str(v).replace('\\', '\\\\').replace("'", "\\'").replace('\n', '\\n').replace('\r', '\\r') + "'"


def strip(r):
    return {k: v for k, v in r.items() if k not in IGN}


def by(rows, key):
    return {r[key]: r for r in rows}


def col_defs(create_sql):
    defs = {}
    for line in create_sql.split('\n')[1:]:
        m = re.match(r'\s*`(\w+)`\s+(.*?),?\s*$', line)
        if m:
            defs[m.group(1)] = m.group(2)
    return defs


# ---------------------------------------------------------------- header
out.append('-- 由 database/tools/gen_upgrade_v42.py 生成，勿手改；可重复执行')
out.append('SET NAMES utf8mb4;')
out.append('SET FOREIGN_KEY_CHECKS = 0;')
out.append('''
DROP PROCEDURE IF EXISTS crmeb_v42_add_col;
DELIMITER $$
CREATE PROCEDURE crmeb_v42_add_col(IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl TEXT)
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col) THEN
    SET @crmeb_sql = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN ', ddl);
    PREPARE crmeb_stmt FROM @crmeb_sql;
    EXECUTE crmeb_stmt;
    DEALLOCATE PREPARE crmeb_stmt;
  END IF;
END$$
DELIMITER ;
''')

# ---------------------------------------------------------------- 1. new tables
out.append('-- ========== 1. 新表（官方 v4.1 / v4.2 新增） ==========')
for t in sorted(cr42):
    if t in cro:
        continue
    sql = re.sub(r'^CREATE TABLE `', 'CREATE TABLE IF NOT EXISTS `', cr42[t])
    sql = re.sub(r'\s+AUTO_INCREMENT=\d+', '', sql)
    out.append(sql)
    report.append(f'新表 {t}')

# ---------------------------------------------------------------- 2. new columns
out.append('\n-- ========== 2. 已有表新增字段 ==========')
for t in sorted(cr42):
    if t not in cro:
        continue
    d42 = col_defs(cr42[t])
    dours = col_defs(cro[t])
    order = list(d42)
    for i, c in enumerate(order):
        if c in dours:
            continue
        prev = order[i - 1] if i else None
        ddl = f'`{c}` {d42[c]}' + (f' AFTER `{prev}`' if prev else '')
        out.append(f'CALL crmeb_v42_add_col({q(t)}, {q(c)}, {q(ddl)});')
        report.append(f'加字段 {t}.{c}')

# 官方唯一一处真正的类型变化（其余差异只是注释文字）
out.append("ALTER TABLE `eb_delivery_order` MODIFY COLUMN `service_fee` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '服务费';")
# 数据回填：v4.2 用 user_recharge.status 区分已支付(1) / 已退款(-1)
out.append("UPDATE `eb_user_recharge` SET `status` = 1 WHERE `paid` = 1 AND `status` IS NULL;")

# ---------------------------------------------------------------- 3. config classify / config
out.append('\n-- ========== 3. 系统配置分类 / 配置项 ==========')
A, B, O = by(r40['eb_system_config_classify'], 'config_classify_id'), by(r42['eb_system_config_classify'], 'config_classify_id'), by(ro['eb_system_config_classify'], 'config_classify_id')
okeys = {r['classify_key'] for r in O.values()}
for k in sorted(set(B) - set(A), key=int):
    b = B[k]
    if b['classify_key'] in okeys:
        continue
    cols = [c for c in b if not (c == 'config_classify_id' and k in O)]
    out.append(f"INSERT INTO `eb_system_config_classify` ({', '.join('`'+c+'`' for c in cols)}) "
               f"SELECT {', '.join(q(b[c]) for c in cols)} FROM DUAL WHERE NOT EXISTS "
               f"(SELECT 1 FROM `eb_system_config_classify` WHERE `classify_key` = {q(b['classify_key'])});")
    report.append(f"配置分类 {b['classify_name']}({b['classify_key']})")
for k in set(A) & set(B):
    if strip(A[k]) == strip(B[k]) or k not in O:
        continue
    if strip(O[k]) != strip(A[k]):
        report.append(f'[跳过·我们改过] 配置分类 {k}')
        continue
    sets = ', '.join(f'`{c}` = {q(B[k][c])}' for c in B[k] if c not in IGN and B[k][c] != A[k][c])
    conds = ' AND '.join(f'`{c}` = {q(A[k][c])}' for c in A[k] if c not in IGN and B[k][c] != A[k][c])
    out.append(f"UPDATE `eb_system_config_classify` SET {sets} WHERE `config_classify_id` = {k} AND {conds};")

A, B, O = by(r40['eb_system_config'], 'config_id'), by(r42['eb_system_config'], 'config_id'), by(ro['eb_system_config'], 'config_id')
Bc = by(r42['eb_system_config_classify'], 'config_classify_id')
okeys = {r['config_key'] for r in O.values()}
for k in sorted(set(B) - set(A), key=int):
    b = B[k]
    if b['config_key'] in okeys:
        continue
    cols = [c for c in b if c not in ('config_id',)]
    vals = []
    for c in cols:
        if c == 'config_classify_id':
            vals.append(f"(SELECT `config_classify_id` FROM `eb_system_config_classify` WHERE `classify_key` = {q(Bc[b[c]]['classify_key'])} LIMIT 1)")
        else:
            vals.append(q(b[c]))
    out.append(f"INSERT INTO `eb_system_config` ({', '.join('`'+c+'`' for c in cols)}) SELECT {', '.join(vals)} FROM DUAL "
               f"WHERE NOT EXISTS (SELECT 1 FROM `eb_system_config` WHERE `config_key` = {q(b['config_key'])});")
    report.append(f"配置项 {b['config_name']}({b['config_key']})")
upd = 0
for k in set(A) & set(B):
    a, b = A[k], B[k]
    diff = [c for c in b if c not in IGN and a.get(c) != b.get(c)]
    if not diff:
        continue
    # 按 config_key 定位（我们库的 config_id 可能不同），且只在字段仍等于 v4.0 原值时更新
    for c in diff:
        out.append(f"UPDATE `eb_system_config` SET `{c}` = {q(b[c])} WHERE `config_key` = {q(a['config_key'])} AND `{c}` <=> {q(a[c])};")
        upd += 1
report.append(f'配置项说明 / 名称等更新 {upd} 处（仅限我们未改过的）')

# ---------------------------------------------------------------- 4. misc seed data
out.append('\n-- ========== 4. 其他内置数据 ==========')
# 通知配置：只加官方新增的（官方对已有通知的修改是他们自己的模板 ID / 短信开关，不覆盖）
A, B = by(r40['eb_system_notice_config'], 'notice_config_id'), by(r42['eb_system_notice_config'], 'notice_config_id')
for k in sorted(set(B) - set(A), key=int):
    b = B[k]
    cols = [c for c in b if c != 'notice_config_id']
    out.append(f"INSERT INTO `eb_system_notice_config` ({', '.join('`'+c+'`' for c in cols)}) SELECT {', '.join(q(b[c]) for c in cols)} FROM DUAL "
               f"WHERE NOT EXISTS (SELECT 1 FROM `eb_system_notice_config` WHERE `const_key` = {q(b['const_key'])});")
    report.append(f"通知配置 {b['notice_title']}({b['const_key']})")
# 店铺会员权益初始数据（新表）
for b in r42.get('eb_merchant_member_benefits', []):
    cols = list(b)
    out.append(f"INSERT IGNORE INTO `eb_merchant_member_benefits` ({', '.join('`'+c+'`' for c in cols)}) VALUES ({', '.join(q(b[c]) for c in cols)});")
report.append(f"店铺会员权益初始数据 {len(r42.get('eb_merchant_member_benefits', []))} 条")
# DIY 链接
A, B = by(r40['eb_page_link'], 'id'), by(r42['eb_page_link'], 'id')
for k in sorted(set(B) - set(A), key=int):
    b = B[k]
    cols = [c for c in b if c != 'id']
    out.append(f"INSERT INTO `eb_page_link` ({', '.join('`'+c+'`' for c in cols)}) SELECT {', '.join(q(b[c]) for c in cols)} FROM DUAL "
               f"WHERE NOT EXISTS (SELECT 1 FROM `eb_page_link` WHERE `url` = {q(b['url'])});")
    report.append(f"DIY 链接 {b['name']} {b['url']}")
# 活动分类：系统默认分类
for b in r42.get('eb_store_activity_cate', []):
    cols = [c for c in b if c != 'id']
    out.append(f"INSERT INTO `eb_store_activity_cate` ({', '.join('`'+c+'`' for c in cols)}) SELECT {', '.join(q(b[c]) for c in cols)} FROM DUAL "
               f"WHERE NOT EXISTS (SELECT 1 FROM `eb_store_activity_cate` WHERE `name` = {q(b['name'])} AND `mer_id` = {q(b['mer_id'])});")
    report.append(f"活动分类 {b['name']}")

# ---------------------------------------------------------------- 5. menus
out.append('\n-- ========== 5. 菜单 ==========')
A, B, O = by(r40['eb_system_menu'], 'menu_id'), by(r42['eb_system_menu'], 'menu_id'), by(ro['eb_system_menu'], 'menu_id')
added = sorted(set(B) - set(A), key=int)
deleted = sorted(set(A) - set(B), key=int)
changed = sorted((k for k in set(A) & set(B) if strip(A[k]) != strip(B[k])), key=int)
mcols = [c for c in c42['eb_system_menu']]
for k in added:
    b = B[k]
    if k in O and not (O[k]['route'] == b['route'] and O[k]['is_mer'] == b['is_mer']):
        raise SystemExit(f'菜单 ID {k} 已被我们库占用（{O[k]["menu_name"]}），需要先处理')
    out.append(f"INSERT INTO `eb_system_menu` ({', '.join('`'+c+'`' for c in mcols)}) SELECT {', '.join(q(b.get(c)) for c in mcols)} FROM DUAL "
               f"WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = {k});")
n_upd = n_skip = 0
for k in changed:
    a, b = A[k], B[k]
    if k not in O or strip(O[k]) != strip(a):
        if k in O and strip(O[k]) != strip(b):
            report.append(f"[跳过·我们改过] 菜单 {k} {O[k]['menu_name']} {O[k]['route']}")
            n_skip += 1
        continue
    diff = [c for c in b if c not in IGN and a[c] != b[c]]
    sets = ', '.join(f'`{c}` = {q(b[c])}' for c in diff)
    conds = ' AND '.join(f'`{c}` <=> {q(a[c])}' for c in diff)
    out.append(f"UPDATE `eb_system_menu` SET {sets} WHERE `menu_id` = {k} AND {conds};")
    n_upd += 1
for k in deleted:
    a = A[k]
    if k in O and strip(O[k]) == strip(a):
        out.append(f"DELETE FROM `eb_system_menu` WHERE `menu_id` = {k} AND `route` = {q(a['route'])} AND `pid` = {a['pid']};")
report.append(f'菜单：新增 {len(added)}，更新 {n_upd}，删除 {len(deleted)}，跳过（我们改过）{n_skip}')

# ---------------------------------------------------------------- 6. grant new menus
out.append('\n-- ========== 6. 新菜单授权（按上级菜单继承） ==========')
# topological order: parents first
order, seen = [], set()


def visit(k):
    if k in seen:
        return
    seen.add(k)
    p = B[k]['pid']
    if p in B and p in set(added):
        visit(p)
    order.append(k)


for k in added:
    visit(k)
for k in order:
    b = B[k]
    p = b['pid']
    if b['is_mer'] == '1':
        if p == '0':
            out.append(f"INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT t.`mer_type_id`, {k}, 'mer_auth' FROM `eb_merchant_type` t "
                       f"WHERE NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = t.`mer_type_id` AND r.`right_id` = {k});")
        else:
            out.append(f"INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, {k}, 'mer_auth' FROM `eb_relevance` p "
                       f"WHERE p.`type` = 'mer_auth' AND p.`right_id` = {p} AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = {k});")
    if p != '0':
        out.append(f"UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '{k}') "
                   f"WHERE FIND_IN_SET('{p}', `rules`) AND NOT FIND_IN_SET('{k}', `rules`);")
report.append('新菜单授权：商户类型(mer_auth) 与角色(rules) 按上级菜单继承')

# ---------------------------------------------------------------- footer
out.append('\nDROP PROCEDURE IF EXISTS crmeb_v42_add_col;')
out.append('SET FOREIGN_KEY_CHECKS = 1;')

open(out_path, 'w', encoding='utf-8', newline='\n').write('\n'.join(out) + '\n')
print('\n'.join(report))
print(f'-> {out_path} ({len(out)} statements)')
