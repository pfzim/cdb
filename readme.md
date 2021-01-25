# CDB is a database for collect information about different computers and devices

CBD собирает информацию из различных источников о различных объектах (компьютеры, учётные записи,
программное обеспечение и т.п.), сверяет собранные сведения на соответствие установленым требования
и при выявления не соответствия создаёт заявки в системе HelpDesk для устаранения выявленных
несоответствий.

Описание битовых флагов `flags` в таблице `computers` (? and `persons`)

| Bits   | Description                               |
|--------|-------------------------------------------|
| 0x0001 | Disabled in AD                            |
| 0x0002 | Deleted                                   |
| 0x0004 | Manual hide from report                   |
| 0x0008 | Temporary flag for delete after all syncs |
| 0x00F0 | *Mask for next 4 bits (Exist)*            |
| 0x0010 | Exist in AD                               |
| 0x0020 | Exist in TMAO                             |
| 0x0040 | Exist in TMEE                             |
| 0x0080 | Exist in SCCM                             |

Описание битовых флагов `flags` в таблице `tasks`

| Bits     | Description                               |
|----------|-------------------------------------------|
| 0x000001 | Task was closed                           |
| 0x000002 |                                           |
| 0x000004 |                                           |
| 0x000008 | Mbox unlim Task was created in HelpDesk   |
| 0x000010 | IT Invent Move was created in HelpDesk    |
| 0x000020 | IT Invent TaskFix was created in HelpDesk |
| 0x000040 | Task Windows Updates not installed        |
| 0x000080 | Application Control Task was created      |
| 0x000100 | TMEE Task was created in HelpDesk         |
| 0x000200 | TMAO Task was created in HelpDesk         |
| 0x000400 | Rename Task was created in HelpDesk       |
| 0x000800 | LAPS Task was created in HelpDesk         |
| 0x001000 | SCCM Task was created in HelpDesk         |
| 0x002000 | PASSWD Task was created in HelpDesk       |
| 0x004000 | OS Task was created in HelpDesk           |
| 0x008000 | IT Invent Task was created in HelpDesk    |
| 0x010000 | Vulnerabilities                           |
| 0x020000 | Vulnerabilities (mass problem)            |
| 0x040000 | Net errors                                |
| 0x080000 | IT Invent software                        |
| Переделать на:                                       |
| 0x00FF00 | Mask tasks codes                          |
| 0x000100 | Mbox unlim Task was created in HelpDesk   |
| 0x000200 | IT Invent Move was created in HelpDesk    |
| 0x000300 | IT Invent TaskFix was created in HelpDesk |
| 0x000400 | Task Windows Updates not installed        |
| 0x000500 | Application Control Task was created      |
| 0x000600 | TMEE Task was created in HelpDesk         |
| 0x000700 | TMAO Task was created in HelpDesk         |
| 0x000800 | Rename Task was created in HelpDesk       |
| 0x000900 | LAPS Task was created in HelpDesk         |
| 0x000A00 | SCCM Task was created in HelpDesk         |
| 0x000B00 | PASSWD Task was created in HelpDesk       |
| 0x000C00 | OS Task was created in HelpDesk           |
| 0x000D00 | IT Invent Task was created in HelpDesk    |
| 0x000E00 | Vulnerabilities                           |
| 0x000F00 | Vulnerabilities (mass problem)            |
| 0x001000 | Net errors                                |
| 0x001100 | IT Invent software                        |


UPDATE c_tasks SET `flags` = ((`flags` & ~0x000008) | 0x01000000) WHERE `flags` & 0x000008;
UPDATE c_tasks SET `flags` = ((`flags` & ~0x000010) | 0x02000000) WHERE `flags` & 0x000010;
UPDATE c_tasks SET `flags` = ((`flags` & ~0x000020) | 0x03000000) WHERE `flags` & 0x000020;
UPDATE c_tasks SET `flags` = ((`flags` & ~0x000040) | 0x04000000) WHERE `flags` & 0x000040;
UPDATE c_tasks SET `flags` = ((`flags` & ~0x000080) | 0x05000000) WHERE `flags` & 0x000080;
UPDATE c_tasks SET `flags` = ((`flags` & ~0x000100) | 0x06000000) WHERE `flags` & 0x000100;
UPDATE c_tasks SET `flags` = ((`flags` & ~0x000200) | 0x07000000) WHERE `flags` & 0x000200;
UPDATE c_tasks SET `flags` = ((`flags` & ~0x000400) | 0x08000000) WHERE `flags` & 0x000400;
UPDATE c_tasks SET `flags` = ((`flags` & ~0x000800) | 0x09000000) WHERE `flags` & 0x000800;
UPDATE c_tasks SET `flags` = ((`flags` & ~0x001000) | 0x0A000000) WHERE `flags` & 0x001000;
UPDATE c_tasks SET `flags` = ((`flags` & ~0x002000) | 0x0B000000) WHERE `flags` & 0x002000;
UPDATE c_tasks SET `flags` = ((`flags` & ~0x004000) | 0x0C000000) WHERE `flags` & 0x004000;
UPDATE c_tasks SET `flags` = ((`flags` & ~0x008000) | 0x0D000000) WHERE `flags` & 0x008000;
UPDATE c_tasks SET `flags` = ((`flags` & ~0x010000) | 0x0E000000) WHERE `flags` & 0x010000;
UPDATE c_tasks SET `flags` = ((`flags` & ~0x020000) | 0x0F000000) WHERE `flags` & 0x020000;
UPDATE c_tasks SET `flags` = ((`flags` & ~0x040000) | 0x10000000) WHERE `flags` & 0x040000;
UPDATE c_tasks SET `flags` = ((`flags` & ~0x080000) | 0x11000000) WHERE `flags` & 0x080000;

UPDATE c_tasks SET `flags` = ((`flags` & ~0xFF000000) | ((`flags` & 0xFF000000) >> 16 )) WHERE `flags` & 0xFF000000;

Описание битовых флагов `flags` в таблице `ac_log`

| Bits   | Description                               |
|--------|-------------------------------------------|
| 0x0001 |                                           |
| 0x0002 | Problem fixed                             |
| 0x0004 |                                           |
| 0x0008 |                                           |

Описание битовых флагов `flags` в таблице `mac`

| Bits   | Description                                    |
|--------|------------------------------------------------|
| 0x0001 |                                                |
| 0x0002 | Temporary excluded                             |
| 0x0004 | Permanently excluded (Manual exclude)          |
| 0x0008 |                                                |
| 0x0010 | Exist in IT Invent                             |
| 0x0020 | Imported from netdev                           |
| 0x0040 | Active in IT Invent                            |
| 0x0080 | `mac` field is serial number                   |
| 0x0100 | This is mobile device (do not check location)  |

SQL example:
```
SELECT m.`id`, m.`mac`, m.`ip`, m.`name`, m.`inv_no`, d.`name` AS `netdev`, m.`port`, m.`first`, m.`date`,
	CONCAT(
		IF(m.`flags` & 0x0002, 'Temp excl;', ''),
		IF(m.`flags` & 0x0004, 'Perm excl;', ''),
		IF(m.`flags` & 0x0010, 'ITInv;', ''),
		IF(m.`flags` & 0x0020, 'FromNet;', ''),
		IF(m.`flags` & 0x0040, 'ActiveInv;', ''),
		IF(m.`flags` & 0x0080, 'SN;', ''),
		IF(m.`flags` & 0x0100, 'Mobile;', '')
	) AS `flags_to_string`
FROM c_mac AS m
LEFT JOIN c_devices AS d ON d.`id` = m.`pid` AND d.`type` = 3
```

Возможные значения колонки `type` в таблице `devices`

| Value  | Description                               |
|--------|-------------------------------------------|
| 1      | 3PAR storage                              |
| 2      | Hyper-V cluster                           |
| 3      | netdev                                    |
| 4      | Hostname from `vuln_scans` table          |

Описание битовых флагов `flags` в таблице `vuln_scans`

| Bits   | Description                               |
|--------|-------------------------------------------|
| 0x0001 |                                           |
| 0x0002 | Problem fixed                             |
| 0x0004 | Manual hide                               |
| 0x0008 |                                           |

Описание битовых флагов `flags` в таблице `vulnerabilities`

| Bits   | Description                               |
|--------|-------------------------------------------|
| 0x0001 |                                           |
| 0x0002 |                                           |
| 0x0004 | Manual hide                               |
| 0x0008 |                                           |

Описание битовых флагов `flags` в таблице `devices`

| Bits   | Description                               |
|--------|-------------------------------------------|
| 0x0001 |                                           |
| 0x0002 |                                           |
| 0x0004 | Manual hide                               |
| 0x0008 |                                           |

Описание битовых флагов `flags` в таблице `files`

| Bits   | Description                               |
|--------|-------------------------------------------|
| 0x0001 |                                           |
| 0x0002 |                                           |
| 0x0004 |                                           |
| 0x0008 |                                           |
| 0x0010 | Allowed (Exist in IT Invent)              |

Возможные значения колонки `oid` в таблицах `properties_*`
| Value  | PHP constant                              | Description                               |
|--------|-------------------------------------------|-------------------------------------------|
| 101    | CDB_PROP_USERACCOUNTCONTROL               | AD UserAccountControl                     |
| 102    | CDB_PROP_OPERATINGSYSTEM                  | Operation System name                     |
| 103    | CDB_PROP_OPERATINGSYSTEMVERSION           | Operation System version                  |
| 104    | CDB_PROP_BASELINE_COMPLIANCE_HOTFIX       | SCCM Baseline HotFix Compliance Status    |
| 105    | CDB_PROP_MAILBOX_QUOTA                    | Mailbox quota (Unlimited = 0)             |

Возможные значения колонки `tid` в таблицах `tasks` и `properties_*`
| Value  | Description                               |
|--------|-------------------------------------------|
| 1      | When `pid` from `computers` table         |
| 2      | When `pid` from `persons` table           |
| 3      | When `pid` from `mac` table               |
| 4      | When `pid` from `ac_log` table            |
| 5      | When `pid` from `vuln_scans` table        |
| 6      | When `pid` from `vulnerabilities` table   |
| 7      | When `pid` from `devices` table           |
| 8      | When `pid` from `files` table             |


Change flag 0x40 to 0x0400:
  ``UPDATE c_computers SET `flags` = ((`flags` & ~0x40) | 0x0400) WHERE `flags` & 0x40;``

Change flag 0x1000 to 0x0400:
  ``UPDATE c_tasks SET `flags` = ((`flags` & ~0x1000) | 0x0400) WHERE `flags` & 0x1000;``
