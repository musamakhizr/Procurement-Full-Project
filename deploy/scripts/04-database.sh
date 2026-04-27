#!/usr/bin/env bash
# 04-database.sh — Secure MariaDB and create app database/user
# Pre-req: $DB_NAME, $DB_USER, $DB_PASS, $MYSQL_ROOT_PASS env vars
set -euo pipefail

: "${DB_NAME:?DB_NAME required}"
: "${DB_USER:?DB_USER required}"
: "${DB_PASS:?DB_PASS required}"
: "${MYSQL_ROOT_PASS:?MYSQL_ROOT_PASS required}"

echo ">> Applying tuning config…"
install -m 644 /opt/hexugo-deploy/templates/mariadb-tuning.cnf /etc/mysql/mariadb.conf.d/99-hexugo-tuning.cnf
systemctl restart mariadb

echo ">> Securing root account (unix_socket auth + password backup)…"
# On Ubuntu 24.04 MariaDB roots via unix_socket; we add a password as backup.
mariadb -u root <<SQL
ALTER USER 'root'@'localhost' IDENTIFIED VIA mysql_native_password USING PASSWORD('${MYSQL_ROOT_PASS}') OR unix_socket;
DELETE FROM mysql.user WHERE User='';
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';
FLUSH PRIVILEGES;
SQL

echo ">> Creating application database '${DB_NAME}' and user '${DB_USER}'…"
mariadb -u root <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

echo ">> Verifying connection as ${DB_USER}…"
mariadb -u "${DB_USER}" -p"${DB_PASS}" -e "SHOW DATABASES;" | grep -q "${DB_NAME}"

echo "<<< 04-database.sh complete (db='${DB_NAME}' user='${DB_USER}')"
