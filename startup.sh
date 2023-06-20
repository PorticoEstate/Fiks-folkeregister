#!/bin/bash
/usr/local/bin/docker-php-entrypoint
cd /var/www/html
composer require firebase/php-jwt
composer require vlucas/phpdotenv
composer install
/usr/local/bin/apache2-foreground
