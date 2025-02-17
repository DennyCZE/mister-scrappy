# Mister Scrappy
Small page scrapper/crawler using Laravel 11

### Docker preparation
- create configuration file `cp .env.example .env`
- run `docker compose build app`
- run `docker compose up -d`

### App install
Note: If you are using Docker use commands with prefix `docker compose exec app <command>`
- download dependencies `composer install` for development or `composer install --no-dev` for production
- generate encryption key `php artisan key:generate`
- create sqlite DB file `touch database/database.sqlite`
- migrate data to DB `php artisan migrate:fresh --seed`

### Usage
- configure `.env` with desired page, rules and timeout
  - example
    ```dotenv
    ...
    SCRAPPER_URL="https://google.com/"
    SCRAPPER_RULES='{"buttons":{"child_text":"Detail","parent":["<div class=\"container\">","</div>"],"wrapper":"div[contains(@class, \"col-6\")]","childWrapper":"strong","rule":{"method":"strpos","value":"Detail"}}}'
    SCRAPPER_WATCH_TIMEOUT="1800"
    ...
    ```
- configure `.env` for notifying trough discord webhook
  - example
    ```dotenv
    ...
    DISCORD_WEBHOOK="https://discord.com/api/webhooks/<id>/<hash>"
    ...
    ```
- scrapper can be tested with command `php artisan app:test`
- run command `php artisan app:watch-page` to watch changes on page
