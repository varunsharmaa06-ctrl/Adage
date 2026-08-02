# Optional: self-hosted PHP form handler

You do **not** need this. The live site's form already works with no backend
(see `script.js` → `FORM_ENDPOINT`). Keep this only if you later want the form
handled by your own code instead of a third-party service.

## Why it isn't the default

PHP *can* run on Vercel, but two things are unavoidable:

1. Vercel has no PHP runtime of its own — it needs a community runtime declared
   in `vercel.json`.
2. **`mail()` does not work on Vercel.** The container has no mail server, so
   sending requires either SMTP credentials or an email API key.

That configuration is the "extra work" this setup avoids.

## If you ever want to switch to it

1. Move `contact.php` to `api/contact.php` in the project root.
2. Remove `php-alternative/` from `.vercelignore`.
3. Add `vercel.json` in the project root:

   ```json
   {
     "functions": {
       "api/*.php": { "runtime": "vercel-php@0.7.4" }
     }
   }
   ```

4. In `script.js`, change `FORM_ENDPOINT` to `'/api/contact.php'`.
5. Add the credentials in Vercel → Settings → Environment Variables, either:

   - `RESEND_API_KEY` + `MAIL_FROM` (resend.com, free tier, verify the domain), or
   - `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS` for the real
     info@adageuniverse.com mailbox.

6. Redeploy.

The handler hard-codes the recipient as `info@adageuniverse.com`, validates
input, escapes HTML, blocks non-POST requests, includes a honeypot, and speaks
SMTP directly — no Composer packages required.
