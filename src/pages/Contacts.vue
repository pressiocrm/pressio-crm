<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { __ } from '@wordpress/i18n'
import { useContactsStore } from '@/stores/contacts.js'
import { useTagsStore } from '@/stores/tags.js'
import { useConfirm } from '@/composables/useConfirm.js'
import { useNotify } from '@/composables/useNotify.js'
import ContactList from '@/components/contacts/ContactList.vue'
import ContactForm from '@/components/contacts/ContactForm.vue'
import Modal from '@/components/ui/Modal.vue'
import Pagination from '@/components/ui/Pagination.vue'

const contactsStore = useContactsStore()
const tagsStore     = useTagsStore()
const { confirm }   = useConfirm()
const notify        = useNotify()

const showForm       = ref( false )
const editingContact = ref( null )
const selectedIds    = ref( [] )
const savingContact  = ref( false )
let searchTimer      = null

onMounted( () => {
  contactsStore.fetchAll()
  tagsStore.fetchAll()
} )

onUnmounted( () => {
  clearTimeout( searchTimer )
} )

// Debounced search
function onSearchInput( e ) {
  clearTimeout( searchTimer )
  searchTimer = setTimeout( () => {
    contactsStore.filters.search = e.target.value
    contactsStore.filters.page   = 1
    contactsStore.fetchAll()
  }, 300 )
}

function onStatusFilter( e ) {
  contactsStore.filters.status = e.target.value
  contactsStore.filters.page   = 1
  contactsStore.fetchAll()
}

function onTagFilter( e ) {
  contactsStore.filters.tag_id = e.target.value
  contactsStore.filters.page   = 1
  contactsStore.fetchAll()
}

function onPageChange( page ) {
  contactsStore.filters.page = page
  contactsStore.fetchAll()
}

// Selection
function onSelect( id )       { if ( ! selectedIds.value.includes( id ) ) selectedIds.value.push( id ) }
function onDeselect( id )     { selectedIds.value = selectedIds.value.filter( i => i !== id ) }
function onSelectAll()        { selectedIds.value = contactsStore.items.map( c => c.id ) }
function onDeselectAll()      { selectedIds.value = [] }

function onView( contact ) {
  window.location.hash = `/contacts/${contact.id}`
}

function onEdit( contact ) {
  editingContact.value = contact
  showForm.value       = true
}

function closeForm() {
  showForm.value       = false
  editingContact.value = null
}

async function onDelete( contact ) {
  const ok = await confirm( {
    title:        __( 'Delete contact?', 'pressio-crm' ),
    message:      `${__( 'This will permanently delete', 'pressio-crm' )} ${contact.first_name} ${contact.last_name}.`,
    confirmLabel: __( 'Delete', 'pressio-crm' ),
    danger:       true,
  } )
  if ( ! ok ) return

  try {
    await contactsStore.remove( contact.id )
    notify.success( __( 'Contact deleted', 'pressio-crm' ) )
    selectedIds.value = selectedIds.value.filter( i => i !== contact.id )
  } catch {}
}

async function onSubmit( data ) {
  savingContact.value = true
  try {
    if ( editingContact.value ) {
      await contactsStore.update( editingContact.value.id, data )
      notify.success( __( 'Contact updated', 'pressio-crm' ) )
    } else {
      await contactsStore.create( data )
      notify.success( __( 'Contact created', 'pressio-crm' ) )
    }
    closeForm()
  } catch {
    // Errors already surfaced by the store via useNotify
  } finally {
    savingContact.value = false
  }
}

async function onBulkDelete() {
  const ok = await confirm( {
    title:        __( 'Delete selected contacts?', 'pressio-crm' ),
    message:      `${__( 'This will permanently delete', 'pressio-crm' )} ${selectedIds.value.length} ${__( 'contacts.', 'pressio-crm' )}`,
    confirmLabel: __( 'Delete all', 'pressio-crm' ),
    danger:       true,
  } )
  if ( ! ok ) return

  try {
    await contactsStore.bulkAction( 'delete', selectedIds.value )
    notify.success( __( 'Contacts deleted', 'pressio-crm' ) )
    selectedIds.value = []
    contactsStore.fetchAll()
  } catch {}
}

const modalTitle = computed( () =>
  editingContact.value
    ? __( 'Edit Contact', 'pressio-crm' )
    : __( 'Add Contact', 'pressio-crm' )
)
</script>

<template>
  <div>
    <!-- Page header -->
    <div class="crm-page-header">
      <div class="header-title-group">
        <h1 class="crm-page-header__title">{{ __( 'Contacts', 'pressio-crm' ) }}</h1>
        <span class="crm-badge">{{ contactsStore.total }}</span>
      </div>
      <div class="crm-page-header__actions">
        <input
          type="search"
          class="crm-input search-input"
          :placeholder="__( 'Search contacts…', 'pressio-crm' )"
          :value="contactsStore.filters.search"
          @input="onSearchInput"
        />
        <button
          type="button"
          class="crm-btn crm-btn--primary"
          @click="showForm = true; editingContact = null"
        >
          <span class="dashicons dashicons-plus-alt" aria-hidden="true" />
          {{ __( 'Add Contact', 'pressio-crm' ) }}
        </button>
      </div>
    </div>

    <!-- Filter bar -->
    <div class="filter-bar">
      <select class="crm-select filter-select" :value="contactsStore.filters.status" @change="onStatusFilter">
        <option value="">{{ __( 'All statuses', 'pressio-crm' ) }}</option>
        <option value="active">{{ __( 'Active', 'pressio-crm' ) }}</option>
        <option value="inactive">{{ __( 'Inactive', 'pressio-crm' ) }}</option>
        <option value="archived">{{ __( 'Archived', 'pressio-crm' ) }}</option>
      </select>

      <select class="crm-select filter-select" :value="contactsStore.filters.tag_id" @change="onTagFilter">
        <option value="">{{ __( 'All tags', 'pressio-crm' ) }}</option>
        <option v-for="tag in tagsStore.items" :key="tag.id" :value="tag.id">
          {{ tag.name }}
        </option>
      </select>
    </div>

    <!-- Bulk action bar -->
    <div v-if="selectedIds.length > 0" class="bulk-bar">
      <span class="crm-text-muted">
        {{ selectedIds.length }} {{ __( 'selected', 'pressio-crm' ) }}
      </span>
      <button type="button" class="crm-btn crm-btn--danger crm-btn--sm" @click="onBulkDelete">
        {{ __( 'Delete', 'pressio-crm' ) }}
      </button>
      <button type="button" class="crm-btn crm-btn--ghost crm-btn--sm" @click="onDeselectAll">
        {{ __( 'Clear selection', 'pressio-crm' ) }}
      </button>
    </div>

    <ContactList
      :contacts="contactsStore.items"
      :loading="contactsStore.loading"
      :selected-ids="selectedIds"
      @select="onSelect"
      @deselect="onDeselect"
      @select-all="onSelectAll"
      @deselect-all="onDeselectAll"
      @view="onView"
      @edit="onEdit"
      @delete="onDelete"
    />

    <Pagination
      :current-page="contactsStore.filters.page"
      :total-pages="contactsStore.pages"
      :total="contactsStore.total"
      :per-page="contactsStore.filters.per_page"
      @change="onPageChange"
    />

    <Modal
      :show="showForm"
      :title="modalTitle"
      size="md"
      @close="closeForm"
    >
      <ContactForm
        :contact="editingContact"
        :loading="savingContact"
        @submit="onSubmit"
        @cancel="closeForm"
      />
    </Modal>
  </div>
</template>

<style scoped>
.header-title-group {
  display: flex;
  align-items: center;
  gap: 8px;
}

.search-input {
  width: 220px;
}

.filter-bar {
  display: flex;
  gap: 8px;
  margin-bottom: 16px;
  flex-wrap: wrap;
}

.filter-select { width: auto; min-width: 140px; }

.bulk-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  background: rgba( 34, 113, 177, 0.06 );
  border: 1px solid rgba( 34, 113, 177, 0.2 );
  border-radius: var( --crm-radius );
  margin-bottom: 8px;
  font-size: 13px;
}
</style>
