<?php
/**
 * This file implements the UI controller for skins management.
 *
 * b2evolution - {@link http://b2evolution.net/}
 *
 * @copyright (c)2003-2014 by Francois Planque - {@link http://fplanque.com/}
 * Parts of this file are copyright (c)2004-2006 by Daniel HAHLER - {@link http://thequod.de/contact}.
 *
 * Released under GNU GPL License - {@link http://b2evolution.net/about/license.html}
 *
 * {@internal Open Source relicensing agreement:
 * }}
 *
 * @package admin
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author fplanque: Francois PLANQUE.
 *
 * @version $Id: skins.ctrl.php 7178 2014-07-23 08:11:33Z yura $
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

// Memorize this as the last "tab" used in the Blog Settings:
$UserSettings->set( 'pref_coll_settings_tab', 'manage_skins' );
$UserSettings->dbupdate();

load_funcs( 'skins/_skin.funcs.php' );

// Check permission to display:
$current_User->check_perm( 'options', 'view', true );


param( 'action', 'string', 'list' );

param( 'redirect_to', 'url', '?ctrl=skins' );

if( param( 'skin_ID', 'integer', '', true) )
{// Load file type:
	$SkinCache = & get_SkinCache();
	if( ($edited_Skin = & $SkinCache->get_by_ID( $skin_ID, false )) === false )
	{	// We could not find the skin to edit:
		unset( $edited_Skin );
		forget_param( 'skin_ID' );
		$Messages->head = T_('Cannot edit skin!');
		$Messages->add( T_('Requested skin is not installed any longer.'), 'error' );
		$action = 'nil';
	}
}


/**
 * Perform action:
 */
switch( $action )
{
	case 'create':
		param( 'skin_folder', 'string', true );
		// Check validity of requested skin name:
		if( preg_match( '~([^-A-Za-z0-9._]|\.\.)~', $skin_folder ) )
		{
			debug_die( 'The requested skin name is invalid.' );
		}

		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'skin' );

		// Check permission to edit:
		$current_User->check_perm( 'options', 'edit', true );

		// CREATE NEW SKIN:
		$edited_Skin = & skin_install( $skin_folder );

		$Messages->add( T_('Skin has been installed.'), 'success' );

		// We want to highlight the edited object on next list display:
		$Session->set( 'fadeout_array', array( 'skin_ID' => array( $edited_Skin->ID ) ) );

		// Replace a mask by value. Used for install skin on creating of new blog
		$redirect_to = str_replace( '$skin_ID$', $edited_Skin->ID, $redirect_to );

		// PREVENT RELOAD & Switch to list mode:
		header_redirect( $redirect_to );
		break;


	case 'update':
		// Update skin properties:

		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'skin' );

		// Check permission:
		$current_User->check_perm( 'options', 'edit', true );

		// Make sure we got an skin_ID:
		param( 'skin_ID', 'integer', true );

		// load data from request
		if( $edited_Skin->load_from_Request() )
		{	// We could load data from form without errors:
			// Update in DB:
			$edited_Skin->dbupdate();
			$Messages->add( T_('Skin properties updated.'), 'success' );

			// We want to highlight the edited object on next list display:
			$Session->set( 'fadeout_array', array( 'skin_ID' => array($edited_Skin->ID) ) );

			// Redirect so that a reload doesn't write to the DB twice:
			header_redirect( $redirect_to, 303 ); // Will EXIT
			// We have EXITed already at this point!!
		}
		break;


	case 'reload':
		// Reload containers:

		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'skin' );

		// Check permission:
		$current_User->check_perm( 'options', 'edit', true );

		// Make sure we got an skin_ID:
		param( 'skin_ID', 'integer', true );

		// Save to DB:
		$edited_Skin->db_save_containers();

		// We want to highlight the edited object on next list display:
 		$Session->set( 'fadeout_array', array( 'skin_ID' => array($edited_Skin->ID) ) );

		// Redirect so that a reload doesn't write to the DB twice:
		header_redirect( $redirect_to, 303 ); // Will EXIT
		// We have EXITed already at this point!!
		break;


	case 'delete':
		// Uninstall a skin:

		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'skin' );

		// Check permission:
		$current_User->check_perm( 'options', 'edit', true );

		// Make sure we got an skin_ID:
		param( 'skin_ID', 'integer', true );

		if( param( 'confirm', 'integer', 0 ) )
		{ // confirmed, Delete from DB:
			$msg = sprintf( T_('Skin &laquo;%s&raquo; uninstalled.'), $edited_Skin->dget('name') );
			$edited_Skin->dbdelete( true );
			//unset( $edited_Skin );
			//forget_param( 'skin_ID' );
			$Messages->add( $msg, 'success' );

			// Redirect so that a reload doesn't write to the DB twice:
			header_redirect( $redirect_to, 303 ); // Will EXIT
			// We have EXITed already at this point!!
		}
		else
		{	// not confirmed, Check for restrictions:
			if( ! $edited_Skin->check_delete( sprintf( T_('Cannot uninstall skin &laquo;%s&raquo;'), $edited_Skin->dget('name') ) ) )
			{	// There are restrictions:
				$action = 'edit';
			}
		}

		break;


	case 'reset':
		// Reset settings to default values:

		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'skin' );

 		// Check permission:
		$current_User->check_perm( 'options', 'edit', true );

		// Make sure we got skin and blog IDs:
		param( 'skin_ID', 'integer', true );
		param( 'blog', 'integer', true );

		// At some point we may want to remove skin settings from all blogs
		$DB->query('DELETE FROM T_coll_settings
								 WHERE cset_coll_ID = '.$DB->quote($blog).'
								 			 AND cset_name REGEXP "^skin'.$skin_ID.'_"');

		$Messages->add( T_('Skin params have been reset to defaults.'), 'success' );

		// Redirect so that a reload doesn't write to the DB twice:
		header_redirect( '?ctrl=coll_settings&tab=skin&blog='.$blog, 303 ); // Will EXIT
		// We have EXITed already at this point!!
		break;
}


$AdminUI->set_path( 'blogs', 'skin', 'manage_skins' );


/**
 * Display page header, menus & messages:
 */
$AdminUI->set_coll_list_params( 'blog_properties', 'edit',
											array( 'ctrl' => 'skins' ),
											T_('Site'), '?ctrl=collections&amp;blog=0' );


$AdminUI->breadcrumbpath_init();
$AdminUI->breadcrumbpath_add( T_('Settings'), '?ctrl=coll_settings&amp;blog=$blog$' );
$AdminUI->breadcrumbpath_add( T_('Skin'), '?ctrl=coll_settings&amp;tab=skin&amp;blog=$blog$' );
$AdminUI->breadcrumbpath_add( T_('Skin configuration'), '?ctrl=skins' );


// Display <html><head>...</head> section! (Note: should be done early if actions do not redirect)
$AdminUI->disp_html_head();

// Display title, menu, messages, etc. (Note: messages MUST be displayed AFTER the actions)
$AdminUI->disp_body_top();

// Begin payload block:
$AdminUI->disp_payload_begin();

/**
 * Display Payload:
 */
switch( $action )
{
	case 'new':
		// Display VIEW:
		$AdminUI->disp_view( 'skins/views/_skin_list_available.view.php' );
		break;

	case 'delete':
		// We need to ask for confirmation:
		$edited_Skin->confirm_delete(
				sprintf( T_('Uninstall skin &laquo;%s&raquo;?'),  $edited_Skin->dget( 'name' ) ),
				'skin', $action, get_memorized( 'action' ) );
	case 'edit':
	case 'update':	// we return in this state after a validation error
		// Display VIEW:
		$AdminUI->disp_view( 'skins/views/_skin.form.php' );
		break;

	case 'list':
		// Display VIEW:
		$AdminUI->disp_view( 'skins/views/_skin_list.view.php' );
		break;
}

// End payload block:
$AdminUI->disp_payload_end();

// Display body bottom, debug info and close </html>:
$AdminUI->disp_global_footer();

?>