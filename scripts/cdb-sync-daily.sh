#!/bin/sh

php -f /var/www/html/cdb/cdb_cli.php sync-all
php -f /var/www/html/cdb/cdb_cli.php report-tmao-servers
php -f /var/www/html/cdb/cdb_cli.php check-tasks-status
php -f /var/www/html/cdb/cdb_cli.php create-tasks-tmao
php -f /var/www/html/cdb/cdb_cli.php create-tasks-tmee
php -f /var/www/html/cdb/cdb_cli.php create-tasks-laps
php -f /var/www/html/cdb/cdb_cli.php create-tasks-rename
php -f /var/www/html/cdb/cdb_cli.php report-tasks-status
php -f /var/www/html/cdb/cdb_cli.php report-incorrect-names
php -f /var/www/html/cdb/cdb_cli.php report-laps
