# CDB is a database for collect information about different computers

Now it collect information about Trent Micro Apex One and Endpoint Encryption.
It can create tasks in HelpDesk.


Available `flags` bit options

| Bits   | Description                               |
|--------|-------------------------------------------|
| 0x0001 | Disabled in AD                            |
| 0x0200 | TMEE Task was created in HelpDesk         |
| 0x0004 | Manual hide from report                   |
| 0x0800 | TMAO Task was created in HelpDesk         |
| 0x0010 | Temporary flag for delete after all syncs |
| 0x0020 | Deleted in AD                             |
| 0x4000 | Rename Task was created in HelpDesk       |
