# TeamSuite Capacitor (Android / iOS)

Mobile shells load the **same HTTPS origin** as the VPS admin/portal (no separate API rewrite). Deploy Laravel + Vite assets on the server; Capacitor WebView points at that URL.

## Prerequisites

- Node.js 20+
- Android Studio (Android) and/or Xcode (iOS, macOS only)
- TeamSuite_ver.3 web app built and reachable at your VPS URL

## Environment

Set the server URL (used by `capacitor.config.json` via env at sync time):

```bash
# PowerShell
$env:CAPACITOR_SERVER_URL = "https://your-vps.example.com"

# bash
export CAPACITOR_SERVER_URL="https://your-vps.example.com"
```

Default if unset: `http://localhost:8000` (local `php artisan serve`).

## Setup (from repo root)

```bash
cd mobile
npm init -y
npm install @capacitor/core @capacitor/cli @capacitor/android @capacitor/ios
npx cap init TeamSuite com.teamsuite.app --web-dir=../public
npx cap add android
npx cap add ios   # macOS only
```

Copy or merge `mobile/capacitor.config.json` after `cap init` and set `server.url` to your `CAPACITOR_SERVER_URL` (JSON does not expand env vars — edit the file or use a `capacitor.config.ts` wrapper).

## Sync & run

```bash
# After VPS deploy or local build
npm run build          # from repo root (Vite → public/build)
npx cap sync
npx cap open android
npx cap open ios
```

## Root `package.json` scripts (suggested)

Add to the Laravel project root `package.json`:

```json
{
  "scripts": {
    "cap:sync": "cd mobile && npx cap sync",
    "cap:android": "cd mobile && npx cap open android",
    "cap:ios": "cd mobile && npx cap open ios"
  }
}
```

## Entry routes

- Admin: `/dashboard` (auth)
- Parent portal: `/app/parent`
- Coach portal: `/app/coach`
- Athlete portal: `/app/athlete`

Updating the VPS deployment updates all installed apps instantly (WebView reload).
