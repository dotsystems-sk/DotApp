<?php
namespace Dotsystems\App\Parts;

class EmailerPop3 {
    private $serverHost = 'localhost';
    private $serverPort = 110;
    private $connectionTimeout = 30;
    private $connection = null;
    private $isConnected = false;
    private $debugLevel = 0;
    private $errorLog = [];
    private $lineEnd = "\r\n";
    private $username = '';
    private $password = '';
    private $secureMode = ''; // 'ssl' or 'tls'

    public function __construct(string $host = 'localhost', int $port = 110, int $timeout = 30, string $secure = '')
    {
        $this->serverHost = $host;
        $this->serverPort = $port;
        $this->connectionTimeout = $timeout;
        $this->secureMode = $secure;
    }

    public function setCredentials(string $username, string $password): void
    {
        $this->username = $username;
        $this->password = $password;
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
        set_error_handler([$this, 'handleError']);

        $this->connection = @fsockopen($host, $this->serverPort, $errno, $errstr, $this->connectionTimeout);

        restore_error_handler();

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

        $this->isConnected = true;
        return true;
    }

    public function authenticate(): bool
    {
        if (!$this->isConnected) {
            $this->setError("Cannot authenticate: Not connected to server");
            return false;
        }

        $this->sendCommand("USER {$this->username}");
        $response = $this->readResponse();

        if (!$this->isValidResponse($response)) {
            $this->setError("Username not accepted: $response");
            return false;
        }

        $this->sendCommand("PASS {$this->password}");
        $response = $this->readResponse();

        if (!$this->isValidResponse($response)) {
            $this->setError("Password not accepted: $response");
            return false;
        }

        return true;
    }

    public function getEmailList(string $mailbox = 'INBOX', int $limit = 10, int $offset = 0, string $criteria = 'RECENT'): array
    {
        if (!$this->isConnected || !$this->authenticate()) {
            return [];
        }

        $this->sendCommand('LIST');
        $response = $this->readMultiLineResponse();

        if (!$this->isValidResponse($response[0])) {
            $this->setError("LIST command failed: {$response[0]}");
            return [];
        }

        $emails = [];
        $messageIds = [];
        foreach (array_slice($response, 1) as $line) {
            if (preg_match('/^(\d+)\s+(\d+)/', $line, $matches)) {
                $messageIds[$matches[1]] = $matches[2]; // číslo správy => veľkosť
            }
        }

        // Zoradiť ID zostupne (najnovšie správy majú vyššie ID)
        krsort($messageIds);

        // Aplikovať stránkovanie
        $messageIds = array_slice($messageIds, $offset, $limit, true);

        // Použiť TOP pre hlavičky, ak je criteria RECENT alebo ALL
        if ($criteria === 'RECENT' || $criteria === 'ALL') {
            foreach ($messageIds as $messageId => $size) {
                $this->sendCommand("TOP $messageId 0");
                $response = $this->readMultiLineResponse();

                if ($this->isValidResponse($response[0])) {
                    $emails[$messageId] = [
                        'size' => $size,
                        'headers' => $this->parseHeaders($response),
                    ];
                } else {
                    $this->setError("TOP command failed for message $messageId: {$response[0]}");
                }
            }
        }

        return $emails;
    }
	
	private function parseHeaders(array $response): array
    {
        $headers = [];
        $currentHeader = '';

        foreach ($response as $line) {
            if ($line === '') {
                break; // Koniec hlavičiek
            }
            if (preg_match('/^\s+(.+)/', $line, $matches)) {
                $headers[$currentHeader] .= ' ' . trim($matches[1]);
            } elseif (preg_match('/^([^:]+):\s*(.+)/', $line, $matches)) {
                $currentHeader = strtolower(trim($matches[1]));
                $headers[$currentHeader] = trim($matches[2]);
            }
        }

        return $headers;
    }
	
	public function saveAttachment(int $messageId, int $attachmentIndex, string $path): bool
	{
		$email = $this->getEmail($messageId);
		if (isset($email['attachments'][$attachmentIndex])) {
			return file_put_contents($path, $email['attachments'][$attachmentIndex]['data']) !== false;
		}
		return false;
	}

    public function getEmail(int $messageId): ?array
    {
        if (!$this->isConnected || !$this->authenticate()) {
            return null;
        }

        $this->sendCommand("RETR $messageId");
        $response = $this->readMultiLineResponse();

        if (!$this->isValidResponse($response[0])) {
            $this->setError("RETR command failed: {$response[0]}");
            return null;
        }

        return $this->parseEmail($response);
    }

    public function deleteEmail(int $messageId): bool
    {
        if (!$this->isConnected || !$this->authenticate()) {
            return false;
        }

        $this->sendCommand("DELE $messageId");
        $response = $this->readResponse();

        if (!$this->isValidResponse($response)) {
            $this->setError("DELE command failed: $response");
            return false;
        }

        return true;
    }

    private function parseEmail(array $response): array
    {
        $email = [
            'headers' => [],
            'body' => '',
            'attachments' => []
        ];
        $inHeaders = true;
        $currentHeader = '';
        $bodyLines = [];

        foreach ($response as $line) {
            if ($inHeaders && $line === '') {
                $inHeaders = false;
                continue;
            }

            if ($inHeaders) {
                if (preg_match('/^\s+(.+)/', $line, $matches)) {
                    // Pokračovanie hlavičky
                    $email['headers'][$currentHeader] .= ' ' . trim($matches[1]);
                } elseif (preg_match('/^([^:]+):\s*(.+)/', $line, $matches)) {
                    $currentHeader = strtolower(trim($matches[1]));
                    $email['headers'][$currentHeader] = trim($matches[2]);
                }
            } else {
                $bodyLines[] = $line;
            }
        }

        $email['body'] = implode($this->lineEnd, $bodyLines);

        // Jednoduché spracovanie príloh (ak je multipart)
        if (isset($email['headers']['content-type']) && strpos($email['headers']['content-type'], 'multipart/') === 0) {
            $boundary = $this->extractBoundary($email['headers']['content-type']);
            if ($boundary) {
                $email['attachments'] = $this->parseAttachments($bodyLines, $boundary);
            }
        }

        return $email;
    }

    private function extractBoundary(string $contentType): ?string
    {
        if (preg_match('/boundary="([^"]+)"/', $contentType, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function parseAttachments(array $lines, string $boundary): array
    {
        $attachments = [];
        $currentAttachment = null;
        $inAttachment = false;

        foreach ($lines as $line) {
            if (strpos($line, "--$boundary") === 0) {
                if ($inAttachment && $currentAttachment) {
                    $attachments[] = $currentAttachment;
                }
                $inAttachment = true;
                $currentAttachment = ['headers' => [], 'data' => ''];
                continue;
            }

            if ($inAttachment) {
                if ($line === '' && empty($currentAttachment['data'])) {
                    continue;
                }
                if (preg_match('/^([^:]+):\s*(.+)/', $line, $matches)) {
                    $currentAttachment['headers'][strtolower($matches[1])] = $matches[2];
                } else {
                    $currentAttachment['data'] .= $line . $this->lineEnd;
                }
            }
        }

        if ($inAttachment && $currentAttachment) {
            $attachments[] = $currentAttachment;
        }

        return $attachments;
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

        $response = fgets($this->connection, $size);
        if ($this->debugLevel >= 2) {
            $this->debug("Received: $response");
        }

        return $response;
    }

    private function readMultiLineResponse(int $size = 512): array
    {
        $response = [];
        while ($line = fgets($this->connection, $size)) {
            $response[] = trim($line);
            if ($line === ".\r\n") {
                break;
            }
        }

        if ($this->debugLevel >= 2) {
            $this->debug("Received multiline: " . implode("\n", $response));
        }

        return $response;
    }

    private function isValidResponse(string $response): bool
    {
        return substr($response, 0, 3) === '+OK';
    }

    private function setError(string $message): void
    {
        $this->errorLog[] = $message;
        if ($this->debugLevel >= 1) {
            $this->debug("Error: $message");
        }
    }

    private function debug(string $message): void
    {
        echo htmlspecialchars($message) . "<br />\n";
    }

    private function handleError(int $errno, string $errstr, string $errfile, int $errline): void
    {
        $this->setError("PHP Warning: $errstr in $errfile on line $errline");
    }

    public function getErrors(): array
    {
        return $this->errorLog;
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