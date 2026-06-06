import re
import cv2
import numpy as np
from flask import Flask, request, jsonify

app = Flask(__name__)

# ── Unicode checkbox indicators ───────────────────────────────────────────────
# Characters PaddleOCR may output for a CHECKED box or handwritten tick
CHECKED_CHARS = {'☑', '✓', '✔', '☒', '✗', '√', 'v', 'V', 'x', 'X'}
# Characters PaddleOCR may output for an UNCHECKED (empty) box
UNCHECKED_CHARS = {'□', '☐', '○', 'O'}

# ── Label lists (must match frontend/backend label order exactly) ──────────

ADULT_CONDITIONS_LEFT = [
    "Admission to ICU",
    "Anorexia Nervosa / Bulimia Nervosa",
    "Cachexia (temporal wasting, muscle wasting, cancer, cardiac)",
    "Cerebrovascular accident",
    "Coma",
    "Diabetes Mellitus / Gestational Diabetes Mellitus",
    "Gastrointestinal disease or complication",
    "Liver disease",
]
ADULT_CONDITIONS_RIGHT = [
    "Malabsorption (celiac sprue, ulcerative colitis, Crohn's disease, short bowel syndrome)",
    "Multiple trauma (closed head injury, pressure injury)",
    "Non-healing wounds",
    "On tube feeding / parenteral nutrition",
    "Renal disease (acute, chronic, undergoing dialysis)",
    "Sepsis",
    "Serum albumin <3.5 gm/L",
]
ADULT_INTAKE_LEFT = [
    "Unintentional weight loss in the past 3 months",
    "Reduced dietary intake in the past week",
    "BMI below 18.5 and above 30 (to be computed by the RND)",
    "Others",
]
ADULT_INTAKE_RIGHT = [
    "Pregnant patient is aged 18 years old or 35 years old",
    "Pregnancy with Hyperemesis Gravidarum / Pregnancy-induced Hypertension",
    "Multiple Pregnancy",
    "Lactating Mother",
]

PEDIATRIC_CONDITIONS_LEFT = [
    "Admission to ICU",
    "Anorexia Nervosa / Bulimia Nervosa",
    "Cachexia (temporal wasting, muscle wasting, cancer, cardiac)",
    "Cerebrovascular accident",
    "Coma",
    "Congenital anomalies (e.g. Down's Syndrome, Craniofacial anomalies, Spina bifida, Hydrocephalus, Chiari Malformation)",
    "Diabetes Mellitus / Gestational Diabetes Mellitus",
    "Gastrointestinal disease or complication / impending GI surgery (e.g. Pancreatitis, Inflammatory Bowel Disease, GERD, Malabsorption conditions, Crohn's Disease)",
    "Inborn errors of metabolism",
]
PEDIATRIC_CONDITIONS_RIGHT = [
    "Inflammatory diseases (e.g. Sepsis, Encephalitis, Meningitis, Kawasaki Disease, Enterocolitis, Community-acquired pneumonia, Upper/Lower Respiratory Tract Infection)",
    "Liver disease",
    "Malabsorption (celiac sprue, ulcerative colitis, Crohn's disease, short bowel syndrome)",
    "Multiple trauma (closed head injury, penetrating trauma, multiple fractures)",
    "Neurologically challenged (e.g. ADHD, Cerebral palsy, seizure disorders, Infantile spasms)",
    "On tube feeding / parenteral nutrition",
    "Renal disease (acute, chronic, undergoing dialysis)",
    "Sepsis",
    "Serum albumin <3.5 gm/L",
]
PEDIATRIC_INTAKE_LEFT = [
    "Unintentional weight loss in the past 3 months",
    "Patient on breastmilk feeding",
    "Reduced dietary intake in the past week",
    "Reduction of dietary intake in the past week/s and/or during the hospital stay",
    "For patients ages >5 years old to <18 years old, 364 days: BMI z-scores above +2 and below -2 (c/o RND)",
]
PEDIATRIC_INTAKE_RIGHT = [
    "For patients ages >2 to 5 years old: Weight for Height z-scores above +2 and below -2 (c/o RND)",
    "For patients ages 1 month to 2 years old: Weight for Length z-scores above +2 and below -2 (c/o RND)",
    "Others",
]

# ── Text-based checkbox detection ─────────────────────────────────────────

def _normalise(text: str) -> str:
    return re.sub(r'\s+', ' ', text.strip().lower())

def _word_overlap(a: str, b: str) -> float:
    """Fraction of significant words in `a` that appear anywhere in `b`."""
    words = [w for w in a.split() if len(w) > 3]
    if not words:
        return 0.0
    return sum(1 for w in words if w in b) / len(words)

def _fuzzy_match(text: str, labels: list[str]) -> str | None:
    """Return best-matching label or None."""
    t = _normalise(text)
    for label in labels:
        lbl = _normalise(label)
        if t in lbl or lbl in t:
            return label
        if _word_overlap(t, lbl) >= 0.6:
            return label
    return None

def parse_checkboxes_from_text(raw_text: str, form_type: str) -> dict:
    """
    Parse PaddleOCR text output to find checked items.
    Handles:
      - Check char at start:  "☑ Label text"
      - Check char at end:    "Label text ☑"
      - Stand-alone check char on its own line followed by the label line
    Works for any hospital form layout — no pixel positions needed.
    """
    if form_type == 'pediatric':
        conditions = PEDIATRIC_CONDITIONS_LEFT + PEDIATRIC_CONDITIONS_RIGHT
        intake     = PEDIATRIC_INTAKE_LEFT    + PEDIATRIC_INTAKE_RIGHT
    else:
        conditions = ADULT_CONDITIONS_LEFT + ADULT_CONDITIONS_RIGHT
        intake     = ADULT_INTAKE_LEFT    + ADULT_INTAKE_RIGHT

    checked_conditions = []
    checked_intake     = []

    lines = raw_text.split('\n')
    pending_check = False   # true when the previous line was a lone check character

    for line in lines:
        stripped = line.strip()
        if not stripped:
            pending_check = False
            continue

        first = stripped[0]
        last  = stripped[-1]

        # Case 1: line is a lone check/uncheck character
        if len(stripped) == 1:
            pending_check = first in CHECKED_CHARS
            continue

        # Case 2: previous line was a lone check char → this line is the label
        if pending_check:
            candidate = stripped
        # Case 3: check char leads the line  "☑ Reduced dietary intake…"
        elif first in CHECKED_CHARS:
            candidate = stripped[1:].strip()
        # Case 4: check char trails the line  "Reduced dietary intake… ☑"
        elif last in CHECKED_CHARS:
            candidate = stripped[:-1].strip()
        else:
            pending_check = False
            continue

        pending_check = False

        if not candidate:
            continue

        match = _fuzzy_match(candidate, conditions)
        if match and match not in checked_conditions:
            checked_conditions.append(match)
            continue

        match = _fuzzy_match(candidate, intake)
        if match and match not in checked_intake:
            checked_intake.append(match)

    return {
        'clinical_conditions':   checked_conditions,
        'intake_weight_history': checked_intake,
        'method': 'ocr_text',
    }


# ── OMR core ───────────────────────────────────────────────────────────────

def find_checkboxes(gray_region, img_width):
    """
    Find all checkbox contours in a grayscale image region.
    Returns list of dicts: {x, y, w, h, checked}
    """
    blurred = cv2.GaussianBlur(gray_region, (3, 3), 0)
    _, thresh = cv2.threshold(blurred, 180, 255, cv2.THRESH_BINARY_INV)

    # EXTERNAL only — avoids inner/outer border duplicates
    contours, _ = cv2.findContours(thresh, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)

    min_px = max(8, int(img_width * 0.007))
    max_px = max(30, int(img_width * 0.028))

    candidates = []
    for cnt in contours:
        x, y, w, h = cv2.boundingRect(cnt)
        if not (min_px <= w <= max_px and min_px <= h <= max_px):
            continue
        if not (0.55 <= w / h <= 1.8):
            continue

        margin = max(1, min(w, h) // 5)
        interior = thresh[y + margin: y + h - margin, x + margin: x + w - margin]
        if interior.size == 0:
            continue
        fill = np.count_nonzero(interior) / interior.size
        candidates.append({'x': int(x), 'y': int(y), 'w': int(w), 'h': int(h), 'fill': fill, 'checked': fill > 0.18})

    # Deduplicate: if two boxes have centres within 8px of each other, keep the one with higher fill
    candidates.sort(key=lambda b: b['fill'], reverse=True)
    boxes = []
    for box in candidates:
        cx, cy = box['x'] + box['w'] / 2, box['y'] + box['h'] / 2
        duplicate = any(
            abs(cx - (b['x'] + b['w'] / 2)) < 8 and abs(cy - (b['y'] + b['h'] / 2)) < 8
            for b in boxes
        )
        if not duplicate:
            boxes.append(box)

    return boxes


def split_columns(boxes, img_width):
    """Split boxes into left / right columns by image midpoint."""
    mid = img_width / 2
    left  = sorted([b for b in boxes if (b['x'] + b['w'] / 2) < mid],  key=lambda b: b['y'])
    right = sorted([b for b in boxes if (b['x'] + b['w'] / 2) >= mid], key=lambda b: b['y'])
    return left, right


def map_labels(boxes, left_labels, right_labels, img_width):
    """Map checked boxes to label strings by positional order."""
    left_boxes, right_boxes = split_columns(boxes, img_width)
    checked = []
    for i, box in enumerate(left_boxes):
        if box['checked'] and i < len(left_labels):
            checked.append(left_labels[i])
    for i, box in enumerate(right_boxes):
        if box['checked'] and i < len(right_labels):
            checked.append(right_labels[i])
    return checked


def crop_section(gray, y_start_pct, y_end_pct):
    """Return a horizontal slice of the image by percentage height."""
    h = gray.shape[0]
    return gray[int(h * y_start_pct): int(h * y_end_pct), :]


# ── Approximate y-ranges for each section (tuned to B.06 / B.07 layout) ──
# These are fractions of total image height.
# Form layout:  header ~0–24%, Section A ~24–62%, Section B ~62–80%, referral ~80–100%
SECTION_A = (0.24, 0.62)
SECTION_B = (0.62, 0.82)

# ── Flask endpoints ────────────────────────────────────────────────────────

@app.route('/health', methods=['GET'])
def health():
    return jsonify({'status': 'ok'})


@app.route('/parse', methods=['POST'])
def parse_text():
    """
    Text-based checkbox detection — works for any hospital form layout.
    Body: { "text": "<raw OCR output>", "form_type": "adult" | "pediatric" }
    """
    data = request.get_json(silent=True)
    if not data or 'text' not in data:
        return jsonify({'error': 'Missing text field'}), 400

    result = parse_checkboxes_from_text(
        raw_text=data['text'],
        form_type=data.get('form_type', 'adult').lower(),
    )
    return jsonify(result)


@app.route('/omr', methods=['POST'])
def omr_detect():
    if 'file' not in request.files:
        return jsonify({'error': 'No file provided'}), 400

    form_type = request.form.get('form_type', 'adult').lower()

    file_bytes = request.files['file'].read()
    img_array  = np.frombuffer(file_bytes, np.uint8)
    img        = cv2.imdecode(img_array, cv2.IMREAD_COLOR)

    if img is None:
        return jsonify({'error': 'Could not decode image'}), 400

    gray    = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    h, w    = gray.shape

    # Crop to each section
    sec_a = crop_section(gray, *SECTION_A)
    sec_b = crop_section(gray, *SECTION_B)

    boxes_a = find_checkboxes(sec_a, w)
    boxes_b = find_checkboxes(sec_b, w)

    if form_type == 'pediatric':
        cond_left, cond_right   = PEDIATRIC_CONDITIONS_LEFT, PEDIATRIC_CONDITIONS_RIGHT
        intake_left, intake_right = PEDIATRIC_INTAKE_LEFT,   PEDIATRIC_INTAKE_RIGHT
    else:
        cond_left, cond_right   = ADULT_CONDITIONS_LEFT,   ADULT_CONDITIONS_RIGHT
        intake_left, intake_right = ADULT_INTAKE_LEFT,     ADULT_INTAKE_RIGHT

    return jsonify({
        'clinical_conditions':  map_labels(boxes_a, cond_left,   cond_right,   w),
        'intake_weight_history': map_labels(boxes_b, intake_left, intake_right, w),
    })


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5001, debug=False)
