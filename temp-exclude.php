<?php
define('MAC_NOT_EXCLUDE_REGEX', '^(?!943fc2|001438|00fd45|1402ec|1c98ec|34fcb9|40b93c|4448c1|48df37|70106f|941882|9cdc71|a8bd27|c8b5ad|d89403|e0071b|e8f724|f40343|0001e6|0001e7|0002a5|004ea|000802|000883|0008c7|000a57|000bcd|000d9d|000e7f|000eb3|000f20|000f61|001083|0010e3|00110a|001185|001279|001321|0014c2|001560|001635|001708|0017a4|001871|0018fe|0019bb|001a4b|001b78|001cc4|001e0b|001f29|00215a|002264|00237d|002481|0025b3|002655|00306e|0030c1|00508b|0060b0|00805f|009c02|080009|082e5f|101f74|10604b|1458d0|18a905|1cc1de|24be05|288023|28924a|2c233a|2c27d7|2c4138|2c44fd|2c59e5|2c768a|308d99|30e171|3464a9|3863bb|38eaa7|3c4a92|3c5282|3ca82a|3cd92b|40a8f0|40b034|441ea1|443192|480fcf|5065f3|5820b1|5c8a38|5cb901|643150|645106|68b599|6c3be5|6cc217|705a0f|7446a0|784859|78acc0|78e3b5|78e7d1|80c16e|843497|8851fb|8cdcd4|9457a5|984be1|98e7f4|9c8e99|9cb654|a01d48|a02bb8|a0481c|a08cfd|a0b3cc|a0d3c1|a45d36|ac162d|b05ada|b499ba|b4b52f|b8af67|bceafa|c4346b|c8cbb8|c8d3ff|cc3e5f|d07e28|d0bf9c|d48564|d4c9ef|d89d67|d8d385|dc4a3e|e4115b|e83935|ec8eb5|ec9a74|ecb1d7|f0921c|f4ce46|fc15b4|fc3fdb|000e08|00eeab|007278|68cae4)');
define('MAC_EXCLUDE_VM', '^00155d|^9eafd8'); //все MAC для hyperv VM


define('MAC_EXCLUDE_ARRAY', array(
		array(
			'mac_regex'  => MAC_NOT_EXCLUDE_REGEX,
			'name_regex' => '^RU-\\d\\d-\\d{4}-\\w{3}',
			'port_regex' => '^(?:FastEthernet2)|(?:Vlan106)$'
		),
		array(
			'mac_regex'  => MAC_NOT_EXCLUDE_REGEX,
			'name_regex' => '^RU-\\d\\d-B[o\d]\\d-\\w{3}',
			'port_regex' => '^(?:FastEthernet1)|(?:Gi0/1/1)|(?:Gi0/1/2)$'
		),
		array(
			'mac_regex'  => NULL,
			'name_regex' => '^BRC-LAN-SWI-02-H5120$',
			'port_regex' => '^GigabitEthernet1/0/45$'
		),
		array(
			'mac_regex'  => MAC_EXCLUDE_VM,
			'name_regex' => NULL,
			'port_regex' => NULL
		),
		array(
			'mac_regex'  => NULL,
			'name_regex' => '^RU-66-RC2-SW3-2530X1$',
			'port_regex' => '^2$'
		),
		array(
			'mac_regex'  => NULL,
			'name_regex' => '^RU-66-RC2-SW6-2530X1$',
			'port_regex' => '^3|4$'
		),
		array(
			'mac_regex'  => NULL,
			'name_regex' => '^RU-24-RC4-01$',
			'port_regex' => '^Gi0/1/6$'
		),
		array(
			'mac_regex'  => NULL,
			'name_regex' => '^RU-77-RC6-01$',
			'port_regex' => NULL
		),
		array(
			'mac_regex'  => '^006037',
			'name_regex' => '^RU-\\d\\d-\\d{4}-\\w{3}',
			'port_regex' => NULL
		)
	)
);

function test($mac, $last_sw_name, $port)
{
$row = [4 => $port];

echo $mac.'    '.$last_sw_name.'    '.$port."\n";

foreach(MAC_EXCLUDE_ARRAY as &$excl)
{
	if(   (($excl['mac_regex'] === NULL) || preg_match('/'.$excl['mac_regex'].'/i', $mac))
	   && (($excl['name_regex'] === NULL) || preg_match('/'.$excl['name_regex'].'/i', $last_sw_name))
	   && (($excl['port_regex'] === NULL) || preg_match('#'.$excl['port_regex'].'#i', $row[4]))
	)
	{
		$excluded = 0x0002;
		echo '  MAC excluded: '.$mac."\n";
		break;
	}
}
}

test('00121767248c', 'RU-35-0098-VOL', 'FastEthernet2');
test('a8f94b5ea74a', 'RU-77-0103-DUB', 'Vlan106');
test('0012131433af', 'RU-13-0054-ELN', 'FastEthernet2');
test('bcee7be2e523', 'RU-77-RC6-01', 'FastEther7net0');
test('0012122353cb', 'RU-33-0026-KOV', 'FastEthernet2');
test('000b869b3a77', 'BRC-LAN-SWI-02-H5120', 'GigabitEthernet1/0/45');
test('888322c851ca', 'BRC-LAN-SWI-02-H5120', 'GigabitEthernet1/0/45');
test('bcee7be2e523', 'RU-13-0054-ELN', 'FastEthernet0');

test('48df372353cb', 'RU-33-0026-KOV', 'FastEthernet2');
test('0008029b3a77', 'BRC-LAN-SWI-02-H5120', 'GigabitEthernet1/0/45');
