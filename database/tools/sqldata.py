"""Parse INSERT data and CREATE TABLE columns from a MySQL dump / install script."""
import re


def _split_values(s, i):
    """Parse '(v,v,...),(...)' starting at s[i]=='('. Returns (rows, end_index)."""
    rows = []
    n = len(s)
    while i < n:
        while i < n and s[i] in ' \t\r\n,':
            i += 1
        if i >= n or s[i] != '(':
            break
        i += 1
        row, cur, inq = [], [], False
        quoted = False
        while i < n:
            c = s[i]
            if inq:
                if c == '\\' and i + 1 < n:
                    nxt = s[i + 1]
                    cur.append({'n': '\n', 'r': '\r', 't': '\t', '0': '\0', 'Z': '\x1a'}.get(nxt, nxt))
                    i += 2
                    continue
                if c == "'":
                    if i + 1 < n and s[i + 1] == "'":
                        cur.append("'")
                        i += 2
                        continue
                    inq = False
                    i += 1
                    continue
                cur.append(c)
                i += 1
                continue
            if c == "'":
                inq, quoted = True, True
                i += 1
                continue
            if c == ',' or c == ')':
                v = ''.join(cur).strip()
                row.append(v if quoted else (None if v.upper() == 'NULL' else v))
                cur, quoted = [], False
                i += 1
                if c == ')':
                    break
                continue
            cur.append(c)
            i += 1
        rows.append(row)
    return rows, i


def parse(path):
    """Returns (columns_by_table, rows_by_table, create_sql_by_table)."""
    s = open(path, encoding='utf-8', errors='replace').read()
    cols, creates = {}, {}
    for m in re.finditer(r'CREATE TABLE (?:IF NOT EXISTS )?`(\w+)`\s*\((.*?)\n\)([^;]*);', s, re.S):
        t = m.group(1)
        creates[t] = m.group(0)
        cols[t] = re.findall(r'^\s*`(\w+)`', m.group(2), re.M)
    rows = {}
    for m in re.finditer(r'INSERT INTO `(\w+)`\s*(\(([^)]*)\))?\s*VALUES\s*', s):
        t = m.group(1)
        colnames = [c.strip(' `') for c in m.group(3).split(',')] if m.group(3) else cols.get(t)
        data, _ = _split_values(s, m.end())
        for r in data:
            if colnames and len(colnames) == len(r):
                rows.setdefault(t, []).append(dict(zip(colnames, r)))
    return cols, rows, creates
