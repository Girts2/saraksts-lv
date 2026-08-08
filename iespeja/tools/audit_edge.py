# -*- coding: utf-8 -*-
# MALĒJO GADĪJUMU AUDITS: ievades, tukšas vietas, scenāriju/konkurentu konsistence,
# rādiusa galējības, siltumkartes kļūdu ceļi, get_points visi 12 tipi.
# HARNESS MĀCĪBA: php -r vajag ["k"=>"v"] masīvu (json.dumps objekts = parse error!).
import json, subprocess, time

IESP = '/Users/mac/Desktop/22.07.2026/_Optimizacija/Iespēja'
TYPES = ['Kafejnīca','Restorāns','Krogs','Frizētava','Beķereja','Aptieka','Skaistumkopšana',
         'Minimārkets','Zobārsts','Ātrā ēdināšana','Fitnesa klubs','Viesnīca']
viol, notes = [], []

def call(params, timeout=180):
    pairs = ','.join('"%s"=>"%s"' % (k, str(v).replace('"', '')) for k, v in params.items())
    php = '$_GET=[' + pairs + ']; include "iespeja-x.php";'
    t0 = time.time()
    for att in range(3):  # pārejošas DB kļūmes — atkārto
        r = subprocess.run(['php', '-r', php], capture_output=True, text=True, cwd=IESP, timeout=timeout)
        out = r.stdout
        try:
            d = json.loads(out[out.index('{'):])
            return d, time.time() - t0, None
        except Exception:
            if att < 2: time.sleep(5)
    return None, time.time() - t0, (out[:200] + '|STDERR:' + r.stderr[:150])

# ============ A. Nederīgas/malējas ievades ============
print("A. Ievades...", flush=True)
d,_,raw = call({'action':'search','lat':'abc','lng':'24.1','radius':'500'})
if not d or not d.get('error'): viol.append(f"A1 lat=abc: jādod kļūda, saņemts {raw or d.get('error')}")
d,_,_ = call({'action':'search','lat':'59.437','lng':'24.754','radius':'500'})  # Tallina
if d is None: viol.append("A2 Tallina: nederīgs JSON")
elif d.get('error'): notes.append(f"A2 Tallina: kļūda '{d['error']}' (pieņemami)")
else:
    if (d.get('points_count') or 0) > 0: viol.append("A2 Tallina: atrastas ēkas ārpus LV?!")
    else: notes.append("A2 Tallina: 0 ēkas, tukša atbilde — OK")
d,_,_ = call({'action':'search','lat':'56.9496','lng':'24.105','radius':'50'})  # zem min
if d is None or d.get('error'): viol.append("A3 radius=50: negaidīta kļūda")
elif d.get('radius_used') != 500: viol.append(f"A3 radius=50: radius_used={d.get('radius_used')} (gaidīts fallback 500)")
d,_,_ = call({'action':'search','lat':'56.9496','lng':'24.105','radius':'500','scenario':'xyz'})
if d is None or d.get('scenario_used') != 'realistic': viol.append(f"A4 scenario=xyz: {d.get('scenario_used') if d else 'JSON?'}")
d,_,_ = call({'action':'heatmap','n':'57.5','s':'56.5','e':'25','w':'24','btype':'Kafejnīca'})  # par lielu bbox
if d is None or not d.get('error'): viol.append("A5 milzu bbox: jādod kļūda")
d,_,_ = call({'action':'heatmap','n':'56.96','s':'56.95','e':'24.12','w':'24.10','btype':'Nekas'})
if d is None or not d.get('error'): viol.append("A6 btype=Nekas: jādod kļūda")

# ============ B. Tukšas lokācijas ============
print("B. Tukšas vietas...", flush=True)
for name, la, lo in [('jūra', 57.30, 23.00), ('mežs (Ķemeru purvi)', 56.955, 23.45), ('lauki (Latgale)', 56.40, 27.30)]:
    d, dt, raw = call({'action':'search','lat':str(la),'lng':str(lo),'radius':'500','competitors':'true'})
    if d is None: viol.append(f"B {name}: nederīgs JSON: {raw}"); continue
    pc = d.get('points_count') or 0
    setups = (d.get('statistics') or {}).get('optimal_setups')
    notes.append(f"B {name}: ēkas={pc}, optimal={'ir' if setups else 'nav'}, {dt:.1f}s")
    if pc == 0 and setups: viol.append(f"B {name}: optimal_setups bez ēkām?!")

# ============ C. Scenāriju sakārtotība: opt >= real >= pess ============
print("C. Scenāriji...", flush=True)
for name, la, lo in [('Vecrīga',56.94964,24.105), ('Madona',56.8536,26.2155), ('Liepāja',56.511,21.011)]:
    res = {}
    for sc in ('pessimistic','realistic','optimistic'):
        d,_,raw = call({'action':'search','lat':str(la),'lng':str(lo),'radius':'500','competitors':'true','scenario':sc})
        if d is None: viol.append(f"C {name}/{sc}: nederīga atbilde {raw}"); res[sc] = {}; continue
        res[sc] = (d.get('statistics') or {}).get('optimal_setups') or {}
    for t in TYPES:
        p = res['pessimistic'].get(t, {}).get('profit_month')
        r = res['realistic'].get(t, {}).get('profit_month')
        o = res['optimistic'].get(t, {}).get('profit_month')
        if None in (p, r, o):
            if res['pessimistic'] and res['realistic'] and res['optimistic']: viol.append(f"C {name}/{t}: trūkst scenārija")
            continue
        # ZINĀMS: Liepājas Frizētava real<pess (~17% dārgāka ierīkošana pie bench-ierobežotiem 3 kl/d, €10)
        if not (o >= r >= p): viol.append(f"C {name}/{t}: secība pess={p} real={r} opt={o}")

# ============ D. Konkurentu OFF >= ON ============
print("D. Konkurenti...", flush=True)
for name, la, lo in [('Ģertrūdes',56.9574,24.1265), ('Purvciems',56.956,24.1965)]:
    d_on,_,_ = call({'action':'search','lat':str(la),'lng':str(lo),'radius':'500','competitors':'true'})
    d_off,_,_ = call({'action':'search','lat':str(la),'lng':str(lo),'radius':'500','competitors':'false'})
    if d_on is None or d_off is None: viol.append(f"D {name}: nederīga atbilde"); continue
    for t in TYPES:
        on = (d_on['statistics']['optimal_setups'].get(t) or {}).get('profit_month')
        off = (d_off['statistics']['optimal_setups'].get(t) or {}).get('profit_month')
        if on is None or off is None: continue
        if off < on - 1: viol.append(f"D {name}/{t}: OFF €{off} < ON €{on}")

# ============ E. Slāņu karogi (API joprojām atbalsta, UI rūtiņas noņemtas) ============
print("E. Slāņi...", flush=True)
d,_,_ = call({'action':'search','lat':'56.9496','lng':'24.105','radius':'500','loff':'false','ltour':'false','linst':'false'})
if d is None or d.get('error'): viol.append("E1 slāņi izslēgti: kļūda")
else:
    kafe_tikai_res = (d['statistics']['optimal_setups'].get('Kafejnīca') or {}).get('profit_month')
    notes.append(f"E1 tikai iedzīvotāji Vecrīgā: Kafejnīca €{kafe_tikai_res}")
d,_,_ = call({'action':'search','lat':'56.9496','lng':'24.105','radius':'500','lres':'false'})
if d is None or d.get('error'): viol.append("E2 lres=false: kļūda")

# ============ F. Rādiusa galējības ============
print("F. Rādiusi...", flush=True)
d,dt,_ = call({'action':'search','lat':'56.9496','lng':'24.105','radius':'100','competitors':'true'})
if d is None or d.get('error'): viol.append("F1 100m: kļūda")
else: notes.append(f"F1 100m Vecrīga: ēkas={d['points_count']}, optimal={'ir' if d['statistics'].get('optimal_setups') else 'nav'}")
d,dt,_ = call({'action':'search','lat':'56.9496','lng':'24.105','radius':'5000','competitors':'true'}, timeout=300)
if d is None or d.get('error'): viol.append("F2 5000m: kļūda")
else:
    st = d['statistics']
    caps_ok = len(d.get('offices_found',[])) <= 300 and len(d.get('tourism_found',[])) <= 100 and len(d.get('institutions_found',[])) <= 150
    if not caps_ok: viol.append("F2 5000m: pārsniegti atbilžu capi")
    notes.append(f"F2 5000m Rīga: ēkas={d['points_count']}, iedz={st['total_iedzivotaji']}, biroji={st['office_workers']}, {dt:.1f}s")
    if dt > 60: viol.append(f"F2 5000m: pārāk lēni {dt:.0f}s")

# ============ G. Siltumkarte tukšā vietā + populācija ============
print("G. Siltumkarte...", flush=True)
d,_,_ = call({'action':'heatmap','n':'57.31','s':'57.29','e':'23.02','w':'22.98','btype':'Kafejnīca'})
if d is None: viol.append("G1 jūras heatmap: nederīgs JSON")
elif d.get('error'): notes.append(f"G1 jūras heatmap: '{d['error']}'")
else: notes.append(f"G1 jūras heatmap: {len(d.get('cells',[]))} šūnas (tukšums OK)")
d,_,_ = call({'action':'heatmap','n':'57.31','s':'57.29','e':'23.02','w':'22.98','btype':'PopNakts'})
if d is None or (d.get('error') and 'apgabals' in str(d.get('error'))): viol.append("G2 PopNakts jūrā: negaidīta kļūda")
else: notes.append(f"G2 PopNakts jūrā: {len(d.get('cells',[]))} šūnas")

# ============ H. get_points visi 12 ============
print("H. get_points...", flush=True)
for pid in ['cafe','food','krogs','hairdresser','bakery','pharmacy','beauty','minimarket','dentist','fastfood','fitness','hotel']:
    d,_,_ = call({'action':'get_points','type':pid})
    n = len(d.get('points') or []) if d else -1
    if d is None or d.get('error') or n == 0: viol.append(f"H {pid}: {d.get('error') if d else 'JSON?'} n={n}")
d,_,_ = call({'action':'get_points','type':'hacker'})
if d is None or not d.get('error'): viol.append("H tips=hacker: jādod kļūda")

print(f"\n=== PIEZĪMES ({len(notes)}):")
for n in notes: print("  ·", n)
print(f"\n=== PĀRKĀPUMI: {len(viol)}")
for v in viol: print("  !", v)
