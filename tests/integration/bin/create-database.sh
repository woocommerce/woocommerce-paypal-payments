#!/bin/bash
# Creates a new, empty test site in its own database. Run it in the DDEV web container.
# The integration tests then write to this database, not to the DDEV site database.
set -euo pipefail

DB_NAME="${PPCP_INTEGRATION_DB_NAME:?Set PPCP_INTEGRATION_DB_NAME}"

# Only root can create a database. DDEV gives the root user the password "root".
mysql -uroot -proot -hdb -e "
	DROP DATABASE IF EXISTS \`${DB_NAME}\`;
	CREATE DATABASE \`${DB_NAME}\`;
	GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO 'db'@'%';
"

wp_test() {
	wp --exec="define( 'DB_NAME', '${DB_NAME}' );" "$@"
}

wp_test core install \
	--title="${WP_TITLE}" \
	--admin_user="${ADMIN_USER}" \
	--admin_password="${ADMIN_PASS}" \
	--admin_email="${ADMIN_EMAIL}" \
	--url="${DDEV_PRIMARY_URL}" \
	--skip-email

wp_test plugin activate woocommerce woocommerce-subscriptions "${PLUGIN_NAME:-$DDEV_PROJECT}"
