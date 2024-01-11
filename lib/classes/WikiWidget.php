<?php

class WikiWidget extends WP_Widget {
		function __construct() {
			global $wiki;

			$widget_ops = array( 'description' => __('Wiki-Seiten anzeigen', 'ps-wiki') );
			$control_ops = array( 'title' => __('Wiki', 'ps-wiki'), 'hierarchical' => 'yes', 'order_by' => $wiki->get_setting('sub_wiki_order_by'), 'order' => $wiki->get_setting('sub_wiki_order'));
			//parent::WP_Widget( 'psource_wiki', __('Wiki', 'wiki'), $widget_ops, $control_ops );
			parent::__construct( 'psource_wiki', __('Wiki', 'ps-wiki'), $widget_ops, $control_ops );
		}

		function widget($args, $instance) {
		global $wpdb, $current_site, $post, $wiki_tree;

		extract($args);

		$options = $instance;
		$title = apply_filters('widget_title', empty($instance['title']) ? __('Wiki', 'ps-wiki') : $instance['title'], $instance, $this->id_base);
		$hierarchical = $instance['hierarchical'];
		$order_by = $instance['order_by'];
		$order = $instance['order'];

		if ($hierarchical == 'yes') {
			$hierarchical = 0;
		} else if ($hierarchical == 'no') {
			$hierarchical = 1;
		}

		echo $before_widget;
		echo $before_title . $title . $after_title;

		$wiki_posts = get_posts(
			array(
				'post_parent' => 0,
				'post_type' => 'psource_wiki',
				'orderby' => $order_by,
				'order' => $order,
				'numberposts' => 100000
			)
		);
		?>

		<ul>
			<?php
			foreach ($wiki_posts as $wiki) {
			?>
				<li>
					<a href="<?php print get_permalink($wiki->ID); ?>" class="<?php print ($wiki->ID == $post->ID)?'current':''; ?>" ><?php print $wiki->post_title; ?></a>
					<?php ($hierarchical == 0 || $hierarchical > 1)?$this->_print_sub_wikis($wiki, $order_by, $order, $hierarchical, 2):''; ?>
				</li>
			<?php
			}
			?>
		</ul>
		<br />

		<?php
		echo $after_widget;
		}

		function _print_sub_wikis($wiki, $order_by, $order, $level, $current_level) {
		global $post;

		$sub_wikis = get_posts(
				array('post_parent' => $wiki->ID,
				'post_type' => 'psource_wiki',
				'orderby' => $order_by,
				'order' => $order,
				'numberposts' => 100000
			));
		?>

		<ul>
			<?php
			foreach ($sub_wikis as $sub_wiki) {
			?>
				<li>
					<a href="<?php print get_permalink($sub_wiki->ID); ?>" class="<?php print ($sub_wiki->ID == $post->ID)?'current':''; ?>" ><?php print $sub_wiki->post_title; ?></a>
					<?php ($level == 0 || $level > $current_level)?$this->_print_sub_wikis($sub_wiki, $order_by, $order, $level, $current_level+1):''; ?>
				</li>
			<?php
			}
			?>
		</ul>
		<?php
		}

		function update($new_instance, $old_instance) {
		global $wiki;

		$instance = $old_instance;
		$new_instance = wp_parse_args( (array) $new_instance, array( 'title' => __('Wiki', 'ps-wiki'), 'hierarchical' => 'yes', 'order_by' => $wiki->get_setting('sub_wiki_order_by'), 'order' => $wiki->get_setting('sub_wiki_order')) );
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['hierarchical'] = $new_instance['hierarchical'];
		$instance['order_by'] = $new_instance['order_by'];
		$instance['order'] = $new_instance['order'];

		return $instance;
	}

	function form($instance) {
		global $wiki;
	
		// Standardwerte festlegen
		$defaults = array(
			'title' => __('Wiki', 'ps-wiki'),
			'hierarchical' => 'yes',
			'order_by' => 'menu_order',
			'order' => 'ASC'
		);
	
		// Standardwerte mit den vorhandenen Instanzwerten zusammenführen
		$instance = wp_parse_args((array) $instance, $defaults);
	
		// Einstellungen abrufen
		$wiki_settings = get_option('ps_wiki_settings'); // Oder den richtigen Optionsnamen verwenden
	
		// Standardwerte für 'order_by' und 'order' anpassen, wenn Einstellungen vorhanden sind
		$instance['order_by'] = isset($wiki_settings['sub_wiki_order_by']) ? $wiki_settings['sub_wiki_order_by'] : $instance['order_by'];
		$instance['order'] = isset($wiki_settings['sub_wiki_order']) ? $wiki_settings['sub_wiki_order'] : $instance['order'];
	
		// Konvertieren Sie 'hierarchical' in einen numerischen Wert
		$instance['hierarchical'] = ($instance['hierarchical'] == 'yes') ? 0 : (($instance['hierarchical'] == 'no') ? 1 : $instance['hierarchical']);
		?>
	
		<div style="text-align:left">
			<label for="<?php echo $this->get_field_id('title'); ?>" style="line-height:35px;display:block;">
				<?php _e('Titel', 'ps-wiki'); ?>:<br />
				<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" value="<?php echo esc_attr($instance['title']); ?>" type="text" style="width:95%;" />
			</label>
	
			<label for="<?php echo $this->get_field_id('hierarchical'); ?>" style="line-height:35px;display:block;">
				<?php _e('Ebenen', 'ps-wiki'); ?>:<br />
				<select id="<?php echo $this->get_field_id('hierarchical'); ?>" name="<?php echo $this->get_field_name('hierarchical'); ?>">
					<?php for ($i = 1; $i < 5; $i++) { ?>
						<option value="<?php echo $i; ?>" <?php selected($instance['hierarchical'], $i); ?>><?php _e($i, 'ps-wiki'); ?></option>
					<?php } ?>
					<option value="0" <?php selected($instance['hierarchical'], 0); ?>><?php _e('Unlimitiert', 'ps-wiki'); ?></option>
				</select>
			</label>
	
			<label for="<?php echo $this->get_field_id('order_by'); ?>" style="line-height:35px;display:block;">
				<?php _e('Ordne nach', 'ps-wiki'); ?>:<br />
				<select id="<?php echo $this->get_field_id('order_by'); ?>" name="<?php echo $this->get_field_name('order_by'); ?>">
					<option value="menu_order" <?php selected($instance['order_by'], 'menu_order'); ?>><?php _e('Menüreihenfolge / Reihenfolge erstellt', 'ps-wiki'); ?></option>
					<option value="title" <?php selected($instance['order_by'], 'title'); ?>><?php _e('Titel', 'ps-wiki'); ?></option>
					<option value="rand" <?php selected($instance['order_by'], 'rand'); ?>><?php _e('Zufällig', 'ps-wiki'); ?></option>
				</select>
			</label>
	
			<label for="<?php echo $this->get_field_id('order'); ?>" style="line-height:35px;display:block;">
				<?php _e('Ordnen', 'ps-wiki'); ?>:<br />
				<select id="<?php echo $this->get_field_id('order'); ?>" name="<?php echo $this->get_field_name('order'); ?>">
					<option value="ASC" <?php selected($instance['order'], 'ASC'); ?>><?php _e('Aufsteigend', 'ps-wiki'); ?></option>
					<option value="DESC" <?php selected($instance['order'], 'DESC'); ?>><?php _e('Absteigend', 'ps-wiki'); ?></option>
				</select>
			</label>
	
			<input type="hidden" name="wiki-submit" id="wiki-submit" value="1" />
		</div>
		<?php
	}
}

