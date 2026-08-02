<?php
/* ==========================================================
   AdAge — Contact form handler (PHP on Vercel)
   POST /api/contact.php   { name, email, company, message }

   Mail always goes to RECIPIENT below. A visitor cannot change it.

   NOTE: PHP's mail() does not work on Vercel — the container has no
   sendmail/MTA. So this talks to a mail provider directly. Set ONE of
   these groups in Vercel → Settings → Environment Variables:

   A) Resend  (https://resend.com — free tier)
      RESEND_API_KEY = re_xxxxxxxx
      MAIL_FROM      = website@adageuniverse.com   (verified domain)

   B) SMTP    (the real info@adageuniverse.com mailbox)
      SMTP_HOST = smtp.yourprovider.com
      SMTP_PORT = 465            (465 = SSL, 587 = STARTTLS)
      SMTP_USER = info@adageuniverse.com
      SMTP_PASS = ********
      MAIL_FROM = info@adageuniverse.com     (optional)
   ========================================================== */

declare(strict_types=1);

const RECIPIENT = 'info@adageuniverse.com';

header('Content-Type: application/json; charset=utf-8');

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function env(string $key, string $default = ''): string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        $value = $_ENV[$key] ?? $default;
    }
    return trim((string) $value);
}

function clean($value, int $max): string
{
    $value = is_string($value) ? $value : '';
    $value = preg_replace('/\s+/u', ' ', $value);
    return mb_substr(trim($value), 0, $max);
}

/* ---------- request ---------- */

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'OPTIONS') {
    header('Allow: POST, OPTIONS');
    respond(204, []);
}
if ($method !== 'POST') {
    header('Allow: POST, OPTIONS');
    respond(405, ['ok' => false, 'error' => 'Method not allowed.']);
}

$raw  = file_get_contents('php://input');
$data = json_decode((string) $raw, true);
if (!is_array($data)) {
    $data = $_POST;                       // plain form posts work too
}

/* ---------- spam trap ---------- */

if (clean($data['website'] ?? '', 100) !== '') {
    respond(200, ['ok' => true]);         // bot: accept, discard
}

/* ---------- validate ---------- */

$name    = clean($data['name'] ?? '', 120);
$email   = clean($data['email'] ?? '', 200);
$company = clean($data['company'] ?? '', 160);
$message = mb_substr(trim((string) ($data['message'] ?? '')), 0, 5000);

if ($name === '' || $email === '' || $message === '') {
    respond(400, ['ok' => false, 'error' => 'Name, email, and message are required.']);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(400, ['ok' => false, 'error' => 'Please enter a valid email address.']);
}
if (mb_strlen($message) < 10) {
    respond(400, ['ok' => false, 'error' => 'Please write a slightly longer message.']);
}

/* ---------- compose ---------- */

$subject = 'New enquiry from ' . $name . ($company !== '' ? " ($company)" : '');

$text = implode("\n", [
    'Name:    ' . $name,
    'Email:   ' . $email,
    'Company: ' . ($company !== '' ? $company : '-'),
    '',
    'Message:',
    $message,
]);

$e    = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$html = '<div style="font-family:Inter,Arial,sans-serif;font-size:15px;color:#111;line-height:1.6">'
      . '<h2 style="margin:0 0 16px;font-size:18px">New enquiry from the AdAge website</h2>'
      . '<table style="border-collapse:collapse">'
      . '<tr><td style="padding:4px 16px 4px 0;color:#666">Name</td><td>' . $e($name) . '</td></tr>'
      . '<tr><td style="padding:4px 16px 4px 0;color:#666">Email</td><td><a href="mailto:' . $e($email) . '">' . $e($email) . '</a></td></tr>'
      . '<tr><td style="padding:4px 16px 4px 0;color:#666">Company</td><td>' . $e($company !== '' ? $company : '-') . '</td></tr>'
      . '</table>'
      . '<p style="margin:20px 0 6px;color:#666">Message</p>'
      . '<div style="white-space:pre-wrap;padding:12px 14px;background:#f6f6f6;border-radius:8px">' . $e($message) . '</div>'
      . '</div>';

/* ---------- transport A: Resend ---------- */

function send_via_resend(string $subject, string $text, string $html, string $replyTo): void
{
    $from = env('MAIL_FROM', 'onboarding@resend.dev');

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . env('RESEND_API_KEY'),
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'from'     => $from,
            'to'       => [RECIPIENT],
            'reply_to' => $replyTo,
            'subject'  => $subject,
            'text'     => $text,
            'html'     => $html,
        ]),
    ]);

    $body   = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        throw new RuntimeException('Resend request failed: ' . $err);
    }
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException("Resend responded $status: $body");
    }
}

/* ---------- transport B: SMTP (no libraries needed) ---------- */

function send_via_smtp(string $subject, string $text, string $html, string $replyTo): void
{
    $host = env('SMTP_HOST');
    $port = (int) (env('SMTP_PORT', '465'));
    $user = env('SMTP_USER');
    $pass = env('SMTP_PASS');
    $from = env('MAIL_FROM', $user);

    // ssl | tls | none — defaults to ssl on 465, tls elsewhere
    $secure = strtolower(env('SMTP_SECURE', $port === 465 ? 'ssl' : 'tls'));

    $target = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $socket = @stream_socket_client($target, $errno, $errstr, 15);
    if (!$socket) {
        throw new RuntimeException("SMTP connect failed: $errstr ($errno)");
    }
    stream_set_timeout($socket, 20);

    $read = function (array $expected) use ($socket): string {
        $line = '';
        do {
            $line = fgets($socket, 2048);
            if ($line === false) {
                throw new RuntimeException('SMTP read timed out.');
            }
            $continues = isset($line[3]) && $line[3] === '-';
        } while ($continues);

        $code = (int) substr($line, 0, 3);
        if (!in_array($code, $expected, true)) {
            throw new RuntimeException('SMTP error: ' . trim($line));
        }
        return $line;
    };
    $write = function (string $command) use ($socket): void {
        fwrite($socket, $command . "\r\n");
    };

    $helo = 'adageuniverse.com';

    $read([220]);
    $write("EHLO $helo");
    $read([250]);

    if ($secure === 'tls') {
        $write('STARTTLS');
        $read([220]);
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('STARTTLS negotiation failed.');
        }
        $write("EHLO $helo");
        $read([250]);
    }

    if ($user !== '') {
        $write('AUTH LOGIN');
        $read([334]);
        $write(base64_encode($user));
        $read([334]);
        $write(base64_encode($pass));
        $read([235]);
    }

    $write('MAIL FROM:<' . $from . '>');
    $read([250]);
    $write('RCPT TO:<' . RECIPIENT . '>');
    $read([250, 251]);
    $write('DATA');
    $read([354]);

    $boundary = 'adage' . bin2hex(random_bytes(8));
    $headers  = [
        'From: AdAge Website <' . $from . '>',
        'To: <' . RECIPIENT . '>',
        'Reply-To: <' . $replyTo . '>',
        'Subject: ' . mb_encode_mimeheader($subject, 'UTF-8'),
        'Date: ' . date(DATE_RFC2822),
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ];

    $body = implode("\r\n", $headers) . "\r\n\r\n"
          . "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n" . $text . "\r\n"
          . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n" . $html . "\r\n"
          . "--$boundary--\r\n";

    // normalise line endings, then dot-stuff so a lone "." can't end DATA early
    $body = preg_replace("/\r\n|\r|\n/", "\r\n", $body);
    $body = preg_replace('/^\./m', '..', $body);

    fwrite($socket, $body . "\r\n.\r\n");
    $read([250]);

    $write('QUIT');
    fclose($socket);
}

/* ---------- send ---------- */

try {
    if (env('RESEND_API_KEY') !== '') {
        send_via_resend($subject, $text, $html, $email);
    } elseif (env('SMTP_HOST') !== '' && env('SMTP_USER') !== '') {
        send_via_smtp($subject, $text, $html, $email);
    } else {
        error_log('Contact form: no mail transport configured (set RESEND_API_KEY or SMTP_*).');
        respond(500, ['ok' => false, 'error' => 'Mail service is not configured yet.']);
    }
} catch (Throwable $err) {
    error_log('Contact form send failed: ' . $err->getMessage());
    respond(502, ['ok' => false, 'error' => 'Could not send your message. Please email us directly.']);
}

respond(200, ['ok' => true]);
