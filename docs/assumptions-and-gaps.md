# Assumptions and known gaps

This page is for CILogon staff who run the Registry and administer the Missouri CO.
It lists the values the plugin hardcodes for Missouri, and the paths that are unfinished
or behave in ways you might not expect. The page describes the code on `main` as it is
now, not how the plugin is meant to work later.

Each entry gives what the code does, what you see in the Registry because of it, and
where the code is. Code is cited by file and function, not line number. Unless noted,
the file is `Model/EntraSourceBackend.php`.

For how a sync runs end to end, see [How sync works](how-sync-works.md). For the
configuration fields, see [Configuration](configuration.md). For what the plugin needs
from Entra and Microsoft Graph, see [Entra contract](entra-contract.md).

## Missouri-specific values

### gidNumber extension property name

- **What the code does:** When it lists source groups, the plugin always asks Graph for
  the group extension property `extension_c5a01f20b34f469fad2518bfb66e7107_gidNumber`
  and reads the group's gidNumber from it. The name is a string literal. It is not
  taken from the configuration, and the Extension Properties set up in the Registry
  UI do not affect it (those apply only to users).
- **What you see:** A source group gets a `gidnumber` Identifier on its CO Group only
  if the Entra group has a value in this exact property. A group with no value gets a
  CO Group with a `uid` Identifier and no `gidnumber` Identifier.
- **Where:** `synchronizeSourceGroups()`, marked
  `TODO remove hardcoded extension for gidNumber.`

### Three groups that are always added

- **What the code does:** After it reads the groups that match the source group filter,
  the plugin adds three more groups to the list, whether or not they match the filter.
  Each has a hardcoded Entra group object id and gidNumber:

  | Group (mailNickname)             | Entra group object id                  | gidNumber |
  | -------------------------------- | -------------------------------------- | --------- |
  | `nic-cluster-admins`             | `18acec66-005e-47f9-8685-c5a983bb8f13` | 647975    |
  | `nic-cluster-admins-tier1-admin` | `8d00e1de-8f74-48b3-a626-1d3f8aff6675` | 100494552 |
  | `nic-software-installer`         | `57efb1ea-cb40-424e-8c7d-6a89f23a26b7` | 100385889 |

- **What you see:** These three groups appear as source groups and as CO Groups (with
  CoGroupOisMapping, UnixClusterGroup, and `uid` and `gidnumber` Identifiers) for every
  EntraSource, even though their names do not match the filter. Their members are
  inventoried like members of any other source group, and `search()` accepts a user who
  is in one of them. The plugin never removes them, because they are always in the list
  it reconciles against. If one of these groups also matches the filter, the hardcoded
  object id and gidNumber replace the values read from Entra.
- **Where:** `synchronizeSourceGroups()`, marked
  `TODO remove this hard-coded additional list of groups.`

### Affiliation is always `member`

- **What the code does:** Every Org Identity built from an Entra user gets affiliation
  `AffiliationEnum::Member`.
- **What you see:** All Org Identities from this source show affiliation "member",
  whatever the user's role at Missouri.
- **Where:** `resultToOrgIdentity()`, marked `TODO affiliation should be configurable.`

### Name type is always `official`

- **What the code does:** If the Entra user has a `givenName` or `surname`, the plugin
  creates one Name of type `NameEnum::Official` and marks it primary.
- **What you see:** Each Org Identity has at most one Name, of type "official", marked
  primary. A user with neither field in Entra gets no Name.
- **Where:** `resultToOrgIdentity()`, marked `TODO Name type should be configurable.`

### Email type is always `official` and verified

- **What the code does:** If the Entra user has `mail`, the plugin creates one Email
  Address of type `EmailAddressEnum::Official` with `verified` set to true.
- **What you see:** The email address shows as type "official" and is already marked verified.
- **Where:** `resultToOrgIdentity()`, marked
  `TODO EmailAddress type should be configurable.`

### `upn` identifier type

- **What the code does:** The plugin always adds the Entra `userPrincipalName` as an
  Identifier of type `upn`. The type is the literal string `'upn'`, not a Registry
  constant. The plugin also adds the Entra object id as an Identifier of type
  `IdentifierEnum::SORID`.
- **What you see:** Each Org Identity has a `upn` Identifier holding the user principal
  name and a SOR ID Identifier holding the Entra object id. Any other user Identifiers
  come from the Extension Properties configured for the source.
- **Where:** `resultToOrgIdentity()`, marked `TODO Identifier type should be configurable.`

### `uid` and `gidnumber` Identifiers on CO Groups

- **What the code does:** For each source group, the plugin makes sure the CO Group has an
  active Identifier of type `uid` equal to the group's mailNickname. If the stored source
  group has a gidNumber, it also makes sure there is an active Identifier of type
  `gidnumber` with that value. Both types are fixed in the code, and both Identifiers
  are saved with validation turned off.
- **What you see:** If someone edits either Identifier in the Registry so it no longer
  matches, the next group sync changes it back.
- **Where:** `synchronizeSourceGroups()`, marked `TODO remove assumptions here.` (uid)
  and `TODO remove assumptions here about Identifier and even the need for an
  Identifier.` (gidnumber)

## Unfinished or assumed behavior

### UnixCluster plugin is required

- **What the code does:** The `EntraSource` model has associations to `UnixCluster`, and
  the backend loads `UnixClusterGroup` from the UnixCluster plugin. For every source
  group it creates a UnixClusterGroup that links the CO Group to the configured
  `unix_cluster_id`. The configuration form marks Unix Cluster as optional, but the
  backend does not check whether it is set before it uses it.
- **What you see:** The UnixCluster plugin must be installed and enabled in the
  Registry. Every CO Group the plugin creates is attached to the configured Unix Cluster.
- **Where:** `Model/EntraSource.php` (class properties, marked
  `TODO Remove assumption that UnixCluster plugin is enabled.`) and
  `synchronizeSourceGroups()`.

### All-users inventory is not implemented; the source group filter is required

- **What the code does:** `inventory()` calls `inventoryAllUsers()` when "use source
  groups" is off, and `inventoryAllUsers()` returns an empty list.
  `synchronizeSourceGroups()` always sends the configured source group filter to Graph.
  It does not handle a blank filter specially.
- **What you see:** With "use source groups" off, the inventory is always empty, so the
  source supplies no records to a sync. `search()` still works in that mode, and it
  returns any Entra user whose `mail` matches, without checking group membership. In
  practice the source must run with "use source groups" on and a source group filter
  set.
- **Where:** `inventoryAllUsers()`; `synchronizeSourceGroups()`, marked
  `TODO Remove the assumption that we are using a source_group_filter.`

### A stored gidNumber is never refreshed from Entra

- **What the code does:** When a source group already exists, the plugin updates its row
  only if the stored Graph id or the stored gidNumber is empty. Once both are set,
  later changes in Entra are not copied.
- **What you see:** If the gidNumber of an Entra group changes, the source group keeps
  the old value, and so does the `gidnumber` Identifier on the CO Group. The same is
  true of the group's Graph object id.
- **Where:** `synchronizeSourceGroups()` (no TODO).

### Inventory is not scoped to one EntraSource

- **What the code does:** `inventoryFromCache()` returns the Graph id of every
  EntraSourceRecord in the database. It does not filter by EntraSource.
  `inventoryBySourceGroups()` uses it for its final result.
- **What you see:** With a single EntraSource, nothing changes. With more than one, the
  inventory for each source also lists users that belong to the others.
- **Where:** `inventoryFromCache()` (no TODO).

### Source records are never removed

- **What the code does:** When a user is no longer a transitive member of a source group,
  the plugin deletes that EntraSourceGroupMembership. It never deletes the
  EntraSourceRecord, even when the user is in no source group at all. The docblock of
  `inventoryOneSourceGroup()` says records are created "(or remove[d])", and the current
  `README.md` says records are deleted when they are no longer part of any group.
  Neither is true of the code.
- **What you see:** A user who leaves every source group stays in the inventory. Their
  Org Identity remains, and the next retrieve shows an empty `memberOf`, so the group
  mapping takes them out of the CO Groups. The same holds for any user fetched by
  `retrieve()`: it calls `addSourceRecord()` for the user it reads, so a user retrieved
  once (for example after a search) stays in later inventories even if they are in no
  source group.
- **Where:** `synchronizeTransitiveMembers()`, `retrieve()`, and `addSourceRecord()`
  (no TODO).

### CO Groups are kept when a source group disappears

- **What the code does:** When a group no longer matches the filter or is gone from Entra,
  the plugin deletes the EntraSourceGroup and its memberships. On purpose, it does not
  delete the CO Group, the CoGroupOisMapping, the UnixClusterGroup, or the Identifiers.
- **What you see:** The CO Group stays in the Registry, still mapped to a `memberOf`
  value that no user will have again. Staff must clean it up by hand if it should go.
- **Where:** `synchronizeSourceGroups()` (comment: "We purposely do not delete related
  CoGroup objects at this time.").

### search() synchronizes source groups

- **What the code does:** When "use source groups" is on, `search()` calls
  `synchronizeSourceGroups()` before it checks whether the user is in a source group.
  `search()` uses only the first Graph user whose `mail` matches.
- **What you see:** An Org Identity search by email can create, update, or delete source
  groups and create CO Groups, Identifiers, mappings, and UnixClusterGroups, just like
  a sync. If more than one Entra user has the same `mail`, only the first is returned.
- **Where:** `search()` (no TODO).

### Fixed request and retry values

These are not Missouri-specific. They are fixed in the code and cannot be configured.

| Value | Effect | Where |
| ----- | ------ | ----- |
| Token renewal 10 seconds before expiry | Fetches a new client-credentials token if the current one expires within 10 seconds | `apiRequest()` |
| 5-second retry wait | Wait used after HTTP 429 only if reading the `Retry-After` header throws; a missing header reads as 0, so the retry is immediate | `apiRequest()` |
| `$top=999` | Page size for group lists, group members, and a user's group memberships | `synchronizeSourceGroups()`, `inventoryOneSourceGroup()`, `getFilteredSourceGroupsForSourceRecord()` |
| `ConsistencyLevel: eventual` | Header sent on the user search by `mail` | `search()` |
| CO Group type `Clusters`, not open | Type and open setting of every CO Group the plugin creates | `synchronizeSourceGroups()` |

For symptoms and fixes, see [Troubleshooting](troubleshooting.md). To change any of these
values, see the [Developer guide](developer-guide.md).
