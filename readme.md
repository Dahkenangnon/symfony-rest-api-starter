# API

## Installation

/usr/bin/php8.1 /usr/local/bin/composer install


## Migrations

/usr/bin/php8.1 bin/console doctrine:migrations:diff

/usr/bin/php8.1 bin/console doctrine:migrations:migrate

## Regenerate fosRestBundle jwt

/usr/bin/php8.1 bin/console lexik:jwt:generate-keypair

## Clear cache

/usr/bin/php8.1 bin/console cache:clear
