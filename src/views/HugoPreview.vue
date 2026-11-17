<template>
	<div class="hugo-preview" :class="{ 'theme-dark': isDarkMode, 'theme-light': !isDarkMode }">

		<div v-if="building" class="loading-screen">
			<div class="spinner"></div>
			<h3>Building your Hugo site...</h3>
			<p>Please wait while the static files are generated.</p>
		</div>

		<div v-else-if="error" class="error-screen">
			<div class="error-icon">❌</div>
			<h3>Syntax mistake / Invalid update</h3>
			<p class="error-description">The last changes made to this project contain invalid syntax or are not supported. Please review your recent edits and try again.</p>

			<div class="error-log-container">
				<p class="error-log-title">Technical Details:</p>
				<pre class="error-log">{{ error }}</pre>
			</div>

			<div class="error-actions">
				<button class="primary-btn" @click="buildProject">Retry Build</button>
				<button class="back-btn" @click="goBack">Back to Projects</button>
			</div>
		</div>

		<div v-else class="preview-frame-container">
			<header class="preview-header">
				<button class="back-btn" @click="goBack">← Exit Preview</button>
				<button class="primary-btn rebuild-btn" @click="buildProject" :disabled="building">
					<svg v-if="building" class="btn-spinner" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<line x1="12" y1="2" x2="12" y2="6"></line>
						<line x1="12" y1="18" x2="12" y2="22"></line>
						<line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line>
						<line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line>
						<line x1="2" y1="12" x2="6" y2="12"></line>
						<line x1="18" y1="12" x2="22" y2="12"></line>
						<line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line>
						<line x1="16.24" y1="7.76" x2="19.07" y2="4.93"></line>
					</svg>
					<svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<polyline points="23 4 23 10 17 10"></polyline>
						<polyline points="1 20 1 14 7 14"></polyline>
						<path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
					</svg>
					{{ building ? 'Rebuilding...' : 'Re-Preview' }}
				</button>
			</header>
			<iframe ref="previewFrame" v-if="previewUrl" :src="previewUrl" class="preview-iframe" title="Hugo Preview"></iframe>
		</div>

	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'HugoPreview',
	props: ['id'],
	data() {
		return {
			building: true,
			error: null,
			previewUrl: null,
			theme: localStorage.getItem('hugo-theme') || 'auto',
			systemIsDark: window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
		}
	},
	computed: {
		isDarkMode() {
			if (this.theme === 'auto') return this.systemIsDark;
			return this.theme === 'dark';
		}
	},
	mounted() {
		this.buildProject()
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
	},
	methods: {
		handleStorageEvent(e) {
			if (e.key === 'hugo_auto_sync_event') {
				try {
					const payload = JSON.parse(e.newValue);
					if (payload.success) {
						// Build succeeded
						if (this.error || !this.previewUrl) {
							// If we were previously showing an error, trigger a clean rebuild to fetch the new preview URL
							this.buildProject();
						} else if (this.$refs.previewFrame) {
							// If iframe is already up, just reload it silently
							try {
								this.$refs.previewFrame.contentWindow.location.reload(true);
							} catch (err) {
								const url = this.previewUrl.split('?')[0];
								this.previewUrl = url + '?t=' + Date.now();
							}
						}
					} else {
						// Build failed! Show our custom error screen instead of the 404 iframe
						this.error = payload.error || 'Syntax error occurred during live sync.';
					}
				} catch (err) {
					console.error('Failed to parse sync event', err);
				}
			} else if (e.key === 'hugo_auto_sync_reload') {
				// Fallback for legacy events
				if (this.$refs.previewFrame && !this.error) {
					try {
						this.$refs.previewFrame.contentWindow.location.reload(true);
					} catch (err) {
						const url = this.previewUrl.split('?')[0];
						this.previewUrl = url + '?t=' + Date.now();
					}
				}
			}
		},
		async buildProject() {
			this.building = true
			this.error = null

			try {
				const response = await axios.post(generateUrl(`/apps/hugopreview_nc_app/api/projects/${this.id}/build`))
				if (response.data.success) {
					this.previewUrl = response.data.preview_url
				} else {
					this.error = response.data.error || 'Unknown error occurred'
				}
			} catch (err) {
				this.error = err.response?.data?.error || err.message || 'Build failed'
			} finally {
				this.building = false
			}
		},
		goBack() {
			this.$router.push('/projects')
		}
	}
}
</script>

<style scoped>
.hugo-preview { width: 100%; height: calc(100vh - 60px); overflow-y: auto; display: flex; flex-direction: column; background: #f9fafb; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }

/* Loading & Error Screens */
.loading-screen, .error-screen { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; text-align: center; }
.spinner { width: 40px; height: 40px; border: 3px solid #e5e7eb; border-top-color: #0082c9; border-radius: 50%; animation: spin 1s cubic-bezier(0.55, 0.15, 0.45, 0.85) infinite; margin-bottom: 24px; }
@keyframes spin { 100% { transform: rotate(360deg); } }
.loading-screen h3 { font-size: 20px; font-weight: 600; color: #111827; margin-bottom: 8px; }
.loading-screen p { color: #6b7280; font-size: 15px; }

.error-icon { font-size: 48px; margin-bottom: 16px; }
.error-screen h3 { font-size: 24px; font-weight: 600; color: #111827; margin-bottom: 8px; }
.error-description { color: #4b5563; font-size: 15px; margin-bottom: 24px; max-width: 500px; line-height: 1.5; }
.error-log-container { text-align: left; max-width: 800px; width: 90%; margin-bottom: 32px; }
.error-log-title { font-size: 13px; font-weight: 600; color: #6b7280; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em; }
.error-log { background: #111827; color: #f87171; padding: 20px 24px; border-radius: 8px; white-space: pre-wrap; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 13px; overflow-x: auto; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin: 0; }
.error-actions { display: flex; gap: 12px; }

/* Toolbar & iframe */
.preview-frame-container { flex: 1; display: flex; flex-direction: column; height: 100%; }
.preview-header { background: #ffffff; border-bottom: 1px solid #e5e7eb; padding: 12px 24px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 1px 2px rgba(0,0,0,0.02); }
.preview-iframe { flex: 1; width: 100%; border: none; background: #ffffff; }

/* Buttons */
.primary-btn { background: #0082c9; color: white; border: 1px solid #0073b1; padding: 8px 16px; border-radius: 6px; font-weight: 500; font-size: 14px; cursor: pointer; box-shadow: 0 1px 2px rgba(0, 130, 201, 0.2); transition: background 0.2s; display: inline-flex; align-items: center; gap: 8px; justify-content: center; }
.primary-btn:hover:not(:disabled) { background: #0073b1; }
.primary-btn:disabled { opacity: 0.7; cursor: not-allowed; }
.back-btn { background: #ffffff; border: 1px solid #d1d5db; font-size: 14px; font-weight: 500; cursor: pointer; color: #374151; padding: 8px 16px; border-radius: 6px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: background 0.2s; display: inline-flex; align-items: center; }
.back-btn:hover { background: #f3f4f6; }
.preview-header .back-btn { padding: 6px 12px; font-size: 13px; }
.preview-header .rebuild-btn { padding: 6px 12px; font-size: 13px; }
.btn-spinner { animation: spin 1.2s linear infinite; }

/* Light Theme Overrides */
.hugo-preview.theme-light { color-scheme: light !important; background-color: #f9fafb !important; }
.hugo-preview.theme-light .loading-screen h3, .hugo-preview.theme-light .error-screen h3 { color: #111827 !important; }
.hugo-preview.theme-light .back-btn { background: #ffffff !important; color: #374151 !important; border-color: #d1d5db !important; }
.hugo-preview.theme-light .back-btn:hover { background: #f3f4f6 !important; }
.hugo-preview.theme-light .preview-header { background: #ffffff !important; border-color: #e5e7eb !important; }
.hugo-preview.theme-light .primary-btn { background: #0082c9 !important; color: white !important; border-color: #0073b1 !important; }
.hugo-preview.theme-light .primary-btn:hover:not(:disabled) { background: #0073b1 !important; }

/* Dark Theme Overrides */
.hugo-preview.theme-dark { color-scheme: dark !important; background-color: #111827 !important; }
.hugo-preview.theme-dark .loading-screen h3, .hugo-preview.theme-dark .error-screen h3 { color: #f9fafb !important; }
.hugo-preview.theme-dark .loading-screen p, .hugo-preview.theme-dark .error-description { color: #9ca3af !important; }
.hugo-preview.theme-dark .spinner { border-color: #374151 !important; border-top-color: #0082c9 !important; }
.hugo-preview.theme-dark .error-log-title { color: #9ca3af !important; }
.hugo-preview.theme-dark .error-log { background: #1f2937 !important; border: 1px solid #374151 !important; }

.hugo-preview.theme-dark .preview-header { background: #1f2937 !important; border-bottom-color: #374151 !important; }
.hugo-preview.theme-dark .back-btn { background: #111827 !important; border-color: #374151 !important; color: #d1d5db !important; }
.hugo-preview.theme-dark .back-btn:hover { background: #374151 !important; color: #f9fafb !important; }

.hugo-preview.theme-dark .primary-btn { color: white !important; }
</style>
