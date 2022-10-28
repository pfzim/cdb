<?php
	// Retrieve information from PaloAlto

	/**
		\file
		\brief Получение информации из PaloAlto
	*/

	if(!defined('Z_PROTECTED')) exit;
	
	echo "\nsync-paloalto:\n";

	$ch = curl_init(PALOALTO_URL.'/Policies/SecurityRules?location=vsys&vsys=vsys1');

	curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-PAN-KEY: '.PALOALTO_API_KEY));
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$result = curl_exec($ch);
	curl_close($ch);

	if($result !== FALSE)
	{
		$result_json = @json_decode($result, true);
		
		if(!isset($result_json['@status'])
			|| ($result_json['@status'] !== 'success')
			|| !isset($result_json['result']['entry'])
		)
		{
			echo 'ERROR: Invalid answer from server!'.PHP_EOL;
			return;
		}

		$db->put(rpv("UPDATE @ad_groups SET `flags` = ((`flags` & ~{%AGF_EXIST_PALOALTO})) WHERE (`flags` & {%AGF_EXIST_PALOALTO}) = {%AGF_EXIST_PALOALTO}"));

		$i = 0;
		foreach($result_json['result']['entry'] as &$rule)
		{
			if(($rule['action'] === 'allow')
				//&& isset($rule['disabled'])
				//&& ($rule['disabled'] === 'no')
				&& isset($rule['source-user']['member'])
			)
			{
				foreach($rule['source-user']['member'] as &$member)
				{
					if(preg_match('/^cn=([^,]+),ou=vpn,ou=service accounts,dc=bristolcapital,dc=ru$/i', $member, $matches))
					{
						echo 'Founded group: '.$matches[1].PHP_EOL;

						$db->put(rpv("
								INSERT INTO @ad_groups (`name`, `flags`)
								VALUES ({s0}, {%AGF_EXIST_PALOALTO})
								ON DUPLICATE KEY UPDATE `flags` = (`flags` | {%AGF_EXIST_PALOALTO})
							",
							strtolower($matches[1])
						));

						$i++;
					}
				}
			}
					
		}
	}
	else
	{
		echo 'ERROR'."\r\n\r\n"	;
	}

	echo 'Count: '.$i."\n";
