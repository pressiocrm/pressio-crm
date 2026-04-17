<script setup>
import { ref, watch } from 'vue'
import { __ } from '@wordpress/i18n'
import { useFormErrors } from '@/composables/useFormErrors.js'
import ContactPicker from '@/components/ui/ContactPicker.vue'
import Spinner from '@/components/ui/Spinner.vue'

const props = defineProps( {
  task:      { type: Object, default: null },
  contactId: { type: Number, default: null },
  dealId:    { type: Number, default: null },
  loading:   { type: Boolean, default: false },
} )

const emit = defineEmits( [ 'submit', 'cancel' ] )

const { errors, setErrors, clearErrors, getError, hasError } = useFormErrors()
const localError = ref( '' )

const form = ref( buildForm( props.task ) )

function buildForm( task ) {
  return {
    title:       task?.title       || '',
    type:        task?.type        || 'task',
    priority:    task?.priority    || 'medium',
    due_date:    task?.due_date    || '',
    description: task?.description || '',
    contact_id:  task?.contact_id  || props.contactId || null,
    deal_id:     task?.deal_id     || props.dealId    || null,
  }
}

watch( () => props.task, ( val ) => {
  form.value = buildForm( val )
  clearErrors()
} )

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
  <form class="task-form" @submit.prevent="onSubmit">

    <div v-if="localError" class="crm-error-text" style="margin-bottom: 12px;">
      {{ localError }}
    </div>

    <div class="crm-form-group">
      <label class="crm-label crm-label--required" for="tf-title">
        {{ __( 'Title', 'pressio-crm' ) }}
      </label>
      <input
        id="tf-title"
        v-model="form.title"
        type="text"
        :class="[ 'crm-input', hasError( 'title' ) && 'crm-input--error' ]"
      />
      <span v-if="hasError( 'title' )" class="crm-error-text">{{ getError( 'title' ) }}</span>
    </div>

    <div class="form-row">
      <div class="crm-form-group">
        <label class="crm-label" for="tf-type">{{ __( 'Type', 'pressio-crm' ) }}</label>
        <select id="tf-type" v-model="form.type" class="crm-select">
          <option value="task">{{ __( 'Task', 'pressio-crm' ) }}</option>
          <option value="call">{{ __( 'Call', 'pressio-crm' ) }}</option>
          <option value="email">{{ __( 'Email', 'pressio-crm' ) }}</option>
          <option value="meeting">{{ __( 'Meeting', 'pressio-crm' ) }}</option>
        </select>
      </div>

      <div class="crm-form-group">
        <label class="crm-label" for="tf-priority">{{ __( 'Priority', 'pressio-crm' ) }}</label>
        <select id="tf-priority" v-model="form.priority" class="crm-select">
          <option value="low">{{ __( 'Low', 'pressio-crm' ) }}</option>
          <option value="medium">{{ __( 'Medium', 'pressio-crm' ) }}</option>
          <option value="high">{{ __( 'High', 'pressio-crm' ) }}</option>
        </select>
      </div>
    </div>

    <div class="crm-form-group">
      <label class="crm-label" for="tf-due">{{ __( 'Due Date', 'pressio-crm' ) }}</label>
      <input id="tf-due" v-model="form.due_date" type="datetime-local" class="crm-input" />
    </div>

    <div class="crm-form-group">
      <label class="crm-label" for="tf-contact">{{ __( 'Contact', 'pressio-crm' ) }}</label>
      <ContactPicker v-model="form.contact_id" />
    </div>

    <div class="crm-form-group">
      <label class="crm-label" for="tf-desc">{{ __( 'Description', 'pressio-crm' ) }}</label>
      <textarea id="tf-desc" v-model="form.description" class="crm-textarea" rows="3" />
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
        {{ __( 'Save Task', 'pressio-crm' ) }}
      </button>
    </div>

  </form>
</template>

<style scoped>
.task-form { display: flex; flex-direction: column; }

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
