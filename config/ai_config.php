<?php
/* ============================================================
   config/ai_config.php — Generative AI settings for the chatbot
   ------------------------------------------------------------
   SAFE TO COMMIT — no secrets live in this file.

   Where the API key comes from (first match wins):
     1. config/ai_config.local.php   ← git-ignored, for local use
     2. AI_API_KEY env var           ← Railway / server dashboards
     3. GEMINI_API_KEY env var       ← alternate env name

   Providers:
     • google  — Gemini API (recommended: free tier)
     • openai  — OpenAI Chat Completions (requires billing)
============================================================ */

/* Optional local-only secret override (git-ignored) */
if (is_file(__DIR__ . '/ai_config.local.php')) {
    require_once __DIR__ . '/ai_config.local.php';
}

if (!defined('AI_API_KEY')) {
    $mcKey = getenv('AI_API_KEY') ?: getenv('GEMINI_API_KEY');
    define('AI_API_KEY', is_string($mcKey) && $mcKey !== '' ? $mcKey : '');
}
if (!defined('AI_PROVIDER')) {
    define('AI_PROVIDER', getenv('AI_PROVIDER') ?: 'google');
}
if (!defined('AI_MODEL')) {
    define('AI_MODEL', getenv('AI_MODEL') ?: 'gemini-3.6-flash');
}
if (!defined('AI_TIMEOUT')) {
    define('AI_TIMEOUT', (int)(getenv('AI_TIMEOUT') ?: 30));
}
if (!defined('AI_ENABLED')) {
    $mcEnabled = getenv('AI_ENABLED');
    define('AI_ENABLED', $mcEnabled === false || strtolower((string)$mcEnabled) !== 'false');
}
?>