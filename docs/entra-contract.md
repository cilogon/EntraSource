# Entra contract

This page is for CILogon staff who run the Registry and administer the Missouri CO. It
lists what the plugin needs from Microsoft Entra and Microsoft Graph: every Graph call it
makes, the permissions those calls need, the user and group attributes it reads, and the
naming and membership rules it assumes. For each one it says what you would see in the
Registry if Missouri changed it on the Entra side. Use it to judge the impact of a
planned Entra change before the change is made.

The page describes the code on `main` as it is now. Code is cited by file and function,
not line number. Unless noted, the file is `Model/EntraSourceBackend.php`.

Related pages:

- [Assumptions and known gaps](assumptions-and-gaps.md) holds the values hardcoded for
  Missouri (the gidNumber extension name, the three always-added groups, fixed retry
  values). This page links there instead of repeating them.
- [How sync works](how-sync-works.md) describes the order in which these calls happen
  during a sync.
- [Configuration](configuration.md) describes the fields that point the plugin at Entra.

## Authentication

The plugin authenticates as an application, not as a user. In `apiRequest()`, before each
Graph call, it asks the Registry whether the cached access token expires within 10
seconds. If it does, it gets a new token by calling the Registry's
`Oauth2Server->obtainToken()` with the `client_credentials` grant, using the OAuth2 server
selected in the configuration. Every Graph call then sends that token as a Bearer token.

The plugin code does not hold the token endpoint, client id, secret, scope, or Graph base
URL. They come from the OAuth2 server and HTTP server objects configured in the Registry
(see [Configuration](configuration.md)).

Because the grant is client credentials, the app registration needs **application**
permissions (not delegated permissions), granted with admin consent.

| If Missouri changes... | What you see in the Registry |
| ---------------------- | ---------------------------- |
| The client secret expires or is rotated without updating the Registry's OAuth2 server | No token is issued. `apiRequest()` logs and throws on every call, so inventory, retrieve, and search all fail. |
| The app registration is deleted or its admin consent is removed | Same as above, or Graph returns 403 on each call, which `apiRequest()` treats as a failure. |

## Graph calls

The plugin makes Graph calls from five places, each through `apiRequest()`. Each row below
is one `apiRequest()` call site. All are `GET` requests. Paged responses are followed
through `@odata.nextLink` until no more pages remain; those follow-up requests go through
the same call site.

| # | Request (path relative to the configured Graph base URL) | Query and headers | Calling function | Called from | Purpose |
| - | -------------------------------------------------------- | ----------------- | ---------------- | ----------- | ------- |
| 1 | `groups` | `$filter` = the configured source group filter; `$select=id,mailNickname,<gidNumber extension>`; `$top=999` | `synchronizeSourceGroups()` | `inventoryBySourceGroups()` and `search()` | List the source groups and read each one's gidNumber. |
| 2 | `groups/{group id}/transitiveMembers/microsoft.graph.user` | `$select=id`; `$top=999` | `inventoryOneSourceGroup()` | `inventoryBySourceGroups()` | List every user who is a direct or nested member of one source group. |
| 3 | `users/{user id}/transitiveMemberOf` | `$select=id,mailNickname`; `$top=999` | `getFilteredSourceGroupsForSourceRecord()` | `search()` only (the call in `retrieve()` is commented out) | Check whether a user found by search is in any stored source group. |
| 4 | `users/{user id}` | `$select=id,givenName,surname,mail,userPrincipalName` plus each configured user extension property | `retrieve()` | Registry, for each record in the inventory | Read one user to build the Org Identity. |
| 5 | `/users` | `$filter=mail eq '<address>'`; same `$select` as row 4; header `ConsistencyLevel: eventual` | `search()` | Registry, for an Org Identity search by email | Find a user by email address. |

### Permissions for each call

These permissions are **inferred**. Nothing in the code declares them. Each is taken from
the "Application" row of the permissions table in the Microsoft Graph v1.0 reference for
that endpoint. **Inferred -- confirm against the app registration.**

| # | Endpoint | Inferred application permission | Notes | Reference |
| - | -------- | ------------------------------- | ----- | --------- |
| 0 | Token request | None (client credentials grant) | The token carries whatever application permissions the app registration has been granted. | -- |
| 1 | List groups | **Group.Read.All** (inferred -- confirm against the app registration) | The reference lists `Group-NestingSupport.ReadWrite.All` as "least privileged", but that is a write permission. Among read-only permissions it lists `Group.ReadBasic.All` and `Group.Read.All`. `Group.ReadBasic.All` reads only "basic properties"; the docs do not say whether that includes `mailNickname` or a directory extension value, so `Group.Read.All` is the safe inference. | [group-list](https://learn.microsoft.com/en-us/graph/api/group-list?view=graph-rest-1.0&tabs=http), [permissions reference](https://learn.microsoft.com/en-us/graph/permissions-reference) |
| 2 | List group transitive members | **GroupMember.ReadBasic.All** (inferred -- confirm against the app registration) | `Group.Read.All` is also listed as sufficient. The plugin reads only `id`. Groups with hidden membership also need `Member.Read.Hidden`. | [group-list-transitivemembers](https://learn.microsoft.com/en-us/graph/api/group-list-transitivemembers?view=graph-rest-1.0&tabs=http) |
| 3 | List a user's transitive memberships | **User.Read.All** (inferred -- confirm against the app registration) | With only `User.Read.All`, Graph may return groups with only `id` and `@odata.type`. The plugin uses only those two fields, so this is enough. | [user-list-transitivememberof](https://learn.microsoft.com/en-us/graph/api/user-list-transitivememberof?view=graph-rest-1.0&tabs=http) |
| 4 | Get user | **User.Read.All** (inferred -- confirm against the app registration) | | [user-get](https://learn.microsoft.com/en-us/graph/api/user-get?view=graph-rest-1.0&tabs=http) |
| 5 | List users | **User.Read.All** (inferred -- confirm against the app registration) | | [user-list](https://learn.microsoft.com/en-us/graph/api/user-list?view=graph-rest-1.0&tabs=http) |

In summary, `User.Read.All` plus `Group.Read.All` covers every call above (inferred --
confirm against the app registration). `Directory.Read.All` alone also appears as a
sufficient, higher-privileged permission on every one of these endpoints.

| If Missouri changes... | What you see in the Registry |
| ---------------------- | ---------------------------- |
| Removes a group permission | Call 1 fails. See [When a Graph call fails](#when-a-graph-call-fails): inventory and search stop. |
| Removes a user permission | Calls 3, 4, and 5 fail. Retrieve and search fail for every user. |
| Makes a source group's membership hidden without granting `Member.Read.Hidden` | Call 2 fails or returns no members for that group (not verified which). If it fails, that group is skipped and its memberships go stale. |

## What the plugin reads from Entra

### User attributes

Read by `retrieve()` and `search()` and turned into an Org Identity by
`resultToOrgIdentity()`.

| Entra attribute | Used for | If missing or changed in Entra |
| --------------- | -------- | ------------------------------ |
| `id` (object id) | Key for the source record (`graph_id`) and the SOR ID Identifier. `retrieve()` is called with this id. | If a user is deleted and recreated, the new object is a different record. The old record stays in the inventory (see [Source records are never removed](assumptions-and-gaps.md#source-records-are-never-removed)), and `GET users/{old id}` returns 404, so `retrieve()` throws for it. |
| `givenName`, `surname` | The Org Identity's Name | If both are empty, the Org Identity gets no Name. A change shows on the next retrieve. |
| `mail` | The Org Identity's Email Address, and the search key | If empty, no Email Address, and the user cannot be found by search. |
| `userPrincipalName` | The `upn` Identifier | A UPN change shows on the next retrieve as a changed `upn` Identifier. |
| User extension properties | Extra Identifiers, one per property configured in the Registry UI | See [User extension properties](#user-extension-properties). |

`memberOf` in the raw record is not read from Entra during retrieve. `retrieve()` builds it
from the source group memberships stored by the last inventory, using each group's stored
mailNickname.

### Group attributes

Read by `synchronizeSourceGroups()` (call 1).

| Entra attribute | Used for |
| --------------- | -------- |
| `id` (object id) | Stored as the source group's `graph_id`. Used for call 2, and by `search()` to match a user's groups (call 3) against stored source groups. |
| `mailNickname` | The source group's name. Also the CO Group name, the CoGroupOisMapping pattern, the `uid` Identifier, and the `memberOf` value. It is the key the plugin uses to match an Entra group to a stored source group. |
| gidNumber group extension | The source group's gidNumber and the CO Group's `gidnumber` Identifier. The extension name is hardcoded (see [gidNumber extension property name](assumptions-and-gaps.md#gidnumber-extension-property-name)). |

Call 3 also selects `mailNickname`, but the code does not use it. It matches on `id`
and keeps only entries whose `@odata.type` is `#microsoft.graph.group`.

### User extension properties

Staff configure user extension properties in the Registry UI (Schema Extension
Properties on the EntraSource). Each one names an Entra property and the Registry
Identifier type to map it to. `getExtensionProperties()` loads them, `retrieve()` and
`search()` add each property name to `$select`, and `resultToOrgIdentity()` turns each
non-empty value into an Identifier of the configured type. If the value is an array, only
the first element is used.

These apply only to users. They do not affect the group gidNumber extension.

| If Missouri changes... | What you see in the Registry | Code change needed? |
| ---------------------- | ---------------------------- | ------------------- |
| Renames or replaces a user extension property | The configured name no longer matches. Either Graph rejects the `$select` and retrieve and search fail for every user, or the property is omitted and that Identifier is no longer produced. Which one happens is not settled by the Graph docs (see [What Graph does with an unknown extension name](#what-graph-does-with-an-unknown-extension-name)). | No. Update the property name in the Registry UI. |
| Clears the value for some users | Those users' Org Identities no longer include that Identifier. | No. |

## Assumptions about Entra

### mailNickname is the group's name and identity

The plugin keys source groups by `mailNickname`, not by object id. In
`synchronizeSourceGroups()` it builds its list of Entra groups indexed by `mailNickname`,
looks up each stored source group by `mailNickname`, and deletes stored source groups whose
`mailNickname` is no longer in the list. The CO Group is found by name, which equals
`mailNickname`.

The source group filter is sent to Graph as given. It usually filters on `mailNickname`, so
a naming change can also change which groups match. The filter is a configuration value
(see [Configuration](configuration.md)).

### Membership is transitive

Members come from `transitiveMembers` (call 2), so a user in a nested group counts as a
member of every source group above it. Only user objects are kept (the
`microsoft.graph.user` cast). Membership is refreshed only by an inventory, not by a
retrieve.

### mail is the search key

`search()` accepts only `mail`. It filters Graph users with `mail eq '<address>'`, sends
`ConsistencyLevel: eventual`, and uses only the first match. The address is inserted into
the filter as given; an address with a single quote in it makes the filter invalid, and
the call fails.

### Throttling and errors

`apiRequest()` handles Graph throttling as Microsoft's
[throttling guidance](https://learn.microsoft.com/en-us/graph/throttling) describes:

- On HTTP 429, it reads the `Retry-After` header, sleeps that many seconds, and repeats the
  same request. It uses 5 seconds only if reading the header throws. There is no retry
  limit; it keeps retrying while Graph returns 429.
- Any status other than 200 or 429 is logged with the response body, and `apiRequest()`
  throws a `RuntimeException`.

The fixed values are listed under
[Fixed request and retry values](assumptions-and-gaps.md#fixed-request-and-retry-values).

### When a Graph call fails

Where a failure is caught decides what you see:

- **Call 2 (one group's members):** `inventoryOneSourceGroup()` catches the exception,
  logs it, and moves on to the next source group. That group's stored memberships are left
  as they were, so they go stale with no sign in the Registry UI.
- **Call 1 (group list):** `inventoryBySourceGroups()` calls `synchronizeSourceGroups()`
  without a try/catch, and `inventory()` does not catch it either. The exception ends the
  inventory. `inventory()` has already saved the inventory start time
  (`recordInventoryStart()`), so until the configured cache time runs out, later calls to
  `inventory()` (including the one at the start of each `retrieve()`) return the stored
  records without calling Graph. `search()` also calls `synchronizeSourceGroups()`
  directly, so search fails too.
- **Calls 3, 4, 5:** not caught in the plugin. The search or retrieve for that user fails.

## Impact of Entra changes

| If Missouri changes... | What you see in the Registry | Code change needed? |
| ---------------------- | ---------------------------- | ------------------- |
| A source group's `mailNickname` | The plugin treats it as a new group. It creates a source group and a new CO Group (with mapping, UnixClusterGroup, and Identifiers) under the new name, and deletes the old source group. The old CO Group stays (see [CO Groups are kept when a source group disappears](assumptions-and-gaps.md#co-groups-are-kept-when-a-source-group-disappears)), and members move to the new CO Group. The new source group takes the Entra gidNumber, so the old and new CO Groups can both carry the same `gidnumber` Identifier. Also, the schema declares a unique index on the source group `graph_id`, and the new row is saved with the same `graph_id` before the old row is deleted; if that index exists in the database, the save can fail and stop `synchronizeSourceGroups()`. This last point is read from the code and schema, not tested. | No, but the old CO Group needs cleanup by hand. |
| A group is deleted and recreated with the same `mailNickname` | The stored source group keeps the old object id if it already has both an id and a gidNumber (see [A stored gidNumber is never refreshed from Entra](assumptions-and-gaps.md#a-stored-gidnumber-is-never-refreshed-from-entra)). Call 2 then asks for the old id, fails, and that group is skipped, so its memberships go stale. Search does not see users in it. | No, but the stored source group must be fixed or removed by hand. |
| A group stops matching the filter, or is deleted | The source group and its memberships are deleted. The CO Group stays. On the next retrieve, members' `memberOf` no longer lists it. | No. |
| Group nesting | Membership follows the nested structure (transitive). Adding or removing a nested group changes who is a member at the next inventory. | No. |
| Two groups that match the filter share a `mailNickname` | Only one is kept; the one later in the Graph response replaces the earlier one. | No. |
| A user's `mail` | The Email Address changes on the next retrieve. Search finds the user only by the new address. | No. |
| A user's name or `userPrincipalName` | The Name or `upn` Identifier changes on the next retrieve. | No. |
| The gidNumber extension | See [The gidNumber extension](#the-gidnumber-extension). | Yes, for a rename or replacement. |
| Graph permissions | See [Permissions for each call](#permissions-for-each-call). | No. |

The three hardcoded groups (see
[Three groups that are always added](assumptions-and-gaps.md#three-groups-that-are-always-added))
are added with a fixed name, object id, and gidNumber whatever Entra says. The plugin
ignores changes to those values in Entra. If one of those groups gets a new object id,
call 2 fails for it and its memberships go stale. Updating any of these values needs a
code change.

## The gidNumber extension

The group gidNumber comes from one directory extension property whose full name is a
string literal in `synchronizeSourceGroups()`. It is not configurable, and the user
extension properties in the Registry UI do not affect it. The exact name is on the
[assumptions page](assumptions-and-gaps.md#gidnumber-extension-property-name).

How the plugin stores it (all in `synchronizeSourceGroups()`):

- A gidNumber is saved on a source group only when Entra returns a non-empty value. The
  stored column is an integer.
- An existing source group is updated only if its stored object id or stored gidNumber is
  empty. Once both are set, the row is never updated again.
- For each source group that has a stored gidNumber, the CO Group is given a `gidnumber`
  Identifier with that value, and the value is put back if someone edits it.
- A source group with no stored gidNumber gets a CO Group with a `uid` Identifier and no
  `gidnumber` Identifier.

### If Missouri renames, replaces, or removes the extension

- **A code change is needed.** The name is hardcoded; there is no setting for it.
- **Source groups already stored keep their existing gidNumber.** Stored values are never
  refreshed from Entra, so their CO Groups keep their current `gidnumber` Identifiers.
- **Source groups first seen after the change get no gidNumber**, so their CO Groups get no
  `gidnumber` Identifier. The same holds for stored source groups that never had one.
- **The three hardcoded groups are not affected.** Their gidNumbers are in the code.
- **The whole group sync may stop**, depending on how Graph answers a `$select` that names
  an extension it no longer knows. See the next section.

### If Missouri changes the gidNumber value on a group

Source groups already stored with a gidNumber keep the old value, and so does the CO
Group's `gidnumber` Identifier. Only a group that had no stored gidNumber picks up the new
value. Changing a stored value means editing the stored source group by hand or a code
change.

### What Graph does with an unknown extension name

The question is whether `GET /groups` with `$select` naming a directory extension that no
longer exists (renamed, deleted, or its owner app deleted) returns an error or quietly
leaves the property out.

**Unverified.** The Microsoft Graph documentation does not settle it:

- The [query parameters guide](https://learn.microsoft.com/en-us/graph/query-parameters)
  ("Error handling for query parameters") says some unsupported query parameters return
  an error and that others "fail silently". It does not say which applies to an unknown
  property in `$select`.
- The [extensibility overview](https://learn.microsoft.com/en-us/graph/extensibility-overview)
  says that deleting a directory extension definition, or its owner app, makes the stored
  data "undiscoverable", and that recreating the definition with the same name on the same
  owner app recovers it. It does not describe the `$select` response.
- The [group-list](https://learn.microsoft.com/en-us/graph/api/group-list?view=graph-rest-1.0&tabs=http)
  and [user-get](https://learn.microsoft.com/en-us/graph/api/user-get?view=graph-rest-1.0&tabs=http)
  references say only that directory extensions can be requested with `$select`.

What the plugin does in each case:

- **If Graph omits the property:** the plugin reads it as empty. The sync runs, and the
  effects listed above apply.
- **If Graph returns an error:** `apiRequest()` throws, `synchronizeSourceGroups()` stops,
  and nothing in the plugin catches it (see [When a Graph call fails](#when-a-graph-call-fails)).
  Inventory and search fail on every run until the extension name is fixed in the code.
  No source groups or memberships are updated in the meantime.

A group that simply has no value in an extension that still exists is a different case.
The code handles it: the group is stored without a gidNumber.

Before Missouri makes the change, test the new and old extension names with a `$select`
against the tenant (for example in Graph Explorer, with an app that has the same
permissions) to see which behavior applies.

## Notes on the Graph references

- The [group-list-transitivemembers](https://learn.microsoft.com/en-us/graph/api/group-list-transitivemembers?view=graph-rest-1.0&tabs=http)
  and [user-list-transitivememberof](https://learn.microsoft.com/en-us/graph/api/user-list-transitivememberof?view=graph-rest-1.0&tabs=http)
  references say that query parameters on those endpoints are supported only as advanced
  queries, with `ConsistencyLevel: eventual` and `$count`. Calls 2 and 3 send `$select`,
  `$top`, and (call 2) an OData cast without either. This page does not establish whether
  Graph enforces that for these calls. If it starts to, those calls fail.
- The code comments link the v1.0 reference. The Graph version actually used is whatever
  the configured base URL names.
