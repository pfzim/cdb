<?php

$server = 'localhost';
echo shell_exec('pwsh -command "&{ \\$ps_creds = New-Object System.Management.Automation.PSCredential (\''.PWSH_USER.'\', (ConvertTo-SecureString \''.PWSH_PASSWD.'\' -AsPlainText -Force)); Invoke-Command -ComputerName \''.$server.'\' -ScriptBlock { \\$ENV:COMPUTERNAME } -Credential \\$ps_creds -Authentication Negotiate }"');
