<?php
namespace Dotsystems\App\Parts;
use \Dotsystems\App\DotApp;
use InvalidArgumentException;

class Emailer {
    private $sender = null;
    private $receiver = null;
    private $debugLevel = 0;
    private $errors = [];
    private $smtpConfig = [];
    private $receiverConfig = [];
    private $receiverProtocol = 'imap';

    /**
     * Constructor initializes SMTP and receiver configurations.
     * @param array $smtpConfig SMTP configuration.
     * @param array $receiverConfig Receiver configuration.
     */
    public function __construct(array $smtpConfig = [], array $receiverConfig = []) {
        $this->smtpConfig = $smtpConfig;
        $this->receiverConfig = $receiverConfig;

        // Validate configurations
        if (!empty($smtpConfig)) {
            $this->validateConfig($smtpConfig, 'SMTP');
        }
        if (!empty($receiverConfig)) {
            $this->validateConfig($receiverConfig, 'Receiver');
            $this->receiverProtocol = strtolower($receiverConfig['protocol'] ?? 'imap');
        }

        // Initialize sender (SMTP)
        if (!empty($smtpConfig)) {
            $smtpHost = $smtpConfig['host'] ?? 'localhost';
            $smtpPort = $smtpConfig['port'] ?? 25;
            $smtpTimeout = $smtpConfig['timeout'] ?? 30;
            $smtpSecure = $smtpConfig['secure'] ?? '';
            $saveEmail = $smtpConfig['saveEmail'] ?? false;

            try {
                $this->sender = new EmailerSmtp($smtpHost, $smtpPort, $smtpTimeout, $smtpSecure, $saveEmail);
            } catch (Exception $e) {
                throw new \InvalidArgumentException("Failed to initialize SMTP sender: " . $e->getMessage());
            }
        }

        // Initialize receiver (IMAP/POP3)
        if (!empty($receiverConfig)) {
            $receiverHost = $receiverConfig['host'] ?? 'localhost';
            $receiverPort = $receiverConfig['port'] ?? ($this->receiverProtocol === 'pop3' ? 110 : 143);
            $receiverTimeout = $receiverConfig['timeout'] ?? 30;
            $receiverSecure = $receiverConfig['secure'] ?? '';

            try {
                $this->receiver = $this->receiverProtocol === 'pop3'
                    ? new EmailerPop3($receiverHost, $receiverPort, $receiverTimeout, $receiverSecure)
                    : new EmailerImap($receiverHost, $receiverPort, $receiverTimeout, $receiverSecure);
                // Set IMAP client for SMTP if IMAP
                if ($this->receiverProtocol === 'imap') {
                    $this->sender->setImapClient($this->receiver);
                }
            } catch (Exception $e) {
                throw new \InvalidArgumentException("Failed to initialize {$this->receiverProtocol} receiver: " . $e->getMessage());
            }
        }
    }

    /**
     * Initializes the SMTP sender.
     */
    private function initSender(): void {
        if ($this->sender === null) {
            if (empty($this->smtpConfig)) {
                throw new \InvalidArgumentException("SMTP configuration is required to initialize sender.");
            }

            $smtpHost = $this->smtpConfig['host'] ?? 'localhost';
            $smtpPort = $this->smtpConfig['port'] ?? 25;
            $smtpTimeout = $this->smtpConfig['timeout'] ?? 30;
            $smtpSecure = $this->smtpConfig['secure'] ?? '';
            $saveEmail = $this->smtpConfig['saveEmail'] ?? false;

            try {
                $this->sender = new EmailerSmtp($smtpHost, $smtpPort, $smtpTimeout, $smtpSecure, $saveEmail);
                $this->sender->setDebugLevel($this->debugLevel);
            } catch (Exception $e) {
                throw new \InvalidArgumentException("Failed to initialize SMTP sender: " . $e->getMessage());
            }
        }
    }

    /**
     * Initializes the receiver (IMAP/POP3).
     */
    private function initReceiver(): void {
        if ($this->receiver === null) {
            if (empty($this->receiverConfig)) {
                throw new \InvalidArgumentException("Receiver configuration is required to initialize receiver.");
            }

            $receiverHost = $this->receiverConfig['host'] ?? 'localhost';
            $receiverPort = $this->receiverConfig['port'] ?? ($this->receiverProtocol === 'pop3' ? 110 : 143);
            $receiverTimeout = $this->receiverConfig['timeout'] ?? 30;
            $receiverSecure = $this->receiverConfig['secure'] ?? '';

            try {
                $this->receiver = $this->receiverProtocol === 'pop3'
                    ? new EmailerPop3($receiverHost, $receiverPort, $receiverTimeout, $receiverSecure)
                    : new EmailerImap($receiverHost, $receiverPort, $receiverTimeout, $receiverSecure);
                $this->receiver->setDebugLevel($this->debugLevel);
                // Set IMAP client for SMTP if IMAP
                if ($this->receiverProtocol === 'imap' && $this->sender !== null) {
                    $this->sender->setImapClient($this->receiver);
                }
            } catch (Exception $e) {
                throw new \InvalidArgumentException("Failed to initialize {$this->receiverProtocol} receiver: " . $e->getMessage());
            }
        }
    }

    /**
     * Sets credentials for SMTP and receiver.
     * @param string $smtpUsername SMTP username.
     * @param string $smtpPassword SMTP password.
     * @param string $receiverUsername Receiver username.
     * @param string $receiverPassword Receiver password.
     * @param string $smtpAuthType SMTP authentication type (default: 'LOGIN').
     */
    public function setCredentials(string $smtpUsername = '', string $smtpPassword = '', $receiverUsername = null, $receiverPassword = null, string $smtpAuthType = 'LOGIN'): void {
        try {
            if ($smtpUsername && $smtpPassword) {
                $this->initSender();
                $this->sender->setCredentials($smtpUsername, $smtpPassword, $smtpAuthType);
            }
            if ($receiverUsername && $receiverPassword) {
                $this->initReceiver();
                $this->receiver->setCredentials($receiverUsername, $receiverPassword);
            }
        } catch (Exception $e) {
            $this->setError("Failed to set credentials: " . $e->getMessage());
        }
    }

    /**
     * Sets the debug level.
     * @param int $level Debug level (0-4).
     */
    public function setDebugLevel(int $level): void {
        $this->debugLevel = max(0, min($level, 4));
        if ($this->sender !== null) {
            $this->sender->setDebugLevel($this->debugLevel);
        }
        if ($this->receiver !== null) {
            $this->receiver->setDebugLevel($this->debugLevel);
        }
    }

    /**
     * Switches the receiver protocol (IMAP/POP3).
     * @param string $protocol Protocol ('imap' or 'pop3').
     * @throws InvalidArgumentException If protocol is invalid.
     */
    public function switchReceiverProtocol(string $protocol): void {
        $protocol = strtolower($protocol);
        if (!in_array($protocol, ['imap', 'pop3'])) {
            throw new \InvalidArgumentException("Invalid protocol: $protocol. Use 'imap' or 'pop3'.");
        }

        if ($this->receiverProtocol !== $protocol) {
            if ($this->receiver !== null) {
                $this->receiver->disconnect();
                $this->receiver = null;
            }
            $this->receiverProtocol = $protocol;
            $this->receiverConfig['protocol'] = $protocol;
            $this->initReceiver();
            // Update IMAP client in SMTP
            if ($this->sender !== null) {
                $this->sender->setImapClient($this->receiverProtocol === 'imap' ? $this->receiver : null);
            }
        }
    }

    /**
     * Sends an email.
     * @param string $from Sender email address.
     * @param mixed $to Recipient(s).
     * @param array $cc CC recipient(s).
     * @param array $bcc BCC recipient(s).
     * @param string $subject Email subject.
     * @param string $body Email body.
     * @param string $contentType Content type (default: 'text/plain').
     * @param array $attachments Attachments.
     * @param string $sentFolder Sent folder (default: 'Sent').
     * @return bool Success status.
     */
    public function sendEmail(string $from, $to, array $cc = [], array $bcc = [], string $subject, string $body, string $contentType = 'text/plain', array $attachments = [], string $sentFolder = 'Sent'): bool {
        if (empty($to) && empty($cc) && empty($bcc)) {
            $this->setError("No recipients specified.");
            return false;
        }

        try {
            $this->initSender();
            $result = $this->sender->sendEmail($from, $to, $cc, $bcc, $subject, $body, $contentType, $attachments, $sentFolder);
            if (!$result) {
                $this->errors = array_merge($this->errors, $this->sender->getErrors());
            }
            return $result;
        } catch (Exception $e) {
            $this->setError("Send email failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves a list of emails.
     * @param string $mailbox Mailbox (default: 'INBOX').
     * @param int $limit Number of emails to retrieve (default: 10).
     * @param int $offset Offset (default: 0).
     * @param string $criteria Search criteria (default: 'RECENT').
     * @return array List of emails.
     */
    public function getEmailList(string $mailbox = 'INBOX', int $limit = 10, int $offset = 0, string $criteria = 'RECENT'): array {
        try {
            $this->initReceiver();
            $result = $this->receiver->getEmailList($mailbox, $limit, $offset, $criteria);
            if (empty($result)) {
                $this->errors = array_merge($this->errors, $this->receiver->getErrors());
            }
            return $result;
        } catch (Exception $e) {
            $this->setError("Get email list failed ({$this->receiverProtocol}): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Retrieves a specific email.
     * @param int $messageId Message ID.
     * @param string $mailbox Mailbox (default: 'INBOX').
     * @return array|null Email data or null on failure.
     */
    public function getEmail(int $messageId, string $mailbox = 'INBOX'): ?array {
        if ($messageId <= 0) {
            $this->setError("Invalid message ID: $messageId");
            return null;
        }

        try {
            $this->initReceiver();
            $result = $this->receiver->getEmail($messageId, $mailbox);
            if (!$result) {
                $this->errors = array_merge($this->errors, $this->receiver->getErrors());
            }
            return $result;
        } catch (Exception $e) {
            $this->setError("Get email failed ({$this->receiverProtocol}): " . $e->getMessage());
            return null;
        }
    }

    /**
     * Deletes an email.
     * @param int $messageId Message ID.
     * @param string $mailbox Mailbox (default: 'INBOX').
     * @return bool Success status.
     */
    public function deleteEmail(int $messageId, string $mailbox = 'INBOX'): bool {
        if ($messageId <= 0) {
            $this->setError("Invalid message ID: $messageId");
            return false;
        }

        try {
            $this->initReceiver();
            $result = $this->receiver->deleteEmail($messageId, $mailbox);
            if (!$result) {
                $this->errors = array_merge($this->errors, $this->receiver->getErrors());
            }
            return $result;
        } catch (Exception $e) {
            $this->setError("Delete email failed ({$this->receiverProtocol}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Saves an email attachment to a file.
     * @param int $messageId Message ID.
     * @param int $attachmentIndex Attachment index.
     * @param string $path File path to save the attachment.
     * @param string $mailbox Mailbox (default: 'INBOX').
     * @return bool Success status.
     */
    public function saveAttachment(int $messageId, int $attachmentIndex, string $path, string $mailbox = 'INBOX'): bool {
        try {
            $this->initReceiver();
            $email = $this->receiver->getEmail($messageId, $mailbox);
            if (isset($email['attachments'][$attachmentIndex])) {
                return file_put_contents($path, $email['attachments'][$attachmentIndex]['data']) !== false;
            }
            $this->setError("Attachment at index $attachmentIndex not found for message $messageId");
            return false;
        } catch (Exception $e) {
            $this->setError("Failed to save attachment: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Returns the list of errors.
     * @return array Errors.
     */
    public function getErrors(): array {
        return $this->errors;
    }

    /**
     * Sets an error message.
     * @param string $message Error message.
     */
    private function setError(string $message): void {
        $this->errors[] = $message;
        if ($this->debugLevel >= 1) {
            $this->debug("Error: $message");
        }
    }

    /**
     * Logs a debug message.
     * @param string $message Debug message.
     */
    private function debug(string $message): void {
        DotApp:DotApp()->Logger->debug(htmlspecialchars($message));
    }

    /**
     * Validates configuration settings.
     * @param array $config Configuration array.
     * @param string $type Configuration type ('SMTP' or 'Receiver').
     * @throws InvalidArgumentException If configuration is invalid.
     */
    private function validateConfig(array $config, string $type): void {
        if (isset($config['host']) && (!is_string($config['host']) || empty(trim($config['host'])))) {
            throw new \InvalidArgumentException("$type configuration: Invalid or empty host.");
        }
        if (isset($config['port']) && (!is_int($config['port']) || $config['port'] <= 0)) {
            throw new \InvalidArgumentException("$type configuration: Invalid port number.");
        }
        if (isset($config['timeout']) && (!is_int($config['timeout']) || $config['timeout'] <= 0)) {
            throw new \InvalidArgumentException("$type configuration: Invalid timeout value.");
        }
        if (isset($config['secure']) && !in_array($config['secure'], ['', 'ssl', 'tls'])) {
            throw new \InvalidArgumentException("$type configuration: Invalid secure mode. Use 'ssl', 'tls', or empty string.");
        }
        if ($type === 'Receiver' && isset($config['protocol']) && !in_array(strtolower($config['protocol']), ['imap', 'pop3'])) {
            throw new \InvalidArgumentException("Receiver configuration: Invalid protocol. Use 'imap' or 'pop3'.");
        }
        if ($type === 'SMTP' && isset($config['saveEmail']) && !is_bool($config['saveEmail'])) {
            throw new \InvalidArgumentException("SMTP configuration: saveEmail must be a boolean.");
        }
    }

    /**
     * Disconnects sender and receiver.
     */
    public function disconnect(): void {
        try {
            if ($this->sender !== null) {
                $this->sender->disconnect();
            }
            if ($this->receiver !== null) {
                $this->receiver->disconnect();
            }
        } catch (Exception $e) {
            $this->setError("Disconnect failed: " . $e->getMessage());
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
