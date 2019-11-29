#!/bin/sh

php -f /var/www/html/cdb/cdb_cli.php sync-3par
php -f /var/www/html/cdb/cdb_cli.php report-3par
pwsh -File /var/cdb/scripts/sync-vm.ps1
