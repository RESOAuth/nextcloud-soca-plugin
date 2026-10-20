#!/bin/sh
set -eu
mkdir -p /var/www/html/custom_apps/soca
cp -a /opt/soca/. /var/www/html/custom_apps/soca/
chown -R www-data:www-data /var/www/html/custom_apps/soca
touch /tmp/soca-app-ready
