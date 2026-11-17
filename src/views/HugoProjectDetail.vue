<template>
	<div class="hugo-project-detail" :class="{ 'theme-dark': isDarkMode, 'theme-light': !isDarkMode }">
		<header class="page-header">
			<button class="back-btn" @click="goBack">← Back</button>
			<h2>{{ isNew ? 'Add Hugo Project' : 'Project Details' }}</h2>
		</header>

		<div class="content-body">
			<form @submit.prevent="saveProject" class="project-form">
				<div class="form-group" v-if="!isMultiSelect">
					<label>Project Name</label>
					<input v-if="isEditing" type="text" v-model="project.name" required maxlength="64" placeholder="e.g. My Website" />
					<div v-else class="readonly-text">{{ project.name }}</div>
				</div>
				<div class="form-group" v-else>
					<label>Multiple Projects Selected</label>
					<div class="readonly-text">{{ selectedNewFolders.length }} projects will be created using their folder names.</div>
				</div>

				<div class="form-group">
					<label>Nextcloud Folder Path</label>
					<div v-if="isEditing" class="folder-picker-input">
						<input type="text" :value="folderInputDisplay" required readonly placeholder="Click Browse to choose a folder" @click="showFolderPicker = true" />
						<button type="button" class="btn" @click="showFolderPicker = true">Browse...</button>
					</div>
					<div v-else class="readonly-text">{{ project.folder }}</div>
				</div>

				<div class="form-actions">
					<button type="button" class="btn edit-btn" v-if="!isNew && !isEditing" @click="isEditing = true">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 5px;">
							<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
							<path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
						</svg>
						Edit
					</button>
					<button type="button" class="btn" v-if="isEditing" @click="cancelEdit">
						Cancel
					</button>
					<button type="submit" class="primary-btn" v-if="isEditing" :disabled="saving">
						{{ saving ? 'Saving...' : 'Save' }}
					</button>
					<button type="button" class="primary-btn preview-btn" v-if="!isNew && !isEditing" @click="previewProject">
						Preview
					</button>
				</div>
			</form>
		</div>

		<FolderPickerDialog
			:open="showFolderPicker"
			:initialPath="project.folder"
			:multiSelect="isNew"
			:disabledPaths="disabledPaths"
			@update:open="showFolderPicker = $event"
			@select="onFolderSelected"
		/>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'
import FolderPickerDialog from '../components/FolderPickerDialog.vue'

export default {
	name: 'HugoProjectDetail',
	components: {
		FolderPickerDialog
	},
	props: ['id'],
	data() {
		return {
			project: {
				name: '',
				folder: ''
			},
			loading: false,
			saving: false,
			showFolderPicker: false,
			isEditing: false,
			selectedNewFolders: [],
			existingProjects: [],
			theme: localStorage.getItem('hugo-theme') || 'auto',
			systemIsDark: window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
		}
	},
	computed: {
		isDarkMode() {
			if (this.theme === 'auto') return this.systemIsDark;
			return this.theme === 'dark';
		},
		isNew() {
			return this.id === 'new'
		},
		isMultiSelect() {
			return this.isNew && this.selectedNewFolders.length > 1;
		},
		folderInputDisplay() {
			if (this.isMultiSelect) {
				return this.selectedNewFolders.map(f => f.name).join(', ');
			}
			return this.project.folder;
		},
		disabledPaths() {
			return this.existingProjects
				.map(p => p.folder ? p.folder.replace(/^\/+/, '') : '')
				.filter(f => f);
		}
	},
	mounted() {
		if (this.isNew) {
			this.isEditing = true
		} else {
			this.fetchProject()
		}
		this.fetchAllProjects()
		if (window.matchMedia) {
			this.mediaQueryListener = (e) => {
				this.systemIsDark = e.matches;
			};
			window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', this.mediaQueryListener);
			this.mouseListener = () => { document.body.classList.add('using-mouse'); };
			this.keyListener = (e) => { if (e.key === 'Tab') document.body.classList.remove('using-mouse'); };
			window.addEventListener('mousedown', this.mouseListener);
			window.addEventListener('keydown', this.keyListener);
		}
	},
	beforeUnmount() {
		if (window.matchMedia && this.mediaQueryListener) {
			window.matchMedia('(prefers-color-scheme: dark)').removeEventListener('change', this.mediaQueryListener);
		}
		if (this.mouseListener) window.removeEventListener('mousedown', this.mouseListener);
		if (this.keyListener) window.removeEventListener('keydown', this.keyListener);
	},
	methods: {
		onFolderSelected(selection) {
			if (Array.isArray(selection)) {
				this.selectedNewFolders = selection;
				if (selection.length === 1) {
					this.project.folder = selection[0].path;
					if (!this.project.name || (this._prevSingleFolderName && this.project.name === this._prevSingleFolderName)) {
						this.project.name = selection[0].name;
						this._prevSingleFolderName = selection[0].name;
					}
				} else if (selection.length > 1) {
					this.project.folder = selection.map(s => s.path).join(', ');
				} else {
					this.project.folder = '';
				}
			} else {
				this.project.folder = selection.path;
				if (this.isNew && (!this.project.name || (this._prevSingleFolderName && this.project.name === this._prevSingleFolderName))) {
					this.project.name = selection.name;
					this._prevSingleFolderName = selection.name;
				}
			}
		},
		async fetchProject() {
			this.loading = true
			try {
				const response = await axios.get(generateUrl(`/apps/hugopreview_nc_app/api/projects/${this.id}`))
				this.project = response.data
			} catch (err) {
				console.error('Failed to load project details', err)
				showError('Failed to load project details.', { timeout: 3000 })
			} finally {
				this.loading = false
			}
		},
		async fetchAllProjects() {
			try {
				const response = await axios.get(generateUrl('/apps/hugopreview_nc_app/api/projects'))
				this.existingProjects = response.data
			} catch (err) {
				console.error('Failed to load all projects', err)
				showError('Failed to load available folders.', { timeout: 3000 })
			}
		},
		async saveProject() {
			this.saving = true
			try {
				if (this.isMultiSelect) {
					// Bulk create: send sequentially to prevent race condition on the backend JSON store
					for (const folder of this.selectedNewFolders) {
						await axios.post(generateUrl('/apps/hugopreview_nc_app/api/projects'), {
							name: folder.name,
							folder: '/' + folder.path
						});
					}
				} else {
					// Single create / edit
					const payload = {
						name: this.project.name,
						folder: this.project.folder
					}
					if (!this.isNew) {
						payload.id = this.id
					}
					await axios.post(generateUrl('/apps/hugopreview_nc_app/api/projects'), payload)
				}

				if (this.isNew) {
					showSuccess('Project created successfully!', { timeout: 3000 })
					this.$router.push('/projects')
				} else {
					showSuccess('Project saved successfully!', { timeout: 3000 })
					this.isEditing = false
				}
			} catch (err) {
				console.error('Failed to save project(s)', err)
				showError(err.response?.data?.error || 'Failed to save project. Please check your inputs.', { timeout: 3000 })
			} finally {
				this.saving = false
			}
		},
		previewProject() {
			this.$router.push(`/preview/${this.id}`)
		},
		cancelEdit() {
			if (this.isNew) {
				this.$router.push('/projects')
			} else {
				this.isEditing = false
				this.fetchProject() // Revert any unsaved changes
			}
		},
		goBack() {
			this.$router.push('/projects')
		}
	}
}
</script>

<style scoped>
.hugo-project-detail { padding: 40px; background-color: #f9fafb; height: calc(100vh - 60px); overflow-y: auto; box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; display: flex; flex-direction: column; align-items: center; }
.page-header { width: 100%; max-width: 600px; display: flex; align-items: center; margin-bottom: 24px; }
.page-header h2 { margin: 0; margin-left: 16px; font-size: 20px; font-weight: 600; color: #111827; }
.back-btn { background: #ffffff; border: 1px solid #d1d5db; font-size: 14px; font-weight: 500; cursor: pointer; color: #374151; padding: 6px 12px; border-radius: 6px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: background 0.2s; }
.back-btn:hover { background: #f3f4f6; }

.content-body { width: 100%; max-width: 600px; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 32px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); }

.project-form { width: 100%; }

.form-group { margin-bottom: 24px; }
.form-group label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px; color: #374151; }
.form-group input { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; color: #111827; transition: border-color 0.2s, box-shadow 0.2s; box-sizing: border-box; }
.form-group input:focus { outline: none; border-color: #0082c9; box-shadow: 0 0 0 3px rgba(0, 130, 201, 0.15); }

.folder-picker-input { display: flex; gap: 8px; }
.folder-picker-input input { flex: 1; cursor: pointer; background-color: #f9fafb; }
.readonly-text { padding: 10px 12px; font-size: 15px; color: #111827; background: #f9fafb; border-radius: 6px; border: 1px solid transparent; }

/* Buttons */
.btn { padding: 8px 16px; border-radius: 6px; font-weight: 500; font-size: 14px; cursor: pointer; border: 1px solid #d1d5db; background: #ffffff; color: #374151; transition: all 0.2s ease; box-shadow: 0 1px 2px rgba(0,0,0,0.05); display: inline-flex; align-items: center; justify-content: center; }
.btn:hover { background: #f3f4f6; }
.edit-btn { background: #ffffff; }
.form-actions { margin-top: 32px; padding-top: 24px; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 12px; }

.primary-btn { background: #0082c9; color: white; border: 1px solid #0073b1; padding: 8px 16px; border-radius: 6px; font-weight: 500; font-size: 14px; cursor: pointer; box-shadow: 0 1px 2px rgba(0, 130, 201, 0.2); transition: background 0.2s; }
.primary-btn:hover:not(:disabled) { background: #0073b1; }
.primary-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.preview-btn { background: #10b981; border-color: #059669; box-shadow: 0 1px 2px rgba(16, 185, 129, 0.2); }
.preview-btn:hover { background: #059669; }

.hugo-project-detail.theme-light { color-scheme: light !important; }
.hugo-project-detail.theme-light .back-btn { background: #ffffff !important; color: #374151 !important; border-color: #d1d5db !important; }
.hugo-project-detail.theme-light .back-btn:hover { background: #f3f4f6 !important; color: #111827 !important; }
.hugo-project-detail.theme-light input[type="text"] { background: #ffffff !important; color: #111827 !important; border-color: #d1d5db !important; }
.hugo-project-detail.theme-light input[type="text"]:focus { outline: none !important; border-color: #111827 !important; box-shadow: 0 0 0 2px rgba(17, 24, 39, 0.1) !important; }
body:not(.using-mouse) .hugo-project-detail.theme-light input[type="text"]:focus { outline: 2px solid #111827 !important; outline-offset: 0px !important; box-shadow: none !important; }
.hugo-project-detail.theme-light input[type="text"]::placeholder { color: #9ca3af !important; }
.hugo-project-detail.theme-light .readonly-text { background: #f9fafb !important; color: #111827 !important; border-color: transparent !important; }
.hugo-project-detail.theme-light .btn:not(.primary-btn):not(.preview-btn):not(.back-btn) { background: #ffffff !important; color: #374151 !important; border-color: #d1d5db !important; }
.hugo-project-detail.theme-light .btn:not(.primary-btn):not(.preview-btn):not(.back-btn):hover { background: #f3f4f6 !important; }
.hugo-project-detail.theme-light .primary-btn { background: #0082c9 !important; color: white !important; border-color: #0073b1 !important; }
.hugo-project-detail.theme-light .primary-btn:hover:not(:disabled) { background: #0073b1 !important; }
.hugo-project-detail.theme-light .primary-btn:focus { outline: none !important; }
body:not(.using-mouse) .hugo-project-detail.theme-light .primary-btn:focus { outline: 2px solid #111827 !important; outline-offset: 0px !important; background: #0082c9 !important; border-color: #0073b1 !important; box-shadow: none !important; }
body:not(.using-mouse) .hugo-project-detail.theme-light .preview-btn:focus { background: #10b981 !important; border-color: #059669 !important; outline: 2px solid #111827 !important; outline-offset: 0px !important; box-shadow: none !important; }
body:not(.using-mouse) .hugo-project-detail.theme-light .btn:not(.primary-btn):focus { outline: 2px solid #111827 !important; outline-offset: 0px !important; box-shadow: none !important; }
.hugo-project-detail.theme-light .back-btn:focus { outline: none !important; }
body:not(.using-mouse) .hugo-project-detail.theme-light .back-btn:focus { outline: 2px solid #111827 !important; outline-offset: 0px !important; box-shadow: none !important; }

/* Dark Theme */
.hugo-project-detail.theme-dark { background-color: #111827; color-scheme: dark !important; }
.hugo-project-detail.theme-dark .page-header h2 { color: #f9fafb; }
.hugo-project-detail.theme-dark .back-btn { background: #1f2937; border-color: #374151; color: #d1d5db; }
.hugo-project-detail.theme-dark .back-btn:hover { background: #374151; }

.hugo-project-detail.theme-dark .content-body { background: #1f2937; border-color: #374151; }
.hugo-project-detail.theme-dark .form-group label { color: #d1d5db; }
.hugo-project-detail.theme-dark .form-group input { background: #111827; border-color: #374151; color: #f9fafb; }
.hugo-project-detail.theme-dark .form-group input::placeholder { color: #6b7280 !important; }
.hugo-project-detail.theme-dark .form-group input:focus { outline: none !important; border-color: #f9fafb !important; box-shadow: 0 0 0 2px rgba(249, 250, 251, 0.1) !important; }
body:not(.using-mouse) .hugo-project-detail.theme-dark .form-group input:focus { outline: 2px solid #f9fafb !important; outline-offset: 0px !important; box-shadow: none !important; }

.hugo-project-detail.theme-dark .folder-picker-input input { background: #111827; }
.hugo-project-detail.theme-dark .readonly-text { background: #111827; color: #f9fafb; }

.hugo-project-detail.theme-dark .btn:not(.primary-btn):not(.preview-btn):not(.back-btn) { background: #1f2937; border-color: #374151; color: #d1d5db; }
.hugo-project-detail.theme-dark .btn:not(.primary-btn):not(.preview-btn):not(.back-btn):hover { background: #374151; }
.hugo-project-detail.theme-dark .edit-btn { background: #1f2937; }
.hugo-project-detail.theme-dark .form-actions { border-color: #374151; }

.hugo-project-detail.theme-dark .primary-btn { background: #0082c9 !important; border-color: #0073b1 !important; color: white !important; }
.hugo-project-detail.theme-dark .primary-btn:hover:not(:disabled) { background: #0073b1 !important; }
.hugo-project-detail.theme-dark .primary-btn:focus { outline: none !important; }
body:not(.using-mouse) .hugo-project-detail.theme-dark .primary-btn:focus { outline: 2px solid #f9fafb !important; outline-offset: 0px !important; background: #0082c9 !important; border-color: #0073b1 !important; box-shadow: none !important; }
.hugo-project-detail.theme-dark .preview-btn { background: #10b981 !important; border-color: #059669 !important; }
.hugo-project-detail.theme-dark .preview-btn:hover { background: #059669 !important; }
body:not(.using-mouse) .hugo-project-detail.theme-dark .preview-btn:focus { background: #10b981 !important; border-color: #059669 !important; outline: 2px solid #f9fafb !important; outline-offset: 0px !important; box-shadow: none !important; }
body:not(.using-mouse) .hugo-project-detail.theme-dark .btn:not(.primary-btn):focus { outline: 2px solid #f9fafb !important; outline-offset: 0px !important; box-shadow: none !important; }
.hugo-project-detail.theme-dark .back-btn:focus { outline: none !important; }
body:not(.using-mouse) .hugo-project-detail.theme-dark .back-btn:focus { outline: 2px solid #f9fafb !important; outline-offset: 0px !important; box-shadow: none !important; }
</style>
