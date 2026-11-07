FROM python:3.12-slim

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1

WORKDIR /srv/travis-ml

RUN apt-get update \
    && apt-get install -y --no-install-recommends libgl1 libglib2.0-0 \
    && rm -rf /var/lib/apt/lists/*

COPY Machine_Learning/requirements-vps.txt ./requirements.txt
RUN pip install --no-cache-dir -r requirements.txt

COPY Machine_Learning/api.py ./api.py
COPY computer_vision/ticket_ocr.py ./ticket_ocr.py
COPY computer_vision/license_ocr.py ./license_ocr.py
COPY Machine_Learning/models ./models
COPY Machine_Learning/results ./results

EXPOSE 5001

CMD ["gunicorn", "--bind", "0.0.0.0:5001", "--workers", "2", "--threads", "2", "--timeout", "120", "api:app"]
