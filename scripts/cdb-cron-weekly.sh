#!/bin/sh

pwsh -File /var/cdb/scripts/sync-vm.ps1 >> /var/log/cdb/cron-weekly-pwsh.log
php -f /var/www/html/cdb/cdb.php cron-weekly >> /var/log/cdb/cron-weekly.log
