<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/AccessLogger.php';
require_once __DIR__ . '/PiiMasker.php';
require_once __DIR__ . '/GdprSheetsViewer.php';

session_start();

$logger = new AccessLogger(LOG_FILE);
$masker = new PiiMasker(PII_FIELDS);
$viewer = new GdprSheetsViewer($logger, $masker);

// --- Handle Actions ---
$action = $_REQUEST['action'] ?? '';

switch ($action) {

    case 'upload':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
            $result = $viewer->handleUpload($_FILES['csv_file']);
            $message = $result['success']
                ? "File uploaded successfully: {$result['file']}"
                : "Upload failed: {$result['message']}";
        }
        break;

    case 'view':
        $file = $_GET['file'] ?? '';
        $unmask = ($_GET['unmask'] ?? '0') === '1';
        $justification = $_POST['justification'] ?? $_GET['justification'] ?? '';

        if ($file !== '') {
            $parsed = $viewer->parseCsv($file);
            if (!isset($parsed['error'])) {
                if ($unmask && $justification !== '') {
                    $viewData = $viewer->getUnmaskedData($parsed, $justification);
                } else {
                    $viewData = $viewer->getMaskedData($parsed);
                }
            }
        }
        break;

    case 'delete':
        $file = $_POST['file'] ?? '';
        if ($file !== '') {
            $result = $viewer->deleteFile($file);
            $message = $result['message'];
        }
        break;

    case 'export':
        $file = $_GET['file'] ?? '';
        if ($file !== '') {
            $viewer->exportFile($file);
        }
        break;

    case 'logs':
        $logs = $viewer->getAccessLogs();
        break;

    case 'purge_logs':
        $logger->purgeAllLogs();
        $message = 'All access logs have been purged.';
        break;
}

$files = $viewer->listFiles();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; color: #333; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        h1 { color: #4a90d9; margin-bottom: 20px; }
        .panel {
            background: #fff; border-radius: 8px; padding: 20px;
            margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .panel h2 { font-size: 1.1em; margin-bottom: 15px; color: #555; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85em; }
        th, td { padding: 8px 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #4a90d9; color: #fff; }
        tr:nth-child(even) { background: #f9f9f9; }
        .btn {
            display: inline-block; padding: 6px 14px; border: none;
            border-radius: 4px; cursor: pointer; font-size: 0.85em;
            text-decoration: none; margin-right: 5px; margin-bottom: 5px;
        }
        .btn-primary { background: #4a90d9; color: #fff; }
        .btn-danger { background: #e74c3c; color: #fff; }
        .btn-secondary { background: #95a5a6; color: #fff; }
        .btn-warning { background: #f39c12; color: #fff; }
        .message { padding: 12px; border-radius: 4px; margin-bottom: 15px; }
        .message.success { background: #d4edda; color: #155724; }
        .message.error { background: #f8d7da; color: #721c24; }
        .pii-badge {
            background: #f39c12; color: #fff; font-size: 0.7em;
            padding: 2px 6px; border-radius: 3px; margin-left: 4px;
        }
        .masked-note { font-style: italic; color: #888; font-size: 0.85em; }
        form.inline { display: inline; }
        input[type="text"], input[type="file"] { padding: 6px; border: 1px solid #ccc; border-radius: 4px; }
        .file-list li { list-style: none; padding: 8px; border-bottom: 1px solid #eee; }
        .file-list { list-style: none; }
        .gdpr-banner {
            background: #4a90d9; color: #fff; padding: 10px 15px;
            border-radius: 6px; margin-bottom: 20px; font-size: 0.85em;
        }
    </style>
</head>
<body>
<div class="container">

    <h1><?= APP_NAME ?></h1>

    <div class="gdpr-banner">
        <strong>GDPR Notice:</strong> Personal data is masked by default. 
        Unmasking requires a documented justification. All access is logged 
        per Article 30. You may export or delete data at any time 
        (Articles 15, 17, 20).
    </div>

    <?php if (!empty($message)): ?>
        <div class="message <?= strpos($message, 'failed') !== false || strpos($message, 'error') !== false ? 'error' : 'success' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Upload Section -->
    <div class="panel">
        <h2>Upload CSV File</h2>
        <form method="POST" action="?action=upload" enctype="multipart/form-data">
            <input type="file" name="csv_file" accept=".csv" required>
            <button type="submit" class="btn btn-primary">Upload</button>
        </form>
    </div>

    <!-- File List -->
    <div class="panel">
        <h2>Available Sheets</h2>
        <?php if (empty($files)): ?>
            <p>No files uploaded yet.</p>
        <?php else: ?>
            <ul class="file-list">
                <?php foreach ($files as $f): ?>
                    <li>
                        <strong><?= htmlspecialchars($f['name']) ?></strong>
                        — <?= number_format($f['size'] / 1024, 1) ?> KB — <?= $f['date'] ?>
                        <br>
                        <a class="btn btn-primary" href="?action=view&file=<?= urlencode($f['name']) ?>">View</a>
                        <a class="btn btn-secondary" href="?action=export&file=<?= urlencode($f['name']) ?>">Export</a>
                        <form class="inline" method="POST" action="?action=delete"
                              onconfirm="return confirm('Permanently delete this file?');">
                            <input type="hidden" name="file" value="<?= htmlspecialchars($f['name']) ?>">
                            <button type="submit" class="btn btn-danger"
                                    onclick="return confirm('Permanently delete this file? This cannot be undone.')">
                                Delete
                            </button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <!-- Sheet Viewer -->
    <?php if (isset($viewData) && !isset($viewData['error'])): ?>
        <div class="panel">
            <h2>
                Sheet Viewer: <?= htmlspecialchars($_GET['file']) ?>
                <?php if ($viewData['masked']): ?>
                    <span class="masked-note">(PII Masked)</span>
                <?php else: ?>
                    <span class="masked-note" style="color:#e74c3c;">(PII Visible — Justification logged)</span>
                <?php endif; ?>
            </h2>

            <?php if ($viewData['masked'] && count($viewData['piiColumns']) > 0): ?>
                <div style="margin-bottom: 15px;">
                    <strong>Unmask Data (requires justification):</strong>
                    <form method="POST" style="margin-top: 8px;"
                          action="?action=view&file=<?= urlencode($_GET['file']) ?>&unmask=1">
                        <input type="text" name="justification" placeholder="e.g., Data subject request - Ticket #12345"
                               style="width: 400px;" required>
                        <button type="submit" class="btn btn-warning">Unmask with Justification</button>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($viewData['rowCount'] > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <?php foreach ($viewData['headers'] as $i => $h): ?>
                                <th>
                                    <?= htmlspecialchars($h) ?>
                                    <?php if (in_array($i, $viewData['piiColumns'])): ?>
                                        <span class="pii-badge">PII</span>
                                    <?php endif; ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($viewData['rows'] as $rowNum => $row): ?>
                            <tr>
                                <td><?= $rowNum + 1 ?></td>
                                <?php foreach ($row as $i => $cell): ?>
                                    <td><?= htmlspecialchars($cell) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No data rows found.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Access Logs (Transparency) -->
    <div class="panel">
        <h2>Access Logs (GDPR Article 30 — Audit Trail)</h2>
        <a class="btn btn-primary" href="?action=logs">View Logs</a>
        <?php if (isset($logs) && count($logs) > 0): ?>
            <form class="inline" method="POST" action="?action=purge_logs"
                  onsubmit="return confirm('Purge ALL access logs? This cannot be undone.');">
                <button type="submit" class="btn btn-danger">Purge All Logs</button>
            </form>
            <table style="margin-top: 15px;">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>User ID</th>
                        <th>IP</th>
                        <th>Action</th>
                        <th>Resource</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_reverse($logs) as $log): ?>
                        <tr>
                            <td><?= htmlspecialchars($log['timestamp']) ?></td>
                            <td><?= htmlspecialchars($log['user_id']) ?></td>
                            <td><?= htmlspecialchars($log['user_ip']) ?></td>
                            <td><?= htmlspecialchars($log['action']) ?></td>
                            <td><?= htmlspecialchars($log['resource']) ?></td>
                            <td><code><?= htmlspecialchars(json_encode($log['details'] ?? [])) ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php elseif (isset($logs)): ?>
            <p style="margin-top: 15px;">No access logs recorded.</p>
        <?php endif; ?>
    </div>

</div>
</body>
</html>
