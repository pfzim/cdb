<?php
	// Report Backup communication channel (ДКС - Дополнительные Каналы Связи)

	/**
		\file
		\brief Формирование отчёта по установленным ДКС.
	*/


	if(!defined('Z_PROTECTED')) exit;

	echo "\nreport-itinvent-bcc:\n";

	$xtml = <<<'EOT'
<?xml version="1.0" encoding="UTF-8"?>
<zabbix_export>
    <version>5.4</version>
    <date>2021-11-24T08:28:39Z</date>
    <groups>
        <group>
            <uuid>f9feeb25a27a45d99c679edf77fe202b</uuid>
            <name>TT1</name>
        </group>
    </groups>
    <hosts>
EOT;

	$xmlhosts = '';

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
	SELECT 
		`id`,
		`name`,
		`mac`,
		`ip`,
		`inv_no`,
		DATE_FORMAT(`date`, '%d.%m.%Y %H:%i:%s') AS `last_update`
	FROM @mac
	WHERE `loc_no` IN 
		(SELECT DISTINCT `loc_no`
		FROM @mac
		WHERE (`flags` & 0x0400) > 0 AND (`flags` & 0x0040) > 0 AND `loc_no` <> 0)
	AND PORT LIKE 'self'
	")))
	{

		foreach($result as &$row)
		{
			$xmlhosts .= '<host><host>'.$row['ip'].'</host><name>'.$row['ip'].'</name>';
            $xmlhosts .= <<<'EOT'
			<proxy>
                <name>Zabbix proxy test</name>
            </proxy>
            <templates>
                <template>
                    <name>Cisco 881 test</name>
                </template>
            </templates>
            <groups>
                <group>
                    <name>TT1</name>
                </group>
            </groups>
            <interfaces>
                <interface>
                    <type>SNMP</type>
EOT;
            $xmlhosts .= '<ip>'.$row['ip'].'</ip>';
            $xmlhosts .= <<<'EOT'
                    <port>161</port>
                    <details>
                        <version>SNMPV3</version>
                        <securityname>BR_USR2</securityname>
                        <securitylevel>AUTHPRIV</securitylevel>
                        <authpassphrase>8DRHn_NDVz#Ry7-t</authpassphrase>
                        <privpassphrase>NbQ&gt;-zD)vw]c3Wsb</privpassphrase>
                    </details>
                    <interface_ref>if1</interface_ref>
                </interface>
            </interfaces>
            <inventory_mode>DISABLED</inventory_mode>
        </host>
EOT;

			echo 'Added'.$row['id'].': '.$row['ip']."\r\n";
			$i++;
		}
	}

	$xml .= $xmlhosts;
	$xml .= '</hosts></zabbix_export>';

	echo 'Total BCC: '.$i."\r\n";
	
	try {
		$dom = new DOMDocument; $dom->preserveWhiteSpace = TRUE;
		$dom->loadXML($xml);
		$strxml = $dom->saveXML();
		$handle = fopen("./out/bbc.xml", "c");
		fwrite($handle, $strxml);
		fclose($handle);
	} catch (Exception $e) {
		echo 'XML updated: FAILED';
		echo 'Caught exception: ',  $e->getMessage(), "\n";
	} finally {
		echo 'XML updated: OK';
	}
