import * as React from 'react';
import {ChangeEvent, Component, FormEvent} from 'react';

import {Api, Folder, Group, User, ManageRuleProps, OCSGroup, OCSUser} from './Api';
import {FolderGroups} from './FolderGroups';
import {QuotaSelect} from './QuotaSelect';
import './App.scss';
import {SubmitInput} from "./SubmitInput";
import {SortArrow} from "./SortArrow";
import FlipMove from "react-flip-move";
import AsyncSelect from 'react-select/async'
import Thenable = JQuery.Thenable;
import {FolderUsers} from './FolderUsers';

const defaultQuotaOptions = {
	'1 GB': 1073741274,
	'5 GB': 5368709120,
	'10 GB': 10737412742,
	'無限制': -3
};

export type SortKey = 'mount_point' | 'quota' | 'groups' | 'acl' | 'users';

export interface AppState {
	folders: Folder[];
	groups: Group[],
	users: User[];
	newMountPoint: string;
	editingGroup: number;
	editingUser: number;
	editingMountPoint: number;
	editingAPIServer: number;
	renameMountPoint: string;
	filter: string;
	sort: SortKey;
	sortOrder: number;
}

export class App extends Component<{}, AppState> implements OC.Plugin<OC.Search.Core> {
	api = new Api();

	state: AppState = {
		folders: [],
		groups: [],
		users: [],
		newMountPoint: '',
		editingGroup: 0,
		editingUser: 0,
		editingMountPoint: 0,
		editingAPIServer: 0,
		renameMountPoint: '',
		filter: '',
		sort: 'mount_point',
		sortOrder: 1
	};

	componentDidMount() {
		this.api.listFolders().then((folders) => {
			this.setState({folders});
		});
		this.api.listGroups().then((groups) => {
			this.setState({groups});
		});
		this.api.listUsers().then((users) => {
			this.setState({users});
		});
		OC.Plugins.register('OCA.Search.Core', this);
	}

	createRow = (event: FormEvent) => {
		event.preventDefault();
		const mountPoint = this.state.newMountPoint;
		if (!mountPoint) {
			return;
		}
		this.setState({newMountPoint: ''});
		this.api.createFolder(mountPoint).then((id) => {
			const folders = this.state.folders;
			folders.push({
				mount_point: mountPoint,
				api_server: "https://127.0.0.1:9980",
				groups: {},
				users: {},
				quota: -3,
				size: 0,
				id,
				manage: []
			});
			this.setState({folders});
		});
	};

	attach = (search: OC.Search.Core) => {
		search.setFilter('settings', (query) => {
			this.setState({filter: query});
		});
	};

	deleteFolder(folder: Folder) {
		OC.dialogs.confirm(
			t('templaterepo', 'Are you sure you want to delete "{folderName}" and all files inside? This operation cannot be undone', {folderName: folder.mount_point}),
			t('templaterepo', 'Delete "{folderName}"?', {folderName: folder.mount_point}),
			confirmed => {
				if (confirmed) {
					this.setState({folders: this.state.folders.filter(item => item.id !== folder.id)});
					this.api.deleteFolder(folder.id);
				}
			},
			true
		);
	};

	syncFolder(folder: Folder) {
		OC.dialogs.confirm(
			t('templaterepo', 'Are you sure you want to sync "{folderName}" and all files inside?', { folderName: folder.mount_point }),
			t('templaterepo', 'Sync "{folderName}"?', { folderName: folder.mount_point }),
			confirmed => {
				if (confirmed) {
					this.api.syncFolder(folder.id);
				}
			},
			true
		);
	};

	addGroup(folder: Folder, group: string) {
		const folders = this.state.folders;
		folder.groups[group] = OC.PERMISSION_ALL;
		this.setState({folders});
		this.api.addGroup(folder.id, group);
	}

	removeGroup(folder: Folder, group: string) {
		const folders = this.state.folders;
		delete folder.groups[group];
		this.setState({folders});
		this.api.removeGroup(folder.id, group);
	}

	addUser(folder: Folder, user: string) {
		const folders = this.state.folders;
		folder.users[user] = OC.PERMISSION_ALL;
		this.setState({folders });
		this.api.addUser(folder.id, user);
	}

	removeUser(folder: Folder, user: string) {
		const folders = this.state.folders;
		delete folder.users[user];
		this.setState({folders });
		this.api.removeUser(folder.id, user);
	}

	setPermissions(folder: Folder, group: string, newPermissions: number) {
		const folders = this.state.folders;
		folder.groups[group] = newPermissions;
		this.setState({folders});
		this.api.setPermissions(folder.id, group, newPermissions);
	}

	setPermissionsForUser(folder: Folder, user: string, newPermissions: number) {
		const folders = this.state.folders;
		folder.users[user] = newPermissions;
		this.setState({folders });
		this.api.setPermissions(folder.id, user, newPermissions);
	}

	setQuota(folder: Folder, quota: number) {
		const folders = this.state.folders;
		folder.quota = quota;
		this.setState({folders});
		this.api.setQuota(folder.id, quota);
	}

	renameFolder(folder: Folder, newName: string) {
		const folders = this.state.folders;
		folder.mount_point = newName;
		this.setState({folders, editingMountPoint: 0});
		this.api.renameFolder(folder.id, newName);
	}

	renameAPIServer(folder: Folder, newName: string) {
		const folders = this.state.folders;
		folder.api_server = newName;
		this.setState({folders, editingAPIServer: 0});
		this.api.renameAPIServer(folder.id, newName);
	}

    setAcl(folder: Folder, acl: boolean) {
        const folders = this.state.folders;
        folder.acl = acl;
        this.setState({ folders });
        this.api.setACL(folder.id, acl);
    }

	onSortClick = (sort: SortKey) => {
		if (this.state.sort === sort) {
			this.setState({sortOrder: -this.state.sortOrder});
		} else {
			this.setState({sortOrder: 1, sort});
		}
	};

	render() {
		const rows = this.state.folders
			.filter(folder => {
				if (this.state.filter === '') {
					return true;
				}
				return folder.mount_point.toLowerCase().indexOf(this.state.filter.toLowerCase()) !== -1;
			})
			.sort((a, b) => {
				switch (this.state.sort) {
					case "mount_point":
						return a.mount_point.localeCompare(b.mount_point) * this.state.sortOrder;
					case "quota":
						if (a.quota < 0 && b.quota >= 0) {
							return this.state.sortOrder;
						}
						if (b.quota < 0 && a.quota >= 0) {
							return -this.state.sortOrder;
						}
						return (a.quota - b.quota) * this.state.sortOrder;
					case "groups":
						return (Object.keys(a.groups).length - Object.keys(b.groups).length) * this.state.sortOrder;
					case "users":
						return (Object.keys(a.users).length - Object.keys(b.users).length) * this.state.sortOrder;
					case "acl":
						if (a.acl && !b.acl) {
							return this.state.sortOrder;
						}
						if (!a.acl && b.acl) {
							return -this.state.sortOrder;
						}
						return 0;
				}
			})
			.map(folder => {
				const id = folder.id;
				return <tr key={id}>
					<td className="mountpoint">
						{this.state.editingMountPoint === id ?
							<SubmitInput
								autoFocus={true}
								onSubmitValue={this.renameFolder.bind(this, folder)}
								onClick={event => {
									event.stopPropagation();
								}}
								initialValue={folder.mount_point}
							/> :
							<a
								className="action-rename"
								onClick={event => {
									event.stopPropagation();
									this.setState({editingMountPoint: id})
								}}
							>
								{folder.mount_point}
							</a>
						}
					</td>
					<td className="groups">
						<FolderGroups
							edit={this.state.editingGroup === id}
							showEdit={event => {
								event.stopPropagation();
								this.setState({editingGroup: id})
							}}
							groups={folder.groups}
							allGroups={this.state.groups}
							onAddGroup={this.addGroup.bind(this, folder)}
							removeGroup={this.removeGroup.bind(this, folder)}
							onSetPermissions={this.setPermissions.bind(this, folder)}
						/>
					</td>
					<td className="users">
						<FolderUsers
							edit={this.state.editingUser === id}
							showEdit={event => {
								event.stopPropagation();
								this.setState({ editingUser: id })
							}}
							users={folder.users}
							allUsers={this.state.users}
							onAddUser={this.addUser.bind(this, folder)}
							removeUser={this.removeUser.bind(this, folder)}
							onSetPermissions={this.setPermissionsForUser.bind(this, folder)}
						/>
					</td>
					<td className="quota">
						<QuotaSelect options={defaultQuotaOptions}
									 value={folder.quota}
									 size={folder.size}
									 onChange={this.setQuota.bind(this, folder)}/>
					</td>
					<td className="server">
						{this.state.editingAPIServer === id ?
							<SubmitInput
								autoFocus={true}
								onSubmitValue={this.renameAPIServer.bind(this, folder)}
								onClick={event => {
									event.stopPropagation();
								}}
								initialValue={folder.api_server}
							/> :
							<a
								className="action-rename"
								onClick={event => {
									event.stopPropagation();
									this.setState({editingAPIServer: id})
								}}
							>
								{folder.api_server}
							</a>
						}
					</td>

                    <td className="acl">
                        <input id={`acl-${folder.id}`} type="checkbox" className="checkbox" checked={folder.acl} disabled={!App.supportACL()}
                            title={
                                App.supportACL() ?
                                    t('templaterepo', 'Advanced permissions allows setting permissions on a per-file basis but comes with a performance overhead') :
                                    t('templaterepo', 'Advanced permissions are only supported with Nextcloud 16 and up')}
                            onChange={(event) => this.setAcl(folder, event.target.checked)}
                        />
                        <label htmlFor={`acl-${folder.id}`}></label>
                        {folder.acl &&
                            <ManageAclSelect
                                folder={folder}
                                onChange={this.setManageACL.bind(this, folder)}
                                onSearch={this.searchMappings.bind(this, folder)}
                            />
                        }
                    </td>
					<td className="sync">
						<a className="icon icon-upload icon-visible"
							onClick={this.syncFolder.bind(this, folder)}
							title={t('templaterepo', 'SyncServer')}/>
					</td>
					<td className="remove">
						<a className="icon icon-delete icon-visible"
						   onClick={this.deleteFolder.bind(this, folder)}
						   title={t('templaterepo', 'Delete')}/>
					</td>
				</tr>
			});

		return <div id="templaterepo-react-root"
					onClick={() => {
						this.setState({editingGroup: 0, editingUser: 0, editingMountPoint: 0})
					}}>
			<table>
				<thead>
				<tr>
					<th onClick={() => this.onSortClick('mount_point')}>
						{t('templaterepo', 'Folder name')}
						<SortArrow name='mount_point' value={this.state.sort}
								   direction={this.state.sortOrder}/>
					</th>
					<th onClick={() => this.onSortClick('groups')}>
						{t('templaterepo', 'Groups')}
						<SortArrow name='groups' value={this.state.sort}
								   direction={this.state.sortOrder}/>
					</th>
					<th onClick={() => this.onSortClick('users')}>
						{t('templaterepo', 'Users')}
						<SortArrow name='users' value={this.state.sort}
							direction={this.state.sortOrder} />
					</th>
					<th onClick={() => this.onSortClick('quota')}>
						{t('templaterepo', 'Quota')}
						<SortArrow name='quota' value={this.state.sort}
								   direction={this.state.sortOrder}/>
					</th>
					<th>
						{t('templaterepo', 'APIServer')}
					</th>

					<th onClick={() => this.onSortClick('acl')}>
						{t('templaterepo', 'Advanced Permissions')}
						<SortArrow name='acl' value={this.state.sort}
							direction={this.state.sortOrder} />
					</th>

					<th/>
					<th/>
				</tr>
				</thead>
				<FlipMove typeName='tbody' enterAnimation="accordionVertical" leaveAnimation="accordionVertical">
					{rows}
					<tr>
						<td>
							<form action="#" onSubmit={this.createRow}>
								<input
									className="newgroup-name"
									value={this.state.newMountPoint}
									placeholder={t('templaterepo', 'Folder name')}
									onChange={(event) => {
										this.setState({newMountPoint: event.target.value})
									}}/>
								<input type="submit"
									   value={t('templaterepo', 'Create')}/>
							</form>
						</td>
						<td colSpan={3}/>
					</tr>
				</FlipMove>
			</table>
		</div>;
	}
}

interface ManageAclSelectProps {
    folder: Folder;
    onChange: (type: string, id: string, manageAcl: boolean) => void;
    onSearch: (name: string) => Thenable<{ groups: OCSGroup[]; users: OCSUser[]; }>;
};

function ManageAclSelect({ onChange, onSearch, folder }: ManageAclSelectProps) {
    const handleSearch = (inputValue: string) => {
        return new Promise(resolve => {
            onSearch(inputValue).then((result) => {
                resolve([...result.groups, ...result.users])
            })
        })
    }

    const typeLabel = (item) => {
        return item.type === 'user' ? t('templaterepo', 'User') : t('templaterepo', 'Group')
    }
    return <AsyncSelect
        loadOptions={handleSearch}
        isMulti
        cacheOptions
        defaultOptions
        defaultValue={folder.manage}
        isClearable={false}
        onChange={(option, details) => {
            if (details.action === 'select-option') {
                const addedOption = details.option
                onChange && onChange(addedOption.type, addedOption.id, true)
            }
            if (details.action === 'remove-value') {
                const removedValue = details.removedValue
                onChange && onChange(removedValue.type, removedValue.id, false)
            }
        }}
        placeholder={t('templaterepo', 'Users/groups that can manage')}
        getOptionLabel={(option) => `${option.displayname} (${typeLabel(option)})`}
        getOptionValue={(option) => option.type + '/' + option.id}
        styles={{
            control: base => ({
                ...base,
                minHeight: 25,
                borderWidth: 1
            }),
            dropdownIndicator: base => ({
                ...base,
                padding: 4
            }),
            clearIndicator: base => ({
                ...base,
                padding: 4
            }),
            multiValue: base => ({
                ...base,
                backgroundColor: 'var(--color-background-dark)',
                color: 'var(--color-text)'
            }),
            valueContainer: base => ({
                ...base,
                padding: '0px 6px'
            }),
            input: base => ({
                ...base,
                margin: 0,
                padding: 0
            }),
            menu: (provided) => ({
                ...provided,
                backgroundColor: 'var(--color-main-background)',
                borderColor: 'var(--color-border)',
            })
        }}
    />
}
