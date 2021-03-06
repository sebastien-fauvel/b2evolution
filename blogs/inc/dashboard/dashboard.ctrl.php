<?php
/**
 * This file implements the UI controller for the dashboard.
 *
 * b2evolution - {@link http://b2evolution.net/}
 * Released under GNU GPL License - {@link http://b2evolution.net/about/license.html}
 *
 * @copyright (c)2003-2007 by Francois PLANQUE - {@link http://fplanque.net/}
 *
 * {@internal Open Source relicensing agreement:
 * }}
 *
 * @package admin
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author fplanque: Francois PLANQUE.
 *
 * @todo add 5 plugin hooks. Will be widgetized later (same as SkinTag became Widgets)
 *
 * @version $Id: dashboard.ctrl.php 7512 2014-10-24 10:31:53Z yura $
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

/**
 * @var User
 */
global $current_User;

global $dispatcher, $allow_evo_stats, $blog;

if( $blog )
{
	if( ! $current_User->check_perm( 'blog_ismember', 'view', false, $blog ) )
	{	// We don't have permission for the requested blog (may happen if we come to admin from a link on a different blog)
		set_working_blog( 0 );
		unset( $Blog );
	}
}

$AdminUI->set_coll_list_params( 'blog_ismember', 'view', array(), T_('Global'), '?blog=0' );

$AdminUI->set_path( 'dashboard' );

// Load jquery UI to animate background color on change comment status and to transfer a comment to recycle bin
require_js( '#jqueryUI#' );

require_js( 'communication.js' ); // auto requires jQuery
// Load the appropriate blog navigation styles (including calendar, comment forms...):
require_css( 'blog_base.css' ); // Default styles for the blog navigation
// Colorbox (a lightweight Lightbox alternative) allows to zoom on images and do slideshows with groups of images:
require_js_helper( 'colorbox' );

// Include files to work with charts
require_js( '#easypiechart#' );
require_css( 'jquery/jquery.easy-pie-chart.css' );

$AdminUI->breadcrumbpath_init( true, array( 'text' => T_('Dashboard'), 'url' => '?ctrl=dashboard' ) );

// Display <html><head>...</head> section! (Note: should be done early if actions do not redirect)
$AdminUI->disp_html_head();

// Display title, menu, messages, etc. (Note: messages MUST be displayed AFTER the actions)
$AdminUI->disp_body_top();

if( $blog )
{	// We want to look at a specific blog:

	// load dashboard functions
	load_funcs( 'dashboard/model/_dashboard.funcs.php' );

	// Begin payload block:
	// This div is to know where to display the message after overlay close:
	echo '<div class="first_payload_block">'."\n";

	$AdminUI->disp_payload_begin();

	echo '<h2>'.$Blog->dget( 'name' ).'</h2>';

	echo '<div class="row browse"><div class="col-lg-9 col-xs-12 floatleft">';

	load_class( 'items/model/_itemlist.class.php', 'ItemList' );

	$block_item_Widget = new Widget( 'dash_item' );

	$nb_blocks_displayed = 0;

	$blog_moderation_statuses = explode( ',', $Blog->get_setting( 'moderation_statuses' ) );
	$highest_publish_status = get_highest_publish_status( 'comment', $Blog->ID, false );
	$user_modeartion_statuses = array();

	foreach( $blog_moderation_statuses as $status )
	{
		if( ( $status !== $highest_publish_status ) && $current_User->check_perm( 'blog_comment!'.$status, 'edit', false, $blog ) )
		{
			$user_modeartion_statuses[] = $status;
		}
	}
	$user_perm_moderate_cmt = count( $user_modeartion_statuses );

	if( $user_perm_moderate_cmt )
	{
		/*
		 * COMMENTS:
		 */
		$CommentList = new CommentList2( $Blog );

		// Filter list:
		$CommentList->set_filters( array(
				'types' => array( 'comment','trackback','pingback' ),
				'statuses' => $user_modeartion_statuses,
				'user_perm' => 'moderate',
				'post_statuses' => array( 'published', 'community', 'protected' ),
				'order' => 'DESC',
				'comments' => 30,
			) );

		// Set param prefix for URLs
		$param_prefix = 'cmnt_fullview_';
		if( !empty( $CommentList->param_prefix ) )
		{
			$param_prefix = $CommentList->param_prefix;
		}

		// Get ready for display (runs the query):
		$CommentList->display_init();
	}

	if( $user_perm_moderate_cmt && $CommentList->result_num_rows )
	{	// We have comments awaiting moderation

		load_funcs( 'comments/model/_comment_js.funcs.php' );

		$nb_blocks_displayed++;

		$opentrash_link = get_opentrash_link();
		$refresh_link = '<span class="floatright">'.action_icon( T_('Refresh comment list'), 'refresh', $admin_url.'?blog='.$blog, NULL, NULL, NULL, array( 'onclick' => 'startRefreshComments( \''.request_from().'\' ); return false;' ) ).'</span> ';

		$show_statuses_param = $param_prefix.'show_statuses[]='.implode( '&amp;'.$param_prefix.'show_statuses[]=', $user_modeartion_statuses );
		$block_item_Widget->title = $refresh_link.$opentrash_link.T_('Comments awaiting moderation').
			' <a href="'.$admin_url.'?ctrl=comments&amp;blog='.$Blog->ID.'&amp;'.$show_statuses_param.'" style="text-decoration:none">'.
			'<span id="badge" class="badge badge-important">'.$CommentList->get_total_rows().'</span></a>';

		echo '<div id="styled_content_block">';
		echo '<div id="comments_block">';

		$block_item_Widget->disp_template_replaced( 'block_start' );

		echo '<div id="comments_container">';

		// GET COMMENTS AWAITING MODERATION (the code generation is shared with the AJAX callback):
		show_comments_awaiting_moderation( $Blog->ID, $CommentList );

		echo '</div>';

		$block_item_Widget->disp_template_raw( 'block_end' );

		echo '</div>';
		echo '</div>';
	}

	/*
	 * RECENT POSTS awaiting moderation
	 */
	$post_moderation_statuses = explode( ',', $Blog->get_setting( 'post_moderation_statuses' ) );
	ob_start();
	foreach( $post_moderation_statuses as $status )
	{ // go through all statuses
		if( display_posts_awaiting_moderation( $status, $block_item_Widget ) )
		{ // a block was dispalyed for this status
			$nb_blocks_displayed++;
		}
	}
	$posts_awaiting_moderation_content = ob_get_contents();
	ob_clean();
	if( ! empty( $posts_awaiting_moderation_content ) )
	{
		echo '<div id="styled_content_block" class="items_container">';
		echo $posts_awaiting_moderation_content;
		echo '</div>';
	}

	/*
	 * RECENTLY EDITED
	 */
	// Create empty List:
	$ItemList = new ItemList2( $Blog, NULL, NULL );

	// Filter list:
	$ItemList->set_filters( array(
			'visibility_array' => get_visibility_statuses( 'keys', array('trash') ),
			'orderby' => 'datemodified',
			'order' => 'DESC',
			'posts' => 5,
		) );

	// Get ready for display (runs the query):
	$ItemList->display_init();

	if( $ItemList->result_num_rows )
	{	// We have recent edits

		$nb_blocks_displayed++;

		if( $current_User->check_perm( 'blog_post_statuses', 'edit', false, $Blog->ID ) )
		{	// We have permission to add a post with at least one status:
			$block_item_Widget->global_icon( T_('Write a new post...'), 'new', '?ctrl=items&amp;action=new&amp;blog='.$Blog->ID, T_('New post').' &raquo;', 3, 4 );
		}

		echo '<div id="styled_content_block" class="items_container">';

		$block_item_Widget->title = T_('Recently edited');
		$block_item_Widget->disp_template_replaced( 'block_start' );

		while( $Item = & $ItemList->get_item() )
		{
			echo '<div class="dashboard_post dashboard_post_'.($ItemList->current_idx % 2 ? 'even' : 'odd' ).'" lang="'.$Item->get('locale').'">';
			// We don't switch locales in the backoffice, since we use the user pref anyway
			// Load item's creator user:
			$Item->get_creator_User();

			$Item->status( array(
					'before' => '<div class="floatright"><span class="note status_'.$Item->status.'"><span>',
					'after'  => '</span></span></div>',
				) );

			echo '<div class="dashboard_float_actions">';
			$Item->edit_link( array( // Link to backoffice for editing
					'before'    => ' ',
					'after'     => ' ',
					'class'     => 'ActionButton btn btn-default',
					'text'      => get_icon( 'edit_button' ).' '.T_('Edit')
				) );
			echo '</div>';

			echo '<h3 class="dashboard_post_title">';
			$item_title = $Item->dget('title');
			if( ! strlen($item_title) )
			{
				$item_title = '['.format_to_output(T_('No title')).']';
			}
			echo '<a href="?ctrl=items&amp;blog='.$Blog->ID.'&amp;p='.$Item->ID.'">'.$item_title.'</a>';
			echo '</h3>';

			// Display images that are linked to this post:
			$Item->images( array(
					'before' =>              '<div class="dashboard_thumbnails">',
					'before_image' =>        '',
					'before_image_legend' => NULL,	// No legend
					'after_image_legend' =>  NULL,
					'after_image' =>         '',
					'after' =>               '</div>',
					'image_size' =>          'fit-80x80',
					// Optionally restrict to files/images linked to specific position: 'teaser'|'teaserperm'|'teaserlink'|'aftermore'|'inline'|'albumart'
					'restrict_to_image_position' => 'teaser,teaserperm,teaserlink',
				) );

			echo '<span class="small">'.htmlspecialchars( $Item->get_content_excerpt( 150 ), NULL, $evo_charset ).'</span>';

			echo '<div style="clear:left;">'.get_icon('pixel').'</div>'; // IE crap
			echo '</div>';
		}

		echo '</div>';

		$block_item_Widget->disp_template_raw( 'block_end' );
	}


	if( $nb_blocks_displayed == 0 )
	{	// We haven't displayed anything yet!

		$nb_blocks_displayed++;

		$block_item_Widget = new Widget( 'block_item' );
		$block_item_Widget->title = T_('Getting started');
		$block_item_Widget->disp_template_replaced( 'block_start' );

		echo '<p><strong>'.T_('Welcome to your new blog\'s dashboard!').'</strong></p>';

		echo '<p>'.T_('Use the links on the right to write a first post or to customize your blog.').'</p>';

		echo '<p>'.T_('You can see your blog page at any time by clicking "See" in the b2evolution toolbar at the top of this page.').'</p>';

 		echo '<p>'.T_('You can come back here at any time by clicking "Manage" in that same evobar.').'</p>';

		$block_item_Widget->disp_template_raw( 'block_end' );
	}


	/*
	 * DashboardBlogMain to be added here (anyone?)
	 */


	echo '</div><div class="col-lg-3 col-xs-12 floatright">';

	/*
	 * RIGHT COL
	 */

	$side_item_Widget = new Widget( 'side_item' );

	echo '<div class="row dashboard_sidebar_panels"><div class="col-lg-12 col-sm-6 col-xs-12">';

	$side_item_Widget->title = T_('Manage your blog');
	$side_item_Widget->disp_template_replaced( 'block_start' );

	echo '<div class="dashboard_sidebar">';
	echo '<ul>';
		if( $current_User->check_perm( 'blog_post_statuses', 'edit', false, $Blog->ID ) )
		{
			echo '<li><a href="'.$dispatcher.'?ctrl=items&amp;action=new&amp;blog='.$Blog->ID.'">'.T_('Write a new post').' &raquo;</a></li>';
		}

 		echo '<li>'.T_('Browse').':<ul>';
		echo '<li><a href="'.$dispatcher.'?ctrl=items&tab=full&filter=restore&blog='.$Blog->ID.'">'.T_('Posts (full)').' &raquo;</a></li>';
		echo '<li><a href="'.$dispatcher.'?ctrl=items&tab=list&filter=restore&blog='.$Blog->ID.'">'.T_('Posts (list)').' &raquo;</a></li>';
		if( $current_User->check_perm( 'blog_comments', 'edit', false, $Blog->ID ) )
		{
			echo '<li><a href="'.$dispatcher.'?ctrl=comments&amp;filter=restore&amp;blog='.$Blog->ID.'">'.T_('Comments').' &raquo;</a></li>';
		}
		echo '</ul></li>';

		if( $current_User->check_perm( 'blog_cats', '', false, $Blog->ID ) )
		{
			echo '<li><a href="'.$dispatcher.'?ctrl=chapters&blog='.$Blog->ID.'">'.T_('Edit categories').' &raquo;</a></li>';
		}

		echo '<li><a href="'.$Blog->get('url').'">'.T_('View this blog').'</a></li>';
	echo '</ul>';
	echo '</div>';

	$side_item_Widget->disp_template_raw( 'block_end' );

	echo '</div><div class="col-lg-12 col-sm-6 col-xs-12">';

	if( $current_User->check_perm( 'blog_properties', 'edit', false, $Blog->ID ) )
	{
		$side_item_Widget->title = T_('Customize your blog');
		$side_item_Widget->disp_template_replaced( 'block_start' );

		echo '<div class="dashboard_sidebar">';
		echo '<ul>';

		echo '<li><a href="'.$dispatcher.'?ctrl=coll_settings&amp;tab=general&amp;blog='.$Blog->ID.'">'.T_('Blog properties').' &raquo;</a></li>';
		echo '<li><a href="'.$dispatcher.'?ctrl=coll_settings&amp;tab=features&amp;blog='.$Blog->ID.'">'.T_('Blog features').' &raquo;</a></li>';
		echo '<li><a href="'.$dispatcher.'?ctrl=coll_settings&amp;tab=skin&amp;blog='.$Blog->ID.'">'.T_('Blog skin').' &raquo;</a></li>';
		echo '<li><a href="'.$dispatcher.'?ctrl=widgets&amp;blog='.$Blog->ID.'">'.T_('Blog widgets').' &raquo;</a></li>';
		echo '<li><a href="'.$dispatcher.'?ctrl=coll_settings&amp;tab=urls&amp;blog='.$Blog->ID.'">'.T_('Blog URLs').' &raquo;</a></li>';

		echo '</ul>';
		echo '</div>';

		$side_item_Widget->disp_template_raw( 'block_end' );
	}

	echo '</div></div>';

	/*
	 * DashboardBlogSide to be added here (anyone?)
	 */


	echo '</div><div class="clear"></div></div>';


	// End payload block:
	$AdminUI->disp_payload_end();

	echo '</div>'."\n";
}
else
{	// We're on the GLOBAL tab...

	$AdminUI->disp_payload_begin();
	echo '<h2>'.T_('Select a blog').'</h2>';
	// Display blog list VIEW:
	$AdminUI->disp_view( 'collections/views/_coll_list.view.php' );
	$AdminUI->disp_payload_end();


	/*
	 * DashboardGlobalMain to be added here (anyone?)
	 */
}


/*
 * Administrative tasks
 */

if( $current_User->check_perm( 'options', 'edit' ) )
{	// We have some serious admin privilege:

	load_funcs( 'dashboard/model/_dashboard.funcs.php' );

	/**
	 * @var AbstractSettings
	 */
	global $global_Cache;

	// Begin payload block:
	$AdminUI->disp_payload_begin();

	echo '<div class="row browse"><div class="col-lg-12">';

	//---- START OF - System & Collection stats ----//

	// -- Collection stats -- //
	if( ! empty( $blog ) )
	{
		$chart_data = array();

		// Posts
		$posts_sql_from = 'INNER JOIN T_categories ON cat_ID = post_main_cat_ID';
		$posts_sql_where = 'cat_blog_ID = '.$DB->quote( $blog );
		$chart_data[] = array(
				'title' => T_('Posts'),
				'value' => $post_all_counter = get_table_count( 'T_items__item', $posts_sql_where, $posts_sql_from ),
				'type'  => 'number',
			);
		// Slugs
		$slugs_sql_from = 'INNER JOIN T_items__item ON post_ID = slug_itm_ID '.$posts_sql_from;
		$slugs_sql_where = 'slug_type = "item" AND '.$posts_sql_where;
		$chart_data[] = array(
				'title' => T_('Slugs'),
				'value' => get_table_count( 'T_slug', $slugs_sql_where, $slugs_sql_from ),
				'type'  => 'number',
			);
		// Comments
		$comments_sql_from = 'INNER JOIN T_items__item ON post_ID = comment_item_ID '.$posts_sql_from;
		$comments_sql_where = $posts_sql_where;
		$chart_data[] = array(
				'title' => T_('Comments'),
				'value' => get_table_count( 'T_comments', $comments_sql_where, $comments_sql_from ),
				'type'  => 'number',
			);

		echo '<div class="row"><div class="col-lg-6">';

		// Display a block with charts
		$stat_item_Widget = new Widget( 'block_item' );

		$stat_item_Widget->title = T_('Collection stats');
		$stat_item_Widget->disp_template_replaced( 'block_start' );

		display_charts( $chart_data );

		$stat_item_Widget->disp_template_raw( 'block_end' );

		echo '</div>';
	}

	// -- System stats -- //

	$chart_data = array();
	// Users
	$chart_data[] = array(
			'title' => T_('Users'),
			'value' => get_table_count( 'T_users' ),
			'type'  => 'number',
		);
	// Blogs
	$chart_data[] = array(
			'title' => T_('Blogs'),
			'value' => get_table_count( 'T_blogs' ),
			'type'  => 'number',
		);
	$post_all_counter = get_table_count( 'T_items__item' );
	if( empty( $blog ) )
	{
		// Posts
		$chart_data[] = array(
				'title' => T_('Posts'),
				'value' => $post_all_counter,
				'type'  => 'number',
			);
	}
	// Web posts
	$chart_data[] = array(
			'title' => T_('Web posts'),
			'value' => limit_number_by_interval( $global_Cache->get( 'post_through_admin' ), 0, $post_all_counter ),
			'100%'  => $post_all_counter,
			'type'  => 'percent',
		);
	// XMLRPC posts
	$chart_data[] = array(
			'title' => T_('XMLRPC posts'),
			'value' => limit_number_by_interval( $global_Cache->get( 'post_through_xmlrpc' ), 0, $post_all_counter ),
			'100%'  => $post_all_counter,
			'type'  => 'percent',
		);
	// Email posts
	$chart_data[] = array(
			'title' => T_('Email posts'),
			'value' => limit_number_by_interval( $global_Cache->get( 'post_through_email' ), 0, $post_all_counter ),
			'100%'  => $post_all_counter,
			'type'  => 'percent',
		);
	if( empty( $blog ) )
	{
		// Slugs
		$chart_data[] = array(
				'title' => T_('Slugs'),
				'value' => get_table_count( 'T_slug' ),
				'type'  => 'number',
			);
		// Comments
		$chart_data[] = array(
				'title' => T_('Comments'),
				'value' => get_table_count( 'T_comments' ),
				'type'  => 'number',
			);
	}
	// Files
	$chart_data[] = array(
			'title' => T_('Files'),
			'value' => get_table_count( 'T_files' ),
			'type'  => 'number',
		);
	// Conversations
	$chart_data[] = array(
			'title' => T_('Conversations'),
			'value' => get_table_count( 'T_messaging__thread' ),
			'type'  => 'number',
		);
	// Messages
	$chart_data[] = array(
			'title' => T_('Messages'),
			'value' => get_table_count( 'T_messaging__message' ),
			'type'  => 'number',
		);

	if( ! empty( $blog ) )
	{ // Open second column if first was opened above
		echo '<div class="col-lg-6">';
	}

	$stat_item_Widget = new Widget( 'block_item' );

	$stat_item_Widget->title = T_('System stats');
	$stat_item_Widget->disp_template_replaced( 'block_start' );

	display_charts( $chart_data );

	$stat_item_Widget->disp_template_raw( 'block_end' );

	if( ! empty( $blog ) )
	{ // End of <div class="row"><div class="col-lg-6">
		echo '</div><div class="clear"></div></div>';
	}

?>
<script type="text/javascript">
jQuery( 'document' ).ready( function()
{
	var chart_params = {
		barColor: function(percent)
		{
			return get_color_by_percent( {r:0, g:255, b:0}, {r:255, g:204, b:0}, {r:255, g:0, b:0}, percent );
		},
		size: 75,
		trackColor: '#eee',
		scaleColor: false,
		lineCap: 'round',
		lineWidth: 6,
		animate: 700
	}
	jQuery( '.chart .number' ).easyPieChart( chart_params );

	chart_params['barColor'] = '#00F';
	jQuery( '.chart .percent' ).easyPieChart( chart_params );
} );

function get_color_by_percent( color_from, color_middle, color_to, percent )
{
	function get_color_hex( start_color, end_color )
	{
		num = start_color + Math.round( ( end_color - start_color ) * ( percent / 100 ) );
		num = Math.min( num, 255 ); // not more than 255
		num = Math.max( num, 0 ); // not less than 0
		var str = num.toString( 16 );
		if( str.length < 2 )
		{
			str = "0" + str;
		}
		return str;
	}

	if( percent < 50 )
	{
		color_to = color_middle;
		percent *= 2;
	}
	else
	{
		color_from = color_middle;
		percent = ( percent - 50 ) * 2;
	}

	return "#" +
		get_color_hex( color_from.r, color_to.r ) +
		get_color_hex( color_from.g, color_to.g ) +
		get_color_hex( color_from.b, color_to.b );
}
</script>
<?php
	//---- END OF - System & Collection stats ----//

	$block_item_Widget = new Widget( 'block_item' );

	$block_item_Widget->title = T_('Updates from b2evolution.net');
	$block_item_Widget->disp_template_replaced( 'block_start' );


	// Note: hopefully, the updates will have been downloaded in the shutdown function of a previous page (including the login screen)
	// However if we have outdated info, we will load updates here.

	// Let's clear any remaining messages that should already have been displayed before...
	$Messages->clear();

	if( b2evonet_get_updates() !== NULL )
	{	// Updates are allowed, display them:

		// Display info & error messages
		echo $Messages->display( NULL, NULL, false, 'action_messages' );

		$version_status_msg = $global_Cache->get( 'version_status_msg' );
		if( !empty($version_status_msg) )
		{	// We have managed to get updates (right now or in the past):
			echo '<p>'.$version_status_msg.'</p>';
			$extra_msg = $global_Cache->get( 'extra_msg' );
			if( !empty($extra_msg) )
			{
				echo '<p>'.$extra_msg.'</p>';
			}
		}

		$block_item_Widget->disp_template_replaced( 'block_end' );

		/*
		 * DashboardAdminMain to be added here (anyone?)
		 */
	}
	else
	{
		echo '<p>Updates from b2evolution.net are disabled!</p>';
		echo '<p>You will <b>NOT</b> be alerted if you are running an insecure configuration.</p>';
	}

	// Track just the first login into b2evolution to determine how many people installed manually vs automatic installs:
	if( $current_User->ID == 1 && $UserSettings->get('first_login') == NULL )
	{
		echo 'This is the Admin\'s first ever login.';
		echo '<img src="http://b2evolution.net/htsrv/track.php?key=first-ever-login" alt="" />';
		// OK, done. Never do this again from now on:
		$UserSettings->set('first_login', $localtimenow ); // We might actually display how long the system has been running somewhere
		$UserSettings->dbupdate();
	}


	/*
	 * DashboardAdminSide to be added here (anyone?)
	 */

	echo '</div></div>';

	// End payload block:
	$AdminUI->disp_payload_end();
}

// Display body bottom, debug info and close </html>:
$AdminUI->disp_global_footer();

?>