# -*- coding: utf-8 -*-
# Pilnais audits: 12 tipi × 8 vietas — vērtību robežas, iekšējās paritātes, ģeogrāfiskā loģika
import json, subprocess

IESP = '/Users/mac/Desktop/22.07.2026/_Optimizacija/Iespēja'
SP = '/private/tmp/claude-501/-Users-mac-Desktop/738b0cb2-286e-48ab-b12d-551a6d6d0b99/scratchpad'
CITIES = {
    'Vecrīga':    (56.94964, 24.10500, 'riga'),
    'Ģertrūdes':  (56.95740, 24.12650, 'riga'),
    'Purvciems':  (56.95600, 24.19650, 'riga'),
    'Jūrmala':    (56.97200, 23.79720, 'kurorts'),
    'Liepāja':    (56.51100, 21.01100, 'piekraste'),
    'Daugavpils': (55.87470, 26.53610, 'iekszeme'),
    'Madona':     (56.85360, 26.21550, 'iekszeme'),
    'Valmiera':   (57.53870, 25.42640, 'iekszeme'),
}
TYPES = ['Kafejnīca','Restorāns','Krogs','Frizētava','Beķereja','Aptieka','Skaistumkopšana',
         'Minimārkets','Zobārsts','Ātrā ēdināšana','Fitnesa klubs','Viesnīca']
SEASON_TYPES = {'Kafejnīca','Restorāns','Krogs','Beķereja','Ātrā ēdināšana','Viesnīca'}
MINSTAFF = {'Kafejnīca':2,'Restorāns':3,'Krogs':2,'Frizētava':1,'Beķereja':2,'Aptieka':1,
            'Skaistumkopšana':1,'Minimārkets':2,'Zobārsts':2,'Ātrā ēdināšana':2,'Fitnesa klubs':1,'Viesnīca':2}
SALARY = {'Kafejnīca':(800,1500),'Restorāns':(900,2200),'Krogs':(850,1700),'Frizētava':(750,2000),
          'Beķereja':(750,1400),'Aptieka':(1100,2200),'Skaistumkopšana':(800,2200),'Minimārkets':(750,1300),
          'Zobārsts':(1400,3500),'Ātrā ēdināšana':(800,1400),'Fitnesa klubs':(850,1800),'Viesnīca':(850,1600)}

def run(la, lo):
    code = ('$_GET=["action"=>"search","lat"=>"%.6f","lng"=>"%.6f","radius"=>"500","competitors"=>"true"]; include "iespeja-x.php";' % (la, lo))
    r = subprocess.run(['php', '-r', code], capture_output=True, text=True, cwd=IESP, timeout=180)
    return json.loads(r.stdout[r.stdout.index('{'):])

viol = []
results = {}
for city, (la, lo, zone) in CITIES.items():
    d = run(la, lo)
    s = d['statistics']
    results[city] = d
    setups = s.get('optimal_setups') or {}
    if len(setups) != 12: viol.append(f"{city}: optimal_setups {len(setups)} != 12")
    for t in TYPES:
        b = setups.get(t)
        if not b: viol.append(f"{city}/{t}: NAV optimuma"); continue
        # sim paritāte
        sim = b.get('sim') or []
        if len(sim) != 5: viol.append(f"{city}/{t}: sim garums {len(sim)}")
        row = next((r2 for r2 in sim if r2['check'] == b['check']), None)
        if not row or abs(row['profit'] - b['profit_month']) > 1:
            viol.append(f"{city}/{t}: virsraksts €{b['profit_month']} != sim €{row['profit'] if row else '?'}")
        # robežas
        if b['staff'] < MINSTAFF[t]: viol.append(f"{city}/{t}: staff {b['staff']} < min")
        lo_s, hi_s = SALARY[t]
        if not (lo_s - 1 <= b['salary'] <= hi_s + 1): viol.append(f"{city}/{t}: alga €{b['salary']} ārpus [{lo_s},{hi_s}]")
        if abs(b['profit_month']) > 30000: viol.append(f"{city}/{t}: aizdomīga peļņa €{b['profit_month']}")
        if b['visitors'] < 0: viol.append(f"{city}/{t}: negatīvi apmeklētāji")
        exp_rev = b['visitors'] * b['check'] / 1.21 * 30
        if b['revenue_month'] > 0 and not (0.5 < exp_rev / b['revenue_month'] < 2.0):
            viol.append(f"{city}/{t}: apgrozījuma nesakritība €{b['revenue_month']} vs ~€{exp_rev:.0f}")
        # sezona
        has_season = 'season_zone' in b
        if has_season != (t in SEASON_TYPES): viol.append(f"{city}/{t}: sezonas lauks {'ir' if has_season else 'nav'} nepareizi")
        if has_season and b['season_zone'] != zone: viol.append(f"{city}/{t}: zona {b['season_zone']} != {zone}")
    # konkurenti
    cf = d.get('competitors_found') or {}
    for t in TYPES:
        if t not in cf: viol.append(f"{city}: competitors_found trūkst {t}")

print("=== PEĻŅAS MATRICA (Reālais, €/mēn)")
hdr = f"{'Tips':<16}" + ''.join(f"{c[:9]:>10}" for c in CITIES)
print(hdr)
for t in TYPES:
    row = f"{t:<16}"
    for city in CITIES:
        b = (results[city]['statistics'].get('optimal_setups') or {}).get(t)
        row += f"{b['profit_month'] if b else '—':>10}"
    print(row)

print("\n=== ĢEOGRĀFISKĀS LOĢIKAS PĀRBAUDES")
def prof(city, t): return (results[city]['statistics']['optimal_setups'].get(t) or {}).get('profit_month', -99999)
checks = [
    ("Viesnīca Vecrīgā/Jūrmalā > Purvciemā", max(prof('Vecrīga','Viesnīca'), prof('Jūrmala','Viesnīca')) > prof('Purvciems','Viesnīca')),
    ("Minimārkets Purvciemā > Vecrīgā", prof('Purvciems','Minimārkets') > prof('Vecrīga','Minimārkets')),
    ("Ātrā ēdināšana Ģertrūdes > Madonā", prof('Ģertrūdes','Ātrā ēdināšana') > prof('Madona','Ātrā ēdināšana')),
    ("Aptieka Ģertrūdes > Purvciemā", prof('Ģertrūdes','Aptieka') > prof('Purvciems','Aptieka')),
    ("Beķereja Ģertrūdes > Purvciemā", prof('Ģertrūdes','Beķereja') > prof('Purvciems','Beķereja')),
]
for name, ok in checks:
    print(f"  {'✓' if ok else '✗ NEIZPILDĀS'}: {name}")
    if not ok: viol.append(f"loģika: {name}")

print(f"\n=== PĀRKĀPUMI: {len(viol)}")
for v in viol: print("  !", v)
json.dump({c: results[c]['statistics']['optimal_setups'] for c in CITIES},
          open(f'{SP}/audit12.json', 'w'), ensure_ascii=False)
