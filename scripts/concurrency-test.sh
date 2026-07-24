#!/usr/bin/env bash
# [Phase 2.7/2.8] Preuve de concurrence RÉELLE (processus OS parallèles, verrous
# InnoDB) : N workers se disputent un stock insuffisant, aucune survente possible.
# Résultat attendu : Σ réservé ≤ stock, jamais de dépassement.
# Exécuté le 24/07/2026 : 5 workers × 3 sur stock 10 → 3 OK + 2 refus, réservé 9.
set -euo pipefail
MYSQL="${MYSQL_BIN:-mysql}"
DB=iboa_concur_test
STOCK="${1:-10}"; WORKERS="${2:-5}"; WANT="${3:-3}"

"$MYSQL" -u root -e "DROP DATABASE IF EXISTS $DB; CREATE DATABASE $DB;"
"$MYSQL" -u root $DB -e "CREATE TABLE product_stocks (id INT PRIMARY KEY, quantity DECIMAL(18,4), reserved_quantity DECIMAL(18,4)) ENGINE=InnoDB; INSERT INTO product_stocks VALUES (1,$STOCK,0);"

W=$(mktemp /tmp/concur_XXXX.php)
cat > "$W" <<'PHP'
<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=iboa_concur_test','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$want=(int)$argv[1]; usleep(random_int(0,50000));
$pdo->beginTransaction();
$r=$pdo->query("SELECT quantity,reserved_quantity FROM product_stocks WHERE id=1 FOR UPDATE")->fetch();
$avail=(float)$r['quantity']-(float)$r['reserved_quantity'];
if($avail>=$want){usleep(20000);$pdo->exec("UPDATE product_stocks SET reserved_quantity=reserved_quantity+$want WHERE id=1");$pdo->commit();echo "OK\n";}
else{$pdo->commit();echo "REFUS\n";}
PHP
for i in $(seq 1 "$WORKERS"); do php "$W" "$WANT" & done
wait
RES=$("$MYSQL" -u root -N $DB -e "SELECT reserved_quantity FROM product_stocks WHERE id=1;")
"$MYSQL" -u root -e "DROP DATABASE $DB;"; rm -f "$W"
echo "Stock $STOCK, $WORKERS workers × $WANT : réservé final = $RES"
awk "BEGIN{exit !($RES<=$STOCK)}" && echo "✓ AUCUNE SURVENTE (réservé ≤ stock)" || { echo "✗ SURVENTE"; exit 1; }
