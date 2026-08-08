# -*- coding: utf-8 -*-
# KALIBRĀCIJAS HARNESS: jauno biznesa tipu modelis pret katalogs.sqlite reālajām finansēm.
# Metodika: NACE 2.1 kandidāti (≤40 darb., apgrozījums>0) <-> OSM POI tabulu nosaukumi
# (norm() + unikālie + \b ietveršana), 12 izlase/tips izkliedēta pa apgrozījumu,
# modelis (iespeja-x.php, Reālais, ar konkurentiem) katrā reālajā koordinātē.
# Izvade: calib_new.json + kopsavilkums stdout. Mērķa attiecība ~0.85-1.2 (izdzīvotāju nosliece!).
# ATCERIES: bench-ierobežotiem tipiem (vis≈bench) kalibrē footfallBench, ne visitorsPer1000!
import sqlite3, re, json, subprocess
import mysql.connector

import os
SP = os.path.dirname(os.path.abspath(__file__))  # izvade blakus skriptam
IESP = '/Users/mac/Desktop/22.07.2026/_Optimizacija/Iespēja'
DB = dict(host="localhost", port=3306, database="mydb",
          user="mydb", password="PAROLE_IZNEMTA", connection_timeout=30)

TYPES = {  # tips -> (POI tabula vai 'STAY', NACE kodi)
    'Beķereja':        ('mydb_bakery',     ('1071','4724')),
    'Aptieka':         ('mydb_pharmacy',   ('4773',)),
    'Skaistumkopšana': ('mydb_beauty',     ('9622','9623')),
    'Minimārkets':     ('mydb_minimarket', ('4711',)),
    'Zobārsts':        ('mydb_dentist',    ('8623',)),
    'Ātrā ēdināšana':  ('mydb_fastfood',   ('5611','5612','561')),
    'Fitnesa klubs':   ('mydb_fitness',    ('9313','9311')),
    'Viesnīca':        ('STAY',                  ('5510',)),
}

def norm(s):
    s = (s or '').lower()
    s = re.sub(r'\b(sia|as|ik|z/s|zs|veikals|aptieka|salons|salon|beķereja|bekereja|kafejnīca|viesnīca|viesnica|hotel|fitness|klubs|club|zobārstniecība|zobarstnieciba)\b', ' ', s)
    s = re.sub(r'["\'`«»“”‘’]', '', s)
    s = re.sub(r'[^a-z0-9āčēģīķļņšūž]+', ' ', s).strip()
    return s

cn = mysql.connector.connect(**DB); cur = cn.cursor()
sq = sqlite3.connect('/Users/mac/Desktop/22.07.2026/_Optimizacija/server/nace/katalogs.sqlite')
sq.row_factory = sqlite3.Row

out = {}
for typ, (tbl, codes) in TYPES.items():
    if tbl == 'STAY':
        cur.execute("SELECT tname, ST_Y(location), ST_X(location) FROM tourism_geo WHERE ttype IN ('hotel','hostel','guest_house','motel') AND tname IS NOT NULL AND tname <> ''")
    else:
        cur.execute(f"SELECT name, ST_Y(location), ST_X(location) FROM `{tbl}` WHERE name IS NOT NULL AND name <> ''")
    pois = [(r[0], float(r[1]), float(r[2])) for r in cur.fetchall()]
    q = "SELECT name, turnover, profit, employees, avg_gross_salary FROM companies WHERE (" + \
        " OR ".join("nace_code_np = ?" for _ in codes) + ") AND turnover > 0 AND employees BETWEEN 1 AND 40"
    cands = [dict(r) for r in sq.execute(q, codes).fetchall()]
    idx = {}
    for r in cands:
        n = norm(r['name'])
        if len(n) >= 4: idx.setdefault(n, []).append(r)
    idx = {k: v[0] for k, v in idx.items() if len(v) == 1}
    matches, seen = [], set()
    for pname, la, lo in pois:
        pn = norm(pname)
        if len(pn) < 4 or pn in seen: continue
        hit = idx.get(pn)
        if not hit:
            for kn, kr in idx.items():
                if re.search(r'\b' + re.escape(pn) + r'\b', kn) or re.search(r'\b' + re.escape(kn) + r'\b', pn):
                    hit = kr; break
        if hit:
            seen.add(pn)
            matches.append({'osm': pname, 'firma': hit['name'], 'lat': la, 'lng': lo,
                            'real_rev': round(hit['turnover']/12), 'real_emp': hit['employees'],
                            'real_profit': round((hit['profit'] or 0)/12)})
    # izlase līdz 12, izkliedēta pa apgrozījumu
    matches.sort(key=lambda m: m['real_rev'])
    if len(matches) > 12:
        step = len(matches) / 12.0
        matches = [matches[int(i*step)] for i in range(12)]
    print(f"{typ}: kandidāti={len(cands)}, sakritības izlasē={len(matches)}", flush=True)
    for m in matches:
        code = ('$_GET=["action"=>"search","lat"=>"%.6f","lng"=>"%.6f","radius"=>"500","competitors"=>"true"]; include "iespeja-x.php";' % (m['lat'], m['lng']))
        r = subprocess.run(['php', '-r', code], capture_output=True, text=True, cwd=IESP, timeout=120)
        try:
            d = json.loads(r.stdout[r.stdout.index('{'):])
            b = (d.get('statistics') or {}).get('optimal_setups', {}).get(typ)
            if b:
                m['model_rev'] = b['revenue_month']; m['model_profit'] = b['profit_month']
                m['model_staff'] = b['staff']; m['model_vis'] = b['visitors']; m['pos'] = b['positioning']
        except Exception as e:
            m['err'] = str(e)[:60]
    out[typ] = matches
    json.dump(out, open(f'{SP}/calib_new.json', 'w'), ensure_ascii=False)

import statistics
print("\n=== KOPSAVILKUMS (Reālais scenārijs)")
for typ, ms in out.items():
    ok = [m for m in ms if 'model_rev' in m]
    if not ok: print(f"{typ}: nav modeļa rezultātu"); continue
    ratios = sorted(m['model_rev'] / m['real_rev'] for m in ok if m['real_rev'] > 0)
    r_med = ratios[len(ratios)//2]
    st_real = statistics.median(m['real_emp'] for m in ok)
    st_mod = statistics.median(m['model_staff'] for m in ok)
    rev_real = statistics.median(m['real_rev'] for m in ok)
    rev_mod = statistics.median(m['model_rev'] for m in ok)
    neg = sum(1 for m in ok if m['real_profit'] < 0)
    print(f"{typ:<16} n={len(ok):>2} | reālais apgroz. med €{rev_real:>7} vs modelis €{rev_mod:>7} (attiecība {r_med:.2f}) | darbi {st_real:.0f} vs {st_mod:.0f} | reāli zaudē {neg}/{len(ok)}")
