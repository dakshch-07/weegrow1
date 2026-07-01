<?php
class SMTPMailer {
    public static function send($to, $subject, $body, $headers = []) {
        $host = getenv('SMTP_HOST');
        $port = getenv('SMTP_PORT') ?: 587;
        $username = getenv('SMTP_USER');
        $password = getenv('SMTP_PASS');
        $encryption = getenv('SMTP_ENCRYPTION'); // 'ssl', 'tls', or ''
        $from = getenv('SMTP_FROM') ?: 'no-reply@weegrow.in';
        $from_name = getenv('SMTP_FROM_NAME') ?: 'WeeGROW Leads';

        if (empty($host) || empty($username) || empty($password)) {
            // Fallback to PHP mail()
            $headers_str = implode("\r\n", $headers);
            return @mail($to, $subject, $body, $headers_str);
        }

        try {
            $context = stream_context_create();
            $socket_host = ($encryption === 'ssl') ? "ssl://$host" : $host;
            
            $socket = @stream_socket_client(
                "$socket_host:$port",
                $errno,
                $errstr,
                10, // Timeout
                STREAM_CLIENT_CONNECT,
                $context
            );

            if (!$socket) {
                throw new Exception("Could not connect to SMTP server: $errstr ($errno)");
            }

            $getResponse = function($socket) {
                $response = '';
                while (($line = fgets($socket, 512)) !== false) {
                    $response .= $line;
                    if (substr($line, 3, 1) === ' ') {
                        break;
                    }
                }
                return $response;
            };

            $sendCommand = function($socket, $cmd) use ($getResponse) {
                fwrite($socket, $cmd . "\r\n");
                return $getResponse($socket);
            };

            $getResponse($socket); // Initial connection greeting

            if ($encryption === 'tls') {
                $sendCommand($socket, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
                $res = $sendCommand($socket, "STARTTLS");
                if (strpos($res, '220') === false) {
                    throw new Exception("STARTTLS failed: $res");
                }
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new Exception("Crypto negotiation failed");
                }
            }

            $sendCommand($socket, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
            
            // Authentication
            $res = $sendCommand($socket, "AUTH LOGIN");
            if (strpos($res, '334') === false) {
                throw new Exception("AUTH LOGIN failed: $res");
            }
            
            $res = $sendCommand($socket, base64_encode($username));
            if (strpos($res, '334') === false) {
                throw new Exception("Username authentication failed: $res");
            }
            
            $res = $sendCommand($socket, base64_encode($password));
            if (strpos($res, '235') === false) {
                throw new Exception("Password authentication failed: $res");
            }

            // Mail transaction
            $sendCommand($socket, "MAIL FROM:<$from>");
            $sendCommand($socket, "RCPT TO:<$to>");
            
            $res = $sendCommand($socket, "DATA");
            if (strpos($res, '354') === false) {
                throw new Exception("DATA command failed: $res");
            }

            // Construct email with MIME headers
            $msg = "MIME-Version: 1.0\r\n";
            $msg .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $msg .= "From: =?UTF-8?B?" . base64_encode($from_name) . "?= <$from>\r\n";
            $msg .= "To: <$to>\r\n";
            $msg .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
            $msg .= "Date: " . date('r') . "\r\n";
            
            foreach ($headers as $h) {
                $msg .= $h . "\r\n";
            }
            $msg .= "\r\n" . $body . "\r\n.";
            
            $res = $sendCommand($socket, $msg);
            if (strpos($res, '250') === false) {
                throw new Exception("Message data failed: $res");
            }

            $sendCommand($socket, "QUIT");
            fclose($socket);
            return true;
        } catch (Exception $e) {
            error_log("SMTP Error: " . $e->getMessage() . ". Falling back to mail().");
            // Fallback to PHP mail()
            $headers_str = implode("\r\n", $headers);
            return @mail($to, $subject, $body, $headers_str);
        }
    }
}
?>
