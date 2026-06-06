import io
import logging
import numpy as np
from PIL import Image
from flask import Flask, request, jsonify
from paddleocr import PaddleOCR

logging.basicConfig(level=logging.WARNING)

app = Flask(__name__)

# Initialise once at startup — downloads models on first run (~300 MB)
ocr = PaddleOCR(use_angle_cls=True, lang='en', use_gpu=False, show_log=False)


@app.route('/', methods=['POST'])
@app.route('/ocr', methods=['POST'])
def extract():
    if 'file' not in request.files:
        return jsonify({'error': 'No file provided'}), 400

    file_bytes = request.files['file'].read()
    try:
        img = Image.open(io.BytesIO(file_bytes)).convert('RGB')
    except Exception as e:
        return jsonify({'error': f'Cannot decode image: {e}'}), 400

    img_array = np.array(img)
    result = ocr.ocr(img_array, cls=True)

    lines = []
    if result:
        for page in result:
            if page:
                for detection in page:
                    if detection and len(detection) > 1:
                        text, confidence = detection[1]
                        if confidence > 0.5:
                            lines.append(text)

    return jsonify({'text': '\n'.join(lines)})


@app.route('/health', methods=['GET'])
def health():
    return jsonify({'status': 'ok'})


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=False)
