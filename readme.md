# CDB is a database for collect information about different computers

Now it collect information about Trent Micro Apex One and Endpoint Encryption.
It can create tasks in HelpDesk.


Available `flags` bit options

| Bits | Description                               |
|------|-------------------------------------------|
| 0x01 | Disabled in AD                            |
| 0x02 | TMEE Task was created in HelpDesk         |
| 0x04 | Manual hide from report                   |
| 0x08 | TMAO Task was created in HelpDesk         |
| 0x10 | Temporary flag for delete after all syncs |
| 0x20 | Deleted in AD                             |
