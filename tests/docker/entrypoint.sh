#!/bin/sh

echo "initializing the container"
echo "=========================="

APPDIR=/app/tests/_data/testapp
RUNTIMEDIR=/app/tests/_output

cd /app || exit

# Check config file
if [ ! -f "/app/tests/_data/test-config.php" ]; then
  cp /app/tests/_data/test-config-template.php /app/tests/_data/test-config.php
  echo "'test-config.php' is created from template"
fi

cd $APPDIR || exit

echo "Waiting for database container to be ready..."
php app migrate/wait || echo "Timeout connecting to database."

echo "Migrating database"
php app migrate confirm=yes

if [ ! -d "$RUNTIMEDIR/logs" ]; then
  mkdir $RUNTIMEDIR/logs -m 0774
  chgrp www-data $RUNTIMEDIR/logs
fi

git describe --tags --abbrev=1 > /app/version

cd /app || exit
php vendor/bin/codecept run unit
