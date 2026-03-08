#!/usr/bin/env bash
set -euo pipefail

OUT_DIR="${1:-./tests/e2e_guardian/certs}"
DAYS="${DAYS:-365}"
HOST_CN="${HOST_CN:-127.0.0.1}"

mkdir -p "$OUT_DIR"

openssl genrsa -out "$OUT_DIR/ca-key.pem" 2048
openssl req -x509 -new -nodes -key "$OUT_DIR/ca-key.pem" -sha256 -days "$DAYS" \
  -subj "/C=FR/ST=IDF/L=Paris/O=Guardian/OU=QA/CN=Guardian-CA" \
  -out "$OUT_DIR/ca.pem"

openssl genrsa -out "$OUT_DIR/server-key.pem" 2048
openssl req -new -key "$OUT_DIR/server-key.pem" \
  -subj "/C=FR/ST=IDF/L=Paris/O=Guardian/OU=QA/CN=$HOST_CN" \
  -out "$OUT_DIR/server.csr"
openssl x509 -req -in "$OUT_DIR/server.csr" -CA "$OUT_DIR/ca.pem" -CAkey "$OUT_DIR/ca-key.pem" -CAcreateserial \
  -out "$OUT_DIR/server-cert.pem" -days "$DAYS" -sha256

openssl genrsa -out "$OUT_DIR/client-key.pem" 2048
openssl req -new -key "$OUT_DIR/client-key.pem" \
  -subj "/C=FR/ST=IDF/L=Paris/O=Guardian/OU=QA/CN=guardian-client" \
  -out "$OUT_DIR/client.csr"
openssl x509 -req -in "$OUT_DIR/client.csr" -CA "$OUT_DIR/ca.pem" -CAkey "$OUT_DIR/ca-key.pem" -CAcreateserial \
  -out "$OUT_DIR/client-cert.pem" -days "$DAYS" -sha256

echo "Certificats générés dans $OUT_DIR"
