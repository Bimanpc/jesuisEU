<?php
/**
 * GdprSheetsViewer - Main application class
 * 
 * Features:
 * - Upload and parse CSV files
 * - Display data with PII masking (toggleable with justification)
 * - Export data (GDPR right to portability)
 * - Delete data (GDPR right to erasure)
 * - Access logging for audit trail
 */
class GdprSheetsViewer
{
    private AccessLogger $logger;
    private PiiMasker $masker;

    public function __construct(AccessLogger $logger, PiiMasker $masker)
    {
        $this->logger = $logger;
        $this->masker = $masker;
    }

    /**
     * Handle uploaded CSV file.
     */
    public function handleUpload(array $file): array
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Upload failed.'];
        }

        if ($file['size'] > MAX_FILE_SIZE) {
            return ['success' => false, 'message' => 'File exceeds maximum size.'];
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_EXTENSIONS)) {
            return ['success' => false, 'message' => 'Only CSV files are allowed.'];
        }

        $safeName = $this->sanitizeFilename($file['name']);
        $dest = UPLOAD_DIR . uniqid('sheet_', true) . '_' . $safeName;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return ['success' => false, 'message' => 'Failed to save file.'];
        }

        $this->logger->log('upload', basename($dest), null, [
            'original_name' => $file['name'],
            'size'          => $file['size'],
        ]);

        return ['success' => true, 'file' => basename($dest)];
    }

    /**
     * Parse CSV file into associative array.
     */
    public function parseCsv(string $filepath): array
    {
        $fullPath = UPLOAD_DIR . basename($filepath);

        if (!file_exists($fullPath)) {
            return ['error' => 'File not found.'];
        }

        $handle = fopen($fullPath, 'r');
        if ($handle === false) {
            return ['error' => 'Unable to open file.'];
        }

        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            return ['error' => 'Empty or invalid CSV.'];
        }

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            // Skip empty rows
            if (count(array_filter($row)) === 0) {
                continue;
            }
            $rows[] = $row;
        }
        fclose($handle);

        // Identify PII columns
        $piiColumns = [];
        foreach ($headers as $i => $header) {
            if ($this->masker->isPiiField($header)) {
                $piiColumns[] = $i;
            }
        }

        $this->logger->log('view', basename($filepath), null, [
            'row_count'   => count($rows),
            'pii_columns' => array_map(fn($i) => $headers[$i], $piiColumns),
            'masked'      => true,
        ]);

        return [
            'headers'    => $headers,
            'rows'       => $rows,
            'piiColumns' => $piiColumns,
            'rowCount'   => count($rows),
        ];
    }

    /**
     * Get masked version of data.
     */
    public function getMaskedData(array $data): array
    {
        $maskedRows = [];
        foreach ($data['rows'] as $row) {
            $maskedRows[] = $this->masker->maskRow($row, $data['headers']);
        }

        return [
            'headers' => $data['headers'],
            'rows'    => $maskedRows,
            'rowCount' => $data['rowCount'],
            'piiColumns' => $data['piiColumns'],
            'masked'  => true,
        ];
    }

    /**
     * Get unmasked data (requires justification logging).
     */
    public function getUnmaskedData(array $data, string $justification): array
    {
        $this->logger->log('view_unmasked', $_GET['file'] ?? 'unknown', null, [
            'justification' => $justification,
            'row_count'     => $data['rowCount'],
        ]);

        $data['masked'] = false;
        return $data;
    }

    /**
     * Delete a file (right to erasure).
     */
    public function deleteFile(string $filename): array
    {
        $fullPath = UPLOAD_DIR . basename($filename);

        if (!file_exists($fullPath)) {
            return ['success' => false, 'message' => 'File not found.'];
        }

        if (unlink($fullPath)) {
            $this->logger->log('delete', $filename, null, ['reason' => 'gdpr_erasure_request']);
            return ['success' => true, 'message' => 'File deleted permanently.'];
        }

        return ['success' => false, 'message' => 'Failed to delete file.'];
    }

    /**
     * Export file for download (right to data portability).
     */
    public function exportFile(string $filename): void
    {
        $fullPath = UPLOAD_DIR . basename($filename);

        if (!file_exists($fullPath)) {
            http_response_code(404);
            echo 'File not found.';
            return;
        }

        $this->logger->log('export', $filename, null, ['format' => 'csv']);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Content-Length: ' . filesize($fullPath));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        readfile($fullPath);
        exit;
    }

    /**
     * List all uploaded files.
     */
    public function listFiles(): array
    {
        $files = [];
        foreach (glob(UPLOAD_DIR . '*.csv') as $file) {
            $files[] = [
                'name'  => basename($file),
                'size'  => filesize($file),
                'date'  => date('Y-m-d H:i:s', filemtime($file)),
            ];
        }
        usort($files, fn($a, $b) => strcmp($b['date'], $a['date']));
        return $files;
    }

    /**
     * Get access logs (for transparency).
     */
    public function getAccessLogs(): array
    {
        return $this->logger->getLogs();
    }

    private function sanitizeFilename(string $name): string
    {
        $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
        return trim($name, '._-');
    }
}
