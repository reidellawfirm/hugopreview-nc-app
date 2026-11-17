<template>
	<NcDialog
		class="rd-fp-dialog"
		:class="{ 'theme-dark': isDarkMode, 'theme-light': !isDarkMode }"
		:name="t('hugopreview_nc_app', 'Select Project Folder')"
		:open="open"
		size="normal"
		@update:open="$emit('update:open', $event)"
	>
		<!-- Instruction -->
		<p class="rd-fp__hint">
			{{ t('hugopreview_nc_app', 'Click a folder to select it. Use “Open” or double-click to browse inside.') }}
		</p>

		<!-- Breadcrumb navigation -->
		<nav class="rd-fp__crumb" aria-label="Current path">
			<button class="rd-fp__crumb-seg" @click="navigateBreadcrumb('')">
				<HomeIcon :size="14" />
				{{ t('hugopreview_nc_app', 'Files') }}
			</button>
			<template v-for="(seg, idx) in pathSegments" :key="idx">
				<ChevronRightIcon :size="12" class="rd-fp__crumb-arrow" aria-hidden="true" />
				<button
					class="rd-fp__crumb-seg"
					:class="{ 'rd-fp__crumb-seg--last': idx === pathSegments.length - 1 }"
					@click="navigateBreadcrumb(pathUpTo(idx))"
				>{{ seg }}</button>
			</template>
		</nav>

		<!-- Search and Filter -->
		<div class="rd-fp__filters">
			<div class="rd-fp__search">
				<input type="text" v-model="searchQuery" :placeholder="t('hugopreview_nc_app', 'Search folders...')" class="rd-fp__search-input" />
			</div>
			<div class="rd-fp__date-filter">
				<select v-model="dateFilter" class="rd-fp__select">
					<option value="all">{{ t('hugopreview_nc_app', 'All time') }}</option>
					<option value="recent">{{ t('hugopreview_nc_app', 'Recently uploaded') }}</option>
					<option value="today">{{ t('hugopreview_nc_app', 'Today') }}</option>
					<option value="last7">{{ t('hugopreview_nc_app', 'Last 7 days') }}</option>
					<option value="last30">{{ t('hugopreview_nc_app', 'Last 30 days') }}</option>
					<option value="thisYear">{{ t('hugopreview_nc_app', 'This year (' + currentYear + ')') }}</option>
					<option value="lastYear">{{ t('hugopreview_nc_app', 'Last year (' + (currentYear - 1) + ')') }}</option>
				</select>
			</div>
		</div>

		<!-- â”€â”€ "Use This Folder" section â€” shown whenever inside any folder â”€â”€ -->
		<div v-if="currentPath" class="rd-fp__use-section" :class="{ 'rd-fp__use-section--chosen': selectedPath === currentPath }">
			<div class="rd-fp__use-info">
				<FolderOpenIcon :size="20" class="rd-fp__use-icon" />
				<div class="rd-fp__use-text">
					<span class="rd-fp__use-label">{{ t('hugopreview_nc_app', 'Current folder:') }}</span>
					<span class="rd-fp__use-path" :title="'/' + currentPath">/{{ currentPath }}</span>
				</div>
			</div>
			<NcButton
				v-if="!multiSelect && selectedPath !== currentPath"
				type="primary"
				class="rd-fp__use-btn"
				@click="useCurrentFolder"
			>
				<template #icon><FolderCheckOutlineIcon :size="16" /></template>
				{{ t('hugopreview_nc_app', 'Use This Folder') }}
			</NcButton>
			<span v-else-if="!multiSelect" class="rd-fp__use-chosen-badge">
				<CheckCircleIcon :size="16" />
				{{ t('hugopreview_nc_app', 'Selected') }}
			</span>
		</div>

		<!-- â”€â”€ Subfolder list â”€â”€ -->
		<p class="rd-fp__subfolder-hdr">
			{{ currentPath ? t('hugopreview_nc_app', 'Subfolders') : t('hugopreview_nc_app', 'Folders') }}
		</p>

		<div class="rd-fp__scroll" role="listbox" :aria-label="t('hugopreview_nc_app', 'Folders')">
			<!-- Loading skeleton -->
			<template v-if="loading">
				<div v-for="n in 6" :key="n" class="rd-fp__skel-row">
					<span class="rd-fp__skel-icon" />
					<span class="rd-fp__skel-text" :style="{ width: (40 + (n * 11) % 45) + '%' }" />
				</div>
			</template>

			<!-- Error -->
			<div v-else-if="error" class="rd-fp__state rd-fp__state--error">
				<AlertCircleIcon :size="20" />
				<span>{{ error }}</span>
			</div>

			<!-- Empty at root -->
			<div v-else-if="!folders.length && !currentPath" class="rd-fp__state rd-fp__state--empty">
				{{ t('hugopreview_nc_app', 'No folders found in your Files.') }}
			</div>

			<!-- Empty inside a folder â€” guide user to "Use This Folder" above -->
			<div v-else-if="!folders.length && currentPath && !searchQuery" class="rd-fp__state rd-fp__state--empty rd-fp__state--empty-sub">
				<FolderOutlineIcon :size="28" class="rd-fp__empty-icon" />
				<span>{{ t('hugopreview_nc_app', 'No subfolders here.') }}</span>
				<span class="rd-fp__empty-hint">{{ t('hugopreview_nc_app', 'Use the button above to select this folder.') }}</span>
			</div>

			<!-- Folder rows -->
			<template v-else>
				<!-- Back button -->
				<div
					v-if="currentPath && !searchQuery"
					class="rd-fp__item rd-fp__item--back"
					role="button"
					tabindex="0"
					@click="navigateUp"
					@keydown.enter="navigateUp"
				>
					<ChevronLeftIcon :size="20" class="rd-fp__item-icon" />
					<span class="rd-fp__item-name">.. ({{ t('hugopreview_nc_app', 'Go back') }})</span>
				</div>
				<div
					v-for="folder in filteredFolders"
					:key="folder.path"
					class="rd-fp__item"
					:class="{ 'rd-fp__item--sel': isSelected(folder), 'rd-fp__item--disabled': isDisabled(folder) }"
					role="option"
					:aria-selected="isSelected(folder)"
					:aria-disabled="isDisabled(folder)"
					tabindex="0"
					@click="!isDisabled(folder) && selectFolder(folder)"
					@dblclick.stop="navigateTo(folder.path)"
					@keydown.enter="!isDisabled(folder) && selectFolder(folder)"
					@keydown.space.prevent="!isDisabled(folder) && selectFolder(folder)"
				>
					<!-- Selection indicator -->
					<span class="rd-fp__item-radio" :class="{ 'rd-fp__item-radio--multi': multiSelect }" aria-hidden="true">
						<span v-if="!multiSelect" class="rd-fp__item-radio-dot" />
						<svg v-else class="rd-fp__item-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
							<polyline points="20 6 9 17 4 12"></polyline>
						</svg>
					</span>

					<FolderOpenIcon v-if="isSelected(folder)" :size="20" class="rd-fp__item-icon rd-fp__item-icon--sel" />
					<FolderOutlineIcon v-else :size="20" class="rd-fp__item-icon" />

					<span class="rd-fp__item-name">
						{{ folder.name }}
						<span v-if="isDisabled(folder)" class="rd-fp__item-badge">{{ t('hugopreview_nc_app', '(Already added)') }}</span>
					</span>

					<!-- Explicit "Open" navigate button — clearly separated from selection -->
					<button
						class="rd-fp__item-open"
						:title="t('hugopreview_nc_app', 'Open and browse inside this folder')"
						@click.stop="navigateTo(folder.path)"
						@keydown.enter.stop="navigateTo(folder.path)"
					>
						{{ t('hugopreview_nc_app', 'Open') }}
						<ChevronRightIcon :size="12" />
					</button>
				</div>
			</template>
		</div>

		<!-- Validation Error -->
		<div v-if="validationError" class="rd-fp__validation-error">
			<div class="rd-fp__validation-error-title">
				<AlertCircleIcon :size="20" />
				{{ t('hugopreview_nc_app', 'Invalid Hugo Project') }}
			</div>
			<p>{{ t('hugopreview_nc_app', 'The selected folder is not a valid Hugo project.') }}</p>
			<p>{{ t('hugopreview_nc_app', 'Please select a Hugo project that contains one of the following configuration files:') }}</p>
			<ul>
				<li>hugo.toml</li>
				<li>config.toml</li>
				<li>config.yaml</li>
				<li>config.yml</li>
				<li>config.json</li>
			</ul>
		</div>

		<!-- â”€â”€ Selection preview bar â”€â”€ -->
		<div class="rd-fp__preview" :class="{ 'rd-fp__preview--active': hasSelection }">
			<template v-if="hasSelection">
				<FolderCheckOutlineIcon :size="16" class="rd-fp__preview-icon" />
				<span class="rd-fp__preview-label">{{ t('hugopreview_nc_app', 'Selected:') }}</span>
				<span v-if="multiSelect" class="rd-fp__preview-path" :title="selectedPathsText">{{ selectedPathsText }}</span>
				<span v-else class="rd-fp__preview-path" :title="'/' + selectedPath">/{{ selectedPath }}</span>
			</template>
			<span v-else class="rd-fp__preview-none">{{ t('hugopreview_nc_app', 'No folder selected yet') }}</span>
		</div>

		<template #actions>
			<NcButton @click="$emit('update:open', false)">{{ t('hugopreview_nc_app', 'Cancel') }}</NcButton>
			<NcButton type="primary" :disabled="!hasSelection || validating" @click="confirm">
				<template #icon><FolderCheckOutlineIcon :size="16" /></template>
				<template v-if="validating">{{ t('hugopreview_nc_app', 'Validating...') }}</template>
				<template v-else-if="multiSelect && selectedFolders.length > 1">{{ t('hugopreview_nc_app', 'Select ' + selectedFolders.length + ' Folders') }}</template>
				<template v-else>{{ t('hugopreview_nc_app', 'Select Folder') }}</template>
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import axios from '@nextcloud/axios'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import FolderOutlineIcon from 'vue-material-design-icons/FolderOutline.vue'
import FolderOpenIcon from 'vue-material-design-icons/FolderOpen.vue'
import FolderCheckOutlineIcon from 'vue-material-design-icons/FolderCheckOutline.vue'
import CheckCircleIcon from 'vue-material-design-icons/CheckCircleOutline.vue'
import ChevronRightIcon from 'vue-material-design-icons/ChevronRight.vue'
import ChevronLeftIcon from 'vue-material-design-icons/ChevronLeft.vue'
import HomeIcon from 'vue-material-design-icons/Home.vue'
import AlertCircleIcon from 'vue-material-design-icons/AlertCircleOutline.vue'

export default {
	name: 'FolderPickerDialog',
	components: {
		NcButton,
		NcDialog,
		FolderOutlineIcon,
		FolderOpenIcon,
		FolderCheckOutlineIcon,
		CheckCircleIcon,
		ChevronRightIcon,
		ChevronLeftIcon,
		HomeIcon,
		AlertCircleIcon,
	},
	props: {
		open: { type: Boolean, default: false },
		initialPath: { type: String, default: '' },
		multiSelect: { type: Boolean, default: false },
		disabledPaths: { type: Array, default: () => [] },
	},
	emits: ['update:open', 'select'],
	data() {
		return {
			currentPath: '',       // decoded path being browsed (no leading/trailing slash)
			currentNodeId: null,   // oc:fileid of the folder we're currently browsing
			selectedPath: '',      // single select path
			selectedNodeId: null,  // single select nodeId
			selectedFolders: [],   // multi select array: [{path, nodeId, name}]
			folders: [],           // direct child folders of currentPath
			loading: false,
			error: null,
			searchQuery: '',
			dateFilter: 'all',
			validating: false,
			validationError: false,
			theme: localStorage.getItem('hugo-theme') || 'auto',
			systemIsDark: window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
		}
	},
	computed: {
		isDarkMode() {
			if (this.theme === 'auto') return this.systemIsDark;
			return this.theme === 'dark';
		},
		hasSelection() {
			return this.multiSelect ? this.selectedFolders.length > 0 : !!this.selectedPath;
		},
		selectedPathsText() {
			if (!this.multiSelect || this.selectedFolders.length === 0) return '';
			if (this.selectedFolders.length === 1) return '/' + this.selectedFolders[0].path;
			return this.selectedFolders.map(f => f.name).join(', ');
		},
		currentYear() {
			return new Date().getFullYear();
		},
		pathSegments() {
			return this.currentPath ? this.currentPath.split('/').filter(Boolean) : []
		},
		userId() {
			return window.OC?.currentUser ?? ''
		},
		webRoot() {
			return (window.OC?.webroot ?? '').replace(/\/$/, '')
		},
		filteredFolders() {
			let result = this.folders;

			// Date filter
			if (this.dateFilter !== 'all') {
				const now = new Date();
				const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
				const thisYear = now.getFullYear();

				if (this.dateFilter === 'today') {
					result = result.filter(f => f.lastModified >= today);
				} else if (this.dateFilter === 'last7') {
					const last7 = new Date(today);
					last7.setDate(last7.getDate() - 7);
					result = result.filter(f => f.lastModified >= last7);
				} else if (this.dateFilter === 'last30') {
					const last30 = new Date(today);
					last30.setDate(last30.getDate() - 30);
					result = result.filter(f => f.lastModified >= last30);
				} else if (this.dateFilter === 'thisYear') {
					result = result.filter(f => f.lastModified.getFullYear() === thisYear);
				} else if (this.dateFilter === 'lastYear') {
					result = result.filter(f => f.lastModified.getFullYear() === thisYear - 1);
				}
			}

			// Text search
			if (this.searchQuery) {
				const q = this.searchQuery.toLowerCase()
				result = result.filter(f => f.name.toLowerCase().includes(q))
			}

			// Sort
			if (this.dateFilter === 'recent') {
				// Sort newest first
				result = result.slice().sort((a, b) => b.lastModified - a.lastModified);
			} else {
				// Alphabetical
				result = result.slice().sort((a, b) => a.name.localeCompare(b.name, undefined, { sensitivity: 'base' }));
			}

			return result;
		}
	},
	watch: {
		isDarkMode: {
			immediate: true,
			handler(isDark) {
				if (this.open) {
					document.body.setAttribute('data-hugo-theme', isDark ? 'dark' : 'light');
				}
			}
		},
		open(val) {
			if (val) {
				this.reset()
				document.body.setAttribute('data-hugo-theme', this.isDarkMode ? 'dark' : 'light');
			} else {
				document.body.removeAttribute('data-hugo-theme');
			}
		},
	},
	mounted() {
		window.addEventListener('storage', this.handleStorageEvent)
		if (window.matchMedia) {
			this.mediaQueryListener = (e) => {
				this.systemIsDark = e.matches;
			};
			window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', this.mediaQueryListener);
		}
	},
	beforeUnmount() {
		window.removeEventListener('storage', this.handleStorageEvent)
		if (window.matchMedia && this.mediaQueryListener) {
			window.matchMedia('(prefers-color-scheme: dark)').removeEventListener('change', this.mediaQueryListener);
		}
		document.body.removeAttribute('data-hugo-theme');
	},
	methods: {
		t,
		handleStorageEvent(e) {
			if (e.key === 'hugo-theme') {
				this.theme = e.newValue || 'auto';
			}
		},

		reset() {
			this.currentPath = ''
			this.currentNodeId = null
			this.selectedPath = ''
			this.selectedNodeId = null
			this.selectedFolders = []
			this.error = null
			this.validating = false
			this.validationError = false

			if (this.initialPath) {
				const path = this.initialPath.replace(/^\//, '')
				this.selectedPath = path
				if (this.multiSelect && path) {
					// In multi-select, if we have an initial path, treat it as the only selection initially.
					const segs = path.split('/')
					this.selectedFolders = [{ path: path, nodeId: null, name: segs[segs.length - 1] }]
				}
				const segs = path.split('/')
				segs.pop()
				this.currentPath = segs.join('/')
			}

			this.fetchFolders()
		},

		pathUpTo(idx) {
			return this.pathSegments.slice(0, idx + 1).join('/')
		},

		davUrl(path) {
			const encodedUser = encodeURIComponent(this.userId)
			const encodedPath = path ? path.split('/').map(encodeURIComponent).join('/') : ''
			const suffix = encodedPath ? `/${encodedPath}/` : '/'
			return `${this.webRoot}/remote.php/dav/files/${encodedUser}${suffix}`
		},

		async fetchFolders() {
			this.loading = true
			this.error = null
			this.folders = []
			this.currentNodeId = null
			try {
				const body = `<?xml version="1.0" encoding="utf-8"?>
<D:propfind xmlns:D="DAV:" xmlns:oc="http://owncloud.org/ns">
  <D:prop>
    <D:displayname/>
    <D:resourcetype/>
    <D:getlastmodified/>
    <oc:fileid/>
  </D:prop>
</D:propfind>`
				const resp = await axios({
					method: 'PROPFIND',
					url: this.davUrl(this.currentPath),
					data: body,
					headers: { 'Content-Type': 'application/xml', Depth: '1' },
				})
				this.folders = this.parseDAVResponse(resp.data)
			} catch (err) {
				console.error('[CRM] FolderPicker fetch error:', err)
				this.error = t('hugopreview_nc_app', 'Could not load folders. Please try again.')
			} finally {
				this.loading = false
			}
		},

		parseDAVResponse(xml) {
			const DAV = 'DAV:'
			const OC = 'http://owncloud.org/ns'
			let doc
			try {
				doc = new DOMParser().parseFromString(xml, 'application/xml')
			} catch {
				return []
			}

			const userRootDecoded = `${this.webRoot}/remote.php/dav/files/${this.userId}`
			const decode = (s) => { try { return decodeURIComponent(s) } catch { return s } }
			const results = []

			for (const resp of doc.getElementsByTagNameNS(DAV, 'response')) {
				const rt = resp.getElementsByTagNameNS(DAV, 'resourcetype')[0]
				if (!rt || !rt.getElementsByTagNameNS(DAV, 'collection').length) continue

				const rawHref = resp.getElementsByTagNameNS(DAV, 'href')[0]?.textContent?.trim() ?? ''
				const decodedHref = rawHref.replace(/\/$/, '').split('/').map(decode).join('/')

				let relPath = decodedHref
				if (relPath.startsWith(userRootDecoded + '/')) {
					relPath = relPath.slice(userRootDecoded.length + 1)
				} else if (relPath === userRootDecoded) {
					relPath = ''
				} else {
					continue
				}

				const nodeIdText = resp.getElementsByTagNameNS(OC, 'fileid')[0]?.textContent?.trim() ?? ''
				const nodeId = nodeIdText ? parseInt(nodeIdText, 10) : null

				// Capture the nodeId of the folder we're currently browsing
				if (relPath === this.currentPath) {
					this.currentNodeId = nodeId
					continue
				}

				// Only direct children
				if (this.currentPath) {
					if (!relPath.startsWith(this.currentPath + '/')) continue
					const sub = relPath.slice(this.currentPath.length + 1)
					if (sub.includes('/')) continue
				} else {
					if (relPath.includes('/')) continue
				}

				const name = resp.getElementsByTagNameNS(DAV, 'displayname')[0]?.textContent?.trim()
					|| relPath.split('/').pop()

				const lastModifiedStr = resp.getElementsByTagNameNS(DAV, 'getlastmodified')[0]?.textContent?.trim()
				const lastModified = lastModifiedStr ? new Date(lastModifiedStr) : new Date(0)

				results.push({ name, path: relPath, nodeId, lastModified })
			}

			return results
		},

		navigateTo(path) {
			this.currentPath = path
			this.currentNodeId = null
			this.searchQuery = ''
			this.fetchFolders()
		},

		navigateBreadcrumb(path) {
			const targetPath = path ? path.replace(/\/$/, '') : ''
			this.navigateTo(targetPath)
		},

		navigateUp() {
			if (!this.currentPath) return
			const segs = this.pathSegments
			segs.pop()
			this.navigateBreadcrumb(segs.join('/'))
		},

		isSelected(folder) {
			if (this.multiSelect) {
				return this.selectedFolders.some(f => f.path === folder.path);
			}
			return this.selectedPath === folder.path;
		},

		isDisabled(folder) {
			return this.disabledPaths.includes(folder.path);
		},

		/** Select the folder we're currently browsing (shown in breadcrumb). */
		useCurrentFolder() {
			if (this.multiSelect) return;
			this.selectedPath = this.currentPath
			this.selectedNodeId = this.currentNodeId
		},

		/** Select a child folder from the list. */
		selectFolder(folder) {
			this.validationError = false;
			if (this.multiSelect) {
				const idx = this.selectedFolders.findIndex(f => f.path === folder.path);
				if (idx > -1) {
					this.selectedFolders.splice(idx, 1);
				} else {
					this.selectedFolders.push({ path: folder.path, nodeId: folder.nodeId, name: folder.name });
				}
			} else {
				this.selectedPath = folder.path
				this.selectedNodeId = folder.nodeId
			}
		},

		async validateHugoFolder(path) {
			try {
				const body = `<?xml version="1.0" encoding="utf-8"?>
<D:propfind xmlns:D="DAV:" xmlns:oc="http://owncloud.org/ns">
  <D:prop>
    <D:displayname/>
    <D:resourcetype/>
  </D:prop>
</D:propfind>`
				const resp = await axios({
					method: 'PROPFIND',
					url: this.davUrl(path),
					data: body,
					headers: { 'Content-Type': 'application/xml', Depth: '1' },
				})

				const doc = new DOMParser().parseFromString(resp.data, 'application/xml')
				const DAV = 'DAV:'
				const validFiles = ['hugo.toml', 'config.toml', 'config.yaml', 'config.yml', 'config.json']

				for (const response of doc.getElementsByTagNameNS(DAV, 'response')) {
					const nameNode = response.getElementsByTagNameNS(DAV, 'displayname')[0]
					if (nameNode) {
						const name = nameNode.textContent.trim()
						if (validFiles.includes(name)) {
							const rt = response.getElementsByTagNameNS(DAV, 'resourcetype')[0]
							const isDir = rt && rt.getElementsByTagNameNS(DAV, 'collection').length > 0
							if (!isDir) {
								return true
							}
						}
					}
				}
				return false
			} catch (err) {
				console.error('Validation error', err)
				return false
			}
		},

		async confirm() {
			if (!this.hasSelection || this.validating) return

			this.validating = true
			this.validationError = false

			try {
				if (this.multiSelect) {
					for (const folder of this.selectedFolders) {
						const isValid = await this.validateHugoFolder(folder.path)
						if (!isValid) {
							this.validationError = true
							this.selectedFolders = []
							return
						}
					}

					const mapped = this.selectedFolders.map(f => ({
						path: '/' + f.path,
						nodeId: f.nodeId,
						name: f.name
					}))
					this.$emit('select', mapped)
				} else {
					const isValid = await this.validateHugoFolder(this.selectedPath)
					if (!isValid) {
						this.validationError = true
						this.selectedPath = ''
						this.selectedNodeId = null
						return
					}

					this.$emit('select', {
						path: '/' + this.selectedPath,
						nodeId: this.selectedNodeId,
						name: this.selectedPath.split('/').pop() || ''
					})
				}
				this.$emit('update:open', false)
			} finally {
				this.validating = false
			}
		},
	},
}
</script>

<style lang="scss" scoped>
@import './_tokens.scss';

/* â”€â”€ Hint â”€â”€ */
.rd-fp__hint {
	color: $rd-text-muted;
	font-size: $rd-font-sm;
	margin: 0 0 $rd-space-2;
}

/* â”€â”€ Breadcrumb â”€â”€ */
.rd-fp__crumb {
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	gap: $rd-space-1;
	padding: $rd-space-2;
	background: $rd-bg-page;
	border: 1px solid $rd-border;
	border-radius: $rd-radius-sm;
	margin-bottom: $rd-space-2;
	min-height: 38px;

	&-seg {
		display: inline-flex;
		align-items: center;
		gap: 4px;
		background: none;
		border: none;
		cursor: pointer;
		color: $rd-primary;
		font-size: $rd-font-sm;
		padding: 3px 8px;
		border-radius: $rd-radius-sm;
		transition: background 0.12s;
		&:hover { background: $rd-primary-soft; }
		&--last { color: $rd-text; font-weight: 600; cursor: default; pointer-events: none; }
	}

	&-arrow { color: $rd-text-muted; flex-shrink: 0; }
}

/* ―― Filters ―― */
.rd-fp__filters {
	display: flex;
	gap: $rd-space-2;
	margin-bottom: $rd-space-2;
}

.rd-fp__search {
	flex: 1;
}

.rd-fp__search-input, .rd-fp__select {
	width: 100%;
	padding: 8px 12px;
	border: 1px solid $rd-border;
	border-radius: $rd-radius-sm;
	font-size: $rd-font-sm;
	outline: none;
	transition: border-color 0.2s, box-shadow 0.2s;
	box-sizing: border-box;
	background: $rd-bg-page;
	color: $rd-text;

	&:focus {
		border-color: $rd-primary;
		box-shadow: 0 0 0 3px rgba(0, 130, 201, 0.15);
	}
}

.rd-fp__date-filter {
	flex: 0 0 160px;
}

/* â”€â”€ "Use This Folder" section â”€â”€ */
.rd-fp__use-section {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: $rd-space-3;
	padding: $rd-space-3;
	border: 2px solid $rd-primary;
	border-radius: $rd-radius-md;
	background: $rd-primary-soft;
	margin-bottom: $rd-space-2;
	transition: background 0.15s;

	&--chosen {
		background: $rd-primary-soft;
		border-color: $rd-primary;
	}
}

.rd-fp__use-info {
	display: flex;
	align-items: center;
	gap: $rd-space-2;
	min-width: 0;
	flex: 1;
}

.rd-fp__use-icon {
	color: $rd-primary;
	flex-shrink: 0;
}

.rd-fp__use-text {
	display: flex;
	flex-direction: column;
	min-width: 0;
}

.rd-fp__use-label {
	font-size: $rd-font-xs;
	color: $rd-text-muted;
	text-transform: uppercase;
	letter-spacing: 0.04em;
	font-weight: 600;
}

.rd-fp__use-path {
	font-size: $rd-font-sm;
	font-weight: 700;
	color: $rd-primary;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.rd-fp__use-btn {
	flex-shrink: 0;
}

.rd-fp__use-chosen-badge {
	display: flex;
	align-items: center;
	gap: $rd-space-1;
	font-size: $rd-font-sm;
	font-weight: 600;
	color: $rd-primary;
	flex-shrink: 0;
	padding: $rd-space-1 $rd-space-2;
}

/* â”€â”€ Subfolder section header â”€â”€ */
.rd-fp__subfolder-hdr {
	font-size: $rd-font-xs;
	color: $rd-text-muted;
	text-transform: uppercase;
	letter-spacing: 0.06em;
	font-weight: 700;
	margin: 0 0 $rd-space-1;
}

/* â”€â”€ Scrollable folder list â”€â”€ */
.rd-fp__scroll {
	border: 1px solid $rd-border;
	border-radius: $rd-radius-md;
	overflow-y: auto;
	min-height: 120px;
	max-height: 260px;
	margin-bottom: $rd-space-2;
}

/* â”€â”€ State messages â”€â”€ */
.rd-fp__state {
	display: flex;
	align-items: center;
	gap: $rd-space-2;
	padding: $rd-space-3 $rd-space-4;
	font-size: $rd-font-sm;

	&--error { color: var(--color-error, #e9322d); }

	&--empty {
		color: $rd-text-muted;
		justify-content: center;
		flex-direction: column;
		text-align: center;
		padding: $rd-space-4;
		gap: $rd-space-1;
	}

	&--empty-sub { gap: $rd-space-1; }
}

.rd-fp__validation-error {
	background-color: #fef2f2;
	border: 1px solid #f87171;
	border-radius: $rd-radius-md;
	padding: $rd-space-3 $rd-space-4;
	margin-bottom: $rd-space-3;
	color: #b91c1c;
	font-size: $rd-font-sm;

	&-title {
		display: flex;
		align-items: center;
		gap: $rd-space-2;
		font-weight: 600;
		font-size: $rd-font-md;
		margin-bottom: $rd-space-2;
	}

	p {
		margin: 0 0 $rd-space-1;
	}

	ul {
		margin: $rd-space-1 0 0;
		padding-left: $rd-space-4;

		li {
			list-style-type: disc;
		}
	}
}

.rd-fp__empty-icon { opacity: 0.4; }
.rd-fp__empty-hint { font-size: $rd-font-xs; font-style: italic; }

/* â”€â”€ Folder row â”€â”€ */
.rd-fp__item {
	display: flex;
	align-items: center;
	gap: $rd-space-2;
	padding: $rd-space-2 $rd-space-3;
	cursor: pointer;
	border-bottom: 1px solid $rd-border;
	transition: background 0.1s;
	user-select: none;
	outline: none;

	&:last-child { border-bottom: none; }
	&:hover { background: $rd-primary-soft; }

	&--sel {
		background: $rd-primary-soft !important;
		.rd-fp__item-name { font-weight: 600; color: $rd-primary; }
	}

	&--back {
		.rd-fp__item-name { font-style: italic; color: $rd-text-muted; }
	}

	&--disabled {
		.rd-fp__item-name { color: $rd-text-muted; }
		.rd-fp__item-radio { border-color: #e5e7eb; background: #f3f4f6; }
		cursor: default;
		&:hover { background: transparent; }
		.rd-fp__item-open { cursor: pointer; pointer-events: auto; }
	}

	&:focus-visible { outline: 2px solid $rd-primary; outline-offset: -2px; }

	/* Radio/Checkbox indicator */
	&-radio {
		width: 18px;
		height: 18px;
		border-radius: 50%;
		border: 2px solid $rd-border;
		flex-shrink: 0;
		display: flex;
		align-items: center;
		justify-content: center;
		transition: border-color 0.15s, background 0.15s;

		&--multi {
			border-radius: 4px;
		}

		.rd-fp__item--sel & {
			border-color: $rd-primary;
			background: $rd-primary;
		}
	}

	&-radio-dot {
		width: 7px;
		height: 7px;
		border-radius: 50%;
		background: white;
		opacity: 0;
		transition: opacity 0.15s;

		.rd-fp__item--sel & { opacity: 1; }
	}

	&-check {
		width: 12px;
		height: 12px;
		color: white;
		opacity: 0;
		transition: opacity 0.15s;

		.rd-fp__item--sel & { opacity: 1; }
	}

	&-icon { color: $rd-text-muted; flex-shrink: 0; &--sel { color: $rd-primary; } }

	&-name {
		flex: 1;
		font-size: $rd-font-md;
		color: $rd-text;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
		display: flex;
		align-items: center;
		gap: 8px;
	}

	&-badge {
		font-size: 11px;
		color: #9ca3af;
		font-style: italic;
		background: #f3f4f6;
		padding: 2px 6px;
		border-radius: 4px;
	}

	/* Explicit "Open" navigation button */
	&-open {
		display: inline-flex;
		align-items: center;
		gap: 3px;
		padding: 3px 10px;
		border: 1px solid $rd-border;
		border-radius: $rd-radius-sm;
		background: none;
		font-size: $rd-font-xs;
		color: $rd-text-muted;
		cursor: pointer;
		flex-shrink: 0;
		white-space: nowrap;
		transition: color 0.12s, border-color 0.12s, background 0.12s;

		&:hover {
			color: $rd-primary;
			border-color: $rd-primary;
			background: $rd-primary-soft;
		}
		&:focus-visible {
			outline: 2px solid $rd-primary;
			outline-offset: 1px;
		}
	}
}

/* â”€â”€ Skeleton rows â”€â”€ */
.rd-fp__skel-row {
	display: flex;
	align-items: center;
	gap: $rd-space-2;
	padding: $rd-space-2 $rd-space-3;
	border-bottom: 1px solid $rd-border;
	&:last-child { border-bottom: none; }
}

.rd-fp__skel-icon {
	width: 20px; height: 20px; flex-shrink: 0;
	border-radius: $rd-radius-sm;
	background: linear-gradient(90deg, #e5e7eb 25%, #eff0f2 50%, #e5e7eb 75%);
	background-size: 200% 100%;
	animation: rd-fp-shimmer 1.4s infinite;
}

.rd-fp__skel-text {
	height: 14px;
	border-radius: $rd-radius-sm;
	background: linear-gradient(90deg, #e5e7eb 25%, #eff0f2 50%, #e5e7eb 75%);
	background-size: 200% 100%;
	animation: rd-fp-shimmer 1.4s infinite 0.1s;
}

@keyframes rd-fp-shimmer {
	0%   { background-position: 200% 0; }
	100% { background-position: -200% 0; }
}

/* â”€â”€ Selection preview â”€â”€ */
.rd-fp__preview {
	display: flex;
	align-items: center;
	gap: $rd-space-2;
	padding: $rd-space-2 $rd-space-3;
	border-radius: $rd-radius-sm;
	font-size: $rd-font-sm;
	border: 1px solid $rd-border;
	min-height: 40px;
	transition: border-color 0.15s, background 0.15s;
	background: $rd-bg-page;

	&--active { border-color: $rd-primary; background: $rd-primary-soft; }

	&-icon { color: $rd-primary; flex-shrink: 0; }
	&-label { color: $rd-text-muted; white-space: nowrap; }
	&-path { color: $rd-primary; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
	&-none { color: $rd-text-muted; font-style: italic; }
}
</style>

<style>
/* Force Light Theme Overrides on NcDialog via Global Attribute */
body[data-hugo-theme="light"] .modal-wrapper .modal-container {
	background-color: #ffffff !important;
	color: #111827 !important;
	color-scheme: light !important;
	--color-main-background: #ffffff !important;
	--color-main-text: #111827 !important;
	--color-primary: #0082c9 !important;
	--color-primary-element: #0082c9 !important;
	--color-primary-text: #ffffff !important;
	--color-border: #e5e7eb !important;
	--color-border-dark: #d1d5db !important;
	--color-background-hover: #f3f4f6 !important;
	--color-background-dark: #f9fafb !important;
}
body[data-hugo-theme="light"] .modal-wrapper .modal-container .modal-header {
	background-color: #ffffff !important;
	color: #111827 !important;
	border-bottom-color: #e5e7eb !important;
}
body[data-hugo-theme="light"] .modal-wrapper .modal-container .modal-header h2,
body[data-hugo-theme="light"] .modal-wrapper .modal-container .modal-header .modal-title {
	color: #111827 !important;
}
body[data-hugo-theme="light"] .modal-wrapper .modal-container button.modal-header__close {
	filter: none !important;
}
body[data-hugo-theme="light"] .rd-fp__crumb { background: #f9fafb !important; border-color: #e5e7eb !important; }
body[data-hugo-theme="light"] .rd-fp__crumb-seg { color: #0082c9 !important; background-color: transparent !important; border: none !important; box-shadow: none !important; }
body[data-hugo-theme="light"] .rd-fp__crumb-seg--last { color: #111827 !important; }
body[data-hugo-theme="light"] .rd-fp__search-input, body[data-hugo-theme="light"] .rd-fp__select { background: #ffffff !important; border-color: #d1d5db !important; color: #111827 !important; }
body[data-hugo-theme="light"] .rd-fp__search-input:focus, body[data-hugo-theme="light"] .rd-fp__select:focus { outline: none !important; border-color: #111827 !important; box-shadow: 0 0 0 2px rgba(17, 24, 39, 0.1) !important; }
body[data-hugo-theme="light"]:not(.using-mouse) .rd-fp__search-input:focus, body[data-hugo-theme="light"]:not(.using-mouse) .rd-fp__select:focus { outline: 2px solid #111827 !important; outline-offset: 0px !important; box-shadow: none !important; }
body[data-hugo-theme="light"] .rd-fp__item-name { color: #111827 !important; }
body[data-hugo-theme="light"] .rd-fp__search-input::placeholder { color: #9ca3af !important; }
body[data-hugo-theme="light"] .rd-fp__item-badge { background-color: #f3f4f6 !important; color: #9ca3af !important; }
body[data-hugo-theme="light"] .rd-fp__item--disabled .rd-fp__item-name { color: #9ca3af !important; }
body[data-hugo-theme="light"] .rd-fp__item--disabled .rd-fp__item-radio { background-color: #f3f4f6 !important; border-color: #e5e7eb !important; }
body[data-hugo-theme="light"] .rd-fp__item-icon { color: #6b7280 !important; }
body[data-hugo-theme="light"] .rd-fp__item-icon--sel { color: #0082c9 !important; }
body[data-hugo-theme="light"] .rd-fp__item:hover { background: #f3f4f6 !important; }
body[data-hugo-theme="light"] .rd-fp__item--sel { background: #e6f3fb !important; }
body[data-hugo-theme="light"] .rd-fp__item-radio { border-color: #d1d5db !important; }
body[data-hugo-theme="light"] .rd-fp__item--sel .rd-fp__item-radio { border-color: #0082c9 !important; background: #0082c9 !important; }
body[data-hugo-theme="light"] .rd-fp__item-open { color: #6b7280 !important; border-color: #e5e7eb !important; }
body[data-hugo-theme="light"] .rd-fp__item-open:hover { color: #0082c9 !important; border-color: #0082c9 !important; background: #e6f3fb !important; }
body[data-hugo-theme="light"] .rd-fp__item-open:focus-visible { background-color: transparent !important; outline: 2px solid #111827 !important; outline-offset: 0px !important; box-shadow: none !important; }
body[data-hugo-theme="light"] .rd-fp__scroll { border-color: #e5e7eb !important; }
body[data-hugo-theme="light"] .rd-fp__use-section { background: #e6f3fb !important; border-color: #0082c9 !important; }
body[data-hugo-theme="light"] .rd-fp__use-label { color: #6b7280 !important; }
body[data-hugo-theme="light"] .rd-fp__use-path { color: #0082c9 !important; }
body[data-hugo-theme="light"] .rd-fp__preview { background: #f9fafb !important; border-color: #e5e7eb !important; }
body[data-hugo-theme="light"] .rd-fp__preview-label { color: #6b7280 !important; }

/* Force Nextcloud Buttons explicitly in Light Mode */
body[data-hugo-theme="light"] .modal-wrapper .modal-container .button-vue {
	background-color: #ffffff !important;
	color: #111827 !important;
	border-color: #d1d5db !important;
}
body[data-hugo-theme="light"] .modal-wrapper .modal-container .button-vue:hover:not(:disabled) {
	background-color: #f3f4f6 !important;
}
body[data-hugo-theme="light"] .modal-wrapper .modal-container .button-vue.button-vue--vue-primary {
	background-color: #0082c9 !important;
	color: #ffffff !important;
	border-color: #0082c9 !important;
}
body[data-hugo-theme="light"] .modal-wrapper .modal-container .button-vue.button-vue--vue-primary:hover:not(:disabled) {
	background-color: #006bb3 !important;
}
body[data-hugo-theme="light"] .modal-wrapper .modal-container .button-vue:disabled {
	background-color: #f9fafb !important;
	color: #9ca3af !important;
	border-color: #e5e7eb !important;
	opacity: 0.6 !important;
}
/* Ensure the custom checkbox isn't overridden by NC dark mode */
body[data-hugo-theme="light"] .modal-wrapper .modal-container .rd-fp__item-radio {
	background-color: #ffffff !important;
	border-color: #d1d5db !important;
}
body[data-hugo-theme="light"] .modal-wrapper .modal-container .rd-fp__item--sel .rd-fp__item-radio {
	background-color: #0082c9 !important;
	border-color: #0082c9 !important;
}

/* Force Dark Theme Overrides on NcDialog via Global Attribute */
body[data-hugo-theme="dark"] .modal-wrapper .modal-container {
	background-color: #111827 !important;
	color: #f9fafb !important;
	color-scheme: dark !important;
	--color-main-background: #111827 !important;
	--color-main-text: #f9fafb !important;
	--color-primary: #38bdf8 !important;
	--color-primary-element: #38bdf8 !important;
	--color-primary-text: #111827 !important;
	--color-border: #374151 !important;
	--color-border-dark: #4b5563 !important;
	--color-background-hover: #1f2937 !important;
	--color-background-dark: #111827 !important;
}
body[data-hugo-theme="dark"] .modal-wrapper .modal-container p {
	color: #d1d5db !important;
}
body[data-hugo-theme="dark"] .modal-wrapper .modal-container .modal-header {
	background-color: #111827 !important;
	color: #f9fafb !important;
	border-bottom-color: #374151 !important;
}
body[data-hugo-theme="dark"] .modal-wrapper .modal-container .modal-header h2,
body[data-hugo-theme="dark"] .modal-wrapper .modal-container .modal-header .modal-title {
	color: #f9fafb !important;
}
body[data-hugo-theme="dark"] .modal-wrapper .modal-container button.modal-header__close {
	filter: invert(1) !important;
}
body[data-hugo-theme="dark"] .rd-fp__crumb { background: #1f2937 !important; border-color: #374151 !important; }
body[data-hugo-theme="dark"] .rd-fp__crumb-seg { color: #38bdf8 !important; background-color: transparent !important; border: none !important; box-shadow: none !important; }
body[data-hugo-theme="dark"] .rd-fp__crumb-seg--last { color: #f9fafb !important; }
body[data-hugo-theme="dark"] .rd-fp__crumb-arrow { color: #9ca3af !important; }
body[data-hugo-theme="dark"] .rd-fp__search-input, body[data-hugo-theme="dark"] .rd-fp__select { background: #111827 !important; border-color: #374151 !important; color: #f9fafb !important; }
body[data-hugo-theme="dark"] .rd-fp__search-input:focus, body[data-hugo-theme="dark"] .rd-fp__select:focus { outline: none !important; border-color: #f9fafb !important; box-shadow: 0 0 0 2px rgba(249, 250, 251, 0.1) !important; }
body[data-hugo-theme="dark"]:not(.using-mouse) .rd-fp__search-input:focus, body[data-hugo-theme="dark"]:not(.using-mouse) .rd-fp__select:focus { outline: 2px solid #f9fafb !important; outline-offset: 0px !important; box-shadow: none !important; }
body[data-hugo-theme="dark"] .rd-fp__search-input::placeholder { color: #9ca3af !important; }
body[data-hugo-theme="dark"] .rd-fp__item { border-bottom-color: #374151 !important; }
body[data-hugo-theme="dark"] .rd-fp__item-name { color: #f9fafb !important; }
body[data-hugo-theme="dark"] .rd-fp__item-badge { background-color: #374151 !important; color: #d1d5db !important; }
body[data-hugo-theme="dark"] .rd-fp__item--disabled .rd-fp__item-name { color: #9ca3af !important; }
body[data-hugo-theme="dark"] .rd-fp__item--disabled .rd-fp__item-radio { background-color: #374151 !important; border-color: #4b5563 !important; }
body[data-hugo-theme="dark"] .rd-fp__item-icon { color: #9ca3af !important; }
body[data-hugo-theme="dark"] .rd-fp__item-icon--sel { color: #38bdf8 !important; }
body[data-hugo-theme="dark"] .rd-fp__item:hover { background: #374151 !important; }
body[data-hugo-theme="dark"] .rd-fp__item--sel { background: #1e3a8a !important; }
body[data-hugo-theme="dark"] .rd-fp__item-radio { border-color: #4b5563 !important; }
body[data-hugo-theme="dark"] .rd-fp__item--sel .rd-fp__item-radio { border-color: #38bdf8 !important; background: #38bdf8 !important; }
body[data-hugo-theme="dark"] .rd-fp__item-open { color: #9ca3af !important; border-color: #374151 !important; }
body[data-hugo-theme="dark"] .rd-fp__item-open:hover { color: #38bdf8 !important; border-color: #38bdf8 !important; background: #1e3a8a !important; }
body[data-hugo-theme="dark"] .rd-fp__item-open:focus-visible { background-color: transparent !important; outline: 2px solid #f9fafb !important; outline-offset: 0px !important; box-shadow: none !important; }
body[data-hugo-theme="dark"] .rd-fp__scroll { border-color: #374151 !important; }
body[data-hugo-theme="dark"] .rd-fp__use-section { background: #1f2937 !important; border-color: #374151 !important; }
body[data-hugo-theme="dark"] .rd-fp__use-section--chosen { border-color: #38bdf8 !important; background: #1e3a8a !important; }
body[data-hugo-theme="dark"] .rd-fp__use-label { color: #9ca3af !important; }
body[data-hugo-theme="dark"] .rd-fp__use-path { color: #38bdf8 !important; }
body[data-hugo-theme="dark"] .rd-fp__subfolder-hdr { color: #9ca3af !important; }
body[data-hugo-theme="dark"] .rd-fp__preview { background: #1f2937 !important; border-color: #374151 !important; }
body[data-hugo-theme="dark"] .rd-fp__preview-label { color: #9ca3af !important; }
body[data-hugo-theme="dark"] .rd-fp__preview-path { color: #38bdf8 !important; }
body[data-hugo-theme="dark"] .rd-fp__preview-icon { color: #38bdf8 !important; }

/* Force Nextcloud Buttons explicitly in Dark Mode */
body[data-hugo-theme="dark"] .modal-wrapper .modal-container .button-vue {
	background-color: #1f2937 !important;
	color: #f9fafb !important;
	border-color: #374151 !important;
}
body[data-hugo-theme="dark"] .modal-wrapper .modal-container .button-vue:hover:not(:disabled) {
	background-color: #374151 !important;
}
body[data-hugo-theme="dark"] .modal-wrapper .modal-container .button-vue.button-vue--vue-primary {
	background-color: #38bdf8 !important;
	color: #111827 !important;
	border-color: #38bdf8 !important;
}
body[data-hugo-theme="dark"] .modal-wrapper .modal-container .button-vue.button-vue--vue-primary:hover:not(:disabled) {
	background-color: #0ea5e9 !important;
}
body[data-hugo-theme="dark"] .modal-wrapper .modal-container .button-vue:disabled {
	background-color: #374151 !important;
	color: #9ca3af !important;
	border-color: #4b5563 !important;
	opacity: 0.6 !important;
}
/* Ensure the custom checkbox isn't overridden by NC light mode */
body[data-hugo-theme="dark"] .modal-wrapper .modal-container .rd-fp__item-radio {
	background-color: #111827 !important;
	border-color: #4b5563 !important;
}
body[data-hugo-theme="dark"] .modal-wrapper .modal-container .rd-fp__item--sel .rd-fp__item-radio {
	background-color: #38bdf8 !important;
	border-color: #38bdf8 !important;
}

</style>
