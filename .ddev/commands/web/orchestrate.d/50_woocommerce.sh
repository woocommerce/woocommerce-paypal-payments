#!/bin/bash

flags=""
if [ "${WP_MULTISITE}" = "true" ]; then
  flags+=" --network"
fi

wp plugin install woocommerce --version="${WC_VERSION}"
wp plugin activate woocommerce $flags
