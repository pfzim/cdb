#!/bin/sh

php -f /var/www/html/cdb/cdb.php -- cron-weekly >> /var/log/cdb/cron-weekly.log
