# -*- coding: utf-8 -*-
"""
Viltus mysql.connector — pieraksta INSERT rindas CSV failā, MySQL neaiztiek.

Kāpēc: 5.–9. solis raksta RAŽOŠANAS datubāzi. Lai salīdzinātu PHP portu ar
Python oriģinālu, vajag zināt, ko Python IEVIETOTU, to reāli neievietojot.
Šis modulis PYTHONPATH sākumā aizēno īsto draiveri un katru executemany()
izraksta failā <PYSHIM_OUT>/<tabula>.csv tieši tādā secībā, kādā rindas ietu
uz serveri. PHP pusē to pašu dara --dry-run.

Formāts sakrīt ar IeSink sauso režīmu: None → tukšs, float → repr, pārējais → str.

Lietošana:
    PYSHIM_OUT=/kāda/mape PYTHONPATH=.../pyshim python3 "6 Offices.py"
"""
import csv
import os
import re
import sys

__version__ = 'shim-1.0'


class Error(Exception):
    pass


class InterfaceError(Error):
    pass


class DatabaseError(Error):
    pass


_INSERT_RE = re.compile(r'INSERT\s+(?:IGNORE\s+)?INTO\s+`?([A-Za-z0-9_]+)`?', re.I)
_TABLE_RE = re.compile(r'(?:CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?|TRUNCATE\s+TABLE)\s+`?([A-Za-z0-9_]+)`?', re.I)


def _out_dir():
    d = os.environ.get('PYSHIM_OUT') or '.'
    os.makedirs(d, exist_ok=True)
    return d


def _fmt(v):
    if v is None:
        return ''
    if isinstance(v, bool):
        return '1' if v else '0'
    if isinstance(v, float):
        return repr(v)
    return str(v)


_SELECT_RE = re.compile(r'SELECT\s+(.*?)\s+FROM\s', re.I | re.S)


def _select_arity(sql):
    """Cik kolonnu atdod SELECT — lai fetchone() atgrieztu īstā garuma kopu.
    Skaita komatus tikai ārpus iekavām, citādi COUNT(*), SUM(x) skaitītos divreiz."""
    m = _SELECT_RE.search(sql)
    if not m:
        return 1
    depth, n = 0, 1
    for ch in m.group(1):
        if ch == '(':
            depth += 1
        elif ch == ')':
            depth -= 1
        elif ch == ',' and depth == 0:
            n += 1
    return n


class _Cursor:
    def __init__(self, conn):
        self._conn = conn
        self._arity = 1

    # — rakstīšana —
    def execute(self, sql, params=None, multi=False):
        m = _TABLE_RE.search(sql)
        if m:
            self._conn._touch(m.group(1), sql)
        m = _INSERT_RE.search(sql)
        if m and params:
            self._conn._write(m.group(1), [params])
        if _SELECT_RE.search(sql):
            self._arity = _select_arity(sql)

    def executemany(self, sql, seq):
        m = _INSERT_RE.search(sql)
        if not m:
            return
        self._conn._write(m.group(1), list(seq))

    # — lasīšana: skripti pēc ielādes izdrukā kopsavilkumu no DB —
    def fetchone(self):
        return tuple([0] * self._arity)

    def fetchall(self):
        return []

    def close(self):
        pass

    @property
    def rowcount(self):
        return 0


class _Connection:
    def __init__(self, **kw):
        self._dir = _out_dir()
        self._files = {}
        self._writers = {}
        self._counts = {}
        sys.stderr.write('[pyshim] MySQL NETIEK aiztikts; rindas → %s\n' % self._dir)

    def _touch(self, table, sql):
        # CREATE/TRUNCATE tikai pieteic tabulu; failu izveido pirmais INSERT.
        self._counts.setdefault(table, 0)

    def _write(self, table, rows):
        if table not in self._writers:
            path = os.path.join(self._dir, table + '.csv')
            fh = open(path, 'w', newline='', encoding='utf-8')
            self._files[table] = fh
            self._writers[table] = csv.writer(fh)
        w = self._writers[table]
        for r in rows:
            w.writerow([_fmt(v) for v in r])
        self._counts[table] = self._counts.get(table, 0) + len(rows)

    def cursor(self, **kw):
        return _Cursor(self)

    def commit(self):
        for fh in self._files.values():
            fh.flush()

    def is_connected(self):
        return True

    def close(self):
        for table, fh in self._files.items():
            fh.close()
            sys.stderr.write('[pyshim] %s: %d rindas\n' % (table, self._counts[table]))
        self._files = {}
        self._writers = {}

    def __del__(self):
        try:
            self.close()
        except Exception:
            pass


def connect(**kw):
    return _Connection(**kw)
