"""
Test script: compare text-based (/parse) vs pixel-based (/omr) checkbox detection.

Usage:
  python test_approaches.py <image_path> [adult|pediatric]

Examples:
  python test_approaches.py annex_a1_adult.jpg adult
  python test_approaches.py annex_a2_pediatric.jpg pediatric
  python test_approaches.py ../omr/test_form.png adult

The script calls OMR locally (port 5001) and PaddleOCR (port 5000).
Make sure both services are running, or adjust BASE_OMR / BASE_OCR below.
"""

import sys
import json
import requests

BASE_OMR = "http://localhost:5001"
BASE_OCR = "http://localhost:5000"


def ocr_text(image_path: str) -> str:
    """Get raw OCR text from PaddleOCR."""
    with open(image_path, "rb") as f:
        resp = requests.post(BASE_OCR, files={"file": f}, timeout=30)
    resp.raise_for_status()
    text = resp.json().get("text", "")
    print("\n── Raw OCR text ─────────────────────────────────────────")
    print(text or "(empty)")
    print("─────────────────────────────────────────────────────────")
    return text


def test_text_parse(text: str, form_type: str) -> dict:
    """Call /parse with raw OCR text."""
    resp = requests.post(
        f"{BASE_OMR}/parse",
        json={"text": text, "form_type": form_type},
        timeout=10,
    )
    resp.raise_for_status()
    return resp.json()


def test_pixel_omr(image_path: str, form_type: str) -> dict:
    """Call /omr with the image file."""
    with open(image_path, "rb") as f:
        resp = requests.post(
            f"{BASE_OMR}/omr",
            files={"file": f},
            data={"form_type": form_type},
            timeout=15,
        )
    resp.raise_for_status()
    return resp.json()


def main():
    if len(sys.argv) < 2:
        print(__doc__)
        sys.exit(1)

    image_path = sys.argv[1]
    form_type  = sys.argv[2].lower() if len(sys.argv) > 2 else "adult"

    print(f"Image   : {image_path}")
    print(f"Form    : {form_type}")

    # --- OCR text approach ---
    print("\n[1/3] Running PaddleOCR …")
    try:
        raw_text = ocr_text(image_path)
    except Exception as e:
        print(f"  PaddleOCR error: {e}")
        raw_text = ""

    print("\n[2/3] Text-based checkbox detection (/parse) …")
    if raw_text:
        try:
            text_result = test_text_parse(raw_text, form_type)
            print(json.dumps(text_result, indent=2))
        except Exception as e:
            print(f"  /parse error: {e}")
    else:
        print("  Skipped (no OCR text).")

    # --- Pixel OMR approach ---
    print("\n[3/3] Pixel-based OMR (/omr) …")
    try:
        omr_result = test_pixel_omr(image_path, form_type)
        print(json.dumps(omr_result, indent=2))
    except Exception as e:
        print(f"  /omr error: {e}")


if __name__ == "__main__":
    main()
