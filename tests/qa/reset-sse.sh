#!/bin/bash

# Load environment variables from .env file in the same directory
set -a
source .env
set +a

ssh -t "$SSH_USER@$SSH_HOST" << EOF
rm -rf /var/www/html/* 2>/dev/null
wp core download --version=$WP_VERSION
wp config create
mariadb -e "DROP DATABASE $DB_NAME; CREATE DATABASE $DB_NAME;"
wp core install
exit
EOF
