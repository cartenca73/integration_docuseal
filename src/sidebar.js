import { createApp } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import SignatureStatusSidebar from './components/SignatureStatusSidebar.vue'

if (window.OCA?.Files?.Sidebar) {
	const sidebarTab = new OCA.Files.Sidebar.Tab({
		id: 'docuseal-signatures',
		name: t('integration_docuseal', 'Firme'),
		iconSvg: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="currentColor" d="M6 2a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6H6zm7 1.5L18.5 9H13V3.5zM7 14.5c1.5 0 2.5-1 3.5-2s2-2 3.5-2 2.5 1 3 1.5l-1 1c-.5-.5-1.3-1.2-2-1.2s-1.5.7-2.7 2c-1.2 1.3-2.3 2.2-4.3 2.2v-1.5z"/></svg>',

		async mount(el, fileInfo) {
			if (this._app) {
				this._app.unmount()
			}
			this._app = createApp(SignatureStatusSidebar, {
				fileId: fileInfo.id,
			})
			this._app.mount(el)
		},

		update(fileInfo) {
			// Handled by prop reactivity
		},

		destroy() {
			if (this._app) {
				this._app.unmount()
				this._app = null
			}
		},

		enabled(fileInfo) {
			return fileInfo?.mimetype === 'application/pdf'
				|| fileInfo?.mimetype === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
		},
	})

	OCA.Files.Sidebar.registerTab(sidebarTab)
}
