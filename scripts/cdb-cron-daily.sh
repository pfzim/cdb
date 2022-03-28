#!/bin/sh

pwsh -File /var/cdb/scripts/sync-exch.ps1 >> /var/log/cdb/cron-daily-pwsh.log
php -f /var/www/html/cdb/cdb.php cron-daily >> /var/log/cdb/cron-daily.log
