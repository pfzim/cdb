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

| Bits   | Description                               |
|--------|-------------------------------------------|
| 0x0001 | Task was closed                           |
| 0x0002 |                                           |
| 0x0004 |                                           |
| 0x0008 |                                           |
| 0x0010 |                                           |
| 0x0020 |                                           |
| 0x0040 | Task Windows Updates not installed        |
| 0x0080 | Application Control Task was created      |
| 0x0100 | TMEE Task was created in HelpDesk         |
| 0x0200 | TMAO Task was created in HelpDesk         |
| 0x0400 | Rename Task was created in HelpDesk       |
| 0x0800 | LAPS Task was created in HelpDesk         |
| 0x1000 | SCCM Task was created in HelpDesk         |
| 0x2000 | PASSWD Task was created in HelpDesk       |
| 0x4000 | OS Task was created in HelpDesk           |
| 0x8000 | IT Invent Task was created in HelpDesk    |

Описание битовых флагов `flags` в таблице `ac_log`

| Bits   | Description                               |
|--------|-------------------------------------------|
| 0x0001 |                                           |
| 0x0002 | Problem fixed                             |
| 0x0004 |                                           |
| 0x0008 |                                           |

Описание битовых флагов `flags` в таблице `mac`

| Bits   | Description                               |
|--------|-------------------------------------------|
| 0x0001 |                                           |
| 0x0002 | Deleted (excluded)                        |
| 0x0004 |                                           |
| 0x0008 |                                           |
| 0x0010 | Exist in IT Invent                        |
| 0x0020 | Imported from netdev                      |
| 0x0040 | Active in IT Invent                       |
| 0x0080 | `mac` field is serial number              |

Возможные значения колонки `type` в таблице `devices`

| Value  | Description                               |
|--------|-------------------------------------------|
| 1      | 3PAR storage                              |
| 2      | Hyper-V cluster                           |
| 3      | netdev                                    |


Возможные значения колонки `oid` в таблицах `properties_*`
| Value  | PHP constant                              | Description                               |
|--------|-------------------------------------------|-------------------------------------------|
| 101    | CDB_PROP_USERACCOUNTCONTROL               | AD UserAccountControl                     |
| 102    | CDB_PROP_OPERATINGSYSTEM                  | Operation System name                     |
| 103    | CDB_PROP_OPERATINGSYSTEMVERSION           | Operation System version                  |
| 104    | CDB_PROP_BASELINE_COMPLIANCE_HOTFIX       | SCCM Baseline HotFix Compliance Status    |
|        |                                           |                                           |
|        |                                           |                                           |

Возможные значения колонки `tid` в таблицах `tasks` и `properties_*`
| Value  | Description                               |
|--------|-------------------------------------------|
| 1      | When `pid` from `computers` table         |
| 2      | When `pid` from `persons` table           |
| 3      | When `pid` from `mac` table               |
| 4      | When `pid` from `ac_log` table            |


Change flag 0x40 to 0x0400:
  ``UPDATE c_computers SET `flags` = ((`flags` & ~0x40) | 0x0400) WHERE `flags` & 0x40;``

Change flag 0x1000 to 0x0400:
  ``UPDATE c_tasks SET `flags` = ((`flags` & ~0x1000) | 0x0400) WHERE `flags` & 0x1000;``
