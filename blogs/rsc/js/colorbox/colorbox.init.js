/**
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * Used only to initialize colorbox for the links with attribute "rel" ^= "lightbox"
 * Don't load this file directly, It is appended to "/build/jquery.colorbox.b2evo.min.js" by Grunt, because we load colorbox plugin asynchronously.
 *
 * @version $Id: colorbox.init.js 7525 2014-10-27 10:39:46Z yura $
 */
jQuery( document ).ready( function()
{
	jQuery( 'a[rel^="lightbox"]' ).each( function()
	{
		var type = jQuery( this ).attr( 'rel' ).match( /lightbox\[([a-z]+)/i );
		type = type ? type[1] : '';
		switch( type[1] )
		{
			case 'p': // post
				jQuery( this ).colorbox( b2evo_colorbox_params_post );
				break;
			case 'c': // comment
				jQuery( this ).colorbox( b2evo_colorbox_params_cmnt );
				break;
			case 'user': // user
				jQuery( this ).colorbox( b2evo_colorbox_params_user );
				break;
			default: // all others
				jQuery( this ).colorbox( b2evo_colorbox_params );
		}
	} );

	jQuery( '#colorbox' ).swipe(
	{ // Use swipe plugin for touch devices
		swipeLeft: function( event, direction, distance, duration, fingerCount )
		{
			jQuery.colorbox.next();
		},
		swipeRight: function( event, direction, distance, duration, fingerCount )
		{
			jQuery.colorbox.prev();
		},
	} );
	jQuery( document ).on( 'click', '#colorbox img.cboxPhoto', function()
	{
		if( jQuery( this ).hasClass( 'zoomout' ) )
		{ // Disable swipe plugin when picture is zoomed
			jQuery( '#colorbox' ).swipe( 'disable' );
		}
		else
		{ // Re-enable swipe plugin
			jQuery( '#colorbox' ).swipe( 'enable' );
		}
	} );
} );
