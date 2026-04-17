import { ref } from 'vue'
import { defineStore } from 'pinia'
import { apiFetch } from '@/api/client.js'
import { useNotify } from '@/composables/useNotify.js'

export const useActivitiesStore = defineStore( 'activities', () => {
  const items   = ref( [] )
  const loading = ref( false )

  async function fetchForContact( contactId, params = {} ) {
    loading.value = true
    try {
      const data = await apiFetch( 'activities', {
        params: { contact_id: contactId, ...params },
      } )
      items.value = Array.isArray( data ) ? data : ( data.items || [] )
      return items.value
    } catch ( e ) {
      useNotify().error( e.message )
    } finally {
      loading.value = false
    }
  }

  async function fetchForDeal( dealId, params = {} ) {
    loading.value = true
    try {
      const data = await apiFetch( 'activities', {
        params: { deal_id: dealId, ...params },
      } )
      items.value = Array.isArray( data ) ? data : ( data.items || [] )
      return items.value
    } catch ( e ) {
      useNotify().error( e.message )
    } finally {
      loading.value = false
    }
  }

  async function fetchRecent( limit = 20 ) {
    loading.value = true
    try {
      const data = await apiFetch( 'activities', { params: { limit } } )
      items.value = Array.isArray( data ) ? data : ( data.items || [] )
      return items.value
    } catch ( e ) {
      useNotify().error( e.message )
    } finally {
      loading.value = false
    }
  }

  return { items, loading, fetchForContact, fetchForDeal, fetchRecent }
} )
