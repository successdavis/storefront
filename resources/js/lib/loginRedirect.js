import { route } from 'ziggy-js'

/**
 * Login URL that brings the user back to the page they are currently on
 * after signing in (used when a guest triggers an auth-only action).
 */
export function loginUrlWithRedirect() {
    const current = window.location.pathname + window.location.search + window.location.hash

    return route('login', { redirect: current })
}
