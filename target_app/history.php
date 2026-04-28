<?php
session_start();

// Koneksi ke database
$conn = new mysqli("localhost", "root", "Iqbal20061125", "sqli_demo");

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Ambil semua history login
$sql = "SELECT * FROM login_history ORDER BY attempt_time DESC";
$result = $conn->query($sql);

$history_data = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $history_data[] = $row;
    }
}

// Statistik
$total_attempts = count($history_data);
$sqli_detected = 0;
$successful_logins = 0;
$failed_logins = 0;

foreach ($history_data as $record) {
    if ($record['is_sqli_detected']) {
        $sqli_detected++;
    }
    if ($record['login_status'] == 'SUCCESS') {
        $successful_logins++;
    } elseif ($record['login_status'] == 'FAILED') {
        $failed_logins++;
    }
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login History - Hybrid SQL Injection IDS</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .header h1 {
            color: #333;
            font-size: 28px;
        }
        
        .btn-back {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            transition: background 0.3s;
        }
        
        .btn-back:hover {
            background: #764ba2;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        
        .stat-card h3 {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        
        .stat-card .number {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-card.danger .number {
            color: #f44336;
        }
        
        .stat-card.success .number {
            color: #4CAF50;
        }
        
        .table-wrapper {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table thead {
            background: #f5f5f5;
            border-bottom: 2px solid #e0e0e0;
        }
        
        table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        table tbody tr {
            border-bottom: 1px solid #e0e0e0;
            transition: background 0.2s;
        }
        
        table tbody tr:hover {
            background: #fafafa;
        }
        
        table td {
            padding: 15px;
            font-size: 13px;
            color: #555;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
            min-width: 80px;
        }
        
        .status-badge.success {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.failed {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-badge.blocked {
            background: #fff3cd;
            color: #856404;
        }
        
        .sqli-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
        }
        
        .sqli-badge.yes {
            background: #ffcdd2;
            color: #c62828;
        }
        
        .sqli-badge.no {
            background: #c8e6c9;
            color: #2e7d32;
        }
        
        .score {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .score-bar {
            width: 60px;
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            overflow: hidden;
        }
        
        .score-fill {
            height: 100%;
            background: linear-gradient(90deg, #4CAF50, #FFC107, #FF5722);
            width: 0%;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .no-data p {
            font-size: 16px;
            margin-bottom: 10px;
        }
        
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .filter-section label {
            margin-right: 15px;
            font-weight: 600;
        }
        
        .filter-section select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
            margin-right: 15px;
        }
        
        .filter-btn {
            padding: 8px 16px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .filter-btn:hover {
            background: #764ba2;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Login History Dashboard</h1>
            <a href="index.php" class="btn-back">← Kembali ke Login</a>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats-container">
            <div class="stat-card">
                <h3>Total Attempts</h3>
                <div class="number"><?php echo $total_attempts; ?></div>
            </div>
            
            <div class="stat-card success">
                <h3>Successful Logins</h3>
                <div class="number"><?php echo $successful_logins; ?></div>
            </div>
            
            <div class="stat-card">
                <h3>Failed Logins</h3>
                <div class="number"><?php echo $failed_logins; ?></div>
            </div>
            
            <div class="stat-card danger">
                <h3>SQLi Detected</h3>
                <div class="number"><?php echo $sqli_detected; ?></div>
            </div>
        </div>
        
        <!-- History Table -->
        <div class="table-wrapper">
            <?php if ($total_attempts > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Username Input</th>
                            <th>Password Input</th>
                            <th>NFA Score</th>
                            <th>SQLi Detected?</th>
                            <th>Login Status</th>
                            <th>Description</th>
                            <th>IP Address</th>
                            <th>Waktu Attempt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        foreach ($history_data as $row): 
                        ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td><code><?php echo htmlspecialchars($row['username_input']); ?></code></td>
                                <td><code><?php echo htmlspecialchars(str_repeat('*', strlen($row['password_input']))); ?></code></td>
                                <td>
                                    <div class="score">
                                        <span><?php echo number_format($row['nfa_score'], 2); ?></span>
                                        <div class="score-bar">
                                            <div class="score-fill" style="width: <?php echo ($row['nfa_score'] * 100); ?>%;"></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="sqli-badge <?php echo ($row['is_sqli_detected'] ? 'yes' : 'no'); ?>">
                                        <?php echo ($row['is_sqli_detected'] ? '🚨 YES' : '✅ NO'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo strtolower($row['login_status']); ?>">
                                        <?php echo $row['login_status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $desc = $row['description'];
                                    if ($desc == 'SQL Injection Attack Detected') {
                                        echo '<span style="color: #f44336; font-weight: 600;">⚠️ ' . $desc . '</span>';
                                    } elseif ($desc == 'Valid credentials') {
                                        echo '<span style="color: #4CAF50; font-weight: 600;">✓ ' . $desc . '</span>';
                                    } else {
                                        echo htmlspecialchars($desc);
                                    }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['ip_address']); ?></td>
                                <td><?php echo date('d-m-Y H:i:s', strtotime($row['attempt_time'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">
                    <p>📭 Belum ada history login</p>
                    <p style="font-size: 13px; color: #bbb;">Silakan lakukan login terlebih dahulu</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php
$conn->close();
?>
