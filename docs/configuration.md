# Configuration

This page is for CILogon staff who run the Registry and administer the Missouri CO. It
explains every setting an administrator enters for the EntraSource plugin: what the
Registry needs before you start, each field on the EntraSource configuration form, and
the Schema Extension Properties attached to a source. It describes the code on `main`
as it is now.

Code is cited by file and function, not line number. For what a sync does with these
settings, see [How sync works](how-sync-works.md). For the Graph endpoints and the
permissions the Entra application needs, see [Entra contract](entra-contract.md). For
values hardcoded for Missouri and behavior you might not expect, see
[Assumptions and known gaps](assumptions-and-gaps.md).

## Where the plugin is attached

EntraSource is an Organizational Identity Source (OIS) plugin. You add it in the CO as
an Organizational Identity Source whose plugin is EntraSource, then open that source's
configuration ("Configure") to reach the form described below. The EntraSource
configuration row is tied one-to-one to the Organizational Identity Source (unique index
on `org_identity_source_id` in `Config/Schema/schema.xml`). Settings that belong to the
Organizational Identity Source itself, such as its status and sync mode, are standard
Registry settings; see the COmanage
[Organizational Identity Sources](https://spaces.at.internet2.edu/display/COmanage/Organizational+Identity+Sources)
documentation.

Who can edit: a CMP administrator, or a CO administrator when Org Identities are not
pooled (`isAuthorized()` in `Controller/EntraSourcesController.php` and
`Controller/EntraSourceExtensionPropertiesController.php`).

## Prerequisites

Create these records in the same CO before you configure the source. The drop-down lists
on the form show only records whose `co_id` is the current CO (`beforeRender()` in
`Controller/EntraSourcesController.php`).

### OAuth2 Server (for "Access Token Server")

A Registry Server record of type OAuth2. The plugin uses it to get an access token for
Microsoft Graph with the client credentials grant: when the cached token is missing or
expires within 10 seconds, it calls the Registry's `Oauth2Server::obtainToken()` with
the grant type `client_credentials`, and it sends the token as a `Bearer` header on every
Graph request (`apiConnect()` and `apiRequest()` in `Model/EntraSourceBackend.php`).
The plugin passes the grant type itself; it does not read it from the Server record.

The record needs what the Registry's OAuth2 Server needs to run a client credentials
request against the Microsoft identity platform token endpoint for your tenant:

- the server URL for the tenant's OAuth 2.0 token endpoint (in the form
  `https://login.microsoftonline.com/<tenant-id>/oauth2/v2.0/...`; check the Registry
  OAuth2 Server documentation for whether it expects the full token URL or a base URL);
- the client ID of the Entra application registration;
- the client secret for that application;
- the scope Microsoft requires for client credentials access to Graph
  (`https://graph.microsoft.com/.default`).

The Entra application and the Graph permissions it must be granted are described in
[Entra contract](entra-contract.md). Never put real tenant IDs, client IDs, or secrets in
documentation or tickets.

If the token request does not return an `access_token`, the plugin logs "Unable to obtain
new access token" and the operation fails.

### HTTP Server (for "Microsoft Graph API Server")

A Registry Server record of type HTTP. The plugin reads only its server URL
(`HttpServer.serverurl`). Every Graph request is built as the server URL, a `/`, and
the request path, for example `users/<id>` or `groups` (`apiRequest()`). Paging links
that Graph returns (`@odata.nextLink`) are used as-is when they start with the server
URL.

Set the server URL to the Graph v1.0 base, `https://graph.microsoft.com/v1.0`, without a
trailing slash, because the plugin adds the slash. Credentials on this record are not
used; authentication comes from the OAuth2 Server above.

### UnixCluster plugin and a Unix Cluster

The plugin depends on the UnixCluster plugin. `Model/EntraSource.php` declares
associations to `UnixCluster`, and the backend loads `UnixClusterGroup` from that plugin,
so the UnixCluster plugin must be installed and enabled even though the form marks
"Unix Cluster" as optional. Create a Cluster in the CO that uses the UnixCluster plugin;
it then appears in the "Unix Cluster" drop-down. See
[UnixCluster plugin is required](assumptions-and-gaps.md#unixcluster-plugin-is-required).

## EntraSource fields

The form is `View/EntraSources/fields.inc`. Labels come from `Lib/lang.php`. Validation
rules are in `Model/EntraSource.php`. Columns are in the `entra_sources` table in
`Config/Schema/schema.xml`.

| UI label | Column | Required | Validation | What it controls |
| -------- | ------ | -------- | ---------- | ---------------- |
| Access Token Server | `access_token_server_id` | Yes | Must not be blank | OAuth2 Server used to get the Graph access token |
| Microsoft Graph API Server | `api_server_id` | Yes | Must not be blank | HTTP Server whose URL is the base for every Graph request |
| Select Using Groups | `use_source_groups` | No | Boolean | Whether the set of users is defined by Entra group membership |
| Entra group filter query parameter | `source_group_filter` | No (but see below) | Up to 256 characters | OData `$filter` that selects the source groups |
| Maximum inventory cache lifetime | `max_inventory_cache` | No (but see below) | Numeric | Minutes an inventory is reused before Graph is queried again |
| Unix Cluster | `unix_cluster_id` | No (but see below) | Numeric | Unix Cluster that every created CO Group is attached to |

The table has one more column, `inventory_cache_start`, which is not on the form. The
plugin writes the current time to it at the start of each full inventory
(`recordInventoryStart()`), and the cache check reads it. You do not set it.

### Access Token Server

- **Description on the form:** "The server consuming client credentials and issuing an
  access token".
- **Choices:** OAuth2 Servers in the current CO, listed by description.
- **Effect:** `apiConnect()` loads this OAuth2 Server before any Graph call. If the
  record cannot be found, the operation fails with a "not found" error for the server.
- **Gotcha:** Changing the client secret on the OAuth2 Server takes effect on the next
  token request. A token already cached on the Server record is used until it is within
  10 seconds of expiry.

### Microsoft Graph API Server

- **Description on the form:** "The server configured for the Microsoft Graph API
  endpoint".
- **Choices:** HTTP Servers in the current CO, listed by description.
- **Effect:** Its server URL is the prefix of every Graph request (see
  [HTTP Server](#http-server-for-microsoft-graph-api-server)). If the record cannot be
  found, the operation fails with a "not found" error for the server.
- **Gotcha:** Any HTTP status other than 200 (after 429 throttling retries) makes the
  request fail with "Microsoft Graph API returned code N", and the response body is
  logged. A wrong base URL usually shows up this way.

### Select Using Groups

- **Description on the form:** "Select the set of users using Entra groups".
- **Default:** No schema default. An unchecked box saves as false.
- **Effect when on:** `inventory()` builds the user list from the transitive members of
  the source groups chosen by the group filter, and reuses that list while the inventory
  cache is valid. `search()` returns a user only if they are in at least one source
  group, and it synchronizes the source groups first. See
  [How sync works](how-sync-works.md).
- **Effect when off:** `inventory()` calls `inventoryAllUsers()`, which is not
  implemented and returns an empty list, so the source supplies no users to a sync.
  `search()` returns the first Entra user whose `mail` matches, with no group check.
  The inventory cache is not consulted in this mode.
- **Gotcha:** In practice this must be on. See
  [All-users inventory is not implemented](assumptions-and-gaps.md#all-users-inventory-is-not-implemented-the-source-group-filter-is-required).

### Entra group filter query parameter

- **Description on the form:** "The OData V4 query language $filter query parameter
  value used to select the set of Entra groups".
- **Effect:** `synchronizeSourceGroups()` sends this value unchanged as `$filter` on a
  Graph `groups` request. Each group returned becomes a source group, keyed by its
  `mailNickname`, and gets a CO Group of the same name plus a group mapping on
  `memberOf`. A filter on the group name is typical, for example
  `startswith(mailNickname, 'prefix-')`.
- **Gotchas:**
  - The form does not require a value, but the plugin always sends it and has no
    handling for a blank filter. Set it whenever "Select Using Groups" is on.
  - The groups request is sent without the `ConsistencyLevel: eventual` header, so
    filters that need Graph advanced query support can be rejected by Graph. See
    [Entra contract](entra-contract.md).
  - Three Missouri groups are always added to the source groups whether or not they
    match the filter. See
    [Three groups that are always added](assumptions-and-gaps.md#three-groups-that-are-always-added).
  - Narrowing the filter removes the groups that no longer match from the source groups
    and their memberships, but keeps their CO Groups. See
    [CO Groups are kept when a source group disappears](assumptions-and-gaps.md#co-groups-are-kept-when-a-source-group-disappears).

### Maximum inventory cache lifetime

- **Description on the form:** "The maximum inventory cache validity time in minutes".
- **Effect:** Used only when "Select Using Groups" is on. `inventoryCacheValid()` treats
  the cache as valid while the start time of the last full inventory plus this many
  minutes is in the future. While it is valid, `inventory()` returns the stored records
  and makes no Graph calls. When it is not, the next inventory queries the source groups
  and their members again.
- **Why it matters:** `retrieve()` calls `inventory()` before it reads the user, so once
  the cache has expired, the next retrieve runs a full inventory. Set the
  lifetime long enough to cover one sync run. A value of 0 means the cache is never
  valid, so every inventory and every retrieve queries all source groups.
- **Gotcha:** The field is optional on the form, but `inventoryCacheValid()` does not
  handle a blank value specially. Set an explicit number.

### Unix Cluster

- **Description on the form:** "The Unix Cluster with which to associate groups for
  POSIX groups".
- **Choices:** UnixCluster clusters in the current CO, listed by description.
- **Effect:** For every source group, `synchronizeSourceGroups()` makes sure a
  UnixClusterGroup links the CO Group to this cluster.
- **Gotchas:**
  - The form marks it optional, but the backend uses it without checking that it is
    set. Always set it.
  - Changing it later makes the plugin add UnixClusterGroups in the new cluster. The
    ones in the old cluster are not removed.
  - In the read-only display of the form, the cluster name is looked up in
    `$vv_unix_cluster_ids`, while the controller sets `$vv_unix_clusters`
    (`View/EntraSources/fields.inc`), so the name may show blank there. The edit form is
    not affected.

## Schema Extension Properties

A Schema Extension Property tells the plugin to read one extra property from each Entra
user and store its value as an Identifier on the Org Identity. Use it for directory
extension attributes your tenant defines on users, such as a uidNumber.

### Where to manage them

On the EntraSource configuration page, use the "Manage Schema Extension Properties"
link (`View/EntraSources/buttons.inc`). It opens the list for that source, where you can
add, edit, and delete entries (`Controller/EntraSourceExtensionPropertiesController.php`).
Each entry belongs to one EntraSource, and entries are deleted with their source
(`Model/EntraSource.php`, `hasMany` with `dependent`).

### Fields

The form is `View/EntraSourceExtensionProperties/fields.inc`. Validation is in
`Model/EntraSourceExtensionProperty.php`. Columns are in the
`entra_source_extension_properties` table.

| UI label | Column | Required | Validation | What it controls |
| -------- | ------ | -------- | ---------- | ---------------- |
| Property | `property` | Yes | Must not be blank; up to 256 characters | Name of the Entra user property to read |
| Identifier Type | `identifier_type` | Yes | Must not be blank; up to 32 characters | Identifier type the value is stored under |

- **Property** ("The full name of the schema extension property"): the exact property
  name as Graph returns it on a user. For a directory extension that is the full
  generated name, `extension_<application id without dashes>_<attribute name>`, not the
  short attribute name.
- **Identifier Type** ("The Identifier type this property will be mapped to"): chosen
  from the CO's Identifier types, including any extended types defined for the CO
  (`Identifier->types()` in `beforeFilter()` of the controller).

The table also has `entra_source_id`, which the form sets in a hidden field; you do not
enter it.

### How a property becomes an Identifier

- `retrieve()` and `search()` add every configured property to the `$select` list of
  the Graph user request, after `id`, `givenName`, `surname`, `mail`, and
  `userPrincipalName` (`getExtensionProperties()`).
- `resultToOrgIdentity()` then adds, for each configured property that has a non-empty
  value on the user, one Identifier with that value, the configured type, and status
  Active. If the value is a list, only the first element is used. A user with no value
  for the property gets no Identifier for it, and no error.
- These Identifiers come after the two the plugin always adds: the Entra object id as
  SOR ID and the user principal name as type `upn`. See
  [`upn` identifier type](assumptions-and-gaps.md#upn-identifier-type).

### Gotchas

- Extension properties apply only to users. The group gidNumber is read from a fixed
  extension property name in the code, not from this list. See
  [gidNumber extension property name](assumptions-and-gaps.md#gidnumber-extension-property-name).
- Because every property is placed in `$select`, a misspelled or unknown property name
  can make Graph reject the user request. Any response other than 200 makes `retrieve()`
  or `search()` fail with "Microsoft Graph API returned code N"; the Graph error body is
  in the log.
- The property is read from the user record only. Groups and other Entra objects are not
  consulted.
