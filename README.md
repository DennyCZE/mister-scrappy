# Mister Scrappy

### Docker install
- create configuration file `cp .env.example .env`
  - in new `.env` file configure forward port and URI
  - for example
    ```
        ...
        APP_URL=http://localhost:8755
        ...
        FORWARD_NGINX_PORT=8755
    ```
- run `docker compose build app`
- run `docker compose up -d`

### Laravel install
Note: If you are using Docker use commands with prefix `docker compose exec app <command>`
- download dependencies `composer install`
- generate encryption key `php artisan key:generate`
- create sqlite DB file `touch database/database.sqlite`
- migrate data to DB `php artisan migrate:fresh --seed`

### Usage
