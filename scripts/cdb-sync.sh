#!/bin/sh

php -f /var/www/html/cdb/sync-ad.php
php -f /var/www/html/cdb/sync-tmao.php
php -f /var/www/html/cdb/sync-tmee.php
php -f /var/www/html/cdb/create-tasks-tmao.php
php -f /var/www/html/cdb/check-tasks-status.php
php -f /var/www/html/cdb/report-tasks-status.php