FROM python:3.12-alpine
WORKDIR /app
COPY docker/sip-mock/server.py /app/server.py
EXPOSE 5070/udp
CMD ["python3", "/app/server.py"]
