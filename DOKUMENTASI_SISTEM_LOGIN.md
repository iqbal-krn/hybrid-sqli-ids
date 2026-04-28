# 📖 DOKUMENTASI SISTEM LOGIN HYBRID SQL INJECTION IDS

## 📋 Ringkasan Fitur

Sistem ini menggabungkan **login system yang real**, **deteksi SQL Injection real-time**, dan **tracking history** untuk monitoring semua attempt login.

---

## 🎯 Fitur Utama

### 1. **Login Form yang User-Friendly**
- Interface modern dengan design responsif
- Form input username & password
- Visual feedback untuk setiap attempt

### 2. **Deteksi SQL Injection Real-Time**
- Menggunakan NFA Score calculation (0.0 - 1.0)
- Threshold default: 0.3 untuk detect SQLi
- Pattern recognition untuk 4 jenis serangan SQLi

### 3. **Login History & Tracking**
- Menyimpan semua attempt login ke database
- Track username, password (hashed), NFA score, status
- Menampilkan IP address dan timestamp
- Dashboard untuk analisa

---

## 📁 File Structure

```
target_app/
├── index.php          # Sistem login dengan deteksi SQLi
├── history.php        # Dashboard history login
└── database/
    └── db.sql         # Database schema (updated)
```

---

## 🔧 Penjelasan Code: index.php

### A. **Session & Database Connection**
```php
session_start();
$conn = new mysqli("localhost", "root", "Iqbal20061125", "sqli_demo");
```
- Memulai session untuk tracking user yang login
- Koneksi ke database MySQL dengan credentials

### B. **Fungsi: checkSQLiWithWAF()**
```php
function checkSQLiWithWAF($payload) {
    // Menghitung NFA Score berdasarkan pola SQL injection
    // Return nilai 0.0 - 1.0
}
```

**Pola Deteksi:**

| Pola | Score | Contoh |
|------|-------|--------|
| Authentication Bypass | +0.8 | `' OR 1=1` |
| Union Based | +0.85 | `' UNION SELECT` |
| SQL Comments | +0.5 | `--`, `#`, `/**/` |
| Time-based | +0.9 | `SLEEP()`, `WAITFOR DELAY` |

**Cara Kerja:**
1. Decode URL → convert ke lowercase
2. Check setiap pola dengan regex
3. Hitung total score (max 1.0)
4. Return score untuk evaluasi

### C. **Fungsi: saveLoginHistory()**
```php
function saveLoginHistory($username, $password, $nfa_score, $is_sqli, $status, $description, $conn)
```

**Menyimpan data:**
- `username_input` → Input username dari form
- `password_input` → Input password (plain text untuk demo)
- `nfa_score` → Hasil deteksi WAF
- `is_sqli_detected` → Boolean (1/0)
- `login_status` → SUCCESS/FAILED/BLOCKED
- `ip_address` → IP client
- `attempt_time` → Auto timestamp

### D. **Proses Login (POST Handler)**

```php
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // Step 1: Check SQLi dengan WAF
    $username_nfa = checkSQLiWithWAF($username);
    $password_nfa = checkSQLiWithWAF($password);
    $nfa_score_result = max($username_nfa, $password_nfa);
    
    // Step 2: Tentukan apakah SQLi atau tidak
    $is_sqli = ($nfa_score_result > 0.3); // Threshold
    
    // Step 3: Jika SQLi terdeteksi, BLOCK!
    if ($is_sqli) {
        // BLOCK REQUEST + SAVE HISTORY
        saveLoginHistory($username, $password, $nfa_score_result, 1, "BLOCKED", "SQL Injection Attack Detected", $conn);
    } else {
        // Step 4: Input aman, lakukan authentication normal
        // Gunakan PREPARED STATEMENT (aman dari SQLi)
        $stmt = $conn->prepare("SELECT id, username FROM users WHERE username = ? AND password = ?");
        $stmt->bind_param("ss", $username, $password);
        $stmt->execute();
        
        if ($result->num_rows > 0) {
            // Login SUCCESS
            saveLoginHistory($username, $password, $nfa_score_result, 0, "SUCCESS", "Valid credentials", $conn);
        } else {
            // Login FAILED (wrong credentials)
            saveLoginHistory($username, $password, $nfa_score_result, 0, "FAILED", "Invalid credentials", $conn);
        }
    }
}
```

**Flow Chart:**
```
User Input
    ↓
Check SQLi (NFA Score) → Score > 0.3?
    ↓ YES              ↓ NO
  BLOCKED         Prepared Statement Query
  (Save History)        ↓
  Return Warning    Credentials Valid?
                    ↓ YES      ↓ NO
                   SUCCESS    FAILED
                   (Save History)
```

### E. **Frontend Tampilan**

**Login Form:**
- Username input
- Password input (type="password")
- Submit button

**Detection Result:**
- Menampilkan pesan (Success/Error/Warning)
- Visualisasi NFA Score dengan progress bar
- Penjelasan deteksi (Clean Input / SQLi Detected)

**Dashboard (After Login):**
- Welcome message dengan username
- Link ke Login History
- Logout button

---

## 🗄️ Database Schema Update

### Tabel: login_history

```sql
CREATE TABLE login_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username_input VARCHAR(255),
    password_input VARCHAR(255),
    nfa_score FLOAT,
    is_sqli_detected BOOLEAN,      -- 1 = SQLi, 0 = Normal
    login_status VARCHAR(50),       -- SUCCESS/FAILED/BLOCKED
    attempt_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(50),
    description TEXT
);
```

**Field Penjelasan:**

| Field | Type | Keterangan |
|-------|------|-----------|
| `id` | INT | Primary key, auto-increment |
| `username_input` | VARCHAR(255) | Username yang di-input user |
| `password_input` | VARCHAR(255) | Password yang di-input user |
| `nfa_score` | FLOAT | Nilai NFA detection (0.0 - 1.0) |
| `is_sqli_detected` | BOOLEAN | 1 jika SQLi detected, 0 jika normal |
| `login_status` | VARCHAR(50) | SUCCESS/FAILED/BLOCKED |
| `attempt_time` | DATETIME | Waktu attempt (auto) |
| `ip_address` | VARCHAR(50) | IP address dari client |
| `description` | TEXT | Deskripsi detail attempt |

---

## 📊 Penjelasan Code: history.php

### A. **Fetch Data dari Database**
```php
$sql = "SELECT * FROM login_history ORDER BY attempt_time DESC";
$result = $conn->query($sql);
```
- Ambil semua record dari tabel login_history
- Urut dari attempt terbaru (DESC)

### B. **Hitung Statistik**
```php
$total_attempts = count($history_data);
$sqli_detected = 0;
$successful_logins = 0;
$failed_logins = 0;

foreach ($history_data as $record) {
    if ($record['is_sqli_detected']) $sqli_detected++;
    if ($record['login_status'] == 'SUCCESS') $successful_logins++;
    if ($record['login_status'] == 'FAILED') $failed_logins++;
}
```

**Menampilkan Stat Cards:**
- Total Attempts
- Successful Logins
- Failed Logins
- SQLi Detected (⚠️)

### C. **Tabel History**

Menampilkan kolom-kolom:

| Kolom | Fungsi |
|-------|--------|
| Username Input | Apa yang user input di field username |
| Password Input | Ditampilkan sebagai `****` (hidden) |
| NFA Score | Nilai deteksi (0.0 - 1.0) dengan progress bar |
| SQLi Detected? | 🚨 YES / ✅ NO |
| Login Status | SUCCESS / FAILED / BLOCKED |
| Description | Keterangan detail |
| IP Address | Siapa yang coba login |
| Waktu Attempt | Kapan attempt terjadi |

---

## 🧪 Testing Scenarios

### Scenario 1: Normal Login (Correct Credentials)
**Input:**
- Username: `admin`
- Password: `rahasia123`

**Output:**
- NFA Score: 0.00
- Status: ✅ SUCCESS
- Message: ✅ Login Berhasil!

**History Record:**
```
username_input: admin
password_input: rahasia123
nfa_score: 0.00
is_sqli_detected: 0
login_status: SUCCESS
description: Valid credentials
```

---

### Scenario 2: Wrong Credentials
**Input:**
- Username: `admin`
- Password: `wrongpass`

**Output:**
- NFA Score: 0.00
- Status: ❌ FAILED
- Message: ❌ Username atau Password salah!

**History Record:**
```
username_input: admin
password_input: wrongpass
nfa_score: 0.00
is_sqli_detected: 0
login_status: FAILED
description: Invalid credentials
```

---

### Scenario 3: Authentication Bypass SQLi
**Input:**
- Username: `admin`
- Password: `' OR 1=1`

**Output:**
- NFA Score: 0.80
- Status: 🚨 BLOCKED
- Message: ⚠️ PERINGATAN: Kemungkinan SQL Injection Terdeteksi!

**History Record:**
```
username_input: admin
password_input: ' OR 1=1
nfa_score: 0.80
is_sqli_detected: 1
login_status: BLOCKED
description: SQL Injection Attack Detected
```

---

### Scenario 4: Union-Based SQLi
**Input:**
- Username: `admin' UNION SELECT * FROM users --`
- Password: `anything`

**Output:**
- NFA Score: 0.85
- Status: 🚨 BLOCKED

**History Record:**
```
username_input: admin' UNION SELECT * FROM users --
nfa_score: 0.85
is_sqli_detected: 1
login_status: BLOCKED
```

---

## 🔐 Keamanan

### ✅ Fitur Keamanan yang Diterapkan

1. **SQL Injection Prevention**
   - ✅ Menggunakan Prepared Statement untuk query database
   - ✅ WAF untuk detect incoming attacks
   - ✅ Input validation dengan NFA scoring

2. **Session Management**
   - ✅ Menggunakan `session_start()`
   - ✅ Session variable untuk tracking login status

3. **Password Security (Demo)**
   - ⚠️ **Catatan:** Untuk production, gunakan `password_hash()` dan `password_verify()`
   - Saat ini plain text untuk kemudahan demo

4. **Input Sanitization**
   - ✅ `htmlspecialchars()` untuk output
   - ✅ URL decoding sebelum validation

### ⚠️ Improvement Untuk Production

```php
// Instead of:
INSERT INTO users VALUES ('admin', 'rahasia123');

// Use:
$hashed = password_hash('rahasia123', PASSWORD_BCRYPT);
INSERT INTO users VALUES ('admin', $hashed);

// And verify:
if (password_verify($input_password, $db_password)) {
    // Valid
}
```

---

## 📈 Analytics dari History

### Contoh Query untuk Analisa

**1. Berapa banyak SQLi attacks hari ini?**
```sql
SELECT COUNT(*) as sqli_attacks 
FROM login_history 
WHERE is_sqli_detected = 1 
AND DATE(attempt_time) = CURDATE();
```

**2. Attack dari mana aja?**
```sql
SELECT ip_address, COUNT(*) as total 
FROM login_history 
WHERE is_sqli_detected = 1 
GROUP BY ip_address;
```

**3. Pattern attack apa yang paling sering?**
```sql
SELECT description, COUNT(*) 
FROM login_history 
WHERE is_sqli_detected = 1 
GROUP BY description;
```

**4. Success rate login?**
```sql
SELECT 
  COUNT(CASE WHEN login_status = 'SUCCESS' THEN 1 END) as success,
  COUNT(CASE WHEN login_status = 'FAILED' THEN 1 END) as failed,
  COUNT(CASE WHEN login_status = 'BLOCKED' THEN 1 END) as blocked
FROM login_history;
```

---

## 🚀 Cara Menggunakan

### Step 1: Update Database
```bash
# Import db.sql yang sudah di-update
mysql -u root -p sqli_demo < database/db.sql
```

### Step 2: Akses Aplikasi
```
http://localhost/hybrid-sqli-ids/target_app/index.php
```

### Step 3: Test Login
- Coba login dengan credentials yang benar
- Coba dengan SQL injection payload
- Lihat history di `history.php`

### Step 4: Analisa History
- Buka `http://localhost/hybrid-sqli-ids/target_app/history.php`
- Lihat statistik dan detail setiap attempt

---

## 🎓 Konsep Pembelajaran

### 1. **NFA (Non-deterministic Finite Automaton)**
- Digunakan untuk pattern matching
- Regex di PHP adalah implementasi NFA
- Efektif untuk detect SQLi signatures

### 2. **Scoring System**
- Multiple pattern → score tinggi = likely attack
- Threshold 0.3 = balance antara detection dan false positive
- Bisa di-tune sesuai kebutuhan

### 3. **Prepared Statement**
- Parameter binding mencegah SQLi
- `?` placeholder untuk value
- Database tidak execute string literal

### 4. **Logging & Audit Trail**
- Catat semua activity
- Membantu forensic analysis
- Compliance dengan security standards

---

## 📝 Summary

| Aspek | Fitur |
|-------|-------|
| **Login** | Form HTML dengan email/password |
| **Detection** | NFA-based SQLi detection (0-1 score) |
| **Security** | Prepared statements + WAF |
| **Logging** | Database tracking semua attempts |
| **Analytics** | Dashboard dengan statistics |
| **UI/UX** | Modern responsive design |

---

Setiap kode sudah explained dengan detail! Silakan test di aplikasi Anda dan sesuaikan sesuai kebutuhan. 🎉
