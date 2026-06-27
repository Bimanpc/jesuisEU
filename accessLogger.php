<?php
/**
 * AccessLogger - GDPR Article 30: Records of Processing Activities
 * Logs all access to personal data for audit trail compliance.
 */
class AccessLogger
{
    private string $logFile;

    public function __construct(string $logFile)
    {
        $this->logFile = $logFile;
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * Log a data access event.
     */
    public function log(
        string $action,
        string $resource,
        ?string $userId = null,
        array $details = []
    ): void {
        $entry = [
            'timestamp'   => date('c'),
            'user_id'     => $userId ?? ($_SESSION['user_id'] ?? 'anonymous'),
            'user_ip'     => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'action'      => $action,
            'resource'    => $resource,
            'details'     => $details,
        ];

        $this->appendLog($entry);
    }

    /**
     * Retrieve all log entries.
     */
    public function getLogs(): array
    {
        if (!file_exists($this->logFile)) {
            return [];
        }

        $content = file_get_contents($this->logFile);
        $lines = array_filter(explode(PHP_EOL, $content));
        $logs = [];

        foreach ($lines as $line) {
            $decoded = json_decode($line, true);
            if ($decoded !== null) {
                $logs[] = $decoded;
            }
        }

        return $logs;
    }

    /**
     * Delete all logs (right to erasure for log data).
     */
    public function purgeAllLogs(): void
    {
        if (file_exists($this->logFile)) {
            file_put_contents($this->logFile, '');
        }
    }

    private function appendLog(array $entry): void
    {
        $line = json_encode($entry) . PHP_EOL;
        file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }
}
