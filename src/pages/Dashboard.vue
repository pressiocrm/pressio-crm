<script setup>
import { ref, onMounted } from 'vue'
import { __ } from '@wordpress/i18n'
import { useDashboardStore } from '@/stores/dashboard.js'
import { useContactsStore } from '@/stores/contacts.js'
import { useNotify } from '@/composables/useNotify.js'
import StatsCards from '@/components/dashboard/StatsCards.vue'
import PipelineFunnel from '@/components/dashboard/PipelineFunnel.vue'
import RecentActivity from '@/components/dashboard/RecentActivity.vue'
import ContactForm from '@/components/contacts/ContactForm.vue'
import Modal from '@/components/ui/Modal.vue'

const dashboardStore = useDashboardStore()
const contactsStore  = useContactsStore()
const notify         = useNotify()

const showAddContact  = ref( false )
const savingContact   = ref( false )

onMounted( () => dashboardStore.fetchAll() )

async function onContactSubmit( data ) {
  savingContact.value = true
  try {
    await contactsStore.create( data )
    notify.success( __( 'Contact added', 'pressio-crm' ) )
    showAddContact.value = false
    dashboardStore.fetchAll()
  } catch {} finally {
    savingContact.value = false
  }
}
</script>

<template>
  <div>
    <div class="crm-page-header">
      <h1 class="crm-page-header__title">{{ __( 'Dashboard', 'pressio-crm' ) }}</h1>
      <div class="crm-page-header__actions">
        <button
          type="button"
          class="crm-btn crm-btn--primary"
          @click="showAddContact = true"
        >
          <span class="dashicons dashicons-plus-alt" aria-hidden="true" />
          {{ __( 'Add Contact', 'pressio-crm' ) }}
        </button>
      </div>
    </div>

    <StatsCards :stats="dashboardStore.stats" :loading="dashboardStore.loading" />

    <div class="dashboard-row">
      <div class="dashboard-row__main">
        <PipelineFunnel :funnel="dashboardStore.funnel" :loading="dashboardStore.loading" />
      </div>
      <div class="dashboard-row__aside">
        <RecentActivity :activities="dashboardStore.recentActivity" :loading="dashboardStore.loading" />
      </div>
    </div>

    <Modal
      :show="showAddContact"
      :title="__( 'Add Contact', 'pressio-crm' )"
      size="md"
      @close="showAddContact = false"
    >
      <ContactForm
        :contact="null"
        :loading="savingContact"
        @submit="onContactSubmit"
        @cancel="showAddContact = false"
      />
    </Modal>
  </div>
</template>

<style scoped>
.dashboard-row {
  display: grid;
  grid-template-columns: 1fr;
  gap: 16px;
}

@media ( min-width: 960px ) {
  .dashboard-row { grid-template-columns: 3fr 2fr; }
}

.dashboard-row__main,
.dashboard-row__aside {
  min-width: 0;
}
</style>
