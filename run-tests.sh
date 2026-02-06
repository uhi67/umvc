#!/bin/bash
if [ ! -f tests/.env ]; then
  cp tests/.env-template tests/.env
fi
if [ ! -f tests/docker-compose.yml ]; then
  cp tests/docker-compose-template.yml tests/docker-compose.yml
fi
docker compose --file tests/docker-compose.yml up $1 --exit-code-from test-runner test-runner "$@"
#docker exec -it umvc-php-1 sh -c "php /app/vendor/bin/codecept run"

