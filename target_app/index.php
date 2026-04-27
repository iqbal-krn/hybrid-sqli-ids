<?php
// Koneksi ke database
$conn = new mysqli("localhost", "root", "Iqbal20061125", "sqli_demo");

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Ambil input dari user (contoh menggunakan GET agar mudah diuji via URL)
$user = $_GET['username'] ?? '';
$pass = $_GET['password'] ?? '';

// QUERY VULNERABLE (Tanpa sanitasi/rentan serangan)
$sql = "SELECT * FROM users WHERE username = '$user' AND password = '$pass'";
$result = $conn->query($sql);

echo "<h2>Aplikasi Target (Rentan SQLi)</h2>";
echo "<p>Query yang dieksekusi: <code>$sql</code></p>";

if ($result && $result->num_rows > 0) {
    echo "<h3 style='color:green;'>Login Berhasil! Selamat datang, " . htmlspecialchars($user) . ".</h3>";
} else {
    echo "<h3 style='color:red;'>Login Gagal!</h3>";
}
$conn->close();
?>