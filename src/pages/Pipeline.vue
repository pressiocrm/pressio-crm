<script setup>
import { ref, computed, onMounted } from 'vue'
import { __ } from '@wordpress/i18n'
import { usePipelineStore } from '@/stores/pipeline.js'
import { useDealsStore } from '@/stores/deals.js'
import { useNotify } from '@/composables/useNotify.js'
import { useConfirm } from '@/composables/useConfirm.js'
import KanbanBoard from '@/components/pipeline/KanbanBoard.vue'
import DealForm from '@/components/pipeline/DealForm.vue'
import Modal from '@/components/ui/Modal.vue'
import Spinner from '@/components/ui/Spinner.vue'
import EmptyState from '@/components/ui/EmptyState.vue'

const pipelineStore = usePipelineStore()
const dealsStore    = useDealsStore()
const notify        = useNotify()
const { confirm }   = useConfirm()

const showDealForm  = ref( false )
const editingDeal   = ref( null )
const addToStageId  = ref( null )
const savingDeal    = ref( false )

const activePipelineId = ref( null )

onMounted( async () => {
  await pipelineStore.fetchAll()
  if ( pipelineStore.defaultPipeline ) {
    activePipelineId.value         = pipelineStore.defaultPipeline.id
    dealsStore.filters.pipeline_id = pipelineStore.defaultPipeline.id
    dealsStore.fetchAll()
  }
} )

const activePipeline = computed( () =>
  pipelineStore.pipelines.find( p => p.id === activePipelineId.value ) || pipelineStore.defaultPipeline
)

async function onPipelineSwitch( e ) {
  activePipelineId.value         = Number( e.target.value )
  dealsStore.filters.pipeline_id = activePipelineId.value
  await dealsStore.fetchAll()
}

function onAddDeal( stageId ) {
  addToStageId.value = stageId
  editingDeal.value  = null
  showDealForm.value = true
}

function onEditDeal( deal ) {
  editingDeal.value  = deal
  addToStageId.value = deal.stage_id
  showDealForm.value = true
}

async function onDeleteDeal( deal ) {
  const ok = await confirm( {
    title:        __( 'Delete deal?', 'pressio-crm' ),
    message:      `${__( 'This will permanently delete', 'pressio-crm' )} "${deal.title}".`,
    confirmLabel: __( 'Delete', 'pressio-crm' ),
    danger:       true,
  } )
  if ( ! ok ) return
  try {
    await dealsStore.remove( deal.id )
    notify.success( __( 'Deal deleted', 'pressio-crm' ) )
  } catch {}
}

async function onDealMoved( dealId, stageId, position ) {
  try {
    await dealsStore.move( dealId, stageId, position )
  } catch {}
}

async function onDealSubmit( data ) {
  savingDeal.value = true
  try {
    if ( editingDeal.value ) {
      await dealsStore.update( editingDeal.value.id, data )
      notify.success( __( 'Deal updated', 'pressio-crm' ) )
    } else {
      await dealsStore.create( data )
      notify.success( __( 'Deal created', 'pressio-crm' ) )
    }
    showDealForm.value = false
    editingDeal.value  = null
  } catch {} finally {
    savingDeal.value = false
  }
}

const modalTitle = computed( () =>
  editingDeal.value ? __( 'Edit Deal', 'pressio-crm' ) : __( 'Add Deal', 'pressio-crm' )
)
</script>

<template>
  <div>
    <div class="crm-page-header">
      <div class="header-left">
        <h1 class="crm-page-header__title">{{ __( 'Pipeline', 'pressio-crm' ) }}</h1>
        <select
          v-if="pipelineStore.pipelines.length > 1"
          class="crm-select pipeline-select"
          :value="activePipelineId"
          @change="onPipelineSwitch"
        >
          <option v-for="p in pipelineStore.pipelines" :key="p.id" :value="p.id">
            {{ p.name }}
          </option>
        </select>
      </div>
      <div class="crm-page-header__actions">
        <button
          type="button"
          class="crm-btn crm-btn--primary"
          :disabled="! activePipeline"
          @click="onAddDeal( activePipeline?.stages?.[0]?.id || null )"
        >
          <span class="dashicons dashicons-plus-alt" aria-hidden="true" />
          {{ __( 'Add Deal', 'pressio-crm' ) }}
        </button>
      </div>
    </div>

    <div v-if="pipelineStore.loading" class="crm-flex-center" style="padding: 48px;">
      <Spinner size="lg" />
    </div>

    <div v-else-if="! activePipeline" class="crm-card">
      <EmptyState
        icon="dashicons-chart-bar"
        :title="__( 'No pipeline set up', 'pressio-crm' )"
        :description="__( 'Create your first pipeline in Settings.', 'pressio-crm' )"
      />
    </div>

    <KanbanBoard
      v-else
      :pipeline="activePipeline"
      :deals="dealsStore.items"
      @deal-moved="onDealMoved"
      @add-deal="onAddDeal"
      @edit-deal="onEditDeal"
      @delete-deal="onDeleteDeal"
    />

    <Modal
      :show="showDealForm"
      :title="modalTitle"
      size="md"
      @close="showDealForm = false"
    >
      <DealForm
        v-if="activePipeline"
        :deal="editingDeal"
        :stage-id="addToStageId"
        :pipeline="activePipeline"
        :loading="savingDeal"
        @submit="onDealSubmit"
        @cancel="showDealForm = false"
      />
    </Modal>
  </div>
</template>

<style scoped>
.header-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.pipeline-select {
  width: auto;
  min-width: 160px;
}
</style>
