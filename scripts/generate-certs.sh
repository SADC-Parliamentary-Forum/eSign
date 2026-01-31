#!/bin/bash
mkdir -p docker/nginx/ssl
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout docker/nginx/ssl/privkey.pem \
    -out docker/nginx/ssl/fullchain.pem \
    -subj "/C=BW/ST=Gaborone/L=Gaborone/O=SADC PF/OU=IT/CN=esign.sadcpf.org"
echo "Self-signed certificates generated in docker/nginx/ssl/"
