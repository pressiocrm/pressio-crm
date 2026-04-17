export class ApiError extends Error {
  constructor( message, code, status ) {
    super( message )
    this.name   = 'ApiError'
    this.code   = code
    this.status = status
  }
}

export async function apiFetch( endpoint, options = {} ) {
  const { restUrl, nonce } = window.pressioCrm

  const url = new URL( `${restUrl}/${endpoint}` )

  if ( options.params ) {
    Object.entries( options.params ).forEach( ( [ k, v ] ) => {
      if ( v !== null && v !== undefined && v !== '' ) {
        url.searchParams.set( k, v )
      }
    } )
  }

  const response = await fetch( url.toString(), {
    method:  options.method || 'GET',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce':   nonce,
    },
    body: options.body ? JSON.stringify( options.body ) : undefined,
  } )

  if ( ! response.ok ) {
    const error = await response.json().catch( () => ( {} ) )
    throw new ApiError(
      error.message || 'Request failed',
      error.code    || 'unknown_error',
      response.status
    )
  }

  if ( response.status === 204 ) {
    return null
  }

  const data = await response.json()

  const total = response.headers.get( 'X-WP-Total' )
  if ( total !== null ) {
    return {
      items: data,
      total: parseInt( total, 10 ) || 0,
      pages: parseInt( response.headers.get( 'X-WP-TotalPages' ), 10 ) || 1,
    }
  }

  return data
}
