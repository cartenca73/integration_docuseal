<template>
	<NcModal v-if="show" size="large" @close="close">
		<div class="docuseal-modal">
			<h2>{{ t('integration_docuseal', 'Richiedi firma') }}</h2>
			<p class="subtitle">
				{{ t('integration_docuseal', 'Documento:') }} <strong>{{ fileName }}</strong>
			</p>

			<!-- Signing mode selector -->
			<div class="mode-selector">
				<NcCheckboxRadioSwitch
					v-model:checked="signMode"
					value="direct"
					name="sign-mode"
					type="radio">
					{{ t('integration_docuseal', 'Invio diretto PDF') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					v-model:checked="signMode"
					value="template"
					name="sign-mode"
					type="radio">
					{{ t('integration_docuseal', 'Usa template DocuSeal') }}
				</NcCheckboxRadioSwitch>
			</div>

			<!-- Template selector (only in template mode) -->
			<div v-if="signMode === 'template'" class="template-section">
				<label>{{ t('integration_docuseal', 'Seleziona template') }}</label>
				<NcSelect
					v-model="selectedTemplate"
					:options="templates"
					:loading="loadingTemplates"
					:placeholder="t('integration_docuseal', 'Cerca template...')"
					label="name"
					track-by="id"
					@open="loadTemplates"
					@update:model-value="onTemplateSelected" />

				<!-- Template preview -->
				<div v-if="selectedTemplate && templateDetail" class="template-preview">
					<div class="preview-header" @click="showPreview = !showPreview">
						<span>{{ showPreview ? '▾' : '▸' }} {{ t('integration_docuseal', 'Anteprima template') }}</span>
					</div>
					<div v-if="showPreview" class="preview-content">
						<p><strong>{{ templateDetail.name }}</strong></p>
						<p v-if="templateDetail.folder_name" class="preview-folder">
							{{ t('integration_docuseal', 'Cartella:') }} {{ templateDetail.folder_name }}
						</p>
						<div v-if="templateFields.length" class="preview-fields">
							<p class="preview-fields-title">{{ t('integration_docuseal', 'Campi del template:') }}</p>
							<ul>
								<li v-for="field in templateFields" :key="field.name">
									{{ field.name }} <span class="field-type">({{ field.type }})</span>
								</li>
							</ul>
						</div>
						<div v-if="templateDetail.schema && templateDetail.schema.length" class="preview-roles">
							<p class="preview-fields-title">{{ t('integration_docuseal', 'Ruoli:') }}</p>
							<span v-for="schema in templateDetail.schema" :key="schema.name" class="role-badge">
								{{ schema.name }}
							</span>
						</div>
					</div>
				</div>
			</div>

			<!-- Recipients -->
			<div class="recipients-section">
				<label>{{ t('integration_docuseal', 'Destinatari') }}</label>
				<MultiselectWho
					v-model="recipients" />
				<p class="hint">
					{{ t('integration_docuseal', 'Cerca utenti Nextcloud o inserisci indirizzi email') }}
				</p>
			</div>

			<!-- Options -->
			<div class="options-section">
				<NcCheckboxRadioSwitch v-model:checked="sendEmail">
					{{ t('integration_docuseal', 'Invia email di notifica ai destinatari') }}
				</NcCheckboxRadioSwitch>

				<NcCheckboxRadioSwitch v-model:checked="showEmbedOption">
					{{ t('integration_docuseal', 'Abilita firma embedded (in-app)') }}
				</NcCheckboxRadioSwitch>
			</div>

			<!-- Expiry date -->
			<div class="expiry-section">
				<NcCheckboxRadioSwitch v-model:checked="hasExpiry">
					{{ t('integration_docuseal', 'Imposta scadenza') }}
				</NcCheckboxRadioSwitch>
				<div v-if="hasExpiry" class="expiry-field">
					<input
						v-model="expiryDate"
						type="datetime-local"
						:min="minExpiryDate">
				</div>
			</div>

			<!-- Custom message -->
			<div class="message-section">
				<NcCheckboxRadioSwitch v-model:checked="customMessage">
					{{ t('integration_docuseal', 'Messaggio personalizzato') }}
				</NcCheckboxRadioSwitch>
				<div v-if="customMessage" class="message-fields">
					<input
						v-model="subject"
						type="text"
						:placeholder="t('integration_docuseal', 'Oggetto email')">
					<textarea
						v-model="message"
						:placeholder="t('integration_docuseal', 'Messaggio per i firmatari...')"
						rows="3" />
				</div>
			</div>

			<!-- Actions -->
			<div class="actions">
				<NcButton type="tertiary" @click="close">
					{{ t('integration_docuseal', 'Annulla') }}
				</NcButton>
				<NcButton
					type="primary"
					:disabled="!canSubmit || submitting"
					@click="requestSignature">
					<template #icon>
						<NcLoadingIcon v-if="submitting" :size="20" />
					</template>
					{{ submitting ? t('integration_docuseal', 'Invio in corso...') : t('integration_docuseal', 'Richiedi firma') }}
				</NcButton>
			</div>

			<!-- Embed signing area -->
			<div v-if="embedUrl" class="embed-section">
				<h3>{{ t('integration_docuseal', 'Firma il documento') }}</h3>
				<iframe
					:src="embedUrl"
					class="embed-frame"
					frameborder="0"
					allow="camera" />
			</div>
		</div>
	</NcModal>
</template>

<script>
import NcModal from '@nextcloud/vue/components/NcModal'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { generateUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'
import MultiselectWho from './MultiselectWho.vue'

export default {
	name: 'DocuSealModal',

	components: {
		NcModal,
		NcButton,
		NcSelect,
		NcCheckboxRadioSwitch,
		NcLoadingIcon,
		MultiselectWho,
	},

	emits: ['close'],

	props: {
		fileId: {
			type: Number,
			required: true,
		},
		fileName: {
			type: String,
			required: true,
		},
		fileMime: {
			type: String,
			default: 'application/pdf',
		},
	},

	data() {
		return {
			show: true,
			signMode: 'direct',
			selectedTemplate: null,
			templateDetail: null,
			templates: [],
			loadingTemplates: false,
			recipients: [],
			sendEmail: true,
			showEmbedOption: false,
			customMessage: false,
			subject: '',
			message: '',
			hasExpiry: false,
			expiryDate: '',
			showPreview: false,
			submitting: false,
			embedUrl: null,
		}
	},

	computed: {
		canSubmit() {
			if (this.recipients.length === 0) {
				return false
			}
			if (this.signMode === 'template' && !this.selectedTemplate) {
				return false
			}
			return true
		},
		minExpiryDate() {
			const now = new Date()
			now.setHours(now.getHours() + 1)
			return now.toISOString().slice(0, 16)
		},
		templateFields() {
			if (!this.templateDetail?.fields) return []
			return this.templateDetail.fields
		},
	},

	methods: {
		close() {
			this.show = false
			this.$emit('close')
		},

		async loadTemplates() {
			if (this.templates.length > 0) {
				return
			}
			this.loadingTemplates = true
			try {
				const url = generateUrl('/apps/integration_docuseal/templates')
				const response = await axios.get(url)
				this.templates = response.data || []
			} catch (e) {
				showError(t('integration_docuseal', 'Errore nel caricamento dei template'))
				console.error(e)
			}
			this.loadingTemplates = false
		},

		async onTemplateSelected(template) {
			if (!template) {
				this.templateDetail = null
				return
			}
			try {
				const url = generateUrl('/apps/integration_docuseal/templates/{templateId}', { templateId: template.id })
				const response = await axios.get(url)
				this.templateDetail = response.data
				this.showPreview = true
			} catch (e) {
				console.error('Error loading template detail:', e)
			}
		},

		async requestSignature() {
			this.submitting = true
			try {
				const targetUserIds = []
				const targetEmails = []

				this.recipients.forEach(r => {
					if (r.type === 'user' && r.id) {
						targetUserIds.push(r.id)
					} else if (r.email) {
						targetEmails.push(r.email)
					}
				})

				let url, params

				if (this.signMode === 'direct') {
					url = generateUrl('/apps/integration_docuseal/sign/direct/{fileId}', { fileId: this.fileId })
					params = {
						targetUserIds,
						targetEmails,
						sendEmail: this.sendEmail,
					}
				} else {
					url = generateUrl('/apps/integration_docuseal/sign/template')
					params = {
						templateId: this.selectedTemplate.id,
						fileId: this.fileId,
						targetUserIds,
						targetEmails,
						sendEmail: this.sendEmail,
					}
				}

				if (this.customMessage) {
					if (this.subject) params.subject = this.subject
					if (this.message) params.message = this.message
				}

				if (this.hasExpiry && this.expiryDate) {
					params.expireAt = new Date(this.expiryDate).toISOString()
				}

				const response = await axios.post(url, params)

				if (response.data.success) {
					showSuccess(t('integration_docuseal', 'Richiesta di firma inviata con successo! I destinatari riceveranno una notifica.'))

					if (this.showEmbedOption && response.data.requestId) {
						await this.loadEmbedUrl(response.data.requestId)
					} else {
						this.close()
					}
				} else {
					showError(response.data.error || t('integration_docuseal', 'Errore nell\'invio della richiesta'))
				}
			} catch (e) {
				const errorMsg = e.response?.data?.error || e.message
				showError(t('integration_docuseal', 'Errore: ') + errorMsg)
				console.error(e)
			}
			this.submitting = false
		},

		async loadEmbedUrl(requestId) {
			try {
				const url = generateUrl('/apps/integration_docuseal/embed/{requestId}', { requestId })
				const response = await axios.get(url)
				if (response.data.embedSrc) {
					this.embedUrl = response.data.embedSrc
				}
			} catch (e) {
				this.close()
			}
		},
	},
}
</script>

<style scoped>
.docuseal-modal {
	padding: 20px;
	min-width: 400px;
	max-height: 80vh;
	overflow-y: auto;
}

h2 {
	margin-top: 0;
}

.subtitle {
	color: var(--color-text-maxcontrast);
	margin-bottom: 16px;
}

.mode-selector {
	display: flex;
	gap: 16px;
	margin-bottom: 16px;
	padding: 12px;
	background: var(--color-background-dark);
	border-radius: var(--border-radius);
}

.template-section,
.recipients-section,
.options-section,
.message-section,
.expiry-section {
	margin-bottom: 16px;
}

.template-section label,
.recipients-section label {
	display: block;
	font-weight: bold;
	margin-bottom: 4px;
}

.hint {
	color: var(--color-text-maxcontrast);
	font-size: 0.85em;
	margin-top: 4px;
}

.template-preview {
	margin-top: 8px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	overflow: hidden;
}

.preview-header {
	padding: 8px 12px;
	background: var(--color-background-dark);
	cursor: pointer;
	font-weight: 500;
	user-select: none;
}

.preview-header:hover {
	background: var(--color-background-hover);
}

.preview-content {
	padding: 12px;
}

.preview-folder {
	color: var(--color-text-maxcontrast);
	font-size: 0.9em;
}

.preview-fields-title {
	font-weight: 500;
	margin-bottom: 4px;
}

.preview-fields ul {
	margin: 0;
	padding-left: 20px;
}

.preview-fields li {
	font-size: 0.9em;
	margin-bottom: 2px;
}

.field-type {
	color: var(--color-text-maxcontrast);
	font-size: 0.85em;
}

.role-badge {
	display: inline-block;
	padding: 2px 8px;
	margin: 2px 4px 2px 0;
	background: var(--color-primary-element-light);
	border-radius: 10px;
	font-size: 0.85em;
}

.expiry-field {
	margin-top: 8px;
}

.expiry-field input {
	width: 100%;
	max-width: 300px;
}

.message-fields {
	margin-top: 8px;
}

.message-fields input,
.message-fields textarea {
	width: 100%;
	margin-bottom: 8px;
}

.actions {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
	margin-top: 20px;
	padding-top: 16px;
	border-top: 1px solid var(--color-border);
}

.embed-section {
	margin-top: 20px;
	border-top: 1px solid var(--color-border);
	padding-top: 16px;
}

.embed-frame {
	width: 100%;
	height: 600px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
}
</style>
