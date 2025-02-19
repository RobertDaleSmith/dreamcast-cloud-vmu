<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['file'])) {
    $filename = basename($_POST['file']); // Sanitize filename
    $baseDir = "uploads/";
    
    // Find and delete the VMI file (case-insensitive)
    $baseFilename = pathinfo($filename, PATHINFO_FILENAME);
    $files = scandir($baseDir);
    foreach ($files as $file) {
        if (strcasecmp(pathinfo($file, PATHINFO_FILENAME), $baseFilename) === 0 && 
            strcasecmp(pathinfo($file, PATHINFO_EXTENSION), 'vmi') === 0) {
            unlink($baseDir . $file);
            break;
        }
    }
    
    // Find and delete the corresponding VMS file
    $files = scandir($baseDir);
    foreach ($files as $file) {
        if (strcasecmp(pathinfo($file, PATHINFO_FILENAME), $baseFilename) === 0 && 
            strcasecmp(pathinfo($file, PATHINFO_EXTENSION), 'vms') === 0) {
            unlink($baseDir . $file);
            break;
        }
    }
    
    // Delete the GIF file if it exists
    $gifPath = $baseDir . $baseFilename . ".gif";
    if (file_exists($gifPath)) {
        unlink($gifPath);
    }
}

// Redirect back to the main page
header("Location: index.php");
exit;
?>
