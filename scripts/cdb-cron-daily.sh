#!/bin/sh

pwsh -File /var/cdb/scripts/sync-exch.ps1
php -f /var/www/html/cdb/cdb.php cron-daily