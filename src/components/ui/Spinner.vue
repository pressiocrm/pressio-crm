<script setup>
import { __ } from '@wordpress/i18n'

defineProps( {
  size: { type: String, default: 'md' },
} )
</script>

<template>
  <span
    :class="[ 'crm-spinner', size !== 'md' ? `crm-spinner--${size}` : '' ]"
    role="status"
    :aria-label="__( 'Loading', 'pressio-crm' )"
  />
</template>
