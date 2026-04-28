<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Koneksi ke database
<<<<<<< HEAD
$conn = new mysqli("localhost", "root", "Iqbal20061125", "sqli_demo");
=======
$conn = new mysqli("localhost", "root", "041206", "sqli_demo");
>>>>>>> 2f4005d9fb5aed4d96072aeccff5705146122cd6

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Fungsi untuk memanggil Python WAF untuk deteksi SQLi
function checkSQLiWithWAF($payload) {
    // Simulasi NFA Score calculation dari Python WAF
    $payload_lower = strtolower(urldecode($payload));
    $nfa_score = 0.0;
    
    // Pola 1: Authentication Bypass (' OR 1=1)
    if (preg_match("/'.*or.*1\s*=\s*1|.*or.*true/i", $payload_lower)) {
        $nfa_score += 0.8;
    }
    
    // Pola 2: Union Based (' UNION SELECT)
    if (preg_match("/union\s+.*select.*/i", $payload_lower)) {
        $nfa_score += 0.85;
    }
    
    // Pola 3: Tanda kutip atau komentar SQL
    if (preg_match("/(--|#|\/\*.*\*\/)/i", $payload_lower)) {
        $nfa_score += 0.5;
    }
    
    // Pola 4: Fungsi mencurigakan
    if (preg_match("/(sleep\(|waitfor\s+delay|benchmark\()/i", $payload_lower)) {
        $nfa_score += 0.9;
    }
    
    $nfa_score = min($nfa_score, 1.0); // Maksimal 1.0
    
    return $nfa_score;
}

// Fungsi untuk menyimpan history
function saveLoginHistory($username, $password, $nfa_score, $is_sqli, $status, $description, $conn) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $conn->prepare("INSERT INTO login_history (username_input, password_input, nfa_score, is_sqli_detected, login_status, ip_address, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssdisss", $username, $password, $nfa_score, $is_sqli, $status, $ip_address, $description);
    $stmt->execute();
    $stmt->close();
}

// Proses login ketika form disubmit
$login_result = null;
$login_message = null;
$detected_sqli = false;
$nfa_score_result = 0;

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Cek SQL Injection dengan WAF
    $username_nfa = checkSQLiWithWAF($username);
    $password_nfa = checkSQLiWithWAF($password);
    $nfa_score_result = max($username_nfa, $password_nfa);
    
    // Threshold untuk mendeteksi SQLi (>0.3)
    $sqli_threshold = 0.3;
    $is_sqli = ($nfa_score_result > $sqli_threshold);
    
    if ($is_sqli) {
        // SQLi TERDETEKSI!
        $detected_sqli = true;
        $login_result = false;
        $login_message = "⚠️ PERINGATAN: Kemungkinan SQL Injection Terdeteksi!";
        saveLoginHistory($username, $password, $nfa_score_result, 1, "BLOCKED", "SQL Injection Attack Detected", $conn);
    } else {
        // Tidak ada SQLi, proses login normal dengan prepared statement
        $stmt = $conn->prepare("SELECT id, username FROM users WHERE username = ? AND password = ?");
        $stmt->bind_param("ss", $username, $password);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Login sukses
            $login_result = true;
            $login_message = "✅ Login Berhasil!";
            $_SESSION['username'] = $username;
            saveLoginHistory($username, $password, $nfa_score_result, 0, "SUCCESS", "Valid credentials", $conn);
        } else {
            // Username atau password salah
            $login_result = false;
            $login_message = "❌ Username atau Password salah!";
            saveLoginHistory($username, $password, $nfa_score_result, 0, "FAILED", "Invalid credentials", $conn);
        }
        $stmt->close();
    }
}

// Redirect ke dashboard jika sudah login
if (isset($_SESSION['username']) && $_SESSION['username']) {
    // Tampilkan dashboard
    $dashboard = true;
} else {
    $dashboard = false;
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hybrid SQL Injection IDS - Login</title>
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
        
        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            max-width: 900px;
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr;
            overflow: hidden;
        }
        
        @media (max-width: 768px) {
            .container {
                grid-template-columns: 1fr;
            }
            .info-section {
                display: none;
            }
        }
        
        .info-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .info-section h2 {
            margin-bottom: 20px;
            font-size: 24px;
        }
        
        .info-section p {
            margin-bottom: 15px;
            line-height: 1.6;
            font-size: 14px;
        }
        
        .feature-list {
            margin-top: 20px;
        }
        
        .feature-list li {
            margin-bottom: 10px;
            margin-left: 20px;
            font-size: 13px;
        }
        
        .login-section {
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .login-section h1 {
            margin-bottom: 10px;
            color: #333;
            font-size: 28px;
        }
        
        .login-section p {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .message {
            margin: 20px 0;
            padding: 15px;
            border-radius: 5px;
            font-weight: 600;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .message.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        
        .detection-info {
            margin-top: 20px;
            padding: 15px;
            background: #f5f5f5;
            border-left: 4px solid #667eea;
            border-radius: 5px;
            font-size: 13px;
        }
        
        .detection-info strong {
            display: block;
            margin-bottom: 8px;
            color: #333;
        }
        
        .detection-info p {
            margin: 5px 0;
            color: #666;
        }
        
        .score-bar {
            width: 100%;
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            margin-top: 8px;
            overflow: hidden;
        }
        
        .score-fill {
            height: 100%;
            background: linear-gradient(90deg, #4CAF50, #FFC107, #FF5722);
            width: 0%;
            transition: width 0.3s;
        }
        
        .dashboard {
            padding: 30px;
        }
        
        .dashboard h2 {
            color: #333;
            margin-bottom: 20px;
        }
        
        .welcome-box {
            background: #e8f5e9;
            border-left: 4px solid #4CAF50;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .welcome-box p {
            color: #2e7d32;
            margin: 5px 0;
        }
        
        .btn-logout {
            padding: 10px 20px;
            background: #f44336;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
        }
        
        .btn-logout:hover {
            background: #d32f2f;
        }
        
        .btn-history {
            padding: 10px 20px;
            background: #2196F3;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
            margin-left: 10px;
        }
        
        .btn-history:hover {
            background: #1976D2;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="info-section">
            <h2>🛡️ Hybrid SQL Injection IDS</h2>
            <p>Sistem keamanan yang menggabungkan:</p>
            <ul class="feature-list">
                <li><strong>Deteksi Real-time</strong> - Analisis input dengan NFA dan pattern matching</li>
                <li><strong>ML-Based Detection</strong> - Random Forest classifier untuk akurasi tinggi</li>
                <li><strong>Tracking History</strong> - Monitoring semua attempt login</li>
                <li><strong>Attack Signature</strong> - Recognisi serangan umum SQLi</li>
            </ul>
            <p style="margin-top: 30px; font-size: 12px; opacity: 0.8;">
                💡 Test credentials:<br>
                User: admin | Pass: rahasia123<br>
                User: iqbal | Pass: ganteng123
            </p>
        </div>
        
        <div class="login-section">
            <?php if (!$dashboard): ?>
                <h1>Login</h1>
                <p>Masukkan kredensial Anda untuk melanjutkan</p>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            required 
                            placeholder="Masukkan username"
                            value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            required 
                            placeholder="Masukkan password"
                        >
                    </div>
                    
                    <button type="submit" name="login" class="btn-login">Login</button>
                </form>
                
                <?php if ($login_message): ?>
                    <div class="message <?php echo ($login_result === true) ? 'success' : ($detected_sqli ? 'warning' : 'error'); ?>">
                        <?php echo $login_message; ?>
                    </div>
                    
                    <div class="detection-info">
                        <strong>🔍 Detection Analysis:</strong>
                        <p><strong>NFA Score:</strong> <?php echo number_format($nfa_score_result, 2); ?> / 1.00</p>
                        <div class="score-bar">
                            <div class="score-fill" style="width: <?php echo ($nfa_score_result * 100); ?>%;"></div>
                        </div>
                        <p><strong>Status:</strong> 
                            <?php 
                            if ($detected_sqli) {
                                echo '🚨 SQL Injection Detected!';
                            } else {
                                echo ($login_result === true) ? '✅ Clean Input - Login Success' : '✅ Clean Input - Invalid Credentials';
                            }
                            ?>
                        </p>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="dashboard">
                    <h2>🎉 Selamat Datang!</h2>
                    <div class="welcome-box">
                        <p><strong>Username:</strong> <?php echo htmlspecialchars($_SESSION['username']); ?></p>
                        <p><strong>Status:</strong> Terautentikasi</p>
                        <p><strong>Waktu:</strong> <?php echo date('d-m-Y H:i:s'); ?></p>
                    </div>
                    
                    <div style="margin-top: 20px;">
                        <a href="history.php" class="btn-history">📊 Lihat Login History</a>
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="logout" class="btn-logout">Logout</button>
                        </form>
                    </div>
                </div>
                <?php
                if (isset($_POST['logout'])) {
                    session_destroy();
                    echo '<script>window.location.reload();</script>';
                }
                ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php
$conn->close();
?>