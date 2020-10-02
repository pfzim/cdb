# Collect info about mailboxes
<#
	Скрипт загружает информация по квотам установленным на почтовые ящики.
	Из-за ограниченности удаленной PowerShell загружается только пометка: Безлимитная квота или нет.
	Размер квоты и почтового ящика не загружается.
#>

$ErrorActionPreference = 'Stop'

$scriptPath = $PSScriptRoot
if(!$scriptPath)
{
	if($MyInvocation.MyCommand.Path)
	{
		$scriptPath = Split-Path -parent $MyInvocation.MyCommand.Path
	}
	else
	{
		$scriptPath = $PSCommandPath
	}
}

. ($scriptPath + '\inc.config.ps1')

$ps_creds = New-Object System.Management.Automation.PSCredential ($g_config.ps_login, (ConvertTo-SecureString $g_config.ps_passwd -AsPlainText -Force))

Write-Host -ForegroundColor Green ('Connecting to {0}...' -f $g_config.vmm_server)
Invoke-Command -ComputerName $g_config.vmm_server -Credential $ps_creds -Authentication Negotiate -ArgumentList @($g_config) -ScriptBlock {
	param($g_config)

	function ExecuteNonQueryFailover($Query)
	{
		$retry = 5
		while($retry)
		{
			try
			{
				$Query.ExecuteNonQuery() | Out-Null
				$retry = 0
			}
			catch
			{
				$retry--
				if(!$retry)
				{
					Write-Host -ForegroundColor Red ('ERROR: {0}' -f $_.Exception.Message)
				}
			}
		}
	}

	$exch_creds = New-Object System.Management.Automation.PSCredential ($g_config.exch_login, (ConvertTo-SecureString $g_config.exch_passwd -AsPlainText -Force))
	Write-Host -ForegroundColor Green ('Connecting to {0}...' -f $g_config.exch_conn_uri)

	$session = New-PSSession -ConfigurationName Microsoft.Exchange -ConnectionUri $g_config.exch_conn_uri -Credential $exch_creds -Authentication Basic
	Import-PSSession $session

	Write-Host -ForegroundColor Green ('Connecting to DB {0}...' -f $g_config.db_host)
	$db = New-Object System.Data.Odbc.OdbcConnection
	$db.ConnectionString = 'DRIVER={{MariaDB ODBC 3.1 Driver}};SERVER={0};DATABASE={1};UID={2};PWD={3};OPTION=4194304' -f $g_config.db_host, $g_config.db_name, $g_config.db_user, $g_config.db_passwd
	$db.Open()
	$query = New-Object System.Data.Odbc.OdbcCommand('', $db)

	Write-Host -ForegroundColor Green ('Query mailboxes info...')
	#Get-Mailbox -ResultSize Unlimited | ? {$_.ProhibitSendQuota -eq 'Unlimited' -or $_.ProhibitSendReceiveQuota -eq 'Unlimited'} | %{ 
	Get-Mailbox -ResultSize Unlimited | %{
		try
		{
			$mbx_s = Get-MailboxStatistics -Identity $_.DistinguishedName
			$query.CommandText = 'SELECT `id` FROM c_persons WHERE `login` = ''{0}'' LIMIT 1' -f $_.SamAccountName
			$result = $query.ExecuteReader()
			$result.Read() | Out-Null
			$id = [int] $result.GetString(0)
			$result.Close() | Out-Null
			
			if($id)
			{
				$value = 0
				if($_.ProhibitSendReceiveQuota -ne 'Unlimited')
				{
					$value = 1 # $_.ProhibitSendReceiveQuota.Value.ToBytes()
				}
				
				$query.CommandText = 'INSERT INTO c_properties_int (`tid`, `pid`, `oid`, `value`) VALUES (2, {0}, 105, {1}) ON DUPLICATE KEY UPDATE `value` = {1}' -f $id, $value
				#Write-Host -ForegroundColor Yellow $query.CommandText
				ExecuteNonQueryFailover -Query $query
			}

			#Write-Host -ForegroundColor Green ('{0,45} {1,45} {2,10} {3,10} {4,20}' -f $_.PrimarySmtpAddress, $_.Name, $_.ProhibitSendReceiveQuota, $_.ProhibitSendQuota, $mbx_s.TotalItemSize)
			#Write-Host -ForegroundColor Green ('{0,45} {1,45} {2,10} {3,10} {4,20}' -f $_.PrimarySmtpAddress, $_.Name, $_.ProhibitSendReceiveQuota, $_.ProhibitSendQuota, $mbx_s.TotalItemSize.Value.ToBytes())
		}
		catch
		{
			Write-Host -ForegroundColor Red ('ERROR: {0}' -f $_.Exception.Message)
		}
	}

	$db.Close()
	
	Remove-PSSession -Session $session
}

Write-Host -ForegroundColor Green ('DONE')
