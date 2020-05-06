# Template repo

Admin configured folders accessible by everyone in a group.

## References

* **[Known issues and FAQs](https://github.com/nextcloud/templaterepo/issues/1414)**
* **[Release Notes](https://github.com/nextcloud-releases/templaterepo/releases)[^1]**
* **[App Store](https://apps.nextcloud.com/apps/templaterepo)**

[^1]: The releases are now managed in a [separate repository](https://github.com/nextcloud-releases/templaterepo/releases). The releases in this repository are outdated.

## Configure folders

Folders can be configured from *Template repo* in the admin settings.

After a folder is created, the admin can give access to the folder to one or more groups, a quota can be assigned for the folder and advanced permissions can be activated and configured.


![edit](screenshots/edit.png)

Permissions to the content of a template repo can be configured on a per-group basis.

![permissions](screenshots/permissions.png)

The configuration options include the _Write_, _Share_ and _Delete_ permissions for each group.

## Folders

Once configured, the folders will show up in the home folder for each user in the configured groups.

![folders](screenshots/folders.png)

## Advanced Permissions

_Advanced Permissions_ allows entitled users to configure permissions inside templaterepo on a per file and folder basis.

Permissions are configured by setting one or more of "Read", "Write", "Create", "Delete" or "Share" permissions to "allow" or "deny". Any permission not explicitly set will inherit the permissions from the parent folder. If multiple configured advanced permissions for a single file or folder apply for a single user (such as when a user belongs to multiple groups), the "allow" permission will overwrite any "deny" permission. Denied permissions configured for the template repo itself cannot be overwritten to "allow" permissions by the advanced permission rules.

![advanced permissions](screenshots/acl.png)

Users or whole groups can be entitled to set advanced permissions for each template repo separately on the template repo admin page.
For entitlements, only users from those groups are selectable which have to be configured selected in the Groups column.

![advanced permission entitlement](screenshots/aclAdmin.png)

## Command line configuration via occ

Template repo can be configured on the command line (cli) using the `occ` command:

- `occ templaterepo:create <name>` &rarr; create a template repo
- `occ templaterepo:delete <folder_id> [-f|--force]` &rarr; delete a template repo and all its contents
- `occ templaterepo:expire` &rarr; trigger file version and trashbin expiration (see [Nextcloud docs for versionning](https://docs.nextcloud.com/server/latest/admin_manual/configuration_files/file_versioning.html) and [Nextcloud docs for the trash bin](https://docs.nextcloud.com/server/latest/admin_manual/configuration_files/trashbin_configuration.html) for details)
- `occ templaterepo:group <folder_id> <group_id> [-d|--delete] [write|share|delete]` &rarr; assign groups and their rights to a template repo
- `occ templaterepo:list` &rarr; list configured template repo
- `occ templaterepo:permissions` &rarr; configure advanced permissions (see below for details)
- `occ templaterepo:quota <folder_id> [<quota>|unlimited]` &rarr; set a quota for a template repo
- `occ templaterepo:rename <folder_id> <name>` &rarr; rename a template repo
- `occ templaterepo:scan <folder_id>` &rarr; trigger a filescan for a template repo
- `occ templaterepo:trashbin:cleanup ` &rarr; empty the trashbin of all template repo

### Configuring advanced permissions trough occ

Advanced permissions can also be configured trough the `occ templaterepo:permissions` command.

To use the occ command you'll first need to find the id of the templaterepo you're trying to configure trough `occ templaterepo:list`.

Before configuring any advanced permissions you'll first have to enable advanced permissions for the folder using `occ templaterepo:permissions <folder_id> --enable`.
Then you can list all configured permissions trough `occ templaterepo:permissions <folder_id>`.
To disable the advanced permissions feature for a template repo, use `occ templaterepo:permissions <folder_id> --disable`.

```
occ templaterepo:permissions 1
+------------+--------------+-------------+
| Path       | User/Group   | Permissions |
+------------+--------------+-------------+
| folder     | group: admin | +write      |
| folder/sub | user: admin  | +share      |
|            | user: test   | -share      |
+------------+--------------+-------------+
```

Permissions for files and folders can be set trough `occ templaterepo:permissions <folder_id> --group <group_id> <path> -- <permissions>` to set permissions for a group or `occ templaterepo:permissions <folder_id> --user <user_id> <path> -- <permissions>` to set permissions for a single user.

`<permissions>` can be one or more of the following options: `-read`, `+read`, `-write`, `+write`, `-create`, `+create`, `-delete`, `+delete`, `-share` or `+share` to set the set the respective permission to "deny" or "allow".
You can delete a rule by passing `clear` as the `<permissions>` field.
Note: An advanced permission settings set always needs to be complete (for example `+read -create +delete`) and not just incremental (for example `-create`).
Not mentioned options (in the above example that's _write_ and _share_) are interpreted as _inherited_.

To help with configuring nested permission rules, you can check the effective permissions a user has for a path using `occ templaterepo:permissions <folder_id> --user <user_id> <path> --test`.

To manage the users or groups entitled to set advanced permissions, use `occ templaterepo:permissions <folder_id> [[-m|--manage-add] | [-r|--manage-remove]] [[-u|--user <user_id>] | [-g|--group <group_id>]]`.

## API

Template repo can be configured externally trough REST Api's.

The following REST API's are supported:

- `GET apps/templaterepo/folders`: Returns a list of all configured folders and their settings
- `POST apps/templaterepo/folders`: Create a new template repo
    - `mountpoint`: The name for the new folder
- `GET apps/templaterepo/folders/$folderId`: Return a specific configured folder and its settings
- `DELETE apps/templaterepo/folders/$folderId`: Delete a template repo
- `POST apps/templaterepo/folders/$folderId/groups`: Give a group access to a folder
    - `group`: The id of the group to be given access to the folder
- `DELETE apps/templaterepo/folders/$folderId/groups/$groupId`: Remove access from a group to a folder
- `POST apps/templaterepo/folders/$folderId/acl`: Enable/Disable folder advanced permissions
    - `acl` 1 for enable, 0 for disable.
- `POST apps/templaterepo/folders/$folderId/manageACL`: Grants/Removes a group or user the ability to manage a templaterepo' advanced permissions
    - `$mappingId`: the id of the group/user to be granted/removed access to/from the folder
    - `$mappingType`: 'group' or 'user'
    - `$manageAcl`: true to grants ability to manage a templaterepo' advanced permissions, false to remove
- `POST apps/templaterepo/folders/$folderId/groups/$groupId`: Set the permissions a group has in a folder
    - `permissions` The new permissions for the group as bitmask of [permissions constants](https://github.com/nextcloud/server/blob/b4f36d44c43aac0efdc6c70ff8e46473341a9bfe/lib/public/Constants.php#L65)
- `POST apps/templaterepo/folders/$folderId/quota`: Set the quota for a folder
    - `quota`: The new quota for the folder in bytes, user `-3` for unlimited
- `POST apps/templaterepo/folders/$folderId/mountpoint`: Change the name of a folder
    - `mountpoint`: The new name for the folder

For all `POST` calls the required parameters are listed.
