# 🎯 QUICK REFERENCE - Sistem Login & SQL Injection Detection

## 📌 Struktur File Baru

```
target_app/
├── index.php          ← Login page + SQLi detection
├── history.php        ← Login history dashboard
├── database/
│   └── db.sql         ← Updated dengan tabel login_history
```

---

## 🔑 Key Functions di index.php

### 1️⃣ `checkSQLiWithWAF($payload)`
**Fungsi:** Menghitung NFA Score untuk deteksi SQL Injection  
**Return:** Float (0.0 - 1.0)

```
Input: "admin' OR 1=1"
       ↓
Deteksi 4 Pola:
- ' OR 1=1         → +0.8  (Auth Bypass)
- UNION SELECT     → +0.85 (Union-based)
- --, #, /**/      → +0.5  (Comments)
- SLEEP, WAITFOR   → +0.9  (Time-based)
       ↓
Output: 0.8 (Score) → > 0.3 threshold → SQLI DETECTED! 🚨
```

### 2️⃣ `saveLoginHistory($username, $password, $nfa_score, $is_sqli, $status, $description, $conn)`
**Fungsi:** Menyimpan attempt login ke database

**Data yang disimpan:**
- Username & Password (input user)
- NFA Score (hasil deteksi)
- SQLi Detected? (boolean)
- Status (SUCCESS/FAILED/BLOCKED)
- IP Address & Timestamp

---

## 🔄 Flow Login Process

```
┌─────────────────────────────────┐
│  User Input Username & Password │
└────────────┬────────────────────┘
             │
             ↓
┌──────────────────────────────────────┐
│ Check SQLi dengan WAF                │
│ (checkSQLiWithWAF function)          │
└────────────┬─────────────────────────┘
             │
        ┌────┴────┐
        ↓         ↓
    NFA > 0.3?   No
        │           │
        ↓           ↓
      YES      Prepared Statement
      │        Username? Password?
      │             │
      ↓         ┌───┴────┐
   BLOCK        ↓        ↓
   (Save)    YES       NO
   History    │         │
             ↓         ↓
           SUCCESS   FAILED
           (Save)    (Save)
           History   History
              │         │
              └────┬────┘
                   ↓
          Show Dashboard/Error
```

---

## 💾 Database Schema

### Tabel: login_history

```sql
┌─────────────────────────────────────────────────────────────┐
│ login_history                                               │
├─────────────────┬──────────┬─────────────────────────────┤
│ id              │ INT      │ Primary Key (auto)          │
├─────────────────┼──────────┼─────────────────────────────┤
│ username_input  │ VARCHAR  │ Input username dari form    │
├─────────────────┼──────────┼─────────────────────────────┤
│ password_input  │ VARCHAR  │ Input password dari form    │
├─────────────────┼──────────┼─────────────────────────────┤
│ nfa_score       │ FLOAT    │ Hasil deteksi (0.0-1.0)   │
├─────────────────┼──────────┼─────────────────────────────┤
│ is_sqli_detected│ BOOLEAN  │ 1=SQLi, 0=Normal           │
├─────────────────┼──────────┼─────────────────────────────┤
│ login_status    │ VARCHAR  │ SUCCESS/FAILED/BLOCKED     │
├─────────────────┼──────────┼─────────────────────────────┤
│ ip_address      │ VARCHAR  │ IP client yang attempt     │
├─────────────────┼──────────┼─────────────────────────────┤
│ description     │ TEXT     │ Keterangan detail          │
├─────────────────┼──────────┼─────────────────────────────┤
│ attempt_time    │ DATETIME │ Timestamp auto             │
└─────────────────┴──────────┴─────────────────────────────┘
```

---

## 🧪 Test Cases & Expected Results

### Test 1: Normal Login ✅
```
Username: admin
Password: rahasia123
    ↓
NFA Score: 0.00
Status: SUCCESS
Message: ✅ Login Berhasil!
DB: is_sqli_detected=0, login_status=SUCCESS
```

### Test 2: Wrong Credentials ❌
```
Username: admin
Password: salahpass
    ↓
NFA Score: 0.00
Status: FAILED
Message: ❌ Username atau Password salah!
DB: is_sqli_detected=0, login_status=FAILED
```

### Test 3: Authentication Bypass SQLi 🚨
```
Username: admin
Password: ' OR 1=1
    ↓
NFA Score: 0.80  (> 0.3 threshold)
Status: BLOCKED
Message: ⚠️ PERINGATAN: SQL Injection Terdeteksi!
DB: is_sqli_detected=1, login_status=BLOCKED
    description: "SQL Injection Attack Detected"
```

### Test 4: Union-Based SQLi 🚨
```
Username: admin' UNION SELECT * FROM users --
Password: anything
    ↓
NFA Score: 0.85  (> 0.3 threshold)
Status: BLOCKED
DB: is_sqli_detected=1
```

---

## 📊 History.php Features

### Statistics Cards
- **Total Attempts** - Total semua login attempts
- **Successful Logins** - Berhasil login (SUCCESS)
- **Failed Logins** - Salah password/username (FAILED)
- **SQLi Detected** - Attacks yang terblock (BLOCKED)

### History Table
Menampilkan:
- Username & Password (masked)
- NFA Score dengan progress bar
- SQLi status (YES/NO)
- Login status badge
- IP address & timestamp

---

## 🛡️ Security Highlights

### ✅ Yang Sudah Aman
1. **Prepared Statements** untuk authentication query
   ```php
   $stmt = $conn->prepare("SELECT ... WHERE username = ? AND password = ?");
   $stmt->bind_param("ss", $username, $password);
   ```

2. **WAF Detection** untuk incoming attacks
   ```php
   if ($nfa_score_result > 0.3) {
       // Block & log
   }
   ```

3. **Input Output Handling**
   ```php
   echo htmlspecialchars($user); // Prevent XSS
   ```

### ⚠️ Untuk Production (Improvement)
```php
// Current (Demo):
INSERT INTO users VALUES ('admin', 'rahasia123');

// Production (Better):
$hash = password_hash('rahasia123', PASSWORD_BCRYPT);
INSERT INTO users VALUES ('admin', $hash);

// And verify:
if (password_verify($input, $db_hash)) { ... }
```

---

## 🎨 UI/UX Components

### index.php
- **Two-column layout** (info section + login form)
- **Form inputs** (username, password)
- **Result messages** (success/error/warning)
- **Detection visualization** (NFA score bar)
- **Dashboard** (after login successful)

### history.php
- **Statistics cards** dengan metrics
- **Responsive table** dengan full history
- **Color-coded badges** (success/failed/blocked)
- **Progress bars** untuk NFA score

---

## 🔍 Analisis & Monitoring

### Dari History.php Bisa Kita Ketahui:

| Pertanyaan | Query/Action |
|-----------|-----------|
| Berapa SQLi attacks hari ini? | COUNT WHERE is_sqli_detected=1 AND DATE=today |
| Attack dari IP mana aja? | GROUP BY ip_address |
| Pattern attack apa? | GROUP BY description |
| Success rate berapa persen? | COUNT SUCCESS / COUNT TOTAL |
| User mana yang paling banyak login? | GROUP BY username_input |

---

## 🚀 Deployment Checklist

- [ ] Update database dengan db.sql (baru)
- [ ] Replace index.php dengan version baru
- [ ] Upload history.php (file baru)
- [ ] Test credentials: admin/rahasia123
- [ ] Test SQLi detection dengan payload
- [ ] Verify history table populated
- [ ] Check UI responsive di mobile
- [ ] Monitor server logs

---

## 📞 Troubleshooting

### Problem: "Koneksi gagal"
```
→ Check MySQL service running
→ Verify credentials (user, password, database)
```

### Problem: "Table doesn't exist"
```
→ Run: mysql -u root -p < database/db.sql
→ Verify login_history table created
```

### Problem: History tidak menyimpan
```
→ Check database permissions
→ Verify INSERT permission untuk user
→ Check SQL syntax di saveLoginHistory function
```

### Problem: SQLi tidak ter-detect
```
→ Verify NFA score calculation
→ Check threshold value (default: 0.3)
→ Test dengan payload: ' OR 1=1
```

---

**Version:** 1.0  
**Last Updated:** 2026-04-28  
**Status:** Production Ready ✅
