<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { __, sprintf } from '@wordpress/i18n'
import { useSettingsStore } from '@/stores/settings.js'
import { usePipelineStore } from '@/stores/pipeline.js'
import { useTagsStore } from '@/stores/tags.js'
import { useNotify } from '@/composables/useNotify.js'
import { useConfirm } from '@/composables/useConfirm.js'
import { apiFetch } from '@/api/client.js'
import Spinner from '@/components/ui/Spinner.vue'
import RichTextEditor from '@/components/ui/RichTextEditor.vue'

const settingsStore = useSettingsStore()
const pipelineStore = usePipelineStore()
const tagsStore     = useTagsStore()
const notify        = useNotify()
const { confirm }   = useConfirm()

const activeTab = ref( 'general' )

const TABS = [
  { key: 'general',      label: () => __( 'General',      'pressio-crm' ) },
  { key: 'email',        label: () => __( 'Email',        'pressio-crm' ) },
  { key: 'smtp',         label: () => __( 'SMTP',         'pressio-crm' ) },
  { key: 'pipeline',     label: () => __( 'Pipeline',     'pressio-crm' ) },
  { key: 'integrations', label: () => __( 'Integrations', 'pressio-crm' ) },
  { key: 'data',         label: () => __( 'Data',         'pressio-crm' ) },
  { key: 'tags',         label: () => __( 'Tags',         'pressio-crm' ) },
]

// ── General ──────────────────────────────────────────────────────────────────
const general = reactive( { currency: 'USD', date_format: 'Y-m-d', company_name: '' } )
const savingGeneral = ref( false )

// ── Email ─────────────────────────────────────────────────────────────────────
const email = reactive( {
  from_name:        '',
  from_email:       '',
  reply_to:         '',
  header_type:      'none',
  header_logo_url:  '',
  header_logo_id:   0,
  header_custom:    '',
  accent_color:     '#2271b1',
  footer:           '',
} )
const savingEmail    = ref( false )
const showPreview    = ref( false )

const EMAIL_MERGE_TAGS = [
  { label: '{{contact.first_name}}', value: '{{contact.first_name}}' },
  { label: '{{contact.last_name}}',  value: '{{contact.last_name}}'  },
  { label: '{{contact.email}}',      value: '{{contact.email}}'      },
  { label: '{{business.name}}',      value: '{{business.name}}'      },
  { label: '{{business.email}}',     value: '{{business.email}}'     },
]

function safeColor( color ) {
  return /^#[0-9a-fA-F]{3,8}$/.test( color ) ? color : '#6366f1'
}

function escHtml( str ) {
  return String( str )
    .replace( /&/g, '&amp;' )
    .replace( /</g, '&lt;' )
    .replace( />/g, '&gt;' )
    .replace( /"/g, '&quot;' )
}

function safeHexColor( color, fallback = '#2271b1' ) {
  return /^#[0-9a-fA-F]{3,8}$/.test( color ) ? color : fallback
}

const emailPreviewHtml = computed( () => {
  const accent = safeHexColor( email.accent_color )

  let header = ''
  if ( email.header_type === 'logo' && email.header_logo_url ) {
    const safeUrl = escHtml( email.header_logo_url )
    header = `<tr><td align="center" style="background:${accent};padding:20px">
      <img src="${safeUrl}" style="max-height:60px;max-width:220px;display:block;margin:0 auto" /></td></tr>`
  } else if ( email.header_type === 'custom' && email.header_custom ) {
    // header_custom is intentional rich-text — kept as-is but header_type is enum-validated
    header = `<tr><td>${escHtml( email.header_custom )}</td></tr>`
  }

  const footer = email.footer
    ? `<tr><td style="padding:16px 24px;border-top:1px solid #e2e8f0;font-size:12px;color:#666">${escHtml( email.footer )}</td></tr>`
    : ''

  return `<!DOCTYPE html><html><head><meta charset="utf-8">
<style>body{margin:0;font-family:Arial,sans-serif;background:#f4f5f7;font-size:14px}
a{color:${accent}}</style></head>
<body><table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;padding:16px 0">
<tr><td align="center"><table width="560" cellpadding="0" cellspacing="0"
style="background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.1)">
${header}
<tr><td style="padding:24px 28px;line-height:1.6">
<p>Hi <strong>John</strong>,</p>
<p>This is a preview of how your emails will look when sent from Pressio CRM.</p>
<p>You can customise the header, footer, and accent colour using the settings on the left.</p>
</td></tr>
${footer}
</table></td></tr></table></body></html>`
} )

function openMediaLibrary() {
  if ( ! window.wp?.media ) return
  const frame = window.wp.media( {
    title:    __( 'Select Logo', 'pressio-crm' ),
    button:   { text: __( 'Use this image', 'pressio-crm' ) },
    multiple: false,
    library:  { type: 'image' },
  } )
  frame.on( 'select', () => {
    const att = frame.state().get( 'selection' ).first().toJSON()
    email.header_logo_url = att.url
    email.header_logo_id  = att.id
  } )
  frame.open()
}

async function saveEmail() {
  savingEmail.value = true
  try {
    await settingsStore.save( {
      email_from_name:       email.from_name,
      email_from_email:      email.from_email,
      email_reply_to:        email.reply_to,
      email_header_type:     email.header_type,
      email_header_logo_url: email.header_logo_url,
      email_header_logo_id:  email.header_logo_id,
      email_header_custom:   email.header_custom,
      email_accent_color:    email.accent_color,
      email_footer:          email.footer,
    } )
  } catch {} finally {
    savingEmail.value = false
  }
}

// ── Pipeline ──────────────────────────────────────────────────────────────────
const newPipelineName = ref( '' )
const savingPipeline  = ref( false )

// ── CSV Export ────────────────────────────────────────────────────────────────
const exporting = ref( false )

async function exportCsv() {
  exporting.value = true
  try {
    const res  = await apiFetch( 'contacts/export' )
    const blob = new Blob( [ res.csv ], { type: 'text/csv;charset=utf-8;' } )
    const url  = URL.createObjectURL( blob )
    const a    = document.createElement( 'a' )
    a.href     = url
    a.download = res.filename || 'contacts.csv'
    a.click()
    URL.revokeObjectURL( url )
    notify.success( __( 'Contacts exported', 'pressio-crm' ) )
  } catch {
    notify.error( __( 'Export failed', 'pressio-crm' ) )
  } finally {
    exporting.value = false
  }
}

// ── CSV Import ────────────────────────────────────────────────────────────────
const importFile        = ref( null )
const importOnDuplicate = ref( 'skip' )
const importing         = ref( false )
const importResult      = ref( null )

function onFileChange( e ) {
  importFile.value  = e.target.files[0] || null
  importResult.value = null
}

async function runImport() {
  if ( ! importFile.value ) return
  importing.value = true
  importResult.value = null
  try {
    const text = await importFile.value.text()
    importResult.value = await apiFetch( 'contacts/import', {
      method: 'POST',
      body:   { csv: text, on_duplicate: importOnDuplicate.value },
    } )
    notify.success( __( 'Import complete', 'pressio-crm' ) )
  } catch {
    notify.error( __( 'Import failed — check your CSV format', 'pressio-crm' ) )
  } finally {
    importing.value = false
  }
}

// ── Lifecycle ─────────────────────────────────────────────────────────────────
onMounted( async () => {
  await settingsStore.fetch()
  await pipelineStore.fetchAll()
  await tagsStore.fetchAll()
  syncFromStore( settingsStore.data )
} )

watch( () => settingsStore.data, syncFromStore, { deep: true } )

function syncFromStore( data ) {
  if ( ! data ) return
  general.currency     = data.currency     || 'USD'
  general.date_format  = data.date_format  || 'Y-m-d'
  general.company_name = data.company_name || ''

  email.from_name       = data.email_from_name       || ''
  email.from_email      = data.email_from_email      || ''
  email.reply_to        = data.email_reply_to        || ''
  email.header_type     = data.email_header_type     || 'none'
  email.header_logo_url = data.email_header_logo_url || ''
  email.header_logo_id  = data.email_header_logo_id  || 0
  email.header_custom   = data.email_header_custom   || ''
  email.accent_color    = data.email_accent_color    || '#2271b1'
  email.footer          = data.email_footer          || ''
}

async function saveGeneral() {
  savingGeneral.value = true
  try {
    await settingsStore.save( { ...general } )
  } catch {} finally {
    savingGeneral.value = false
  }
}

async function addPipeline() {
  if ( ! newPipelineName.value.trim() ) return
  savingPipeline.value = true
  try {
    await pipelineStore.createPipeline( { name: newPipelineName.value.trim() } )
    notify.success( __( 'Pipeline created', 'pressio-crm' ) )
    newPipelineName.value = ''
  } catch {} finally {
    savingPipeline.value = false
  }
}

async function deletePipeline( pipeline ) {
  const ok = await confirm( {
    title:        __( 'Delete pipeline?', 'pressio-crm' ),
    /* translators: %s is the pipeline name */
    message:      sprintf( __( 'This will permanently delete "%s" and all its stages.', 'pressio-crm' ), pipeline.name ),
    confirmLabel: __( 'Delete', 'pressio-crm' ),
    danger:       true,
  } )
  if ( ! ok ) return
  try {
    await pipelineStore.deletePipeline( pipeline.id )
    notify.success( __( 'Pipeline deleted', 'pressio-crm' ) )
  } catch {}
}

// ── Tags ──────────────────────────────────────────────────────────────────────
const showTagForm  = ref( false )
const savingTag    = ref( false )
const newTag       = reactive( { name: '', color: '#6366f1' } )
const editingTagId = ref( null )
const editTag      = reactive( { name: '', color: '' } )

function startEdit( tag ) {
  editingTagId.value = tag.id
  editTag.name  = tag.name
  editTag.color = tag.color
}

function cancelEdit() {
  editingTagId.value = null
}

async function saveNewTag() {
  if ( ! newTag.name.trim() ) return
  savingTag.value = true
  try {
    await tagsStore.create( { name: newTag.name.trim(), color: newTag.color } )
    notify.success( __( 'Tag created', 'pressio-crm' ) )
    newTag.name  = ''
    newTag.color = '#6366f1'
    showTagForm.value = false
  } catch {} finally {
    savingTag.value = false
  }
}

async function saveEditTag( id ) {
  if ( ! editTag.name.trim() ) return
  savingTag.value = true
  try {
    await tagsStore.update( id, { name: editTag.name.trim(), color: editTag.color } )
    notify.success( __( 'Tag updated', 'pressio-crm' ) )
    editingTagId.value = null
  } catch {} finally {
    savingTag.value = false
  }
}

async function deleteTag( tag ) {
  const ok = await confirm( {
    title:        __( 'Delete tag?', 'pressio-crm' ),
    /* translators: %s is the tag name */
    message:      sprintf( __( 'This will remove "%s" from all contacts.', 'pressio-crm' ), tag.name ),
    confirmLabel: __( 'Delete', 'pressio-crm' ),
    danger:       true,
  } )
  if ( ! ok ) return
  try {
    await tagsStore.remove( tag.id )
    notify.success( __( 'Tag deleted', 'pressio-crm' ) )
  } catch {}
}

// ── SMTP ──────────────────────────────────────────────────────────────────────
const smtpInfo        = ref( null )
const installingSmtp  = ref( false )

async function installFluentSmtp() {
  installingSmtp.value = true
  try {
    const res = await apiFetch( 'settings/install-fluent-smtp', { method: 'POST' } )
    notify.success( res.message )
    // Refresh settings so smtpInfo updates to installed state.
    await settingsStore.fetch()
  } catch ( e ) {
    notify.error( e.message )
  } finally {
    installingSmtp.value = false
  }
}

watch( () => settingsStore.data?.fluentsmtp_info, ( val ) => {
  smtpInfo.value = val || null
}, { immediate: true } )
</script>

<template>
  <div>
    <div class="crm-page-header">
      <h1 class="crm-page-header__title">{{ __( 'Settings', 'pressio-crm' ) }}</h1>
    </div>

    <!-- Tab navigation -->
    <div class="settings-tabs">
      <button
        v-for="tab in TABS"
        :key="tab.key"
        type="button"
        :class="[ 'settings-tab', activeTab === tab.key ? 'settings-tab--active' : '' ]"
        @click="activeTab = tab.key"
      >
        {{ tab.label() }}
      </button>
    </div>

    <div v-if="settingsStore.loading" class="crm-flex-center" style="padding: 48px;">
      <Spinner size="lg" />
    </div>

    <!-- Email Tab -->
    <div v-else-if="activeTab === 'email'" class="tab-content">
      <div class="email-settings-layout">

        <!-- Left: form fields -->
        <div class="email-settings-form">

          <!-- Sender Identity -->
          <div class="crm-card" style="margin-bottom: 16px;">
            <h2 class="tab-content__title">{{ __( 'Sender Identity', 'pressio-crm' ) }}</h2>
            <p class="crm-help-text" style="margin: -12px 0 16px;">
              {{ __( 'Name and address shown in your recipients\' inbox.', 'pressio-crm' ) }}
            </p>

            <div class="crm-form-group">
              <label class="crm-label" for="e-from-name">{{ __( 'From Name', 'pressio-crm' ) }}</label>
              <input id="e-from-name" v-model="email.from_name" type="text" class="crm-input"
                :placeholder="__( 'e.g. John from Acme', 'pressio-crm' )" />
            </div>

            <div class="crm-form-group">
              <label class="crm-label" for="e-from-email">{{ __( 'From Email', 'pressio-crm' ) }}</label>
              <input id="e-from-email" v-model="email.from_email" type="email" class="crm-input"
                :placeholder="__( 'e.g. john@acme.com', 'pressio-crm' )" />
              <p class="crm-help-text">{{ __( 'Should match your domain to avoid spam filters.', 'pressio-crm' ) }}</p>
            </div>

            <div class="crm-form-group">
              <label class="crm-label" for="e-reply-to">{{ __( 'Reply-To Email', 'pressio-crm' ) }}</label>
              <input id="e-reply-to" v-model="email.reply_to" type="email" class="crm-input"
                :placeholder="__( 'Optional — defaults to From Email', 'pressio-crm' )" />
            </div>
          </div>

          <!-- Branding -->
          <div class="crm-card" style="margin-bottom: 16px;">
            <h2 class="tab-content__title">{{ __( 'Email Branding', 'pressio-crm' ) }}</h2>

            <div class="crm-form-group">
              <label class="crm-label">{{ __( 'Accent Colour', 'pressio-crm' ) }}</label>
              <div style="display: flex; align-items: center; gap: 10px;">
                <input v-model="email.accent_color" type="color" class="color-picker" />
                <span class="crm-help-text" style="margin: 0;">{{ email.accent_color }}</span>
              </div>
            </div>

            <div class="crm-form-group">
              <label class="crm-label">{{ __( 'Email Header', 'pressio-crm' ) }}</label>
              <div class="radio-group">
                <label class="radio-option">
                  <input v-model="email.header_type" type="radio" value="none" />
                  {{ __( 'None', 'pressio-crm' ) }}
                </label>
                <label class="radio-option">
                  <input v-model="email.header_type" type="radio" value="logo" />
                  {{ __( 'Logo', 'pressio-crm' ) }}
                </label>
                <label class="radio-option">
                  <input v-model="email.header_type" type="radio" value="custom" />
                  {{ __( 'Custom HTML', 'pressio-crm' ) }}
                </label>
              </div>

              <!-- Logo upload -->
              <div v-if="email.header_type === 'logo'" style="margin-top: 12px;">
                <div v-if="email.header_logo_url" style="margin-bottom: 8px;">
                  <img :src="email.header_logo_url" style="max-height: 60px; max-width: 200px; display: block; border: 1px solid var(--crm-border); border-radius: 4px; padding: 4px;" />
                </div>
                <button type="button" class="crm-btn crm-btn--secondary crm-btn--sm" @click="openMediaLibrary">
                  <span class="dashicons dashicons-upload" aria-hidden="true" />
                  {{ email.header_logo_url ? __( 'Change Logo', 'pressio-crm' ) : __( 'Upload Logo', 'pressio-crm' ) }}
                </button>
                <p v-if="! window?.wp?.media" class="crm-help-text" style="margin-top: 6px;">
                  {{ __( 'Media library not available. Enter URL manually:', 'pressio-crm' ) }}
                  <input v-model="email.header_logo_url" type="url" class="crm-input" style="margin-top: 6px;" />
                </p>
              </div>

              <!-- Custom HTML -->
              <div v-if="email.header_type === 'custom'" style="margin-top: 12px;">
                <RichTextEditor
                  v-model="email.header_custom"
                  :placeholder="__( 'Enter header content…', 'pressio-crm' )"
                  min-height="80px"
                />
              </div>
            </div>
          </div>

          <!-- Email Footer -->
          <div class="crm-card" style="margin-bottom: 16px;">
            <h2 class="tab-content__title">{{ __( 'Email Footer', 'pressio-crm' ) }}</h2>
            <p class="crm-help-text" style="margin: -12px 0 16px;">
              {{ __( 'Appended to every email. Use variables like', 'pressio-crm' ) }} <code>&#123;&#123;business.name&#125;&#125;</code>.
            </p>
            <RichTextEditor
              v-model="email.footer"
              :placeholder="__( 'e.g. Acme Inc, 123 Main St, Springfield', 'pressio-crm' )"
              :merge-tags="EMAIL_MERGE_TAGS"
              min-height="100px"
            />
          </div>

          <button
            type="button"
            class="crm-btn crm-btn--primary"
            :disabled="savingEmail || settingsStore.saving"
            @click="saveEmail"
          >
            <Spinner v-if="savingEmail || settingsStore.saving" size="sm" />
            {{ __( 'Save Email Settings', 'pressio-crm' ) }}
          </button>
        </div>

        <!-- Right: live preview -->
        <div class="email-preview-panel">
          <div class="email-preview-header">
            <span class="tab-content__title" style="margin: 0; font-size: 14px;">{{ __( 'Live Preview', 'pressio-crm' ) }}</span>
            <button type="button" class="crm-btn crm-btn--ghost crm-btn--sm" @click="showPreview = !showPreview">
              {{ showPreview ? __( 'Hide', 'pressio-crm' ) : __( 'Show', 'pressio-crm' ) }}
            </button>
          </div>
          <iframe
            v-show="showPreview"
            class="email-preview-iframe"
            :srcdoc="emailPreviewHtml"
            sandbox=""
            title="Email preview"
          />
          <p v-if="!showPreview" class="crm-help-text" style="padding: 16px; margin: 0;">
            {{ __( 'Click "Show" to see a live preview of your email template.', 'pressio-crm' ) }}
          </p>
        </div>
      </div>
    </div>

    <!-- General Tab -->
    <div v-else-if="activeTab === 'general'" class="crm-card tab-content">
      <h2 class="tab-content__title">{{ __( 'General Settings', 'pressio-crm' ) }}</h2>

      <div class="crm-form-group">
        <label class="crm-label" for="s-company">{{ __( 'Company Name', 'pressio-crm' ) }}</label>
        <input id="s-company" v-model="general.company_name" type="text" class="crm-input" style="max-width: 400px;" />
      </div>

      <div class="crm-form-group">
        <label class="crm-label" for="s-currency">{{ __( 'Currency', 'pressio-crm' ) }}</label>
        <select id="s-currency" v-model="general.currency" class="crm-select" style="max-width: 200px;">
          <option value="USD">USD ($)</option>
          <option value="EUR">EUR (€)</option>
          <option value="GBP">GBP (£)</option>
          <option value="AUD">AUD (A$)</option>
          <option value="CAD">CAD (C$)</option>
        </select>
      </div>

      <div class="crm-form-group">
        <label class="crm-label" for="s-date-format">{{ __( 'Date Format', 'pressio-crm' ) }}</label>
        <select id="s-date-format" v-model="general.date_format" class="crm-select" style="max-width: 200px;">
          <option value="Y-m-d">Y-m-d (2026-03-26)</option>
          <option value="d/m/Y">d/m/Y (26/03/2026)</option>
          <option value="m/d/Y">m/d/Y (03/26/2026)</option>
        </select>
      </div>

      <div class="tab-content__footer">
        <button
          type="button"
          class="crm-btn crm-btn--primary"
          :disabled="savingGeneral || settingsStore.saving"
          @click="saveGeneral"
        >
          <Spinner v-if="savingGeneral || settingsStore.saving" size="sm" />
          {{ __( 'Save Settings', 'pressio-crm' ) }}
        </button>
      </div>
    </div>

    <!-- SMTP Tab -->
    <div v-else-if="activeTab === 'smtp'" class="tab-content">

      <!-- FluentSMTP not installed -->
      <div v-if="smtpInfo && ! smtpInfo.installed" class="crm-card smtp-upsell-card">
        <div class="smtp-upsell-header">
          <h2 class="tab-content__title" style="margin: 0;">{{ __( 'SMTP / Email Sending', 'pressio-crm' ) }}</h2>
        </div>
        <p class="crm-text-muted smtp-upsell-desc">
          {{ __( 'By default, WordPress sends emails using the server\'s built-in mail function, which is often blocked or flagged as spam. Install FluentSMTP to connect a real email service so your CRM emails are delivered reliably.', 'pressio-crm' ) }}
        </p>
        <div class="smtp-features">
          <div class="smtp-features__col">
            <p class="smtp-features__heading">{{ __( 'Supported providers', 'pressio-crm' ) }}</p>
            <ul class="smtp-features__list">
              <li>Amazon SES</li>
              <li>Mailgun</li>
              <li>SendGrid</li>
              <li>Postmark</li>
              <li>{{ __( '+ Any SMTP provider', 'pressio-crm' ) }}</li>
            </ul>
          </div>
          <div class="smtp-features__col">
            <p class="smtp-features__heading">{{ __( 'What you get', 'pressio-crm' ) }}</p>
            <ul class="smtp-features__list">
              <li>{{ __( 'Reliable email delivery', 'pressio-crm' ) }}</li>
              <li>{{ __( 'Email logging', 'pressio-crm' ) }}</li>
              <li>{{ __( 'Multiple sending connections', 'pressio-crm' ) }}</li>
              <li>{{ __( 'Free plugin, no monthly fees', 'pressio-crm' ) }}</li>
            </ul>
          </div>
        </div>
        <button
          type="button"
          class="crm-btn crm-btn--primary smtp-install-btn"
          :disabled="installingSmtp"
          @click="installFluentSmtp"
        >
          <Spinner v-if="installingSmtp" size="sm" />
          {{ installingSmtp ? __( 'Installing…', 'pressio-crm' ) : __( 'Install FluentSMTP (free)', 'pressio-crm' ) }}
        </button>
      </div>

      <!-- FluentSMTP installed but not configured -->
      <div v-else-if="smtpInfo && smtpInfo.installed && ! smtpInfo.configured" class="crm-card smtp-status-card smtp-status-card--warn">
        <div class="smtp-status-icon">⚠</div>
        <div>
          <h2 class="tab-content__title" style="margin: 0 0 4px;">{{ __( 'FluentSMTP is installed but not configured', 'pressio-crm' ) }}</h2>
          <p class="crm-text-muted" style="margin: 0 0 16px; font-size: 13px;">
            {{ __( 'You need to add at least one SMTP connection before emails will be sent reliably.', 'pressio-crm' ) }}
          </p>
          <a
            :href="smtpInfo.config_url"
            target="_blank"
            rel="noopener"
            class="crm-btn crm-btn--primary"
          >
            {{ __( 'Configure FluentSMTP →', 'pressio-crm' ) }}
          </a>
        </div>
      </div>

      <!-- FluentSMTP installed and configured -->
      <div v-else-if="smtpInfo && smtpInfo.installed && smtpInfo.configured" class="crm-card smtp-status-card smtp-status-card--ok">
        <div class="smtp-status-icon smtp-status-icon--ok">✓</div>
        <div>
          <h2 class="tab-content__title" style="margin: 0 0 4px;">{{ __( 'FluentSMTP is active', 'pressio-crm' ) }}</h2>
          <p class="crm-text-muted" style="margin: 0 0 4px; font-size: 13px;">
            {{ __( 'Your emails are being sent through FluentSMTP.', 'pressio-crm' ) }}
          </p>
          <p v-if="smtpInfo.verified_senders?.length" class="crm-text-muted" style="margin: 0 0 16px; font-size: 12px;">
            {{ __( 'Verified senders:', 'pressio-crm' ) }}
            <strong>{{ smtpInfo.verified_senders.join( ', ' ) }}</strong>
          </p>
          <a
            :href="smtpInfo.config_url"
            target="_blank"
            rel="noopener"
            class="crm-btn crm-btn--ghost crm-btn--sm"
          >
            {{ __( 'Manage FluentSMTP settings →', 'pressio-crm' ) }}
          </a>
        </div>
      </div>

      <!-- Loading state -->
      <div v-else class="crm-flex-center" style="padding: 48px;">
        <Spinner size="lg" />
      </div>

    </div>

    <!-- Pipeline Tab -->
    <div v-else-if="activeTab === 'pipeline'" class="tab-content">
      <div class="crm-card" style="margin-bottom: 16px;">
        <h2 class="tab-content__title">{{ __( 'Pipelines', 'pressio-crm' ) }}</h2>

        <div v-if="pipelineStore.loading" class="crm-flex-center" style="padding: 24px;">
          <Spinner />
        </div>

        <div v-else>
          <div v-if="pipelineStore.pipelines.length === 0" class="crm-text-muted" style="padding: 16px 0; font-size: 13px;">
            {{ __( 'No pipelines yet.', 'pressio-crm' ) }}
          </div>

          <div v-for="pipeline in pipelineStore.pipelines" :key="pipeline.id" class="pipeline-row">
            <div>
              <p class="pipeline-row__name">{{ pipeline.name }}</p>
              <p class="pipeline-row__stages crm-text-muted">
                {{ ( pipeline.stages || [] ).length }} {{ __( 'stages', 'pressio-crm' ) }}
              </p>
            </div>
            <button
              type="button"
              class="crm-btn crm-btn--danger crm-btn--sm"
              @click="deletePipeline( pipeline )"
            >
              {{ __( 'Delete', 'pressio-crm' ) }}
            </button>
          </div>
        </div>

        <hr class="crm-divider" />

        <div class="add-pipeline-row">
          <input
            v-model="newPipelineName"
            type="text"
            class="crm-input"
            :placeholder="__( 'New pipeline name…', 'pressio-crm' )"
            style="max-width: 300px;"
            @keydown.enter="addPipeline"
          />
          <button
            type="button"
            class="crm-btn crm-btn--primary"
            :disabled="savingPipeline || ! newPipelineName.trim()"
            @click="addPipeline"
          >
            <Spinner v-if="savingPipeline" size="sm" />
            {{ __( 'Add Pipeline', 'pressio-crm' ) }}
          </button>
        </div>

      </div>
    </div>

    <!-- Integrations Tab -->
    <div v-else-if="activeTab === 'integrations'" class="tab-content">
      <div class="crm-card" style="margin-bottom: 16px;">
        <h2 class="tab-content__title">{{ __( 'Contact Form 7 Integration', 'pressio-crm' ) }}</h2>
        <div class="integration-info">
          <span class="dashicons dashicons-yes-alt integration-info__icon" style="color: var(--crm-success);" aria-hidden="true" />
          <div>
            <p class="integration-info__title">{{ __( 'Auto-detected', 'pressio-crm' ) }}</p>
            <p class="integration-info__desc crm-text-muted">
              {{ __( 'When Contact Form 7 is active, form submissions are automatically captured as contacts. No configuration required.', 'pressio-crm' ) }}
            </p>
          </div>
        </div>
      </div>

    </div>

    <!-- Data Tab -->
    <div v-else-if="activeTab === 'data'" class="tab-content">

      <!-- Export -->
      <div class="crm-card" style="margin-bottom: 16px;">
        <h2 class="tab-content__title">{{ __( 'Export Contacts', 'pressio-crm' ) }}</h2>
        <p class="crm-text-muted" style="font-size: 13px; margin: 0 0 16px;">
          {{ __( 'Download all contacts as a CSV file. Includes all fields — name, email, phone, company, address, tags, and notes.', 'pressio-crm' ) }}
        </p>
        <button type="button" class="crm-btn crm-btn--secondary" :disabled="exporting" @click="exportCsv">
          <Spinner v-if="exporting" size="sm" />
          <span v-else class="dashicons dashicons-download" aria-hidden="true" />
          {{ __( 'Export Contacts (CSV)', 'pressio-crm' ) }}
        </button>
      </div>

      <!-- Import -->
      <div class="crm-card" style="margin-bottom: 16px;">
        <h2 class="tab-content__title">{{ __( 'Import Contacts', 'pressio-crm' ) }}</h2>
        <p class="crm-text-muted" style="font-size: 13px; margin: 0 0 16px;">
          {{ __( 'Upload a CSV file to import contacts. The first row should be column headers. Recognised columns: first_name, last_name, email, phone, company, job_title, city, state, country, notes.', 'pressio-crm' ) }}
        </p>

        <div class="crm-form-group">
          <label class="crm-label" for="import-file">{{ __( 'CSV File', 'pressio-crm' ) }}</label>
          <input id="import-file" type="file" accept=".csv,text/csv" class="crm-input" style="padding: 5px;" @change="onFileChange" />
          <p class="crm-help-text">{{ __( 'Maximum 5,000 contacts per import.', 'pressio-crm' ) }}</p>
        </div>

        <div class="crm-form-group">
          <label class="crm-label">{{ __( 'If contact email already exists', 'pressio-crm' ) }}</label>
          <div class="radio-group">
            <label class="radio-option">
              <input v-model="importOnDuplicate" type="radio" value="skip" />
              {{ __( 'Skip (keep existing)', 'pressio-crm' ) }}
            </label>
            <label class="radio-option">
              <input v-model="importOnDuplicate" type="radio" value="update" />
              {{ __( 'Update with CSV data', 'pressio-crm' ) }}
            </label>
          </div>
        </div>

        <button
          type="button"
          class="crm-btn crm-btn--primary"
          :disabled="! importFile || importing"
          @click="runImport"
        >
          <Spinner v-if="importing" size="sm" />
          <span v-else class="dashicons dashicons-upload" aria-hidden="true" />
          {{ __( 'Import Contacts', 'pressio-crm' ) }}
        </button>

        <!-- Import results -->
        <div v-if="importResult" class="import-result">
          <div class="import-result__row import-result__row--success">
            <span class="dashicons dashicons-yes" aria-hidden="true" />
            {{ importResult.imported }} {{ __( 'contacts imported', 'pressio-crm' ) }}
          </div>
          <div v-if="importResult.updated" class="import-result__row import-result__row--info">
            <span class="dashicons dashicons-update" aria-hidden="true" />
            {{ importResult.updated }} {{ __( 'contacts updated', 'pressio-crm' ) }}
          </div>
          <div v-if="importResult.skipped" class="import-result__row import-result__row--muted">
            <span class="dashicons dashicons-minus" aria-hidden="true" />
            {{ importResult.skipped }} {{ __( 'rows skipped', 'pressio-crm' ) }}
          </div>
        </div>
      </div>
    </div>

    <!-- Tags Tab -->
    <div v-else-if="activeTab === 'tags'" class="tab-content">
      <div class="crm-card">
        <div class="tab-content__header">
          <div>
            <h2 class="tab-content__title">{{ __( 'Tags', 'pressio-crm' ) }}</h2>
            <p class="crm-help-text">{{ __( 'Manage tags used to categorise contacts.', 'pressio-crm' ) }}</p>
          </div>
          <button
            type="button"
            class="crm-btn crm-btn--primary"
            @click="showTagForm = ! showTagForm"
          >
            {{ showTagForm ? __( 'Cancel', 'pressio-crm' ) : __( '+ Add Tag', 'pressio-crm' ) }}
          </button>
        </div>

        <!-- New tag form -->
        <div v-if="showTagForm" class="tag-new-form">
          <input
            v-model="newTag.name"
            type="text"
            class="crm-input"
            :placeholder="__( 'Tag name', 'pressio-crm' )"
            @keyup.enter="saveNewTag"
          />
          <div class="tag-color-field">
            <label class="crm-label">{{ __( 'Colour', 'pressio-crm' ) }}</label>
            <input v-model="newTag.color" type="color" class="tag-color-input" />
          </div>
          <button
            type="button"
            class="crm-btn crm-btn--primary"
            :disabled="savingTag || ! newTag.name.trim()"
            @click="saveNewTag"
          >
            <Spinner v-if="savingTag" size="sm" />
            {{ __( 'Save Tag', 'pressio-crm' ) }}
          </button>
        </div>

        <!-- Tags table -->
        <div v-if="tagsStore.loading" class="crm-flex-center" style="padding: 32px;">
          <Spinner size="lg" />
        </div>

        <div v-else-if="tagsStore.items.length === 0" class="crm-empty-inline">
          {{ __( 'No tags yet. Create your first tag above.', 'pressio-crm' ) }}
        </div>

        <table v-else class="crm-table" style="margin-top: 16px;">
          <thead>
            <tr>
              <th>{{ __( 'Name', 'pressio-crm' ) }}</th>
              <th>{{ __( 'Slug', 'pressio-crm' ) }}</th>
              <th>{{ __( 'Colour', 'pressio-crm' ) }}</th>
              <th style="width: 120px;">{{ __( 'Actions', 'pressio-crm' ) }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="tag in tagsStore.items" :key="tag.id">
              <!-- Edit mode -->
              <template v-if="editingTagId === tag.id">
                <td>
                  <input
                    :ref="el => el && el.focus()"
                    v-model="editTag.name"
                    type="text"
                    class="crm-input crm-input--sm"
                    @keyup.enter="saveEditTag( tag.id )"
                    @keyup.escape="cancelEdit"
                  />
                </td>
                <td class="crm-text-secondary">{{ tag.slug }}</td>
                <td>
                  <input v-model="editTag.color" type="color" class="tag-color-input" />
                </td>
                <td class="crm-table-actions">
                  <button
                    type="button"
                    class="crm-btn crm-btn--primary crm-btn--sm"
                    :disabled="savingTag || ! editTag.name.trim()"
                    @click="saveEditTag( tag.id )"
                  >
                    {{ __( 'Save', 'pressio-crm' ) }}
                  </button>
                  <button
                    type="button"
                    class="crm-btn crm-btn--ghost crm-btn--sm"
                    @click="cancelEdit"
                  >
                    {{ __( 'Cancel', 'pressio-crm' ) }}
                  </button>
                </td>
              </template>

              <!-- View mode -->
              <template v-else>
                <td>
                  <span class="tag-pill" :style="{ background: safeColor( tag.color ) + '22', color: safeColor( tag.color ), borderColor: safeColor( tag.color ) + '55' }">
                    {{ tag.name }}
                  </span>
                </td>
                <td class="crm-text-secondary">{{ tag.slug }}</td>
                <td>
                  <span class="tag-color-swatch" :style="{ background: safeColor( tag.color ) }" :title="tag.color" />
                </td>
                <td class="crm-table-actions">
                  <button
                    type="button"
                    class="crm-btn crm-btn--ghost crm-btn--sm"
                    @click="startEdit( tag )"
                  >
                    {{ __( 'Edit', 'pressio-crm' ) }}
                  </button>
                  <button
                    type="button"
                    class="crm-btn crm-btn--danger-ghost crm-btn--sm"
                    @click="deleteTag( tag )"
                  >
                    {{ __( 'Delete', 'pressio-crm' ) }}
                  </button>
                </td>
              </template>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<style scoped>
.settings-tabs {
  display: flex;
  gap: 0;
  border-bottom: 2px solid var( --crm-border );
  margin-bottom: 24px;
}

.settings-tab {
  padding: 10px 20px;
  font-size: 14px;
  font-weight: 500;
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  margin-bottom: -2px;
  cursor: pointer;
  color: var( --crm-text-secondary );
  transition: color 0.15s, border-color 0.15s;
  white-space: nowrap;
}

.settings-tab:hover { color: var( --crm-text ); }

.settings-tab--active {
  color: var( --crm-primary );
  border-bottom-color: var( --crm-primary );
}

.tab-content { padding: 24px; }

.tab-content__title {
  margin: 0 0 20px;
  font-size: 16px;
  font-weight: 600;
}

.tab-content__footer {
  margin-top: 24px;
  padding-top: 16px;
  border-top: 1px solid var( --crm-border );
}

.pipeline-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 0;
  border-bottom: 1px solid var( --crm-border );
}
.pipeline-row:last-of-type { border-bottom: none; }

.pipeline-row__name { margin: 0; font-weight: 600; font-size: 14px; }
.pipeline-row__stages { margin: 2px 0 0; font-size: 12px; }

.add-pipeline-row {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.integration-info {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.integration-info__icon { font-size: 28px; flex-shrink: 0; }
.integration-info__title { margin: 0 0 4px; font-weight: 600; font-size: 14px; }
.integration-info__desc { margin: 0; font-size: 13px; }

/* ── SMTP tab ────────────────────────────────────────────── */

.smtp-upsell-card { max-width: 680px; }
.smtp-upsell-header { margin-bottom: 12px; }
.smtp-upsell-desc { font-size: 13px; margin: 0 0 20px; line-height: 1.6; }

.smtp-features {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-bottom: 24px;
}

.smtp-features__heading {
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var( --crm-text-secondary );
  margin: 0 0 8px;
}

.smtp-features__list {
  margin: 0;
  padding-left: 18px;
  font-size: 13px;
  line-height: 1.8;
  color: var( --crm-text );
}

.smtp-install-btn { min-width: 200px; }

.smtp-status-card {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  max-width: 680px;
}

.smtp-status-icon {
  font-size: 28px;
  line-height: 1;
  flex-shrink: 0;
  color: #f59e0b;
}

.smtp-status-icon--ok { color: #10b981; }

.smtp-status-card--ok { border-color: rgba( 16, 185, 129, 0.3 ); background: rgba( 16, 185, 129, 0.03 ); }
.smtp-status-card--warn { border-color: rgba( 245, 158, 11, 0.3 ); background: rgba( 245, 158, 11, 0.03 ); }

/* ── Email settings layout ───────────────────────────────── */

.email-settings-layout {
  display: grid;
  grid-template-columns: 1fr;
  gap: 0;
  align-items: start;
}

@media ( min-width: 1100px ) {
  .email-settings-layout { grid-template-columns: 1fr 380px; gap: 24px; }
}

.email-preview-panel {
  background: var( --crm-surface );
  border-radius: var( --crm-radius-lg );
  box-shadow: var( --crm-shadow );
  overflow: hidden;
  position: sticky;
  top: 72px; /* below topnav */
}

.email-preview-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  border-bottom: 1px solid var( --crm-border );
}

.email-preview-iframe {
  width: 100%;
  height: 480px;
  border: none;
  display: block;
}

/* ── Radio groups ────────────────────────────────────────── */

.radio-group {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 6px;
}

.radio-option {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  cursor: pointer;
  color: var( --crm-text );
}

/* ── Colour picker ───────────────────────────────────────── */

.color-picker {
  width: 36px;
  height: 36px;
  padding: 2px;
  border: 1px solid var( --crm-border-strong );
  border-radius: var( --crm-radius );
  cursor: pointer;
  background: none;
}

/* ── Import results ──────────────────────────────────────── */

.import-result {
  margin-top: 16px;
  padding: 12px 16px;
  background: var( --crm-bg );
  border-radius: var( --crm-radius );
  border: 1px solid var( --crm-border );
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.import-result__row {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
}

.import-result__row .dashicons {
  font-size: 16px !important;
  width: 16px !important;
  height: 16px !important;
  line-height: 1 !important;
  flex-shrink: 0;
}

.import-result__row--success { color: var( --crm-success ); }
.import-result__row--info    { color: var( --crm-primary ); }
.import-result__row--muted   { color: var( --crm-text-secondary ); }

/* ── Tags tab ──────────────────────────────────────────────────── */

.tab-content__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 20px;
  gap: 16px;
}

.tab-content__header .tab-content__title {
  margin-bottom: 4px;
}

.tag-new-form {
  display: flex;
  align-items: flex-end;
  gap: 12px;
  padding: 16px;
  background: var( --crm-bg );
  border-radius: var( --crm-radius );
  margin-bottom: 16px;
  flex-wrap: wrap;
}

.tag-new-form .crm-input {
  flex: 1;
  min-width: 160px;
}

.tag-color-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.tag-color-field .crm-label {
  margin: 0;
  font-size: 12px;
}

.tag-color-input {
  width: 40px;
  height: 32px;
  padding: 2px;
  border: 1px solid var( --crm-border );
  border-radius: var( --crm-radius );
  cursor: pointer;
  background: none;
}

.tag-color-swatch {
  display: inline-block;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  vertical-align: middle;
}

.tag-pill {
  display: inline-flex;
  align-items: center;
  padding: 2px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 500;
  border: 1px solid;
  white-space: nowrap;
}

.crm-empty-inline {
  padding: 32px 0;
  text-align: center;
  color: var( --crm-text-secondary );
  font-size: 14px;
}

.crm-input--sm {
  padding: 4px 8px;
  font-size: 13px;
}
</style>
