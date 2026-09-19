<?php
/* ============================================================
   config/ai_client.php — Generative AI client for MediConnect
   ------------------------------------------------------------
   Small, dependency-free PHP client for the chatbot's AI layer.
   Talks to either the Google Gemini API or the OpenAI Chat
   Completions API over cURL (no SDKs needed).

   Public functions used by user/chatbot_api.php:
     ai_available()              → true if a key is configured
     ai_route($msg, $pdo, $uid)  → decides: start an action flow
                                   OR return a direct AI reply
                                   (array ['reply','quick'] | null)
     ai_chat_health($msg)       → direct health-Q&A reply (string|null)

   Privacy: user messages are redacted (emails / phone numbers
   removed) before they are sent to the AI provider, and only the
   last few chat turns are ever shared.
============================================================ */

require_once __DIR__ . '/ai_config.php';

/* ------------------------------------------------------------
   Availability
------------------------------------------------------------ */
function ai_available(): bool {
    if (!defined('AI_ENABLED') || !AI_ENABLED) return false;
    return defined('AI_API_KEY') && is_string(AI_API_KEY) && trim(AI_API_KEY) !== '';
}

function ai_provider(): string {
    $p = defined('AI_PROVIDER') ? strtolower((string)AI_PROVIDER) : 'google';
    return in_array($p, ['google', 'openai'], true) ? $p : 'google';
}

function ai_model(): string {
    return defined('AI_MODEL') && is_string(AI_MODEL) && AI_MODEL !== '' ? AI_MODEL : 'gemini-3.6-flash';
}

function ai_timeout(): int {
    return defined('AI_TIMEOUT') ? max(0, (int)AI_TIMEOUT) : 30;
}

/* ------------------------------------------------------------
   PII redaction — never send personal data to the AI provider
------------------------------------------------------------ */
function ai_redact(string $msg): string {
    // Emails
    $msg = preg_replace('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', '[email hidden]', $msg);
    // Phone numbers (+123..., 07xx..., sequences of 7+ digits with separators)
    $msg = preg_replace('/\+?[0-9][0-9 \-\\.()]{6,}[0-9]/i', '[phone hidden]', $msg);
    // Long runs of digits (IDs / account numbers)
    $msg = preg_replace('/\b[0-9]{9,}\b/', '[number hidden]', $msg);
    return trim((string)$msg);
}

/* ------------------------------------------------------------
   HTTP POST helper (JSON in / JSON out)
------------------------------------------------------------ */
function ai_http_post(string $url, string $json, int $timeout): ?array {
    if (!function_exists('curl_init')) return null;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout > 0 ? $timeout : 60,
        CURLOPT_CONNECTTIMEOUT => $timeout > 0 ? min(10, $timeout) : 10,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $json,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'MediConnect-Assistant/1.0',
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($body === false || $code < 200 || $code >= 300) return null;
    $j = json_decode($body, true);
    return is_array($j) ? $j : null;
}

/* ------------------------------------------------------------
   Provider adapters — messages are OpenAI-format:
   [ { role: system|user|assistant, content: string } ]
------------------------------------------------------------ */
function ai_gemini_complete(array $messages, bool $jsonMode): ?string {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
         . rawurlencode(ai_model())
         . ':generateContent?key=' . rawurlencode(AI_API_KEY);

    $system    = '';
    $contents  = [];
    foreach ($messages as $m) {
        $role = (string)($m['role'] ?? 'user');
        $text = (string)($m['content'] ?? '');
        if ($text === '') continue;
        if ($role === 'system') {
            $system .= ($system === '' ? '' : "\n\n") . $text;
            continue;
        }
        $contents[] = ['role' => ($role === 'assistant' ? 'model' : 'user'), 'parts' => [['text' => $text]]];
    }
    if (!$contents) return null;

    $payload = ['contents' => $contents];
    if ($system !== '') $payload['systemInstruction'] = ['parts' => [['text' => $system]]];
    if ($jsonMode) $payload['generationConfig'] = ['responseMimeType' => 'application/json'];

    $resp = ai_http_post($url, json_encode($payload), ai_timeout());
    if ($resp === null) return null;
    $text = $resp['candidates'][0]['content']['parts'][0]['text'] ?? null;
    return is_string($text) && trim($text) !== '' ? trim($text) : null;
}

function ai_openai_complete(array $messages, bool $jsonMode): ?string {
    $payload = [
        'model'       => ai_model(),
        'messages'    => [],
        'temperature' => 0.4,
    ];
    foreach ($messages as $m) {
        $role = (string)($m['role'] ?? 'user');
        $text = (string)($m['content'] ?? '');
        if ($text === '') continue;
        $payload['messages'][] = ['role' => $role, 'content' => $text];
    }
    if (empty($payload['messages'])) return null;
    // OpenAI requires the word "json" in the prompt for json_object mode.
    if ($jsonMode) $payload['response_format'] = ['type' => 'json_object'];

    $url = 'https://api.openai.com/v1/chat/completions';
    if (!function_exists('curl_init')) return null;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => max(1, ai_timeout()),
        CURLOPT_CONNECTTIMEOUT => min(10, max(1, ai_timeout())),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . AI_API_KEY,
        ],
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'MediConnect-Assistant/1.0',
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $code < 200 || $code >= 300) return null;
    $resp = json_decode($body, true);
    if (!is_array($resp)) return null;
    $text = $resp['choices'][0]['message']['content'] ?? null;
    return is_string($text) && trim($text) !== '' ? trim($text) : null;
}

/** Send a completion; returns raw text or null on any failure. */
function ai_complete(array $messages, bool $jsonMode = false): ?string {
    return ai_provider() === 'openai'
        ? ai_openai_complete($messages, $jsonMode)
        : ai_gemini_complete($messages, $jsonMode);
}

/** Request structured (JSON) output and decode it. */
function ai_json(array $messages): ?array {
    $txt = ai_complete($messages, true);
    if ($txt === null) return null;
    $j = json_decode($txt, true);
    if (is_array($j)) return $j;
    // Robust extraction if the model wrapped the JSON in fences/prose.
    if (preg_match('/\{.*\}/s', $txt, $mm)) {
        $j = json_decode($mm[0], true);
        if (is_array($j)) return $j;
    }
    return null;
}

/* ------------------------------------------------------------
   Grounding context — a compact snapshot of real app data so
   the model can answer about actual hospitals/doctors/pharmacies/
   medicines from MediConnect itself. Cached 2 minutes/session.
------------------------------------------------------------ */
function ai_static_context(PDO $pdo): string {
    if (isset($_SESSION['ai_ctx_cache'], $_SESSION['ai_ctx_ts'])
        && is_string($_SESSION['ai_ctx_cache'])
        && (time() - (int)$_SESSION['ai_ctx_ts']) < 120) {
        return $_SESSION['ai_ctx_cache'];
    }

    $lines = [];
    $try = static function (callable $fn) {
        try { return $fn(); } catch (Throwable $e) { return null; }
    };

    $hospitals = $try(static function () use ($pdo) {
        return $pdo->query("SELECT hospital_name, city FROM hospitals WHERE status='approved' ORDER BY hospital_name LIMIT 6")->fetchAll();
    });
    foreach ((array)$hospitals as $h) {
        $lines[] = 'Hospital: ' . $h['hospital_name'] . ($h['city'] ? ' (' . $h['city'] . ')' : '');
    }

    $doctors = $try(static function () use ($pdo) {
        return $pdo->query("SELECT doctor_name, specialization FROM doctors ORDER BY doctor_name LIMIT 6")->fetchAll();
    });
    foreach ((array)$doctors as $d) {
        $lines[] = 'Doctor: Dr. ' . $d['doctor_name'] . ($d['specialization'] ? ' — ' . $d['specialization'] : '');
    }

    $pharmacies = $try(static function () use ($pdo) {
        return $pdo->query("SELECT pharmacy_name, city FROM pharmacies WHERE status='approved' ORDER BY pharmacy_name LIMIT 4")->fetchAll();
    });
    foreach ((array)$pharmacies as $p) {
        $lines[] = 'Pharmacy: ' . $p['pharmacy_name'] . ($p['city'] ? ' (' . $p['city'] . ')' : '');
    }

    $medicines = $try(static function () use ($pdo) {
        return $pdo->query("SELECT medicine_name, generic_name FROM medicines ORDER BY medicine_name LIMIT 8")->fetchAll();
    });
    foreach ((array)$medicines as $m) {
        $lines[] = 'Medicine: ' . $m['medicine_name'] . ($m['generic_name'] ? ' (' . $m['generic_name'] . ')' : '');
    }

    $txt = $lines ? "Current MediConnect data (from the live database):\n" . implode("\n", $lines) : '';
    $_SESSION['ai_ctx_cache'] = $txt;
    $_SESSION['ai_ctx_ts']    = time();
    return $txt;
}

/* ------------------------------------------------------------
   System prompt for classification + chat
------------------------------------------------------------ */
function ai_system_prompt(PDO $pdo): string {
    return
        "You are MediConnect Assistant, the friendly AI built into the MediConnect health platform. " .
        "You help logged-in users with health services: finding hospitals, doctors, medicines and pharmacies, " .
        "booking appointments, requesting medicines, uploading prescriptions, and answering general health questions.\n\n" .

        "RULES\n" .
        "1. If the user wants to DO something in the app (book or cancel an appointment, request or cancel a medicine, " .
        "add/upload a prescription), set intent to the matching ACTION and leave \"reply\" empty — the app itself will " .
        "run that feature.\n" .
        "2. Otherwise set intent to \"chat\" and write a helpful, accurate, friendly reply in plain, simple language. " .
        "Use short paragraphs or a short bullet list (markdown bullets with \"-\"). You may use the MediConnect data " .
        "provided below to answer questions about local hospitals, doctors, medicines and pharmacies.\n" .
        "3. For medical / health questions, give general educational information only (common causes, general advice, " .
        "when to see a doctor) and be clear it's general information, not a diagnosis.\n" .
        "4. Never invent medicines, dosages, prices, facilities or doctors that are not in the MediConnect data provided.\n" .
        "5. Never ask the user to share private health records or personal details in chat.\n" .
        "6. Keep replies brief (1–3 short paragraphs). If the user is changing the subject, follow them.\n" .
        "7. Earlier messages below marked [system] were answered by the app's quick-action features — ignore them if " .
        "they are not relevant to the user's current question.\n\n" .

        "AVAILABLE ACTIONS\n" .
        "- book: book an appointment (hospital → doctor → date → time)\n" .
        "- med: request a medicine from a pharmacy\n" .
        "- presc: upload a prescription and get medicine details\n" .
        "- cancel_appt: cancel an existing appointment\n" .
        "- cancel_req: cancel a pending medicine request\n\n" .

        ai_static_context($pdo) . "\n\n" .

        "Reply with JSON only, in exactly this shape (no prose, no code fences):\n" .
        '{"intent":"chat","reply":"your answer here"}' . "\n" .
        "where intent is one of: chat, book, med, presc, cancel_appt, cancel_req.";
}

/* ------------------------------------------------------------
   Recent chat history (last few turns, already redacted).
   History lines marked [system] may be app-generated replies.
------------------------------------------------------------ */
function ai_history(): array {
    $hist = $_SESSION['ai_history'] ?? [];
    if (!is_array($hist)) $hist = [];
    return array_slice($hist, -6); // last ~3 turns
}

function ai_push_history(string $role, string $content): void {
    $hist   = ai_history();
    $hist[] = ['role' => $role, 'content' => $content];
    $_SESSION['ai_history'] = array_slice($hist, -12);
}

/* ------------------------------------------------------------
   MAIN ENTRY POINT — route a message through the AI.
   Returns the final reply as ['reply','quick'] when the AI
   should answer directly, or null when:
     • the AI is not configured, or
     • the API call failed (caller shows its normal fallback).

   When the model chooses an ACTION, the matching flow starter is
   launched (those functions reply + exit on their own).
------------------------------------------------------------ */
function ai_route(string $msg, PDO $pdo, int $uid): ?array {
    if (!ai_available()) return null;

    $redacted = ai_redact($msg);
    if ($redacted === '') return null;

    // Build the request: system prompt + recent history + current message.
    $messages = [['role' => 'system', 'content' => ai_system_prompt($pdo)]];
    foreach (ai_history() as $turn) {
        if (!is_array($turn)) continue;
        $role = ($turn['role'] ?? 'user') === 'model' ? 'assistant' : 'user';
        $text = is_string($turn['content'] ?? null) ? $turn['content'] : '';
        if ($text === '') continue;
        $messages[] = ['role' => $role, 'content' => ($role === 'user' ? '' : '[system] ') . $text];
    }
    $messages[] = ['role' => 'user', 'content' => $redacted];

    $json = ai_json($messages);
    if ($json === null) return null;

    $intent = (string)($json['intent'] ?? 'chat');
    $reply  = trim((string)($json['reply'] ?? ''));

    switch ($intent) {
        case 'book':        start_book_flow($pdo);         break; // replies + exits
        case 'med':         start_med_flow($pdo);          break;
        case 'presc':       start_presc_flow();            break;
        case 'cancel_appt': start_cancel_appt_flow($pdo, $uid); break;
        case 'cancel_req':  start_cancel_req_flow($pdo, $uid);  break;
        default: /* chat */
            break;
    }

    if ($reply === '') return null; // action handled above, no chat text

    ai_push_history('user', $redacted);
    ai_push_history('model', $reply);

    return [
        'reply' => $reply . "\n\n"
                 . "ℹ️ *MediConnect AI — general information only, not a diagnosis. "
                 . "Please confirm anything important with a doctor or pharmacist.*",
        'quick' => [
            ['label' => '📅 Book appointment', 'msg' => 'book appointment', 'icon' => 'calendar-plus'],
            ['label' => '💊 Request medicine', 'msg' => 'request medicine',  'icon' => 'capsule'],
            ['label' => '🗑 Cancel appointment', 'msg' => 'cancel appointment', 'icon' => 'trash'],
        ],
    ];
}

/* ------------------------------------------------------------
   Direct health-Q&A helper used for obvious symptom questions.
   One call, no classification step. Returns reply text or null.
------------------------------------------------------------ */
function ai_chat_health(string $msg): ?string {
    if (!ai_available()) return null;
    $redacted = ai_redact($msg);
    if ($redacted === '') return null;

    $system =
        "You are MediConnect Assistant, a supportive health-information assistant.\n\n" .
        "Answer the user's question with general, accurate health information in plain, simple language: " .
        "likely/common reasons, sensible self-care tips, and clear signals for when to see a doctor " .
        "(e.g. high fever, worsening pain, breathing trouble, chest pain, confusion). " .
        "Do NOT diagnose, do NOT invent medicines or dosages, and do NOT ask for personal health records. " .
        "Keep it to 2–4 short paragraphs or a short bullet list. End by noting it's general information, " .
        "not medical advice. Reply in the same language the user writes in.";

    $messages = [
        ['role' => 'system', 'content' => $system],
        ['role' => 'user',   'content' => $redacted],
    ];
    $text = ai_complete($messages, false);
    if (!is_string($text) || trim($text) === '') return null;

    ai_push_history('user', $redacted);
    ai_push_history('model', $text);
    return trim($text);
}