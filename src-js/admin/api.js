/**
 * REST calls of the admin apps (own-instagram/v1). apiFetch adds the
 * wp_rest nonce and the site's REST root itself.
 */
import apiFetch from '@wordpress/api-fetch';

const NS = '/own-instagram/v1';

export const getSite = () => apiFetch( { path: `${ NS }/site` } );

export const siteAction = ( action, returnUrl ) =>
	apiFetch( {
		path: `${ NS }/site/${ action }`,
		method: 'POST',
		data: { returnUrl },
	} );

export const getApp = () => apiFetch( { path: `${ NS }/app` } );

export const saveApp = ( data ) =>
	apiFetch( { path: `${ NS }/app`, method: 'POST', data } );

export const getSites = () => apiFetch( { path: `${ NS }/sites` } );

export const sitesAction = ( blogId, action, returnUrl ) =>
	apiFetch( {
		path: `${ NS }/sites/${ blogId }/${ action }`,
		method: 'POST',
		data: { returnUrl },
	} );

/** The admin page the browser is on, as the place to come back to. */
export const currentUrl = () => window.location.href;
