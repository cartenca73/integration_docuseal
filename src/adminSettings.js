import { createApp } from 'vue'
import AdminSettings from './components/AdminSettings.vue'

document.addEventListener('DOMContentLoaded', () => {
	const container = document.getElementById('docuseal_prefs')
	if (container) {
		const app = createApp(AdminSettings)
		app.mount(container)
	}
})
