<?php
// server/registrs/lib/apraksts_v3.php — PROTOTIPS (NAV pieslēgts lapai; lapa lieto apraksts_v2.php).
//
// Apraksts kā atbildes uz lasītāja jautājumiem (partneris, piegādātājs, darba meklētājs):
//   Saistības — vai samaksās?   Peļņa — vai pelna?   Izaugsme — vai aug?
//   Valsts un tiesa — vai nav problēmu?   Vadība — pašreizējās valdes reģistrācija (bez vārdiem).
// v2 bija "Īsumā" saraksts (reģ. datums, NACE, PVN, darbinieki, alga, nodokļi, iepirkumi, vieta nozarē)
// + atsevišķi «Stiprās puses» / «Kam pievērst uzmanību». Īsumā dublēja paneļus blakus un neatbildēja uz
// "vai samaksās" un "valsts/tiesa"; saraksti atbildēja, bet bija atrauti no sākuma (lietotājs 2026-11-19).
//
// Tie paši konteksts un sliekšņi kā v2 (reg_apraksts2_konteksts, APR2_*). Katrs fakts ir zaļš (stiprā
// puse), sarkans (uzmanības punkts) vai melns (neitrāls); rindas virsraksts melns. Lapā blakus esošos
// rekvizītus NEATKĀRTO. Aktīva 'risk' procesa, novecojuša pārskata un izslēgta uzņēmuma gadījumā zaļā
// krāsa netiek lietota (faktu paliek, vērtējums — nē).
declare(strict_types=1);

require_once __DIR__ . '/apraksts_v2.php';

const APR3_ZALS = '#1e7d34';
const APR3_SARKANS = '#b3261e';

if (!function_exists('reg_apraksts3_konteksts')) {
    /** v2 konteksts + pašreizējās valdes reģistrācija (tikai skaits un gadi — bez vārdiem). */
    function reg_apraksts3_konteksts(array $pd): array {
        $c = reg_apraksts2_konteksts($pd);
        // Lapa valdes datus NEIELĀDĒ (fizisko personu dati) — te tikai skaits un gadi, bez vārdiem,
        // ar vienu indeksētu vaicājumu (idx_officers_at_legal_entity_registration_number).
        $c['valde'] = null;
        if (function_exists('get_ur_db') && preg_match('/^\d{11}$/', $c['reg'])) {
            try {
                $st = get_ur_db()->prepare("SELECT COUNT(*), MIN(substr(registered_on,1,4)), MAX(substr(registered_on,1,4)) FROM officers
                    WHERE at_legal_entity_registration_number = ? AND governing_body = 'EXECUTIVE_BOARD'");
                $st->execute([$c['reg']]);
                [$n, $no, $lidz] = $st->fetch(PDO::FETCH_NUM) ?: [0, null, null];
                if ((int)$n > 0 && (int)$no > 1900) $c['valde'] = ['n' => (int)$n, 'no' => (int)$no, 'lidz' => (int)$lidz];
            } catch (Throwable $e) { /* bez rindas «Vadība» */ }
        }
        return $c;
    }
}

if (!function_exists('reg_apraksts3_teksts')) {
    function reg_apraksts3_teksts(array $c): string {
        $val = $c['valuta'] ?: 'EUR';
        $eur = fn(float $v): string => apr2_eur($v, $val);
        $h = fn(string $s): string => htmlspecialchars($s, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $gads = $c['gads'];
        $ir_parskats = $gads !== null;
        $izslegts = !empty($c['izslegts']);
        $tiesiskie = (array)($c['tiesiskie'] ?? []);
        $process_aktivs = (bool)array_filter($tiesiskie, fn($x) => $x['lvl'] === 'risk');
        $svaigs = !$ir_parskats || (int)$gads >= (int)date('Y') - 2;
        // Zaļo neliek: aktīvs 'risk' process, izslēgts uzņēmums, novecojis pārskats. Sarkano neliek
        // izslēgtam un novecojušam (vēsturisks fakts, ne šodienas signāls).
        // Novecojis pārskats neitralizē tikai FINANŠU rindas — tiesiskie ieraksti un VID reitings ir šodienas
        // fakti (Elektrooptika: 2022. g. pārskats, bet sankcijas un nodrošinājumi aktīvi tagad).
        $fin_rindas = ['Saistības', 'Peļņa', 'Izaugsme'];
        $bez_zala = fn(string $r): bool => $process_aktivs || $izslegts || (!$svaigs && in_array($r, $fin_rindas, true));
        $bez_sarkana = fn(string $r): bool => $izslegts || (!$svaigs && in_array($r, $fin_rindas, true));
        $rinda = [];   // virsraksts => [[teksts, tonis '+'|'-'|'0'], ...]
        $f = function (string $r, string $t, string $tonis) use (&$rinda): void { $rinda[$r][] = [$t, $tonis]; };

        $ak = $c['aktivi']; $pk = $c['pasu_kapitals']; $ap = $c['apgrozamie']; $is = $c['iss_saistibas'];
        $likv = ($ap !== null && $is !== null && $is > 0) ? $ap / $is : null;
        $ievads = '';

        if ($ir_parskats) {
            $nesal = fn($m): bool => $m !== null && ($m < APR2_PILNS_GADS_MEN || $m > APR2_PILNS_GADS_MAX);
            $dalejs = $nesal($c['periods_men']);
            $idal = $nesal($c['iepr_men']);
            $ir_dat = $c['periods_no'] !== '' && $c['periods_lidz'] !== '';
            $viena_gada = $ir_dat && substr($c['periods_no'], 0, 4) === substr($c['periods_lidz'], 0, 4);
            $dat = $ir_dat ? ($viena_gada ? apr2_datums_iss($c['periods_no']) : date('d.m.Y', strtotime($c['periods_no'])))
                . '–' . date('d.m.Y', strtotime($c['periods_lidz'])) : '';
            $men = $dalejs ? apr2_garums($c['periods_no'], $c['periods_lidz'], $c['periods_men']) : '';
            if ($c['pirmais_periods']) $ievads = 'Finanšu dati no pirmā pārskata' . ($dat !== '' ? " ({$dat}{$men})" : '');
            elseif ($ir_dat && !$viena_gada) $ievads = "Finanšu dati no pārskata par periodu {$dat}{$men}";
            else $ievads = "Finanšu dati no {$gads}. gada pārskata" . (($dalejs || !$c['kalendars']) && $dat !== '' ? " ({$dat}{$men})" : '');
            $gl = function ($g) use ($c): string {
                $v = $c['vesture'][(int)$g] ?? null;
                if ($v === null || ($v['kal'] ?? null) === null) return $c['kalendars'] ? "{$g}. gadu" : "{$g}. gada pārskatu";
                return $v['kal'] ? "{$g}. gadu" : 'periodu ' . date('d.m.Y', strtotime($v['no'])) . '–' . date('d.m.Y', strtotime($v['lidz']));
            };
            // Lokatīvs ("2024. gadā bija peļņa"); $gl — akuzatīvs pēc "pret" ("pret 2024. gadu").
            $gloc = function ($g) use ($c): string {
                $v = $c['vesture'][(int)$g] ?? null;
                if ($v === null || ($v['kal'] ?? null) === null) return $c['kalendars'] ? "{$g}. gadā" : "{$g}. gada pārskatā";
                return $v['kal'] ? "{$g}. gadā" : 'periodā ' . date('d.m.Y', strtotime($v['no'])) . '–' . date('d.m.Y', strtotime($v['lidz']));
            };

            // --- Saistības: vai samaksās? -------------------------------------------------------
            $nd = ($likv !== null && $c['nauda'] !== null && $ap > 0) ? $c['nauda'] / $ap : null;
            if ($likv !== null) {
                if ($likv < 1) {
                    $nt = ($c['nauda'] !== null && $c['nauda'] <= 0) ? ', naudas nebija'
                        : (($c['nauda'] !== null && $c['nauda'] < $ap) ? ', no tiem nauda ' . $eur($c['nauda']) : '');
                    $f('Saistības', 'apgrozāmie līdzekļi sedz ' . apr2_proc($likv) . ' no īstermiņa saistībām ('
                        . $eur($ap) . ' pret ' . $eur($is) . ')' . $nt, '-');
                } elseif ($likv >= APR2_LIKVID_STIPRA && $nd !== null && $nd >= APR2_NAUDA_DALA) {
                    $nl = $c['nauda'] / $is;
                    if ($nl >= 2 && $nl < 10) $t = 'naudas līdzekļi ' . $eur($c['nauda']) . ' — ' . apr2_reizes($nl) . ' vairāk par īstermiņa saistībām';
                    elseif ($nl >= 1) $t = 'naudas līdzekļi ' . $eur($c['nauda']) . ' pārsniedz īstermiņa saistības (' . $eur($is) . ')';
                    else $t = 'apgrozāmie līdzekļi pārsniedz īstermiņa saistības (' . $eur($ap) . ' pret ' . $eur($is) . '), no tiem nauda ' . $eur($c['nauda']);
                    $f('Saistības', $t, '+');
                } elseif ($likv >= APR2_LIKVID_STIPRA && $nd === null) {
                    $f('Saistības', 'apgrozāmie līdzekļi ' . (($likv >= 2 && $likv < 10) ? apr2_reizes($likv) . ' pārsniedz' : 'pārsniedz')
                        . ' īstermiņa saistības (' . $eur($ap) . ' pret ' . $eur($is) . ')', '+');
                } else {
                    // 1,0–1,2 vai lielākā daļa — debitori/krājumi: fakts, ne stiprā puse (nauda vēl jāiekasē/jāpārdod).
                    $sast = '';
                    if ($c['debitori'] !== null && $c['debitori'] >= 0.5 * $ap) $sast = ', lielākā daļa — debitoru parādi (' . $eur($c['debitori']) . ')';
                    elseif ($c['krajumi'] !== null && $c['krajumi'] >= 0.5 * $ap) $sast = ', lielākā daļa — krājumi (' . $eur($c['krajumi']) . ')';
                    $f('Saistības', 'apgrozāmie līdzekļi sedz ' . apr2_proc($likv) . ' no īstermiņa saistībām' . $sast, '0');
                }
            }
            if ($pk !== null && $ak !== null && $ak >= 0) {
                $ipas = !empty($c['komersants']) ? ' (īpašnieku daļa)' : '';
                if ($pk < 0) {
                    $f('Saistības', ($ak > 0 && -$pk / $ak < APR2_NEDAUDZ_NEG_KAP)
                        ? 'pašu kapitāls nedaudz negatīvs: ' . $eur($pk) . ' jeb ' . apr2_proc($pk / $ak) . ' no aktīviem'
                        : 'saistības par ' . $eur(-$pk) . ' pārsniedz aktīvus (negatīvs pašu kapitāls)', '-');
                } elseif ($ak > 0 && $pk <= $ak) {
                    $f('Saistības', "pašu kapitāls{$ipas} veido " . apr2_proc($pk / $ak) . ' no aktīviem', $pk / $ak >= APR2_KAPITALS_STIPRS ? '+' : '0');
                }
            }

            // --- Peļņa: vai pelna? -------------------------------------------------------------------
            $r = $c['rezultats']; $rp = $c['rezultats_iepr'];
            $jauns_bez_apgr = $c['apgrozijums'] !== null && $c['apgrozijums'] == 0.0;
            if ($r !== null) {
                if ($r > 0) {
                    $rent = ($c['apgrozijums'] ?? 0) > 0 ? $r / $c['apgrozijums'] : null;
                    $f('Peļņa', 'peļņa ' . $eur($r) . ($rent !== null && $rent <= 1 ? ' jeb ' . apr2_proc($rent) . ' no apgrozījuma' : ''),
                        ($rent !== null && $rent >= APR2_RENTAB_STIPRA && $rent <= 1) ? '+' : '0');
                } elseif ($r < 0) {
                    $f('Peļņa', 'zaudējumi ' . $eur(-$r) . ($jauns_bez_apgr ? ' (apgrozījuma nebija)' : ''), '0');
                } else {
                    $f('Peļņa', 'peļņas un zaudējumu nebija', '0');
                }
            }
            if (!$dalejs && !$idal && $r !== null && $rp !== null && $c['gads_iepr'] !== null) {
                $gi = $c['gads_iepr'];
                $rez_v0 = array_filter(array_map(fn($v) => $v['rez'], $c['vesture']), fn($v) => $v !== null);
                $rek = (count($rez_v0) >= APR2_VESTURE_MIN_GADI && $rp > 0 && $rp >= max($rez_v0)) ? ', kas bija lielākā ' . apr2_sk(count($rez_v0)) . (apr2_vsk(count($rez_v0)) ? ' gadā' : ' gados') : '';
                if ($rp > 0 && $r > 0 && $rp >= APR2_PELNA_MIN) {
                    $x = $r / $rp - 1;
                    if ($x >= APR2_PELNA_IZMAINA) $f('Peļņa', ($x >= 1 ? 'pieaugusi ' . apr2_reizes($x + 1) : 'pieaugusi par ' . apr2_proc($x)) . ' pret ' . $gl($gi) . ' (' . $eur($rp) . ')', '+');
                    elseif ($x <= -APR2_PELNA_IZMAINA) $f('Peļņa', 'samazinājusies par ' . apr2_proc($x) . ' pret ' . $gl($gi) . ' (' . $eur($rp) . $rek . ')', '-');
                } elseif ($rp > 0 && $r < 0 && max($rp, -$r) >= APR2_PELNA_MIN) {
                    $f('Peļņa', 'pēc peļņas ' . $gloc($gi) . ' (' . $eur($rp) . ')', '-');
                } elseif ($rp < 0 && $r > 0 && max(-$rp, $r) >= APR2_PELNA_MIN) {
                    $f('Peļņa', 'pēc zaudējumiem ' . $gloc($gi) . ' (' . $eur(-$rp) . ')', '+');
                } elseif ($rp < 0 && $r < 0 && -$rp >= APR2_PELNA_MIN) {
                    $x = $r / $rp - 1;
                    if ($x <= -APR2_PELNA_IZMAINA) $f('Peļņa', 'zaudējumi samazinājušies par ' . apr2_proc($x) . ' pret ' . $gl($gi) . ' (' . $eur(-$rp) . ')', '+');
                    elseif ($x >= APR2_PELNA_IZMAINA) $f('Peļņa', 'zaudējumi pieauguši ' . ($x >= 1 ? apr2_reizes($x + 1) : 'par ' . apr2_proc($x)) . ' pret ' . $gl($gi) . ' (' . $eur(-$rp) . ')', '-');
                }
            }
            if ($c['procenti'] !== null && $c['procenti'] > 0 && $c['pirms_nodokliem'] !== null && $c['pirms_nodokliem'] < 0
                && $c['pirms_nodokliem'] + $c['procenti'] > 0) {
                $f('Peļņa', 'zaudējumus rada procentu izdevumi: ' . $eur($c['procenti']) . ' pret peļņu pirms tiem ' . $eur($c['pirms_nodokliem'] + $c['procenti']), '-');
            }
            $rez_v = array_filter(array_map(fn($v) => $v['rez'], $c['vesture']), fn($v) => $v !== null);
            $nr = count($rez_v);
            if ($nr >= APR2_VESTURE_MIN_GADI) {
                $k = count(array_filter($rez_v, fn($v) => $v > 0));
                $y1 = min(array_keys($rez_v));
                if ($k === $nr) $f('Peļņa', 'peļņa bijusi visos ' . apr2_sk($nr) . (apr2_vsk($nr) ? ' gadā' : ' gados') . " kopš {$y1}. gada", '+');
                elseif (count(array_filter($rez_v, fn($v) => $v < 0)) === $nr) $f('Peļņa', 'zaudējumi visos ' . apr2_sk($nr) . (apr2_vsk($nr) ? ' gadā' : ' gados') . " kopš {$y1}. gada", '-');
                else {
                    $ped = $k > 0 ? max(array_keys(array_filter($rez_v, fn($v) => $v > 0))) : null;
                    $f('Peļņa', 'peļņa bijusi ' . apr2_sk($k) . ' no ' . apr2_sk($nr) . " gadiem kopš {$y1}. gada"
                        . ($ped !== null && $ped < max(array_keys($rez_v)) ? ", pēdējo reizi {$ped}. gadā" : ''), '0');
                }
            }

            // --- Izaugsme: vai aug? ------------------------------------------------------------------
            $ai = $c['apgrozijums_iepr'];
            $izm = null; $vid = false;
            if ($c['apgrozijums'] !== null && $ai !== null && $ai > 0) {
                if ($dalejs || $idal) {
                    $pm = (float)($c['periods_men_prec'] ?? 0); $im = (float)($c['iepr_men_prec'] ?? 0);
                    if ($pm >= 3 && $im >= 3) { $izm = ($c['apgrozijums'] / $pm) / ($ai / $im) - 1; $vid = true; }
                } else {
                    $izm = $c['apgrozijums'] / $ai - 1;
                }
            }
            $apg_v = array_filter($c['vesture'], fn($v) => $v['apgr'] !== null && $v['apgr'] > 0
                && ($v['men'] === null || ($v['men'] >= APR2_PILNS_GADS_MEN && $v['men'] <= APR2_PILNS_GADS_MAX)));
            $rekords = count($apg_v) >= APR2_VESTURE_MIN_GADI && isset($apg_v[$gads]) && $apg_v[$gads]['apgr'] >= max(array_column($apg_v, 'apgr'));
            if ($c['apgrozijums'] !== null && $c['apgrozijums'] > 0) {
                $t = 'apgrozījums ' . $eur($c['apgrozijums']);
                $tonis = '0';
                if ($izm !== null) {
                    $t .= ', ' . ($vid ? 'mēneša vidējais ' : '') . ($izm >= 1
                        ? apr2_reizes($izm + 1) . ' vairāk nekā ' . $gloc($c['gads_iepr'])
                        : ($izm >= 0 ? '+' : '−') . apr2_proc($izm) . ' pret ' . $gl($c['gads_iepr']));
                    $tonis = $izm >= APR2_PIEAUGUMS_STIPRS ? '+' : ($izm <= APR2_KRITUMS_UZMANIBA ? '-' : '0');
                } elseif ($c['pirmais_periods']) {
                    $t .= ' (pirmais periods — salīdzinājuma vēl nav)';
                }
                if ($rekords) { $t .= ', lielākais ' . apr2_sk(count($apg_v)) . (apr2_vsk(count($apg_v)) ? ' gadā' : ' gados'); if ($tonis === '0') $tonis = '+'; }
                $f('Izaugsme', $t, $tonis);
            } elseif ($jauns_bez_apgr) {
                $agrak = (bool)array_filter($c['vesture'], fn($v, $g) => $g < $gads && ($v['apgr'] ?? 0) > 0, ARRAY_FILTER_USE_BOTH);
                if ($agrak && $ai !== null && $ai > 0) $f('Izaugsme', 'apgrozījuma nebija (' . $gloc($c['gads_iepr']) . ' — ' . $eur($ai) . ')', '-');
                else $f('Izaugsme', $c['pirmais_periods'] ? 'apgrozījuma vēl nebija (pirmais periods)' : 'apgrozījuma nebija', '0');
            }
            if (count($apg_v) >= APR2_VESTURE_MIN_GADI && isset($apg_v[$gads])) {
                $yf = array_key_first($apg_v);
                if ($yf !== $gads) {
                    $m = $apg_v[$gads]['apgr'] / $apg_v[$yf]['apgr'];
                    $f('Izaugsme', "kopš {$yf}. gada " . ($m >= 1.1 ? 'pieaudzis ' . apr2_reizes($m) : ($m <= 0.9 ? 'samazinājies par ' . apr2_proc($m - 1) : 'apmēram tāds pats')), '0');
                }
            }
        }

        // --- Valsts un tiesa -------------------------------------------------------------------------
        foreach ($tiesiskie as $x) {
            if ($x['t'] === 'sankcijas') {
                $d = [];
                foreach ((array)$c['sankcijas'] as $loma => $saraksti) {
                    $d[] = ((string)$loma !== '' ? apr2_lcfirst((string)$loma) : 'pats uzņēmums') . ' (' . implode(' un ', (array)$saraksti) . ')';
                }
                $tx = 'sankciju sarakstos: ' . implode(', ', $d);
            } else {
                $tx = $x['t'];
            }
            $det = array_filter([(string)($x['det'] ?? ''), $x['no'] !== '' ? 'kopš ' . date('d.m.Y', strtotime($x['no'])) : '']);
            $f('Valsts un tiesa', apr2_lcfirst($tx) . ($det ? ' (' . implode(', ', $det) . ')' : ''), '-');
        }
        if (in_array($c['vid_reitings'], ['B', 'C'], true)) {
            $f('Valsts un tiesa', "VID reitings {$c['vid_reitings']}" . ($c['vid_skaidrojums'] !== '' ? ' (' . mb_strtolower($c['vid_skaidrojums'], 'UTF-8') . ')' : ''), '-');
        } elseif ($c['vid_reitings'] === 'A') {
            $f('Valsts un tiesa', 'VID reitings A' . ($c['vid_skaidrojums'] !== '' ? ' (' . mb_strtolower($c['vid_skaidrojums'], 'UTF-8') . ')' : ''), '+');
        }
        if (!$c['tiesiskais']['has']) $f('Valsts un tiesa', 'nav reģistrētu maksātnespējas, tiesiskās aizsardzības vai citu procesu', '+');

        // --- Vadība (bez vārdiem; vēsturisku izmaiņu datos nav — tikai pašreizējā sastāva gadi) ------
        if (!empty($c['valde']) && !$izslegts) {
            $v = $c['valde'];
            $f('Vadība', 'pašreizējā valde (' . apr2_sk($v['n']) . (apr2_vsk($v['n']) ? ' loceklis' : ' locekļi') . ') reģistrēta '
                . ($v['no'] === $v['lidz'] ? "{$v['no']}. gadā" : "{$v['no']}.–{$v['lidz']}. gadā"), '0');
        }

        // --- Izkārtojums ----------------------------------------------------------------------------
        $rindas = [];
        if ($izslegts) {
            $bt = ($c['izslegts_dat'] ?? '') !== '' ? strtotime($c['izslegts_dat']) : false;
            $rindas[] = $h((string)$c['nosaukums']) . ' bija Uzņēmumu reģistrā' . ($bt !== false
                ? ' līdz ' . date('Y', $bt) . '. gada ' . (int)date('j', $bt) . '. ' . APR2_MENESI_DAT[(int)date('n', $bt)] : '') . '.';
        }
        if ($ievads !== '') $rindas[] = $ievads . '.';
        foreach (['Saistības', 'Peļņa', 'Izaugsme', 'Valsts un tiesa', 'Vadība'] as $r) {
            if (empty($rinda[$r])) continue;
            $dalas = [];
            foreach ($rinda[$r] as [$t, $tonis]) {
                if ($tonis === '+' && $bez_zala($r)) $tonis = '0';
                if ($tonis === '-' && $bez_sarkana($r)) $tonis = '0';
                $tt = $h($t);
                $dalas[] = $tonis === '0' ? $tt : "<span style='color: " . ($tonis === '+' ? APR3_ZALS : APR3_SARKANS) . ";'>{$tt}</span>";
            }
            $rindas[] = "<b>{$r}:</b> " . implode('; ', $dalas) . '.';
        }
        if (!$rindas) return '';
        if ($ir_parskats && !$izslegts && !$svaigs) {
            $rindas[] = "<span style='color: gray;'>Jaunākais pieejamais gada pārskats ir par {$gads}. gadu, tāpēc tā rādītāji nav iekrāsoti kā stiprās puses vai uzmanības punkti.</span>";
        }
        $av = ['Uzņēmumu reģistrs' . ($ir_parskats ? " ({$gads}. gada pārskats)" : '')];
        if ($c['vid_reitings'] !== '' && in_array($c['vid_reitings'], ['A', 'B', 'C'], true)) $av[] = 'VID';
        $piezime = 'Apraksts veidots automātiski pēc fiksētiem noteikumiem no atvērtajiem datiem: ' . implode(', ', $av)
            . '. Zaļš — stiprā puse, sarkans — uzmanības punkts. Tas nav kredītreitings un neņem vērā nozares specifiku.';
        return implode('<br>', $rindas) . "<br><br><span style='font-size:0.85em; color:gray;'>* {$piezime}</span>";
    }
}
