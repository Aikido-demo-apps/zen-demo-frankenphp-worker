#!/usr/bin/env bash

/usr/local/bin/frankenphp php-cli /var/www/html/artisan config:cache --no-ansi -q
/usr/local/bin/frankenphp php-cli /var/www/html/artisan route:cache --no-ansi -q
/usr/local/bin/frankenphp php-cli /var/www/html/artisan view:cache --no-ansi -q
