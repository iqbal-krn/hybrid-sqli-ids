<<<<<<< HEAD
import re
import urllib.parse
import sys
import json
import os
import pandas as pd
import joblib

def calculate_nfa_score(payload):
    """
    Mensimulasikan NFA menggunakan Regular Expression untuk mendeteksi pola SQLi.
    Mengembalikan nilai antara 0.0 hingga 1.0
    """
    if not isinstance(payload, str) or pd.isna(payload):
        return 0.0

    # Pre-processing: Decode URL dan ke huruf kecil
    payload = urllib.parse.unquote(str(payload)).lower()
    
    score = 0.0
    
    # Pola 1: Authentication Bypass (' OR 1=1)
    if re.search(r"('.*or.*1\s*=\s*1|.*or.*true)", payload):
        score += 0.8
        
    # Pola 2: Union Based (' UNION SELECT)
    if re.search(r"(union\s+.*select.*)", payload):
        score += 0.85
        
    # Pola 3: Kumpulan tanda kutip atau komentar SQL
    if re.search(r"(--|#|\/\*.*\*\/)", payload):
        score += 0.5
        
    # Pola 4: Fungsi mencurigakan
    if re.search(r"(sleep\(|waitfor\s+delay|benchmark\()", payload):
        score += 0.9

    return min(score, 1.0) # Maksimal skor adalah 1.0

def extract_features(payload):
    """Mengekstrak fitur struktural untuk Random Forest"""
    payload = urllib.parse.unquote(str(payload)).lower()
    
    nfa_score = calculate_nfa_score(payload)
    count_sql_kw = sum(payload.count(kw) for kw in ['select', 'union', 'insert', 'drop', 'delete', 'update'])
    freq_quote = payload.count("'") + payload.count('"')
    has_union = 1 if 'union' in payload else 0
    payload_length = len(payload)
    
    return [nfa_score, count_sql_kw, freq_quote, has_union, payload_length]

def detect_sqli(payload):
    """Mendeteksi SQL Injection menggunakan NFA Score dan Model Random Forest"""
    features = extract_features(payload)
    nfa_score = features[0]
    
    # Format fitur sebagai DataFrame agar sesuai dengan model pelatihan
    feature_df = pd.DataFrame([features], columns=['nfa_score', 'count_sql_kw', 'freq_quote', 'has_union', 'payload_length'])
    
    model_path = os.path.join(os.path.dirname(__file__), 'rf_model.pkl')
    
    is_sqli = False
    
    try:
        # Coba load dan gunakan model Random Forest jika ada
        if os.path.exists(model_path):
            rf_model = joblib.load(model_path)
            prediction = rf_model.predict(feature_df)[0]
            is_sqli = bool(prediction == 1)
        else:
            # Fallback ke NFA murni jika model belum dibuat
            is_sqli = bool(nfa_score > 0.3)
    except Exception as e:
        # Fallback ke NFA jika ada error saat load model
        is_sqli = bool(nfa_score > 0.3)
        
    return {
        "payload": payload,
        "nfa_score": nfa_score,
        "is_sqli": is_sqli
    }

if __name__ == "__main__":
    if len(sys.argv) > 1:
        payload = sys.argv[1]
        result = detect_sqli(payload)
        print(json.dumps(result))
    else:
        print(json.dumps({"error": "No payload provided"}))
=======
import pandas as pd
import re
import urllib.parse
import math
import pickle

# NFA SIMULATION (AUTOMATA)
def calculate_nfa_score(payload):
    """
    Mensimulasikan NFA menggunakan Regular Expression
    untuk mendeteksi pola SQL Injection
    """

    if not isinstance(payload, str) or pd.isna(payload):
        return 0.0

    payload = urllib.parse.unquote(str(payload)).lower()

    score = 0.0

    # Authentication bypass
    if re.search(r"('.*or.*1\s*=\s*1|.*or.*true)", payload):
        score += 0.8

    # UNION attack
    if re.search(r"(union\s+.*select.*)", payload):
        score += 0.85

    # SQL comment
    if re.search(r"(--|#|\/\*.*\*\/)", payload):
        score += 0.5

    # Time-based attack
    if re.search(r"(sleep\(|waitfor\s+delay|benchmark\()", payload):
        score += 0.9

    # tambahan
    if re.search(r"(drop\s+table|insert\s+into|delete\s+from)", payload):
        score += 0.8

    return min(score, 1.0)


# NFA DECISION (LAYER 1)
def nfa_detect(payload, threshold=0.7):
    """
    Jika skor NFA >= threshold → langsung dianggap SQL Injection
    """
    return calculate_nfa_score(payload) >= threshold


# Entropy
def calculate_entropy(text):
    if not isinstance(text, str) or len(text) == 0:
        return 0.0

    prob = [float(text.count(c)) / len(text) for c in set(text)]
    return -sum([p * math.log2(p) for p in prob])


# Feature Extraction
def extract_features(payload):
    payload = urllib.parse.unquote(str(payload)).lower()

    nfa_score = calculate_nfa_score(payload)

    count_sql_kw = sum(payload.count(kw) for kw in [
        'select', 'union', 'insert', 'drop', 'delete', 'update'
    ])

    freq_quote = payload.count("'") + payload.count('"')
    has_union = 1 if 'union' in payload else 0
    payload_length = len(payload)
    entropy = calculate_entropy(payload)

    return [
        nfa_score,
        count_sql_kw,
        freq_quote,
        has_union,
        payload_length,
        entropy
    ]

# Load Model
def load_model():
    with open("model/rf_model.pkl", "rb") as f:
        return pickle.load(f)

# Hybrid Detection
def detect_request(payload, model):
    """
    Hybrid Detection:
    1. NFA (rule-based)
    2. Random Forest (ML-based)
    """

    if not isinstance(payload, str) or pd.isna(payload):
        return "Invalid Input"

    payload = payload.lower()

    # LAYER 1: NFA
    if nfa_detect(payload):
        return "SQL Injection (Detected by NFA)"

    # LAYER 2: RANDOM FOREST
    features = extract_features(payload)
    prediction = model.predict([features])[0]

    if prediction == 1:
        return "SQL Injection (Detected by Random Forest)"
    else:
        return "Normal"

# Testing
if __name__ == "__main__":
    model = load_model()

    test_payloads = [
        "id=1",
        "id=1 OR 1=1",
        "username=admin' --",
        "search=normalquery",
        "UNION SELECT username, password FROM users"
    ]

    for payload in test_payloads:
        result = detect_request(payload, model)
        print(f"{payload} --> {result}")
>>>>>>> 2f4005d9fb5aed4d96072aeccff5705146122cd6
