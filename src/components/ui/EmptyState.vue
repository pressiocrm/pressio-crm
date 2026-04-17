<script setup>
defineProps( {
  icon:        { type: String, default: 'dashicons-database' },
  title:       { type: String, default: '' },
  description: { type: String, default: '' },
  actionLabel: { type: String, default: '' },
} )

defineEmits( [ 'action' ] )
</script>

<template>
  <div class="crm-empty-state">
    <span class="crm-empty-state__icon" aria-hidden="true">
      <span :class="[ 'dashicons', icon ]" />
    </span>
    <h3 v-if="title" class="crm-empty-state__title">{{ title }}</h3>
    <p v-if="description" class="crm-empty-state__description">{{ description }}</p>
    <button
      v-if="actionLabel"
      type="button"
      class="crm-btn crm-btn--primary"
      @click="$emit( 'action' )"
    >
      {{ actionLabel }}
    </button>
  </div>
</template>
