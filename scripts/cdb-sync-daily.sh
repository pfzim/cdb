#!/bin/sh

php -f /var/www/html/cdb/mark-before-sync.php
php -f /var/www/html/cdb/sync-ad.php
php -f /var/www/html/cdb/sync-tmao.php
php -f /var/www/html/cdb/sync-tmee.php
php -f /var/www/html/cdb/mark-after-sync.php
php -f /var/www/html/cdb/report-tmao-servers.php
php -f /var/www/html/cdb/check-tasks-status.php
php -f /var/www/html/cdb/create-tasks-tmao.php
php -f /var/www/html/cdb/create-tasks-tmee.php
php -f /var/www/html/cdb/report-tasks-status.php
