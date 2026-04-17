<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { __, sprintf } from '@wordpress/i18n'
import { apiFetch } from '@/api/client.js'
import { useContactsStore } from '@/stores/contacts.js'
import { useTasksStore } from '@/stores/tasks.js'
import { useDealsStore } from '@/stores/deals.js'
import { useActivitiesStore } from '@/stores/activities.js'
import { useNotify } from '@/composables/useNotify.js'
import ContactTimeline from '@/components/contacts/ContactTimeline.vue'
import ContactForm from '@/components/contacts/ContactForm.vue'
import TaskForm from '@/components/tasks/TaskForm.vue'
import Modal from '@/components/ui/Modal.vue'
import Spinner from '@/components/ui/Spinner.vue'
import RichTextEditor from '@/components/ui/RichTextEditor.vue'

const route            = useRoute()
const router           = useRouter()
const contactsStore    = useContactsStore()
const tasksStore       = useTasksStore()
const dealsStore       = useDealsStore()
const activitiesStore  = useActivitiesStore()
const notify           = useNotify()

const contactId = computed( () => Number( route.params.id ) )

const showEditForm  = ref( false )
const showTaskForm  = ref( false )
const editingNotes  = ref( false )
const draftNotes    = ref( '' )
const savingContact = ref( false )
const savingTask    = ref( false )

// ── Email ─────────────────────────────────────────────────────────────────────
const showEmailModal  = ref( false )
const emailSubject    = ref( '' )
const emailBody       = ref( '' )
const sendingEmail    = ref( false )
const emailHistory       = ref( [] )
const loadingHistory     = ref( false )
const historyLoadFailed  = ref( false )

const EMAIL_MERGE_TAGS = [
  { label: '{{contact.first_name}}', value: '{{contact.first_name}}' },
  { label: '{{contact.last_name}}',  value: '{{contact.last_name}}'  },
  { label: '{{contact.email}}',      value: '{{contact.email}}'      },
  { label: '{{business.name}}',      value: '{{business.name}}'      },
]

onMounted( async () => {
  await contactsStore.fetchOne( contactId.value )
  if ( contactsStore.current ) {
    draftNotes.value = contactsStore.current.notes || ''
  }
  activitiesStore.fetchForContact( contactId.value )
  fetchEmailHistory()
} )

const contact = computed( () => contactsStore.current )

const linkedDeals = computed( () =>
  dealsStore.items.filter( d => d.contact_id === contactId.value )
)

const linkedTasks = computed( () =>
  tasksStore.items.filter( t => t.contact_id === contactId.value && t.status !== 'completed' )
)

function statusClass( status ) {
  if ( status === 'active' ) return 'crm-badge crm-badge--success'
  return 'crm-badge'
}

function safeColor( color ) {
  return /^#[0-9a-fA-F]{3,8}$/.test( color ) ? color : '#6366f1'
}

async function onContactSubmit( data ) {
  savingContact.value = true
  try {
    await contactsStore.update( contactId.value, data )
    notify.success( __( 'Contact updated', 'pressio-crm' ) )
    showEditForm.value = false
    if ( contactsStore.current ) draftNotes.value = contactsStore.current.notes || ''
  } catch {} finally {
    savingContact.value = false
  }
}

async function saveNotes() {
  try {
    await contactsStore.update( contactId.value, { notes: draftNotes.value } )
    notify.success( __( 'Notes saved', 'pressio-crm' ) )
    editingNotes.value = false
  } catch {}
}

async function onTaskSubmit( data ) {
  savingTask.value = true
  try {
    await tasksStore.create( { ...data, contact_id: contactId.value } )
    notify.success( __( 'Task created', 'pressio-crm' ) )
    showTaskForm.value = false
    activitiesStore.fetchForContact( contactId.value )
  } catch {} finally {
    savingTask.value = false
  }
}

async function onAddNote() {
  editingNotes.value = true
  showEditForm.value  = false
}

async function fetchEmailHistory() {
  loadingHistory.value    = true
  historyLoadFailed.value = false
  try {
    const data = await apiFetch( `contacts/${contactId.value}/email` )
    emailHistory.value = data.items || []
  } catch {
    emailHistory.value      = []
    historyLoadFailed.value = true
  } finally {
    loadingHistory.value = false
  }
}

function hasBodyContent( html ) {
  return html.replace( /<[^>]*>/g, '' ).replace( /&nbsp;/g, '' ).trim().length > 0
}

async function sendEmail() {
  if ( ! emailSubject.value.trim() || ! hasBodyContent( emailBody.value ) ) return
  sendingEmail.value = true
  try {
    await apiFetch( `contacts/${contactId.value}/email`, {
      method: 'POST',
      body: {
        subject: emailSubject.value.trim(),
        body:    emailBody.value,
      },
    } )
    notify.success( __( 'Email sent', 'pressio-crm' ) )
    showEmailModal.value = false
    emailSubject.value   = ''
    emailBody.value      = ''
    // Refresh history and timeline
    await fetchEmailHistory()
    activitiesStore.fetchForContact( contactId.value )
  } catch ( e ) {
    notify.error( e.message )
  } finally {
    sendingEmail.value = false
  }
}
</script>

<template>
  <div>
    <div v-if="contactsStore.loading && ! contact" class="crm-flex-center" style="padding: 48px;">
      <Spinner size="lg" />
    </div>

    <template v-else-if="contact">
      <!-- Page header -->
      <div class="crm-page-header">
        <div class="header-back">
          <button type="button" class="crm-btn crm-btn--ghost crm-btn--sm" @click="router.push( '/contacts' )">
            <span class="dashicons dashicons-arrow-left-alt" aria-hidden="true" />
            {{ __( 'Contacts', 'pressio-crm' ) }}
          </button>
          <h1 class="crm-page-header__title">
            {{ contact.first_name }} {{ contact.last_name }}
          </h1>
          <span :class="statusClass( contact.status )">{{ contact.status || 'active' }}</span>
        </div>
        <div class="crm-page-header__actions">
          <button
            v-if="contact.email"
            type="button"
            class="crm-btn crm-btn--secondary"
            @click="showEmailModal = true"
          >
            <span class="dashicons dashicons-email-alt" aria-hidden="true" />
            {{ __( 'Send Email', 'pressio-crm' ) }}
          </button>
          <button
            type="button"
            class="crm-btn crm-btn--secondary"
            @click="showTaskForm = true"
          >
            <span class="dashicons dashicons-plus-alt" aria-hidden="true" />
            {{ __( 'Add Task', 'pressio-crm' ) }}
          </button>
          <button
            type="button"
            class="crm-btn crm-btn--primary"
            @click="showEditForm = true"
          >
            <span class="dashicons dashicons-edit" aria-hidden="true" />
            {{ __( 'Edit Contact', 'pressio-crm' ) }}
          </button>
        </div>
      </div>

      <!-- Two-column layout -->
      <div class="contact-layout">
        <!-- Left: Timeline -->
        <div class="contact-layout__main">
          <ContactTimeline
            :contact-id="contactId"
            :activities="activitiesStore.items"
            :loading="activitiesStore.loading"
            @add-note="onAddNote"
          />
        </div>

        <!-- Right: Detail card -->
        <div class="contact-layout__sidebar">
          <div class="crm-card detail-card">
            <h3 class="detail-card__section-title">{{ __( 'Details', 'pressio-crm' ) }}</h3>

            <dl class="detail-list">
              <dt>{{ __( 'Email', 'pressio-crm' ) }}</dt>
              <dd>
                <a v-if="contact.email" :href="`mailto:${contact.email}`" class="detail-link">
                  {{ contact.email }}
                </a>
                <span v-else class="crm-text-muted">—</span>
              </dd>

              <dt>{{ __( 'Phone', 'pressio-crm' ) }}</dt>
              <dd>
                <a v-if="contact.phone" :href="`tel:${contact.phone}`" class="detail-link">
                  {{ contact.phone }}
                </a>
                <span v-else class="crm-text-muted">—</span>
              </dd>

              <dt>{{ __( 'Company', 'pressio-crm' ) }}</dt>
              <dd>{{ contact.company || '—' }}</dd>

              <dt>{{ __( 'Job Title', 'pressio-crm' ) }}</dt>
              <dd>{{ contact.job_title || '—' }}</dd>

              <template v-if="contact.city || contact.country">
                <dt>{{ __( 'Location', 'pressio-crm' ) }}</dt>
                <dd>{{ [ contact.city, contact.state, contact.country ].filter( Boolean ).join( ', ' ) }}</dd>
              </template>
            </dl>

            <div v-if="( contact.tags || [] ).length > 0" class="detail-tags">
              <p class="detail-card__section-title">{{ __( 'Tags', 'pressio-crm' ) }}</p>
              <div class="tags-row">
                <span
                  v-for="tag in contact.tags"
                  :key="tag.id"
                  class="crm-tag-pill"
                  :style="tag.color ? `--tag-color: ${safeColor( tag.color )}` : ''"
                >
                  {{ tag.name }}
                </span>
              </div>
            </div>

            <!-- Notes -->
            <div class="detail-notes">
              <div class="detail-notes__header">
                <p class="detail-card__section-title">{{ __( 'Notes', 'pressio-crm' ) }}</p>
                <button
                  v-if="! editingNotes"
                  type="button"
                  class="crm-btn crm-btn--ghost crm-btn--sm"
                  @click="editingNotes = true"
                >
                  {{ __( 'Edit', 'pressio-crm' ) }}
                </button>
              </div>

              <template v-if="editingNotes">
                <textarea v-model="draftNotes" class="crm-textarea" rows="5" />
                <div class="notes-actions">
                  <button
                    type="button"
                    class="crm-btn crm-btn--ghost crm-btn--sm"
                    @click="editingNotes = false; draftNotes = contact.notes || ''"
                  >
                    {{ __( 'Cancel', 'pressio-crm' ) }}
                  </button>
                  <button type="button" class="crm-btn crm-btn--primary crm-btn--sm" @click="saveNotes">
                    {{ __( 'Save', 'pressio-crm' ) }}
                  </button>
                </div>
              </template>
              <p v-else class="notes-text crm-text-muted">
                {{ contact.notes || __( 'No notes yet. Click Edit to add some.', 'pressio-crm' ) }}
              </p>
            </div>
          </div>

          <!-- Linked Tasks -->
          <div v-if="linkedTasks.length > 0" class="crm-card linked-card">
            <h3 class="detail-card__section-title">{{ __( 'Open Tasks', 'pressio-crm' ) }}</h3>
            <ul class="linked-list">
              <li v-for="task in linkedTasks" :key="task.id" class="linked-item">
                <span class="dashicons dashicons-yes" aria-hidden="true" />
                <span>{{ task.title }}</span>
                <span v-if="task.due_date" class="crm-text-muted linked-item__date">
                  {{ new Date( task.due_date ).toLocaleDateString() }}
                </span>
              </li>
            </ul>
          </div>

          <!-- Email History -->
          <div class="crm-card linked-card">
            <h3 class="detail-card__section-title">{{ __( 'Sent Emails', 'pressio-crm' ) }}</h3>

            <div v-if="loadingHistory" class="crm-flex-center" style="padding: 16px;">
              <Spinner size="sm" />
            </div>

            <p v-else-if="historyLoadFailed" class="crm-text-muted" style="font-size: 13px; margin: 0; color: var( --crm-danger );">
              {{ __( 'Could not load email history.', 'pressio-crm' ) }}
            </p>

            <p v-else-if="emailHistory.length === 0" class="crm-text-muted" style="font-size: 13px; margin: 0;">
              {{ __( 'No emails sent yet.', 'pressio-crm' ) }}
            </p>

            <ul v-else class="linked-list">
              <li v-for="mail in emailHistory" :key="mail.id" class="linked-item linked-item--email">
                <span class="dashicons dashicons-email" aria-hidden="true" />
                <span class="linked-item__subject">{{ mail.subject }}</span>
                <span
                  :class="[ 'crm-badge', mail.status === 'sent' ? 'crm-badge--success' : 'crm-badge--danger' ]"
                  style="font-size: 10px; padding: 1px 6px;"
                >
                  {{ mail.status === 'sent' ? __( 'sent', 'pressio-crm' ) : __( 'failed', 'pressio-crm' ) }}
                </span>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </template>

    <!-- Edit Contact Modal -->
    <Modal
      :show="showEditForm"
      :title="__( 'Edit Contact', 'pressio-crm' )"
      size="md"
      @close="showEditForm = false"
    >
      <ContactForm
        :contact="contact"
        :loading="savingContact"
        @submit="onContactSubmit"
        @cancel="showEditForm = false"
      />
    </Modal>

    <!-- Add Task Modal -->
    <Modal
      :show="showTaskForm"
      :title="__( 'Add Task', 'pressio-crm' )"
      size="md"
      @close="showTaskForm = false"
    >
      <TaskForm
        :task="null"
        :contact-id="contactId"
        :loading="savingTask"
        @submit="onTaskSubmit"
        @cancel="showTaskForm = false"
      />
    </Modal>

    <!-- Send Email Modal -->
    <Modal
      :show="showEmailModal"
      :title="sprintf( __( 'Send Email to %s', 'pressio-crm' ), contact?.first_name || '' )"
      size="lg"
      @close="showEmailModal = false; emailSubject = ''; emailBody = ''"
    >
      <div class="send-email-form">
        <div class="crm-form-group">
          <label class="crm-label" for="email-to">{{ __( 'To', 'pressio-crm' ) }}</label>
          <input
            id="email-to"
            :value="contact.email"
            type="email"
            class="crm-input"
            disabled
          />
        </div>

        <div class="crm-form-group">
          <label class="crm-label" for="email-subject">{{ __( 'Subject', 'pressio-crm' ) }}</label>
          <input
            id="email-subject"
            v-model="emailSubject"
            type="text"
            class="crm-input"
            :placeholder="__( 'Enter subject…', 'pressio-crm' )"
          />
        </div>

        <div class="crm-form-group">
          <label class="crm-label">{{ __( 'Message', 'pressio-crm' ) }}</label>
          <RichTextEditor
            v-model="emailBody"
            :merge-tags="EMAIL_MERGE_TAGS"
            min-height="200"
          />
        </div>
      </div>

      <template #footer>
        <button
          type="button"
          class="crm-btn crm-btn--ghost"
          @click="showEmailModal = false; emailSubject = ''; emailBody = ''"
        >
          {{ __( 'Cancel', 'pressio-crm' ) }}
        </button>
        <button
          type="button"
          class="crm-btn crm-btn--primary"
          :disabled="sendingEmail || ! emailSubject.trim() || ! hasBodyContent( emailBody )"
          @click="sendEmail"
        >
          <Spinner v-if="sendingEmail" size="sm" />
          {{ __( 'Send Email', 'pressio-crm' ) }}
        </button>
      </template>
    </Modal>
  </div>
</template>

<style scoped>
.header-back {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.contact-layout {
  display: grid;
  grid-template-columns: 1fr;
  gap: 20px;
  align-items: start;
}

@media ( min-width: 900px ) {
  .contact-layout { grid-template-columns: 2fr 1fr; }
}

.detail-card { padding: 20px; }

.detail-card__section-title {
  margin: 0 0 12px;
  font-size: 13px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var( --crm-text-secondary );
}

.detail-list {
  display: grid;
  grid-template-columns: 90px 1fr;
  gap: 8px 12px;
  font-size: 13px;
  margin: 0 0 16px;
}

dt { color: var( --crm-text-secondary ); font-weight: 500; }
dd { margin: 0; }

.detail-link { color: var( --crm-primary ); text-decoration: none; }
.detail-link:hover { text-decoration: underline; }

.detail-tags { margin-bottom: 16px; }
.tags-row { display: flex; flex-wrap: wrap; gap: 4px; }

.detail-notes { border-top: 1px solid var( --crm-border ); padding-top: 16px; }

.detail-notes__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
}
.detail-notes__header .detail-card__section-title { margin-bottom: 0; }

.notes-text { margin: 0; font-size: 13px; white-space: pre-wrap; }

.notes-actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
  margin-top: 8px;
}

.linked-card { padding: 16px; margin-top: 16px; }
.linked-list { list-style: none; margin: 0; padding: 0; }

.linked-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 0;
  font-size: 13px;
  border-bottom: 1px solid var( --crm-border );
}
.linked-item:last-child { border-bottom: none; }
.linked-item .dashicons { color: var( --crm-primary ); font-size: 14px; flex-shrink: 0; }
.linked-item__date { margin-left: auto; font-size: 12px; }

.linked-item--email {
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.linked-item__subject {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 13px;
}

.send-email-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
</style>
