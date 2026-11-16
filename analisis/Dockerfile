# Estación del analista forense: Jupyter Lab + DuckDB (motor columnar) + Pandas + Matplotlib.
# DuckDB procesa cientos de miles/millones de líneas JSON en un portátil sin la JVM de Spark;
# las consultas son SQL estándar y se trasladan casi sin cambios a Spark SQL.
FROM python:3.12-slim
ENV PYTHONUNBUFFERED=1 PIP_NO_CACHE_DIR=1 PIP_DISABLE_PIP_VERSION_CHECK=1 \
    HOME=/home/analista MPLCONFIGDIR=/tmp/matplotlib JUPYTER_RUNTIME_DIR=/tmp/jupyter-runtime
RUN apt-get update && apt-get install -y --no-install-recommends fonts-dejavu-core tini \
 && rm -rf /var/lib/apt/lists/*
COPY requirements.txt /tmp/requirements.txt
RUN pip install -r /tmp/requirements.txt
RUN mkdir -p /home/analista/notebooks /home/analista/salida /evidencia/logs \
 && chmod -R 0777 /home/analista
WORKDIR /home/analista
EXPOSE 8888
ENTRYPOINT ["tini", "--"]
CMD ["jupyter", "lab", "--ip=0.0.0.0", "--port=8888", "--no-browser", "--ServerApp.root_dir=/home/analista", "--ServerApp.default_url=/lab/tree/notebooks/analisis_forense.ipynb"]
