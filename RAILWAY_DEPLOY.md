# Deploy MediConnect to Railway

This project ships with everything needed to run on Railway: a `Dockerfile`
(PHP 8.2 + Apache + MySQL extensions), a `docker/startup.sh` entrypoint, and an
idempotent schema migrator (`docker/migrate.php`) that imports `database/database.sql`
on first boot.

## 0. Prerequisites

- The project pushed to a GitHub repository (see `git push` steps below).
- A Railway account (<https://railway.app>).

## 1. Push the code to GitHub

```bash
# in the project folder (C:\xampp\htdocs\mediconnect)
git init
git add .
git commit -m "MediConnect: rule-based chatbot + generative AI layer, deployable to Railway"

# create the repo on github.com first (New repository), then:
git remote add origin https://github.com/YOUR_USERNAME/mediconnect.git
git push -u origin main
```

> 🔒 **Secrets are already protected** — `.gitignore` blocks
> `config/ai_config.local.php` (Gemini key) and `scripts/.openfda.key`, and
> `config/ai_config.php` + `config/database.php` are now env-var driven with no
> secrets inside. Verify with `git status` before pushing.

## 2. Create the Railway project

1. Go to <https://railway.app/new> and sign in.
2. **Deploy from GitHub repo** → pick `mediconnect`.
   Railway auto-detects the `Dockerfile` (PHP + Apache image).
3. Wait for the first build (a few minutes).

## 3. Add MySQL

1. In the project, click **New** → **Database** → **MySQL**.
2. Wait until provisioning finishes.
3. The app service and the MySQL database are linked in the same project, so
   Railway injects `MYSQLHOST`, `MYSQLPORT`, `MYSQLUSER`, `MYSQLPASSWORD`,
   `MYSQLDATABASE` (and `MYSQL_URL`) into the app automatically. No manual
   wiring needed.

## 4. Set the AI key

1. Open the app service → **Variables**.
2. Add `AI_API_KEY` = your Gemini key (from <https://aistudio.google.com/apikey>).
   Optional: `AI_MODEL` (default `gemini-3.6-flash`).

## 5. Start it up

1. Deploy (or Railway redeploys on push).
2. On first boot, `docker/startup.sh` waits for MySQL and imports the schema
   (it skips import if tables already exist — safe on redeploys).
3. Open the service's public domain: **Settings → Networking → Generate Domain**
   (or copy the `*.up.railway.app` URL). You'll see the MediConnect home page.

Seeded admin login (imported from `database/database.sql`):
`admin@mediconnect.com` — check the dump for its hashed password, or simply
register a new user/use the demo data.

## Notes & limits

- **Uploads are ephemeral.** `user/uploads/` lives on the container's local
  disk, which resets on every redeploy. Fine for a demo — prescription
  uploads won't survive redeploys. To persist them, move uploads to the
  database or object storage (future work).
- **Default MySQL credentials in `config/database.php`** are localhost-only;
  on Railway the env vars always take precedence.
- **openFDA sync** (`scripts/sync_medicines.php`): works anonymously; or set
  `OPENFDA_API_KEY` as a variable. The seed data already includes 600+
  medicines, so it's not required.
- **Scale:** keep 1 replica of the app service (sessions use local files).
- **Custom domain:** Settings → Networking → Custom Domain.

## Troubleshooting

**"Database Connection Failed" / `migrate.php` connecting to `localhost/root`**
The container resolves the DB purely from env vars. Make sure the MySQL
service is **linked to the app service** (or add the variables manually under
**Variables**). Supported names (any of these, first match wins):

| Group | Variable names |
| --- | --- |
| URL form | `MYSQL_URL`, `DATABASE_URL` → `mysql://user:pass@host:port/db` |
| Individual | `MYSQLHOST` / `MYSQL_HOST`, `MYSQLPORT` / `MYSQL_PORT`, `MYSQLUSER` / `MYSQL_USER`, `MYSQLPASSWORD` / `MYSQL_PASSWORD`, `MYSQLDATABASE` / `MYSQL_DATABASE` |

Migration is idempotent and never fatal: if the DB is unreachable at boot the
web server still starts; fix the variables and redeploy (or restart) — the
schema imports automatically on the next boot.

**"More than one MPM loaded" (AH00534)**
Fixed in the image build (only `mpm_prefork` remains enabled). If you see it
again on a fresh deployment, redeploy — the `Dockerfile` disables `mpm_event`
and `mpm_worker`.

**Site loads but Railway reports the service unhealthy / 502**
The container now listens on Railway's `$PORT` (default 80). If you previously
added a `PORT` variable or a custom Health Check Path, confirm it points at a
real route (e.g. `/` or `/index.php`).