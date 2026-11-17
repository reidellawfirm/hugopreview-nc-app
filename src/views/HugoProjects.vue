<template>
	<div class="hugo-projects" :class="{ 'theme-dark': isDarkMode, 'theme-light': !isDarkMode }">
		<header class="page-header">
			<div class="header-left">
				<h2>Hugo Projects</h2>
				<button class="icon-btn theme-toggle-btn" @click="toggleTheme" :title="themeTooltip">
					<!-- Auto (System) -->
					<svg v-if="theme === 'auto'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
					<!-- Light -->
					<svg v-else-if="theme === 'light'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
					<!-- Dark -->
					<svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
				</button>
			</div>
			<div class="header-right">
				<label class="select-all-label" v-if="projects.length > 0">
					<input type="checkbox" :checked="isAllSelected" @change="toggleSelectAll" class="select-all-checkbox" />
					Select All
				</label>
				<div class="search-bar">
					<input type="text" v-model="searchQuery" placeholder="Search projects..." class="search-input" />
				</div>
				<select v-model="filterOption" class="filter-select">
					<option value="all">All Projects</option>
					<option value="recent">Recently Added</option>
					<option value="today">Today</option>
					<option value="week">This Week</option>
					<option value="month">This Month</option>
				</select>
				<button class="primary-btn" @click="addProject">Add Another Project</button>
			</div>
		</header>

		<div class="content-body">
			<div v-if="loading" class="hugo-loading">Loading projects...</div>

			<div v-else-if="projects.length === 0" class="empty-state">
				<div class="empty-icon">📂</div>
				<h3>No Hugo Projects Found</h3>
				<p>Click "Add Another Project" to add your first Hugo project.</p>
			</div>

			<div v-else-if="filteredProjects.length === 0" class="empty-state">
				<div class="empty-icon">🔍</div>
				<h3>No projects match your search</h3>
			</div>

			<div v-else class="project-grid">
				<div v-for="project in filteredProjects" :key="project.id" class="project-card" :class="{ 'selected-card': selectedProjects.includes(project.id) }">
					<div class="card-header">
						<div class="card-header-top">
							<input type="checkbox" :value="project.id" v-model="selectedProjects" class="project-checkbox" />
							<h3>{{ project.name }}</h3>
						</div>
						<span class="folder-path">{{ project.folder }}</span>
					</div>
					<div class="card-actions">
						<button class="btn icon-btn delete-btn" @click="promptDelete(project)" title="Delete Project">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<polyline points="3 6 5 6 21 6"></polyline>
								<path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
								<line x1="10" y1="11" x2="10" y2="17"></line>
								<line x1="14" y1="11" x2="14" y2="17"></line>
							</svg>
						</button>
						<div class="actions-right">
							<button class="btn" @click="openProject(project.id)">Open Project</button>
							<button class="btn primary-btn" @click="previewProject(project.id)">Preview</button>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Custom Delete Confirmation Modal -->
		<div v-if="projectToDelete" class="modal-overlay" @click.self="cancelDelete">
			<div class="modal">
				<div class="modal-header">
					<h3>Delete Project</h3>
				</div>
				<div class="modal-body">
					<p>Are you sure you want to delete the project <strong>"{{ projectToDelete.name }}"</strong>?</p>
					<p class="warning-text">This will only remove the project from the list. It will <strong>not</strong> delete the actual files from your disk.</p>
				</div>
				<div class="modal-footer">
					<button class="btn cancel-btn" @click="cancelDelete" :disabled="isDeleting">Cancel</button>
					<button class="btn confirm-btn" @click="confirmDelete" :disabled="isDeleting">
						{{ isDeleting ? 'Deleting...' : 'Delete' }}
					</button>
				</div>
			</div>
		</div>

		<!-- Floating Toolbar for Multi-Select -->
		<div v-if="visibleSelectedProjects.length > 0" class="floating-toolbar">
			<span class="selected-count">{{ visibleSelectedProjects.length }} Project{{ visibleSelectedProjects.length !== 1 ? 's' : '' }} Selected</span>
			<div class="toolbar-actions">
				<button class="btn cancel-btn" @click="clearSelection">Cancel Selection</button>
				<button class="btn confirm-btn" @click="promptBulkDelete">Delete Selected</button>
			</div>
		</div>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'

export default {
	name: 'HugoProjects',
	data() {
		return {
			projects: [],
			loading: true,
			searchQuery: '',
			filterOption: 'all',
			selectedProjects: [],
			projectToDelete: null,
			bulkDeleteMode: false,
			isDeleting: false,
			theme: localStorage.getItem('hugo-theme') || 'auto',
			systemIsDark: window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
		}
	},
	computed: {
		filteredProjects() {
			let result = this.projects.slice();

			// Filter by search query
			if (this.searchQuery) {
				const query = this.searchQuery.toLowerCase();
				result = result.filter(p => p.name.toLowerCase().includes(query) || p.folder.toLowerCase().includes(query));
			}

			// Filter by Date
			const now = new Date();
			const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime() / 1000;
			const startOfWeek = new Date(now.getFullYear(), now.getMonth(), now.getDate() - now.getDay()).getTime() / 1000;
			const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1).getTime() / 1000;
			const last12Hours = (now.getTime() / 1000) - (12 * 60 * 60);

			if (this.filterOption === 'recent') {
				result = result.filter(p => p.created_at && p.created_at >= last12Hours);
			} else if (this.filterOption === 'today') {
				result = result.filter(p => p.created_at && p.created_at >= startOfToday);
			} else if (this.filterOption === 'week') {
				result = result.filter(p => p.created_at && p.created_at >= startOfWeek);
			} else if (this.filterOption === 'month') {
				result = result.filter(p => p.created_at && p.created_at >= startOfMonth);
			}

			// Sort
			// Default to newest first (including 'recent', 'today', 'week', 'month', 'all')
			result.sort((a, b) => (b.created_at || 0) - (a.created_at || 0));

			return result;
		},
		visibleSelectedProjects() {
			const filteredIds = new Set(this.filteredProjects.map(p => p.id));
			return this.selectedProjects.filter(id => filteredIds.has(id));
		},
		isAllSelected() {
			const visible = this.visibleSelectedProjects;
			return visible.length > 0 && visible.length === this.filteredProjects.length;
		},
		isDarkMode() {
			if (this.theme === 'auto') return this.systemIsDark;
			return this.theme === 'dark';
		},
		themeTooltip() {
			if (this.theme === 'auto') return 'Theme: System (Auto)';
			if (this.theme === 'light') return 'Theme: Light';
			return 'Theme: Dark';
		}
	},
	mounted() {
		// Listeners to distinguish mouse click from Tab key navigation
		this.mouseListener = () => { document.body.classList.add('using-mouse'); };
		this.keyListener = (e) => { if (e.key === 'Tab') document.body.classList.remove('using-mouse'); };
		window.addEventListener('mousedown', this.mouseListener);
		window.addEventListener('keydown', this.keyListener);

		this.fetchProjects();
		if (window.matchMedia) {
			this.mediaQueryListener = (e) => {
				this.systemIsDark = e.matches;
			};
			window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', this.mediaQueryListener);
		}
	},
	beforeDestroy() {
		if (window.matchMedia && this.mediaQueryListener) {
			window.matchMedia('(prefers-color-scheme: dark)').removeEventListener('change', this.mediaQueryListener);
		}
		window.removeEventListener('mousedown', this.mouseListener);
		window.removeEventListener('keydown', this.keyListener);
	},
	methods: {
		async fetchProjects() {
			try {
				const response = await axios.get(generateUrl('/apps/hugopreview_nc_app/api/projects'))
				this.projects = response.data
			} catch (err) {
				console.error('Failed to load projects', err)
				showError('Failed to load projects.', { timeout: 3000 })
			} finally {
				this.loading = false
			}
		},
		addProject() {
			this.$router.push('/projects/new')
		},
		openProject(id) {
			this.$router.push(`/projects/${id}`)
		},
		previewProject(id) {
			this.$router.push(`/preview/${id}`)
		},
		promptDelete(project) {
			this.bulkDeleteMode = false;
			this.projectToDelete = project;
		},
		promptBulkDelete() {
			this.bulkDeleteMode = true;
			this.projectToDelete = { name: `${this.selectedProjects.length} selected projects` };
		},
		cancelDelete() {
			this.projectToDelete = null;
			this.bulkDeleteMode = false;
		},
		toggleSelectAll(e) {
			if (e.target.checked) {
				const visibleIds = this.filteredProjects.map(p => p.id);
				this.selectedProjects = [...new Set([...this.selectedProjects, ...visibleIds])];
			} else {
				const visibleIds = new Set(this.filteredProjects.map(p => p.id));
				this.selectedProjects = this.selectedProjects.filter(id => !visibleIds.has(id));
			}
		},
		clearSelection() {
			this.selectedProjects = [];
		},
		async confirmDelete() {
			if (!this.projectToDelete) return;
			this.isDeleting = true;
			try {
				if (this.bulkDeleteMode) {
					// Sequential delete to not overwhelm the server
					const idsToDelete = [...this.visibleSelectedProjects];
					for (const id of idsToDelete) {
						await axios.delete(generateUrl(`/apps/hugopreview_nc_app/api/projects/${id}`));
					}
					const deletedSet = new Set(idsToDelete);
					this.selectedProjects = this.selectedProjects.filter(id => !deletedSet.has(id));
				} else {
					await axios.delete(generateUrl(`/apps/hugopreview_nc_app/api/projects/${this.projectToDelete.id}`));
					this.selectedProjects = this.selectedProjects.filter(id => id !== this.projectToDelete.id);
				}
				await this.fetchProjects();
				this.cancelDelete();
				showSuccess('Project(s) deleted successfully.', { timeout: 3000 })
			} catch (err) {
				console.error('Failed to delete project(s)', err);
				showError('Failed to delete project(s).', { timeout: 3000 });
			} finally {
				this.isDeleting = false;
			}
		},
		toggleTheme() {
			if (this.theme === 'auto') {
				this.theme = 'light';
			} else if (this.theme === 'light') {
				this.theme = 'dark';
			} else {
				this.theme = 'auto';
			}
			localStorage.setItem('hugo-theme', this.theme);
		}
	}
}
</script>

<style scoped>
.hugo-projects { padding: 40px; background-color: #f9fafb; height: calc(100vh - 60px); overflow-y: auto; box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; flex-wrap: wrap; gap: 16px; }
.header-left { display: flex; align-items: center; gap: 16px; }
.header-right { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
.page-header h2 { margin: 0; font-size: 24px; font-weight: 600; color: #111827; white-space: nowrap; }

.select-all-label { display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 500; color: #374151; cursor: pointer; }
.select-all-checkbox { width: 16px; height: 16px; cursor: pointer; accent-color: #0082c9; margin: 0; outline: none !important; border: none !important; box-shadow: none !important; }
.select-all-checkbox:hover, .select-all-checkbox:focus:not(:focus-visible), .select-all-checkbox:active { outline: none !important; box-shadow: none !important; border: none !important; }
.select-all-checkbox:focus-visible { outline: 2px solid #111827 !important; outline-offset: 2px !important; }
.search-input { padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; width: 220px; outline: none; transition: border-color 0.2s, box-shadow 0.2s; }
.search-input:focus { border-color: #0082c9; box-shadow: 0 0 0 3px rgba(0, 130, 201, 0.15); }
.filter-select { padding: 8px 32px 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; outline: none; background: #fff; cursor: pointer; min-width: 140px; appearance: none; -webkit-appearance: none; -moz-appearance: none; }

/* Buttons */
.btn { padding: 8px 16px; border-radius: 6px; font-weight: 500; font-size: 14px; cursor: pointer; border: 1px solid #d1d5db; background: #ffffff; color: #374151; transition: all 0.2s ease; box-shadow: 0 1px 2px rgba(0,0,0,0.05); display: inline-flex; align-items: center; justify-content: center; }
.btn:hover { background: #f3f4f6; border-color: #d1d5db; }
.primary-btn { background: #0082c9 !important; color: white !important; border: 1px solid #0073b1 !important; box-shadow: 0 1px 2px rgba(0, 130, 201, 0.2) !important; }
.primary-btn:hover { background: #0073b1 !important; }

/* Grid and Cards */
.project-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px; margin-bottom: 80px; }
.project-card { border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; background: #ffffff; box-shadow: 0 2px 4px rgba(0,0,0,0.02), 0 1px 2px rgba(0,0,0,0.04); transition: transform 0.2s ease, box-shadow 0.2s ease; display: flex; flex-direction: column; }
.project-card:hover { transform: translateY(-2px); box-shadow: 0 8px 16px rgba(0,0,0,0.04), 0 4px 8px rgba(0,0,0,0.02); }
.selected-card { border-color: #0082c9; background: #f0f9ff; }

.card-header-top { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 8px; }
.project-checkbox { width: 18px; height: 18px; cursor: pointer; accent-color: #0082c9; margin-top: 2px; outline: none !important; border: none !important; box-shadow: none !important; }
.project-checkbox:hover, .project-checkbox:focus:not(:focus-visible), .project-checkbox:active { outline: none !important; box-shadow: none !important; border: none !important; }
.project-checkbox:focus-visible { outline: 2px solid #111827 !important; outline-offset: 2px !important; }
.card-header h3 { margin: 0; font-size: 18px; font-weight: 600; color: #111827; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; }
.folder-path { color: #6b7280; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 13px; background: #f3f4f6; padding: 4px 8px; border-radius: 4px; display: inline-block; max-width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; box-sizing: border-box; }
.card-actions { margin-top: auto; padding-top: 24px; display: flex; justify-content: space-between; align-items: center; gap: 12px; }
.actions-right { display: flex; gap: 12px; }
.icon-btn { padding: 8px; color: #9ca3af; border-color: transparent; background: transparent; box-shadow: none; }
.icon-btn:hover { color: #374151; background: #f3f4f6; border-color: transparent; }

/* Empty / Loading */
.empty-state { text-align: center; padding: 64px 20px; background: #ffffff; border: 1px dashed #d1d5db; border-radius: 12px; color: #6b7280; }
.empty-icon { font-size: 48px; margin-bottom: 16px; opacity: 0.5; }
.empty-state h3 { font-size: 18px; font-weight: 500; color: #374151; margin-bottom: 8px; }
.empty-state p { font-size: 14px; }
.hugo-loading { display: flex; align-items: center; justify-content: center; min-height: 400px; color: #6b7280; font-size: 16px; }

/* Custom Modal */
.modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.4); display: flex; align-items: center; justify-content: center; z-index: 10000; padding: 20px; animation: fadeIn 0.2s ease; }
.modal { background: #ffffff; width: 100%; max-width: 450px; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); overflow: hidden; animation: slideUp 0.2s ease; }
.modal-header { padding: 16px 24px; border-bottom: 1px solid #e5e7eb; }
.modal-header h3 { margin: 0; font-size: 18px; font-weight: 600; color: #111827; }
.modal-body { padding: 24px; color: #374151; font-size: 15px; }
.modal-body p { margin-top: 0; margin-bottom: 12px; }
.warning-text { color: #d97706; font-size: 13.5px; background: #fffbeb; padding: 12px; border-radius: 6px; border-left: 4px solid #f59e0b; margin-bottom: 0 !important; }
.modal-footer { padding: 16px 24px; background: #f9fafb; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 12px; }
.cancel-btn { background: #ffffff; border-color: #d1d5db; color: #374151; }
.cancel-btn:hover { background: #f3f4f6; }
.confirm-btn { background: #dc2626 !important; border-color: #b91c1c !important; color: white !important; }
.confirm-btn:hover { background: #b91c1c !important; }
.confirm-btn:disabled, .cancel-btn:disabled { opacity: 0.6; cursor: not-allowed; }

/* Floating Toolbar */
.floating-toolbar { position: fixed; bottom: 32px; left: 50%; transform: translateX(-50%); background: #111827; color: white; padding: 16px 24px; border-radius: 12px; display: flex; align-items: center; gap: 24px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); z-index: 9000; animation: slideUpFade 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
.selected-count { font-weight: 500; font-size: 15px; }
.toolbar-actions { display: flex; gap: 12px; }
.toolbar-actions .cancel-btn { background: transparent; border-color: #4b5563; color: white; }
.toolbar-actions .cancel-btn:hover { background: #374151; }

@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideUp { from { transform: translateY(10px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
@keyframes slideUpFade { from { transform: translate(-50%, 20px); opacity: 0; } to { transform: translate(-50%, 0); opacity: 1; } }

.theme-toggle-btn { display: inline-flex; align-items: center; justify-content: center; padding: 6px; border-radius: 6px; cursor: pointer; transition: all 0.2s; color: #6b7280; }
.theme-toggle-btn:hover { background: #f3f4f6; color: #111827; }

/* Light Theme Overrides (Protect against Nextcloud Global Dark Mode) */
.hugo-projects.theme-light { color-scheme: light !important; }
.hugo-projects.theme-light .search-input { background-color: #ffffff !important; color: #111827 !important; border-color: #d1d5db !important; }
.hugo-projects.theme-light .search-input::placeholder { color: #9ca3af !important; }
.hugo-projects.theme-light .filter-select { background-color: #ffffff !important; color: #111827 !important; border-color: #d1d5db !important; background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%236b7280%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E") !important; background-repeat: no-repeat !important; background-position: right 12px center !important; background-size: 10px auto !important; }
.hugo-projects.theme-light .search-input:focus, .hugo-projects.theme-light .filter-select:focus { outline: none !important; border-color: #111827 !important; box-shadow: 0 0 0 2px rgba(17, 24, 39, 0.1) !important; }
body:not(.using-mouse) .hugo-projects.theme-light .search-input:focus, body:not(.using-mouse) .hugo-projects.theme-light .filter-select:focus { outline: 2px solid #111827 !important; outline-offset: 0px !important; box-shadow: none !important; }
.hugo-projects.theme-light .btn:not(.primary-btn):not(.confirm-btn):not(.cancel-btn) { background-color: #ffffff !important; color: #374151 !important; border-color: #d1d5db !important; }
.hugo-projects.theme-light .btn:not(.primary-btn):not(.confirm-btn):not(.cancel-btn):hover { background-color: #f3f4f6 !important; color: #111827 !important; }
.hugo-projects.theme-light .primary-btn { background-color: #0082c9 !important; color: white !important; border-color: #0073b1 !important; }
.hugo-projects.theme-light .primary-btn:hover { background-color: #0073b1 !important; color: white !important; }
.hugo-projects.theme-light .primary-btn:focus-visible { outline: 2px solid #111827 !important; outline-offset: 0px !important; background-color: #0082c9 !important; border-color: #0073b1 !important; box-shadow: none !important; }
.hugo-projects.theme-light .icon-btn { background-color: transparent !important; color: #9ca3af !important; border-color: transparent !important; }
.hugo-projects.theme-light .icon-btn:hover { background-color: #f3f4f6 !important; color: #374151 !important; border-color: transparent !important; }
.hugo-projects.theme-light .theme-toggle-btn { background-color: transparent !important; color: #6b7280 !important; }
.hugo-projects.theme-light .theme-toggle-btn:focus { background-color: transparent !important; outline: none !important; box-shadow: none !important; }
body:not(.using-mouse) .hugo-projects.theme-light .theme-toggle-btn:focus { outline: 2px solid #111827 !important; outline-offset: 0px !important; }
.hugo-projects.theme-light input[type="checkbox"]:not(:checked) { background-color: #ffffff !important; border-color: #d1d5db !important; }

/* Light Theme Floating Toolbar */
.hugo-projects.theme-light .floating-toolbar { background: #ffffff !important; color: #111827 !important; border: 1px solid #e5e7eb; }
.hugo-projects.theme-light .toolbar-actions .cancel-btn { border-color: #d1d5db !important; color: #374151 !important; }
.hugo-projects.theme-light .toolbar-actions .cancel-btn:hover { background: #f3f4f6 !important; }

/* Dark Theme Overrides */
.hugo-projects.theme-dark { background-color: #111827; color-scheme: dark !important; }
.hugo-projects.theme-dark .page-header h2 { color: #f9fafb; }
.hugo-projects.theme-dark .select-all-label { color: #d1d5db; }
body:not(.using-mouse) .hugo-projects.theme-dark .select-all-checkbox:focus { outline: 2px solid #f9fafb !important; }
.hugo-projects.theme-dark .search-input { background: #1f2937; border-color: #374151; color: #f9fafb; }
.hugo-projects.theme-dark .search-input::placeholder { color: #6b7280 !important; }
.hugo-projects.theme-dark .filter-select { background-color: #1f2937 !important; border-color: #374151 !important; color: #f9fafb !important; background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23d1d5db%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E") !important; background-repeat: no-repeat !important; background-position: right 12px center !important; background-size: 10px auto !important; }
.hugo-projects.theme-dark .search-input:focus, .hugo-projects.theme-dark .filter-select:focus { outline: none !important; border-color: #f9fafb !important; box-shadow: 0 0 0 2px rgba(249, 250, 251, 0.1) !important; }
body:not(.using-mouse) .hugo-projects.theme-dark .search-input:focus, body:not(.using-mouse) .hugo-projects.theme-dark .filter-select:focus { outline: 2px solid #f9fafb !important; outline-offset: 0px !important; box-shadow: none !important; }

.hugo-projects.theme-dark .btn { background: #1f2937; border-color: #374151; color: #d1d5db; }
.hugo-projects.theme-dark .btn:hover { background: #374151; border-color: #4b5563; }
.hugo-projects.theme-dark .primary-btn { background-color: #0082c9 !important; border-color: #0073b1 !important; color: white !important; }
.hugo-projects.theme-dark .primary-btn:hover { background-color: #0073b1 !important; color: white !important; }
.hugo-projects.theme-dark .primary-btn:focus-visible { outline: 2px solid #f9fafb !important; outline-offset: 0px !important; background-color: #0082c9 !important; border-color: #0073b1 !important; box-shadow: none !important; }

.hugo-projects.theme-dark .project-card { background: #1f2937; border-color: #374151; }
.hugo-projects.theme-dark .project-card:hover { box-shadow: 0 8px 16px rgba(0,0,0,0.2), 0 4px 8px rgba(0,0,0,0.1); }
.hugo-projects.theme-dark .selected-card { border-color: #0082c9; background: #1e3a5f; }
.hugo-projects.theme-dark .card-header h3 { color: #f9fafb; }
.hugo-projects.theme-dark .folder-path { background: #374151; color: #d1d5db; }
.hugo-projects.theme-dark .project-checkbox:focus-visible { outline: 2px solid #f9fafb !important; }
.hugo-projects.theme-dark input[type="checkbox"]:not(:checked) { background-color: #1f2937 !important; border-color: #374151 !important; }

.hugo-projects.theme-dark .icon-btn:hover { background: #374151; border-color: transparent; color: #f9fafb; }
.hugo-projects.theme-dark .theme-toggle-btn { background-color: transparent !important; }
.hugo-projects.theme-dark .theme-toggle-btn:hover { background: #374151 !important; color: #f9fafb !important; border-color: transparent !important; }
.hugo-projects.theme-dark .theme-toggle-btn:focus-visible { background-color: transparent !important; outline: 2px solid #f9fafb !important; outline-offset: 0px !important; box-shadow: none !important; }

.hugo-projects.theme-dark .empty-state { background: #1f2937; border-color: #374151; color: #9ca3af; }
.hugo-projects.theme-dark .empty-state h3 { color: #d1d5db; }

.hugo-projects.theme-dark .modal { background: #1f2937; }
.hugo-projects.theme-dark .modal-header { border-color: #374151; }
.hugo-projects.theme-dark .modal-header h3 { color: #f9fafb; }
.hugo-projects.theme-dark .modal-body { color: #d1d5db; }
.hugo-projects.theme-dark .warning-text { background: #78350f; color: #fde68a; border-color: #b45309; }
.hugo-projects.theme-dark .modal-footer { background: #111827; border-color: #374151; }
.hugo-projects.theme-dark .cancel-btn { background: #1f2937; border-color: #374151; color: #d1d5db; }
.hugo-projects.theme-dark .cancel-btn:hover { background: #374151; }
</style>
