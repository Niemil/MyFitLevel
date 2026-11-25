<?php
declare(strict_types=1);

/* Outgoing email: a small SMTP client (SSL 465 / STARTTLS 587 / plain) with PHP mail() as fallback, plus the HTML template. */

function mail_conf_path(): string
{
    return APP_ROOT . '/config/mail.json';
}

function mail_conf(): array
{
    $d = ['host' => 'mail.myfitlevel.com', 'port' => 465, 'secure' => 'ssl', 'user' => 'noreply@myfitlevel.com', 'pass' => '',
          'from' => 'noreply@myfitlevel.com', 'name' => '', 'verify' => 1];
    $f = mail_conf_path();
    if (is_file($f)) {
        $j = json_decode((string)file_get_contents($f), true);
        if (is_array($j)) {
            $d = array_merge($d, $j);
        }
    }
    if ($d['name'] === '') {
        $d['name'] = site_name();
    }
    return $d;
}

function mail_conf_save(array $c): bool
{
    $f = mail_conf_path();
    @mkdir(dirname($f), 0775, true);
    $ok = @file_put_contents($f, json_encode($c, JSON_PRETTY_PRINT), LOCK_EX) !== false;
    @chmod($f, 0600);
    return $ok;
}

function mail_log(string $to, bool $ok, string $err): void
{
    $log = json_decode((string)setting('mail_log', '[]'), true) ?: [];
    array_unshift($log, ['t' => date('Y-m-d H:i:s'), 'to' => $to, 'ok' => $ok, 'err' => mb_substr($err, 0, 200)]);
    set_setting('mail_log', json_encode(array_slice($log, 0, 8)));
}

function smtp_expect($fp, array $ok): string
{
    $all = '';
    while (($line = fgets($fp, 1024)) !== false) {
        $all .= $line;
        if (strlen($line) < 4 || $line[3] !== '-') {
            break;
        }
    }
    $code = (int)substr($all, 0, 3);
    if (!in_array($code, $ok, true)) {
        throw new RuntimeException('SMTP said: ' . trim($all));
    }
    return $all;
}

function smtp_cmd($fp, string $cmd, array $ok): string
{
    fwrite($fp, $cmd . "\r\n");
    return smtp_expect($fp, $ok);
}

function mail_encode_header(string $s): string
{
    return preg_match('/[^\x20-\x7e]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
}

/** Sends one email. Returns [true,''] or [false,'reason']. */
function send_mail(string $to, string $subject, string $html, string $text = '', array $extra = []): array
{
    $r = send_mail_raw($to, $subject, $html, $text, $extra);
    mail_log($to, $r[0], $r[1]);
    return $r;
}

function send_mail_raw(string $to, string $subject, string $html, string $text = '', array $extra = []): array
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return [false, 'Bad address'];
    }
    $c = mail_conf();
    $from = $c['from'];
    $text = $text !== '' ? $text : trim(html_entity_decode(strip_tags(preg_replace('#<(br|/p|/h\d|/tr)[^>]*>#i', "\n", $html)), ENT_QUOTES, 'UTF-8'));
    $b = 'b' . bin2hex(random_bytes(8));
    $domain = substr(strrchr($from, '@') ?: '@localhost', 1);
    $hdr = 'Date: ' . date('r') . "\r\n"
        . 'From: ' . mail_encode_header($c['name']) . " <$from>\r\n"
        . "To: <$to>\r\n"
        . 'Subject: ' . mail_encode_header($subject) . "\r\n"
        . 'Message-ID: <' . bin2hex(random_bytes(10)) . "@$domain>\r\n"
        . "MIME-Version: 1.0\r\n"
        . "Auto-Submitted: auto-generated\r\n"
        . implode('', array_map(fn($l) => $l . "\r\n", $extra))
        . "Content-Type: multipart/alternative; boundary=\"$b\"\r\n";
    $body = "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text))
        . "--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html))
        . "--$b--\r\n";

    if ($c['pass'] === '' || $c['host'] === '') {
        // no SMTP login saved: fall back to the server's own mail()
        // mail() adds To/Subject/Date itself; sending them twice makes some hosts reject the message
        $h = ['MIME-Version: 1.0', 'Content-Type: multipart/alternative; boundary="' . $b . '"',
              'From: ' . mail_encode_header($c['name']) . " <$from>", "Reply-To: $from", 'Auto-Submitted: auto-generated'];
        foreach ($extra as $line) { $h[] = $line; }
        $ok = @mail($to, mail_encode_header($subject), $body, implode("\r\n", $h), '-f' . $from);
        return $ok ? [true, ''] : [false, 'PHP mail() failed (add SMTP details in Admin > Email)'];
    }
    try {
        $ctx = stream_context_create(['ssl' => ['verify_peer' => (bool)$c['verify'], 'verify_peer_name' => (bool)$c['verify'], 'allow_self_signed' => !$c['verify']]]);
        $scheme = $c['secure'] === 'ssl' ? 'ssl' : 'tcp';
        $fp = @stream_socket_client("$scheme://{$c['host']}:{$c['port']}", $en, $es, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            throw new RuntimeException("Could not connect to {$c['host']}:{$c['port']} ($es)");
        }
        stream_set_timeout($fp, 20);
        smtp_expect($fp, [220]);
        $me = preg_replace('/[^A-Za-z0-9.-]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
        smtp_cmd($fp, "EHLO $me", [250]);
        if ($c['secure'] === 'tls') {
            smtp_cmd($fp, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('STARTTLS failed');
            }
            smtp_cmd($fp, "EHLO $me", [250]);
        }
        if ($c['user'] !== '') {
            smtp_cmd($fp, 'AUTH LOGIN', [334]);
            smtp_cmd($fp, base64_encode($c['user']), [334]);
            smtp_cmd($fp, base64_encode($c['pass']), [235]);
        }
        smtp_cmd($fp, "MAIL FROM:<$from>", [250]);
        smtp_cmd($fp, "RCPT TO:<$to>", [250, 251]);
        smtp_cmd($fp, 'DATA', [354]);
        $data = preg_replace('/^\./m', '..', str_replace(["\r\n", "\n"], ["\n", "\r\n"], $hdr . "\r\n" . $body));
        fwrite($fp, $data . "\r\n.\r\n");
        smtp_expect($fp, [250]);
        @smtp_cmd($fp, 'QUIT', [221]);
        fclose($fp);
        return [true, ''];
    } catch (Throwable $e) {
        if (!empty($fp) && is_resource($fp)) {
            @fclose($fp);
        }
        return [false, $e->getMessage()];
    }
}

/** Branded HTML email (tables + inline styles so it works everywhere). */
function mail_html(string $title, string $bodyHtml, string $btnText = '', string $btnUrl = '', string $foot = ''): string
{
    $name = e(site_name());
    $btn = $btnUrl !== ''
        ? '<p style="margin:26px 0"><a href="' . e($btnUrl) . '" style="background:#36a85c;color:#ffffff;text-decoration:none;font-weight:700;padding:14px 26px;border-radius:10px;display:inline-block">' . e($btnText) . '</a></p>'
        : '';
    return '<!DOCTYPE html><html><body style="margin:0;background:#eef1f4;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#1e2c39">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 12px">'
        . '<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#ffffff;border-radius:14px;overflow:hidden">'
        . '<tr><td style="background:#2c3e50;padding:18px 24px;border-bottom:3px solid #36a85c;color:#ffffff;font-size:20px;font-weight:800">' . $name . '</td></tr>'
        . '<tr><td style="padding:28px 24px;font-size:16px;line-height:1.55"><h1 style="font-size:22px;margin:0 0 14px">' . e($title) . '</h1>' . $bodyHtml . $btn . '</td></tr>'
        . '<tr><td style="padding:0 24px 22px;font-size:12px;color:#667686">' . $foot . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

function site_secret(): string
{
    $s = (string)setting('site_secret', '');
    if ($s === '') {
        $s = bin2hex(random_bytes(24));
        set_setting('site_secret', $s);
    }
    return $s;
}

/** Signed link that unsubscribes a member from reminder emails. */
function unsub_url(int $uid): string
{
    $sig = substr(hash_hmac('sha256', 'unsub' . $uid, site_secret()), 0, 16);
    return site_url('unsub/' . $uid . '/' . $sig);
}

function mail_user_verify(array $user): array
{
    $tok = bin2hex(random_bytes(16));
    q('UPDATE users SET email_token = ? WHERE id = ?', [$tok, (int)$user['id']]);
    $url = site_url('verify/' . $tok);
    $html = mail_html('Confirm your email', '<p>Hi ' . e(display_name($user)) . ',</p><p>Tap the button to confirm this email address. It lets us send you password resets and the occasional friendly nudge.</p>', 'Confirm my email', $url,
        'If you did not create an account, you can ignore this message.');
    return send_mail((string)$user['email'], 'Confirm your email for ' . site_name(), $html, "Confirm your email: $url");
}
