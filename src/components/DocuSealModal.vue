<template>
	<NcModal v-if="show" :size="step === 'builder' ? 'full' : 'large'" @close="close">
		<div class="docuseal-modal" :class="{ 'builder-active': step === 'builder' }">
			<!-- ============ STEP: BUILDER ============ -->
			<div v-if="step === 'builder'" class="builder-view">
				<div class="builder-toolbar">
					<NcButton type="tertiary" @click="exitBuilder">
						<template #icon>
							<ArrowLeftIcon :size="20" />
						</template>
						{{ t('integration_docuseal', 'Indietro') }}
					</NcButton>
					<h2 class="builder-title">
						{{ t('integration_docuseal', 'Configura campi del documento') }}
					</h2>
					<div class="builder-toolbar-spacer" />
				</div>
				<div class="builder-container">
					<DocusealBuilder
						v-if="builderToken"
						:token="builderToken"
						:host="builderHost"
						:language="'it'"
						:with-send-button="false"
						:with-sign-yourself-button="false"
						:with-recipients-button="false"
						:with-upload-button="false"
						:with-title="false"
						:with-documents-list="false"
						:autosave="true"
						:save-button-text="t('integration_docuseal', 'Salva configurazione')"
						:background-color="'#f5f5f5'"
						@save="onBuilderSave"
						@load="onBuilderLoad" />
					<div v-if="loadingBuilder" class="builder-loading">
						<NcLoadingIcon :size="44" />
						<p>{{ t('integration_docuseal', 'Caricamento editor...') }}</p>
					</div>
				</div>
			</div>

			<!-- ============ STEP: CONFIG / READY ============ -->
			<div v-else class="config-view">
				<h2>{{ t('integration_docuseal', 'Richiedi firma') }}</h2>
				<p class="subtitle">
					{{ t('integration_docuseal', 'Documento:') }} <strong>{{ fileName }}</strong>
				</p>

				<!-- Signing mode selector -->
				<div class="mode-selector">
					<NcCheckboxRadioSwitch
						:model-value="signMode"
						value="direct"
						name="sign-mode"
						type="radio"
						@update:model-value="onModeChange($event)">
						{{ t('integration_docuseal', 'Invio diretto PDF') }}
					</NcCheckboxRadioSwitch>
					<NcCheckboxRadioSwitch
						:model-value="signMode"
						value="template"
						name="sign-mode"
						type="radio"
						@update:model-value="onModeChange($event)">
						{{ t('integration_docuseal', 'Usa template DocuSeal') }}
					</NcCheckboxRadioSwitch>
				</div>

				<!-- ===== DIRECT MODE: Field Builder CTA ===== -->
				<div v-if="signMode === 'direct'" class="builder-cta">
					<div v-if="configuredTemplateId" class="builder-configured">
						<div class="configured-badge">
							<CheckIcon :size="20" />
							<span>{{ t('integration_docuseal', 'Campi configurati') }}</span>
						</div>
						<NcButton type="secondary" @click="openBuilder">
							<template #icon>
								<PencilIcon :size="20" />
							</template>
							{{ t('integration_docuseal', 'Modifica campi') }}
						</NcButton>
					</div>
					<div v-else class="builder-prompt" @click="openBuilder">
						<div class="builder-prompt-icon">
							<DocuSealIcon :size="32" />
						</div>
						<div class="builder-prompt-text">
							<strong>{{ t('integration_docuseal', 'Configura campi firma') }}</strong>
							<p>{{ t('integration_docuseal', 'Trascina e posiziona firma, data, testo e altri campi sul documento') }}</p>
						</div>
						<div class="builder-prompt-arrow">
							<ArrowRightIcon :size="24" />
						</div>
					</div>
				</div>

				<!-- ===== TEMPLATE MODE: Template Selector ===== -->
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
					<MultiselectWho v-model="recipients" />
					<p class="hint">
						{{ t('integration_docuseal', 'Cerca utenti Nextcloud o inserisci indirizzi email') }}
					</p>
				</div>

				<!-- Options -->
				<div class="options-section">
					<NcCheckboxRadioSwitch :model-value="sendEmail" @update:model-value="sendEmail = $event">
						{{ t('integration_docuseal', 'Invia email di notifica ai destinatari') }}
					</NcCheckboxRadioSwitch>
					<NcCheckboxRadioSwitch :model-value="showEmbedOption" @update:model-value="showEmbedOption = $event">
						{{ t('integration_docuseal', 'Abilita firma embedded (in-app)') }}
					</NcCheckboxRadioSwitch>
				</div>

				<!-- Expiry date -->
				<div class="expiry-section">
					<NcCheckboxRadioSwitch :model-value="hasExpiry" @update:model-value="hasExpiry = $event">
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
					<NcCheckboxRadioSwitch :model-value="customMessage" @update:model-value="customMessage = $event">
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
		</div>
	</NcModal>
</template>

<script>
import NcModal from '@nextcloud/vue/components/NcModal'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { DocusealBuilder } from '@docuseal/vue'
import { generateUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'
import { showSuccess, showError } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'
import MultiselectWho from './MultiselectWho.vue'
import DocuSealIcon from '../icons/DocuSealIcon.vue'

// Inline MDI icons to avoid extra dependencies
const ArrowLeftIcon = {
	props: { size: { type: Number, default: 24 } },
	template: '<svg :width="size" :height="size" viewBox="0 0 24 24"><path fill="currentColor" d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>',
}
const ArrowRightIcon = {
	props: { size: { type: Number, default: 24 } },
	template: '<svg :width="size" :height="size" viewBox="0 0 24 24"><path fill="currentColor" d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8-8-8z"/></svg>',
}
const CheckIcon = {
	props: { size: { type: Number, default: 24 } },
	template: '<svg :width="size" :height="size" viewBox="0 0 24 24"><path fill="currentColor" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>',
}
const PencilIcon = {
	props: { size: { type: Number, default: 24 } },
	template: '<svg :width="size" :height="size" viewBox="0 0 24 24"><path fill="currentColor" d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 000-1.41l-2.34-2.34a1 1 0 00-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>',
}

export default {
	name: 'DocuSealModal',

	components: {
		NcModal,
		NcButton,
		NcSelect,
		NcCheckboxRadioSwitch,
		NcLoadingIcon,
		DocusealBuilder,
		MultiselectWho,
		DocuSealIcon,
		ArrowLeftIcon,
		ArrowRightIcon,
		CheckIcon,
		PencilIcon,
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
			step: 'config', // 'config' | 'builder' | 'ready'
			signMode: 'direct',
			// Builder
			builderToken: null,
			builderHost: '',
			configuredTemplateId: null,
			loadingBuilder: false,
			// Template mode
			selectedTemplate: null,
			templateDetail: null,
			templates: [],
			loadingTemplates: false,
			// Recipients & options
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
		t,
		close() {
			this.show = false
			this.$emit('close')
		},

		onModeChange(val) {
			this.signMode = val
			// Reset builder state when switching modes
			if (val !== 'direct') {
				this.configuredTemplateId = null
				this.builderToken = null
			}
		},

		// ======== BUILDER METHODS ========

		async openBuilder() {
			this.loadingBuilder = true
			this.step = 'builder'
			try {
				const url = generateUrl('/apps/integration_docuseal/builder-token/{fileId}', { fileId: this.fileId })
				const response = await axios.get(url)
				this.builderToken = response.data.token
				// Extract hostname for the builder component
				const serverUrl = response.data.serverUrl
				this.builderHost = serverUrl.replace(/^https?:\/\//, '')
			} catch (e) {
				showError(t('integration_docuseal', 'Errore nel caricamento dell\'editor'))
				console.error('Builder token error:', e)
				this.step = this.configuredTemplateId ? 'ready' : 'config'
			}
			this.loadingBuilder = false
		},

		onBuilderLoad(data) {
			this.loadingBuilder = false
		},

		onBuilderSave(data) {
			if (data && data.id) {
				this.configuredTemplateId = data.id
			}
			this.step = 'ready'
			showSuccess(t('integration_docuseal', 'Configurazione campi salvata!'))
		},

		exitBuilder() {
			this.step = this.configuredTemplateId ? 'ready' : 'config'
		},

		// ======== TEMPLATE METHODS ========

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

		// ======== SUBMIT ========

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
					if (this.configuredTemplateId) {
						params.builderTemplateId = this.configuredTemplateId
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
}

.docuseal-modal.builder-active {
	padding: 0;
	height: 100%;
	display: flex;
	flex-direction: column;
}

.config-view {
	max-height: 80vh;
	overflow-y: auto;
}

/* ======== BUILDER VIEW ======== */
.builder-view {
	display: flex;
	flex-direction: column;
	height: 90vh;
}

.builder-toolbar {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 12px 16px;
	border-bottom: 1px solid var(--color-border);
	background: var(--color-main-background);
	flex-shrink: 0;
}

.builder-title {
	margin: 0;
	font-size: 1.1em;
	font-weight: 600;
}

.builder-toolbar-spacer {
	flex: 1;
}

.builder-container {
	flex: 1;
	position: relative;
	overflow: hidden;
}

.builder-container :deep(docuseal-builder) {
	display: block;
	width: 100%;
	height: 100%;
}

.builder-container :deep(iframe) {
	width: 100%;
	height: 100%;
	border: none;
}

.builder-loading {
	position: absolute;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 16px;
	background: var(--color-main-background);
	z-index: 10;
}

.builder-loading p {
	color: var(--color-text-maxcontrast);
	font-size: 1em;
}

/* ======== BUILDER CTA (Configure Fields Button) ======== */
.builder-cta {
	margin-bottom: 20px;
}

.builder-prompt {
	display: flex;
	align-items: center;
	gap: 16px;
	padding: 16px 20px;
	border: 2px dashed var(--color-primary-element);
	border-radius: var(--border-radius-large);
	cursor: pointer;
	transition: all 0.2s ease;
	background: var(--color-primary-element-light);
}

.builder-prompt:hover {
	background: var(--color-primary-element);
	color: white;
	border-color: var(--color-primary-element);
	transform: translateY(-1px);
	box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.builder-prompt:hover .builder-prompt-text p {
	color: rgba(255, 255, 255, 0.85);
}

.builder-prompt-icon {
	flex-shrink: 0;
	width: 48px;
	height: 48px;
	display: flex;
	align-items: center;
	justify-content: center;
	border-radius: 12px;
	background: var(--color-primary-element);
	color: white;
}

.builder-prompt:hover .builder-prompt-icon {
	background: rgba(255, 255, 255, 0.2);
}

.builder-prompt-text {
	flex: 1;
}

.builder-prompt-text strong {
	display: block;
	font-size: 1.05em;
	margin-bottom: 2px;
}

.builder-prompt-text p {
	margin: 0;
	font-size: 0.88em;
	color: var(--color-text-maxcontrast);
	transition: color 0.2s ease;
}

.builder-prompt-arrow {
	flex-shrink: 0;
	opacity: 0.6;
}

.builder-configured {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 12px 16px;
	border-radius: var(--border-radius-large);
	background: var(--color-success-light, #e8f5e9);
	border: 1px solid var(--color-success, #4caf50);
}

.configured-badge {
	display: flex;
	align-items: center;
	gap: 8px;
	font-weight: 600;
	color: var(--color-success, #2e7d32);
}

/* ======== FORM SECTIONS ======== */
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
