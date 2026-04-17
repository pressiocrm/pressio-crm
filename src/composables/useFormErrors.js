import { ref } from 'vue'

export function useFormErrors() {
  const errors = ref( {} )

  function setErrors( apiError ) {
    const params = apiError?.data?.params
    if ( params && typeof params === 'object' ) {
      errors.value = Object.fromEntries(
        Object.entries( params ).map( ( [ field, detail ] ) => [
          field,
          typeof detail === 'string' ? detail : ( detail?.message || String( detail ) ),
        ] )
      )
    } else {
      errors.value = {}
    }
  }

  function clearErrors() {
    errors.value = {}
  }

  function getError( field ) {
    return errors.value[ field ] || null
  }

  function hasError( field ) {
    return Boolean( errors.value[ field ] )
  }

  return { errors, setErrors, clearErrors, getError, hasError }
}
