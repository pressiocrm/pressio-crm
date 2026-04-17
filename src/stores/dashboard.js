import { ref } from 'vue'
import { defineStore } from 'pinia'
import { apiFetch } from '@/api/client.js'
import { useNotify } from '@/composables/useNotify.js'

export const useDashboardStore = defineStore( 'dashboard', () => {
  const stats          = ref( null )
  const funnel         = ref( [] )
  const recentActivity = ref( [] )
  const loading        = ref( false )

  async function fetchAll() {
    loading.value = true
    try {
      const [ statsResult, funnelResult, activityResult ] = await Promise.allSettled( [
        apiFetch( 'dashboard/stats' ),
        apiFetch( 'dashboard/funnel' ),
        apiFetch( 'dashboard/activity' ),
      ] )

      if ( statsResult.status === 'fulfilled' ) {
        stats.value = statsResult.value
      } else {
        useNotify().error( statsResult.reason?.message )
      }

      if ( funnelResult.status === 'fulfilled' ) {
        const d = funnelResult.value
        funnel.value = Array.isArray( d ) ? d : ( d?.items || [] )
      } else {
        useNotify().error( funnelResult.reason?.message )
      }

      if ( activityResult.status === 'fulfilled' ) {
        const d = activityResult.value
        recentActivity.value = Array.isArray( d ) ? d : ( d?.items || [] )
      } else {
        useNotify().error( activityResult.reason?.message )
      }
    } finally {
      loading.value = false
    }
  }

  return { stats, funnel, recentActivity, loading, fetchAll }
} )
