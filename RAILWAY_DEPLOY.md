# UGAT TrainTrack — Railway Deployment Guide

Complete step-by-step instructions for deploying this PHP + MySQL app to Railway.

---

## Prerequisites

Before you start, make sure you have:

- [ ] A [Railway account](https://railway.app) (free tier works)
- [ ] [Git](https://git-scm.com/) installed on your machine
- [ ] A GitHub account (Railway deploys from GitHub)
- [ ] Your Gmail App Password ready (for email notifications)
- [ ] Your UniSMS / Semaphore API key (for SMS)
- [ ] Your PayMongo keys (for payments)

---

## Step 1 — Push the Project to GitHub

Railway deploys directly from a GitHub repository.

1. Go to [github.com/new](https://github.com/new) and create a **new private repository** named `ugat-traintrack`.

2. Open a terminal in the project root (`ugat (2)/`) and run:

```bash
git init
git add .
git commit -m "Initial commit"
git remote add origin https://github.com/YOUR_USERNAME/ugat-traintrack.git
git branch -M main
git push -u origin main
```

> **Note:** The `Dockerfile` must be at the root of the repository (same level as the `ugat/` folder). It is already placed there.

---

## Step 2 — Create a New Railway Project

1. Log in to [railway.app](https://railway.app).
2. Click **New Project**.
3. Select **Deploy from GitHub repo**.
4. Authorize Railway to access your GitHub account if prompted.
5. Find and select the `ugat-traintrack` repository.
6. Railway will detect the `Dockerfile` automatically and queue a build. **Do not configure variables yet — do that in Step 4.**

---

## Step 3 — Add a MySQL Database

1. Inside your Railway project, click **+ New** (top right).
2. Select **Database → Add MySQL**.
3. Railway will provision a MySQL 8 instance and automatically inject these variables into your project:
   - `MYSQLHOST`
   - `MYSQLUSER`
   - `MYSQLPASSWORD`
   - `MYSQLDATABASE`
   - `MYSQLPORT`

   Your `config/db.php` already reads these — no manual changes needed.

4. Click on the MySQL service, then go to the **Data** tab (or **Connect** tab).
5. Click **Query** or open the MySQL shell and import your schema:

### Option A — Railway MySQL Shell (browser)
1. In the MySQL service, click the **Data** tab → **Query**.
2. Paste the contents of `ugat/ugat_db (3).sql` and run it.
3. Then paste and run `ugat/paymongo_migration.sql`.

### Option B — MySQL Workbench or TablePlus (local tool)
1. In the MySQL service, go to **Connect** and copy the connection string or individual credentials.
2. Open MySQL Workbench → New Connection → fill in the host, port, user, password, and database from Railway.
3. Run `ugat/ugat_db (3).sql` → then run `ugat/paymongo_migration.sql`.

### Option C — MySQL CLI
```bash
mysql -h RAILWAY_HOST -P RAILWAY_PORT -u RAILWAY_USER -p RAILWAY_DATABASE < "ugat/ugat_db (3).sql"
mysql -h RAILWAY_HOST -P RAILWAY_PORT -u RAILWAY_USER -p RAILWAY_DATABASE < ugat/paymongo_migration.sql
```
Replace `RAILWAY_HOST`, etc. with the values from the Railway MySQL **Connect** tab.

---

## Step 4 — Set Environment Variables

1. Click on your **web service** (the PHP app, not the database) in the Railway dashboard.
2. Go to the **Variables** tab.
3. Click **+ New Variable** and add each of the following:

### Required Variables

| Variable | Value | Notes |
|----------|-------|-------|
| `APP_URL` | `https://your-app.up.railway.app` | Replace with your actual Railway URL (get it after first deploy) |
| `GMAIL_ADDRESS` | `your-gmail@gmail.com` | The Gmail account used to send emails |
| `GMAIL_APP_PASSWORD` | `xxxx xxxx xxxx xxxx` | 16-char Google App Password (see below) |
| `EMAIL_FROM_NAME` | `UGAT Notifications` | Sender display name |
| `EMAIL_FROM_ADDRESS` | `your-gmail@gmail.com` | Same as GMAIL_ADDRESS |
| `PAYMONGO_SECRET_KEY` | `sk_test_...` | From PayMongo dashboard |
| `PAYMONGO_WEBHOOK_SECRET` | `whsk_...` | From PayMongo webhook settings |

### Optional Variables (SMS)

| Variable | Value |
|----------|-------|
| `UNISMS_API_KEY` | Your UniSMS API key |
| `SEMAPHORE_API_KEY` | Your Semaphore API key |
| `SMS_ENABLED` | `true` or `false` |

> **Tip:** You can also click **RAW Editor** in Railway and paste all variables at once in `KEY=VALUE` format.

### How to get a Gmail App Password
1. Go to your Google account → **Security** → **2-Step Verification** (must be enabled).
2. Search for **App Passwords** at the bottom of the Security page.
3. Select app: **Mail**, device: **Other (Custom name)** → type `UGAT`.
4. Copy the 16-character password shown — paste it as `GMAIL_APP_PASSWORD`.

---

## Step 5 — Get Your App URL and Update APP_URL

1. After the first deploy succeeds, click your web service in Railway.
2. Go to **Settings → Domains** and click **Generate Domain**.
3. Copy the URL (e.g., `https://ugat-traintrack-production.up.railway.app`).
4. Go back to **Variables** and update `APP_URL` to this URL.
5. This is used by PayMongo webhooks to redirect payments back to your app.

---

## Step 6 — Verify the Deployment

1. Open your Railway URL in a browser — you should see the UGAT landing page.
2. Try logging in with an admin account (seeded from the SQL dump).
3. Check that the admin dashboard loads without errors.
4. Test a trainee login.

### Default Admin Credentials (from SQL seed data)
Check the `users` table in your database for the seeded admin account, or look for `INSERT INTO users` in `ugat_db (3).sql` for credentials.

---

## Step 7 — Configure PayMongo Webhook (for GCash Payments)

1. Log in to [dashboard.paymongo.com](https://dashboard.paymongo.com).
2. Go to **Developers → Webhooks → Add Endpoint**.
3. Set the URL to:  
   `https://your-app.up.railway.app/pages/admin/paymongo_webhook.php`
4. Select events: `payment.paid`, `payment.failed`.
5. Copy the **Webhook Secret** and set it as `PAYMONGO_WEBHOOK_SECRET` in Railway variables.

---

## File Structure After Deployment

```
repository root/
├── Dockerfile              ← Railway builds from this
├── .env.example            ← Template (do NOT commit real .env)
├── RAILWAY_DEPLOY.md       ← This guide
└── ugat/                   ← PHP application (served as web root)
    ├── config/
    │   ├── db.php          ← Reads MYSQL* env vars
    │   ├── email.php       ← Reads GMAIL_* env vars
    │   ├── sms.php         ← Reads UNISMS_* / SEMAPHORE_* env vars
    │   └── paymongo.php    ← Reads PAYMONGO_* env vars
    ├── pages/
    ├── uploads/            ← WARNING: ephemeral storage (see note below)
    └── ugat_db (3).sql     ← Import this into Railway MySQL
```

---

## Important Notes

### Uploads / File Storage (avatars, receipts, inventory images)
Railway uses **ephemeral storage** — files uploaded to `uploads/` are deleted every time the app redeploys. For production:
- Use [Cloudflare R2](https://developers.cloudflare.com/r2/) (free 10 GB/month) or AWS S3.
- For now, uploads will work but will reset on each deploy. This is fine for testing.

### PHP Sessions
Sessions are stored on the server filesystem by default — this works on Railway since only one instance runs. If you scale to multiple instances in the future, switch to database-backed sessions.

### Logs
View real-time logs in Railway: click your web service → **Logs** tab. PHP errors are written to `stderr` (captured by Railway automatically).

---

## Troubleshooting

| Problem | Solution |
|---------|----------|
| Build fails with "mysqli not found" | The Dockerfile installs `mysqli` — check if the build log shows an error during `docker-php-ext-install` |
| White screen / 500 error | Check Railway Logs tab for PHP fatal errors |
| "Database connection error" | Confirm MySQL service is running; check the `MYSQL*` variables are set on the **web service** (not just the DB service) |
| Emails not sending | Verify `GMAIL_APP_PASSWORD` is correct and 2FA is enabled on the Gmail account |
| PayMongo redirects to wrong URL | Update `APP_URL` to your Railway domain |
| 403 Forbidden on uploads | The Dockerfile sets `chmod 775` on uploads; re-deploy if this happens |
| CSS/JS not loading | Check browser console — paths should be relative; clear browser cache |

---

## Redeploying After Code Changes

Any push to your GitHub `main` branch will automatically trigger a new Railway build and deploy.

```bash
git add .
git commit -m "Your change description"
git push
```

Railway will rebuild the Docker image and swap the container with zero-downtime.

---

## Local Development

To run the app locally without Railway:

1. Install XAMPP or Laragon.
2. Copy `ugat/` into your `htdocs/` folder.
3. Import `ugat/ugat_db (3).sql` into your local MySQL.
4. Open `http://localhost/ugat/pages/landing/Landing.html`.

The config files fall back to `localhost` defaults when environment variables are not set.
