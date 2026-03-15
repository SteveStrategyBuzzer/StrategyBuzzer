#!/bin/bash
set -e

composer install --no-interaction --prefer-dist -q
php artisan migrate --force
