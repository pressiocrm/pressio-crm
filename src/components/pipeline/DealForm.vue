<script setup>
import { ref, computed, watch } from 'vue'
import { __ } from '@wordpress/i18n'
import { useFormErrors } from '@/composables/useFormErrors.js'
import ContactPicker from '@/components/ui/ContactPicker.vue'
import Spinner from '@/components/ui/Spinner.vue'

const props = defineProps( {
  deal:     { type: Object, default: null },
  stageId:  { type: Number, default: null },
  pipeline: { type: Object, required: true },
  loading:  { type: Boolean, default: false },
} )

const emit = defineEmits( [ 'submit', 'cancel' ] )

const { errors, setErrors, clearErrors, getError, hasError } = useFormErrors()
const localError = ref( '' )

const stages = computed( () =>
  [ ...( props.pipeline?.stages || [] ) ].sort( ( a, b ) => a.position - b.position )
)

const form = ref( buildForm( props.deal, props.stageId ) )

function buildForm( deal, stageId ) {
  return {
    pipeline_id:    deal?.pipeline_id    || props.pipeline?.id || null,
    title:          deal?.title          || '',
    contact_id:     deal?.contact_id     || null,
    stage_id:       deal?.stage_id       || stageId || ( stages.value[0]?.id ?? null ),
    value:          deal?.value          || '',
    currency:       deal?.currency       || 'USD',
    expected_close: deal?.expected_close || '',
    notes:          deal?.notes          || '',
  }
}

watch( () => [ props.deal, props.stageId ], ( [ deal, stageId ] ) => {
  form.value = buildForm( deal, stageId )
  clearErrors()
}, { deep: true } )

function validate() {
  localError.value = ''
  if ( ! form.value.title.trim() ) {
    localError.value = __( 'Title is required.', 'pressio-crm' )
    return false
  }
  return true
}

function onSubmit() {
  clearErrors()
  if ( ! validate() ) return
  emit( 'submit', { ...form.value } )
}
</script>

<template>
  <form class="deal-form" @submit.prevent="onSubmit">

    <div v-if="localError" class="crm-error-text" style="margin-bottom: 12px;">
      {{ localError }}
    </div>

    <div class="crm-form-group">
      <label class="crm-label crm-label--required" for="df-title">
        {{ __( 'Deal Title', 'pressio-crm' ) }}
      </label>
      <input
        id="df-title"
        v-model="form.title"
        type="text"
        :class="[ 'crm-input', hasError( 'title' ) && 'crm-input--error' ]"
      />
      <span v-if="hasError( 'title' )" class="crm-error-text">{{ getError( 'title' ) }}</span>
    </div>

    <div class="crm-form-group">
      <label class="crm-label" for="df-contact">{{ __( 'Contact', 'pressio-crm' ) }}</label>
      <ContactPicker v-model="form.contact_id" />
    </div>

    <div class="crm-form-group">
      <label class="crm-label" for="df-stage">{{ __( 'Stage', 'pressio-crm' ) }}</label>
      <select id="df-stage" v-model="form.stage_id" class="crm-select">
        <option v-for="stage in stages" :key="stage.id" :value="stage.id">
          {{ stage.name }}
        </option>
      </select>
    </div>

    <div class="form-row">
      <div class="crm-form-group">
        <label class="crm-label" for="df-value">{{ __( 'Value', 'pressio-crm' ) }}</label>
        <input
          id="df-value"
          v-model="form.value"
          type="number"
          min="0"
          step="0.01"
          class="crm-input"
        />
      </div>

      <div class="crm-form-group">
        <label class="crm-label" for="df-currency">{{ __( 'Currency', 'pressio-crm' ) }}</label>
        <select id="df-currency" v-model="form.currency" class="crm-select">
          <option value="USD">USD</option>
          <option value="EUR">EUR</option>
          <option value="GBP">GBP</option>
          <option value="AUD">AUD</option>
          <option value="CAD">CAD</option>
        </select>
      </div>
    </div>

    <div class="crm-form-group">
      <label class="crm-label" for="df-close">{{ __( 'Expected Close Date', 'pressio-crm' ) }}</label>
      <input
        id="df-close"
        v-model="form.expected_close"
        type="date"
        class="crm-input"
      />
    </div>

    <div class="crm-form-group">
      <label class="crm-label" for="df-notes">{{ __( 'Notes', 'pressio-crm' ) }}</label>
      <textarea id="df-notes" v-model="form.notes" class="crm-textarea" rows="3" />
    </div>

    <div class="form-footer">
      <button
        type="button"
        class="crm-btn crm-btn--ghost"
        :disabled="loading"
        @click="emit( 'cancel' )"
      >
        {{ __( 'Cancel', 'pressio-crm' ) }}
      </button>
      <button type="submit" class="crm-btn crm-btn--primary" :disabled="loading">
        <Spinner v-if="loading" size="sm" />
        {{ __( 'Save Deal', 'pressio-crm' ) }}
      </button>
    </div>

  </form>
</template>

<style scoped>
.deal-form { display: flex; flex-direction: column; }

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0 16px;
}

.form-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
  padding-top: 16px;
  border-top: 1px solid var( --crm-border );
  margin-top: 8px;
}
</style>
