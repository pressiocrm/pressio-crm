<script setup>
import { ref, computed, watch } from 'vue'
import { __ } from '@wordpress/i18n'
import { useFormErrors } from '@/composables/useFormErrors.js'
import TagPicker from '@/components/ui/TagPicker.vue'
import Spinner from '@/components/ui/Spinner.vue'

const props = defineProps( {
  contact: { type: Object,  default: null },
  loading: { type: Boolean, default: false },
} )

const emit = defineEmits( [ 'submit', 'cancel' ] )

const { errors, setErrors, clearErrors, getError, hasError } = useFormErrors()

const form = ref( buildForm( props.contact ) )

function buildForm( contact ) {
  return {
    first_name: contact?.first_name || '',
    last_name:  contact?.last_name  || '',
    email:      contact?.email      || '',
    phone:      contact?.phone      || '',
    company:    contact?.company    || '',
    job_title:  contact?.job_title  || '',
    status:     contact?.status     || 'active',
    notes:      contact?.notes      || '',
    tags:       ( contact?.tags || [] ).map( t => t.id ),
  }
}

// Re-populate when the prop changes (switching between edit targets).
watch( () => props.contact, ( val ) => {
  form.value = buildForm( val )
  clearErrors()
} )

const localError = ref( '' )

function validate() {
  localError.value = ''
  if ( ! form.value.first_name.trim() && ! form.value.email.trim() ) {
    localError.value = __( 'First name or email is required.', 'pressio-crm' )
    return false
  }
  return true
}

async function onSubmit() {
  clearErrors()
  if ( ! validate() ) return
  try {
    emit( 'submit', { ...form.value } )
  } catch ( e ) {
    setErrors( e )
  }
}
</script>

<template>
  <form class="contact-form" @submit.prevent="onSubmit">

    <div v-if="localError" class="crm-error-text" style="margin-bottom: 12px;">
      {{ localError }}
    </div>

    <div class="form-row">
      <div class="crm-form-group">
        <label class="crm-label crm-label--required" for="cf-first-name">
          {{ __( 'First Name', 'pressio-crm' ) }}
        </label>
        <input
          id="cf-first-name"
          v-model="form.first_name"
          type="text"
          :class="[ 'crm-input', hasError( 'first_name' ) && 'crm-input--error' ]"
        />
        <span v-if="hasError( 'first_name' )" class="crm-error-text">{{ getError( 'first_name' ) }}</span>
      </div>

      <div class="crm-form-group">
        <label class="crm-label" for="cf-last-name">{{ __( 'Last Name', 'pressio-crm' ) }}</label>
        <input
          id="cf-last-name"
          v-model="form.last_name"
          type="text"
          :class="[ 'crm-input', hasError( 'last_name' ) && 'crm-input--error' ]"
        />
        <span v-if="hasError( 'last_name' )" class="crm-error-text">{{ getError( 'last_name' ) }}</span>
      </div>
    </div>

    <div class="form-row">
      <div class="crm-form-group">
        <label class="crm-label" for="cf-email">{{ __( 'Email', 'pressio-crm' ) }}</label>
        <input
          id="cf-email"
          v-model="form.email"
          type="email"
          :class="[ 'crm-input', hasError( 'email' ) && 'crm-input--error' ]"
        />
        <span v-if="hasError( 'email' )" class="crm-error-text">{{ getError( 'email' ) }}</span>
      </div>

      <div class="crm-form-group">
        <label class="crm-label" for="cf-phone">{{ __( 'Phone', 'pressio-crm' ) }}</label>
        <input
          id="cf-phone"
          v-model="form.phone"
          type="text"
          :class="[ 'crm-input', hasError( 'phone' ) && 'crm-input--error' ]"
        />
        <span v-if="hasError( 'phone' )" class="crm-error-text">{{ getError( 'phone' ) }}</span>
      </div>
    </div>

    <div class="form-row">
      <div class="crm-form-group">
        <label class="crm-label" for="cf-company">{{ __( 'Company', 'pressio-crm' ) }}</label>
        <input
          id="cf-company"
          v-model="form.company"
          type="text"
          class="crm-input"
        />
      </div>

      <div class="crm-form-group">
        <label class="crm-label" for="cf-job-title">{{ __( 'Job Title', 'pressio-crm' ) }}</label>
        <input
          id="cf-job-title"
          v-model="form.job_title"
          type="text"
          class="crm-input"
        />
      </div>
    </div>

    <div class="crm-form-group">
      <label class="crm-label" for="cf-status">{{ __( 'Status', 'pressio-crm' ) }}</label>
      <select id="cf-status" v-model="form.status" class="crm-select">
        <option value="active">{{ __( 'Active', 'pressio-crm' ) }}</option>
        <option value="inactive">{{ __( 'Inactive', 'pressio-crm' ) }}</option>
        <option value="archived">{{ __( 'Archived', 'pressio-crm' ) }}</option>
      </select>
    </div>

    <div class="crm-form-group">
      <label class="crm-label">{{ __( 'Tags', 'pressio-crm' ) }}</label>
      <TagPicker v-model="form.tags" />
    </div>

    <div class="crm-form-group">
      <label class="crm-label" for="cf-notes">{{ __( 'Notes', 'pressio-crm' ) }}</label>
      <textarea
        id="cf-notes"
        v-model="form.notes"
        class="crm-textarea"
        rows="4"
      />
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
      <button
        type="submit"
        class="crm-btn crm-btn--primary"
        :disabled="loading"
      >
        <Spinner v-if="loading" size="sm" />
        {{ __( 'Save Contact', 'pressio-crm' ) }}
      </button>
    </div>

  </form>
</template>

<style scoped>
.contact-form { display: flex; flex-direction: column; }

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
