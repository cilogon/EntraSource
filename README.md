# EntraSource Plugin
## COmanage Registry Organizational Identity Source (OIS) Plugin

### Models

- EntraSource: primary plugin model

- EntraSourceExtensionProperty: represents schema extension properties
that may be defined by an organization to enhance the default Microsoft
Entra user account, such as uidNumber, gidNumber, and primaryCampus.

- EntraSourceRecord: represents a user account in Entra, indexed by the
```graph_id```.

- EntraSourceGroup: represents a collection of Entra user accounts and the
group resource type in Entra.

- EntraSourceGroupMembership: tracks the membership of an Entra user account
in an Entra group resource.

- EntraSourceBackend: class that extends the Registry OrgIdentitySourceBackend
class and where most of the logic for the plugin is located.


### Interfaces

See the COmanage Registry 
[Organizational Identity Source Plugins](https://spaces.at.internet2.edu/display/COmanage/Organizational+Identity+Source+Plugins)
documentation for details on the requirements for these interfaces:

- ```inventory()```: Returns all available records. Since the set of users
   may be defined in terms of group memberships, and since querying for the
   groups and group memberships is expensive, a cache is used. See the
   section below on caching.

   Two logic paths are used for inventory, one when the set of users is
   defined by membership in a list or collection of groups, and one when
   all users known to Entra are inventoried.

   - Inventory by source groups uses the
     [group-list](https://learn.microsoft.com/en-us/graph/api/group-list?view=graph-rest-1.0&tabs=http)
     endpoint with a filter that defines the set of groups to include, 
     for example ```startswith(mailNickname, 'rss-')```.

     Returned objects are synchronized with EntraSourceGroup objects. A CO
     Group, CoGroupOisMapping, and UnixClusterGroup object is created
     corresponding to each Entra source group. 

     The name of each CO Group object is the name of the Entra group.
     Identifier objects attached carry the gidNumber for the group and
     UID that will be used when provisioning as a posixGroup in LDAP.

   - After the list of source groups is synchronized the list of
     groups is looped over and the transitive members for each group
     synchronized with the EntraSourceGroupMembership objects. When necessary
     a new EntraSourceRecord is created to represent a user record. Records
     are also deleted when no longer part of any group.





- ```retrieve()```:

- ```groupableAttributes()```: 

- ```resultToGroups()```:


- ```search()```:

- ```searchableAttributes()```:




   



### Caching


### Credentials

