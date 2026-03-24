<template>
	<NcSelect
		v-model="selected"
		:options="options"
		:multiple="true"
		:taggable="true"
		:loading="loading"
		:placeholder="t('integration_docuseal', 'Cerca utenti o inserisci email...')"
		label="displayName"
		track-by="trackId"
		:tag-placeholder="t('integration_docuseal', 'Premi Invio per aggiungere questa email')"
		:create-option="createEmailOption"
		@search="onSearch"
		@update:model-value="onInput">
		<template #option="slotProps">
			<div v-if="slotProps" class="option-row">
				<NcAvatar v-if="slotProps.type === 'user'" :user="slotProps.email" :size="24" />
				<span v-else class="email-icon">✉</span>
				<span class="option-name">{{ slotProps.displayName }}</span>
				<span v-if="slotProps.email && slotProps.type === 'user'" class="option-email">({{ slotProps.email }})</span>
			</div>
		</template>
		<template #tag="{ option }">
			<span v-if="option" class="tag-item">
				<NcAvatar v-if="option.type === 'user'" :user="option.id" :size="20" />
				<span v-else class="email-icon-small">✉</span>
				{{ option.displayName }}
			</span>
		</template>
	</NcSelect>
</template>

<script>
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'

const EMAIL_REGEX = /^\w+([.+-]?\w+)*@\w+([.-]?\w+)*(\.\w{2,})+$/

let searchTimeout = null

export default {
	name: 'MultiselectWho',

	components: {
		NcSelect,
		NcAvatar,
	},

	props: {
		modelValue: {
			type: Array,
			default: () => [],
		},
	},

	emits: ['update:modelValue'],

	data() {
		return {
			selected: this.modelValue,
			options: [],
			loading: false,
		}
	},

	watch: {
		modelValue(val) {
			this.selected = val
		},
	},

	methods: {
		t,
		onInput(val) {
			this.$emit('update:modelValue', val)
		},

		onSearch(query) {
			if (searchTimeout) {
				clearTimeout(searchTimeout)
			}
			if (!query || query.length < 2) {
				return
			}
			searchTimeout = setTimeout(() => {
				this.search(query)
			}, 300)
		},

		async search(query) {
			this.loading = true
			try {
				const url = generateOcsUrl('core/autocomplete/get', 2)
				const response = await axios.get(url, {
					params: {
						search: query,
						itemType: '',
						itemId: '',
						shareTypes: [0], // users only
						limit: 20,
					},
				})

				this.options = (response.data.ocs?.data || []).map(item => ({
					id: item.id,
					displayName: item.label || item.id,
					type: 'user',
					email: item.id, // Will resolve server-side
					trackId: 'user-' + item.id,
				}))

				// Also add email option if the query looks like an email
				if (EMAIL_REGEX.test(query)) {
					const exists = this.options.some(o => o.email === query)
					if (!exists) {
						this.options.push({
							id: query,
							displayName: query,
							type: 'email',
							email: query,
							trackId: 'email-' + query,
						})
					}
				}
			} catch (e) {
				console.error('Error searching users:', e)
			}
			this.loading = false
		},

		createEmailOption(query) {
			if (EMAIL_REGEX.test(query)) {
				return {
					id: query,
					displayName: query,
					type: 'email',
					email: query,
					trackId: 'email-' + query,
				}
			}
			return null
		},
	},
}
</script>

<style scoped>
.option-row {
	display: flex;
	align-items: center;
	gap: 8px;
}

.option-name {
	font-weight: 500;
}

.option-email {
	color: var(--color-text-maxcontrast);
	font-size: 0.9em;
}

.email-icon {
	width: 24px;
	height: 24px;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 16px;
}

.email-icon-small {
	width: 20px;
	height: 20px;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	font-size: 14px;
}

.tag-item {
	display: inline-flex;
	align-items: center;
	gap: 4px;
}
</style>
