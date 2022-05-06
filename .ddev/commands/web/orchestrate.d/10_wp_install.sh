#!/bin/bash

if [ ! -z "${RECREATE_ENV}" ]; then
  echo "Deleting database before creating a new one"
  wp db clean --yes
fi

if [ "${WP_MULTISITE}" = "true" ]; then
  wp core multisite-install \
    --title="${WP_TITLE}" \
    --admin_user="${ADMIN_USER}" \
    --admin_password="${ADMIN_PASS}" \
    --url="${DDEV_PRIMARY_URL}" \
    --admin_email="${ADMIN_EMAIL}" \
    --skip-email

  cat << 'EOF' >> ".htaccess"
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

readarray -d , -t slugs <<< "${WP_MULTISITE_SLUGS},"; unset "slugs[-1]";
for slug in "${slugs[@]}"; do
  if [ ! -z "${slug}" ]; then
    wp site create --slug="${slug}"
  fi
done

else
  wp core install \
    --title="${WP_TITLE}" \
    --admin_user="${ADMIN_USER}" \
    --admin_password="${ADMIN_PASS}" \
    --url="${DDEV_PRIMARY_URL}" \
    --admin_email="${ADMIN_EMAIL}" \
    --skip-email
fi
