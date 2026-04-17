import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import { apiFetch } from '@/api/client.js'
import { useNotify } from '@/composables/useNotify.js'

export const usePipelineStore = defineStore( 'pipeline', () => {
  const pipelines = ref( [] )
  const loading   = ref( false )

  const defaultPipeline = computed( () =>
    pipelines.value.find( p => p.is_default ) || pipelines.value[ 0 ] || null
  )

  const stageById = computed( () => ( id ) => {
    for ( const pipeline of pipelines.value ) {
      const stage = ( pipeline.stages || [] ).find( s => s.id === id )
      if ( stage ) return stage
    }
    return null
  } )

  async function fetchAll() {
    loading.value = true
    try {
      const data    = await apiFetch( 'pipelines' )
      pipelines.value = Array.isArray( data ) ? data : ( data.items || [] )
    } catch ( e ) {
      useNotify().error( e.message )
    } finally {
      loading.value = false
    }
  }

  async function createPipeline( data ) {
    try {
      const pipeline = await apiFetch( 'pipelines', { method: 'POST', body: data } )
      pipelines.value.push( pipeline )
      return pipeline
    } catch ( e ) {
      useNotify().error( e.message )
      throw e
    }
  }

  async function updatePipeline( id, data ) {
    try {
      const pipeline = await apiFetch( `pipelines/${id}`, { method: 'PATCH', body: data } )
      const idx = pipelines.value.findIndex( p => p.id === id )
      if ( idx > -1 ) pipelines.value.splice( idx, 1, pipeline )
      return pipeline
    } catch ( e ) {
      useNotify().error( e.message )
      throw e
    }
  }

  async function deletePipeline( id ) {
    try {
      await apiFetch( `pipelines/${id}`, { method: 'DELETE' } )
      pipelines.value = pipelines.value.filter( p => p.id !== id )
    } catch ( e ) {
      useNotify().error( e.message )
      throw e
    }
  }

  async function reorderStages( pipelineId, stages ) {
    try {
      const result = await apiFetch( `pipelines/${pipelineId}/stages`, {
        method: 'PUT',
        body:   { stages },
      } )
      const idx = pipelines.value.findIndex( p => p.id === pipelineId )
      if ( idx > -1 ) {
        pipelines.value.splice( idx, 1, result )
      }
      return result
    } catch ( e ) {
      useNotify().error( e.message )
      throw e
    }
  }

  return {
    pipelines, loading,
    defaultPipeline, stageById,
    fetchAll, createPipeline, updatePipeline, deletePipeline, reorderStages,
  }
} )
