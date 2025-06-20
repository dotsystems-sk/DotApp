<?php
namespace Dotsystems\App\Parts;

class EmailerSmtp {
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
    private $authType = 'LOGIN'; // Options: 'LOGIN', 'PLAIN', 'NTLM'
    private $imapClient = null; // EmailerImap instance for saving
    private $saveEmail = false; // Optional saving to Sent folder

    /**
     * Constructor initializes SMTP settings.
     * @param string $host Server host (default: 'localhost').
     * @param int $port Server port (default: 25).
     * @param int $timeout Connection timeout (default: 30).
     * @param string $secure Secure mode ('tls' or 'ssl', default: '').
     * @param bool $saveEmail Save email to Sent folder (default: false).
     */
    public function __construct(string $host = 'localhost', int $port = 25, int $timeout = 30, string $secure = '', bool $saveEmail = false) {
        $this->serverHost = $host;
        $this->serverPort = $port;
        $this->connectionTimeout = $timeout;
        $this->secureMode = $secure;
        $this->saveEmail = $saveEmail;
    }

    /**
     * Sets the IMAP client for saving emails.
     * @param EmailerImap $imapClient IMAP client instance.
     */
    public function setImapClient(EmailerImap $imapClient): void {
        $this->imapClient = $imapClient;
    }

    /**
     * Sets authentication credentials.
     * @param string $username Username.
     * @param string $password Password.
     * @param string $authType Authentication type (default: 'LOGIN').
     */
    public function setCredentials(string $username, string $password, string $authType = 'LOGIN'): void {
        $this->username = $username;
        $this->password = $password;
        $this->authType = in_array($authType, ['LOGIN', 'PLAIN', 'NTLM']) ? $authType : 'LOGIN';
    }

    /**
     * Sets the debug level.
     * @param int $level Debug level (0-4).
     */
    public function setDebugLevel(int $level): void {
        $this->debugLevel = max(0, min($level, 4));
    }

    /**
     * Connects to the SMTP server.
     * @return bool Success status.
     */
    public function connect(): bool {
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

    /**
     * Initiates TLS encryption.
     * @return bool Success status.
     */
    private function startTLS(): bool {
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

    /**
     * Authenticates with the SMTP server.
     * @return bool Success status.
     */
    public function authenticate(): bool {
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

    /**
     * Performs LOGIN authentication.
     * @return bool Success status.
     */
    private function authLogin(): bool {
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

    /**
     * Performs PLAIN authentication.
     * @return bool Success status.
     */
    private function authPlain(): bool {
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

    /**
     * Performs NTLM authentication.
     * @return bool Success status.
     */
    private function authNTLM(): bool {
        $this->setError("NTLM authentication not implemented");
        return false;
    }

    /**
     * Sends an email.
     * @param string $from Sender email address.
     * @param array $to Recipient(s).
     * @param array $cc CC recipient(s).
     * @param array $bcc BCC recipient(s).
     * @param string $subject Email subject.
     * @param string $body Email body.
     * @param string $contentType Content type (default: 'text/plain').
     * @param array $attachments Attachments.
     * @param string Stu sa string $sentFolder Sent folder (default: 'Sent').
     * @return bool Success status.
     */
    public function sendEmail(string $from, array $to, array $cc, array $bcc, string $subject, string $body, string $contentType = 'text/plain', array $attachments = [], string $sentFolder = 'Sent'): bool {
        if (!$this->isConnected && !$this->connect()) {
            return false;
        }

        if ($this->username && !$this->authenticate()) {
            return false;
        }

        // Send MAIL FROM
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

        // Send RCPT TO
        foreach ([$to, $cc, $bcc] as $recipients) {
            foreach ($recipients as $recipient) {
                $recipient = trim($recipient);
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

        // Send DATA
        if (!$this->sendCommand('DATA')) {
            return false;
        }
        $response = $this->readResponse();
        if (substr($response, 0, 3) !== '354') {
            $this->setError("DATA command failed: $response");
            return false;
        }

        // Build and send message
        $headers = $this->buildHeaders($from, $to, $cc, $bcc, $subject, $contentType, $attachments);
        $message = $this->buildMessage($body, $contentType, $attachments);
        $fullMessage = $headers . $this->lineEnd . $message;

        $this->sendCommand($fullMessage . $this->lineEnd . '.');

        $response = $this->readResponse();
        if (substr($response, 0, 3) !== '250') {
            $this->setError("Message not accepted: $response");
            return false;
        }

        // Save to Sent folder if enabled and IMAP is set
        if ($this->saveEmail && $this->imapClient !== null) {
            try {
                if (!$this->saveToSentFolder($fullMessage, $sentFolder)) {
                    $this->setError("Failed to save email to Sent folder");
                    return false;
                }
            } catch (Exception $e) {
                $this->setError("IMAP save to Sent folder failed: " . $e->getMessage());
                return false;
            }
        }

        return true;
    }

    /**
     * Saves email to Sent folder via IMAP.
     * @param string $message Email message.
     * @param string $sentFolder Sent folder name.
     * @return bool Success status.
     */
    private function saveToSentFolder(string $message, string $sentFolder): bool {
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

    /**
     * Builds email headers.
     * @param string $from Sender email address.
     * @param array $to Recipient(s).
     * @param array $cc CC recipient(s).
     * @param array $bcc BCC recipient(s).
     * @param string $subject Email subject.
     * @param string $contentType Content type.
     * @param array $attachments Attachments.
     * @return string Headers.
     */
    private function buildHeaders(string $from, array $to, array $cc, array $bcc, string $subject, string $contentType, array $attachments = []): string {
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

    /**
     * Builds email message body.
     * @param string $body Email body.
     * @param string $contentType Content type.
     * @param array $attachments Attachments.
     * @return string Message body.
     */
    private function buildMessage(string $body, string $contentType, array $attachments): string {
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

    /**
     * Determines MIME type of a file.
     * @param string $file File path.
     * @return string MIME type.
     */
    private function getMimeType(string $file): string {
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

    /**
     * Encodes email header for non-ASCII characters.
     * @param string $str Header string.
     * @return string Encoded header.
     */
    private function encodeHeader(string $str): string {
        if (preg_match('/[^\x20-\x7E]/', $str)) {
            return '=?UTF-8?B?' . base64_encode($str) . '?=';
        }
        return $str;
    }

    /**
     * Sends HELO or EHLO command.
     * @param string $command Command type ('HELO' or 'EHLO').
     * @return bool Success status.
     */
    private function sendHello(string $command): bool {
        $this->sendCommand("$command " . gethostname());
        $response = $this->readResponse();

        if (substr($response, 0, 3) !== '250') {
            $this->setError("$command not accepted: $response");
            return false;
        }

        return true;
    }

    /**
     * Sends an SMTP command.
     * @param string $command Command to send.
     * @return bool Success status.
     */
    private function sendCommand(string $command): bool {
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

    /**
     * Reads the server response.
     * @param int $size Buffer size (default: 512).
     * @return string Response.
     */
    private function readResponse(int $size = 512): string {
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

    /**
     * Checks if the response is valid.
     * @param string $response Server response.
     * @return bool Validity status.
     */
    private function isValidResponse(string $response): bool {
        return substr($response, 0, 3) === '220';
    }

    /**
     * Sets an error message.
     * @param string $message Error message.
     */
    private function setError(string $message): void {
        $this->errorMessage[] = $message;
        if ($this->debugLevel >= 1) {
            $this->debug("Error: $message");
        }
    }

    /**
     * Logs a debug message.
     * @param string $message Debug message.
     */
    private function debug(string $message): void {
        error_log(htmlspecialchars($message));
    }

    /**
     * Returns the list of errors.
     * @return array Errors.
     */
    public function getErrors(): array {
        return $this->errorMessage;
    }

    /**
     * Disconnects from the SMTP server.
     */
    public function disconnect(): void {
        if ($this->isConnected) {
            $this->sendCommand('QUIT');
            fclose($this->connection);
            $this->connection = null;
            $this->isConnected = false;
        }
    }

    /**
     * Destructor to ensure disconnection.
     */
    public function __destruct() {
        $this->disconnect();
    }
}
?>