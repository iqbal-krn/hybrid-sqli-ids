import re
import urllib.parse
import pandas as pd
import math 

# NFA SIMULATION (RULE-BASED DETECTION)
def calculate_nfa_score(payload):
    """
    Mensimulasikan NFA menggunakan Regular Expression untuk mendeteksi pola SQL Injection.
    
    Konsep:
    - Regex di sini merepresentasikan Non-Deterministic Finite Automata (NFA)
    - Setiap pola = jalur transisi dalam automata
    - Output berupa skor probabilitas (0.0 - 1.0)

    Return:
    float (0.0 - 1.0)
    """

    # Validasi input
    if not isinstance(payload, str) or pd.isna(payload):
        return 0.0

    # Preprocessing
    payload = urllib.parse.unquote(str(payload)).lower()
    score = 0.0

    # Pola 1: Authentication Bypass (' OR 1=1)
    if re.search(r"('.*or.*1\s*=\s*1|.*or.*true)", payload):
        score += 0.8

    # Pola 2: UNION-based SQLi
    if re.search(r"(union\s+.*select.*)", payload):
        score += 0.85

    # Pola 3: SQL Comment Injection
    if re.search(r"(--|#|\/\*.*\*\/)", payload):
        score += 0.5

    # Pola 4: Time-based SQLi
    if re.search(r"(sleep\(|waitfor\s+delay|benchmark\()", payload):
        score += 0.9

    # Optional tambahan (biar lebih kuat)
    if re.search(r"(drop\s+table|insert\s+into|delete\s+from)", payload):
        score += 0.8

    return min(score, 1.0)  # maksimal 1.0

# NFA DECISION (LAYER 1 - HYBRID SYSTEM)
def nfa_detect(payload, threshold=0.7):
    """ Menentukan apakah payload dianggap SQL Injection oleh NFA.

    threshold:
    - >= 0.7 → dianggap attack
    - < 0.7 → lanjut ke Machine Learning"""
    score = calculate_nfa_score(payload)
    return score >= threshold

# ENTROPY
def calculate_entropy(text):
    # Mengukur kompleksitas string. Payload SQLi biasanya punya entropy lebih tinggi.
    if not isinstance(text, str) or len(text) == 0:
        return 0.0

    prob = [float(text.count(c)) / len(text) for c in set(text)]
    return -sum([p * math.log2(p) for p in prob])

# FEATURE EXTRACTION (UNTUK RANDOM FOREST)
def extract_features(payload):
    """ Mengekstrak fitur dari payload untuk digunakan oleh Random Forest.

    Kombinasi:
    - Rule-based (NFA score)
    - Structural features
    - Statistical features (entropy)
    """
    # Preprocessing
    payload = urllib.parse.unquote(str(payload)).lower()

    # Feature 1: NFA Score (integrasi automata + ML)
    nfa_score = calculate_nfa_score(payload)

    # Feature 2: Jumlah keyword SQL
    count_sql_kw = sum(payload.count(kw) for kw in [
        'select', 'union', 'insert', 'drop', 'delete', 'update'
    ])

    # Feature 3: Frekuensi tanda kutip (indikasi injection)
    freq_quote = payload.count("'") + payload.count('"')

    # Feature 4: Apakah ada UNION (binary feature)
    has_union = 1 if 'union' in payload else 0

    # Feature 5: Panjang payload
    payload_length = len(payload)

    # Feature 6: Entropy (fitur tambahan)
    entropy = calculate_entropy(payload)

    return [
        nfa_score,
        count_sql_kw,
        freq_quote,
        has_union,
        payload_length,
        entropy
    ]
