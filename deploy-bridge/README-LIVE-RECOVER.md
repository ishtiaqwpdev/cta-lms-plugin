# Live recover without cPanel (504 / WP Pusher timeout)

If you only have **WordPress admin** access (no cPanel/FTP), use this helper plugin.

## Step 1 — Download the ZIP

From GitHub, download:

`deploy-bridge/aaa-cta-lms-recover.zip`

Or build locally:

```bash
php scripts/build-aaa-cta-lms-recover-zip.php
```

## Step 2 — Upload in WordPress

1. Log in to `wp-admin`
2. **Plugins → Add New → Upload Plugin**
3. Choose `aaa-cta-lms-recover.zip`
4. **Install Now → Activate**

> Use **Plugins → Add New**, NOT the WP Pusher page (that page may 504).

## Step 3 — Run recover

1. **Tools → CTA LMS Recover**
2. Click **Clear 504 upgrade lock**
3. Click **Queue workbook sync**
4. Click **Run one sync batch now** until "Missing workbook banks" shows `0`

## Step 4 — Update main plugin

When the site loads normally again:

- **WP Pusher → Pull** `main` (v1.0.283+), or ask hosting to deploy from GitHub

## Step 5 — Cleanup

Deactivate and delete **AAA CTA LMS Live Recover** when live is healthy.
