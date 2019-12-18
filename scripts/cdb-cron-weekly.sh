#!/bin/sh

php -f /var/www/html/cdb/cdb_cli.php cron-weekly
pwsh -File /var/cdb/scripts/sync-vm.ps1
