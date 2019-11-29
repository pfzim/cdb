# CDB is a database for collect information about different computers and devices

Now it collect information about Trent Micro Apex One and Endpoint Encryption.
It can create tasks in HelpDesk.

Available `flags` bit options for table `computers`

| Bits   | Description                               |
|--------|-------------------------------------------|
| 0x0001 | Disabled in AD                            |
| 0x0002 | Deleted in AD                             |
| 0x0004 | Manual hide from report                   |
| 0x0008 | Temporary flag for delete after all syncs |

`tasks` table `flags`

| Bits   | Description                               |
|--------|-------------------------------------------|
| 0x0001 | Task was closed                           |
| 0x0002 |                                           |
| 0x0004 |                                           |
| 0x0008 |                                           |
| 0x0010 |                                           |
| 0x0020 |                                           |
| 0x0040 |                                           |
| 0x0080 |                                           |
| 0x0100 | TMEE Task was created in HelpDesk         |
| 0x0200 | TMAO Task was created in HelpDesk         |
| 0x0400 | Rename Task was created in HelpDesk       |
| 0x0800 | LAPS Task was created in HelpDesk         |

Table `devices` column `type`

| Value  | Description                               |
|--------|-------------------------------------------|
| 1      | 3PAR storage                              |
| 2      | Hyper-V cluster                           |


Change flag 0x40 to 0x0400:
  ``UPDATE c_computers SET `flags` = ((`flags` & ~0x40) | 0x0400) WHERE `flags` & 0x40;``
