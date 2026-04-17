import { ref } from 'vue'
import { defineStore } from 'pinia'
import { __ } from '@wordpress/i18n'
import { apiFetch } from '@/api/client.js'
import { useNotify } from '@/composables/useNotify.js'

export const useSettingsStore = defineStore( 'settings', () => {
  const data    = ref( {} )
  const loading = ref( false )
  const saving  = ref( false )

  async function fetch() {
    loading.value = true
    try {
      data.value = await apiFetch( 'settings' )
    } catch ( e ) {
      useNotify().error( e.message )
    } finally {
      loading.value = false
    }
  }

  async function save( payload ) {
    saving.value = true
    try {
      data.value = await apiFetch( 'settings', { method: 'PUT', body: payload } )
      useNotify().success( __( 'Settings saved', 'pressio-crm' ) )
    } catch ( e ) {
      useNotify().error( e.message )
      throw e
    } finally {
      saving.value = false
    }
  }

  return { data, loading, saving, fetch, save }
} )
