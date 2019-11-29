#!/bin/sh

php -f /var/www/html/cdb/sync-3par.php
php -f /var/www/html/cdb/report-3par.php
pwsh -File /var/cdb/scripts/sync-vm.ps1
