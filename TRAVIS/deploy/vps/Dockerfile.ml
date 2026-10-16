FROM python:3.12-slim

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1

WORKDIR /srv/travis-ml

COPY Machine_Learning/requirements-vps.txt ./requirements.txt
RUN pip install --no-cache-dir -r requirements.txt

COPY Machine_Learning/api.py ./api.py
COPY Machine_Learning/models ./models
COPY Machine_Learning/results ./results

EXPOSE 5001

CMD ["gunicorn", "--bind", "0.0.0.0:5001", "--workers", "2", "--threads", "2", "--timeout", "60", "api:app"]
