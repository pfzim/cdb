<?php
/*
	Enable-PSRemoting –Force
	Get-Item WSMan:\localhost\Client\TrustedHosts
	Set-Item WSMan:\localhost\Client\TrustedHosts -Force -Concatenate -Value 10.2.0.12
	Restart-Service -Force WinRM

	curl https://packages.microsoft.com/config/rhel/7/prod.repo | sudo tee /etc/yum.repos.d/microsoft.repo
	sudo yum install powershell
	sudo yum install gssntlmssp
*/

	$server = 'localhost';
	echo shell_exec('pwsh -command "&{ \\$ps_creds = New-Object System.Management.Automation.PSCredential (\''.PWSH_USER.'\', (ConvertTo-SecureString \''.PWSH_PASSWD.'\' -AsPlainText -Force)); Invoke-Command -ComputerName \''.$server.'\' -ScriptBlock { \\$ENV:COMPUTERNAME } -Credential \\$ps_creds -Authentication Negotiate }"');
