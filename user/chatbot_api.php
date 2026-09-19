<?php
/* ============================================================
   user/chatbot_api.php — MediConnect Assistant (backend)
   ------------------------------------------------------------
   Reads a natural-language message from the logged-in user,
   matches it against intents (or continues a step-based
   conversational flow stored in the session), talks to the live
   database and returns JSON:
       { reply: string, quick: [ { label, url?, msg?, icon? } ] }

   Full features are reachable through chat:
     • browse hospitals / doctors / pharmacies / medicines
     • view + book appointments (multi-step flow)
     • request medicines (multi-step flow)
     • cancel appointments / medicine requests
     • read notifications, profile & dashboard summary

   GENERATIVE AI LAYER (config/ai_client.php):
     When no rule matches, the message is sent to a generative
     model (Gemini by default) which either:
       • routes it into one of the ACTION flows above, or
       • answers the question directly (health Q&A / chat).
     Falls back gracefully to the canned replies if no API key
     is set or the API is unreachable.

   Consumed by:
     • user/chatbot.php      (full chat page)
     • floating chat widget  (injected via _footer.php)
============================================================ */
require '_init.php';
require_once __DIR__ . '/../config/ai_client.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

/* ============================================================
   INPUT
============================================================ */
$raw  = file_get_contents('php://input');
$body = json_decode($raw ?: '', true) ?: [];
$msg  = strtolower(trim($body['message'] ?? ($_POST['message'] ?? '')));
$uid  = (int)($_SESSION['user_id'] ?? 0);
$myFile       = $_FILES['prescription'] ?? null;
$isFileUpload = $myFile !== null && (($myFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK);

/* ============================================================
   HELPERS
=========================================================== */
function respond(string $reply, array $quick = []): void {
    echo json_encode(['reply' => $reply, 'quick' => $quick], JSON_UNESCAPED_UNICODE);
    exit;
}

function q_item(string $label, string $url, string $icon = 'link-45deg'): array {
    return ['label' => $label, 'url' => $url, 'icon' => $icon];
}

/* Quick button that SENDS a chat message (starts a flow, etc.) */
function q_action(string $label, string $msg, string $icon = 'arrow-return-right'): array {
    return ['label' => $label, 'msg' => $msg, 'icon' => $icon];
}

/* Run a closure that talks to $pdo; return null on any DB error
   so the chatbot never white-screens the page. */
function tries(?callable $fn) {
    try { return $fn(); }
    catch (Throwable $e) { return null; }
}

/* ---------------- Flow state ---------------- */
function set_flow(?array $f): void {
    if ($f === null) unset($_SESSION['chat_flow']);
    else $_SESSION['chat_flow'] = $f;
}

/* ---------------- Option pickers ---------------- */
/* Build numbered list text from rows: [{label, sub}] */
function numbered_list(array $rows): string {
    $out = [];
    foreach ($rows as $i => $r) {
        $line = ($i + 1) . '. ' . $r['label'];
        if (!empty($r['sub'])) $line .= ' — ' . $r['sub'];
        $out[] = $line;
    }
    return implode("\n", $out);
}

/* Resolve a reply to one of $rows by number or by substring.
   $rows entries: ['id' => ..., 'label' => ...]. Returns id or null. */
function resolve_choice(string $msg, array $rows, string $key = 'id'): ?int {
    $m = strtolower(trim($msg));
    if ($m === '') return null;
    if (preg_match('/^\d{1,2}$/', $m)) {
        $n = (int)$m;
        return isset($rows[$n - 1]) ? (int)$rows[$n - 1][$key] : null;
    }
    foreach ($rows as $r) {
        $label = strtolower((string)($r['label'] ?? ''));
        if ($label !== '' && strpos($label, $m) !== false) return (int)$r[$key];
    }
    return null;
}

/* Like resolve_choice but returns ALL matching row ids (for ambiguity).
   Matches by number or by substring; returns [] when nothing matches. */
function resolve_choices(string $msg, array $rows, string $key = 'id'): array {
    $m = strtolower(trim($msg));
    if ($m === '') return [];
    if (preg_match('/^\d{1,2}$/', $m)) {
        $n = (int)$m;
        return isset($rows[$n - 1]) ? [(int)$rows[$n - 1][$key]] : [];
    }
    $out = [];
    foreach ($rows as $r) {
        $label = strtolower((string)($r['label'] ?? ''));
        if ($label !== '' && strpos($label, $m) !== false) $out[] = (int)$r[$key];
    }
    return array_values(array_unique($out));
}

/* ---------------- DB row builders ---------------- */
function approved_hospital_rows(PDO $pdo): ?array {
    try {
        $rows = $pdo->query("SELECT id, hospital_name, city FROM hospitals WHERE status='approved' ORDER BY hospital_name")->fetchAll();
    } catch (Throwable $e) { return null; }
    return array_map(function ($r) {
        return ['id' => (int)$r['id'], 'label' => $r['hospital_name'], 'sub' => $r['city'] ?? ''];
    }, $rows);
}

function doctor_rows(PDO $pdo, int $hospitalId): ?array {
    try {
        $st = $pdo->prepare("SELECT id, doctor_name, specialization FROM doctors WHERE hospital_id = ? ORDER BY doctor_name");
        $st->execute([$hospitalId]);
        $rows = $st->fetchAll();
    } catch (Throwable $e) { return null; }
    return array_map(function ($r) {
        return ['id' => (int)$r['id'], 'label' => $r['doctor_name'], 'sub' => $r['specialization'] ?? ''];
    }, $rows);
}

function approved_pharmacy_rows(PDO $pdo): ?array {
    try {
        $rows = $pdo->query("SELECT id, pharmacy_name, city FROM pharmacies WHERE status='approved' ORDER BY pharmacy_name")->fetchAll();
    } catch (Throwable $e) { return null; }
    return array_map(function ($r) {
        return ['id' => (int)$r['id'], 'label' => $r['pharmacy_name'], 'sub' => $r['city'] ?? ''];
    }, $rows);
}

function medicine_rows(PDO $pdo, ?string $kw, int $limit = 8): ?array {
    $sql    = "SELECT id, medicine_name, generic_name, category FROM medicines";
    $params = [];
    if ($kw !== null && $kw !== '') {
        $sql .= " WHERE medicine_name LIKE ? OR generic_name LIKE ? OR category LIKE ?";
        $like  = "%$kw%";
        $params = [$like, $like, $like];
    }
    $sql .= " ORDER BY medicine_name LIMIT $limit";
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll();
    } catch (Throwable $e) { return null; }
    return array_map(function ($r) {
        $sub = trim(($r['generic_name'] ?? '') . ($r['category'] ? ' — ' . $r['category'] : ''));
        return ['id' => (int)$r['id'], 'label' => $r['medicine_name'], 'sub' => $sub];
    }, $rows);
}

/* Pharmacy stock/price rows for a medicine keyword:
   [{medicine_name, generic_name, pharmacy_name, city, price, stock}] */
function pharmacy_stock_rows(PDO $pdo, string $kw): ?array {
    $like = "%$kw%";
    try {
        $st = $pdo->prepare(
            "SELECT m.medicine_name, m.generic_name,
                    p.pharmacy_name, p.city,
                    pm.price, pm.stock
             FROM pharmacy_medicines pm
             JOIN medicines  m ON m.id = pm.medicine_id
             JOIN pharmacies p ON p.id = pm.pharmacy_id
             WHERE m.medicine_name LIKE ? OR m.generic_name LIKE ? OR m.category LIKE ?
             ORDER BY p.pharmacy_name LIMIT 10"
        );
        $st->execute([$like, $like, $like]);
        return $st->fetchAll();
    } catch (Throwable $e) { return null; }
}

/* Weekly schedule + upcoming exceptions for a doctor */
function hospital_name(PDO $pdo, int $hospitalId): string {
    try {
        $st = $pdo->prepare("SELECT hospital_name FROM hospitals WHERE id = ?");
        $st->execute([$hospitalId]);
        $v = $st->fetchColumn();
        return is_string($v) && $v !== '' ? $v : '';
    } catch (Throwable $e) { return ''; }
}

function doctor_schedule_rows(PDO $pdo, int $doctorId): ?array {
    try {
        $st = $pdo->prepare(
            "SELECT day_of_week, start_time, end_time, is_available
             FROM doctor_schedules
             WHERE doctor_id = ?
             ORDER BY FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')"
        );
        $st->execute([$doctorId]);
        return $st->fetchAll();
    } catch (Throwable $e) { return null; }
}

function doctor_exception_rows(PDO $pdo, int $doctorId): ?array {
    try {
        $st = $pdo->prepare(
            "SELECT exception_date, status, reason
             FROM doctor_exceptions
             WHERE doctor_id = ? AND exception_date >= CURDATE()
             ORDER BY exception_date DESC LIMIT 5"
        );
        $st->execute([$doctorId]);
        return $st->fetchAll();
    } catch (Throwable $e) { return null; }
}

function cancellable_appt_rows(PDO $pdo, int $uid): ?array {
    try {
        $st = $pdo->prepare(
            "SELECT id, doctor_name, appointment_date, appointment_time
             FROM appointments
             WHERE user_id = ? AND status IN ('pending','approved')
               AND appointment_date >= CURDATE()
             ORDER BY appointment_date, appointment_time"
        );
        $st->execute([$uid]);
        $rows = $st->fetchAll();
    } catch (Throwable $e) { return null; }
    return array_map(function ($r) {
        return [
            'id' => (int)$r['id'],
            'label' => $r['doctor_name'] ?: 'Doctor',
            'sub' => date('d M Y', strtotime($r['appointment_date'])) . ' · ' . date('h:i A', strtotime($r['appointment_time'])),
        ];
    }, $rows);
}

function pending_request_rows(PDO $pdo, int $uid): ?array {
    try {
        $st = $pdo->prepare(
            "SELECT mr.id, m.medicine_name, p.pharmacy_name
             FROM medicine_requests mr
             JOIN medicines m ON m.id = mr.medicine_id
             LEFT JOIN pharmacies p ON p.id = mr.pharmacy_id
             WHERE mr.user_id = ? AND mr.status = 'pending'
             ORDER BY mr.created_at DESC"
        );
        $st->execute([$uid]);
        $rows = $st->fetchAll();
    } catch (Throwable $e) { return null; }
    return array_map(function ($r) {
        return ['id' => (int)$r['id'], 'label' => $r['medicine_name'] ?: 'Medicine', 'sub' => $r['pharmacy_name'] ?? ''];
    }, $rows);
}

/* ---------------- Date / time parsing ---------------- */
function parse_date_msg(string $msg): ?string {
    // strips "book for the 5th", "on the 25th of next month", "12/31", "31 dec"
    $m = strtolower(trim($msg));
    if ($m === '') return null;
    if (preg_match('/^(?:this |on |for )?today$/', $m)) return date('Y-m-d');
    if (preg_match('/\b(asap|as soon as possible)\b/', $m)) return date('Y-m-d', strtotime('+1 day'));
    $m2 = preg_replace('/\b(book|appointment|visit|for|on|the)\b/', ' ', $m);
    $m2 = strtolower(trim(preg_replace('/\s+/', ' ', $m2)));
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $m2)) {
        $ts = strtotime($m2);
        if ($ts === false || $ts < strtotime('today')) return null;
        return date('Y-m-d', $ts);
    }
    // US mm/dd or ISO-ish dd/mm-yyyy
    if (preg_match('#^(\d{1,2})[-/](\d{1,2})(?:[-/](\d{2,4}))?$#', $m2, $mm)) {
        $mo = (int)$mm[1]; $dy = (int)$mm[2];
        $yr = isset($mm[3]) && $mm[3] !== ''
            ? ((int)$mm[3] < 100 ? 2000 + (int)$mm[3] : (int)$mm[3])
            : date('Y'); // default to this year; fall to next year if past
        $cands = [];
        if ($yr >= date('Y')) $cands[] = date('Y-m-d', mktime(0, 0, 0, $mo, $dy, $yr));
        if (isset($mm[3]) && $mm[3] !== '') return null; // explicit year already handled
        foreach (['+1 year', 'this year'] as $shift) {
            $cands[] = date('Y-m-d', mktime(0, 0, 0, $mo, $dy, (int)date('Y', strtotime($shift))));
        }
        foreach ($cands as $c) {
            if (strtotime($c) !== false && strtotime($c) >= strtotime('today')) return $c;
        }
        return null;
    }
    if (preg_match('/^(\d{1,2})(st|nd|rd|th)?(?:\s+of\s+)?([a-z]{3,}|\d{1,2})(?:\s+(\d{2,4}))?$/', $m2, $mm)) {
        $dy = (int)$mm[1];
        if (isset($mm[3]) && ctype_digit($mm[3])) {
            $mo = (int)$mm[3];
            $yr = isset($mm[4]) && $mm[4] !== '' ? ((int)$mm[4] < 100 ? 2000 + (int)$mm[4] : (int)$mm[4]) : (int)date('Y');
            $c = date('Y-m-d', mktime(0, 0, 0, $mo, $dy, $yr));
            if (strtotime($c) !== false && strtotime($c) >= strtotime('today')) return $c;
            return null;
        }
        if (isset($mm[3]) && is_string($mm[3])) {
            $monthNames = ['jan'=>1,'january'=>1,'feb'=>2,'february'=>2,'mar'=>3,'march'=>3,'apr'=>4,'april'=>4,'may'=>5,'jun'=>6,'june'=>6,'jul'=>7,'july'=>7,'aug'=>8,'august'=>8,'sep'=>9,'september'=>9,'oct'=>10,'october'=>10,'nov'=>11,'november'=>11,'dec'=>12,'december'=>12];
            $mn = $monthNames[$mm[3]] ?? null;
            if ($mn !== null) {
                $yr = (int)date('Y');
                $c = date('Y-m-d', mktime(0, 0, 0, $mn, $dy, $yr));
                if (strtotime($c) >= strtotime('today')) return $c;
                $c = date('Y-m-d', mktime(0, 0, 0, $mn, $dy, $yr + 1));
                if (strtotime($c) !== false) return $c;
            }
        }
        return null;
    }
    if (preg_match('/^(?:next\s+week|in\s+a\s+week|one\s+week)$/', $m2)) return date('Y-m-d', strtotime('+7 days'));
    if (preg_match('/^(?:this\s+weekend|weekend)$/', $m2)) return date('Y-m-d', strtotime('next saturday'));
    if ($m2 === 'tomorrow' || $m2 === 'tmr') return date('Y-m-d', strtotime('+1 day'));
    $days = ['sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6];
    foreach ($days as $name => $dow) {
        $needle = strlen($name) > 6 ? substr($name, 0, 3) : $name;
        if (strpos($m2, $needle) !== false) {
            $today = (int)date('w');
            $diff  = ($dow - $today + 7) % 7;
            if ($diff === 0) $diff = 7;
            // "next X" always jumps a full week ahead
            if (preg_match('/\bnext\b/', $m2)) $diff += 7;
            return date('Y-m-d', strtotime("+$diff days"));
        }
    }
    if (preg_match('/^(\d{1,2})(st|nd|rd|th)?$/', $m2)) {
        $day = (int)$m2;
        if ($day >= 1 && $day <= 31) {
            foreach (['+1 month', 'this month'] as $shift) {
                $candidate = date('Y-m', strtotime($shift)) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
                if (strtotime($candidate) !== false && strtotime($candidate) >= strtotime('today')) return $candidate;
            }
        }
    }
    return null;
}

function parse_time_msg(string $msg): ?string {
    $m = strtolower(trim($msg));
    if ($m === '') return null;
    $specials = ['noon' => 12, 'midnight' => 0, 'morning' => 9, 'afternoon' => 15, 'evening' => 18, 'night' => 20, 'tonight' => 20];
    foreach ($specials as $word => $h24) {
        if ($m === $word || preg_match('/\b' . $word . '\b/', $m)) return sprintf('%02d:%02d', $h24, 0);
    }
    if (preg_match('/\b(asap|right now|immediately|immediate)\b/', $m)) return date('H:i', strtotime('+1 hour'));
    $h = null; $min = 0; $ap = null;
    if (preg_match('/^(\d{1,2}):(\d{2})(?:\s*(am|pm))?$/i', $m, $mm)) {
        $h   = (int)$mm[1];
        $min = (int)$mm[2];
        $ap  = isset($mm[3]) && $mm[3] !== '' ? strtolower($mm[3]) : null;
    } elseif (preg_match('/^(\d{1,2})(?::(\d{2}))?\s*(am|pm)?$/i', $m, $mm)) {
        $h   = (int)$mm[1];
        $min = (int)($mm[2] ?? 0);
        $ap  = isset($mm[3]) && $mm[3] !== '' ? strtolower($mm[3]) : null;
    } else {
        return null;
    }
    if ($h > 23 || $min > 59) return null;
    if ($ap !== null) {
        if ($ap === 'pm' && $h < 12) $h += 12;
        if ($ap === 'am' && $h === 12) $h = 0;
        if ($h > 23) return null;
    } elseif ($h > 23) {
        return null;
    }
    return sprintf('%02d:%02d', $h, $min);
}

function is_yes(string $msg): bool {
    return (bool)preg_match('/\b(yes|yep|yup|yeah|sure|ok|okay|confirm|confirmed|book|go ahead|proceed|do it|continue|next|fine|that\'s right|thats right|correct)\b/', $msg);
}
function is_no(string $msg): bool {
    return (bool)preg_match('/\b(no|nope|cancel|abort|never mind|don\'t|dont|nay|stop|quit|back|skip this|leave it)\b/', $msg);
}

/* ---------------- Prescription upload ---------------- */
function save_prescription_upload(array $f): ?array {
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($f['name'])) return null;
    if ((int)$f['size'] > 5 * 1024 * 1024) {
        return ['type' => 'error', 'msg' => 'That file is over 5 MB. Please upload a smaller prescription (JPG, PNG, or PDF).'];
    }
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'], true)) {
        return ['type' => 'error', 'msg' => 'Prescriptions must be a JPG, PNG, or PDF image.'];
    }
    $dir = __DIR__ . '/uploads/prescriptions';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    if (!is_dir($dir) || !is_writable($dir)) {
        return ['type' => 'error', 'msg' => "I couldn't write to the uploads folder. Please upload the prescription from the Medicines page instead."];
    }
    $fname = uniqid('rx_', true) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], "$dir/$fname")) return ['type' => 'error', 'msg' => "I couldn't save that file. Please try again."];
    return ['type' => 'ok', 'path' => "uploads/prescriptions/$fname", 'name' => $f['name']];
}

/* Jump into the prescription flow right after a file is attached */
function start_rx_flow_with_upload(array $saved): void {
    set_flow([
        'action' => 'presc',
        'step'   => 'medicine',
        'data'   => ['prescription_image' => $saved['path'], 'prescription_name' => $saved['name']],
    ]);
    respond(
        "📄 Got it — I've attached **" . $saved['name'] . "**.\n\n" .
        "Now, which medicine on your prescription would you like details & uses for?\n" .
        "Type a name (e.g. paracetamol), pick from the list, or browse the catalogue.",
        [
            q_item('Browse catalogue', 'catalogue.php', 'capsule'),
            q_action('Cancel', 'stop', 'x-circle'),
        ]
    );
}

/* ============================================================
   ONLINE MEDICINE DETAILS  (US FDA / OpenFDA — no API key)
   Used when a medicine isn't in the local catalogue, or when the
   user explicitly asks for "details / about / info" on a drug.
============================================================ */
function brief(string $s, int $max = 160): string {
    $s = trim(preg_replace('/\s+/', ' ', (string)$s));
    if ($s === '') return '';
    if (function_exists('mb_strlen')) {
        if (mb_strlen($s) <= $max) return $s;
        return rtrim(mb_substr($s, 0, $max), ' .,;:-') . '…';
    }
    if (strlen($s) <= $max) return $s;
    return rtrim(substr($s, 0, $max), ' .,;:-') . '…';
}

function openfda_fetch(string $lucene): ?array {
    if (!function_exists('curl_init')) return null;
    $url = 'https://api.fda.gov/drug/label.json?limit=1&search=' . rawurlencode($lucene);
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 7,
        CURLOPT_USERAGENT      => 'MediConnect-Assistant/1.0',
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200 || !$body) return null;
    $j = json_decode($body, true);
    $r = $j['results'][0] ?? null;
    if (!$r) return null;
    $of = $r['openfda'] ?? [];
    return [
        'brand'        => $of['brand_name'][0]        ?? null,
        'generic'      => $of['generic_name'][0]      ?? null,
        'type'         => $of['product_type'][0]      ?? null,
        'manufacturer' => $of['manufacturer_name'][0] ?? null,
        'route'        => is_array($r['route'] ?? null) ? $r['route'][0] : ($r['route'] ?? null),
        'purpose'      => is_array($r['purpose'] ?? null) ? $r['purpose'][0] : ($r['purpose'] ?? null),
        'use'          => is_array($r['indications_and_usage'] ?? null) ? $r['indications_and_usage'][0] : ($r['indications_and_usage'] ?? null),
        'dosage'       => is_array($r['dosage_and_administration'] ?? null) ? $r['dosage_and_administration'][0] : ($r['dosage_and_administration'] ?? null),
        'warnings'     => is_array($r['warnings'] ?? null) ? $r['warnings'][0] : ($r['warnings'] ?? null),
        'boxed'        => is_array($r['boxed_warning'] ?? null) ? $r['boxed_warning'][0] : ($r['boxed_warning'] ?? null),
    ];
}

function online_medicine_lookup(string $name): ?array {
    if (strlen(trim($name)) < 2) return null;
    $synonyms = [
        'paracetamol' => 'acetaminophen',
        'panadol'     => 'acetaminophen',
        'crocin'      => 'acetaminophen',
        'tylenol'     => 'acetaminophen',
        'calpol'      => 'acetaminophen',
        'napa'        => 'acetaminophen',
    ];
    $q = $synonyms[strtolower(trim($name))] ?? trim($name);
    $searches = [
        'openfda.generic_name:"' . $q . '"',
        'openfda.brand_name:"' . $q . '"',
    ];
    foreach ($searches as $s) {
        $found = openfda_fetch($s);
        if ($found) return $found;
    }
    return openfda_fetch('"' . $q . '"');
}

function online_med_reply(array $m, string $asked): string {
    $head = '💊 **' . (!empty($m['brand']) ? ucfirst($m['brand']) : ucfirst($asked)) . '**';
    if (!empty($m['generic'])) $head .= ' (' . $m['generic'] . ')';
    $r = $head . "\n";
    if (!empty($m['type']))        $r .= "🏷 Type: " . $m['type'] . "\n";
    if (!empty($m['manufacturer'])) $r .= "🏭 Made by: " . $m['manufacturer'] . "\n";
    if (!empty($m['route']))       $r .= "➡ Route: " . $m['route'] . "\n";
    if (!empty($m['purpose']))     $r .= "🎯 Purpose: " . brief($m['purpose'], 160) . "\n";
    if (!empty($m['use']))         $r .= "📚 Used for: " . brief($m['use'], 190) . "\n";
    if (!empty($m['dosage']))      $r .= "💧 Dosage: " . brief($m['dosage'], 150) . "\n";
    if (!empty($m['warnings']))    $r .= "⚠️ Caution: " . brief($m['warnings'], 230) . "\n";
    if (!empty($m['boxed']))       $r .= "🔴 Serious warning: " . brief($m['boxed'], 230) . "\n";
    return rtrim($r);
}

/* ============================================================
   FLOW STARTERS — shared by the keyword intents and the
   generative-AI router so the AI can launch the exact same
   multi-step flows (each replies & exits on its own).
============================================================ */
function start_book_flow(PDO $pdo): void {
    $hosp = approved_hospital_rows($pdo);
    if (!$hosp) {
        respond("I couldn't find any approved hospitals right now, so booking isn't possible. 🏥", [q_item('Open hospitals', 'hospitals.php', 'hospital')]);
    }
    set_flow(['action' => 'book', 'step' => 'hospital', 'data' => []]);
    respond(
        "Let's book an appointment! 📅\n\n" .
        "First — which hospital? 🏥\n\n" . numbered_list($hosp) . "\n\n" .
        "Reply with a number or name.",
        [q_action('Cancel', 'stop', 'x-circle')]
    );
}

function start_med_flow(PDO $pdo): void {
    $hosp_ph = approved_pharmacy_rows($pdo);
    if (!$hosp_ph) {
        respond("There are no approved pharmacies right now, so you can't request a medicine yet.", [q_item('Browse medicines', 'catalogue.php', 'capsule')]);
    }
    $rows = medicine_rows($pdo, null, 8);
    $data = $rows ? ['med_list' => $rows] : [];
    set_flow(['action' => 'med', 'step' => 'medicine', 'data' => $data]);
    respond(
        "Let's request some medicine! 💊\n\n" .
        "What medicine do you need? Type a name (e.g. paracetamol) — or pick from these:\n\n" .
        ($rows ? numbered_list($rows) : "(no medicines in the catalogue right now — just type a name to search)") . "\n\n" .
        "I'll ask a couple more questions and place the request for you.",
        [q_action('Cancel', 'stop', 'x-circle')]
    );
}

function start_presc_flow(): void {
    set_flow(['action' => 'presc', 'step' => 'upload', 'data' => []]);
    respond(
        "Happy to help with your prescription! 🩻\n\n" .
        "Tap the **📎** button next to the input box and attach the prescription image (JPG, PNG or PDF, max 5 MB).\n\n" .
        "Once it's attached, I'll ask which medicine it's for and show you its details & uses — and I can send it to a pharmacy with your request.",
        [q_action('Cancel', 'stop', 'x-circle')]
    );
}

function start_cancel_appt_flow(PDO $pdo, int $uid): void {
    $rows = cancellable_appt_rows($pdo, $uid);
    if (!$rows) {
        respond("You don't have any cancellable appointments (pending or upcoming). 🗓", [q_item('My appointments', 'appointments.php', 'calendar-check')]);
    }
    set_flow(['action' => 'cancel_appt', 'step' => 'pick', 'data' => []]);
    respond(
        "Which appointment would you like to cancel? 🗑\n\n" . numbered_list($rows) . "\n\n" .
        "Reply with a number or name.",
        [q_action('Never mind', 'stop', 'x-circle')]
    );
}

function start_cancel_req_flow(PDO $pdo, int $uid): void {
    $rows = pending_request_rows($pdo, $uid);
    if (!$rows) {
        respond("You don't have any pending medicine requests to cancel. 📦", [q_item('Track requests', 'appointments.php?tab=requests', 'box')]);
    }
    set_flow(['action' => 'cancel_req', 'step' => 'pick', 'data' => []]);
    respond(
        "Which request would you like to cancel? 🗑\n\n" . numbered_list($rows) . "\n\n" .
        "Reply with a number or name.",
        [q_action('Never mind', 'stop', 'x-circle')]
    );
}

/* ============================================================
   CONVERSATIONAL FLOWS
   A flow lives in $_SESSION['chat_flow'] as
   [ 'action' => 'book'|'med'|'cancel_appt'|'cancel_req',
     'step'   => string,
     'data'   => array ]
============================================================ */

/* Top-level intent flags — computed BEFORE the flow handler so a
   fresh intent can interrupt an in-progress flow (e.g. typing
   "book appointment" while inside the date step restarts booking). */
$bookWords  = preg_match('/\b(book|schedule|make|set|arrange|create|fix)\b/', $msg)
           && preg_match('/\b(appointment|visit|consultation|doctor|dr)\b/', $msg);

$prescWords = preg_match('/\b(prescription|rx)\b/', $msg)
           && !preg_match('/\b(no|skip|without|don\'t|dont)\s+(a\s+)?(prescription|rx)\b/', $msg);

/* NOTE: "get medicine details" must NOT start a request — it falls through
   to the catalogue/details intent instead. See the MEDICINES intent. */
$medRequestWords = preg_match('/\b(request|order|buy|get|need|want|procure|place)\b/', $msg)
                && preg_match('/\b(medicine|medicines|drug|drugs|tablet|capsule|medication|syrup|painkiller|paracetamol|amoxicillin|ibuprofen)\b/', $msg)
                && !preg_match('/\b(show|view|list|track|status|details?|about|info|information|catalogue|available)\b/', $msg);

$cancelApptWords = preg_match('/\bcancel\b/', $msg) && preg_match('/\b(appointment|booking|visit)\b/', $msg);
$cancelReqWords  = preg_match('/\bcancel\b/', $msg) && preg_match('/\b(request|order|medicine)\b/', $msg);

$flow = $_SESSION['chat_flow'] ?? null;

/* A fresh top-level intent interrupts any in-progress flow. */
$restartIntent = ($flow !== null)
    && ($bookWords || $prescWords || $medRequestWords || $cancelApptWords || $cancelReqWords);
if ($restartIntent) {
    set_flow(null);
    $flow = null;
    $action = '';
    $step   = '';
    $data   = [];
}

if ($flow) {
    $action = $flow['action'] ?? '';
    $step   = $flow['step']   ?? '';
    $data   = $flow['data']   ?? [];

    /* Universal escape: cancel / exit the flow */
    if (preg_match('/\b(cancel|exit|stop|quit|abort|never mind)\b/', $msg)) {
        set_flow(null);
        respond(
            "No problem — I've cancelled that action. ✅\n\n" .
            "What else can I help you with?",
            [
                q_action('Book appointment', 'book appointment', 'calendar-plus'),
                q_action('Request medicine', 'request medicine', 'capsule'),
                q_item('Open dashboard', 'dashboard.php', 'speedometer2'),
            ]
        );
    }

    /* Prescription file attached while a flow is active — switch to the
       prescription flow (this is the expected way to add a prescription). */
    if ($isFileUpload) {
        $saved = save_prescription_upload($myFile);
        if ($saved === null) respond("Sorry — I couldn't save that file. Please try again, or upload it from the Medicines page.", [q_action('Close', 'cancel', 'x-circle')]);
        if ($saved['type'] === 'error') respond($saved['msg'], [q_action('Close', 'cancel', 'x-circle')]);
        start_rx_flow_with_upload($saved);
    }

    /* ================= BOOK APPOINTMENT ================= */
    if ($action === 'book') {

        if ($step === 'hospital') {
            $hosp = approved_hospital_rows($pdo);
            if (!$hosp) { set_flow(null); respond("I couldn't find any approved hospitals right now. 🏥", [q_item('Open hospitals', 'hospitals.php', 'hospital')]); }
            $ids = resolve_choices($msg, $hosp);
            if (count($ids) === 0) {
                respond("Sorry, I couldn't match that hospital. 🏥\n\nReply with a number or name:\n\n" . numbered_list($hosp));
            }
            if (count($ids) > 1) {
                $subset = array_filter($hosp, fn($r) => in_array($r['id'], $ids, true));
                respond("I found a few hospitals with that name — which one do you mean? 🏥\n\n" . numbered_list(array_values($subset)));
            }
            $id = $ids[0];
            $data['hospital_id'] = $id;
            $data['hospital_name'] = $hosp[array_search($id, array_column($hosp, 'id'))]['label'];
            $docs = doctor_rows($pdo, $id);
            if (!$docs) { set_flow(null); respond("That hospital hasn't published any doctors yet. You could still view its page.", [q_item('Open hospitals', 'hospitals.php', 'hospital')]); }
            set_flow(['action' => 'book', 'step' => 'doctor', 'data' => $data]);
            respond(
                "Great — " . $data['hospital_name'] . ".\n\n" .
                "👨‍⚕ Which doctor would you like to see?\n\n" . numbered_list($docs) . "\n\n" .
                "Reply with a number or name.",
                [q_action('Start over', 'book appointment', 'arrow-counterclockwise'), q_action('Cancel', 'stop', 'x-circle')]
            );
        }

        if ($step === 'doctor') {
            $docs = doctor_rows($pdo, $data['hospital_id'] ?? 0);
            if (!$docs) { set_flow(null); respond("I couldn't find any doctors for that hospital right now.", [q_item('Open hospitals', 'hospitals.php', 'hospital')]); }
            $ids = resolve_choices($msg, $docs);
            if (count($ids) === 0) {
                respond("Hmm, I couldn't match that doctor. 👨‍⚕\n\nReply with a number or name:\n\n" . numbered_list($docs));
            }
            if (count($ids) > 1) {
                $subset = array_filter($docs, fn($r) => in_array($r['id'], $ids, true));
                respond("I found a few doctors — which one do you mean? 👨‍⚕\n\n" . numbered_list(array_values($subset)));
            }
            $id = $ids[0];
            $data['doctor_name'] = $docs[array_search($id, array_column($docs, 'id'))]['label'];
            set_flow(['action' => 'book', 'step' => 'date', 'data' => $data]);
            respond(
                "Good choice — Dr. " . $data['doctor_name'] . " at " . $data['hospital_name'] . ".\n\n" .
                "🗓 What date would you like?\n\n" .
                "• " . date('Y-m-d', strtotime('+7 days')) . "\n• tomorrow\n• monday\n• 25th\n• 25th of october",
                [q_action('Start over', 'book appointment', 'arrow-counterclockwise'), q_action('Cancel', 'stop', 'x-circle')]
            );
        }

        if ($step === 'date') {
            $d = parse_date_msg($msg);
            if ($d === null) {
                respond("Hmm, I couldn't understand that date. 🗓\n\nTry:\n• " . date('Y-m-d', strtotime('+7 days')) . "\n• tomorrow\n• friday\n• 25th\n• 25th of october");
            }
            $data['date'] = $d;
            set_flow(['action' => 'book', 'step' => 'time', 'data' => $data]);
            respond("And what time works best? 🕒\n\nTry:\n• 9:30 am\n• 2:00 pm\n• 15:00\n• morning\n• noon");
        }

        if ($step === 'time') {
            $t = parse_time_msg($msg);
            if ($t === null) {
                respond("That time didn't parse. ⏰\n\nTry:\n• 9:30 am\n• 2:00 pm\n• 15:00\n• morning\n• noon");
            }
            $data['time'] = $t;
            $user = current_user($pdo);
            $data['patient_name']  = trim($user['name']  ?? '');
            $data['patient_phone'] = trim($user['phone'] ?? '');
            if ($data['patient_name'] === '') {
                set_flow(['action' => 'book', 'step' => 'name', 'data' => $data]);
                respond("What name should the appointment be booked under? (we couldn't find one on your profile)");
            } elseif ($data['patient_phone'] === '') {
                set_flow(['action' => 'book', 'step' => 'phone', 'data' => $data]);
                respond("What phone number should we use? (we couldn't find one on your profile)");
            } else {
                set_flow(['action' => 'book', 'step' => 'confirm', 'data' => $data]);
                respond(
                    "Here's your appointment summary 📋:\n\n" .
                    "🏥 Hospital: " . $data['hospital_name'] . "\n" .
                    "👨‍⚕ Doctor: Dr. " . $data['doctor_name'] . "\n" .
                    "📅 Date: " . date('D, d M Y', strtotime($data['date'])) . "\n" .
                    "🕒 Time: " . date('h:i A', strtotime($data['time'])) . "\n" .
                    "👤 Patient: " . $data['patient_name'] . "\n" .
                    "📞 Phone: " . (strlen($data['patient_phone']) ? $data['patient_phone'] : '—') . "\n\n" .
                    "Reply **yes** to confirm, or **no** to cancel.",
                    [q_action('Confirm booking', 'yes', 'check2-circle'), q_action('Cancel', 'no', 'x-circle')]
                );
            }
        }

        if ($step === 'name') {
            if (preg_match('/\b(skip|none|default|use account)\b/', $msg)) {
                $name = 'Patient';
            } else {
                $name = trim($msg);
            }
            if (strlen($name) < 2 || preg_match('/^\d/', $name)) {
                respond("That doesn't look like a name. Could you type the patient's name, please?");
            }
            $data['patient_name'] = $name;
            if (trim($data['patient_phone'] ?? '') === '') {
                set_flow(['action' => 'book', 'step' => 'phone', 'data' => $data]);
                respond("Got it. What phone number should we use? (or reply 'skip')");
            } else {
                set_flow(['action' => 'book', 'step' => 'confirm', 'data' => $data]);
                respond("Thanks! Let me confirm the details.\n\n" . flow_book_summary($data) . "\n\nReply **yes** to confirm, or **no** to cancel.",
                    [q_action('Confirm booking', 'yes', 'check2-circle'), q_action('Cancel', 'no', 'x-circle')]);
            }
        }

        if ($step === 'phone') {
            if (!preg_match('/\b(skip|none)\b/', $msg)) {
                $phone = preg_replace('/[^0-9+]/', '', $msg);
                if (strlen($phone) < 6) {
                    respond("That doesn't look like a phone number. Please enter a valid one (or reply 'skip').");
                }
                $data['patient_phone'] = $phone;
            } else {
                $data['patient_phone'] = '';
            }
            set_flow(['action' => 'book', 'step' => 'confirm', 'data' => $data]);
            respond("Thanks! Let me confirm the details.\n\n" . flow_book_summary($data) . "\n\nReply **yes** to confirm, or **no** to cancel.",
                [q_action('Confirm booking', 'yes', 'check2-circle'), q_action('Cancel', 'no', 'x-circle')]);
        }

        if ($step === 'confirm') {
            if (is_no($msg) || preg_match('/\b(cancel|stop)\b/', $msg)) {
                set_flow(null);
                respond("Okay — appointment booking cancelled. ✅", [q_action('Book appointment', 'book appointment', 'calendar-plus')]);
            }
            if (!is_yes($msg)) {
                respond("Just reply **yes** to confirm or **no** to cancel. 🙂");
            }
            /* Duplicate check (mirrors book_appointment.php) */
            $dup = tries(function () use ($pdo, $uid, $data) {
                $st = $pdo->prepare("SELECT id FROM appointments WHERE user_id = ? AND doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status IN ('pending','approved')");
                $st->execute([$uid, $data['doctor_id'], $data['date'], $data['time']]);
                return $st->fetch() ? true : false;
            });
            if ($dup) {
                set_flow(null);
                respond(
                    "You already have a pending appointment with this doctor at that time. 😅\n\n" .
                    "I've cancelled the flow — just tell me 'book appointment' to try a different slot.",
                    [q_item('My appointments', 'appointments.php', 'calendar-check')]
                );
            }
            $ok = tries(function () use ($pdo, $uid, $data) {
                $pdo->prepare(
                    "INSERT INTO appointments
                        (user_id, hospital_id, doctor_id, patient_name, patient_phone,
                         doctor_name, appointment_date, appointment_time, notes, status, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())"
                )->execute([
                    $uid, $data['hospital_id'], $data['doctor_id'],
                    $data['patient_name'], $data['patient_phone'] ?? '',
                    $data['doctor_name'], $data['date'], $data['time'], ''
                ]);
                $apptId = (int)$pdo->lastInsertId();
                $pdo->prepare("INSERT INTO user_notifications (user_id, title, message, type, related_id) VALUES (?, ?, ?, 'appointment', ?)")
                    ->execute([
                        $uid, 'Appointment requested',
                        "Your appointment with Dr. {$data['doctor_name']} on {$data['date']} at {$data['time']} is pending confirmation.",
                        $apptId,
                    ]);
                return $apptId;
            });
            set_flow(null);
            if ($ok) {
                respond(
                    "🎉 Booking submitted!\n\n" .
                    "Dr. " . $data['doctor_name'] . " · " . $data['hospital_name'] . "\n" .
                    date('D, d M Y', strtotime($data['date'])) . " at " . date('h:i A', strtotime($data['time'])) . "\n\n" .
                    "Your request is **pending** until the hospital confirms it — we'll notify you here when it's approved.",
                    [
                        q_item('My appointments', 'appointments.php', 'calendar-check'),
                        q_action('Book another', 'book appointment', 'calendar-plus'),
                        q_item('Open dashboard', 'dashboard.php', 'speedometer2'),
                    ]
                );
            }
            respond("Sorry, something went wrong while saving your appointment. Please try again from the Appointments page.", [q_item('Appointments', 'appointments.php', 'calendar-check')]);
        }
    }

    /* ================= REQUEST MEDICINE ================= */
    if ($action === 'med') {

        if ($step === 'medicine') {
            $list = $data['med_list'] ?? [];
            $id   = null;

            if (preg_match('/^\d{1,2}$/', $msg) && count($list)) {
                $id = resolve_choice($msg, $list);
                if ($id === null) {
                    respond("That number isn't in the list. Reply with a number or type a medicine name. 🙂");
                }
                $data['medicine_id']   = $id;
                $data['medicine_name'] = $list[array_search($id, array_column($list, 'id'))]['label'];
            } else {
                $kw   = ($msg !== '' && !preg_match('/\b(cancel|stop)\b/', $msg)) ? $msg : null;
                $rows = medicine_rows($pdo, $kw);
                if (!$rows) {
                    $online = tries(function () use ($kw) { return online_medicine_lookup($kw); });
                    if ($online) {
                        set_flow(null);
                        respond(
                            online_med_reply($online, $kw) . "\n\n" .
                            "ℹ️ Source: US FDA (OpenFDA). General info only — confirm with a doctor or pharmacist.\n\n" .
                            "This medicine isn't in our catalogue, so I can't place an order for it. You can pick an available medicine from the catalogue instead.",
                            [q_item('Browse catalogue', 'catalogue.php', 'capsule'), q_action('Request an available medicine', 'request medicine', 'send')]
                        );
                    }
                    respond(
                        "I couldn't find \"$kw\" in our catalogue or online just now. 💊\n\nTry another name or browse the catalogue.",
                        [q_item('Browse catalogue', 'catalogue.php', 'capsule'), q_action('Cancel', 'stop', 'x-circle')]
                    );
                }
                if (count($rows) === 1) {
                    $id                    = $rows[0]['id'];
                    $data['medicine_id']   = $id;
                    $data['medicine_name'] = $rows[0]['label'];
                } else {
                    $data['med_list'] = array_slice($rows, 0, 8);
                    set_flow(['action' => 'med', 'step' => 'medicine', 'data' => $data]);
                    respond(
                        "I found these 💊:\n\n" . numbered_list($data['med_list']) . "\n\n" .
                        "Reply with a number, or type another medicine name to search again.",
                        [q_action('Cancel', 'stop', 'x-circle')]
                    );
                }
            }

            $ph = approved_pharmacy_rows($pdo);
            if (!$ph) { set_flow(null); respond("There are no approved pharmacies right now, so you can't request a medicine yet.", [q_item('Browse medicines', 'catalogue.php', 'capsule')]); }
            set_flow(['action' => 'med', 'step' => 'pharmacy', 'data' => $data]);
            respond(
                "**" . $data['medicine_name'] . "**.\n\n" .
                "Which pharmacy should send it?\n\n" . numbered_list($ph) . "\n\nReply with a number or name.",
                [q_action('Start over', 'request medicine', 'arrow-counterclockwise'), q_action('Cancel', 'stop', 'x-circle')]
            );
        }

        if ($step === 'pharmacy') {
            $ph = approved_pharmacy_rows($pdo);
            if (!$ph) { set_flow(null); respond("There are no approved pharmacies right now, so you can't request a medicine yet.", [q_item('Browse medicines', 'catalogue.php', 'capsule')]); }
            $ids = resolve_choices($msg, $ph);
            if (count($ids) === 0) {
                respond("Sorry, I couldn't match that pharmacy. 🏪\n\nReply with a number or name:\n\n" . numbered_list($ph));
            }
            if (count($ids) > 1) {
                $subset = array_filter($ph, fn($r) => in_array($r['id'], $ids, true));
                respond("I found a few pharmacies with that name — which one do you mean? 🏪\n\n" . numbered_list(array_values($subset)));
            }
            $id = $ids[0];
            $data['pharmacy_id'] = $id;
            $data['pharmacy_name'] = $ph[array_search($id, array_column($ph, 'id'))]['label'];
            set_flow(['action' => 'med', 'step' => 'qty', 'data' => $data]);
            respond("How many would you like? (1–99)", [q_action('1 (default)', '1', 'plus-circle'), q_action('Cancel', 'stop', 'x-circle')]);
        }

        if ($step === 'qty') {
            $qty = (int)$msg;
            if ($qty < 1 || $qty > 99 || (string)$qty !== $msg || !preg_match('/^\d{1,2}$/', $msg)) {
                respond("Please reply with a quantity between 1 and 99.");
            }
            $data['qty'] = $qty;
            set_flow(['action' => 'med', 'step' => 'notes', 'data' => $data]);
            respond("Any notes for the pharmacy? ✍\n(e.g. dosage, brand preference — or reply 'none' / 'skip')",
                [q_action('No notes', 'none', 'check2-circle'), q_action('Cancel', 'stop', 'x-circle')]);
        }

        if ($step === 'notes') {
            if (preg_match('/\b(none|skip|no notes|n\/a)\b/', $msg)) {
                $data['notes'] = '';
            } else {
                $data['notes'] = ucfirst($msg);
            }
            set_flow(['action' => 'med', 'step' => 'confirm', 'data' => $data]);
            respond(
                "Here's your medicine request 📦:\n\n" .
                "💊 Medicine: " . $data['medicine_name'] . "\n" .
                "🏪 Pharmacy: " . $data['pharmacy_name'] . "\n" .
                "🔢 Quantity: " . $data['qty'] . ($data['notes'] ? "\n📝 Notes: " . $data['notes'] : '') .
                "\n📄 Prescription: " . (!empty($data['prescription_name']) ? 'attached (' . $data['prescription_name'] . ')' : 'none') . "\n\n" .
                "Reply **yes** to submit, or **no** to cancel.",
                [q_action('Submit request', 'yes', 'send'), q_action('Cancel', 'no', 'x-circle')]
            );
        }

        if ($step === 'confirm') {
            if (is_no($msg) || preg_match('/\b(cancel|stop)\b/', $msg)) {
                set_flow(null);
                respond("Okay — medicine request cancelled. ✅", [q_action('Request medicine', 'request medicine', 'capsule')]);
            }
            if (!is_yes($msg)) {
                respond("Just reply **yes** to submit or **no** to cancel. 🙂");
            }
            $ok = tries(function () use ($pdo, $uid, $data) {
                $pdo->prepare(
                    "INSERT INTO medicine_requests (user_id, pharmacy_id, medicine_id, quantity, prescription_image, notes, status)
                     VALUES (?, ?, ?, ?, ?, ?, 'pending')"
                )->execute([$uid, $data['pharmacy_id'], $data['medicine_id'], $data['qty'], $data['prescription_image'] ?? null, $data['notes'] ?? '']);
                $reqId = (int)$pdo->lastInsertId();
                $pdo->prepare("INSERT INTO user_notifications (user_id, title, message, type, related_id) VALUES (?, ?, ?, 'medicine_request', ?)")
                    ->execute([
                        $uid, 'Medicine request submitted',
                        "Your request for {$data['medicine_name']} (Qty: {$data['qty']}) has been sent to the pharmacy.",
                        $reqId,
                    ]);
                return $reqId;
            });
            set_flow(null);
            if ($ok) {
respond(
                    "✅ Request submitted!\n\n" .
                    "**" . $data['medicine_name'] . "** × " . $data['qty'] . " → " . $data['pharmacy_name'] . ".\n\n" .
                    "The pharmacy will update it shortly — we'll notify you when it's accepted.",
                    [
                        q_item('Track requests', 'appointments.php?tab=requests', 'box'),
                        q_action('Request another', 'request medicine', 'capsule'),
                    ]
                );
            }
            respond("Sorry, something went wrong while saving your request. Please try again from the catalogue.", [q_item('Catalogue', 'catalogue.php', 'capsule')]);
        }
    }

    /* ================= CANCEL APPOINTMENT ================= */
    if ($action === 'cancel_appt') {

        if ($step === 'pick') {
            $rows = cancellable_appt_rows($pdo, $uid);
            if (!$rows) { set_flow(null); respond("You don't have any cancellable appointments (pending or upcoming). 🗓", [q_item('My appointments', 'appointments.php', 'calendar-check')]); }
            $id = resolve_choice($msg, $rows);
            if ($id === null) {
                respond("Sorry, I couldn't match that appointment.\n\n" . numbered_list($rows));
            }
            $data['appt_id'] = $id;
            $data['appt_label'] = $rows[array_search($id, array_column($rows, 'id'))]['label'] . ' — ' . $rows[array_search($id, array_column($rows, 'id'))]['sub'];
            set_flow(['action' => 'cancel_appt', 'step' => 'confirm', 'data' => $data]);
            respond(
                "Cancel this appointment? 🗑\n\n• " . $data['appt_label'] . "\n\nReply **yes** to cancel or **no** to keep it.",
                [q_action('Yes, cancel it', 'yes', 'trash'), q_action('No, keep it', 'no', 'check2-circle')]
            );
        }

        if ($step === 'confirm') {
            if (is_no($msg)) { set_flow(null); respond("Good call — I kept your appointment. ✅", [q_item('My appointments', 'appointments.php', 'calendar-check')]); }
            if (!is_yes($msg)) { respond("Just reply **yes** to cancel or **no** to keep it. 🙂"); }
            $ok = tries(function () use ($pdo, $uid, $data) {
                $st = $pdo->prepare("SELECT doctor_name, appointment_date, appointment_time FROM appointments WHERE id = ? AND user_id = ? AND status IN ('pending','approved')");
                $st->execute([$data['appt_id'], $uid]);
                $a = $st->fetch();
                if (!$a) return false;
                $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ?")->execute([$data['appt_id']]);
                $pdo->prepare("INSERT INTO user_notifications (user_id, title, message, type, related_id) VALUES (?, ?, ?, 'appointment', ?)")
                    ->execute([$uid, 'Appointment cancelled', "Your appointment with {$a['doctor_name']} on {$a['appointment_date']} at {$a['appointment_time']} has been cancelled.", $data['appt_id']]);
                return true;
            });
            set_flow(null);
            respond(
                $ok ? "✅ Done — that appointment has been cancelled." : "Hmm, I couldn't cancel it — maybe it's already been updated elsewhere.",
                [q_item('My appointments', 'appointments.php', 'calendar-check'), q_action('Book appointment', 'book appointment', 'calendar-plus')]
            );
        }
    }

    /* ================= CANCEL MEDICINE REQUEST ================= */
    if ($action === 'cancel_req') {

        if ($step === 'pick') {
            $rows = pending_request_rows($pdo, $uid);
            if (!$rows) { set_flow(null); respond("You don't have any pending medicine requests to cancel. 📦", [q_item('Track requests', 'appointments.php?tab=requests', 'box')]); }
            $id = resolve_choice($msg, $rows);
            if ($id === null) {
                respond("Sorry, I couldn't match that request.\n\n" . numbered_list($rows));
            }
            $data['req_id'] = $id;
            $data['req_label'] = $rows[array_search($id, array_column($rows, 'id'))]['label'] . ($rows[array_search($id, array_column($rows, 'id'))]['sub'] ? ' @ ' . $rows[array_search($id, array_column($rows, 'id'))]['sub'] : '');
            set_flow(['action' => 'cancel_req', 'step' => 'confirm', 'data' => $data]);
            respond(
                "Cancel this request? 🗑\n\n• " . $data['req_label'] . "\n\nReply **yes** to cancel or **no** to keep it.",
                [q_action('Yes, cancel it', 'yes', 'trash'), q_action('No, keep it', 'no', 'check2-circle')]
            );
        }

        if ($step === 'confirm') {
            if (is_no($msg)) { set_flow(null); respond("Good call — I kept your request. ✅", [q_item('Track requests', 'appointments.php?tab=requests', 'box')]); }
            if (!is_yes($msg)) { respond("Just reply **yes** to cancel or **no** to keep it. 🙂"); }
            $ok = tries(function () use ($pdo, $uid, $data) {
                $st = $pdo->prepare("SELECT m.medicine_name FROM medicine_requests mr JOIN medicines m ON m.id = mr.medicine_id WHERE mr.id = ? AND mr.user_id = ? AND mr.status = 'pending'");
                $st->execute([$data['req_id'], $uid]);
                $r = $st->fetch();
                if (!$r) return false;
                $pdo->prepare("UPDATE medicine_requests SET status = 'cancelled' WHERE id = ?")->execute([$data['req_id']]);
                $pdo->prepare("INSERT INTO user_notifications (user_id, title, message, type, related_id) VALUES (?, ?, ?, 'medicine_request', ?)")
                    ->execute([$uid, 'Medicine request cancelled', "Your request for {$r['medicine_name']} has been cancelled.", $data['req_id']]);
                return true;
            });
            set_flow(null);
            respond(
                $ok ? "✅ Done — that request has been cancelled." : "Hmm, I couldn't cancel it — maybe it's already been updated elsewhere.",
                [q_item('Track requests', 'appointments.php?tab=requests', 'box'), q_action('Request medicine', 'request medicine', 'capsule')]
            );
        }
    }

    /* ================= PRESCRIPTION (upload → details & uses) ================= */
    if ($action === 'presc') {

        if ($step === 'upload') {
            respond(
                "Sure! 🩻 Upload your prescription using the **📎** button next to the input box.\n\n" .
                "Formats: JPG, PNG or PDF · max 5 MB.\n\n" .
                "Once it's attached, I'll ask which medicine it's for and show you its details & uses.",
                [q_action('Cancel', 'stop', 'x-circle')]
            );
        }

        if ($step === 'medicine') {
            $list = $data['med_list'] ?? null;
            $id   = null;

            if ($list && preg_match('/^\d{1,2}$/', $msg)) {
                $id = resolve_choice($msg, $list);
                if ($id === null) {
                    respond("That number isn't in the list. 🙂\n\nReply with a number:\n\n" . numbered_list($list), [q_action('Cancel', 'stop', 'x-circle')]);
                }
            } else {
                $kw = ($msg !== '' && !preg_match('/\b(cancel|stop)\b/', $msg)) ? $msg : null;
                if ($kw === null) {
                    respond("Type a medicine name (e.g. paracetamol) — or browse the catalogue.", [q_item('Browse catalogue', 'catalogue.php', 'capsule'), q_action('Cancel', 'stop', 'x-circle')]);
                }
                $rows = medicine_rows($pdo, $kw);
                if (!$rows) {
                    $online = tries(function () use ($kw) { return online_medicine_lookup($kw); });
                    if ($online) {
                        set_flow(null);
                        respond(
                            online_med_reply($online, $kw) . "\n\n" .
                            "ℹ️ Source: US FDA (OpenFDA) — general info only.\n\n" .
                            "This medicine isn't in our catalogue, so it can't be ordered from a pharmacy here. Your prescription is still saved.",
                            [q_item('Browse catalogue', 'catalogue.php', 'capsule')]
                        );
                    }
                    respond("I couldn't find \"$kw\" in our catalogue or online. Try another name, or browse the catalogue.", [q_item('Browse catalogue', 'catalogue.php', 'capsule'), q_action('Cancel', 'stop', 'x-circle')]);
                }
                if (count($rows) === 1) {
                    $id = (int)$rows[0]['id'];
                } else {
                    $data['med_list'] = array_slice($rows, 0, 8);
                    set_flow(['action' => 'presc', 'step' => 'medicine', 'data' => $data]);
                    respond("I found these 💊:\n\n" . numbered_list($data['med_list']) . "\n\nReply with a number, or type another name.", [q_action('Cancel', 'stop', 'x-circle')]);
                }
            }

            if ($id === null) {
                respond("Hmm, I couldn't match that. Try again or browse the catalogue.", [q_item('Browse catalogue', 'catalogue.php', 'capsule'), q_action('Cancel', 'stop', 'x-circle')]);
            }
            $med = tries(function () use ($pdo, $id) {
                $st = $pdo->prepare("SELECT id, medicine_name, generic_name, category, description FROM medicines WHERE id = ?");
                $st->execute([$id]);
                return $st->fetch() ?: null;
            });
            if (!$med) { set_flow(null); respond("Hmm, I couldn't load that medicine. Try again?", [q_item('Browse catalogue', 'catalogue.php', 'capsule')]); }

            $data['medicine_id']   = (int)$med['id'];
            $data['medicine_name'] = $med['medicine_name'];
            unset($data['med_list']);

            $d = "💊 **" . $med['medicine_name'] . "**";
            if (!empty($med['generic_name'])) $d .= " (" . $med['generic_name'] . ")";
            if (!empty($med['category']))    $d .= " — " . $med['category'];
            if (!empty($med['description'])) $d .= "\n\n📋 **Details & uses:**\n" . $med['description'];
            $online = tries(function () use ($med) { return online_medicine_lookup($med['medicine_name']); });
            if ($online && !empty($online['use'])) $d .= "\n\n⚕️ **Uses (online source):** " . brief($online['use'], 190);

            set_flow(['action' => 'presc', 'step' => 'askrx', 'data' => $data]);
            respond(
                $d . "\n\n" .
                "📄 Your prescription is attached. Should I request this medicine from a pharmacy with it?",
                [
                    q_action('Yes, request it', 'yes', 'send'),
                    q_action('No, that\'s all', 'no', 'check2-circle'),
                    q_item('Browse catalogue', 'catalogue.php', 'capsule'),
                ]
            );
        }

        if ($step === 'askrx') {
            if (is_no($msg)) {
                set_flow(null);
                respond(
                    "Okay — details above, prescription saved. 📄\n\nAnything else I can help with?",
                    [q_action('Request medicine', 'request medicine', 'capsule'), q_item('Browse catalogue', 'catalogue.php', 'capsule')]
                );
            }
            if (!is_yes($msg)) {
                respond("Just reply **yes** to request it with the prescription, or **no** if you're done. 🙂");
            }
            $ph = approved_pharmacy_rows($pdo);
            if (!$ph) { set_flow(null); respond("There are no approved pharmacies right now, so a request can't be placed. 😕", [q_item('Browse medicines', 'catalogue.php', 'capsule')]); }
            set_flow(['action' => 'med', 'step' => 'pharmacy', 'data' => $data]);
            respond(
                "Great — let's place the request. 🏪\n\n" .
                "Which pharmacy should send **" . $data['medicine_name'] . "**?\n\n" . numbered_list($ph) . "\n\nReply with a number or name.",
                [q_action('Start over', 'request medicine', 'arrow-counterclockwise'), q_action('Cancel', 'stop', 'x-circle')]
            );
        }
    }

    /* Unknown flow state — reset and fall through */
    set_flow(null);
}

/* Shared booking summary used after name / phone steps */
function flow_book_summary(array $data): string {
    return
        "🏥 Hospital: " . ($data['hospital_name'] ?? '—') . "\n" .
        "👨‍⚕ Doctor: Dr. " . ($data['doctor_name'] ?? '—') . "\n" .
        "📅 Date: " . (isset($data['date']) ? date('D, d M Y', strtotime($data['date'])) : '—') . "\n" .
        "🕒 Time: " . (isset($data['time']) ? date('h:i A', strtotime($data['time'])) : '—') . "\n" .
        "👤 Patient: " . ($data['patient_name'] ?? '—') . "\n" .
        "📞 Phone: " . (strlen($data['patient_phone'] ?? '') ? $data['patient_phone'] : '—');
}

/* ============================================================
   INDIVIDUAL INTENTS (no active flow)
============================================================ */

/* Prescription file attached with no active flow — start the flow */
if ($isFileUpload) {
    $saved = save_prescription_upload($myFile);
    if ($saved === null) respond("Sorry — I couldn't save that file. Please try again, or upload it from the Medicines page.", [q_action('Close', 'cancel', 'x-circle')]);
    if ($saved['type'] === 'error') respond($saved['msg'], [q_action('Close', 'cancel', 'x-circle')]);
    start_rx_flow_with_upload($saved);
}

/* Intent flags are defined near the top (before the flow handler). */

/* ============================================================
   GREETING / DEFAULT
============================================================ */
if ($msg === '' || preg_match('/\b(hi|hello|hey|yo|hy|howdy)\b/', $msg)) {
    respond(
        "Hello! 👋 Welcome to MediConnect.\n\n" .
        "I'm your health assistant — I can find hospitals, doctors, medicines & pharmacies, and I can also do things for you right here:\n\n" .
        "📅 Book appointments\n" .
        "💊 Request medicines\n" .
        "🗑 Cancel appointments / requests\n" .
        "🔔 Check notifications\n\n" .
        "How can I help you today?",
        [
            q_action('📅 Book appointment', 'book appointment', 'calendar-plus'),
            q_action('💊 Request medicine', 'request medicine', 'capsule'),
            q_action('🩻 Add prescription', 'add prescription', 'file-earmark-medical'),
            q_item('🏥 Hospitals', 'hospitals.php', 'hospital'),
            q_item('👨‍⚕ Doctors', 'doctors.php', 'person-badge'),
            q_item('💊 Medicines', 'catalogue.php', 'capsule'),
            q_item('🏪 Pharmacies', 'pharmacies.php', 'shop'),
        ]
    );
}

/* ============================================================
   HELP
=========================================================== */
if (preg_match('/\b(help|what can you do|options|menu|commands|how do i|how to)\b/', $msg)) {
    respond(
        "Here's everything I can do 👇\n\n" .
        "🔎 Find things:\n" .
        "   🏥 Hospitals · 👨‍⚕ Doctors · 💊 Medicines · 🏪 Pharmacies\n\n" .
        "⚡ Take action:\n" .
        "   • Book an appointment (just say \"book appointment\")\n" .
        "   • Request a medicine (\"request medicine\" or \"need paracetamol\")\n" .
        "   • Cancel an appointment / request (\"cancel\")\n\n" .
        "👤 My stuff:\n" .
        "   • \"my appointments\" · \"my medicine requests\" · \"notifications\" · \"profile\"\n\n" .
        "Try:\n\"find hospitals in Lagos\"\n\"book appointment\"",
        [
            q_action('Book appointment', 'book appointment', 'calendar-plus'),
            q_action('Request medicine', 'request medicine', 'capsule'),
            q_action('Add prescription', 'add prescription', 'file-earmark-medical'),
            q_item('Open Hospitals', 'hospitals.php', 'hospital'),
            q_item('Open Doctors', 'doctors.php', 'person-badge'),
        ]
    );
}

/* ============================================================
   BOOK APPOINTMENT (conversational)
=========================================================== */
if ($bookWords) {
    start_book_flow($pdo);
}

/* ============================================================
   PRESCRIPTION (conversational)
   "add / upload / my prescription" — attach + details & uses
============================================================ */
if ($prescWords) {
    start_presc_flow();
}

/* ============================================================
   REQUEST MEDICINE (conversational)
============================================================ */
if ($medRequestWords && !$cancelReqWords && !$cancelApptWords) {
    start_med_flow($pdo);
}

/* ============================================================
   CANCEL APPOINTMENT (conversational)
=========================================================== */
if (preg_match('/\bcancel\b/', $msg) && preg_match('/\b(appointment|booking|visit)\b/', $msg)) {
    start_cancel_appt_flow($pdo, $uid);
}

/* ============================================================
   CANCEL MEDICINE REQUEST (conversational)
=========================================================== */
if (preg_match('/\bcancel\b/', $msg) && preg_match('/\b(request|order|medicine)\b/', $msg)) {
    start_cancel_req_flow($pdo, $uid);
}

/* ============================================================
   NOTIFICATIONS
=========================================================== */
if (preg_match('/\b(notification|notifications|notice|notices|alert|alerts|inbox)\b/', $msg)) {
    $notifs = tries(function () use ($pdo, $uid) {
        return $pdo->query(
            "SELECT title, message, created_at FROM user_notifications
             WHERE user_id = $uid AND is_read = 0
             ORDER BY created_at DESC LIMIT 5"
        )->fetchAll();
    });
    if (!$notifs) {
        respond(
            "You're all caught up! 🔔 No unread notifications right now.",
            [q_item('Open dashboard', 'dashboard.php#notifications', 'speedometer2')]
        );
    }
    $lines = [];
    foreach ($notifs as $n) {
        $lines[] = "• " . $n['title'] . "\n   " . $n['message'] . "\n   " . date('d M Y, h:i A', strtotime($n['created_at']));
    }
    respond(
        "Here are your unread notifications 🔔:\n\n" . implode("\n\n", $lines) . "\n\n" .
        "You can mark them as read from your dashboard.",
        [q_item('Open dashboard', 'dashboard.php#notifications', 'bell')]
    );
}

/* ============================================================
   PROFILE / SUMMARY
=========================================================== */
if (preg_match('/\b(profile|account|summary|overview|dashboard|my stats|myself|my records?|patient records?)\b/', $msg)) {
    $appts = tries(function () use ($pdo, $uid) { return (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE user_id = $uid")->fetchColumn(); });
    $reqs  = tries(function () use ($pdo, $uid) { return (int)$pdo->query("SELECT COUNT(*) FROM medicine_requests WHERE user_id = $uid")->fetchColumn(); });
    $unread= unread_notifications($pdo, $uid);
    $user  = current_user($pdo);
    $records = tries(function () use ($pdo, $user) {
        $conds = []; $params = [];
        if (!empty($user['email'])) { $conds[] = "email = ?"; $params[] = $user['email']; }
        if (!empty($user['phone'])) { $conds[] = "phone = ?";  $params[] = $user['phone']; }
        if (!$conds) return [];
        $st = $pdo->prepare("SELECT p.id, p.patient_name, p.gender, p.age, h.hospital_name
                             FROM patients p
                             LEFT JOIN hospitals h ON h.id = p.hospital_id
                             WHERE " . implode(' OR ', $conds) . " LIMIT 3");
        $st->execute($params);
        return $st->fetchAll();
    });
    $userName = $user['name'] ?? 'friend';
    $head = "Here's your health summary 📊, " . $userName . ":\n\n" .
        "📅 Appointments: " . (int)$appts . "\n" .
        "💊 Medicine requests: " . (int)$reqs . "\n" .
        "🔔 Unread notifications: " . (int)$unread . "\n";
    if ($records && count($records)) {
        $head .= "\n🩺 Patient records on file:\n";
        foreach ($records as $r) {
            $head .= "• " . $r['patient_name'] . ($r['gender'] ? ' (' . $r['gender'] . ($r['age'] ? ' · ' . (int)$r['age'] . ' yrs' : '') . ')' : '')
                   . ($r['hospital_name'] ? ' @ ' . $r['hospital_name'] : '') . "\n";
        }
    }
    respond(
        rtrim($head) . "\n\nWhat would you like to do?",
        [
            q_action('📅 Book appointment', 'book appointment', 'calendar-plus'),
            q_action('💊 Request medicine', 'request medicine', 'capsule'),
            q_item('My profile', 'profile.php', 'person'),
            q_item('My appointments', 'appointments.php', 'calendar-check'),
        ]
    );
}

/* ============================================================
   HOSPITALS
=========================================================== */
if (preg_match('/\b(hospital|hospitals|clinic)\b/', $msg)) {
    $city = null;
    if (preg_match('/\b(?:in|at|near)\s+([a-z ]+?)(?:\?|$)/', $msg, $m)) {
        $city = trim($m[1]);
        if (preg_match('/\b(hospital|pharmacy|city|any|around)\b/', $city)) $city = null;
    }
    $rows = tries(function () use ($pdo, $city) {
        $sql    = "SELECT id, hospital_name, city, specialty FROM hospitals WHERE status = 'approved'";
        $params = [];
        if ($city) { $sql .= " AND city LIKE ?"; $params[] = "%$city%"; }
        $sql .= " ORDER BY hospital_name LIMIT 6";
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    });
    if ($rows === null || !count($rows)) {
        respond(
            "I couldn't find any approved hospitals" . ($city ? " in \"$city\"" : '') . " right now.\n\n" .
            "Try a different city or browse the Hospitals page.",
            [q_item('Open Hospitals', 'hospitals.php', 'hospital')]
        );
    } else {
        $lines = [];
        foreach ($rows as $h) {
            $lines[] = "• " . $h['hospital_name'] .
                       ($h['city']      ? ' — ' . $h['city']      : '') .
                       ($h['specialty'] ? ' (' . $h['specialty'] . ')' : '');
        }
        respond(
            "Here are some approved hospitals" . ($city ? " in \"$city\"" : '') . ":\n\n" .
            implode("\n\n", $lines) . "\n\n" .
            "You can view their doctors and book appointments from each hospital's page — or I can help you book one now.",
            [
                q_action('Book an appointment', 'book appointment', 'calendar-plus'),
                q_item('View all hospitals', 'hospitals.php', 'hospital'),
            ]
        );
    }
}

/* ============================================================
   DOCTORS
=========================================================== */
if (preg_match('/\b(doctor|doctors|physician|specialist)\b/', $msg)
    && !preg_match('/\b(available|availability|free|working hours|on duty|on call|schedule|when is)\b/', $msg)) {
    $spec = null;
    if (preg_match('/\b(cardiolog|pediatr|gynecolog|dermatolog|dentist|surgeon|neurolog|orthoped|physiotherap|general)\b/', $msg, $m)) {
        $spec = $m[1];
    }
    $rows = tries(function () use ($pdo, $spec) {
        $sql    = "SELECT d.doctor_name d_name, d.specialization,
                         h.hospital_name
                  FROM doctors d
                  LEFT JOIN hospitals h ON h.id = d.hospital_id
                  WHERE 1=1";
        $params = [];
        if ($spec) {
            $sql .= " AND d.specialization LIKE ?";
            $params[] = "%$spec%";
        }
        $sql .= " ORDER BY d.doctor_name LIMIT 6";
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    });
    if ($rows === null || !count($rows)) {
        respond(
            "I couldn't find any doctors" . ($spec ? " specializing in \"$spec\"" : '') . " right now.\n\n" .
            "Try another specialization or browse the Doctors page.",
            [q_item('Open Doctors', 'doctors.php', 'person-badge')]
        );
    } else {
        $lines = [];
        foreach ($rows as $d) {
            $lines[] = "• Dr. " . $d['d_name'] .
                       ($d['specialization'] ? ' — ' . $d['specialization'] : '') .
                       ($d['hospital_name']  ? ' @ ' . $d['hospital_name']  : '');
        }
        respond(
            "Here are the doctors I found" . ($spec ? " for \"$spec\"" : '') . ":\n\n" .
            implode("\n\n", $lines) . "\n\n" .
            "Would you like to book an appointment with any of them?",
            [
                q_action('Book appointment', 'book appointment', 'calendar-plus'),
                q_item('Book appointment page', 'book_appointment.php', 'calendar'),
                q_item('View all doctors', 'doctors.php', 'person-badge'),
            ]
        );
    }
}

/* ============================================================
   DOCTOR AVAILABILITY
   "when is dr X available/free", "is dr X working monday",
   "what are dr X's hours"
============================================================ */
if (preg_match('/\b(available|availability|free|working hours|hours|shift|on duty|on call|schedule)\b/', $msg)
    && preg_match('/\b(doctor|dr\.?)\b/i', $msg)) {

    /* Extract the doctor's name: "dr satheesh", "doctor smith", "satheesh" */
    $docKw = null;
    if (preg_match('/\b(?:dr\.?|doctor)\s+([a-z0-9 .\-]+?)(?=\s+(?:available|free|working|is|on|this|next|today|tomorrow|monday|tuesday|wednesday|thursday|friday|saturday|sunday|in|for|\?)|$)/i', $msg, $m)) {
        $docKw = trim(rtrim($m[1], ' .'));
        if (strlen($docKw) < 3) $docKw = null;
    }

    $docs = tries(function () use ($pdo) {
        return $pdo->query("SELECT id, doctor_name, hospital_id FROM doctors ORDER BY doctor_name")->fetchAll();
    });

    if ($docs === null || !count($docs)) {
        respond("I couldn't find any doctors in the system right now. 👨‍⚕", [q_item('Open doctors', 'doctors.php', 'person-badge')]);
    }

    /* Choose: by extracted name, else by substring of name in the message */
    $match = null;
    foreach ($docs as $d) {
        $dn = strtolower($d['doctor_name']);
        if ($docKw !== null && strpos($dn, strtolower($docKw)) !== false) { $match = $d; break; }
        $token = preg_split('/\s+/', $dn)[0] ?? '';
        if ($docKw === null && $token && strpos($msg, $token) !== false) { $match = $d; break; }
    }
    if ($match === null) {
        if ($docKw !== null) {
            respond(
                "I couldn't find a doctor named \"$docKw\". 🧐\n\n" .
                "Here are the doctors I know:\n\n" . numbered_list(array_map(fn($d) => ['label' => $d['doctor_name'], 'sub' => hospital_name($pdo, (int)$d['hospital_id'])], $docs)),
                [q_item('Browse doctors', 'doctors.php', 'person-badge')]
            );
        }
        respond(
            "Which doctor should I check? Just tell me their name (e.g. \"is Dr. satheesh available tomorrow?\").",
            [q_item('Browse doctors', 'doctors.php', 'person-badge')]
        );
    }

    $did = (int)$match['id'];
    $schedule = doctor_schedule_rows($pdo, $did) ?? [];
    $exc      = doctor_exception_rows($pdo, $did) ?? [];

    if (!$schedule) {
        respond(
            "Dr. **" . $match['doctor_name'] . "** hasn't published a weekly schedule yet.\n\n" .
            "You can still view the doctor or book and the hospital will confirm availability.",
            [
                q_action('Book appointment', 'book appointment', 'calendar-plus'),
                q_item('View doctors', 'doctors.php', 'person-badge'),
            ]
        );
    }

    $excByDate = [];
    foreach ($exc as $e) { $excByDate[$e['exception_date']] = $e['status'] . ($e['reason'] ? ' (' . $e['reason'] . ')' : ''); }

    $lines = [];
    foreach ($schedule as $s) {
        $day  = $s['day_of_week'];
        if (!(int)$s['is_available']) {
            $lines[] = "• " . $day . " — off";
            continue;
        }
        $t = date('g:i A', strtotime($s['start_time'])) . " – " . date('g:i A', strtotime($s['end_time']));
        $lines[] = "• " . $day . " — " . $t;
    }

    $extra = '';
    if (count($excByDate)) {
        $xs = [];
        foreach ($excByDate as $date => $info) {
            $xs[] = date('d M Y', strtotime($date)) . " (" . $info . ")";
        }
        $extra = "\n\n⚠️ Upcoming exceptions:\n" . implode("\n", array_map(fn($x) => '• ' . $x, $xs));
    }

    $hospName = hospital_name($pdo, (int)$match['hospital_id']);
    respond(
        "👨‍⚕ Dr. **" . $match['doctor_name'] . "**" . ($hospName ? " at $hospName" : '') . " — weekly availability:\n\n" .
        implode("\n", $lines) . $extra . "\n\n" .
        "Want to book a slot?",
        [
            q_action('Book appointment', 'book appointment', 'calendar-plus'),
            q_item('View doctors', 'doctors.php', 'person-badge'),
        ]
    );
}

/* ============================================================
   PHARMACIES
============================================================ */
if (preg_match('/\b(pharmacy|pharmacies|chemist|drugstore|shop)\b/', $msg)) {
    $rows = tries(function () use ($pdo) {
        $sql = "SELECT id, pharmacy_name, city FROM pharmacies WHERE status = 'approved' ORDER BY pharmacy_name LIMIT 6";
        return $pdo->query($sql)->fetchAll();
    });
    if ($rows === null || !count($rows)) {
        respond(
            "There are no approved pharmacies listed right now.\n\n" .
            "You can still browse medicines and request them from a pharmacy once one is approved.",
            [q_item('Browse medicines', 'catalogue.php', 'capsule')]
        );
    } else {
        $lines = [];
        foreach ($rows as $p) {
            $lines[] = "• " . $p['pharmacy_name'] . ($p['city'] ? ' — ' . $p['city'] : '');
        }
        respond(
            "Here are the approved pharmacies:\n\n" . implode("\n\n", $lines) . "\n\n" .
            "You can request medicines from any of them — want me to place a request?",
            [
                q_action('Request a medicine', 'request medicine', 'capsule'),
                q_item('View all pharmacies', 'pharmacies.php', 'shop'),
            ]
        );
    }
}

/* ============================================================
   STOCK / PRICE CHECK
   "is paracetamol in stock", "price of paracetamol", "do you sell X"
============================================================ */
if (preg_match('/\b(in stock|out of stock|stock|price|prices?|how much|how much is|how much does|available|availability|sell|do you sell|have you got|in offer|discount)\b/', $msg)
    && preg_match('/\b(medicine|medicines|drug|drugs|tablet|capsule|syrup|paracetamol|acetaminophen|amoxicillin|amoxiclav|ibuprofen|aspirin|tylenol|panadol|metformin|penicillin|losartan|omeprazole|nurofen)\b/', $msg)) {

    /* Pull the medicine keyword out of the sentence */
    $kw = null;
    if (preg_match('/\b(?:for|search|find|about|details?\s+(?:of|on|about)?)\s+([a-z][a-z0-9 ]*?)(?:\?|$)/', $msg, $m)) {
        $kw = trim($m[1]);
        $kw = trim(preg_replace('/\b(medicine|medicines|drug|drugs|tablet|capsule|syrup|details?|about|info|information)\b.*$/i', '', (string)$kw));
    }
    /* "price of paracetamol" / "how much is paracetamol" */
    if ($kw === null || $kw === '' || !preg_match('/[a-z0-9]{3,}/', (string)$kw)) {
        foreach (['paracetamol', 'acetaminophen', 'amoxicillin', 'ibuprofen', 'aspirin', 'penicillin', 'tylenol', 'panadol', 'metformin', 'amoxiclav'] as $w) {
            if (strpos($msg, $w) !== false) { $kw = $w; break; }
        }
    }
    /* try "price of X" without medicine-suffix noise */
    if ($kw === null || $kw === '' || !preg_match('/[a-z0-9]{3,}/', (string)$kw)) {
        if (preg_match('/\b(?:price|cost|price of)\s+(?:of\s+)?([a-z][a-z0-9 ]*?)(?:\?|$)/', $msg, $m)) {
            $kw = trim($m[1]);
            $kw = trim(preg_replace('/\b(medicine|medicines|drug|drugs|at|from|in)\b.*$/i', '', (string)$kw));
        }
    }
    if ($kw === null || $kw === '' || !preg_match('/[a-z0-9]{3,}/', (string)$kw)) {
        $kw = null;
    }

    if ($kw !== null) {
        /* First: is this in our catalogue? */
        $med = tries(function () use ($pdo, $kw) {
            $like = "%$kw%";
            $st = $pdo->prepare("SELECT id, medicine_name, generic_name FROM medicines WHERE medicine_name LIKE ? OR generic_name LIKE ? LIMIT 3");
            $st->execute([$like, $like]);
            return $st->fetchAll();
        });

        if ($med) {
            /* Then: which approved pharmacies stock it (and at what price)? */
            $stock = tries(function () use ($pdo, $med) {
                $ids = array_map(fn($r) => (int)$r['id'], $med);
                $in  = implode(',', $ids);
                return $pdo->query(
                    "SELECT pm.price, pm.stock, p.pharmacy_name, p.city
                     FROM pharmacy_medicines pm
                     JOIN pharmacies p ON p.id = pm.pharmacy_id
                     WHERE pm.medicine_id IN ($in) AND p.status = 'approved'
                     ORDER BY p.pharmacy_name"
                )->fetchAll();
            });

            $name = $med[0]['medicine_name'];
            if ($stock && count($stock)) {
                $lines = [];
                foreach ($stock as $s) {
                    $price = $s['price'] !== null ? 'Rs ' . number_format((float)$s['price'], 0) . '/- ' : 'price not set ';
                    $lines[] = "• " . $s['pharmacy_name'] . ($s['city'] ? ' (' . $s['city'] . ')' : '') . " — " . $price . "· " . (int)$s['stock'] . " in stock";
                }
                respond(
                    "**" . $name . "** is available at:\n\n" . implode("\n\n", $lines) . "\n\n" .
                    "Want me to request it from one of these?",
                    [
                        q_action('Request medicine', 'request medicine', 'send'),
                        q_item('Browse catalogue', 'catalogue.php', 'capsule'),
                    ]
                );
            } else {
                respond(
                    "**" . $name . "** is in our catalogue, but no approved pharmacy has it listed as in stock right now.\n\n" .
                    "I can still request it for you — the pharmacy will sort out availability.",
                    [
                        q_action('Request medicine', 'request medicine', 'send'),
                        q_item('Browse catalogue', 'catalogue.php', 'capsule'),
                    ]
                );
            }
        } else {
            /* Not in catalogue — try the web */
            $online = tries(function () use ($kw) { return online_medicine_lookup($kw); });
            if ($online) {
                respond(
                    online_med_reply($online, $kw) . "\n\n" .
                    "ℹ️ Source: US FDA (OpenFDA). This medicine isn't in our catalogue, so it can't be ordered through MediConnect.",
                    [
                        q_item('Browse catalogue', 'catalogue.php', 'capsule'),
                        q_action('Find pharmacies', 'show pharmacy', 'shop'),
                        q_action('Request a medicine', 'request medicine', 'send'),
                    ]
                );
            }
            respond(
                "I couldn't find \"$kw\" in our catalogue or online just now. 🤔\n\n" .
                "Try the exact brand or generic name — or browse the catalogue.",
                [q_item('Browse catalogue', 'catalogue.php', 'capsule')]
            );
        }
    }
}

/* ============================================================
   MEDICINES / CATALOGUE (+ online details lookup)
============================================================ */
if (preg_match('/\b(medicine|medicines|drug|drugs|tablet|capsule|syrup|catalogue|paracetamol|acetaminophen|amoxicillin|amoxiclav|ibuprofen|aspirin|tylenol|panadol|metformin|penicillin|antibiotic|losartan|omeprazole|nurofen)\b/', $msg)
    && !preg_match('/\b(my (medicine )?requests?|request status|my orders)\b/', $msg)) {
    $kw = null;
    if (preg_match('/\b(?:for|search|find|about|details?\s+(?:of|on|about)?)\s+([a-z][a-z0-9 ]*?)(?:\?|$)/', $msg, $m)) {
        $kw = trim($m[1]);
        $kw = trim(preg_replace('/\b(medicine|medicines|drug|drugs|tablet|capsule|syrup|details?|about|info|information)\b.*$/i', '', (string)$kw));
    }
    if ($kw === '' || preg_match('/\b(catalogue|medicine|medicines|drug|drugs|tablet|capsule|syrup)\b/', (string)$kw)) $kw = null;

    $askDetails = (bool)preg_match('/\b(details?|about|information|info|tell me|what is|describe)\b/', $msg);

    /* If no keyword was pulled out, fall back to a known drug word in the message */
    if ($kw === null) {
        foreach (['paracetamol', 'acetaminophen', 'amoxicillin', 'ibuprofen', 'aspirin', 'penicillin', 'tylenol', 'panadol', 'metformin', 'amoxiclav'] as $w) {
            if (strpos($msg, $w) !== false) { $kw = $w; break; }
        }
    }

    /* ----- True keyword query (generic / brand name) ----- */
    if ($kw !== null) {
        $local = tries(function () use ($pdo, $kw) {
            $like = "%$kw%";
            $st = $pdo->prepare(
                "SELECT id, medicine_name, generic_name, category, description
                 FROM medicines
                 WHERE medicine_name LIKE ? OR generic_name LIKE ? OR category LIKE ?
                 ORDER BY medicine_name LIMIT 6"
            );
            $st->execute([$like, $like, $like]);
            return $st->fetchAll();
        }) ?: [];

        if (!$local) {
            /* Nothing in our catalogue — try the web (US FDA / OpenFDA) */
            $online = tries(function () use ($kw) { return online_medicine_lookup($kw); });
            if (!$online) {
                respond(
                    "I couldn't find \"$kw\" in our catalogue or online just now. 🤔\n\n" .
                    "Try:\n" .
                    "• the brand name (e.g. Tylenol)\n" .
                    "• the generic name (e.g. acetaminophen)\n" .
                    "• browsing our catalogue",
                    [q_item('Browse catalogue', 'catalogue.php', 'capsule')]
                );
            }
            respond(
                online_med_reply($online, $kw) . "\n\n" .
                "ℹ️ Source: US FDA (OpenFDA). This is general information for educational purposes only — it isn't tailored to you and doesn't replace a doctor or pharmacist.\n\n" .
                "This medicine isn't in our catalogue yet, so it can't be ordered through MediConnect right now.",
                [
                    q_item('Browse catalogue', 'catalogue.php', 'capsule'),
                    q_action('Find a pharmacy', 'show pharmacy', 'shop'),
                    q_action('Request a medicine', 'request medicine', 'send'),
                ]
            );
        }

        /* Local match found — honour explicit detail requests with the web too */
        if ($askDetails) {
            $online = tries(function () use ($kw) { return online_medicine_lookup($kw); });
            if ($online) {
                respond(
                    online_med_reply($online, $kw) . "\n\n" .
                    "ℹ️ Source: US FDA (OpenFDA). General info only — confirm dosages with your doctor or pharmacist.\n\n" .
                    "This medicine is in our catalogue, so you can request it from a pharmacy.",
                    [
                        q_action('Request this medicine', 'request medicine', 'send'),
                        q_item('Catalogue', 'catalogue.php', 'capsule'),
                    ]
                );
            }
        }

        /* ----- Local catalogue listing (default result) ----- */
        $lines = [];
        foreach ($local as $m) {
            $lines[] = "• " . $m['medicine_name'] .
                       ($m['generic_name'] ? ' (' . $m['generic_name'] . ')' : '') .
                       ($m['category']    ? ' — ' . $m['category']    : '');
        }
        respond(
            "Here's what I found in the catalogue for \"$kw\":\n\n" .
            implode("\n\n", $lines) . "\n\n" .
            "You can view details or request any of these from an approved pharmacy.",
            [
                q_action('Request one of these', 'request medicine', 'send'),
                q_item('Browse catalogue', 'catalogue.php', 'capsule'),
            ]
        );
    }

    /* ----- Generic browse (no keyword) ----- */
    $rows = tries(function () use ($pdo) {
        return $pdo->query(
            "SELECT id, medicine_name, generic_name, category FROM medicines ORDER BY medicine_name LIMIT 6"
        )->fetchAll();
    });
    if ($rows === null || !count($rows)) {
        respond(
            "There are no medicines in the catalogue right now. 💊\n\n" .
            "You can still ask me about any medicine (e.g. \"details about paracetamol\") and I'll look it up online.",
            [q_item('Open Medicines', 'catalogue.php', 'capsule')]
        );
    }
    $lines = [];
    foreach ($rows as $m) {
        $lines[] = "• " . $m['medicine_name'] .
                   ($m['generic_name'] ? ' (' . $m['generic_name'] . ')' : '') .
                   ($m['category']    ? ' — ' . $m['category']    : '');
    }
    respond(
        "Here are some medicines from our catalogue:\n\n" . implode("\n\n", $lines) . "\n\n" .
        "Type a specific name (e.g. \"paracetamol\") and I'll pull the details — or browse the full catalogue.",
        [
            q_action('Request a medicine', 'request medicine', 'send'),
            q_item('Browse catalogue', 'catalogue.php', 'capsule'),
        ]
    );
}

/* ============================================================
   MY APPOINTMENTS (view)
=========================================================== */
if (preg_match('/\b(my |)(appointment|appointments|booked|booking|schedule)\b/', $msg)) {
    /* Optional filter: "appointment with Dr X" / "appointment at <hospital>" */
    $filter = null;
    if (preg_match('/\b(?:with|at|about|for)\s+(?:dr\.?|doctor\s+)?([a-z][a-z0-9 .\-]{2,40}?)(?:\?|$)/i', $msg, $m)) {
        $f = trim($m[1]);
        $f = trim(preg_replace('/\b(appointment|appointments|booked|booking|schedule|please|with|at|about|for)\b.*$/i', '', (string)$f));
        if ($f !== '' && preg_match('/[a-z0-9]{2,}/i', $f)) $filter = $f;
    }
    $appts = tries(function () use ($pdo, $uid, $filter) {
        $sql = "SELECT a.*, h.hospital_name
                FROM appointments a
                LEFT JOIN hospitals h ON h.id = a.hospital_id
                WHERE a.user_id = ?";
        $params = [$uid];
        if ($filter !== null) {
            $sql .= " AND (a.doctor_name LIKE ? OR a.hospital_id = h.id AND h.hospital_name LIKE ?)";
            $like = "%$filter%";
            $params[] = $like; $params[] = $like;
        }
        $sql .= " ORDER BY a.appointment_date DESC LIMIT 5";
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    });
    if ($appts === null || !count($appts)) {
        if ($filter !== null) {
            respond(
                "I couldn't find an appointment" . ($filter !== null ? " for \"$filter\"" : '') . ".\n\n" .
                "Here are all your appointments — or book a new one.",
                [
                    q_action('Book an appointment', 'book appointment', 'calendar-plus'),
                    q_item('My appointments', 'appointments.php', 'calendar-check'),
                    q_item('Find doctors', 'doctors.php', 'person-badge'),
                ]
            );
        }
        respond(
            "You don't have any appointments yet. 📅\n\n" .
            "Would you like to book one? You can pick a hospital, a doctor, and a time that works for you.",
            [
                q_action('Book an appointment', 'book appointment', 'calendar-plus'),
                q_item('Find doctors', 'doctors.php', 'person-badge'),
            ]
        );
    } else {
        $lines = [];
        foreach ($appts as $a) {
            $lines[] = "• " . ($a['doctor_name'] ?? 'Doctor') .
                       ($a['hospital_name'] ? ' @ ' . $a['hospital_name'] : '') .
                       ' — ' . date('d M Y', strtotime($a['appointment_date'])) .
                       ' · ' . date('h:i A', strtotime($a['appointment_time'])) .
                       ' [' . ucfirst($a['status']) . ']';
        }
        respond(
            "Here are your recent appointments" . ($filter !== null ? " matching \"$filter\"" : '') . ":\n\n" . implode("\n\n", $lines) . "\n\n" .
            "You can manage them from the Appointments page — or cancel one right here.",
            [
                q_action('Cancel an appointment', 'cancel appointment', 'trash'),
                q_item('My appointments', 'appointments.php', 'calendar-check'),
                q_action('Book new', 'book appointment', 'calendar-plus'),
            ]
        );
    }
}

/* ============================================================
   MEDICINE REQUESTS (view)
=========================================================== */
if (preg_match('/\b(medicine request|medicine requests|my requests|my orders|request status)\b/', $msg)) {
    $reqs = tries(function () use ($pdo, $uid) {
        $sql = "SELECT mr.*, m.medicine_name, p.pharmacy_name
                FROM medicine_requests mr
                LEFT JOIN medicines m   ON m.id = mr.medicine_id
                LEFT JOIN pharmacies p  ON p.id = mr.pharmacy_id
                WHERE mr.user_id = ?
                ORDER BY mr.created_at DESC
                LIMIT 5";
        $st = $pdo->prepare($sql);
        $st->execute([$uid]);
        return $st->fetchAll();
    });
    if ($reqs === null || !count($reqs)) {
        respond(
            "You don't have any medicine requests yet. 📦\n\n" .
            "Browse the catalogue, pick a medicine, and request it from an approved pharmacy.",
            [q_item('Browse medicines', 'catalogue.php', 'capsule')]
        );
    } else {
        $lines = [];
        foreach ($reqs as $r) {
            $lines[] = "• " . ($r['medicine_name'] ?: 'Medicine') .
                       ($r['pharmacy_name'] ? ' @ ' . $r['pharmacy_name'] : '') .
                       ' × ' . (int)$r['quantity'] . ' — ' . ucfirst($r['status']);
        }
        respond(
            "Here are your recent medicine requests:\n\n" . implode("\n\n", $lines) . "\n\n" .
            "You can track each one from your dashboard — or cancel a pending one right here.",
            [
                q_action('Cancel a request', 'cancel request', 'trash'),
                q_item('Open dashboard', 'dashboard.php', 'speedometer2'),
            ]
        );
    }
}

/* ============================================================
   CONTACT / ABOUT
=========================================================== */
if (preg_match('/\b(contact|contact us|phone number|phone|email address|email|support|reach|help desk|about us|about this site|about the (site|app|website))\b/', $msg)) {
    respond(
        "You can reach us through the site:\n\n" .
        "• Your profile page holds your contact details\n" .
        "• Hospitals & pharmacies list their phone & email on their pages\n" .
        "• The main site has an About & Contact page\n\n" .
        "Is there something specific I can help you with?",
        [
            q_item('My profile', 'profile.php', 'person'),
            q_item('Contact us', '../contact.php', 'envelope'),
        ]
    );
}

/* ============================================================
   THANKS / GOODBYE
=========================================================== */
if (preg_match('/\b(thank|thanks|thx|ty)\b/', $msg)) {
    respond("You're welcome! 😊 Anything else I can help you with?", [
        q_action('Book appointment', 'book appointment', 'calendar-plus'),
        q_action('Request medicine', 'request medicine', 'capsule'),
    ]);
}
if (preg_match('/\b(bye|goodbye|see you|later)\b/', $msg)) {
    respond("Goodbye! 👋 Stay healthy — I'm here whenever you need me.", []);
}

/* ============================================================
   WHO ARE YOU / IDENTITY
============================================================ */
if (preg_match('/\b(who are you|who are u|your name|what is your name|what\'s your name|what are you|are you human|are you a (bot|robot|person)|are u a (bot|robot|person))\b/', $msg)) {
    respond(
        "I'm **MediConnect Assistant** 🤖 — the friendly AI helper built into MediConnect.\n\n" .
        "I can look up hospitals, doctors, medicines & pharmacies, and I can even do things for you right here: book appointments, request medicines, cancel bookings and check notifications.",
        [q_action('What can you do?', 'help', 'info-circle')]
    );
}

/* ============================================================
   HEALTH Q&A — straight to the generative model
   Catches symptom / general-health wording ("why do I feel
   dizzy?", "what is hypertension?") so it never wastes time on
   the drug-lookup fallback, and gets a genuinely useful answer.
============================================================ */
if (ai_available()
    && preg_match('/\b(symptom|symptoms|feel|feeling|pain|ache|aches|hurt|burns?|itch|itchy|nausea|vomit|fever|headache|migraine|dizzy|dizziness|tired|fatigue|exhausted|swollen|swelling|rash|sore|cough|cold|flu|allerg|diabet|sugar level|blood pressure|hypertension|anxiety|depress|stress|stomach|backache|back pain|insomnia|sleep|palpitation|chest|breathless|shortness of breath|diet|nutrition|vitamin|vaccin|pregnan|mood|heartburn|constipat|diarrh|dehydrat|infection|inflammat|arthritis|asthma|sinus|throat)\b/', $msg)
    && !preg_match('/\b(book|request|cancel|order|buy)\b/', $msg)) {
    $aiHealth = ai_chat_health($msg);
    if ($aiHealth !== null) {
        respond(
            $aiHealth . "\n\nℹ️ *MediConnect AI — general information only, not a diagnosis or medical advice. Please consult a doctor or pharmacist for anything serious or persistent.*",
            [
                q_action('📅 Book appointment', 'book appointment', 'calendar-plus'),
                q_action('💊 Request medicine', 'request medicine', 'capsule'),
                q_item('Browse medicines', 'catalogue.php', 'capsule'),
            ]
        );
    }
}

/* ============================================================
   LAST RESORT: online medicine lookup
   Catches "tell me about <drug>" and bare/short drug-like names
   (e.g. "sildenafil", "cetirizine") before the generic fallback.
============================================================ */
$detailPhrase = (bool)preg_match('/\b(what is|what\'s|tell me about|information (?:on|about)|details? (?:of|on|about)|describe|uses of|side effects of)\b/', $msg);

$cand = trim(preg_replace('/[^a-z0-9 ]/', ' ', $msg));
$cand = trim(preg_replace('/\b(what|whats|is|tell|me|about|information|on|of|details?|describe|uses|use|side|effects|the|a|an|please|medicine|medicines|drug|tablet|capsule)\b/', ' ', (string)$cand));
$cand = trim(preg_replace('/\s+/', ' ', (string)$cand));
$candWords = $cand === '' ? [] : explode(' ', $cand);
$stops = ['who','whom','whose','why','how','when','where','can','could','would','should','will','shall','does','do','did','are','am','was','were','be','been','being','you','your','my','mine','he','she','it','they','we','give','show','find','need','want','any','some','help','hi','hello','hey','ok','okay','yes','no','good','morning','evening','night','there','here','now','today','tomorrow','later','thanks','thank','stop','cancel','quit','exit','back','off','asap'];
$hasStop = false;
foreach ($candWords as $w) { if (in_array($w, $stops, true)) { $hasStop = true; break; } }
$drugLike = count($candWords) >= 1 && count($candWords) <= 2 && !$hasStop && strlen(implode('', $candWords)) >= 4;

if (($detailPhrase && $cand !== '') || $drugLike) {
    $localHit = tries(function () use ($pdo, $cand) {
        $like = "%$cand%";
        $st = $pdo->prepare("SELECT medicine_name, generic_name, category FROM medicines WHERE medicine_name LIKE ? OR generic_name LIKE ? LIMIT 3");
        $st->execute([$like, $like]);
        return $st->fetchAll();
    }) ?: [];
    $online = tries(function () use ($cand) { return online_medicine_lookup($cand); });

    if ($online || $localHit) {
        $parts = [];
        if ($online) $parts[] = online_med_reply($online, $cand);
        if ($localHit) {
            $ls = [];
            foreach ($localHit as $m) {
                $ls[] = "• " . $m['medicine_name'] .
                        ($m['generic_name'] ? ' (' . $m['generic_name'] . ')' : '') .
                        ($m['category']    ? ' — ' . $m['category']    : '');
            }
            $parts[] = "📦 This medicine is in our catalogue:\n" . implode("\n", $ls) . "\nYou can request it from an approved pharmacy.";
        } else {
            $parts[] = "This medicine isn't in our catalogue yet, so it can't be ordered through MediConnect right now.";
        }
        respond(
            implode("\n\n", $parts) . "\n\n" .
            "ℹ️ Source: US FDA (OpenFDA). General information only — it isn't medical advice. Confirm with a doctor or pharmacist.",
            [
                q_item('Browse catalogue', 'catalogue.php', 'capsule'),
                q_action('Request a medicine', 'request medicine', 'send'),
            ]
        );
    }
    if ($detailPhrase) {
        respond(
            "I couldn't find information on \"$cand\" online just now. 🤔\n\n" .
            "Try the exact brand or generic name, or browse our catalogue.",
            [q_item('Browse catalogue', 'catalogue.php', 'capsule')]
        );
    }
}

/* ============================================================
   GENERATIVE AI ROUTER (last line of defense)
   Reached only when every built-in rule above missed. The model
   decides in ONE call whether this is:
     • an ACTION request ("find me a doctor to see about my
       shoulder" → book)   → launches the matching flow, or
     • a CHAT / question   → answered directly with real
       MediConnect data as grounding.
   Falls through to the canned reply below if no key is set or
   the API fails.
============================================================ */
if (ai_available()) {
    $aiResult = ai_route($msg, $pdo, $uid);
    if ($aiResult !== null && !empty($aiResult['reply'])) {
        respond($aiResult['reply'], $aiResult['quick'] ?? []);
    }
}

/* ============================================================
   FALLBACK
============================================================ */
respond(
    "Hmm, I'm not sure about that one. 🤔\n\n" .
    "I can help you **find** hospitals, doctors, medicines & pharmacies, and **do** things like booking appointments, requesting medicines and cancelling bookings — all right here.\n\n" .
    "Try:\n" .
    "\"find hospitals in Lagos\"\n" .
    "\"book appointment\"\n" .
    "\"request medicine\"\n" .
    "\"my appointments\"",
    [
        q_action('📅 Book appointment', 'book appointment', 'calendar-plus'),
        q_action('💊 Request medicine', 'request medicine', 'capsule'),
        q_item('Hospitals',   'hospitals.php',   'hospital'),
        q_item('Doctors',     'doctors.php',     'person-badge'),
        q_item('Medicines',   'catalogue.php',   'capsule'),
        q_item('Pharmacies',  'pharmacies.php',  'shop'),
        q_item('Appointments','appointments.php','calendar-check'),
    ]
);