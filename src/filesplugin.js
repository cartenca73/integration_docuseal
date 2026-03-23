import { registerFileAction, FileAction, Permission } from '@nextcloud/files'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { createApp } from 'vue'

let modalApp = null
let DocuSealModalComponent = null
let docuSealConnected = null

async function checkConnection() {
	if (docuSealConnected !== null) {
		return docuSealConnected
	}
	try {
		const url = generateUrl('/apps/integration_docuseal/info')
		const response = await axios.get(url)
		docuSealConnected = response.data.connected === true
	} catch (e) {
		docuSealConnected = false
	}
	return docuSealConnected
}

async function getModal() {
	if (DocuSealModalComponent === null) {
		const { default: DocuSealModal } = await import('./components/DocuSealModal.vue')
		DocuSealModalComponent = DocuSealModal
	}
	return DocuSealModalComponent
}

async function showSignModal(fileInfo) {
	const Component = await getModal()

	if (modalApp) {
		modalApp.unmount()
		const oldContainer = document.getElementById('docuseal-modal-container')
		if (oldContainer) {
			oldContainer.remove()
		}
	}

	const container = document.createElement('div')
	container.id = 'docuseal-modal-container'
	document.body.appendChild(container)

	modalApp = createApp(Component, {
		fileId: fileInfo.fileid,
		fileName: fileInfo.basename,
		fileMime: fileInfo.mime,
		onClose: () => {
			if (modalApp) {
				modalApp.unmount()
				modalApp = null
			}
			container.remove()
		},
	})
	modalApp.mount(container)
}

const docuSealAction = new FileAction({
	id: 'docuseal-sign',
	displayName: () => t('integration_docuseal', 'Richiedi firma con DocuSeal'),
	iconSvgInline: () => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="currentColor" d="M6 2a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6H6zm7 1.5L18.5 9H13V3.5zM9.5 17.5c0 .28-.22.5-.5.5s-.5-.22-.5-.5S8.72 17 9 17s.5.22.5.5zm2.5.5c-.28 0-.5-.22-.5-.5s.22-.5.5-.5.5.22.5.5-.22.5-.5.5zm2.5-.5c0 .28-.22.5-.5.5s-.5-.22-.5-.5.22-.5.5-.5.5.22.5.5zM7 14.5c1.5 0 2.5-1 3.5-2s2-2 3.5-2 2.5 1 3 1.5l-1 1c-.5-.5-1.3-1.2-2-1.2s-1.5.7-2.7 2c-1.2 1.3-2.3 2.2-4.3 2.2v-1.5z"/></svg>',
	enabled(nodes) {
		if (nodes.length !== 1) {
			return false
		}
		const node = nodes[0]
		const allowedMimes = [
			'application/pdf',
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'application/msword',
			'image/png',
			'image/jpeg',
		]
		if (!allowedMimes.includes(node.mime)) {
			return false
		}
		if (!(node.permissions & Permission.READ)) {
			return false
		}
		return true
	},
	async exec(node) {
		const connected = await checkConnection()
		if (!connected) {
			if (window.OC?.Notification) {
				OC.Notification.showTemporary(t('integration_docuseal', 'DocuSeal non è configurato. Contatta l\'amministratore.'))
			}
			return null
		}
		showSignModal(node)
		return null
	},
	order: 90,
})

registerFileAction(docuSealAction)
