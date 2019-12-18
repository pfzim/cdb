#!/bin/sh

pwsh -File /var/cdb/scripts/sync-vm.ps1
php -f /var/www/html/cdb/cdb_cli.php cron-weekly
