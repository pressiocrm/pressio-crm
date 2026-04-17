<script setup>
import { __ } from '@wordpress/i18n'
import { useConfirm } from '@/composables/useConfirm.js'
import Modal from '@/components/ui/Modal.vue'

const { state, onConfirm, onCancel } = useConfirm()
</script>

<template>
  <Modal
    :show="state.show"
    :title="state.title || __( 'Are you sure?', 'pressio-crm' )"
    size="sm"
    @close="onCancel"
  >
    <p v-if="state.message" style="margin: 0; color: var(--crm-text);">{{ state.message }}</p>

    <template #footer>
      <button type="button" class="crm-btn crm-btn--secondary" @click="onCancel">
        {{ state.cancelLabel }}
      </button>
      <button
        type="button"
        :class="[ 'crm-btn', state.danger ? 'crm-btn--danger' : 'crm-btn--primary' ]"
        @click="onConfirm"
      >
        {{ state.confirmLabel }}
      </button>
    </template>
  </Modal>
</template>
