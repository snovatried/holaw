#!/bin/bash
set -e

SMTP_HOST_VAL="${SMTP_HOST:-}"
SMTP_PORT_VAL="${SMTP_PORT:-587}"
SMTP_FROM_VAL="${SMTP_FROM:-${MAIL_FROM:-}}"
SMTP_USER_VAL="${SMTP_USER:-}"
SMTP_PASS_VAL="${SMTP_PASS:-}"
SMTP_TLS_VAL="${SMTP_TLS:-}"
SMTP_AUTH_VAL="${SMTP_AUTH:-}"

if [ -n "$SMTP_HOST_VAL" ] && [ -n "$SMTP_FROM_VAL" ]; then
  if [ -z "$SMTP_AUTH_VAL" ]; then
    if [ -n "$SMTP_USER_VAL" ] && [ -n "$SMTP_PASS_VAL" ]; then
      SMTP_AUTH_VAL="on"
    else
      SMTP_AUTH_VAL="off"
    fi
  fi

  if [ -z "$SMTP_TLS_VAL" ]; then
    if [ "$SMTP_HOST_VAL" = "localhost" ] || [ "$SMTP_HOST_VAL" = "127.0.0.1" ]; then
      SMTP_TLS_VAL="off"
    else
      SMTP_TLS_VAL="on"
    fi
  fi

  cat >/etc/msmtprc <<EOF
# Generado automáticamente por entrypoint
defaults
auth           ${SMTP_AUTH_VAL}
tls            ${SMTP_TLS_VAL}
tls_trust_file /etc/ssl/certs/ca-certificates.crt
logfile        /tmp/msmtp.log

account default
host ${SMTP_HOST_VAL}
port ${SMTP_PORT_VAL}
from ${SMTP_FROM_VAL}
EOF

  if [ "$SMTP_AUTH_VAL" = "on" ]; then
    {
      echo "user ${SMTP_USER_VAL}"
      echo "password ${SMTP_PASS_VAL}"
    } >>/etc/msmtprc
  fi

  chmod 600 /etc/msmtprc
else
  echo "[entrypoint] SMTP incompleto: se omite generación de /etc/msmtprc"
  echo "[entrypoint] Requerido: SMTP_HOST y SMTP_FROM (o MAIL_FROM)"
fi

exec "$@"
