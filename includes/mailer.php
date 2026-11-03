<?php
// Sends plain-text email through the SMTP server set in config/mail.php, with no extra libraries.
// While mail is switched off, the message is written to the PHP error log instead and true is returned,
// so login codes can be read there during development.

function sendMail(string $to, string $subject, string $body): bool
{
    static $cfg = null;
    $cfg ??= require __DIR__ . '/../config/mail.php';

    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || preg_match('/[\s<>"]/', $to)) {
        error_log('Scholar Monitor mail: refused an invalid recipient address');
        return false;
    }
    $subject = mb_substr(trim(preg_replace('/\s+/', ' ', $subject)), 0, 200);
    if (!$cfg['enabled']) {
        error_log("Scholar Monitor mail is OFF. Would have sent to $to | $subject\n$body");
        return true;
    }
    if (!$cfg['configured']) {
        error_log('Scholar Monitor mail is on, but the settings in config/mail.php are incomplete');
        return false;
    }

    $fp = null;
    try {
        // The server certificate is always checked. Never switch this off to "make it work".
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $cfg['host']]]);
        $scheme = $cfg['encryption'] === 'ssl' ? 'ssl' : 'tcp';
        $fp = @stream_socket_client("$scheme://{$cfg['host']}:{$cfg['port']}", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            throw new RuntimeException("could not connect to {$cfg['host']}:{$cfg['port']} ($errstr)");
        }
        stream_set_timeout($fp, 10);

        smtpExpect($fp, [220]);
        smtpCommand($fp, 'EHLO localhost', [250]);
        if ($cfg['encryption'] === 'tls') {
            smtpCommand($fp, 'STARTTLS', [220]); // if the server cannot do this we stop, and never send the password in clear
            if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('could not start an encrypted connection (certificate not trusted?)');
            }
            smtpCommand($fp, 'EHLO localhost', [250]);
        }
        smtpCommand($fp, 'AUTH PLAIN ' . base64_encode("\0{$cfg['username']}\0{$cfg['password']}"), [235]);
        smtpCommand($fp, "MAIL FROM:<{$cfg['from_email']}>", [250]);
        smtpCommand($fp, "RCPT TO:<$to>", [250, 251]);
        smtpCommand($fp, 'DATA', [354]);

        $fromName = preg_replace('/["\\\\\r\n]/', '', $cfg['from_name']);
        if ($fromName !== '') {
            $fromName = preg_match('/[^\x20-\x7e]/', $fromName)
                ? mb_encode_mimeheader($fromName, 'UTF-8', 'B', "\r\n") . ' '
                : '"' . $fromName . '" ';
        }
        $subjectLine = preg_match('/[^\x20-\x7e]/', $subject)
            ? mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n", 9)
            : $subject;
        $domain = substr(strrchr($cfg['from_email'], '@'), 1);
        $message = implode("\r\n", [
            'Date: ' . date('r'),
            "From: {$fromName}<{$cfg['from_email']}>",
            "To: <$to>",
            'Subject: ' . $subjectLine,
            'Message-ID: <' . bin2hex(random_bytes(12)) . "@$domain>",
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64', // base64 keeps any text, even a lone "." line, from ending the message early
            '',
            rtrim(chunk_split(base64_encode(preg_replace('/\r\n|\r|\n/', "\r\n", $body)), 76, "\r\n")),
        ]);
        smtpWrite($fp, $message . "\r\n.");
        smtpExpect($fp, [250]);
        smtpWrite($fp, 'QUIT');
        return true;
    } catch (Throwable $e) {
        error_log('Scholar Monitor mail failed: ' . $e->getMessage());
        return false;
    } finally {
        if (is_resource($fp)) {
            fclose($fp);
        }
    }
}

function smtpWrite($fp, string $data): void
{
    if (@fwrite($fp, $data . "\r\n") === false) {
        throw new RuntimeException('lost the connection to the mail server');
    }
}

// Reads one reply (a reply can span several lines) and fails unless its code is in $ok.
function smtpExpect($fp, array $ok): void
{
    $text = '';
    do {
        $line = fgets($fp, 1024);
        if ($line === false) {
            throw new RuntimeException('the mail server closed the connection or took too long');
        }
        $text .= $line;
    } while (isset($line[3]) && $line[3] === '-');
    if (!in_array((int) substr($text, 0, 3), $ok, true)) {
        throw new RuntimeException('the mail server answered: ' . trim($text));
    }
}

function smtpCommand($fp, string $command, array $ok): void
{
    smtpWrite($fp, $command);
    smtpExpect($fp, $ok);
}