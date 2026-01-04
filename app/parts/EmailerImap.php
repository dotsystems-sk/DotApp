<?php
namespace Dotsystems\App\Parts;
use \Dotsystems\App\DotApp;

class EmailerImap
{
    private $serverHost = 'localhost';
    private $serverPort = 143;
    private $connectionTimeout = 30;
    private $connection = null;
    private $isConnected = false;
    private $debugLevel = 0;
    private $errorLog = [];
    private $lineEnd = "\r\n";
    private $username = '';
    private $password = '';
    private $secureMode = ''; // 'ssl' or 'tls'
    private $tagCounter = 0;
    private $selectedMailbox = '';

    public function __construct(string $host = 'localhost', int $port = 143, int $timeout = 30, string $secure = '')
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

        if ($this->secureMode === 'tls') {
            if (!$this->startTLS()) {
                return false;
            }
        }

        $this->isConnected = true;
        return true;
    }

    private function startTLS(): bool
    {
        $tag = $this->generateTag();
        $this->sendCommand("$tag STARTTLS");
        $response = $this->readResponse($tag);

        if (!$this->isValidResponse($response)) {
            $this->setError("STARTTLS failed: $response");
            return false;
        }

        if (!stream_socket_enable_crypto($this->connection, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
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

        $tag = $this->generateTag();
        // Bezpečné escapovanie prihlasovacích údajov
        $username = str_replace('"', '\\"', $this->username);
        $password = str_replace('"', '\\"', $this->password);
        $this->sendCommand("$tag LOGIN \"$username\" \"$password\"");
        $response = $this->readResponse($tag);

        if (!$this->isValidResponse($response)) {
            $this->setError("Authentication failed: $response");
            return false;
        }

        return true;
    }
	
	public function saveAttachment(int $messageId, int $attachmentIndex, string $path): bool
	{
		$email = $this->getEmail($messageId);
		if (isset($email['attachments'][$attachmentIndex])) {
			return file_put_contents($path, $email['attachments'][$attachmentIndex]['data']) !== false;
		}
		return false;
	}

    public function selectMailbox(string $mailbox = 'INBOX'): bool
    {
        if (!$this->isConnected || !$this->authenticate()) {
            return false;
        }

        if ($this->selectedMailbox === $mailbox) {
            return true;
        }

        $tag = $this->generateTag();
        $this->sendCommand("$tag SELECT \"$mailbox\"");
        $response = $this->readMultiLineResponse($tag);

        if (!$this->isValidResponse(end($response))) {
            $this->setError("SELECT mailbox failed: " . end($response));
            return false;
        }

        $this->selectedMailbox = $mailbox;
        return true;
    }

    public function getEmailList(string $mailbox = 'INBOX', int $limit = 10, int $offset = 0, string $criteria = 'RECENT'): array
    {
        if (!$this->selectMailbox($mailbox)) {
            return [];
        }

        $tag = $this->generateTag();
        $this->sendCommand("$tag SEARCH $criteria");
        $response = $this->readMultiLineResponse($tag);

        if (!$this->isValidResponse(end($response))) {
            $this->setError("SEARCH command failed: " . end($response));
            return [];
        }

        $messageIds = [];
        foreach ($response as $line) {
            if (preg_match('/^\* SEARCH (.+)/', $line, $matches)) {
                $messageIds = array_filter(explode(' ', trim($matches[1])), 'is_numeric');
                $messageIds = array_map('intval', $messageIds);
            }
        }

        // Zoradiť ID zostupne (najnovšie správy majú vyššie ID)
        rsort($messageIds);

        // Aplikovať stránkovanie
        $messageIds = array_slice($messageIds, $offset, $limit);

        $emails = [];
        if (!empty($messageIds)) {
            // Načítať hlavičky pre vybrané správy
            $range = implode(',', $messageIds);
            $tag = $this->generateTag();
            $this->sendCommand("$tag FETCH $range (FLAGS BODY.PEEK[HEADER])");
            $response = $this->readMultiLineResponse($tag);

            if (!$this->isValidResponse(end($response))) {
                $this->setError("FETCH headers failed: " . end($response));
                return [];
            }

            $currentMessageId = null;
            foreach ($response as $line) {
                if (preg_match('/^\* (\d+) FETCH .+ FLAGS \(([^)]+)\)/', $line, $matches)) {
                    $currentMessageId = (int)$matches[1];
                    $emails[$currentMessageId] = [
                        'headers' => [],
                        'flags' => explode(' ', trim($matches[2])),
                    ];
                } elseif ($currentMessageId && preg_match('/^([^:]+):\s*(.+)/', $line, $matches)) {
                    $emails[$currentMessageId]['headers'][strtolower($matches[1])] = trim($matches[2]);
                } elseif ($currentMessageId && preg_match('/^\s+(.+)/', $line, $matches)) {
                    $lastHeader = array_key_last($emails[$currentMessageId]['headers']);
                    $emails[$currentMessageId]['headers'][$lastHeader] .= ' ' . trim($matches[1]);
                }
            }
        }

        return $emails;
    }

    public function getEmail(int $messageId, string $mailbox = 'INBOX'): ?array
    {
        if (!$this->selectMailbox($mailbox)) {
            return null;
        }

        $tag = $this->generateTag();
        $this->sendCommand("$tag FETCH $messageId (FLAGS BODY.PEEK[HEADER] BODY.PEEK[TEXT] BODYSTRUCTURE)");
        $response = $this->readMultiLineResponse($tag);

        if (!$this->isValidResponse(end($response))) {
            $this->setError("FETCH command failed: " . end($response));
            return null;
        }

        return $this->parseEmail($response);
    }

    public function deleteEmail(int $messageId, string $mailbox = 'INBOX'): bool
    {
        if (!$this->selectMailbox($mailbox)) {
            return false;
        }

        $tag = $this->generateTag();
        $this->sendCommand("$tag STORE $messageId +FLAGS (\\Deleted)");
        $response = $this->readResponse($tag);

        if (!$this->isValidResponse($response)) {
            $this->setError("STORE command failed: $response");
            return false;
        }

        $tag = $this->generateTag();
        $this->sendCommand("$tag EXPUNGE");
        $response = $this->readResponse($tag);

        if (!$this->isValidResponse($response)) {
            $this->setError("EXPUNGE command failed: $response");
            return false;
        }

        return true;
    }

    private function parseEmail(array $response): array
    {
        $email = [
            'headers' => [],
            'body' => '',
            'flags' => [],
            'attachments' => [],
        ];
        $inHeaders = true;
        $currentHeader = '';
        $bodyLines = [];
        $structure = null;

        foreach ($response as $line) {
            if (preg_match('/^\* \d+ FETCH .+ FLAGS \(([^)]+)\)/', $line, $matches)) {
                $email['flags'] = explode(' ', trim($matches[1]));
                continue;
            }

            if (preg_match('/BODYSTRUCTURE\s+\((.+)\)/', $line, $matches)) {
                $structure = $this->parseBodyStructure($matches[1]);
                continue;
            }

            if ($inHeaders && $line === ')') {
                $inHeaders = false;
                continue;
            }

            if ($inHeaders) {
                if (preg_match('/^\s+(.+)/', $line, $matches)) {
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

        // Spracovanie príloh pomocou BODYSTRUCTURE
        if ($structure && isset($email['headers']['content-type']) && strpos($email['headers']['content-type'], 'multipart/') === 0) {
            $boundary = $this->extractBoundary($email['headers']['content-type']);
            if ($boundary) {
                $email['attachments'] = $this->parseAttachments($bodyLines, $boundary, $structure);
            }
        }

        return $email;
    }

    private function parseBodyStructure(string $structure): array
    {
        // Jednoduché spracovanie BODYSTRUCTURE (môže byť zložité, preto iba základ)
        $parts = [];
        // Predpokladáme, že štruktúra je v tvare (part1 part2 ...)
        // Pre komplexné štruktúry by bolo potrebné rekurzívne spracovanie
        if (preg_match_all('/\("([^"]+)"\s+"([^"]+)"\s+.*?"([^"]*)"\s*(\d*)/', $structure, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $parts[] = [
                    'type' => $match[1],
                    'subtype' => $match[2],
                    'name' => $match[3] ?: null,
                    'size' => $match[4] ?: 0,
                ];
            }
        }
        return $parts;
    }

    private function extractBoundary(string $contentType): ?string
    {
        if (preg_match('/boundary="([^"]+)"/', $contentType, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function parseAttachments(array $lines, string $boundary, array $structure): array
    {
        $attachments = [];
        $currentAttachment = null;
        $inAttachment = false;

        foreach ($lines as $line) {
            if (strpos($line, "--$boundary") === 0) {
                if ($inAttachment && $currentAttachment) {
                    // Dekódovať obsah, ak je base64
                    if (isset($currentAttachment['headers']['content-transfer-encoding']) &&
                        strtolower($currentAttachment['headers']['content-transfer-encoding']) === 'base64') {
                        $currentAttachment['data'] = base64_decode($currentAttachment['data']);
                    }
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
            if (isset($currentAttachment['headers']['content-transfer-encoding']) &&
                strtolower($currentAttachment['headers']['content-transfer-encoding']) === 'base64') {
                $currentAttachment['data'] = base64_decode($currentAttachment['data']);
            }
            $attachments[] = $currentAttachment;
        }

        // Priradiť názvy súborov zo štruktúry
        foreach ($structure as $index => $part) {
            if ($part['name'] && isset($attachments[$index])) {
                $attachments[$index]['filename'] = $part['name'];
                $attachments[$index]['size'] = $part['size'];
            }
        }

        return $attachments;
    }

    public function getTagCounter(): int
    {
        return $this->tagCounter;
    }

    public function generateTag(): string
    {
        return 'A' . str_pad(++$this->tagCounter, 4, '0', STR_PAD_LEFT);
    }

    public function sendCommand(string $command): bool
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

    public function readResponse(string $tag = null, int $size = 512): string
    {
        if (!$this->connection) {
            return '';
        }

        $response = '';
        while ($line = fgets($this->connection, $size)) {
            $response .= $line;
            if ($tag && strpos($line, "$tag ") === 0) {
                break;
            }
            if (!$tag && strpos($line, '* ') !== 0) {
                break;
            }
        }

        if ($this->debugLevel >= 2) {
            $this->debug("Received: $response");
        }

        return $response;
    }

    public function readMultiLineResponse(string $tag, int $size = 512): array
    {
        $response = [];
        while ($line = fgets($this->connection, $size)) {
            $response[] = trim($line);
            if (strpos($line, "$tag ") === 0) {
                break;
            }
        }

        if ($this->debugLevel >= 2) {
            $this->debug("Received multiline: " . implode("\n", $response));
        }

        return $response;
    }

    public function isValidResponse(string $response): bool
    {
        return strpos($response, 'OK') !== false;
    }

    public function setError(string $message): void
    {
        $this->errorLog[] = $message;
        if ($this->debugLevel >= 1) {
            $this->debug("Error: $message");
        }
    }

    public function debug(string $message): void
    {
        DotApp:DotApp()->Logger->debug(htmlspecialchars($message));
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
            $tag = $this->generateTag();
            $this->sendCommand("$tag LOGOUT");
            fclose($this->connection);
            $this->connection = null;
            $this->isConnected = false;
            $this->selectedMailbox = '';
        }
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}

?>
