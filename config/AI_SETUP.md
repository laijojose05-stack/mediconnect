# MediConnect Assistant — Generative AI setup

The chatbot in `user/chatbot.php` has two layers:

1. **Smart actions** (built-in, always work): booking appointments, requesting
   medicines, cancelling bookings, uploading prescriptions, and database
   lookups (hospitals, doctors, medicines, pharmacies, prices).
2. **Generative AI**: open conversation and health Q&A, plus smart routing of
   anything the built-in rules can't understand.

The AI layer is optional — with no key configured, the chatbot still works
with its original fallback replies.

---

## Where the API key lives

`config/ai_config.php` is **safe to commit — it contains no secrets.** The key
is resolved at runtime, first match wins:

| Source | When to use it |
| --- | --- |
| `config/ai_config.local.php` | Local development (this file is git-ignored) |
| `AI_API_KEY` env var | Railway / server dashboards |
| `GEMINI_API_KEY` env var | alternate env name |

### Locally (XAMPP)

Create or edit `config/ai_config.local.php` with just this line:

```php
define('AI_API_KEY', 'your-api-key-here'); // ← git-ignored, never committed
```

### On Railway (or any server)

Set the environment variable `AI_API_KEY` on the service. No files to edit.

---

## Option 1 — Google Gemini (recommended, free tier)

1. Go to <https://aistudio.google.com/apikey> and sign in with a Google account.
2. Click **Create API key** → copy the key (starts with `AIza…` or `AQ.…`).
3. Put it in `config/ai_config.local.php` (local) or the `AI_API_KEY` env var (Railway).
4. Save, refresh the chat page, and ask something like
   *"why do I feel dizzy in the morning?"* or *"what can I do for a headache?"*

Model options (all free-tier friendly) — set via `AI_MODEL` env var or
`AI_MODEL` in the config:

| Model | Use when |
| --- | --- |
| `gemini-3.5-flash-lite` | cheapest / fastest replies |
| `gemini-3.6-flash` | good balance (default) |
| `gemini-3.8-flash` | best quality answers |

## Option 2 — OpenAI

1. Create a key at <https://platform.openai.com/api-keys> (billing required).
2. Set `AI_PROVIDER=openai` and `AI_MODEL=gpt-4o-mini` (env vars or config),
   and put your `sk-…` key in the same places as above.

Other tunables (env vars or config constants): `AI_TIMEOUT` (seconds),
`AI_ENABLED` (`false` disables the AI layer entirely).

---

## How it works (architecture)

```
User message
   │
   ├─ Built-in rules match? (book / med request / cancel / prescription / DB lookup)
   │     └─ run the existing action flow (always reliable)
   │
   ├─ Health question? (what is / how to / why do I feel / symptoms…)
   │     └─ generative AI, grounded with real DB context (medicines, nearby doctors)
   │
   └─ Anything else → generative AI classifier:
          ─ classifies into an action intent → starts that flow
          ─ otherwise → answers as a free-form chat (with a short disclaimer)
```

**Safety rules:** messages are redacted (phones / emails / long digit runs) before
any external call; only the last ~3 chat turns are sent; the AI never writes to
the database directly and never sees passwords.