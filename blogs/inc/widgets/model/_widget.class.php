<?php
/**
 * This file implements the Widget class.
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2014 by Francois Planque - {@link http://fplanque.com/}
 *
 * {@internal License choice
 * - If you have received this file as part of a package, please find the license.txt file in
 *   the same folder or the closest folder above for complete license terms.
 * - If you have received this file individually (e-g: from http://evocms.cvs.sourceforge.net/)
 *   then you must choose one of the following licenses before using the file:
 *   - GNU General Public License 2 (GPL) - http://www.opensource.org/licenses/gpl-license.php
 *   - Mozilla Public License 1.1 (MPL) - http://www.opensource.org/licenses/mozilla1.1.php
 * }}
 *
 * @package evocore
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author fplanque: Francois PLANQUE.
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

load_class( '_core/model/dataobjects/_dataobject.class.php', 'DataObject' );

/**
 * ComponentWidget Class
 *
 * A ComponentWidget is a displayable entity that can be placed into a Container on a web page.
 *
 * @package evocore
 */
class ComponentWidget extends DataObject
{
	var $coll_ID;
	/**
	 * Container name
	 */
	var $sco_name;
	var $order;
	/**
	 * @var string Type of the plugin ("core" or "plugin")
	 */
	var $type;
	var $code;
	var $params;

	/**
	 * Indicates whether the widget is enabled.
	 *
	 * @var boolean
	 */
	var $enabled;

	/**
	 * Array of params which have been customized for this widget instance
	 *
	 * This is saved to the DB as a serialized string ($params)
	 */
	var $param_array = NULL;

	/**
	 * Array of params used during display()
	 */
	var $disp_params = NULL;

	/**
	 * Lazy instantiated.
	 *
	 * This gets set/used for widget plugins (those that hook into SkinTag).
	 * (false if this Widget is not handled by a Plugin)
	 * @see get_Plugin()
	 * @var Plugin
	 */
	var $Plugin;

	/**
	* @var BlockCache
	*/
	var $BlockCache;


	/**
	 * Constructor
	 *
	 * @param object data row from db
	 */
	function ComponentWidget( $db_row = NULL, $type = 'core', $code = NULL )
	{
		// Call parent constructor:
		parent::DataObject( 'T_widget', 'wi_', 'wi_ID' );

		if( is_null($db_row) )
		{	// We are creating an object here:
			// Using parent:: instead of $this-> in order to fix http://forums.b2evolution.net//viewtopic.php?p=94778
			parent::set( 'type', $type );
			parent::set( 'code', $code );
		}
		else
		{	// We are loading an object:
			$this->ID       = $db_row->wi_ID;
			$this->coll_ID  = $db_row->wi_coll_ID;
			$this->sco_name = $db_row->wi_sco_name;
			$this->type     = $db_row->wi_type;
			$this->code     = $db_row->wi_code;
			$this->params   = $db_row->wi_params;
			$this->order    = $db_row->wi_order;
			$this->enabled  = $db_row->wi_enabled;
		}
	}


	/**
	 * Get ref to the Plugin handling this Widget.
	 *
	 * @return Plugin
	 */
	function & get_Plugin()
	{
		global $Plugins;

		if( is_null( $this->Plugin ) )
		{
			if( $this->type != 'plugin' )
			{
				$this->Plugin = false;
			}
			else
			{
				$this->Plugin = & $Plugins->get_by_code( $this->code );
			}
		}

		return $this->Plugin;
	}


	/**
	 * Load params
	 */
	function load_from_Request()
	{
		load_funcs('plugins/_plugin.funcs.php');

		// Loop through all widget params:
		foreach( $this->get_param_definitions( array( 'for_editing' => true, 'for_updating' => true  ) ) as $parname => $parmeta )
		{
			autoform_set_param_from_request( $parname, $parmeta, $this, 'Widget' );
		}
	}


	/**
	 * Get name of widget
	 *
	 * Should be overriden by core widgets
	 */
	function get_name()
	{
		if( $this->type == 'plugin' )
		{
			// Make sure Plugin is loaded:
			if( $this->get_Plugin() )
			{
				return $this->Plugin->name;
			}
			return T_('Inactive / Uninstalled plugin');
		}

		return T_('Unknown');
	}


	/**
	 * Get a very short desc. Used in the widget list.
	 *
	 * MAY be overriden by core widgets. Example: menu link widget.
	 */
	function get_short_desc()
	{
		return $this->get_name();
	}


	/**
	 * Get a clean description to display in the widget list.
	 * @return string
	 */
	function get_desc_for_list()
	{
		$name = $this->get_name();

		if( $this->type == 'plugin' )
		{
			return '<strong>'.$name.'</strong> ('.T_('Plugin').')';
		}

		$short_desc = $this->get_short_desc();

		if( $name == $short_desc || empty($short_desc) )
		{
			return '<strong>'.$name.'</strong>';
		}

		return '<strong>'.$short_desc.'</strong> ('.$name.')';
	}


	/**
	 * Get desc of widget
	 *
	 * Should be overriden by core widgets
	 */
	function get_desc()
	{
		if( $this->type == 'plugin' )
		{
			// Make sure Plugin is loaded:
			if( $this->get_Plugin() )
			{
				return $this->Plugin->short_desc;
			}
			return T_('Inactive / Uninstalled plugin');
		}

		return T_('Unknown');
	}


	/**
	 * Get definitions for editable params.
	 *
	 * @see Plugin::GetDefaultSettings()
	 *
	 * @param array Local params like 'for_editing' => true
	 */
	function get_param_definitions( $params )
	{

		$r = array();

		if( $this->type == 'plugin' )
		{
			// Make sure Plugin is loaded:
			if( $this->get_Plugin() )
			{
				$r = $this->Plugin->get_widget_param_definitions( $params );
			}
		}

		$r_standart = array(
				'widget_css_class' => array(
					'label' => '<span class="dimmed">'.T_( 'CSS Class' ).'</span>',
					'size' => 20,
					'note' => T_( 'Replaces $wi_class$ in your skins containers.'),
				),
				'widget_ID' => array(
					'label' => '<span class="dimmed">'.T_( 'DOM ID' ).'</span>',
					'size' => 20,
					'note' => T_( 'Replaces $wi_ID$ in your skins containers.'),
				),
				'allow_blockcache' => array(
					'label' => T_( 'Allow caching' ),
					'note' => T_( 'Uncheck to prevent this widget from ever being cached in the block cache. (The whole page may still be cached.) This is only needed when a widget is poorly handling caching and cache keys.' ),
					'type' => 'checkbox',
					'defaultvalue' => true,
				),
			);

		return array_merge($r,$r_standart);;
	}


	/**
	 * Load param array.
	 */
	function load_param_array()
	{
		if( is_null( $this->param_array ) )
		{	// Param array has not been loaded yet
			$this->param_array = @unserialize( $this->params );

			if( empty( $this->param_array ) )
			{	// No saved param values were found:
				$this->param_array = array();
			}
		}
	}


	/**
 	 * Get param value.
 	 * @param string
 	 * @param boolean default false, set to true only if it is called from a widget::get_param_definition() function to avoid infinite loop
 	 * @return mixed
	 */
	function get_param( $parname, $check_infinite_loop = false )
	{
		$this->load_param_array();
		if( isset( $this->param_array[$parname] ) )
		{	// We have a value for this param:
			return $this->param_array[$parname];
		}

		// Try default values:
		// Note we set 'infinite_loop' param to avoid calling the get_param() from the get_param_definitions() function recursively
		$params = $this->get_param_definitions( $check_infinite_loop ? array( 'infinite_loop' => true ) : NULL );
		if( isset( $params[$parname]['defaultvalue'] ) )
		{	// We ahve a default value:
			return $params[$parname]['defaultvalue'] ;
		}

		return NULL;
	}


	/**
	 * Set param value
	 *
	 * @param string parameter name
	 * @param mixed parameter value
	 * @param boolean true to set to NULL if empty value
	 * @return boolean true, if a value has been set; false if it has not changed
	 */
	function set( $parname, $parvalue, $make_null = false )
	{
		$params = $this->get_param_definitions( NULL );

		if( isset( $params[$parname] ) )
		{ // This is a widget specific param:
			// Make sure param_array is loaded before set the param value
			$this->load_param_array();
			$this->param_array[$parname] = $parvalue;
			// This is what'll be saved to the DB:
			return $this->set_param( 'params', 'string', serialize($this->param_array), $make_null );
		}

		switch( $parname )
		{
			default:
				return $this->set_param( $parname, 'string', $parvalue, $make_null );
		}
	}


	/**
	 * Request all required css and js files for this widget
	 */
	function request_required_files()
	{
	}


	/**
	 * Prepare display params
	 *
	 * @todo Document default params and default values.
	 *       This might link to a wiki page, too.
	 *
	 * @param array MUST contain at least the basic display params
	 */
	function init_display( $params )
	{
		global $admin_url, $debug;

		if( !is_null($this->disp_params) )
		{ // Params have been initialized before...
			return;
		}

		// Generate widget defaults array:
		$widget_defaults = array();
		$defs = $this->get_param_definitions( array() );
		foreach( $defs as $parname => $parmeta )
		{
			if( isset( $parmeta['defaultvalue'] ) )
			{
				$widget_defaults[ $parname ] = $parmeta['defaultvalue'];
			}
			else
			{
				$widget_defaults[ $parname ] = NULL;
			}
		}

		// Load DB configuration:
		$this->load_param_array();

		// Merge basic defaults < widget defaults < container params < DB params
		// note: when called with skin_widget it falls back to basic defaults < widget defaults < calltime params < array()
		$params = array_merge( array(
					'block_start' => '<div class="$wi_class$">',
					'block_end' => '</div>',
					'block_display_title' => true,
					'block_title_start' => '<h3>',
					'block_title_end' => '</h3>',
					'block_body_start' => '',
					'block_body_end' => '',
					'collist_start' => '',
					'collist_end' => '',
					'coll_start' => '<h4>',
					'coll_end' => '</h4>',
					'list_start' => '<ul>',
					'list_end' => '</ul>',
					'item_start' => '<li>',
					'item_end' => '</li>',
					'link_default_class' => 'default',
					'link_selected_class' => 'selected',
					'item_text_start' => '',
					'item_text_end' => '',
					'item_text' => '%s',
					'item_selected_start' => '<li class="selected">',
					'item_selected_end' => '</li>',
					'item_selected_text' => '%s',
					'item_last_start' => '<li class="last">',
					'item_last_end' => '</li>',
					'grid_start' => '<table cellspacing="1" class="widget_grid">',
					'grid_end' => '</table>',
					'grid_nb_cols' => 2,
					'grid_colstart' => '<tr>',
					'grid_colend' => '</tr>',
					'grid_cellstart' => '<td>',
					'grid_cellend' => '</td>',
					'thumb_size' => 'crop-80x80',
					// 'thumb_size' => 'fit-160x120',
					'link_type' => 'canonic',		// 'canonic' | 'context' (context will regenrate URL injecting/replacing a single filter)
					'item_selected_text_start' => '',
					'item_selected_text_end' => '',
					'group_start' => '<ul>',
					'group_end' => '</ul>',
					'notes_start' => '<div class="notes">',
					'notes_end' => '</div>',
					'tag_cloud_start' => '<p class="tag_cloud">',
					'tag_cloud_end' => '</p>',
					'limit' => 100,
				), $widget_defaults, $params, $this->param_array );


		// Customize params to the current widget:
		// add additional css classes if required
		$widget_css_class = 'widget_'.$this->type.'_'.$this->code.( empty( $params[ 'widget_css_class' ] ) ? '' : ' '.$params[ 'widget_css_class' ] );
		// add custom id if required, default to generic id for validation purposes
		$widget_ID = ( !empty($params[ 'widget_ID' ]) ? $params[ 'widget_ID' ] : 'widget_'.$this->type.'_'.$this->code.'_'.$this->ID );
		// replace the values
		$this->disp_params = str_replace( array( '$wi_ID$', '$wi_class$' ), array( $widget_ID, $widget_css_class ), $params );
	}


	/**
	 * Display the widget!
	 *
	 * Should be overriden by core widgets
	 *
	 * @todo fp> handle custom params for each widget
	 *
	 * @param array MUST contain at least the basic display params
	 */
	function display( $params )
	{
		global $Blog;
		global $Plugins;
		global $rsc_url;

		$this->init_display( $params ); // just in case it hasn't been done before

		switch( $this->type )
		{
			case 'plugin':
				// Call plugin (will return false if Plugin is not enabled):
				if( $Plugins->call_by_code( $this->code, $this->disp_params ) )
				{
					return true;
				}
				// Plugin failed (happens when a plugin has been disabled for example):
				return false;
		}

		echo "Widget $this->type : $this->code did not provide a display() method! ";

		return false;
	}


	/**
	 * Wraps display in a cacheable block.
	 *
	 * @param array MUST contain at least the basic display params
	 * @param array of extra keys to be used for cache keying
	 */
	function display_with_cache( $params, $keys = array() )
	{
		global $Blog, $Timer, $debug, $admin_url, $Session;

		$this->init_display( $params );

		// Display the debug conatainers when $debug = 2 OR when it is turned on from evo menu under "Blog" -> "Show/Hide containers"
		$show_debug_containers = $Session->get( 'debug_containers_'.$Blog->ID ) == 1 || $debug == 2;

		if( ! $Blog->get_setting('cache_enabled_widgets')
			|| ! $this->disp_params['allow_blockcache'] )
		{	// NO CACHING - We do NOT want caching for this collection or for this specific widget:

			if( $show_debug_containers )
			{	// DEBUG:
				echo '<div class="debug_widget"><div class="debug_widget_name" title="'.
							( $Blog->get_setting('cache_enabled_widgets') ? 'Widget params have BlockCache turned off' : 'Collection params have BlockCache turned off' ).'"><span class="debug_container_action"><a href="'
							.$admin_url.'?ctrl=widgets&amp;action=edit&amp;wi_ID='.$this->ID.'">Edit</a></span>CACHE OFF: '.$this->get_name().'</div><div class="$wi_class$">'."\n";
			}

			$this->display( $params );

			if( $show_debug_containers )
			{	// DEBUG:
				echo "</div></div>\n";
			}
		}
		else
		{	// Instantiate BlockCache:
			$Timer->resume( 'BlockCache' );
			// Extend cache keys:
			$keys += $this->get_cache_keys();

			$this->BlockCache = new BlockCache( 'widget', $keys );

			$content = $this->BlockCache->check();

			$Timer->pause( 'BlockCache' );

			if( $content !== false )
			{ // cache hit, let's display:

				if( $show_debug_containers )
				{	// DEBUG:
					echo '<div class="debug_widget widget_in_cache"><div class="debug_widget_name" title="'.$this->BlockCache->serialized_keys.'"><span class="debug_container_action"><a href="'
								.$admin_url.'?ctrl=widgets&amp;action=edit&amp;wi_ID='.$this->ID.'">Edit</a></span>FROM CACHE: '.$this->get_name().'</div><div class="$wi_class$">'."\n";
				}

				echo $content;

				if( $show_debug_containers )
				{	// DEBUG:
					echo "</div></div>\n";
				}

			}
			else
			{	// Cache miss, we have to generate:

				if( $show_debug_containers )
				{	// DEBUG:
					echo '<div class="debug_widget widget_not_in_cache"><div class="debug_widget_name" title="'.$this->BlockCache->serialized_keys.'"><span class="debug_container_action"><a href="'
								.$admin_url.'?ctrl=widgets&amp;action=edit&amp;wi_ID='.$this->ID.'">Edit</a></span>NOT IN CACHE: '.$this->get_name().'</div><div class="$wi_class$">'."\n";
				}

				$this->BlockCache->start_collect();

				$this->display( $params );

				// Save collected cached data if needed:
				$this->BlockCache->end_collect();

				if( $show_debug_containers )
				{	// DEBUG:
					echo "</div></div>\n";
				}

			}
		}
	}


	/**
	 * Maybe be overriden by some widgets, depending on what THEY depend on..
	 *
	 * @return array of keys this widget depends on
	 */
	function get_cache_keys()
	{
		global $Blog;

		return array(
				'wi_ID'   => $this->ID,				// Have the widget settings changed ?
				'set_coll_ID' => $Blog->ID,		// Have the settings of the blog changed ? (ex: new skin)
			);
	}


	/**
	 * Note: a container can prevent display of titles with 'block_display_title'
	 * This is useful for the lists in the headers
	 * fp> I'm not sure if this param should be overridable by widgets themselves (priority problem)
	 * Maybe an "auto" setting.
	 *
	 * @access protected
	 */
	function disp_title( $title = NULL, $display = true )
	{
		if( is_null($title) )
		{
			$title = & $this->disp_params['title'];
		}

		if( $this->disp_params['block_display_title'] && !empty( $title ) )
		{
			$r = $this->disp_params['block_title_start'];
			$r .= format_to_output( $title );
			$r .= $this->disp_params['block_title_end'];

			if( $display ) echo $r;

			return $r;
		}
	}


	/**
	 * List of collections/blogs
	 *
	 * @param array MUST contain at least the basic display params
	 */
	function disp_coll_list( $filter = 'public', $order_by = 'ID', $order_dir = 'ASC' )
	{
		/**
		 * @var Blog
		 */
		global $Blog, $baseurl;

		echo $this->disp_params['block_start'];

		$this->disp_title();

		/**
		 * @var BlogCache
		 */
		$BlogCache = & get_BlogCache();

		if( $filter == 'owner' )
		{	// Load blogs of same owner
			$blog_array = $BlogCache->load_owner_blogs( $Blog->owner_user_ID, $order_by, $order_dir );
		}
		else
		{	// Load all public blogs
			$blog_array = $BlogCache->load_public( $order_by, $order_dir );
		}

		// 3.3? if( $this->disp_params['list_type'] == 'list' )
		// fp> TODO: init default value for $this->disp_params['list_type'] to avoid error
		{
			echo $this->disp_params['list_start'];

			foreach( $blog_array as $l_blog_ID )
			{	// Loop through all public blogs:

				$l_Blog = & $BlogCache->get_by_ID( $l_blog_ID );

				if( $Blog && $l_blog_ID == $Blog->ID )
				{ // This is the blog being displayed on this page:
				echo $this->disp_params['item_selected_start'];
					$link_class = $this->disp_params['link_selected_class'];
				}
				else
				{
					echo $this->disp_params['item_start'];
					$link_class = $this->disp_params['link_default_class'];;
				}

				echo '<a href="'.$l_Blog->gen_blogurl().'" class="'.$link_class.'" title="'
											.$l_Blog->dget( 'name', 'htmlattr' ).'">';

				if( $Blog && $l_blog_ID == $Blog->ID )
				{ // This is the blog being displayed on this page:
					echo $this->disp_params['item_selected_text_start'];
					printf( $this->disp_params['item_selected_text'], $l_Blog->dget( 'shortname', 'htmlbody' ) );
					echo $this->disp_params['item_selected_text_end'];
					echo '</a>';
					echo $this->disp_params['item_selected_end'];
				}
				else
				{
					echo $this->disp_params['item_text_start'];
					printf( $this->disp_params['item_text'], $l_Blog->dget( 'shortname', 'htmlbody' ) );
					echo $this->disp_params['item_text_end'];
					echo '</a>';
					echo $this->disp_params['item_end'];
				}
			}

			echo $this->disp_params['list_end'];
		}
		/* 3.3?
			Problems:
			-In FF3/XP with skin evoCamp, I click to drop down and it already reloads the page on the same blog.
			-Missing appropriate CSS so it displays at least half nicely in most of teh default skins
		{
			$select_options = '';
			foreach( $blog_array as $l_blog_ID )
			{	// Loop through all public blogs:
				$l_Blog = & $BlogCache->get_by_ID( $l_blog_ID );

				// Add item select list:
				$select_options .= '<option value="'.$l_blog_ID.'"';
				if( $Blog && $l_blog_ID == $Blog->ID )
				{
					$select_options .= ' selected="selected"';
				}
				$select_options .= '>'.$l_Blog->dget( 'shortname', 'formvalue' ).'</option>'."\n";
			}

			if( !empty($select_options) )
			{
				echo '<form action="'.$baseurl.'" method="get">';
				echo '<select name="blog" onchange="this.form.submit();">'.$select_options.'</select>';
				echo '<noscript><input type="submit" value="'.T_('Go').'" /></noscript></form>';
			}
		}
		*/
		echo $this->disp_params['block_end'];
	}


	/**
	 * Insert object into DB based on previously recorded changes.
	 *
	 * @return boolean true on success
	 */
	function dbinsert()
	{
		global $DB;

		if( $this->ID != 0 )
		{
			debug_die( 'Existing object cannot be inserted!' );
		}

		$DB->begin();

		$order_max = $DB->get_var(
			'SELECT MAX(wi_order)
				 FROM T_widget
				WHERE wi_coll_ID = '.$this->coll_ID.'
					AND wi_sco_name = '.$DB->quote($this->sco_name), 0, 0, 'Get current max order' );

		$this->set( 'order', $order_max+1 );

		$res = parent::dbinsert();

		$DB->commit();

		return $res;
	}


	/**
	 * Update the DB based on previously recorded changes
	 */
	function dbupdate()
	{
		global $DB;

		parent::dbupdate();

		// BLOCK CACHE INVALIDATION:
		// This widget has been modified, cached content depending on it should be invalidated:
		BlockCache::invalidate_key( 'wi_ID', $this->ID );
	}

}
?>