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

$fileName = basename($filePath);
$fileSize = formatFileSize(filesize($filePath));
$fileDate = date('Y-m-d H:i:s', filemtime($filePath));

function formatFileSize($bytes) {
    if ($bytes == 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

$isImage = strpos($mimeType, 'image') !== false;
$isVideo = strpos($mimeType, 'video') !== false;
$isAudio = strpos($mimeType, 'audio') !== false;
$isText = strpos($mimeType, 'text') !== false;

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View: <?= htmlspecialchars($fileName) ?></title>
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
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }

        .header {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            padding: 20px 30px;
            border-bottom: 1px solid #e9ecef;
        }

        .header h1 {
            color: #333;
            font-size: 24px;
            margin-bottom: 10px;
            word-break: break-all;
        }

        .file-info {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            color: #666;
            font-size: 14px;
        }

        .file-info span {
            background: rgba(255,255,255,0.8);
            padding: 5px 10px;
            border-radius: 15px;
        }

        .content {
            padding: 30px;
            text-align: center;
        }

        .media-container {
            max-width: 100%;
            margin: 0 auto;
        }

        .media-container img {
            max-width: 100%;
            max-height: 80vh;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .media-container video {
            max-width: 100%;
            max-height: 80vh;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .media-container audio {
            width: 100%;
            max-width: 500px;
        }

        .text-content {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            text-align: left;
            font-family: monospace;
            white-space: pre-wrap;
            max-height: 70vh;
            overflow-y: auto;
            font-size: 14px;
            line-height: 1.5;
        }

        .actions {
            margin-top: 30px;
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .not-supported {
            padding: 40px 20px;
            color: #666;
        }

        .not-supported .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .container {
                margin: 10px;
                border-radius: 15px;
            }

            .header {
                padding: 15px 20px;
            }

            .content {
                padding: 20px;
            }

            .file-info {
                flex-direction: column;
                gap: 10px;
            }

            .actions {
                flex-direction: column;
                align-items: center;
            }

            .btn {
                width: 100%;
                max-width: 300px;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📄 <?= htmlspecialchars($fileName) ?></h1>
            <div class="file-info">
                <span>📊 Size: <?= $fileSize ?></span>
                <span>🗓️ Modified: <?= $fileDate ?></span>
                <span>🏷️ Type: <?= htmlspecialchars($mimeType) ?></span>
            </div>
        </div>

        <div class="content">
            <?php if ($isImage): ?>
                <div class="media-container">
                    <img src="data:<?= $mimeType ?>;base64,<?= base64_encode(file_get_contents($filePath)) ?>" 
                         alt="<?= htmlspecialchars($fileName) ?>"
                         onclick="this.style.transform = this.style.transform ? '' : 'scale(1.5)'; this.style.transition = 'transform 0.3s ease';">
                    <p style="margin-top: 15px; color: #666; font-size: 14px;">
                        💡 Klik gambar untuk zoom in/out
                    </p>
                </div>
            <?php elseif ($isVideo): ?>
                <div class="media-container">
                    <video controls>
                        <source src="data:<?= $mimeType ?>;base64,<?= base64_encode(file_get_contents($filePath)) ?>" 
                                type="<?= $mimeType ?>">
                        Browser Anda tidak mendukung tag video.
                    </video>
                </div>
            <?php elseif ($isAudio): ?>
                <div class="media-container">
                    <div style="font-size: 64px; margin-bottom: 20px;">🎵</div>
                    <audio controls>
                        <source src="data:<?= $mimeType ?>;base64,<?= base64_encode(file_get_contents($filePath)) ?>" 
                                type="<?= $mimeType ?>">
                        Browser Anda tidak mendukung tag audio.
                    </audio>
                </div>
            <?php elseif ($isText && filesize($filePath) < 1024 * 1024): ?>
                <div class="text-content">
<?= htmlspecialchars(file_get_contents($filePath)) ?>
                </div>
            <?php else: ?>
                <div class="not-supported">
                    <div class="icon">📄</div>
                    <h3>Preview Tidak Tersedia</h3>
                    <p>File ini tidak dapat ditampilkan di browser.</p>
                    <p>Silakan download untuk membuka dengan aplikasi yang sesuai.</p>
                </div>
            <?php endif; ?>

            <div class="actions">
                <a href="download.php?file=<?= urlencode($filePath) ?>" class="btn btn-primary">
                    💾 Download File
                </a>
                <a href="javascript:history.back()" class="btn btn-secondary">
                    ⬅️ Kembali
                </a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                history.back();
            }

            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                window.location.href = 'download.php?file=<?= urlencode($filePath) ?>';
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            const images = document.querySelectorAll('.media-container img');
            images.forEach(img => {
                let isZoomed = false;
                img.style.cursor = 'zoom-in';
                
                img.addEventListener('click', function() {
                    if (isZoomed) {
                        this.style.transform = '';
                        this.style.cursor = 'zoom-in';
                        isZoomed = false;
                    } else {
                        this.style.transform = 'scale(1.5)';
                        this.style.cursor = 'zoom-out';
                        isZoomed = true;
                    }
                });
            });
        });
    </script>
</body>
</html>
