<?php
/**
 * Shared mail configuration for native PHP mail() and SMTP.
 */

require_once __DIR__ . '/config.php';

define('MAIL_METHOD', appEnv('MAIL_METHOD', 'native'));
define('SMTP_HOST', appEnv('SMTP_HOST', 'localhost'));
define('SMTP_PORT', (int)appEnv('SMTP_PORT', '25'));
define('SMTP_SECURE', appEnv('SMTP_SECURE', ''));
define('SMTP_AUTH', filter_var(getenv('SMTP_AUTH'), FILTER_VALIDATE_BOOLEAN));
define('SMTP_USERNAME', appEnv('SMTP_USERNAME', ''));
define('SMTP_PASSWORD', appEnv('SMTP_PASSWORD', ''));
define('MAIL_FROM_ADDRESS', appEnv('MAIL_FROM', 'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost')));
define('MAIL_FROM_NAME', appEnv('MAIL_FROM_NAME', appName()));

function send_email($to, string $subject, string $message, array $options = []): bool {
    mb_language("uni");
    mb_internal_encoding("UTF-8");

    $fromEmail = $options['from_email'] ?? MAIL_FROM_ADDRESS;
    $fromName = $options['from_name'] ?? MAIL_FROM_NAME;
    $replyTo = $options['reply_to'] ?? null;

    if (MAIL_METHOD === 'smtp') {
        return send_email_smtp($to, $subject, $message, $fromEmail, $fromName, $replyTo);
    }

    return send_email_native($to, $subject, $message, $fromEmail, $fromName, $replyTo);
}

function send_email_native($to, string $subject, string $message, string $fromEmail, string $fromName, ?string $replyTo): bool {
    $recipients = is_array($to) ? $to : [$to];
    $success = true;

    $headers = "From: $fromName <$fromEmail>\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    if ($replyTo) {
        $headers .= "Reply-To: $replyTo\r\n";
    }

    $extraParams = "-f$fromEmail";

    foreach ($recipients as $recipient) {
        $result = mb_send_mail($recipient, $subject, $message, $headers, $extraParams);
        if (!$result) {
            error_log("Mail failed (native) to: $recipient");
            $success = false;
        }
    }

    return $success;
}

function send_email_smtp($to, string $subject, string $message, string $fromEmail, string $fromName, ?string $replyTo): bool {
    $recipients = is_array($to) ? $to : [$to];
    $success = true;

    foreach ($recipients as $recipient) {
        $socket = false;

        try {
            $host = (SMTP_SECURE === 'ssl') ? 'ssl://' . SMTP_HOST : SMTP_HOST;
            $socket = @fsockopen($host, SMTP_PORT, $errno, $errstr, 10);

            if (!$socket) {
                error_log("SMTP connect failed: $errstr ($errno)");
                $success = false;
                continue;
            }

            $response = fgets($socket, 515);
            if (substr($response, 0, 3) !== '220') {
                error_log("SMTP greeting failed: $response");
                fclose($socket);
                $success = false;
                continue;
            }

            if (SMTP_SECURE === 'tls') {
                fwrite($socket, "STARTTLS\r\n");
                fflush($socket);
                $response = fgets($socket, 515);
                if (substr($response, 0, 3) !== '220') {
                    error_log("SMTP STARTTLS failed: $response");
                    fclose($socket);
                    $success = false;
                    continue;
                }
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            }

            fwrite($socket, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n");
            fflush($socket);
            $response = fgets($socket, 515);
            if (substr($response, 0, 3) !== '250') {
                error_log("SMTP EHLO failed: $response");
                fclose($socket);
                $success = false;
                continue;
            }
            while (substr($response, 0, 4) === '250-') {
                $response = fgets($socket, 515);
            }

            if (SMTP_AUTH && SMTP_USERNAME) {
                fwrite($socket, "AUTH LOGIN\r\n");
                fflush($socket);
                $response = fgets($socket, 515);
                if (substr($response, 0, 3) !== '334') {
                    error_log("SMTP AUTH LOGIN failed: $response");
                    fclose($socket);
                    $success = false;
                    continue;
                }

                fwrite($socket, base64_encode(SMTP_USERNAME) . "\r\n");
                fflush($socket);
                $response = fgets($socket, 515);
                if (substr($response, 0, 3) !== '334') {
                    error_log("SMTP AUTH user failed: $response");
                    fclose($socket);
                    $success = false;
                    continue;
                }

                fwrite($socket, base64_encode(SMTP_PASSWORD) . "\r\n");
                fflush($socket);
                $response = fgets($socket, 515);
                if (substr($response, 0, 3) !== '235') {
                    error_log("SMTP AUTH pass failed: $response");
                    fclose($socket);
                    $success = false;
                    continue;
                }
            }

            fwrite($socket, "MAIL FROM:<$fromEmail>\r\n");
            fflush($socket);
            $response = fgets($socket, 515);
            if (substr($response, 0, 3) !== '250') {
                error_log("SMTP MAIL FROM failed: $response");
                fclose($socket);
                $success = false;
                continue;
            }

            fwrite($socket, "RCPT TO:<$recipient>\r\n");
            fflush($socket);
            $response = fgets($socket, 515);
            if (substr($response, 0, 3) !== '250') {
                error_log("SMTP RCPT TO failed for $recipient: $response");
                fclose($socket);
                $success = false;
                continue;
            }

            fwrite($socket, "DATA\r\n");
            fflush($socket);
            $response = fgets($socket, 515);
            if (substr($response, 0, 3) !== '354') {
                error_log("SMTP DATA failed: $response");
                fclose($socket);
                $success = false;
                continue;
            }

            $messageId = '<' . uniqid() . '.' . time() . '@' . (SMTP_HOST ?: 'localhost') . '>';
            $date = date('r');

            fwrite($socket, "From: $fromName <$fromEmail>\r\n");
            fwrite($socket, "To: $recipient\r\n");
            if ($replyTo) {
                fwrite($socket, "Reply-To: $replyTo\r\n");
            }
            fwrite($socket, "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n");
            fwrite($socket, "Date: $date\r\n");
            fwrite($socket, "Message-ID: $messageId\r\n");
            fwrite($socket, "Content-Type: text/plain; charset=UTF-8\r\n");
            fwrite($socket, "Content-Transfer-Encoding: 8bit\r\n");
            fwrite($socket, "\r\n");
            fwrite($socket, $message . "\r\n");
            fwrite($socket, ".\r\n");
            fflush($socket);

            $response = fgets($socket, 515);
            if (substr($response, 0, 3) !== '250') {
                error_log("SMTP mail rejected: $response");
                $success = false;
            }

            fwrite($socket, "QUIT\r\n");
            fflush($socket);
            fgets($socket, 515);
            fclose($socket);
        } catch (Exception $e) {
            error_log("SMTP exception for $recipient: " . $e->getMessage());
            if ($socket) {
                fclose($socket);
            }
            $success = false;
        }
    }

    return $success;
}
