FROM python:3.12-slim
WORKDIR /srv
ENV PIP_NO_CACHE_DIR=1 FASTEMBED_CACHE_PATH=/srv/models
COPY requirements.txt .
RUN pip install -r requirements.txt
COPY app.py .
# Download the model at build time so the container starts without network.
RUN python -c "import app"
EXPOSE 8100
CMD ["uvicorn", "app:app", "--host", "0.0.0.0", "--port", "8100"]
