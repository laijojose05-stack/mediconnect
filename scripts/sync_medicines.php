<?php
/**
 * scripts/sync_medicines.php
 * Imports real medicines from openFDA into the `medicines` table.
 *
 * Run from CLI:      php scripts/sync_medicines.php
 * Run from browser:  http://localhost/mediconnect/scripts/sync_medicines.php
 *
 * Set $MAX_TOTAL to control how many you want to import.
 */

/* ============================================================
   CONFIG
============================================================ */
/* openFDA API key (optional — openFDA works anonymously too).
   Loaded from scripts/.openfda.key (git-ignored) or the
   OPENFDA_API_KEY environment variable, so the key is never
   committed to GitHub. */
$API_KEY = '';
if (getenv('OPENFDA_API_KEY')) {
    $API_KEY = getenv('OPENFDA_API_KEY');
} else {
    $kf = @file_get_contents(__DIR__ . '/.openfda.key');
    if ($kf !== false) $API_KEY = trim($kf);
}
$MAX_TOTAL = 2000000;                        // how many records to import total
$BATCH     = 1000000;                        // openFDA max per request
$CLEAN     = false;                       // set true to DELETE existing medicines first

/* ============================================================
   DB CONNECTION
============================================================ */
require_once __DIR__ . '/../user/_init.php';  // reuse PDO from user module

set_time_limit(1200);            // 20 min for CLI/browser
ini_set('memory_limit', '256M');

$isCli = (php_sapi_name() === 'cli');
$nl    = $isCli ? "\n" : "<br>";
$pre   = $isCli ? "" : "<pre style='background:#0f1319;color:#e6e9ef;padding:20px;font-family:monospace;'>";
$post  = $isCli ? "" : "</pre>";

echo $pre;
echo "MediConnect — openFDA Medicine Sync{$nl}";
echo str_repeat('=', 50) . $nl;

/* ============================================================
   OPTIONAL: wipe existing medicines
============================================================ */
if ($CLEAN) {
    // Turn off FK checks so we don't break linked tables
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
    $pdo->exec("TRUNCATE TABLE medicines");
    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
    echo "✓ Cleared existing medicines{$nl}{$nl}";
}

/* ============================================================
   HELPERS
============================================================ */
function fetch_from_openfda(string $url): ?array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'MediConnect-Sync/1.0',
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || !$resp) {
        echo "  ⚠ HTTP $code on request{$GLOBALS['nl']}";
        return null;
    }

    $json = json_decode($resp, true);
    if (!isset($json['results'])) return null;

    return $json['results'];
}

function clean_text(?string $s, int $max = 500): string {
    if (!$s) return '';
    // collapse whitespace, strip weird control chars, truncate
    $s = preg_replace('/\s+/', ' ', $s);
    $s = trim($s);
    return mb_substr($s, 0, $max);
}

/* ============================================================
   MAIN LOOP — paginated fetch
============================================================ */
$inserted = 0;
$skipped  = 0;
$errors   = 0;
$seen     = [];           // in-run dedupe by lowercase name

$pdo->beginTransaction();

try {
    for ($skip = 0; $skip < $MAX_TOTAL; $skip += $BATCH) {
        $limit = min($BATCH, $MAX_TOTAL - $skip);

        $url = "https://api.fda.gov/drug/label.json"
             . "?api_key=" . urlencode($API_KEY)
             . "&limit={$limit}"
             . "&skip={$skip}";

        echo "→ Fetching records {$skip}–" . ($skip + $limit - 1) . "… ";
        $results = fetch_from_openfda($url);

        if ($results === null) {
            echo "failed, stopping.{$nl}";
            break;
        }

        echo count($results) . " received{$nl}";

        if (empty($results)) break;

        foreach ($results as $drug) {
            $brand   = $drug['openfda']['brand_name'][0]        ?? null;
            $generic = $drug['openfda']['generic_name'][0]      ?? null;
            $maker   = $drug['openfda']['manufacturer_name'][0] ?? null;
            $pType   = $drug['openfda']['product_type'][0]      ?? '';

            /* ----- Prefer brand name; fall back to generic ----- */
            $name = $brand ?: $generic;
            if (!$name) { $skipped++; continue; }

            $name    = clean_text($name, 150);
            $generic = clean_text($generic, 150);
            $key     = strtolower($name);

            /* ----- Skip duplicates within this run ----- */
            if (isset($seen[$key])) { $skipped++; continue; }
            $seen[$key] = true;

            /* ----- Category from product_type ----- */
            $category = 'General';
            if (stripos($pType, 'PRESCRIPTION') !== false)  $category = 'Prescription';
            elseif (stripos($pType, 'OTC') !== false)        $category = 'OTC';
            elseif (stripos($pType, 'VACCINE') !== false)    $category = 'Vaccine';
            elseif (stripos($pType, 'PLASMA') !== false)     $category = 'Plasma';

            /* ----- Short description ----- */
            $desc = '';
            if (!empty($drug['purpose'][0]))               $desc = $drug['purpose'][0];
            elseif (!empty($drug['indications_and_usage'][0])) $desc = $drug['indications_and_usage'][0];
            elseif (!empty($drug['description'][0]))       $desc = $drug['description'][0];
            $desc = clean_text($desc, 500);

            /* ----- Skip duplicates already in DB ----- */
            $chk = $pdo->prepare("SELECT id FROM medicines WHERE medicine_name = ? LIMIT 1");
            $chk->execute([$name]);
            if ($chk->fetch()) { $skipped++; continue; }

            /* ----- Insert ----- */
            try {
                $ins = $pdo->prepare(
                    "INSERT INTO medicines
                        (medicine_name, generic_name, category, description, created_at)
                     VALUES (?, ?, ?, ?, NOW())"
                );
                $ins->execute([$name, $generic, $category, $desc]);
                $inserted++;
            } catch (PDOException $e) {
                $errors++;
            }
        }

        // Be polite to openFDA
        if (!$isCli) { @ob_flush(); @flush(); }
        sleep(1);
    }

    $pdo->commit();

} catch (Throwable $e) {
    $pdo->rollBack();
    echo "{$nl}✗ Fatal error: " . $e->getMessage() . $nl;
    echo $post;
    exit;
}

/* ============================================================
   SUMMARY
============================================================ */
$total = (int)$pdo->query("SELECT COUNT(*) FROM medicines")->fetchColumn();

echo "{$nl}" . str_repeat('=', 50) . $nl;
echo "✓ Inserted : {$inserted}{$nl}";
echo "· Skipped  : {$skipped}{$nl}";
echo "✗ Errors   : {$errors}{$nl}";
echo "📦 Total in DB now: {$total}{$nl}";
echo str_repeat('=', 50) . $nl;

echo $post;