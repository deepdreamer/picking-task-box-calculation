### init
- `cp .env.example .env`
- `docker-compose up -d`
- `docker-compose run shipmonk-packing-app bash`
- `composer install && bin/doctrine orm:schema-tool:create && bin/doctrine dbal:run-sql "$(cat data/packaging-data.sql)"`
- `php bin/setup-test-db.php`

### run
- `php run.php "$(cat sample.json)"`

### environment variables
Env loading follows a Symfony-like pattern:
- `.env` contains shared/default values.
- `bootstrap.php` loads `.env` first, then loads `.env.{APP_ENV}`.
- Included environment files:
  - `.env.prod`
  - `.env.dev`
  - `.env.test`
- Use these examples:
  - `.env.example` (shared/default values)
  - `.env.prod.example` / `.env.dev.example` / `.env.test.example` (environment overrides)

Application:
- `APP_ENV` (default: `prod`)
  - Runtime environment selector used to load `.env.{APP_ENV}`.
  - In this project, supported files are `prod`, `dev`, and `test`.

Database:
- `DB_DRIVER` (default: `pdo_mysql`)
  - Doctrine DBAL driver (`pdo_mysql` or `mysqli`).
- `DB_HOST` (default: `shipmonk-packing-mysql`)
  - Database host.
- `DB_PORT` (default: `3306`)
  - Database port.
- `DB_NAME` (default: `packing`)
  - Database name.
- `DB_USER` (default: `root`)
  - Database user.
- `DB_PASSWORD` (default: `secret`)
  - Database password.
- `DB_CHARSET` (default: `utf8mb4`)
  - Database connection charset.
- `DB_NAME` (overridden per environment)
  - `.env.prod`: `packing`
  - `.env.dev`: `packing`
  - `.env.test`: `packing_test`

Packing API:
- `API_URL` (default: `https://global-api.3dbinpacking.com`)
  - Base URL for the third-party packing API.
- `API_USERNAME` (required for API usage)
  - API username sent in packing requests.
- `API_KEY` (required for API usage)
  - API key sent in packing requests.
  - Usually defined in environment-specific files.

HTTP retry behavior for third-party API calls:
- `API_RETRY_MAX_ATTEMPTS` (default: `3`)
  - Maximum retry attempts.
- `API_RETRY_BASE_DELAY_MS` (default: `200`)
  - Base exponential backoff delay in milliseconds.
- `API_RETRY_MAX_DELAY_MS` (default: `2000`)
  - Maximum capped delay in milliseconds.

Docker user mapping (docker-compose runtime):
- `UID` (default in compose fallback: `1000`)
  - Host user id used for container process user mapping.
- `GID` (default in compose fallback: `1000`)
  - Host group id used for container process user mapping.

### adminer
- Open `http://localhost:8080/?server=mysql&username=root&db=packing`
- Password: secret

### example how to run test
#### all linters + full phpunit suite
- `make test`

#### specific method
- `docker compose run --rm shipmonk-packing-app ./vendor/bin/phpunit tests/Unit/Services/InputValidatorTest.php --filter testGetProductsThrowsWhenInputIsNotJsonArray`
