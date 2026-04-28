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