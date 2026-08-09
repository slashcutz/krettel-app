# Deploying krettel-app to Wasmer Edge (free, no credit card)

Free tier: 100k requests/mo, 150 GB bandwidth, 1 app, 1 GB persistent volume.

## 1. Install the Wasmer CLI

Windows:
```
winget install wasmer
```
Or from https://wasmer.io/install (other OSes).

## 2. Login

```
wasmer login
```

## 3. Edit `app.yaml`

- Confirm `owner: slashcutz_official` and `name: krettel-app` match your Wasmer
  account/app (this repo deploys to `https://krettel-app.wasmer.app`).
- Update `APP_URL` if your app name differs.

## 4. Set secrets

The `deploy/wasmer/set-secrets.ps1` (Windows) / `set-secrets.sh` (bash) scripts
create all secrets for you with the current account values:

```
./deploy/wasmer/set-secrets.ps1   # Windows PowerShell
bash deploy/wasmer/set-secrets.sh # macOS / Linux
```

Or create them manually (these are *not* in `app.yaml`):

```
php artisan key:generate --show
```
```
wasmer app secret create APP_KEY <generated-key>
wasmer app secret create PIXELDRAIN_BASE_URL https://pixeldrain.net
wasmer app secret create PIXELDRAIN_API_KEY <your-pixeldrain-key>
wasmer app secret create PIXELDRAIN_EMAIL <email>
wasmer app secret create PIXELDRAIN_PASSWORD <password>
wasmer app secret create PIXELDRAIN_STREAM_MODE redirect
wasmer app secret create TERABOX_EMAIL <email>
wasmer app secret create TERABOX_PASSWORD <password>
wasmer app secret create TERABOX_NDUS <ndus-cookie>   # optional
wasmer app secret create TERABOX_REMOTE_DIR /Apps/Krettel
wasmer app secret create TERABOX_WEB_HOST https://www.1024terabox.com
```

Note: secrets are write-once — to change a value, `wasmer app secret delete <name>`
first, then create it again.

## 5. Deploy

```
wasmer deploy
```

Wait for the deployment to become ready, then open the URL
(`https://krettel-app.wasmer.app`). The setup jobs run automatically on
deploy: clear stale caches, run migrations + settings seeder, link storage,
seed the admin.

## 6. Redeploy after code changes

```
wasmer deploy
```

## What works / what doesn't

Works:
- Video uploads → pushed to Pixeldrain by the queue job (runs every 5 min)
- Original video playback + seeking via a 307 redirect to the Pixeldrain CDN
  (video bytes never pass through Wasmer — saves the free bandwidth quota)
- Subtitles already stored as files on Pixeldrain
- Login, admin, categories, playlists, watch history
- `db:seed --class=SettingsSeeder` populates Pixeldrain/TeraBox keys from
  environment variables into the settings table (runs in setup-migrate job)

Doesn't work (no ffmpeg in the Wasmer PHP sandbox):
- Audio-track splitting, 720p/480p variants, embedded-subtitle extraction
  (these are skipped by design — exactly what you see on Render's free plan)
- Non-default audio-track playback (needs an ffmpeg remux)
- Note: Laravel's `queue:work --timeout=800` + a 15 min job cap means very
  large Pixeldrain uploads may time out and be marked failed.

## Common issues

- `is not responding` in the dashboard → check health check path `/login`
  returns 200/302.
- Missing CSS/JS → confirm `public/build` exists in the repo (run `npm run build`
  locally before deploy).
- SQLite "database is locked" → transient under the 5-min queue cron; jobs retry.
- Storage quota → media is kept on Pixeldrain, so the 1 GB volume only holds
  SQLite + logs; monitor it in the Wasmer dashboard.
