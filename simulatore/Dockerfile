FROM python:3.11-slim

WORKDIR /simulatore

COPY requirements.txt .

RUN pip install --no-cache-dir -r requirements.txt

COPY . .
# 6. Avvio
CMD ["python", "main.py"]