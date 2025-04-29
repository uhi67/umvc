#!/bin/bash
if [ ! -f tests/.env ]; then
  cp tests/.env-template tests/.env
fi
if [ ! -f tests/docker-compose.yml ]; then
  cp tests/docker-compose-template.yml tests/docker-compose.yml
fi
docker compose --file tests/docker-compose.yml up --build -d $1
docker exec -it umvc-php-1 php /app/vendor/bin/codecept run
