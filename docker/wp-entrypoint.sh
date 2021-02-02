#!/bin/bash
set -e

if ! [ $(id -u) = 0 ]; then
  sudo chown -R $(id -u):$(id -g) ./
fi;

if wait-for-it.sh "${WORDPRESS_DB_HOST}" -t 60; then
  docker-entrypoint.sh apache2 -v

  wp core multisite-install \
    --allow-root \
    --title="${WP_TITLE}" \
    --admin_user="${ADMIN_USER}" \
    --admin_password="${ADMIN_PASS}" \
    --url="${WP_DOMAIN}" \
    --admin_email="${ADMIN_EMAIL}" \
    --skip-email

  cat << 'EOF' > "${DOCROOT_PATH}/.htaccess"
RewriteEngine On
RewriteBase /
RewriteRule ^index\.php$ - [L]

RewriteRule ^([_0-9a-zA-Z-]+/)?wp-admin$ $1wp-admin/ [R=301,L]

RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]
RewriteRule ^([_0-9a-zA-Z-]+/)?(wp-(content|admin|includes).*) $2 [L]
RewriteRule ^([_0-9a-zA-Z-]+/)?(.*\.php)$ $2 [L]
RewriteRule . index.php [L]
EOF

  wp site create \
    --allow-root \
    --slug="de" \
    || true # allow failure if already exists

  wp plugin is-installed akismet --allow-root && wp plugin uninstall akismet --allow-root --path="${DOCROOT_PATH}"
  wp plugin is-installed hello --allow-root && wp plugin uninstall hello --allow-root --path="${DOCROOT_PATH}"
  wp plugin activate "${PLUGIN_NAME}" --network --allow-root --path="${DOCROOT_PATH}"
  # Must be activate after main plugin, because relies on main plugin's autoloader
  wp plugin activate multilingualpress --network --allow-root --path="${DOCROOT_PATH}"

  # Custom setup instructions
fi

exec "$@"
