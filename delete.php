<?php
require_once __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['file'])) {
    header("Location: index.php");
    exit;
}
if (!cloudvmu_can_delete() || !cloudvmu_csrf_valid(isset($_POST['token']) ? $_POST['token'] : null)) {
    http_response_code(403);
    echo "Not allowed to delete files.";
    exit;
}

// Upload names are server-generated: up to 8 of A-Z, 0-9, _ (see upload.php).
$baseFilename = pathinfo(basename($_POST['file']), PATHINFO_FILENAME);
if (!preg_match('/^[A-Za-z0-9_]{1,8}$/', $baseFilename)) {
    http_response_code(400);
    echo "Invalid file name.";
    exit;
}

$baseDir = "uploads/";
// Delete the VMI, VMS and generated GIF (extensions matched case-insensitively)
foreach (scandir($baseDir) as $file) {
    if (strcasecmp(pathinfo($file, PATHINFO_FILENAME), $baseFilename) === 0 &&
        in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['vmi', 'vms', 'gif'], true)) {
        unlink($baseDir . $file);
    }
}

// Redirect back to the main page
header("Location: index.php");
exit;
?>
