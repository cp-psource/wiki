<?php

/*
Plugin Name: PS-Wiki
Plugin URI: https://n3rds.work/piestingtal_source/ps-wiki-plugin/
Description: Ein simples aber mächtiges Wiki-Plugin für Deine WordPress Seite, inkl. Multisitesupport, Frontend-Editor, Rechtemanagment.
Author: Webmasterservice "Die N3rds"
Version: 1.3.4
Author URI: https://n3rds.work
Text Domain: wiki
*/



/*
Copyright 2019-2022 DerN3rd (https://n3rds.work)
Author - Der N3rd

This program is free software; you can redistribute it and/or modify

it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by

the Free Software Foundation.

This program is distributed in the hope that it will be useful,

but WITHOUT ANY WARRANTY; without even the implied warranty of

MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the

GNU General Public License for more details.

You should have received a copy of the GNU General Public License

along with this program; if not, write to the Free Software

Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/

require 'psource/psource-plugin-update/plugin-update-checker.php';
$MyUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
	'https://n3rds.work/wp-update-server/?action=get_metadata&slug=ps-wiki', 
	__FILE__, 
	'ps-wiki' 
);

class Wiki {

	// @var string Aktuelle Version

	var $version = '1.3.4';

	// @var string Der DB Prefix

	var $db_prefix = '';

	// @var string Die Plugin Einstellungen

	var $settings = array();

	// @var string Der für Wiki-Tags zu verwendende Slug

	var $slug_tags = 'tags';

	// @var string Der für Wiki-Kategorien zu verwendende Slug

	var $slug_categories = 'categories';

	// @var string Das Verzeichnis, in dem sich dieses Plugin befindet

	var $plugin_dir = '';

	// @var string Die Basis-URL des Plugins

	var $plugin_url = '';



	/**

	 * Bezieht sich auf unsere einzelne Instanz der Klasse

	 *

	 * @since 1.2.5

	 * @access private

	 */

	private static $_instance = null;



	/**

	 * Ruft die einzelne Instanz der Klasse ab

	 *

	 * @since 1.2.5

	 * @access public

	 */

	public static function get_instance() {

		if ( is_null(self::$_instance) ) {

			self::$_instance = new Wiki();

		}



		return self::$_instance;

	}



	/**

	 * Konstruktorfunktion

	 *

	 * @since 1.2.5

	 * @access private

	 */

	private function __construct() {

		$this->init_vars();


		add_action('init', array(&$this, 'init'));

		add_action('init', array(&$this, 'maybe_flush_rewrites'), 999);

		//add_action('current_screen', function(){ echo get_current_screen()->id; });



		add_action('wpmu_new_blog', array(&$this, 'new_blog'), 10, 6);



		add_action('admin_print_styles-settings_page_wiki', array(&$this, 'admin_styles'));

		add_action('admin_print_scripts-settings_page_wiki', array(&$this, 'admin_scripts'));



		add_action('add_meta_boxes_psource_wiki', array(&$this, 'meta_boxes') );

		add_action('wp_insert_post', array(&$this, 'save_wiki_meta'), 10, 2 );



		add_action('widgets_init', array(&$this, 'widgets_init'));

		add_action('pre_post_update', array(&$this, 'send_notifications'), 50, 1);

		add_filter('the_content', array(&$this, 'theme'), 999);	//auf wirklich niedrige Priorität gesetzt. Wir möchten, dass dies nach allen anderen Filtern ausgeführt wird, da es sonst zu unerwünschten Ausgaben kommen kann.

		add_action('template_include', array(&$this, 'load_templates') );



		add_filter('name_save_pre', array(&$this, 'name_save'));



		add_filter('role_has_cap', array(&$this, 'role_has_cap'), 10, 3);

		add_filter('user_has_cap', array(&$this, 'user_has_cap'), 10, 3);



		add_filter('get_edit_post_link', array(&$this, 'get_edit_post_link'));

		add_filter('comments_open', array(&$this, 'comments_open'), 10, 1);



		add_filter('user_can_richedit', array(&$this, 'user_can_richedit'));

		add_filter('wp_title', array(&$this, 'wp_title'), 10, 3);

		add_filter('the_title', array(&$this, 'the_title'), 10, 2);



		add_filter('404_template', array(&$this, 'not_found_template'));



		add_action('pre_get_posts', array( &$this, 'pre_get_posts'));



		add_filter('request', array(&$this, 'request'));



		add_filter('body_class', array(&$this, 'body_class'), 10);



		add_action('wp_enqueue_scripts', array( &$this, 'wp_enqueue_scripts'), 10);

	}



	/**

	 * Wird beim Hinzufügen eines neuen Blogs in Multisite ausgeführt

	 *

	 * @param int $blog_id

	 * @param int $user_id,

	 * @param string $domain

	 * @param string $path

	 * @param int $site_id

	 * @param array $meta

	 */

	function new_blog( $blog_id, $user_id, $domain, $path, $site_id, $meta ) {

		if ( is_plugin_active_for_network('ps-wiki/wiki.php') )

			$this->setup_blog($blog_id);

	}



	/**

	 * Holt sich den Tabellennamen mit Präfixen

	 *

	 * @param string $table	Tabellenname

	 * @uses $wpdb

	 * @return string Tabellenname komplett mit Präfixen

	 */

	function tablename($table) {

		global $wpdb;

		return $wpdb->base_prefix.'wiki_'.$table;

	}



	/**

	 * Plugin-Variablen initialisieren

	 * @since 1.2.4

	 */

	function init_vars() {

		global $wpdb;



		$this->db_prefix = ( !empty($wpdb->base_prefix) ) ? $wpdb->base_prefix : $wpdb->prefix;

		$this->plugin_dir = plugin_dir_path(__FILE__);

		$this->plugin_url = plugin_dir_url(__FILE__);

	}



	function request( $query_vars ) {

		if (!is_admin() && isset($query_vars['post_type']) && 'psource_wiki' == $query_vars['post_type'] && (isset($query_vars['orderby']) && $query_vars['orderby'] == 'menu_order title') && $query_vars['posts_per_page'] == '-1') {

			$query_vars['orderby'] = 'menu_order';

			unset($query_vars['posts_per_page']);

			unset($query_vars['posts_per_archive_page']);

			return $query_vars;

		}



		return $query_vars;

	}



	function the_title( $title, $id = false ) {

		global $wp_query, $post;



		if (!$id && get_query_var('post_type') == 'psource_wiki' && $wp_query->is_404) {

			$post_type_object = get_post_type_object( get_query_var('post_type') );



			if (current_user_can($post_type_object->cap->publish_posts)) {

				return ucwords(get_query_var('name'));

			}

		}



		return $title;

	}



	function body_class($classes) {

		if (get_query_var('post_type') == 'psource_wiki') {

			if (!in_array('psource_wiki', $classes)) {

				$classes[] = 'psource_wiki';

			}



			if (is_singular() && !in_array('single-psource_wiki', $classes)) {

				$classes[] = 'single-psource_wiki';

			}

		}



		return $classes;

	}



	function not_found_template( $path ) {

		global $wp_query;



		if ( 'psource_wiki' != get_query_var('post_type') )

			return $path;



		$post_type_object = get_post_type_object( get_query_var('post_type') );



		if (current_user_can($post_type_object->cap->publish_posts)) {

			$type = reset( explode( '_', current_filter() ) );

			$file = basename( $path );



			if ( empty( $path ) || "$type.php" == $file ) {

				// Eine spezifischere Vorlage wurde nicht gefunden, lade daher die Standardvorlage

				$path = $this->plugin_dir . "default-templates/$type-psource_wiki.php";

			}

			if ( file_exists( get_stylesheet_directory() . "/$type-psource_wiki.php" ) ) {

				$path = get_stylesheet_directory() . "/$type-psource_wiki.php";

			}

		}

		return $path;

	}



	function load_templates( $template ) {

		global $wp_query, $post;



		if ( is_single() && 'psource_wiki' == get_post_type() ) {

			//Sucht nach benutzerdefinierten Designvorlagen

			$wiki_name = $post->post_name;

			$wiki_id = (int) $post->ID;

			$templates = array('psource_wiki.php');



			if ( $wiki_name )

				$templates[] = "psource_wiki-$wiki_name.php";



			if ( $wiki_id )

				$templates[] = "psource_wiki-$wiki_id.php";



			if ( $new_template = locate_template($templates) ) {

				remove_filter('the_content', array(&$this, 'theme'), 1);

				return $new_template;

			}

		}



		return $template;

	}



	function pre_get_posts( $query ) {

		if( $query->is_main_query() && !is_admin() && !empty($query->query_vars['psource_wiki']) && preg_match('/\//', $query->query_vars['psource_wiki']) == 0 ) {

			$query->query_vars['post_parent'] = 0;

		}

	}



	function user_can_richedit($wp_rich_edit) {

		global $wp_query;



		if (get_query_var('post_type') == 'psource_wiki') {

			return true;

		}

		return $wp_rich_edit;

	}



	/**

	 * Überprüft, ob Neuschreibungen geleert werden sollten, und löscht sie

	 *

	 * @since 1.2.4

	 */

	function maybe_flush_rewrites() {

		if ( !get_option('wiki_flush_rewrites') )

			return;



		flush_rewrite_rules();

		delete_option('wiki_flush_rewrites');

	}



	function comments_open($open) {

		global $wp_query, $psource_tab_check;



		$action = isset($_REQUEST['action'])?$_REQUEST['action']:'view';

		if (get_query_var('post_type') == 'psource_wiki' && ($action != 'discussion')) {

			if ($psource_tab_check == 0 && !isset($_POST['submit']) && !isset($_POST['Submit'])) {

				return false;

			}

		}

		return $open;

	}



	function wp_title($title, $sep, $seplocation) {

		global $post, $wp_query;



		$tmp_title = "";

		$bc = 0;

		if (!$post && get_query_var('post_type') == 'psource_wiki' && $wp_query->is_404) {

			$post_type_object = get_post_type_object( get_query_var('post_type') );

			if (current_user_can($post_type_object->cap->publish_posts)) {

				$tmp_title = ucwords(get_query_var('name'));

				if ($seplocation == 'left') {

					$title = " {$sep} {$tmp_title}";

				}

				if ($seplocation == 'right') {

					$title = " {$tmp_title} {$sep} ";

				}

			}

		} else {

			if (isset($post->ancestors) && is_array($post->ancestors)) {

				foreach($post->ancestors as $parent_pid) {

					if ($bc >= $this->settings['breadcrumbs_in_title']) {

						break;

					}

					$parent_post = get_post($parent_pid);



					if ($seplocation == 'left') {

						$tmp_title .= " {$sep} ";

					}

					$tmp_title .= $parent_post->post_title;

					if ($seplocation == 'right') {

						$tmp_title .= " {$sep} ";

					}

					$bc++;

				}

			}



			$tmp_title = trim($tmp_title);

			if (!empty($tmp_title)) {

				if ($seplocation == 'left') {

					$title = "{$title} {$tmp_title} ";

				}

				if ($seplocation == 'right') {

					$title .= " {$tmp_title} ";

				}

			}

		}



		return $title;

	}



	/**

	 * Benennt $_POST-Daten von Formularnamen in DB-Post-Spalten um.

	 *

	 * Manipuliert $_POST direkt.

	 *

	 * @package WordPress

	 * @since 2.6.0

	 *

	 * @param bool $update Aktualisieren wir einen bereits bestehenden Beitrag?

	 * @param array $post_data Array von Post-Daten. Standardmäßig der Inhalt von $_POST.

	 * @return object|bool WP_Error bei Fehler, true bei Erfolg.

	 */

	function _translate_postdata( $update = false, $post_data = null ) {

		if ( empty($post_data) )

			$post_data = &$_POST;



		if ( $update )

			$post_data['ID'] = (int) $post_data['post_ID'];



		$post_data['post_content'] = isset($post_data['content']) ? $post_data['content'] : '';

		$post_data['post_excerpt'] = isset($post_data['excerpt']) ? $post_data['excerpt'] : '';

		$post_data['post_parent'] = isset($post_data['parent_id'])? $post_data['parent_id'] : '';

		if ( isset($post_data['trackback_url']) )

			$post_data['to_ping'] = $post_data['trackback_url'];



		if ( !isset($post_data['user_ID']) )

			$post_data['user_ID'] = $GLOBALS['user_ID'];



		if (!empty ( $post_data['post_author_override'] ) ) {

			$post_data['post_author'] = (int) $post_data['post_author_override'];

		} else {

			if (!empty ( $post_data['post_author'] ) ) {

				$post_data['post_author'] = (int) $post_data['post_author'];

			} else {

				$post_data['post_author'] = (int) $post_data['user_ID'];

			}

		}



		$ptype = get_post_type_object( $post_data['post_type'] );

		if ( isset($post_data['user_ID']) && ($post_data['post_author'] != $post_data['user_ID']) ) {

			if ( !current_user_can( $ptype->cap->edit_others_posts ) ) {

				if ( 'page' == $post_data['post_type'] ) {

					return new WP_Error( 'edit_others_pages', $update ?

						__( 'Du darfst als dieser Benutzer keine Seiten bearbeiten.' ) :

						__( 'Du darfst als dieser Benutzer keine Seiten erstellen.' )

					);

				} else {

					return new WP_Error( 'edit_others_posts', $update ?

						__( 'Du darfst als dieser Benutzer keine Beiträge bearbeiten.' ) :

						__( 'Du darfst nicht als dieser Benutzer posten.' )

					);

				}

			}

		}



		// What to do based on which button they pressed

		if ( isset($post_data['saveasdraft']) && '' != $post_data['saveasdraft'] )

			$post_data['post_status'] = 'draft';

		if ( isset($post_data['saveasprivate']) && '' != $post_data['saveasprivate'] )

			$post_data['post_status'] = 'private';

		if ( isset($post_data['publish']) && ( '' != $post_data['publish'] ) && ( !isset($post_data['post_status']) || $post_data['post_status'] != 'private' ) )

			$post_data['post_status'] = 'publish';

		if ( isset($post_data['advanced']) && '' != $post_data['advanced'] )

			$post_data['post_status'] = 'draft';

		if ( isset($post_data['pending']) && '' != $post_data['pending'] )

			$post_data['post_status'] = 'pending';



		if ( isset( $post_data['ID'] ) )

			$post_id = $post_data['ID'];

		else

			$post_id = false;

		$previous_status = $post_id ? get_post_field( 'post_status', $post_id ) : false;



		// Posts 'submitted for approval' present are submitted to $_POST the same as if they were being published.

		// Change status from 'publish' to 'pending' if user lacks permissions to publish or to resave published posts.

		if ( isset($post_data['post_status']) && ('publish' == $post_data['post_status'] && !current_user_can( $ptype->cap->publish_posts )) )

			if ( $previous_status != 'publish' || !current_user_can( 'edit_post', $post_id ) )

				$post_data['post_status'] = 'pending';



		if ( ! isset($post_data['post_status']) )

			$post_data['post_status'] = $previous_status;



		if (!isset( $post_data['comment_status'] ))

			$post_data['comment_status'] = 'closed';



		if (!isset( $post_data['ping_status'] ))

			$post_data['ping_status'] = 'closed';



		foreach ( array('aa', 'mm', 'jj', 'hh', 'mn') as $timeunit ) {

			if ( !empty( $post_data['hidden_' . $timeunit] ) && $post_data['hidden_' . $timeunit] != $post_data[$timeunit] ) {

				$post_data['edit_date'] = '1';

				break;

			}

		}



		if ( !empty( $post_data['edit_date'] ) ) {

			$aa = $post_data['aa'];

			$mm = $post_data['mm'];

			$jj = $post_data['jj'];

			$hh = $post_data['hh'];

			$mn = $post_data['mn'];

			$ss = $post_data['ss'];

			$aa = ($aa <= 0 ) ? date('Y') : $aa;

			$mm = ($mm <= 0 ) ? date('n') : $mm;

			$jj = ($jj > 31 ) ? 31 : $jj;

			$jj = ($jj <= 0 ) ? date('j') : $jj;

			$hh = ($hh > 23 ) ? $hh -24 : $hh;

			$mn = ($mn > 59 ) ? $mn -60 : $mn;

			$ss = ($ss > 59 ) ? $ss -60 : $ss;

			$post_data['post_date'] = sprintf( "%04d-%02d-%02d %02d:%02d:%02d", $aa, $mm, $jj, $hh, $mn, $ss );

			$post_data['post_date_gmt'] = get_gmt_from_date( $post_data['post_date'] );

		}



		return $post_data;

	}



	/**

	 * Aktualisiert einen vorhandenen Beitrag mit den in $_POST bereitgestellten Werten.

	 *

	 * @since 1.5.0

	 *

	 * @param array $post_data Optional.

	 * @return int Post ID.

	 */

	function edit_post( $post_data = null ) {

		if ( empty($post_data) )

			$post_data = &$_POST;



		$post_ID = (int) $post_data['post_ID'];



		$ptype = get_post_type_object($post_data['post_type']);



		if ( !current_user_can( $ptype->cap->edit_post, $post_ID ) ) {

			if ( 'page' == $post_data['post_type'] )

				wp_die( __('Du bist nicht berechtigt, diese Seite zu bearbeiten.' ));

			else

				wp_die( __('Du bist nicht berechtigt, diesen Beitrag zu bearbeiten.' ));

		}



		// Autosave sollte nicht zu früh nach einem echten Save speichern

		if ( 'autosave' == $post_data['action'] ) {

			$post =& get_post( $post_ID );

			$now = time();

			$then = strtotime($post->post_date_gmt . ' +0000');

			$delta = AUTOSAVE_INTERVAL / 2;

			if ( ($now - $then) < $delta )

				return $post_ID;

		}



		$post_data = $this->_translate_postdata( true, $post_data );

		$post_data['post_status'] = 'publish';

		if ( is_wp_error($post_data) )

			wp_die( $post_data->get_error_message() );

		if ( 'autosave' != $post_data['action']	 && 'auto-draft' == $post_data['post_status'] )

			$post_data['post_status'] = 'draft';



		if ( isset($post_data['visibility']) ) {

			switch ( $post_data['visibility'] ) {

			case 'public' :

				$post_data['post_password'] = '';

				break;

			case 'password' :

				unset( $post_data['sticky'] );

				break;

			case 'private' :

				$post_data['post_status'] = 'private';

				$post_data['post_password'] = '';

				unset( $post_data['sticky'] );

				break;

			}

		}



		// Beitragsformate

		if ( current_theme_supports( 'post-formats' ) && isset( $post_data['post_format'] ) ) {

			$formats = get_theme_support( 'post-formats' );

			if ( is_array( $formats ) ) {

				$formats = $formats[0];

				if ( in_array( $post_data['post_format'], $formats ) ) {

					set_post_format( $post_ID, $post_data['post_format'] );

				} elseif ( '0' == $post_data['post_format'] ) {

					set_post_format( $post_ID, false );

				}

			}

		}

		// Meta-Zeug

		if ( isset($post_data['meta']) && $post_data['meta'] ) {

			foreach ( $post_data['meta'] as $key => $value ) {

				if ( !$meta = get_post_meta_by_id( $key ) )

					continue;

				if ( $meta->post_id != $post_ID )

					continue;

				update_meta( $key, $value['key'], $value['value'] );

			}

		}



		if ( isset($post_data['deletemeta']) && $post_data['deletemeta'] ) {

			foreach ( $post_data['deletemeta'] as $key => $value ) {

				if ( !$meta = get_post_meta_by_id( $key ) )

					continue;

				if ( $meta->post_id != $post_ID )

					continue;

				delete_meta( $key );

			}

		}



		// add_meta( $post_ID );



		update_post_meta( $post_ID, '_edit_last', $GLOBALS['current_user']->ID );



		wp_update_post( $post_data );



		// Vereinigt alle verwaisten Anhänge wieder mit ihren Eltern

		if ( !$draft_ids = get_user_option( 'autosave_draft_ids' ) )

			$draft_ids = array();

		if ( $draft_temp_id = (int) array_search( $post_ID, $draft_ids ) )

			_relocate_children( $draft_temp_id, $post_ID );



		$this->set_post_lock( $post_ID, $GLOBALS['current_user']->ID );



		if ( current_user_can( $ptype->cap->edit_others_posts ) ) {

			if ( ! empty( $post_data['sticky'] ) )

				stick_post( $post_ID );

			else

				unstick_post( $post_ID );

		}



		return $post_ID;

	}



	function theme( $content ) {

		global $post;



		if ( !is_single() || 'psource_wiki' != get_post_type() )

			return $content;



		if ( post_password_required() )

			return $content;



		if ( function_exists('is_main_query') && !is_main_query() )

			return $content;



		$revision_id = isset($_REQUEST['revision'])?absint($_REQUEST['revision']):0;

		$left				= isset($_REQUEST['left'])?absint($_REQUEST['left']):0;

		$right				= isset($_REQUEST['right'])?absint($_REQUEST['right']):0;

		$action			= isset($_REQUEST['action'])?$_REQUEST['action']:'view';



		$new_content = '';



		if ($action != 'edit') {

			$new_content .= '<div class="psource_wiki psource_wiki_single">';



			if ( isset($_GET['restored']) ) {

				$new_content .= '<div class="psource_wiki_message">' . __('Revision erfolgreich wiederhergestellt', 'wiki') . ' <a class="dismiss" href="#">x</a></div>';

			}



			$new_content .= '<div class="psource_wiki_tabs psource_wiki_tabs_top">' . $this->tabs() . '<div class="psource_wiki_clear"></div></div>';

			$new_content .= $this->decider($content, $action, $revision_id, $left, $right);

		} else {

			$new_content .= $this->get_edit_form(false);

		}



		if ( !comments_open() ) {

			$new_content .= '<style type="text/css">'.

			'#comments { display: none; }'.

				'.comments { display: none; }'.

			'</style>';

		} else {

			$new_content .= '<style type="text/css">'.

			'.hentry { margin-bottom: 5px; }'.

			'</style>';

		}



		return $new_content;

	}



	function decider($content, $action, $revision_id = null, $left = null, $right = null, $stray_close = true) {

		global $post;



		$new_content = '';



		switch ($action) {

			case 'discussion':

				break;

			case 'edit':

				set_include_path(get_include_path().PATH_SEPARATOR.ABSPATH.'wp-admin');



				$post_type_object = get_post_type_object($post->post_type);



				$p = $post;



				if ( empty($post->ID) )

					wp_die( __('Du hast versucht, ein nicht vorhandenes Element zu bearbeiten. Vielleicht wurde es gelöscht?') );



				if ( !current_user_can($post_type_object->cap->edit_post, $post->ID) )

					wp_die( __('Du bist nicht berechtigt, dieses Element zu bearbeiten.') );



				if ( 'trash' == $post->post_status )

					wp_die( __('Du kannst dieses Element nicht bearbeiten, da es sich im Papierkorb befindet. Bitte stelle es wieder her und versuche es erneut.') );



				if ( null == $post_type_object )

					wp_die( __('Unbekannter Beitragstyp.') );



				$post_type = $post->post_type;



				if ( $last = $this->check_post_lock( $post->ID ) ) {

					add_action('admin_notices', '_admin_notice_post_locked' );

				} else {

					$this->set_post_lock( $post->ID );

					wp_enqueue_script('autosave');

				}



				$title = $post_type_object->labels->edit_item;

				$post = $this->post_to_edit($post->ID);



				$new_content = $this->get_edit_form(false);



				break;

			case 'restore':

				if ( ! $revision = wp_get_post_revision( $revision_id ) )

					break;

				if ( ! current_user_can( 'edit_post', $revision->post_parent ) )

					break;

				if ( ! $post = get_post( $revision->post_parent ) )

					break;



				// Überarbeitungen deaktiviert und wir betrachten keine automatische Speicherung

				if ( ( ! WP_POST_REVISIONS || !post_type_supports($post->post_type, 'revisions') ) && !wp_is_post_autosave( $revision ) ) {

					$redirect = get_permalink().'?action=edit';

					break;

				}



				check_admin_referer( "restore-post_$post->ID|$revision->ID" );



				wp_restore_post_revision( $revision->ID );

				$redirect = add_query_arg('restored', 1, get_permalink());

				break;

			case 'diff':

				if ( !$left_revision	= get_post( $left ) ) {

					break;

				}

				if ( !$right_revision = get_post( $right ) ) {

					break;

				}



				// Wenn wir eine Überarbeitung mit sich selbst vergleichen, leiten sie zur Ansichtsseite für diese Überarbeitung oder zur Bearbeitungsseite für diesen Beitrag weiter

				if ( $left_revision->ID == $right_revision->ID ) {

					$redirect = get_permalink().'?action=edit';

					break;

				}



				// Reverse Diffs nicht zulassen?

				if ( strtotime($right_revision->post_modified_gmt) < strtotime($left_revision->post_modified_gmt) ) {

					$redirect = add_query_arg( array( 'left' => $right, 'right' => $left ) );

					break;

				}



				if ( $left_revision->ID == $right_revision->post_parent ) // Rechts ist eine Überarbeitung von Links

					$post =& $left_revision;

				elseif ( $left_revision->post_parent == $right_revision->ID ) // Links ist eine Überarbeitung von Rechts

					$post =& $right_revision;

				elseif ( $left_revision->post_parent == $right_revision->post_parent ) // beide sind Revisionen des gemeinsamen Elternteils

					$post = get_post( $left_revision->post_parent );

				else

					break; // Vergleiche nicht zwei unabhängige Revisionen



				if ( ! WP_POST_REVISIONS || !post_type_supports($post->post_type, 'revisions') ) { // Revisions disabled

					if (

					// Wir sehen uns kein Autosave an

						( !wp_is_post_autosave( $left_revision ) && !wp_is_post_autosave( $right_revision ) )

					||

					// Wir vergleichen eine automatische Speicherung nicht mit dem aktuellen Beitrag

					( $post->ID !== $left_revision->ID && $post->ID !== $right_revision->ID )

					) {

					$redirect = get_permalink().'?action=edit';

					break;

					}

				}



				if (

					// Sie sind gleich

					$left_revision->ID == $right_revision->ID

					||

					// Auch keine Überarbeitung

					( !wp_get_post_revision( $left_revision->ID ) && !wp_get_post_revision( $right_revision->ID ) )

					) {

					break;

				}



				$post_title = '<a href="' . get_permalink().'?action=edit' . '">' . get_the_title() . '</a>';

				$h2 = sprintf( __( 'Vergleiche Revisionen von &#8220;%1$s&#8221;', 'wiki' ), $post_title );

				$title = __( 'Revisionen' );



				$left	 = $left_revision->ID;

				$right = $right_revision->ID;

			case 'history':

				$args = array( 'format' => 'form-table', 'parent' => false, 'right' => $right, 'left' => $left );

				if ( ! WP_POST_REVISIONS || !post_type_supports($post->post_type, 'revisions') ) {

					$args['type'] = 'autosave';

				}



				if (!isset($h2)) {

					$post_title = '<a href="' . get_permalink().'?action=edit' . '">' . get_the_title() . '</a>';

					$revisions = wp_get_post_revisions( $post->ID );

					$revision = array_shift($revisions);

					$revision_title = wp_post_revision_title( $revision, false );

					$h2 = sprintf( __( 'Revision für &#8220;%1$s&#8221; erstellt am %2$s', 'wiki' ), $post_title, $revision_title );

				}



				$new_content .= '<h3 class="long-header">'.$h2.'</h3>';

				$new_content .= '<table class="form-table ie-fixed">';

				$new_content .= '<col class="th" />';



				if ( 'diff' == $action ) :

					$new_content .= '<tr id="revision">';

					$new_content .= '<th scope="row"></th>';

					$new_content .= '<th scope="col" class="th-full">';

					$new_content .= '<span class="alignleft">'.sprintf( __('Älter: %s', 'wiki'), wp_post_revision_title( $left_revision, false ) ).'</span>';

					$new_content .= '<span class="alignright">'.sprintf( __('Neuer: %s', 'wiki'), wp_post_revision_title( $right_revision, false ) ).'</span>';

					$new_content .= '</th>';

					$new_content .= '</tr>';

				endif;



				// get_post_to_edit-Filter verwenden?

				$identical = true;

				foreach ( _wp_post_revision_fields() as $field => $field_title ) :

					if ( 'diff' == $action ) {

						$left_content = apply_filters( "_wp_post_revision_field_$field", $left_revision->$field, $field );

						$right_content = apply_filters( "_wp_post_revision_field_$field", $right_revision->$field, $field );

						if ( !$rcontent = wp_text_diff( $left_content, $right_content ) )

							continue; // Es gibt keinen Unterschied zwischen links und rechts

						$identical = false;

				} else {

						add_filter( "_wp_post_revision_field_$field", 'htmlspecialchars' );

						$rcontent = apply_filters( "_wp_post_revision_field_$field", $revision->$field, $field );

				}

					$new_content .= '<tr id="revision-field-' . $field . '">';

					$new_content .= '<th scope="row">'.esc_html( $field_title ).'</th>';

					$new_content .= '<td><div class="pre">'.$rcontent.'</div></td>';

					$new_content .= '</tr>';

				endforeach;



				if ( 'diff' == $action && $identical ) :

					$new_content .= '<tr><td colspan="2"><div class="updated"><p>'.__( 'Diese Revisionen sind identisch.', 'wiki' ). '</p></div></td></tr>';

				endif;



				$new_content .= '</table>';



				$new_content .= '<br class="clear" />';

				$new_content .= '<div class="psource_wiki_revisions">' . $this->list_post_revisions( $post, $args ) . '</div>';

				$redirect = false;

				break;

			default:

				$top = "";



				$crumbs = array('<a href="'.home_url($this->settings['slug']).'" class="psource_wiki_crumbs">'.$this->settings['wiki_name'].'</a>');

				foreach($post->ancestors as $parent_pid) {

					$parent_post = get_post($parent_pid);



					$crumbs[] = '<a href="'.get_permalink($parent_pid).'" class="psource_wiki_crumbs">'.$parent_post->post_title.'</a>';

				}



				$crumbs[] = '<span class="psource_wiki_crumbs">'.$post->post_title.'</span>';



				sort($crumbs);



				$top .= join(get_option("psource_meta_seperator", " > "), $crumbs);



				$taxonomy = "";



				if ( class_exists('Wiki_Premium') ) {

					$category_list = get_the_term_list( 0, 'psource_wiki_category', __( 'Wiki-Kategorie:', 'wiki' ) . ' <span class="psource_wiki-category">', '', '</span> ' );

					$tags_list = get_the_term_list( 0, 'psource_wiki_tag', __( 'Tags:', 'wiki' ) . ' <span class="psource_wiki-tags">', ' ', '</span> ' );



					$taxonomy .= apply_filters('the_terms', $category_list, 'psource_wiki_category', __( 'Wiki-Kategorie:', 'wiki' ) . ' <span class="psource_wiki-category">', '', '</span> ' );

					$taxonomy .= apply_filters('the_terms', $tags_list, 'psource_wiki_tag', __( 'Tags:', 'wiki' ) . ' <span class="psource_wiki-tags">', ' ', '</span> ' );

				}



				$children = get_posts(array(

					'post_parent' => $post->ID,

					'post_type' => 'psource_wiki',

					'orderby' => $this->settings['sub_wiki_order_by'],

					'order' => $this->settings['sub_wiki_order'],

					'numberposts' => 100000

				));



				$crumbs = array();

				foreach($children as $child) {

					$crumbs[] = '<a href="'.get_permalink($child->ID).'" class="psource_wiki_crumbs">'.$child->post_title.'</a>';

				}



				$bottom = "<h3>" . $this->settings['sub_wiki_name'] . "</h3> <ul><li>";



				$bottom .= join("</li><li>", $crumbs);



				if (count($crumbs) == 0) {

					$bottom = $taxonomy;

				} else {

					$bottom .= "</li></ul>";

					$bottom = "{$taxonomy} {$bottom}";

				}



				$revisions = wp_get_post_revisions($post->ID);



				if (current_user_can('edit_wiki', $post->ID)) {

					$bottom .= '<div class="psource_wiki-meta">';

					if (is_array($revisions) && count($revisions) > 0) {

					$revision = array_shift($revisions);

					}

					$bottom .= '</div>';

				}



				$notification_meta = get_post_meta($post->ID, 'psource_wiki_email_notification', true);



				if ( $notification_meta == 'enabled' && !$this->is_subscribed() ) {

					if (is_user_logged_in()) {

						$bottom .= '<div class="psource_wiki-subscribe"><a href="'.wp_nonce_url(add_query_arg(array('post_id' => $post->ID, 'subscribe' => 1)), "wiki-subscribe-wiki_$post->ID" ).'">'.__('Benachrichtige mich über Änderungen', 'wiki').'</a></div>';

					} else {

						if (!empty($_COOKIE['psource_wiki_email'])) {

							$user_email = $_COOKIE['psource_wiki_email'];

						} else {

							$user_email = "";

						}



						$bottom .= '<div class="psource_wiki-subscribe">'.

						'<form action="" method="post">'.

						'<label>'.__('E-mail', 'wiki').': <input type="text" name="email" id="email" value="'.$user_email.'" /></label> &nbsp;'.

						'<input type="hidden" name="post_id" id="post_id" value="'.$post->ID.'" />'.

						'<input type="submit" name="subscribe" id="subscribe" value="'.__('Benachrichtige mich über Änderungen', 'wiki').'" />'.

						'<input type="hidden" name="_wpnonce" id="_wpnonce" value="'.wp_create_nonce("wiki-subscribe-wiki_$post->ID").'" />'.

						'</form>'.

						'</div>';

					}

				}



				$new_content	= '<div class="psource_wiki_top">' . $top . '</div>'. $new_content;

				$new_content .= '<div class="psource_wiki_content">' . $content . '</div>';

				$new_content .= '<div class="psource_wiki_bottom">' . $bottom . '</div>';

				$redirect = false;

		}



		if ($stray_close) {

			$new_content .= '</div>';

		}



		// Ein leerer post_type bedeutet, dass entweder ein falsch formatiertes Objekt gefunden wurde oder kein gültiges übergeordnetes Element gefunden wurde.

		if ( isset($redirect) && !$redirect && empty($post->post_type) ) {

			$redirect = 'edit.php';

		}



		if ( !empty($redirect) ) {

			echo '<script type="text/javascript">'.

			'window.location = "'.$redirect.'";'.

			'</script>';

			exit;

		}



		return $new_content;

	}



	/**

	 * Standard-Beitragsinformationen, die beim Ausfüllen des Formulars „Beitrag schreiben“ verwendet werden.

	 *

	 * @since 2.0.0

	 *

	 * @param string $post_type Ein Beitragstyp-String, standardmäßig „post“.

	 * @return object stdClass-Objekt, das alle standardmäßigen Beitragsdaten als Attribute enthält

	 */

	function get_default_post_to_edit( $post_type = 'post', $create_in_db = false, $parent_id = 0 ) {

		global $wpdb;



		$post_title = '';

		if ( !empty( $_REQUEST['post_title'] ) )

			$post_title = esc_html( stripslashes( $_REQUEST['post_title'] ));



		$post_content = '';

		if ( !empty( $_REQUEST['content'] ) )

			$post_content = esc_html( stripslashes( $_REQUEST['content'] ));



		$post_excerpt = '';

		if ( !empty( $_REQUEST['excerpt'] ) )

			$post_excerpt = esc_html( stripslashes( $_REQUEST['excerpt'] ));



		if ( $create_in_db ) {

			// Bereinigt alte automatische Entwürfe, die älter als 7 Tage sind

			$old_posts = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_status = 'auto-draft' AND DATE_SUB( NOW(), INTERVAL 7 DAY ) > post_date" );

			foreach ( (array) $old_posts as $delete )

			wp_delete_post( $delete, true ); // Löschen erzwingen

			$post_id = wp_insert_post( array( 'post_parent' => $parent_id, 'post_title' => __( 'Auto Draft' ), 'post_type' => $post_type, 'post_status' => 'auto-draft' ) );

			$post = get_post( $post_id );

			if ( current_theme_supports( 'post-formats' ) && post_type_supports( $post->post_type, 'post-formats' ) && get_option( 'default_post_format' ) )

			set_post_format( $post, get_option( 'default_post_format' ) );

			// Wiki-Privilegien kopieren

			$privileges = get_post_meta($post->post_parent, 'psource_wiki_privileges');

			update_post_meta($post->ID, 'psource_wiki_privileges', $privileges[0]);

		} else {

			$post->ID = 0;

			$post->post_author = '';

			$post->post_date = '';

			$post->post_date_gmt = '';

			$post->post_password = '';

			$post->post_type = $post_type;

			$post->post_status = 'draft';

			$post->to_ping = '';

			$post->pinged = '';

			$post->comment_status = get_option( 'default_comment_status' );

			$post->ping_status = get_option( 'default_ping_status' );

			$post->post_pingback = get_option( 'default_pingback_flag' );

			$post->post_category = get_option( 'default_category' );

			$post->page_template = 'default';

			$post->post_parent = 0;

			$post->menu_order = 0;

		}



		$post->post_content = apply_filters( 'default_content', $post_content, $post );

		$post->post_title		= apply_filters( 'default_title', $post_title, $post	 );

		$post->post_excerpt = apply_filters( 'default_excerpt', $post_excerpt, $post );

		$post->post_name = '';



		return $post;

	}



	function enqueue_comment_hotkeys_js() {

		if ( 'true' == get_user_option( 'comment_shortcuts' ) )

				wp_enqueue_script( 'jquery-table-hotkeys' );

	}



		/**

		 * Holt sich einen vorhandenen Beitrag und formatiert ihn zur Bearbeitung.

		 *

		 * @since 2.0.0

		 *

		 * @param unknown_type $id

		 * @return unknown

		 */

	function post_to_edit( $id ) {

		$post = get_post( $id, OBJECT, 'edit' );



		if ( $post->post_type == 'page' )

			$post->page_template = get_post_meta( $id, '_wp_page_template', true );

		return $post;

	}



		/**

		 * Überprüft, ob der Beitrag gerade von einem anderen Benutzer bearbeitet wird.

		 *

		 * @since 2.5.0

		 *

		 * @param int $post_id ID des Beitrags, der auf Bearbeitung geprüft werden soll

		 * @return bool|int False: nicht gesperrt oder vom aktuellen Benutzer gesperrt. Int: Benutzer-ID des Benutzers mit Sperre.

		 */

	function check_post_lock( $post_id ) {

		if ( !$post = get_post( $post_id ) )

			return false;



		if ( !$lock = get_post_meta( $post->ID, '_edit_lock', true ) )

			return false;



		$lock = explode( ':', $lock );

		$time = $lock[0];

		$user = isset( $lock[1] ) ? $lock[1] : get_post_meta( $post->ID, '_edit_last', true );



		$time_window = apply_filters( 'wp_check_post_lock_window', AUTOSAVE_INTERVAL * 2 );



		if ( $time && $time > time() - $time_window && $user != get_current_user_id() )

			return $user;

		return false;

		}



		/**

		 * Markiert den Beitrag als derzeit vom aktuellen Benutzer bearbeitet

		 *

		 * @since 2.5.0

		 *

		 * @param int $post_id ID des Beitrags, der bearbeitet werden soll

		 * @return bool Gibt false zurück, wenn der Beitrag nicht existiert oder es keinen aktuellen Benutzer gibt

		 */

		function set_post_lock( $post_id ) {

		if ( !$post = get_post( $post_id ) )

			return false;

		if ( 0 == ($user_id = get_current_user_id()) )

			return false;



		$now = time();

		$lock = "$now:$user_id";



		update_post_meta( $post->ID, '_edit_lock', $lock );

	}



	/**

	 * Sicheres Abrufen einer Einstellung

	 *

	 * @param string $key

	 * @param mixed $default Der zurückzugebende Wert, wenn der Einstellungsschlüssel nicht gesetzt ist

	 * @since 1.2.3

	 */

	function get_setting( $key, $default = false ) {

		return isset($this->settings[$key]) ? $this->settings[$key] : $default;

	}



	function new_wiki_form() {

		global $wp_version, $wp_query, $edit_post, $post_id, $post_ID;



		echo '<div class="psource_wiki psource_wiki_single">';

		echo '<div class="psource_wiki_tabs psource_wiki_tabs_top"><div class="psource_wiki_clear"></div></div>';



		echo '<h3>'.__('BEARBEITEN', 'wiki').'</h3>';

		echo	'<form action="" method="post">';

		$edit_post = $this->get_default_post_to_edit(get_query_var('post_type'), true, 0);



		$post_id = $edit_post->ID;

		$post_ID = $post_id;



		$slug_parts = preg_split('/\//', $wp_query->query_vars['psource_wiki']);



		if (count($slug_parts) > 1) {

			for ($i=count($slug_parts)-1; $i>=0; $i--) {

				$parent_post = get_posts(array('name' => $slug_parts[$i], 'post_type' => 'psource_wiki', 'post_status' => 'publish'));

				if (is_array($parent_post) && count($parent_post) > 0) {

					break;

				}

			}

			$parent_post = $parent_post[0];

		}



		echo	'<input type="hidden" name="parent_id" id="parent_id" value="'.$parent_post->ID.'" />';

		echo	'<input type="hidden" name="original_publish" id="original_publish" value="Publish" />';

		echo	'<input type="hidden" name="publish" id="publish" value="Publish" />';

		echo	'<input type="hidden" name="post_type" id="post_type" value="'.$edit_post->post_type.'" />';

		echo	'<input type="hidden" name="post_ID" id="wiki_id" value="'.$edit_post->ID.'" />';

		echo	'<input type="hidden" name="post_status" id="wiki_id" value="published" />';

		echo	'<input type="hidden" name="comment_status" id="comment_status" value="open" />';

		echo	'<input type="hidden" name="action" id="wiki_action" value="editpost" />';

		echo	'<div><input type="hidden" name="post_title" id="wiki_title" value="'.ucwords(get_query_var('name')).'" class="psource_wiki_title" size="30" /></div>';

		echo	'<div>';

		wp_editor($edit_post->post_content, 'wikicontent', array('textarea_name' => 'content'));

		echo	'</div>';

		echo	'<input type="hidden" name="_wpnonce" id="_wpnonce" value="'.wp_create_nonce("wiki-editpost_{$edit_post->ID}").'" />';



		if (is_user_logged_in()) {

			echo	 $this->get_meta_form();

		}

		echo	'<div class="psource_wiki_clear">';

		echo	'<input type="submit" name="save" id="btn_save" value="'.__('Speichern', 'wiki').'" />&nbsp;';

		echo	'<a href="'.get_permalink().'">'.__('Abbrechen', 'wiki').'</a>';

		echo	'</div>';

		echo	'</form>';

		echo	'</div>';



		echo '<style type="text/css">'.

			'#comments { display: none; }'.

			'.comments { display: none; }'.

		'</style>';



		return '';

	}



	function get_edit_form($showheader = false) {

		global $post, $wp_version, $edit_post, $post_id, $post_ID;



		if ( !current_user_can('edit_wiki', $post->ID) && !current_user_can('edit_wikis', $post->ID) && !current_user_can('edit_others_wikis', $post->ID) && !current_user_can('edit_published_wikis', $post->ID) ) {

			return __('Du hast keine Berechtigung zum Anzeigen dieser Seite.', 'wiki');

		}



		$return = '';

		$stack = debug_backtrace();



		// Jet pack compatibility

		if (isset($stack[3]) && isset($stack[3]['class'])

			&& isset($stack[3]['function']) && $stack[3]['class'] == 'Jetpack_PostImages'

			&& $stack[3]['function'] == 'from_html') return $showheader;



		if ($showheader) {

			$return .= '<div class="psource_wiki psource_wiki_single">';

			$return .= '<div class="psource_wiki_tabs psource_wiki_tabs_top">' . $this->tabs() . '<div class="psource_wiki_clear"></div></div>';

		}

		$return .= '<h2>'.__('Bearbeiten', 'wiki').'</h2>';

		$return .=	'<form action="'.get_permalink().'" method="post">';

		if (isset($_REQUEST['eaction']) && $_REQUEST['eaction'] == 'create') {

			$edit_post = $this->get_default_post_to_edit($post->post_type, true, $post->ID);

			$return .=	 '<input type="hidden" name="parent_id" id="parent_id" value="'.$post->ID.'" />';

			$return .=	 '<input type="hidden" name="original_publish" id="original_publish" value="Publish" />';

			$return .=	 '<input type="hidden" name="publish" id="publish" value="Publish" />';

		} else {

			$edit_post = $post;

			$return .=	 '<input type="hidden" name="parent_id" id="parent_id" value="'.$edit_post->post_parent.'" />';

			$return .=	 '<input type="hidden" name="original_publish" id="original_publish" value="Update" />';

		}



		$post_id = $edit_post->ID;

		$post_ID = $post_id;



		$return .=	'<input type="hidden" name="post_type" id="post_type" value="'.$edit_post->post_type.'" />';

		$return .=	'<input type="hidden" name="post_ID" id="wiki_id" value="'.$edit_post->ID.'" />';



		if ( 'private' == $edit_post->post_status ) {

				$edit_post->post_password = '';

				$visibility = 'private';

					$visibility_trans = __('Privat');

		} elseif ( !empty( $edit_post->post_password ) ) {

				$visibility = 'password';

				$visibility_trans = __('Passwortgeschützt');

		} else {

				$visibility = 'public';

				$visibility_trans = __('Öffentlich');

		}



		$return .= '<input type="hidden" name="post_status" id="wiki_post_status" value="'.$edit_post->post_status.'" />';

		$return .= '<input type="hidden" name="visibility" id="wiki_visibility" value="'.$visibility.'" />';



		$return .= '<input type="hidden" name="comment_status" id="comment_status" value="'.$edit_post->comment_status.'" />';

		$return .= '<input type="hidden" name="action" id="wiki_action" value="editpost" />';

		$return .= '<div><input type="text" name="post_title" id="wiki_title" value="'.$edit_post->post_title.'" class="psource_wiki_title" size="30" /></div>';

		$return .= '<div>';



		if ( @ob_start() ) {

			// Die Ausgabepufferung ist aktiviert, erfasst die Ausgabe von wp_editor() und hängt sie an die $return-Variable an

			wp_editor($edit_post->post_content, 'wikicontent', array('textarea_name' => 'content'));

			$return .= ob_get_clean();

		} else {

			/*

			Das ist hacky, aber ohne Ausgabepufferung mussten wir eine Kopie der eingebauten Klasse _WP_Editors erstellen und

            die Methode editor() so geändert werden, dass die Ausgabe zurückgegeben wird, anstatt sie zu wiederholen. Das einzig schlechte daran ist, dass wir

            auch die media_buttons-Aktion entfernen mussten, damit Plugins/Themen nicht daran gebunden werden können

			*/

			require_once $this->plugin_dir . 'lib/classes/WPEditor.php';

			$return .= WikiEditor::editor($edit_post->post_content, 'wikicontent', array('textarea_name' => 'content'));

		}



		$return .= '</div>';

		$return .= '<input type="hidden" name="_wpnonce" id="_wpnonce" value="'.wp_create_nonce("wiki-editpost_{$edit_post->ID}").'" />';



		if (is_user_logged_in()) {

			$return .= $this->get_meta_form(true);

		}



		$return .= '<div class="psource_wiki_clear psource_wiki_form_buttons">';

		$return .= '<input type="submit" name="save" id="btn_save" value="'.__('Speichern', 'wiki').'" />&nbsp;';

		$return .= '<a href="'.get_permalink().'">'.__('Abbrechen', 'wiki').'</a>';

		$return .= '</div>';

		$return .= '</form>';



		if ($showheader) {

			$return .= '</div>';

		}



		$return .= '<style type="text/css">'.

			'#comments { display: none; }'.

			'.comments { display: none; }'.

		'</style>';



		return $return;

	}



	function get_meta_form( $frontend = false ) {

		global $post;



		$content	= '';



		if ( class_exists('Wiki_Premium') ) {

			$content .= ( $frontend ) ? '<h3 class="psource_wiki_header">' . __('Wiki Kategorien/Tags', 'wiki') . '</h3>' : '';

			$content .= '<div class="psource_wiki_meta_box">'. Wiki_Premium::get_instance()->wiki_taxonomies(false) . '</div>';

		}



		$content .= ( $frontend ) ? '<h3 class="psource_wiki_header">' . __('Wiki-Benachrichtigungen', 'wiki') . '</h3>' : '';

		$content .= '<div class="psource_wiki_meta_box">' . $this->notifications_meta_box($post, false) . '</div>';



		if ( current_user_can('edit_wiki_privileges') && class_exists('Wiki_Premium') ) {

			$content .= ( $frontend ) ? '<h3 class="psource_wiki_header">' . __('Wiki-Berechtigungen', 'wiki') . '</h3>' : '';

			$content .= '<div class="psource_wiki_meta_box">' . Wiki_Premium::get_instance()->privileges_meta_box($post, false) . '</div>';

		}



		return $content;

	}



	function tabs() {

		global $post, $psource_tab_check, $wp_query;



		$psource_tab_check = 1;

		$permalink = get_permalink();



		$classes = array();

		$classes['page'] = array('psource_wiki_link_page');

		$classes['discussion'] = array('psource_wiki_link_discussion');

		$classes['history'] = array('psource_wiki_link_history');

		$classes['edit'] = array('psource_wiki_link_edit');

		$classes['advanced_edit'] = array('psource_wiki_link_advanced_edit');

		$classes['create'] = array('psource_wiki_link_create');



		if (!isset($_REQUEST['action'])) {

			$classes['page'][] = 'current';

		}

		if (isset($_REQUEST['action'])) {

			switch ($_REQUEST['action']) {

				case 'page':

					$classes['page'][] = 'current';

					break;

				case 'discussion':

					$classes['discussion'][] = 'current';

					break;

				case 'restore':

				case 'diff':

				case 'history':

					$classes['history'][] = 'current';

					break;

				case 'edit':

					if (isset($_REQUEST['eaction']) && $_REQUEST['eaction'] == 'create')

					$classes['create'][] = 'current';

					else

					$classes['edit'][] = 'current';

					break;

			}

		}







		$tabs	 = '<ul class="left">';

		$tabs .= '<li class="'.join(' ', $classes['page']).'" ><a href="' . $permalink . '" >' . __('Wiki-Seite', 'wiki') . '</a></li>';

		if (comments_open()) {

			$tabs .= '<li class="'.join(' ', $classes['discussion']).'" ><a href="' . add_query_arg('action', 'discussion', $permalink) . '">' . __('Diskussion', 'wiki') . '</a></li>';

		}

		$tabs .= '<li class="'.join(' ', $classes['history']).'" ><a href="' . add_query_arg('action', 'history', $permalink) . '">' . __('Verlauf', 'wiki') . '</a></li>';

		$tabs .= '</ul>';



		$post_type_object = get_post_type_object( get_query_var('post_type') );



		if ($post && current_user_can($post_type_object->cap->edit_post, $post->ID)) {

			$tabs .= '<ul class="right">';

			$tabs .= '<li class="'.join(' ', $classes['edit']).'" ><a href="' . add_query_arg('action', 'edit', $permalink) . '">' . __('Bearbeiten', 'wiki') . '</a></li>';

			if (is_user_logged_in()) {

			$tabs .= '<li class="'.join(' ', $classes['advanced_edit']).'" ><a href="' . get_edit_post_link() . '" >' . __('Erweitertes bearbeiten', 'wiki') . '</a></li>';

			}

			$tabs .= '<li class="'.join(' ', $classes['create']).'"><a href="' . add_query_arg(array('action' => 'edit', 'eaction' => 'create'), $permalink) . '">'.__('Neues (Sub)Wiki', 'wiki').'</a></li>';

			$tabs .= '</ul>';

		}



		$psource_tab_check = 0;



		return $tabs;

	}



	function get_edit_post_link($url, $id = 0, $context = 'display') {

		global $post;

		return $url;

	}



	/**

	 * Liste der Überarbeitungen eines Beitrags anzeigen.

	 *

	 * Kann entweder eine UL mit Edit-Links oder eine TABLE mit Diff-Schnittstelle ausgeben, und

    * Aktionslinks wiederherstellen.

	 *

	 * Zweites Argument steuert Parameter:

	 *	 (bool)		parent : die übergeordnete Version (die "Aktuelle Revision") in die Liste aufnehmen.

	 *	 (string) format : 'list' oder 'form-table'.	'list' outputs UL, 'form-table'

	 *										gibt TABLE mit UI aus.

	 *	 (int)		right	 : welche Revision gerade angezeigt wird - verwendet in

    *                                         Formulartabellenformat.

	 *	 (int)		left	 : welche Revision wird derzeit gegen rechts unterschieden -

	 *										 im Formulartabellenformat verwendet.

	 *

	 * @package WordPress

	 * @subpackage Post_Revisions

	 * @since 2.6.0

	 *

	 * @uses wp_get_post_revisions()

	 * @uses wp_post_revision_title()

	 * @uses get_edit_post_link()

	 * @uses get_the_author_meta()

	 *

	 * @todo Aufteilung in zwei Funktionen (Liste, Formulartabelle) ?

	 *

	 * @param int|object $post_id Post ID or post object.

	 * @param string|array $args Siehe Beschreibung {@link wp_parse_args()}.

	 * @return null

	 */

	function list_post_revisions( $post_id = 0, $args = null ) {

		if ( !$post = get_post( $post_id ) )

			return;



		$content = '';

		$defaults = array( 'parent' => false, 'right' => false, 'left' => false, 'format' => 'list', 'type' => 'all' );

		extract( wp_parse_args( $args, $defaults ), EXTR_SKIP );

		switch ( $type ) {

			case 'autosave' :

				if ( !$autosave = wp_get_post_autosave( $post->ID ) )

					return;

				$revisions = array( $autosave );

				break;

			case 'revision' : // nur Überarbeitungen - Autosave später entfernen

			case 'all' :

			default :

				if ( !$revisions = wp_get_post_revisions( $post->ID ) )

					 return;

				break;

		}



		/* Übersetzer: Post-Revision: 1: wann, 2: Name des Autors */

		$titlef = _x( '%1$s von %2$s', 'post revision' );



		if ( $parent )

			array_unshift( $revisions, $post );



		$rows = '';

		$class = false;

		$can_edit_post = current_user_can( 'edit_wiki', $post->ID );

		foreach ( $revisions as $revision ) {

			/*if ( !current_user_can( 'read_post', $revision->ID ) )

			continue;*/

			if ( 'revision' === $type && wp_is_post_autosave( $revision ) )

				continue;



			$date = wp_post_revision_title( $revision, false );

			$name = get_the_author_meta( 'display_name', $revision->post_author );



			if ( 'form-table' == $format ) {

				if ( $left )

					$left_checked = $left == $revision->ID ? ' checked="checked"' : '';

				else

					$left_checked = (isset($right_checked) && $right_checked) ? ' checked="checked"' : ''; // [sic] (the next one)



				$right_checked = $right == $revision->ID ? ' checked="checked"' : '';



				$class = $class ? '' : " class='alternate'";



				if ( $post->ID != $revision->ID && $can_edit_post && current_user_can( 'read_post', $revision->ID ) )

					$actions = '<a href="' . wp_nonce_url( add_query_arg( array( 'revision' => $revision->ID, 'action' => 'restore' ) ), "restore-post_$post->ID|$revision->ID" ) . '">' . __( 'Wiederherstellen' ) . '</a>';

				else

					$actions = ' ';



				$rows .= "<tr$class>\n";

				$rows .= "\t<td style='white-space: nowrap' scope='row'><input type='radio' name='left' value='{$revision->ID}' {$left_checked} /></td>\n";

				$rows .= "\t<td style='white-space: nowrap' scope='row'><input type='radio' name='right' value='{$revision->ID}' {$right_checked} /></td>\n";

				$rows .= "\t<td>$date</td>\n";

				$rows .= "\t<td>$name</td>\n";

				$rows .= "\t<td class='action-links'>$actions</td>\n";

				$rows .= "</tr>\n";

			} else {

				$title = sprintf( $titlef, $date, $name );

				$rows .= "\t<li>$title</li>\n";

			}

		}

		if ( 'form-table' == $format ) :

			$content .= '<form action="'.get_permalink().'" method="get">';

			$content .= '<div class="tablenav">';

			$content .= '<div class="alignleft">';

			$content .= '<input type="submit" class="button-secondary" value="'.esc_attr( __('Revisionen vergleichen', 'wiki' ) ).'" />';

			$content .= '<input type="hidden" name="action" value="diff" />';

			$content .= '<input type="hidden" name="post_type" value="'.esc_attr($post->post_type).'" />';

			$content .= '</div>';

			$content .= '</div>';

			$content .= '<br class="clear" />';

			$content .= '<table class="widefat post-revisions" cellspacing="0" id="post-revisions">';

			$content .= '<col /><col /><col style="width: 33%" /><col style="width: 33%" /><col style="width: 33%" />';

			$content .= '<thead>';

			$content .= '<tr>';

			$content .= '<th scope="col">'._x( 'Alt', 'revisions column name', 'wiki' ).'</th>';

			$content .= '<th scope="col">'._x( 'Neu', 'revisions column name', 'wiki' ).'</th>';

			$content .= '<th scope="col">'._x( 'Datum erstellt', 'revisions column name', 'wiki' ).'</th>';

			$content .= '<th scope="col">'.__( 'Autor', 'wiki', 'wiki' ).'</th>';

			$content .= '<th scope="col" class="action-links">'.__( 'Aktionen', 'wiki' ).'</th>';

			$content .= '</tr>';

			$content .= '</thead>';

			$content .= '<tbody>';

			$content .= $rows;

			$content .= '</tbody>';

			$content .= '</table>';

			$content .= '</form>';

		else :

			$content .= "<ul class='post-revisions'>\n";

			$content .= $rows;

			$content .= "</ul>";

		endif;

		return $content;

	}



	function user_has_cap( $allcaps, $caps = null, $args = null ) {

		global $current_user, $blog_id, $post;



		$capable = false;



		if (preg_match('/(_wiki|_wikis)/i', join(',', $caps )) > 0) {

			if (in_array('administrator', $current_user->roles) || is_super_admin()) {

				foreach ($caps as $cap) {

					$allcaps[$cap] = 1;

				}

				return $allcaps;

			}

			foreach ($caps as $cap) {

				$capable = false;

				switch ($cap) {

					case 'read_wiki':

						$capable = true;

						break;



					case 'edit_others_wikis':

					case 'edit_published_wikis':

					case 'edit_wikis':

					case 'edit_wiki':

						if (isset($args[2])) {

							$edit_post = get_post($args[2]);

						} else if (isset($_REQUEST['post_ID'])) {

							$edit_post = get_post($_REQUEST['post_ID']);

						} else {

							$edit_post = $post;

						}



						if ($edit_post) {

							$current_privileges = get_post_meta($edit_post->ID, 'psource_wiki_privileges', true);



							if ( empty($current_privileges) ) {

								$current_privileges = array('edit_posts');

							}



							if ($edit_post->post_status == 'auto-draft') {

								$capable = true;

							} else if ($current_user->ID == 0) {

								if (in_array('anyone', $current_privileges)) {

									$capable = true;

								}

							} else {

								if (in_array('edit_posts', $current_privileges) && current_user_can('edit_posts')) {

									$capable = true;

								} else if (in_array('site', $current_privileges) && current_user_can_for_blog($blog_id, 'read')) {

									$capable = true;

								} else if (in_array('network', $current_privileges) && is_user_logged_in()) {

									$capable = true;

								} else if (in_array('anyone', $current_privileges)) {

									$capable = true;

								}

							}

						} else if (current_user_can('edit_posts')) {

							$capable = true;

						}

						break;



					default:

						if (isset($args[1]) && isset($args[2])) {

							if (current_user_can(preg_replace('/_wiki/i', '_post', $cap), $args[1], $args[2])) {

								$capable = true;

							}

						} else if (isset($args[1])) {

							if (current_user_can(preg_replace('/_wiki/i', '_post', $cap), $args[1])) {

								$capable = true;

							}

						} else if (current_user_can(preg_replace('/_wiki/i', '_post', $cap))) {

							$capable = true;

						}

						break;

				}



				if ($capable) {

					$allcaps[$cap] = 1;

				}

			}

		}



		return $allcaps;

	}



	function role_has_cap($capabilities, $cap, $name) {

		// nichts zu tun

		return $capabilities;

	}



	/**

	 * Installieren

	 *

	 * @$uses	$wpdb

	 */

	function install() {

		global $wpdb;



		if ( get_option('wiki_version', false) == $this->version )

			return;



		// Upgrade-/Erstellungsfunktionen für WordPress-Datenbanken

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';



		// Holt sich die richtige Zeichensortierung

		$charset_collate = '';

		if ( ! empty($wpdb->charset) )

			$charset_collate .= "DEFAULT CHARSET $wpdb->charset";

		if ( ! empty($wpdb->collate) )

			$charset_collate .= " COLLATE $wpdb->collate";



		// Richtet die Abonnementtabelle ein

		dbDelta("

			CREATE TABLE {$this->db_prefix}wiki_subscriptions (

				ID BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,

				blog_id BIGINT(20) NOT NULL,

				wiki_id BIGINT(20) NOT NULL,

				user_id BIGINT(20),

				email VARCHAR(255),

				PRIMARY KEY  (ID)

			) ENGINE=InnoDB $charset_collate;");



		$this->setup_blog();



		update_option('wiki_version', $this->version);

	}



	/**

	 * Richtet ein Blog ein - aufgerufen von install() und new_blog()

	 *

	 * @param int $blog (Optional) Wenn multisite blog_id übergeben wird, ansonsten NULL.

	 */

	function setup_blog( $blog_id = NULL ) {

		if ( !is_null($blog_id) )

			switch_to_blog($blog_id);



		// Administratorberechtigungen festlegen

		$role = get_role('administrator');

		$role->add_cap('edit_wiki_privileges');



		// Standardeinstellungen festlegen

		$default_settings = array(

			'slug' => 'wiki',

			'breadcrumbs_in_title' => 0,

			'wiki_name' => __('Wikis', 'wiki'),

			'sub_wiki_name' => __('Sub-Wikis', 'wiki'),

			'sub_wiki_order_by' => 'menu_order',

			'sub_wiki_order' => 'ASC'

		);



		// Migriert und löscht den Namen der alten Einstellungsoption, was nicht sehr intuitiv ist

		if ( $settings = get_option('wiki_default') )

			delete_option('wiki_default');

		else

			$settings = get_option('wiki_settings');



		// Einstellungen zusammenführen

		if ( is_array($settings) )

			$settings = wp_parse_args($settings, $default_settings);

		else

			$settings = $default_settings;



		// Update settings

		$this->settings = $settings;



		update_option('wiki_settings', $settings);

		update_option('wiki_flush_rewrites', 1);



		if ( !is_null($blog_id) ) {

			restore_current_blog();

			refresh_blog_details($blog_id);

		}

	}



	/**

	 * Initialisiert das Plugin

	 *

	 * @see		http://codex.wordpress.org/Plugin_API/Action_Reference

	 * @see		http://adambrown.info/p/wp_hooks/hook/init

	 */

	function init() {

		global $wpdb, $wp_rewrite, $current_user, $blog_id, $wp_roles;



		$this->install();	//Wir führen dies hier aus, weil Aktivierungs-Hooks beim Aktualisieren nicht ausgelöst werden - siehe http://wp.mu/8kv



		if ( is_admin() ) {

			$this->init_admin_pages();

		}



		$this->settings = get_option('wiki_settings');



		if (preg_match('/mu\-plugin/', $this->plugin_dir) > 0)

			load_muplugin_textdomain('wiki', dirname(plugin_basename(__FILE__)).'/languages');

		else

			load_plugin_textdomain('wiki', false, dirname(plugin_basename(__FILE__)).'/languages');



		if ( class_exists('Wiki_Premium') ) {

			// Taxonomien MÜSSEN vor benutzerdefinierten Beitragstypen registriert werden

			Wiki_Premium::get_instance()->register_taxonomies();

		}



		$this->register_post_types();



		if (isset($_REQUEST['action'])) {

			switch ($_REQUEST['action']) {

				case 'editpost':

					// Bearbeiten eines bestehenden Wikis mit dem Frontend-Editor

					if (wp_verify_nonce($_POST['_wpnonce'], "wiki-editpost_{$_POST['post_ID']}")) {

						$post_id = $this->edit_post($_POST);

						wp_redirect(get_permalink($post_id));

						exit();

					}

					break;

			}

		}



		if (isset($_REQUEST['subscribe']) && wp_verify_nonce($_REQUEST['_wpnonce'], "wiki-subscribe-wiki_{$_REQUEST['post_id']}")) {

			if (isset($_REQUEST['email'])) {

				if ($wpdb->insert("{$this->db_prefix}wiki_subscriptions",

					array('blog_id' => $blog_id,

					'wiki_id' => $_REQUEST['post_id'],

					'email' => $_REQUEST['email']))) {

					setcookie('psource_wiki_email', $_REQUEST['email'], time()+3600*24*365, '/');

					wp_redirect(get_permalink($_REQUEST['post_id']));

					exit();

				}

			} elseif (is_user_logged_in()) {

				$result = $wpdb->insert("{$this->db_prefix}wiki_subscriptions", array(

					'blog_id' => $blog_id,

					'wiki_id' => $_REQUEST['post_id'],

					'user_id' => $current_user->ID

				));



				if ( false !== $result ) {

					wp_redirect(get_permalink($_REQUEST['post_id']));

					exit();

				}

			}

		}



		if (isset($_GET['action']) && $_GET['action'] == 'cancel-wiki-subscription') {

			if ($wpdb->query("DELETE FROM {$this->db_prefix}wiki_subscriptions WHERE ID = ".intval($_GET['sid']).";")) {

				wp_redirect(get_option('siteurl'));

				exit();

			}

		}

	}



	/**

	 * Initialisiert die Plugin-Admin-Seiten

	 */

	function init_admin_pages() {

		$files = $this->get_dir_files($this->plugin_dir . 'admin-pages');



		foreach ( $files as $file )

			include_once $file;

	}



	/**

	 * Holt sich alle Dateien aus einem bestimmten Verzeichnis

	 * @param string $dir Der vollständige Pfad des Verzeichnisses

	 * @param string $ext Holt sich nur Dateien mit einer bestimmten Erweiterung. Auf NULL setzen, um alle Dateien zu erhalten.

	 */

	function get_dir_files( $dir, $ext = 'php' ) {

		$files = array();

		$dir = trailingslashit($dir);



		if ( !is_null($ext) )

			$ext = '.' . $ext;



		if ( !is_readable($dir) )

			return false;



		$files = glob($dir . '*' . $ext);



		return ( empty($files) ) ? false : $files;

	}



	/**

	 * Registriert benutzerdefinierte Beitragstypen des Plugins

	 * @since 1.2.4

	 */

	function register_post_types() {

		$slug = $this->settings['slug'];

		register_post_type('psource_wiki', array(

				'labels' => array(

					'name' => __('Wikis', 'wiki'),

					'singular_name' => __('Wiki', 'wiki'),

					'add_new' => __('Wiki hinzufügen', 'wiki'),

					'add_new_item' => __('Neues Wiki hinzufügen', 'wiki'),

					'edit_item' => __('Wiki bearbeiten', 'wiki'),

					'new_item' => __('Neues Wiki', 'wiki'),

					'view_item' => __('Wiki anzeigen', 'wiki'),

					'search_items' => __('Suche Wiki', 'wiki'),

					'not_found' =>	 __('Kein Wiki gefunden', 'wiki'),

					'not_found_in_trash' => __('Im Papierkorb wurden keine Wikis gefunden', 'wiki'),

					'menu_name' => __('Wikis', 'wiki')

				),

				'public' => true,

				'capability_type' => 'wiki',

				'hierarchical' => true,

				'map_meta_cap' => true,

				'query_var' => true,

				'supports' => array(

					'title',

					'editor',

					'author',

					'revisions',

					'comments',

					'page-attributes',

					'thumbnail',

				),

				'has_archive' => true,

				'rewrite' => array(

					'slug' => $slug,

					'with_front' => false

				),

				'menu_icon' => $this->plugin_url . '/images/icon.png',

				'taxonomies' => array(

					'psource_wiki_category',

					'psource_wiki_tag',

				),

			)

		);

	}



	function wp_enqueue_scripts() {

		if ( get_query_var('post_type') != 'psource_wiki' ) { return; }



		wp_enqueue_script('utils');

		wp_enqueue_script('jquery');

		wp_enqueue_script('psource_wiki-js', $this->plugin_url . 'js/wiki.js', array('jquery'), $this->version);

		wp_enqueue_style('psource_wiki-css', $this->plugin_url . 'css/style.css', null, $this->version);

		wp_enqueue_style('psource_wiki-print-css', $this->plugin_url . 'css/print.css', null, $this->version, 'print');



		wp_localize_script('psource_wiki-js', 'Wiki', array(

			'restoreMessage' => __('Bist Du sicher, dass Sie diese Version wiederherstellen möchtest?', 'wiki'),

		));

	}



	function is_subscribed() {

		global $wpdb, $current_user, $post, $blog_id;



		if ( is_user_logged_in() )

			return $wpdb->get_var("SELECT COUNT(ID) FROM {$this->db_prefix}wiki_subscriptions WHERE blog_id = {$blog_id} AND wiki_id = {$post->ID} AND user_id = {$current_user->ID}");



		if ( isset($_COOKIE['psource_wiki_email']) )

			return (bool) $wpdb->get_var("SELECT COUNT(ID) FROM {$this->db_prefix}wiki_subscriptions WHERE blog_id = {$blog_id} AND wiki_id = {$post->ID} AND email = '{$_COOKIE['psource_wiki_email']}'");



		return false;

	}



	function meta_boxes() {

		global $post, $current_user;



		if ($post->post_author == $current_user->ID || current_user_can('edit_posts')) {

			add_meta_box('psource-wiki-notifications', __('Wiki-E-Mail-Benachrichtigungen', 'wiki'), array(&$this, 'notifications_meta_box'), 'psource_wiki', 'side');

		}

	}



	function post_type_link($permalink, $post_id, $leavename) {

		$post = get_post($post_id);



		$rewritecode = array(

			'%psource_wiki%'

		);



		if ($post->post_type == 'psource_wiki' && '' != $permalink) {



			$ptype = get_post_type_object($post->post_type);



			if ($ptype->hierarchical) {

			$uri = get_page_uri($post);

			$uri = untrailingslashit($uri);

			$uri = strrev( stristr( strrev( $uri ), '/' ) );

			$uri = untrailingslashit($uri);



			if (!empty($uri)) {

				$uri .= '/';

				$permalink = str_replace('%psource_wiki%', "{$uri}%psource_wiki%", $permalink);

			}

			}



			$rewritereplace = array(

				($post->post_name == "")?(isset($post->id)?$post->id:0):$post->post_name

			);

			$permalink = str_replace($rewritecode, $rewritereplace, $permalink);

		} else {

			// wenn nicht die Pretty Permalink-Option verwendet

		}



		return $permalink;

	}



	function name_save($post_name) {

		if ($_POST['post_type'] == 'psource_wiki' && empty($post_name)) {

			$post_name = $_POST['post_title'];

		}



		return $post_name;

	}



	function notifications_meta_box( $post, $echo = true ) {

		$settings = get_option('psource_wiki_settings');

		$email_notify = get_post_meta($post->ID, 'psource_wiki_email_notification', true);



		if ( false === $email_notify )

			$email_notify = 'enabled';



		$content	= '';

		$content .= '<input type="hidden" name="psource_wiki_notifications_meta" value="1" />';

		$content .= '<div class="alignleft">';

		$content .= '<label><input type="checkbox" name="psource_wiki_email_notification" value="enabled" ' . checked('enabled', $email_notify, false) .' /> '.__('Aktiviere E-Mail-Benachrichtigungen', 'wiki').'</label>';

		$content .= '</div>';

		$content .= '<div class="clear"></div>';



		if ($echo) {

			echo $content;

		}

		return $content;

	}



	function save_wiki_meta($post_id, $post = null) {

		//Schnellbearbeitung überspringen

		if ( defined('DOING_AJAX') )

			return;



		if ( $post->post_type == "psource_wiki" && isset( $_POST['psource_wiki_notifications_meta'] ) ) {

			$meta = get_post_custom($post_id);

			$email_notify = isset($_POST['psource_wiki_email_notification']) ? $_POST['psource_wiki_email_notification'] : 0;



			update_post_meta($post_id, 'psource_wiki_email_notification', $email_notify);



			//für jedes andere Plugin, in das man sich einklinken kann

			do_action( 'psource_wiki_save_notifications_meta', $post_id, $meta );

		}

	}



	function widgets_init() {

		include_once 'lib/classes/WikiWidget.php';

		register_widget('WikiWidget');

	}



	function send_notifications($post_id) {

		global $wpdb;



		// Wir speichern manuell mit wp_publish_posts_autosave()

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )

				return;



		if ( !$post = get_post( $post_id, ARRAY_A ) )

				return;



		if ( $post['post_type'] != 'psource_wiki' || !post_type_supports($post['post_type'], 'revisions') )

				return;



		// alle Revisionen und (möglicherweise) eine automatische Speicherung

		$revisions = wp_get_post_revisions($post_id, array( 'order' => 'ASC' ));



		$revision = array_pop($revisions);



		$post = get_post($post_id);



		$cancel_url = get_option('siteurl') . '?action=cancel-wiki-subscription&sid=';

		$admin_email = get_option('admin_email');

		$post_title = $post->post_title;

		$post_content = $post->post_content;

		$post_url = get_permalink($post_id);



		$revisions = wp_get_post_revisions($post->ID);

		$revision = array_shift($revisions);



		if ($revision) {

			$revert_url = wp_nonce_url(add_query_arg(array('revision' => $revision->ID), admin_url('revision.php')), "restore-post_$post->ID|$revision->ID" );

		} else {

			$revert_url = "";

		}



		//Bereinige Titel

		$blog_name = get_option('blogname');

		$post_title = strip_tags($post_title);

		//Bereinige Inhalt

		$post_content = strip_tags($post_content);

		//Auszug bekommen

		$post_excerpt = $post_content;

		if (strlen($post_excerpt) > 255) {

			$post_excerpt = substr($post_excerpt,0,252) . 'Weiterlesen...';

		}

		//Email-Nachrichten 

		$wiki_notification_content = array();

		$wiki_notification_content['user'] = sprintf(__("Sehr geehrter Abonnent,



%s wurde geändert



Du kannst die Wiki-Seite hier vollständig lesen: %s



%s



Danke,

BLOGNAME



Abonnement kündigen: CANCEL_URL", 'POST TITLE', 'wiki'), 'POST_URL', 'EXCERPT', 'BLOGNAME');



		if ($revision) {

			$wiki_notification_content['author'] = sprintf(__("Lieber Autor,



%s wurde verändert



Du kannst die Wiki-Seite hier vollständig lesen: %s

Du kannst die Änderungen rückgängig machen: %s



%s



Danke,

%s



Abonnement kündigen: %s", 'wiki'), 'POST_TITLE', 'POST_URL', 'REVERT_URL', 'EXCERPT', 'BLOGNAME', 'CANCEL_URL');

			 } else {

			$wiki_notification_content['author'] = sprintf(__("Lieber Autor,



%s wurde verändert



Du kannst die Wiki-Seite hier vollständig lesen: %s



%s



Danke,

%s



Abonnement kündigen: %s", 'wiki'), 'POST_TITLE', 'POST_URL', 'EXCERPT', 'BLOGNAME', 'CANCEL_URL');

			 }



		//Benachrichtigungstext formatieren

		foreach ($wiki_notification_content as $key => $content) {

			$wiki_notification_content[$key] = str_replace("BLOGNAME",$blog_name,$wiki_notification_content[$key]);

			$wiki_notification_content[$key] = str_replace("POST_TITLE",$post_title,$wiki_notification_content[$key]);

			$wiki_notification_content[$key] = str_replace("EXCERPT",$post_excerpt,$wiki_notification_content[$key]);

			$wiki_notification_content[$key] = str_replace("POST_URL",$post_url,$wiki_notification_content[$key]);

			$wiki_notification_content[$key] = str_replace("REVERT_URL",$revert_url,$wiki_notification_content[$key]);

			$wiki_notification_content[$key] = str_replace("\'","'",$wiki_notification_content[$key]);

		}



		global $blog_id;



		$query = "SELECT * FROM " . $this->db_prefix . "wiki_subscriptions WHERE blog_id = {$blog_id} AND wiki_id = {$post->ID}";

		$subscription_emails = $wpdb->get_results( $query, ARRAY_A );



		if (count($subscription_emails) > 0){

			foreach ($subscription_emails as $subscription_email){

			$loop_notification_content = $wiki_notification_content['user'];



			$loop_notification_content = $wiki_notification_content['user'];



			if ($subscription_email['user_id'] > 0) {

				if ($subscription_email['user_id'] == $post->post_author) {

				$loop_notification_content = $wiki_notification_content['author'];

				}

				$user = get_userdata($subscription_email['user_id']);

				$subscription_to = $user->user_email;

			} else {

				$subscription_to = $subscription_email['email'];

			}



			$loop_notification_content = str_replace("CANCEL_URL",$cancel_url . $subscription_email['ID'],$loop_notification_content);

			$subject_content = $blog_name . ': ' . __('Änderungen an der Wiki-Seite', 'wiki');

			$from_email = $admin_email;

			$message_headers = "MIME-Version: 1.0\n" . "From: " . $blog_name .	 " <{$from_email}>\n" . "Content-Type: text/plain; charset=\"" . get_option('blog_charset') . "\"\n";

			wp_mail($subscription_to, $subject_content, $loop_notification_content, $message_headers);

			}

		}

	}

}



$wiki = Wiki::get_instance();



if ( file_exists($wiki->plugin_dir . 'premium/wiki-premium.php') ) {

	require_once $wiki->plugin_dir . 'premium/wiki-premium.php';

}


