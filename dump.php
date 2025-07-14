<?php
session_start();

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: absen.php');
    exit;
}

$error = '';

$valid_users = [
    'admin' => 'user!@#',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (isset($valid_users[$username]) && $valid_users[$username] === $password) {
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $username;
        $_SESSION['login_time'] = time();
        
        $redirect = $_GET['redirect'] ?? 'absen.php';
        header('Location: ' . $redirect);
        exit;
    } else {
        $error = 'Username atau password salah!';
        sleep(1);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Absensi</title>
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
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            padding: 40px;
            width: 100%;
            max-width: 400px;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header h1 {
            color: #333;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .login-header p {
            color: #666;
            font-size: 14px;
        }

        .login-icon {
            font-size: 64px;
            margin-bottom: 20px;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-10px);
            }
            60% {
                transform: translateY(-5px);
            }
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            color: #333;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background-color: #f8f9fa;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            background-color: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .login-btn {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 14px 20px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .user-list {
            list-style: none;
            padding: 0;
        }

        .user-list li {
            padding: 3px 0;
            border-bottom: 1px solid #b8daff;
        }

        .user-list li:last-child {
            border-bottom: none;
        }

        .password-toggle {
            position: relative;
        }

        .password-toggle button {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 18px;
            color: #666;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .loading {
            display: none;
            text-align: center;
            margin-top: 10px;
        }

        .loading.show {
            display: block;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
                margin: 10px;
            }
            
            .login-header h1 {
                font-size: 24px;
            }
            
            .login-icon {
                font-size: 48px;
            }
        }

        .theme-toggle {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            padding: 10px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 20px;
            transition: all 0.3s ease;
        }

        .theme-toggle:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }

        body.dark-theme {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
        }

        .dark-theme .login-container {
            background: rgba(40, 44, 52, 0.95);
            color: #e4e4e4;
        }

        .dark-theme .login-header h1 {
            color: #e4e4e4;
        }

        .dark-theme .login-header p {
            color: #b4b4b4;
        }

        .dark-theme .form-group label {
            color: #e4e4e4;
        }

        .dark-theme .form-group input {
            background-color: #3c4043;
            border-color: #5f6368;
            color: #e4e4e4;
        }

        .dark-theme .form-group input:focus {
            background-color: #484c52;
            border-color: #667eea;
        }
    </style>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()" title="Toggle Dark Mode">🌙</button>
    
    <div class="login-container">
        <div class="login-header">
            <div class="login-icon">🔐</div>
            <h1>Aplikasi Absensi</h1>
            <p>Silahkan login terlebih dahulu</p>
        </div>

        <?php if ($error): ?>
            <div class="error-message">
                ❌ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="loginForm">
            <div class="form-group">
                <label for="username">👤 Username</label>
                <input type="text" 
                       id="username" 
                       name="username" 
                       required 
                       autocomplete="username"
                       placeholder="Masukkan username"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="password">🔑 Password</label>
                <div class="password-toggle">
                    <input type="password" 
                           id="password" 
                           name="password" 
                           required 
                           autocomplete="current-password"
                           placeholder="Masukkan password">
                    <button type="button" onclick="togglePassword()" title="Show/Hide Password">
                        👁️
                    </button>
                </div>
            </div>

            <button type="submit" class="login-btn">
                🚀 Login
            </button>

            <div class="loading" id="loading">
                <div class="spinner"></div>
                <p>Memverifikasi kredensial...</p>
            </div>
        </form>

    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleBtn = passwordInput.nextElementSibling;
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleBtn.textContent = '🙈';
            } else {
                passwordInput.type = 'password';
                toggleBtn.textContent = '👁️';
            }
        }

        function toggleTheme() {
            const body = document.body;
            const themeToggle = document.querySelector('.theme-toggle');
            
            body.classList.toggle('dark-theme');
            
            if (body.classList.contains('dark-theme')) {
                themeToggle.textContent = '☀️';
                localStorage.setItem('darkTheme', 'true');
            } else {
                themeToggle.textContent = '🌙';
                localStorage.setItem('darkTheme', 'false');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const isDarkTheme = localStorage.getItem('darkTheme') === 'true';
            const themeToggle = document.querySelector('.theme-toggle');
            
            if (isDarkTheme) {
                document.body.classList.add('dark-theme');
                themeToggle.textContent = '☀️';
            }
        });

        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const loading = document.getElementById('loading');
            const submitBtn = document.querySelector('.login-btn');
            
            loading.classList.add('show');
            submitBtn.style.opacity = '0.6';
            submitBtn.disabled = true;
        });

        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });

        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('loginForm').submit();
            }

            if (e.key === 'Escape') {
                document.getElementById('username').value = '';
                document.getElementById('password').value = '';
                document.getElementById('username').focus();
            }
        });

        function quickLogin(username, password) {
            document.getElementById('username').value = username;
            document.getElementById('password').value = password;
            document.getElementById('loginForm').submit();
        }

        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('input');
            
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'scale(1.02)';
                    this.parentElement.style.transition = 'transform 0.2s ease';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'scale(1)';
                });
            });
        });

        window.addEventListener('beforeunload', function() {
            document.getElementById('password').value = '';
        });

        let isSubmitting = false;
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            if (isSubmitting) {
                e.preventDefault();
                return false;
            }
            isSubmitting = true;

            setTimeout(() => {
                isSubmitting = false;
            }, 3000);
        });
    </script>
</body>
</html>
