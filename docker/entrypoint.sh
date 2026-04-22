#!/bin/bash
set -e

SMTP_HOST_VAL="${SMTP_HOST:-}"
SMTP_PORT_VAL="${SMTP_PORT:-587}"
SMTP_FROM_VAL="${SMTP_FROM:-${MAIL_FROM:-}}"
SMTP_USER_VAL="${SMTP_USER:-}"
SMTP_PASS_VAL="${SMTP_PASS:-}"

if [ -n "$SMTP_HOST_VAL" ] && [ -n "$SMTP_FROM_VAL" ] && [ -n "$SMTP_USER_VAL" ] && [ -n "$SMTP_PASS_VAL" ]; then
  cat >/etc/msmtprc <<EOF
# Generado automáticamente por entrypoint
defaults
auth           on
tls            on
tls_trust_file /etc/ssl/certs/ca-certificates.crt
logfile        /tmp/msmtp.log

account default
host ${SMTP_HOST_VAL}
port ${SMTP_PORT_VAL}
from ${SMTP_FROM_VAL}
user ${SMTP_USER_VAL}
password ${SMTP_PASS_VAL}
EOF

  chmod 600 /etc/msmtprc
else
  echo "[entrypoint] SMTP incompleto: se omite generación de /etc/msmtprc"
  echo "[entrypoint] Requerido: SMTP_HOST, SMTP_FROM (o MAIL_FROM), SMTP_USER, SMTP_PASS"
fi

exec "$@"
