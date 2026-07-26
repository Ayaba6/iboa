#!/usr/bin/env bash
# [ACHATS #5] Course RÉELLE multi-processus sur la création de facture fournisseur.
# Deux processus OS indépendants frappent simultanément (barrière de synchro) sur
# le MÊME fournisseur + numéro normalisé. Aucun client mysql requis : tout passe
# par `php artisan` (PDO Laravel). Cible la base de TEST (iboa_erp_test), jamais
# iboa_erp. Produit un rapport JSON avec le SHA Git.
set -uo pipefail
cd "$(dirname "$0")/.."

export DB_DATABASE="${RACE_DB:-iboa_erp_test}"
php artisan config:clear >/dev/null 2>&1 || true

SHA="$(git rev-parse HEAD)"
REPORT="docs/RAPPORT-COURSE-FACTURE-FOURNISSEUR.json"
OFFSET_MS=1500

prep() {
  php artisan tinker --execute='
    $fy = App\Models\FiscalYear::firstOrCreate(["label"=>"2026"],["starts_at"=>"2026-01-01","ends_at"=>"2026-12-31","status"=>"ouvert","is_current"=>true]);
    $co = App\Models\Company::firstOrCreate(["name"=>"RACE"],["email"=>"race@iboa.test","current_fiscal_year_id"=>$fy->id]);
    $sup = App\Models\Supplier::first() ?? App\Models\Supplier::factory()->create();
    App\Models\SupplierInvoice::withTrashed()->forceDelete();
    App\Models\IdempotencyKey::query()->delete();
    echo $sup->id.PHP_EOL;
  ' 2>/dev/null | tr -d "\r" | grep -E "^[0-9]+$" | tail -1
}
count() { php artisan tinker --execute='echo App\Models\SupplierInvoice::where("supplier_invoice_number","'"$1"'")->count();' 2>/dev/null | tr -d '\r' | grep -E '^[0-9]+$' | tail -1; }

echo "Course facture fournisseur — SHA $SHA — base $DB_DATABASE"

# ── Scénario 1 : même clé d'idempotence ─────────────────────────────────────
SUP="$(prep)"
B=$(( $(php -r 'echo (int)(microtime(true)*1000);') + OFFSET_MS ))
W1A=$(mktemp); W2A=$(mktemp)
php artisan a3:purchase-idem-race-worker 1 "$SUP" "RC-1" "RKEY" "$B" >"$W1A" 2>/dev/null &
php artisan a3:purchase-idem-race-worker 2 "$SUP" "RC-1" "RKEY" "$B" >"$W2A" 2>/dev/null &
wait
O1A="$(grep -oE '\{.*\}' "$W1A" | tail -1)"; O2A="$(grep -oE '\{.*\}' "$W2A" | tail -1)"
P_A="$(count RC-1)"; rm -f "$W1A" "$W2A"

# ── Scénario 2 : clés différentes ───────────────────────────────────────────
SUP="$(prep)"
B=$(( $(php -r 'echo (int)(microtime(true)*1000);') + OFFSET_MS ))
W1B=$(mktemp); W2B=$(mktemp)
php artisan a3:purchase-idem-race-worker 1 "$SUP" "RC-2" "RKEY-A" "$B" >"$W1B" 2>/dev/null &
php artisan a3:purchase-idem-race-worker 2 "$SUP" "RC-2" "RKEY-B" "$B" >"$W2B" 2>/dev/null &
wait
O1B="$(grep -oE '\{.*\}' "$W1B" | tail -1)"; O2B="$(grep -oE '\{.*\}' "$W2B" | tail -1)"
P_B="$(count RC-2)"; rm -f "$W1B" "$W2B"

# ── Assemblage JSON valide via PHP ──────────────────────────────────────────
php -r '
  $mk = function($num,$o1,$o2,$p){
    return ["number"=>$num,"worker1"=>json_decode($o1?:"{}"),"worker2"=>json_decode($o2?:"{}"),
            "invoices_persisted"=>(int)$p,"verdict"=>((int)$p===1?"UNE SEULE FACTURE":"ANOMALIE")];
  };
  $rep = [
    "test"=>"course concurrente creation facture fournisseur",
    "git_sha"=>$argv[1],"database"=>getenv("DB_DATABASE"),
    "generated_at"=>gmdate("Y-m-d\TH:i:s\Z"),
    "scenario_1_meme_cle"=>$mk("RC-1",$argv[2],$argv[3],$argv[4]),
    "scenario_2_cles_differentes"=>$mk("RC-2",$argv[5],$argv[6],$argv[7]),
  ];
  file_put_contents($argv[8], json_encode($rep, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
' "$SHA" "$O1A" "$O2A" "$P_A" "$O1B" "$O2B" "$P_B" "$REPORT"

echo "── rapport écrit : $REPORT ──"; cat "$REPORT"
if [ "$P_A" = "1" ] && [ "$P_B" = "1" ]; then echo "✓ COURSE SAINE (une seule facture par scénario)"; else echo "✗ ANOMALIE"; exit 1; fi
