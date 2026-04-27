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