#!/bin/sh

echo "initializing the container"
echo "=========================="

set -o

APPDIR=/app/tests/_data/testapp
RUNTIMEDIR=/app/tests/_output

cd /app

# Check config file
if [ ! -f "/app/tests/_data/test-config.php" ]; then
  cp /app/tests/_data/test-config-template.php /app/tests/_data/test-config.php
  echo "'test-config.php' is created from template"
fi

NODEV=""
if [ "APPLICATION_ENV" = "production" ]; then
    NODEV="--no-dev"
fi

composer update -n $NODEV || true
# check wrong module path (simplesamlphp modules may be installed here first time)
if [ -d "/app/modules" ]; then
    composer install -n $NODEV || true
    echo "Deleting wrong modules path ..."
    rm -rf /app/modules
fi

cd $APPDIR

echo "Waiting for database container to be ready..."
sleep 5
#php app migrate/wait || echo "Timeout connecting to database." # TODO

echo "Migrating database"
php app migrate confirm=yes

if [ ! -d "$RUNTIMEDIR/logs" ]; then
  mkdir $RUNTIMEDIR/logs -m 0774
  chgrp www-data $RUNTIMEDIR/logs
fi

git describe --tags --abbrev=1 > /app/version

echo "Starting apache"
echo "---------------"

# enable ssl module and enable the ssl site
a2enmod ssl
if [ "$HTTP_PORT" != '' ]; then
  a2ensite app-http
fi
if [ "$HTTPS_PORT" != '' ]; then
  a2ensite app-https
fi
a2enmod rewrite
