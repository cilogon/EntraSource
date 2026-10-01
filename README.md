# EntraSource Plugin

EntraSource is an Organizational Identity Source (OIS) plugin for COmanage Registry 4.x.
It reads users and groups from Microsoft Entra through the Microsoft Graph API and makes
them available to the Registry as Organizational Identities. For each Entra source group
it also creates a CO Group, a Unix cluster group, and the group mapping that places
members into that CO Group.

## Who the documentation is for

The pages under `docs/` are written for CILogon staff who operate the Registry and
administer the CO that uses this plugin. They describe the plugin as the code behaves
today, including the values hardcoded for the University of Missouri deployment. A
developer guide covers the internals for whoever maintains the plugin.

## Documentation

- [How sync works](docs/how-sync-works.md): how Entra groups and users become Org
  Identities and CO Group memberships, what the plugin stores, and when changes in
  Entra show up in the Registry.
- [Configuration](docs/configuration.md): every setting on an EntraSource and its
  extension properties, and the Registry server records and Unix Cluster it needs.
- [Troubleshooting](docs/troubleshooting.md): step-by-step diagnosis for a user missing
  from the Registry, missing from a CO Group, or not provisioned to LDAP or DynamoDB.
- [Entra contract](docs/entra-contract.md): every Microsoft Graph call, permission,
  and attribute the plugin depends on, and what changes in the Registry if Entra
  changes.
- [Assumptions and known gaps](docs/assumptions-and-gaps.md): Missouri-specific values
  in the code, and unfinished or surprising behavior.
- [Developer guide](docs/developer-guide.md): data model, backend organization, the
  Graph client, log messages, and open TODOs.

## See also

- [COmanage Registry Organizational Identity Source Plugins](https://spaces.at.internet2.edu/display/COmanage/Organizational+Identity+Source+Plugins)
