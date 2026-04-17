<script setup>
import { computed } from 'vue'
import { __ } from '@wordpress/i18n'
import KanbanColumn from './KanbanColumn.vue'
import EmptyState from '@/components/ui/EmptyState.vue'

const props = defineProps( {
  pipeline: { type: Object, required: true },
  deals:    { type: Array,  default: () => [] },
} )

const emit = defineEmits( [ 'deal-moved', 'add-deal', 'edit-deal', 'delete-deal' ] )

const stages = computed( () =>
  [ ...( props.pipeline.stages || [] ) ].sort( ( a, b ) => a.position - b.position )
)

function dealsForStage( stageId ) {
  return [ ...props.deals ]
    .filter( d => d.stage_id === stageId )
    .sort( ( a, b ) => a.position - b.position )
}

// KanbanColumn calculates position from its local v-model list — just forward up.
function onColumnDealMoved( { dealId, stageId, position } ) {
  emit( 'deal-moved', dealId, stageId, position )
}
</script>

<template>
  <div class="kanban-board">
    <EmptyState
      v-if="stages.length === 0"
      icon="dashicons-chart-bar"
      :title="__( 'No stages configured', 'pressio-crm' )"
      :description="__( 'Add stages to your pipeline in Settings.', 'pressio-crm' )"
    />
    <div v-else class="kanban-board__columns">
      <KanbanColumn
        v-for="stage in stages"
        :key="stage.id"
        :stage="stage"
        :deals="dealsForStage( stage.id )"
        @add-deal="emit( 'add-deal', $event )"
        @edit-deal="emit( 'edit-deal', $event )"
        @delete-deal="emit( 'delete-deal', $event )"
        @deal-moved="onColumnDealMoved"
      />
    </div>
  </div>
</template>

<style scoped>
.kanban-board {
  overflow-x: auto;
  padding-bottom: 16px;
}

.kanban-board__columns {
  display: flex;
  gap: 16px;
  align-items: flex-start;
  min-width: max-content;
  padding: 4px 2px;
}
</style>
