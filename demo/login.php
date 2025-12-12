<?php
require_once 'auth.php';

if (isAuthenticated()) {
    header('Location: index.php');
    exit;
}

$error = '';
$failedAttempts = $_SESSION['failed_attempts'] ?? 0;
$userIcon = 'icons/user.png';
$errorMessage = 'Wrong password';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    
    if (authenticate($password)) {
        $_SESSION['failed_attempts'] = 0;
        header('Location: index.php');
        exit;
    } else {
        $failedAttempts++;
        $_SESSION['failed_attempts'] = $failedAttempts;
        
        if ($failedAttempts >= 5) {
            $userIcon = 'icons/dizzy.png';
            $errorMessage = 'You can login tomorrow.';
        } elseif ($failedAttempts >= 3) {
            $userIcon = 'icons/thinking.png';
            $errorMessage = 'Soon IP will be blocked, contact the administrator.';
        } else {
            $errorMessage = 'Wrong password';
        }
        
        $error = $errorMessage;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="favicon.ico">
    <style>
        .login-container {
            display: flex;
            flex-direction: column;
            margin: auto;
            justify-content: center;
            min-height: 100vh;
            padding: 24px;
        }
        
        .login-box {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 24px;
            max-width: 320px;
            width: 100%;
        }
        
        .user-icon {
            width: 96px;
            height: 96px;
            border-radius: 50%;
        }
        
        .login-form {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .password-input {
            /* width: 100%; */
            width: 320px;
            padding: 12px 16px;
            font-size: 16px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--bg-primary);
            color: var(--text-primary);
            outline: none;
            transition: border-color 0.15s;
        }
        
        .password-input:focus {
            border-color: var(--text-primary);
        }
        
        .password-input:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .login-error {
            color: var(--text-primary);
            font-size: 14px;
            text-align: center;
            padding: 8px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <img src="<?php echo htmlspecialchars($userIcon); ?>" alt="User" class="user-icon">
            
            <form method="POST" class="login-form">
                <input 
                    type="password" 
                    name="password" 
                    placeholder="Password" 
                    class="password-input"
                    autofocus
                    required
                    <?php if ($failedAttempts >= 5) echo 'disabled'; ?>
                >
                
                <?php if ($error): ?>
                    <div class="login-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</body>
</html>
