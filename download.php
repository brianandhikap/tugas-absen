<?php
if (!isset($_GET['file'])) {
    die('No file specified.');
}

$filePath = $_GET['file'];

$filePath = str_replace(['../', '..\\'], '', $filePath);

if (!file_exists($filePath) || !is_readable($filePath)) {
    die('File not found or not readable.');
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $filePath);
finfo_close($finfo);

header('Content-Description: File Transfer');
header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filePath));

ob_clean();
flush();
readfile($filePath);
exit;
?>
