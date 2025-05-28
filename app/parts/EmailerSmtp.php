<?php
namespace Dotsystems\App\Parts;

class EmailerSmtp
{
    private $boundary = '';
    private $serverHost = 'localhost';
    private $serverPort = 25;
    private $connectionTimeout = 30;
    private $connection = null;
    private $isConnected = false;
    private $debugLevel = 0;
    private $errorMessage = [];
    private $lineEnd = "\r\n";
    private $username = '';
    private $password = '';
    private $secureMode = ''; // 'tls' or 'ssl'
    private $authType = 'LOGIN'; // Možnosti: 'LOGIN', 'PLAIN', 'NTLM'
    private $imapClient = null; // Inštancia EmailerImap pre ukladanie
    private $saveEmail = false; // Voliteľné ukladanie do priečinka Sent

    public function __construct(string $host = 'localhost', int $port = 25, int $timeout = 30, string $secure = '', bool $saveEmail = false)
    {
        $this->serverHost = $host;
        $this->serverPort = $port;
        $this->connectionTimeout = $timeout;
        $this->secureMode = $secure;
        $this->saveEmail = $saveEmail;
    }

    public function setImapClient(EmailerImap $imapClient): void
    {
        $this->imapClient = $imapClient;
    }

    public function setCredentials(string $username, string $password, string $authType = 'LOGIN'): void
    {
        $this->username = $username;
        $this->password = $password;
        $this->authType = in_array($authType, ['LOGIN', 'PLAIN', 'NTLM']) ? $authType : 'LOGIN';
    }

    public function setDebugLevel(int $level): void
    {
        $this->debugLevel = max(0, min($level, 4));
    }

    public function connect(): bool
    {
        if ($this->isConnected) {
            return true;
        }

        $host = $this->secureMode === 'ssl' ? 'ssl://' . $this->serverHost : $this->serverHost;
        $this->connection = @fsockopen($host, $this->serverPort, $errno, $errstr, $this->connectionTimeout);

        if (!$this->connection) {
            $this->setError("Failed to connect to $host:$this->serverPort - $errstr ($errno)");
            return false;
        }

        stream_set_timeout($this->connection, $this->connectionTimeout);
        $response = $this->readResponse();

        if (!$this->isValidResponse($response)) {
            $this->setError("Invalid server response: $response");
            return false;
        }

        if (!$this->sendHello('EHLO') && !$this->sendHello('HELO')) {
            return false;
        }

        if ($this->secureMode === 'tls') {
            if (!$this->startTLS()) {
                return false;
            }
            if (!$this->sendHello('EHLO')) {
                return false;
            }
        }

        $this->isConnected = true;
        return true;
    }

    private function startTLS(): bool
    {
        $this->sendCommand('STARTTLS');
        $response = $this->readResponse();

        if (substr($response, 0, 3) !== '220') {
            $this->setError("STARTTLS failed: $response");
            return false;
        }

        if (!stream_socket_enable_crypto($this->connection, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            $this->setError("Failed to enable TLS encryption");
            return false;
        }

        return true;
    }

    public function authenticate(): bool
    {
        if (!$this->isConnected) {
            $this->setError("Cannot authenticate: Not connected to server");
            return false;
        }

        switch ($this->authType) {
            case 'PLAIN':
                return $this->authPlain();
            case 'NTLM':
                return $this->authNTLM();
            case 'LOGIN':
            default:
                return $this->authLogin();
        }
    }

    private function authLogin(): bool
    {
        $this->sendCommand('AUTH LOGIN');
        $response = $this->readResponse();

        if (substr($response, 0, 3) !== '334') {
            $this->setError("AUTH LOGIN failed: $response");
            return false;
        }

        $this->sendCommand(base64_encode($this->username));
        $response = $this->readResponse();

        if (substr($response, 0, 3) !== '334') {
            $this->setError("Username not accepted: $response");
            return false;
        }

        $this->sendCommand(base64_encode($this->password));
        $response = $this->readResponse();

        if (substr($response, 0, 3) !== '235') {
            $this->setError("Password not accepted: $response");
            return false;
        }

        return true;
    }

    private function authPlain(): bool
    {
        $this->sendCommand('AUTH PLAIN');
        $response = $this->readResponse();

        if (substr($response, 0, 3) !== '334') {
            $this->setError("AUTH PLAIN failed: $response");
            return false;
        }

        $authStr = base64_encode("\0" . $this->username . "\0" . $this->password);
        $this->sendCommand($authStr);
        $response = $this->readResponse();

        if (substr($response, 0, 3) !== '235') {
            $this->setError("PLAIN authentication failed: $response");
            return false;
        }

        return true;
    }

    private function authNTLM(): bool
    {
        $this->setError("NTLM authentication not implemented");
        return false;
    }

    public function sendEmail(
        string $from,
        array $to,
        array $cc,
        array $bcc,
        string $subject,
        string $body,
        string $contentType = 'text/plain',
        array $attachments = [],
        string $sentFolder = 'Sent'
    ): bool {
        if (!$this->isConnected && !$this->connect()) {
            return false;
        }

        if ($this->username && !$this->authenticate()) {
            return false;
        }

        // Odoslanie MAIL FROM
        if (!$this->sendCommand("MAIL FROM:<$from>")) {
            $response = $this->readResponse();
            $this->setError("MAIL FROM failed: $response");
            return false;
        }

        $response = $this->readResponse();
        if (substr($response, 0, 3) !== '250') {
            $this->setError("MAIL FROM not accepted: $response");
            return false;
        }

        // Odoslanie RCPT TO
        foreach ([$to, $cc, $bcc] as $recipients) {
            foreach ($recipients as $recipient) {
                if (!$this->sendCommand("RCPT TO:<$recipient>")) {
                    return false;
                }
                $response = $this->readResponse();
                if (substr($response, 0, 3) !== '250' && substr($response, 0, 3) !== '251') {
                    $this->setError("RCPT TO ($recipient) failed: $response");
                    return false;
                }
            }
        }

        // Odoslanie DATA
        if (!$this->sendCommand('DATA')) {
            return false;
        }
        $response = $this->readResponse();
        if (substr($response, 0, 3) !== '354') {
            $this->setError("DATA command failed: $response");
            return false;
        }

        // Vytvorenie hlavičky a tela správy
        $headers = $this->buildHeaders($from, $to, $cc, $bcc, $subject, $contentType, $attachments);
        $message = $this->buildMessage($body, $contentType, $attachments);
        $fullMessage = $headers . $this->lineEnd . $message;

        // Odoslanie správy
        $this->sendCommand($fullMessage . $this->lineEnd . '.');

        $response = $this->readResponse();
        if (substr($response, 0, 3) !== '250') {
            $this->setError("Message not accepted: $response");
            return false;
        }

        // Uloženie správy do priečinka Sent, ak je saveEmail true a IMAP je nastavený
        if ($this->saveEmail && $this->imapClient !== null) {
            try {
                if (!$this->saveToSentFolder($fullMessage, $sentFolder)) {
                    $this->setError("Failed to save email to Sent folder");
                }
            } catch (Exception $e) {
                $this->setError("IMAP save to Sent folder failed: " . $e->getMessage());
            }
        }

        return true;
    }

    private function saveToSentFolder(string $message, string $sentFolder): bool
    {
        if (!$this->imapClient->connect() || !$this->imapClient->authenticate()) {
            return false;
        }

        $tag = 'A' . str_pad($this->imapClient->getTagCounter() + 1, 4, '0', STR_PAD_LEFT);
        $this->imapClient->sendCommand("$tag APPEND \"$sentFolder\" (\\Seen) {" . strlen($message) . "}");
        $response = $this->imapClient->readResponse($tag);

        if (strpos($response, '+') === false) {
            $this->setError("IMAP APPEND command failed: $response");
            return false;
        }

        $this->imapClient->sendCommand($message);
        $response = $this->imapClient->readResponse($tag);

        if (!$this->imapClient->isValidResponse($response)) {
            $this->setError("IMAP APPEND message failed: $response");
            return false;
        }

        return true;
    }

    private function buildHeaders(string $from, array $to, array $cc, array $bcc, string $subject, string $contentType, array $attachments = []): string
    {
        $headers = [];
        $headers[] = "From: $from";
        if (!empty($to)) {
            $headers[] = "To: " . implode(', ', $to);
        }
        if (!empty($cc)) {
            $headers[] = "Cc: " . implode(', ', $cc);
        }
        $headers[] = "Subject: " . $this->encodeHeader($subject);
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Date: " . date('r');

        if (!empty($attachments)) {
            $this->boundary = '==Multipart_Boundary_' . md5(uniqid());
            $headers[] = "Content-Type: multipart/mixed; boundary=\"{$this->boundary}\"";
        } else {
            $headers[] = "Content-Type: $contentType; charset=utf-8";
            $headers[] = "Content-Transfer-Encoding: 8bit";
        }

        return implode($this->lineEnd, $headers);
    }

    private function buildMessage(string $body, string $contentType, array $attachments): string
    {
        if (empty($attachments)) {
            return $body;
        }

        $message = [];
        $message[] = "--{$this->boundary}";
        $message[] = "Content-Type: $contentType; charset=utf-8";
        $message[] = "Content-Transfer-Encoding: 8bit";
        $message[] = "";
        $message[] = $body;
        $message[] = "";

        foreach ($attachments as $attachment) {
            if (is_file($attachment) && is_readable($attachment)) {
                $filename = basename($attachment);
                $fileContent = file_get_contents($attachment);
                $encodedContent = chunk_split(base64_encode($fileContent));

                $message[] = "--{$this->boundary}";
                $message[] = "Content-Type: " . $this->getMimeType($attachment) . "; name=\"$filename\"";
                $message[] = "Content-Transfer-Encoding: base64";
                $message[] = "Content-Disposition: attachment; filename=\"$filename\"";
                $message[] = "";
                $message[] = $encodedContent;
                $message[] = "";
            } else {
                $this->setError("Attachment $attachment not found or not readable");
            }
        }

        $message[] = "--{$this->boundary}--";
        return implode($this->lineEnd, $message);
    }

    private function getMimeType(string $file): string
    {
        if (function_exists('mime_content_type')) {
            $mimeType = mime_content_type($file);
            return $mimeType ?: 'application/octet-stream';
        }
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'txt' => 'text/plain',
        ];
        return $mimeTypes[$ext] ?? 'application/octet-stream';
    }

    private function encodeHeader(string $str): string
    {
        if (preg_match('/[^\x20-\x7E]/', $str)) {
            return '=?UTF-8?B?' . base64_encode($str) . '?=';
        }
        return $str;
    }

    private function sendHello(string $command): bool
    {
        $this->sendCommand("$command " . gethostname());
        $response = $this->readResponse();

        if (substr($response, 0, 3) !== '250') {
            $this->setError("$command not accepted: $response");
            return false;
        }

        return true;
    }

    private function sendCommand(string $command): bool
    {
        if (!$this->connection) {
            $this->setError("No connection available");
            return false;
        }

        $result = fwrite($this->connection, $command . $this->lineEnd);
        if ($this->debugLevel >= 2) {
            $this->debug("Sent: $command");
        }

        return $result !== false;
    }

    private function readResponse(int $size = 512): string
    {
        if (!$this->connection) {
            return '';
        }

        $response = '';
        while ($line = fgets($this->connection, $size)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }

        if ($this->debugLevel >= 2) {
            $this->debug("Received: $response");
        }

        return $response;
    }

    private function isValidResponse(string $response): bool
    {
        return substr($response, 0, 3) === '220';
    }

    private function setError(string $message): void
    {
        $this->errorMessage[] = $message;
        if ($this->debugLevel >= 1) {
            $this->debug("Error: $message");
        }
    }

    private function debug(string $message): void
    {
        error_log(htmlspecialchars($message));
    }

    public function getErrors(): array
    {
        return $this->errorMessage;
    }

    public function disconnect(): void
    {
        if ($this->isConnected) {
            $this->sendCommand('QUIT');
            fclose($this->connection);
            $this->connection = null;
            $this->isConnected = false;
        }
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}

?>