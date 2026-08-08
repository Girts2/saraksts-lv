// horoskops/js/logic/aspect_utils.js
// ─────────────────────────────────────────────────────────────────────────────
// NEPĀRTRAUKTS ASPEKTU KRITIENA KODOLS — dalīta utilīta.
//
// Vairākās vietās (synastry.js, composite.js, relationship_forecast.js,
// weekly_consensus.js) aspekta "tuvuma" svars tika rēķināts ar formulu
// `1 - 0.5 * (orbDiff / orb)`. Tai bija reāla vērtības "klints": TIEŠI pie
// orba malas svars bija 0.5 (puse spēka), bet vienu simtdaļgrādu tālāk aspekts
// vairs "neeksistēja" (svars 0) — pēkšņs 50% lēciens, ne gluda pāreja.
//
// aspectFalloff() aizstāj to ar raised-cosine kodolu: 1 pie eksakta aspekta,
// GLUDI (bez lēciena vērtībā VAI slīpumā) krīt uz tieši 0 pie orba malas.
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Gluds kritiena svars [0,1] atkarībā no attāluma līdz eksaktam aspektam.
 * @param {number} orbDiff - |faktiskā_leņķiskā_starpība - aspekta_leņķis|, grādos (>= 0)
 * @param {number} orb - maksimālais pielaides orbis šim aspektam, grādos
 * @returns {number} 1 (eksakts aspekts) → 0 (orba mala vai ārpus tās), gludi
 */
export function aspectFalloff(orbDiff, orb) {
    if (!(orb > 0) || orbDiff >= orb) return 0;
    if (orbDiff <= 0) return 1;
    return 0.5 * (1 + Math.cos(Math.PI * orbDiff / orb));
}
