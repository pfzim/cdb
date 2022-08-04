# Collect info about VM

$ErrorActionPreference = "Stop"

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

#Enter-PSSession -ComputerName $$g_config.vmm_server -Credential $ps_creds -Authentication Negotiate
Invoke-Command -ComputerName $g_config.vmm_server -Credential $ps_creds -Authentication Negotiate -ArgumentList @($g_config) -ScriptBlock {
	param($g_config)
	$ps_creds = New-Object System.Management.Automation.PSCredential ($g_config.ps_login, (ConvertTo-SecureString $g_config.ps_passwd -AsPlainText -Force))
	Invoke-Command -ComputerName 'localhost' -Credential $ps_creds -Authentication Credssp -ArgumentList @($g_config) -ScriptBlock {
		param($g_config)
		$ErrorActionPreference = "Stop"

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

		Import-Module -Name FailoverClusters
		Import-Module -Name Hyper-V

		$db = New-Object System.Data.Odbc.OdbcConnection
		$db.ConnectionString = 'DRIVER={{MariaDB ODBC 3.1 Driver}};SERVER={0};DATABASE={1};UID={2};PWD={3};OPTION=4194304' -f $g_config.db_host, $g_config.db_name, $g_config.db_user, $g_config.db_passwd
		$db.Open()
		$query = New-Object System.Data.Odbc.OdbcCommand('', $db)

		<#
		$result = $query.ExecuteReader()

		while($result.Read())
		{
			$id = $result.GetString(0)
			$cluster = $result.GetString(1)
		#>

		$query.CommandText = 'UPDATE c_vm SET `flags` = (`flags` & ~0x0010) WHERE `flags` & 0x0010'
		ExecuteNonQueryFailover -Query $query

		$query.CommandText = 'SELECT m.`id`, m.`address`, m.`name` FROM c_devices AS m WHERE m.`type` = 2'
		$dataTable = New-Object System.Data.DataTable
		(New-Object system.Data.odbc.odbcDataAdapter($query)).fill($dataTable) | Out-Null
		foreach($row in $dataTable.Rows)
		{
			$id = $row.id
			$cluster = $row.address

			try
			{
				$nodes = Get-ClusterNode -Cluster $cluster
			}
			catch
			{
				Write-Host -ForegroundColor Red ('ERROR: {1}: {0}' -f $_.Exception.Message, $row.name)
				continue
			}
			
			foreach($node in $nodes)
			{
				try
				{
					#$vms = Get-VM -ComputerName $node.Name
					$vms = Invoke-Command -ComputerName $node.Name -ScriptBlock { Get-VM }
				}
				catch
				{
					Write-Host -ForegroundColor Red ('ERROR: {1}: {0}' -f $_.Exception.Message, $node.Name)
					continue
				}
				foreach($vm in $vms)
				{
					$hdd = 0
					try
					{
						#$vhds = Get-VHD -ComputerName $vm.ComputerName -VmId $vm.VmId
						$vhds = Invoke-Command -ComputerName $node.Name -ArgumentList @($vm.VmId) -ScriptBlock { Get-VHD -VmId $args[0] }
					}
					catch
					{
						Write-Host -ForegroundColor Red ('ERROR: {1}: {0}' -f $_.Exception.Message, $node.Name)
						continue
					}
					foreach($vhd in $vhds)
					{
						$hdd += $vhd.FileSize
					}

					Write-Host -ForegroundColor Green ('{0,-20} {1,9} {2,10} {3,10}' -f $vm.VMName, $vm.ProcessorCount, $($vm.MemoryAssigned / 1gb -as [int]), ($hdd / 1gb -as [int]))

					$query.CommandText = 'INSERT INTO c_vm_history (`pid`, `date`, `name`, `cpu`, `ram_size`, `hdd_size`) VALUES ({0}, NOW(), "{1}", {2}, {3}, {4})' -f $id, $vm.VMName, $vm.ProcessorCount, $($vm.MemoryAssigned / 1gb -as [int]), ($hdd / 1gb -as [int])
					ExecuteNonQueryFailover -Query $query
					
					$query.CommandText = 'INSERT INTO c_vm (`name`, `cpu`, `ram_size`, `hdd_size`, `flags`) VALUES ("{0}", {1}, {2}, {3}, 0x0010) ON DUPLICATE KEY UPDATE `cpu` = {1}, `ram_size` = {2}, `hdd_size` = {3}, `flags` = (`flags` | 0x0010)' -f $vm.VMName.ToUpper(), $vm.ProcessorCount, $($vm.MemoryAssigned / 1gb -as [int]), ($hdd / 1gb -as [int])
					ExecuteNonQueryFailover -Query $query
				}
			}
		}
		$db.Close()
	}
}
#Exit-PSSession
