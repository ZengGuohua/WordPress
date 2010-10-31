<?php
/**
 * MS Themes List Table class.
 *
 * @package WordPress
 * @subpackage List_Table
 * @since 3.1.0
 */
class WP_MS_Themes_Table extends WP_List_Table {

	function WP_MS_Themes_Table() {
		global $status, $page;

		$default_status = get_user_option( 'themes_last_view' );
		if ( empty( $default_status ) )
			$default_status = 'all';
		$status = isset( $_REQUEST['theme_status'] ) ? $_REQUEST['theme_status'] : $default_status;
		if ( !in_array( $status, array( 'all', 'enabled', 'disabled', 'upgrade', 'search' ) ) )
			$status = 'all';
		if ( $status != $default_status && 'search' != $status )
			update_user_meta( get_current_user_id(), 'themes_last_view', $status );

		$page = $this->get_pagenum();

		parent::WP_List_Table( array(
			'screen' => 'themes',
			'plural' => 'plugins', // @todo replace with themes and add css
		) );
	}
	
	function check_permissions() {
		if ( is_multisite() ) {
			$menu_perms = get_site_option( 'menu_items', array() );

			if ( empty( $menu_perms['themes'] ) ) {
				if ( !is_super_admin() )
					wp_die( __( 'Cheatin&#8217; uh?' ) );
			}
		}

		if ( !current_user_can('manage_network_themes') )
			wp_die( __( 'You do not have sufficient permissions to manage themes for this site.' ) );
	}

	function prepare_items() {
		global $status, $themes, $totals, $page, $orderby, $order, $s;

		wp_reset_vars( array( 'orderby', 'order', 's' ) );

		$themes = array(
			'all' => apply_filters( 'all_themes', get_themes() ),
			'search' => array(),
			'enabled' => array(),
			'disabled' => array(),
			'upgrade' => array()
		);
		
		$allowed_themes = get_site_allowed_themes();
		$current = get_site_transient( 'update_themes' );

		foreach ( (array) $themes['all'] as $key => $theme ) {
			if ( array_key_exists( $theme['Template'], $allowed_themes ) ) {
				$themes['all'][$key]['enabled'] = true;
				$themes['enabled'][$key] = $themes['all'][$key];
			}
			else {
				$themes['all'][$key]['enabled'] = false;
				$themes['disabled'][$key] = $themes['all'][$key];
			}
			if ( isset( $current->response[ $theme['Template'] ] ) )
				$themes['upgrade'][$key] = $themes['all'][$key];
		}

		if ( !current_user_can( 'update_themes' ) )
			$themes['upgrade'] = array();

		if ( $s ) {
			$status = 'search'; echo "opopop";
			$themes['search'] = array_filter( $themes['all'], array( $this, '_search_callback' ) );
		}

		$totals = array();
		foreach ( $themes as $type => $list )
			$totals[ $type ] = count( $list );

		if ( empty( $themes[ $status ] ) && !in_array( $status, array( 'all', 'search' ) ) )
			$status = 'all';

		$this->items = $themes[ $status ];
		$total_this_page = $totals[ $status ];

		if ( $orderby ) {
			$orderby = ucfirst( $orderby );
			$order = strtoupper( $order );

			uasort( $this->items, array( $this, '_order_callback' ) );
		}

		$themes_per_page = $this->get_items_per_page( 'themes_per_page', 999 );

		$start = ( $page - 1 ) * $themes_per_page;

		if ( $total_this_page > $themes_per_page )
			$this->items = array_slice( $this->items, $start, $themes_per_page );

		$this->set_pagination_args( array(
			'total_items' => $total_this_page,
			'per_page' => $themes_per_page,
		) );
	}
	
	function _search_callback( $theme ) {
		static $term;
		if ( is_null( $term ) )
			$term = stripslashes( $_REQUEST['s'] );
			
		foreach ( $theme as $key->$theme )
			if ( stripos( $key, $term ) !== false )
				return true;

		return false;
	}

	function _order_callback( $theme_a, $theme_b ) {
		global $orderby, $order;

		$a = $theme_a[$orderby];
		$b = $theme_b[$orderby];

		if ( $a == $b )
			return 0;

		if ( 'DESC' == $order )
			return ( $a < $b ) ? 1 : -1;
		else
			return ( $a < $b ) ? -1 : 1;
	}

	function no_items() {
		global $themes;

		if ( !empty( $themes['all'] ) )
			_e( 'No themes found.' );
		else
			_e( 'You do not appear to have any themes available at this time.' );
	}

	function get_columns() {
		global $status;

		return array(
			'cb'          => '<input type="checkbox" />',
			'name'        => __( 'Theme' ),
			'description' => __( 'Description' ),
		);
	}

	function get_sortable_columns() {
		return array(
			'name'         => 'name',
		);
	}

	function display_tablenav( $which ) {
		global $status;

		parent::display_tablenav( $which );
	}

	function get_views() {
		global $totals, $status;

		$status_links = array();
		foreach ( $totals as $type => $count ) {
			if ( !$count )
				continue;

			switch ( $type ) {
				case 'all':
					$text = _nx( 'All <span class="count">(%s)</span>', 'All <span class="count">(%s)</span>', $count, 'themes' );
					break;
				case 'enabled':
					$text = _n( 'Enabled <span class="count">(%s)</span>', 'Enabled <span class="count">(%s)</span>', $count );
					break;
				case 'disabled':
					$text = _n( 'Disabled <span class="count">(%s)</span>', 'Disabled <span class="count">(%s)</span>', $count );
					break;
				case 'upgrade':
					$text = _n( 'Upgrade Available <span class="count">(%s)</span>', 'Upgrade Available <span class="count">(%s)</span>', $count );
					break;
				case 'search':
					$text = _n( 'Search Results <span class="count">(%s)</span>', 'Search Results <span class="count">(%s)</span>', $count );
					break;
			}

			$status_links[$type] = sprintf( "<li><a href='%s' %s>%s</a>",
				add_query_arg('theme_status', $type, 'themes.php'),
				( $type == $status ) ? ' class="current"' : '',
				sprintf( $text, number_format_i18n( $count ) )
			);
		}

		return $status_links;
	}

	function get_bulk_actions() {
		global $status;

		$actions = array();
		if ( 'enabled' != $status )
			$actions['network-enable-selected'] = __( 'Network Enable' );
		if ( 'disabled' != $status )
			$actions['network-disable-selected'] = __( 'Network Disable' );
		if ( current_user_can( 'update_themes' ) )
			$actions['update-selected'] = __( 'Update' );
			
		return $actions;
	}

	function bulk_actions( $which ) {
		global $status;
		parent::bulk_actions( $which );
	}

	function current_action() {
		return parent::current_action();
	}

	function display_rows() {
		global $status, $page, $s;

		$context = $status;

		foreach ( $this->items as $key => $theme ) {
			// preorder
			$actions = array(
				'network_enable' => '',
				'network_disable' => '',
				'edit' => ''
			);
			
			$theme_key = esc_html( $theme['Stylesheet'] );

			if ( empty( $theme['enabled'] ) ) {
				if ( current_user_can( 'manage_network_themes' ) )
					$actions['network_enable'] = '<a href="' . wp_nonce_url('themes.php?action=network-enable&amp;theme=' . $theme_key . '&amp;paged=' . $page . '&amp;s=' . $s, 'enable-theme_' . $theme_key) . '" title="' . __('Enable this theme for all sites in this network') . '" class="edit">' . __('Network Enable') . '</a>';
			} else {
				if ( current_user_can( 'manage_network_themes' ) )
					$actions['network_disable'] = '<a href="' . wp_nonce_url('themes.php?action=network-disable&amp;theme=' . $theme_key . '&amp;paged=' . $page . '&amp;s=' . $s, 'disable-theme_' . $theme_key) . '" title="' . __('Disable this theme') . '">' . __('Network Disable') . '</a>';
			}
			
			/* @todo link to theme editor	
			if ( current_user_can('edit_themes') )
				$actions['edit'] = '<a href="theme-editor.php?file=' . $theme['Stylesheet Files'][0] . '" title="' . __('Open this theme in the Theme Editor') . '" class="edit">' . __('Edit') . '</a>';
			*/

			$actions = apply_filters( 'theme_action_links', array_filter( $actions ), $theme_key, $theme, $context );
			$actions = apply_filters( "theme_action_links_$theme_key", $actions, $theme_key, $theme, $context );

			$class = empty( $theme['enabled'] ) ? 'inactive' : 'active';
			$checkbox = "<input type='checkbox' name='checked[]' value='" . esc_attr( $theme_key ) . "' />";

			$description = '<p>' . $theme['Description'] . '</p>';
			$theme_name = $theme['Name'];


			$id = sanitize_title( $theme_name );

			echo "
		<tr id='$id' class='$class'>
			<th scope='row' class='check-column'>$checkbox</th>
			<td class='theme-title'><strong>$theme_name</strong></td>
			<td class='desc'>$description</td>
		</tr>
		<tr class='$class second'>
			<td></td>
			<td class='theme-title'>";

			echo $this->row_actions( $actions, true );

			echo "</td>
			<td class='desc'>";
			$theme_meta = array();
			if ( !empty( $theme['Version'] ) )
				$theme_meta[] = sprintf( __( 'Version %s' ), $theme['Version'] );
			if ( !empty( $theme['Author'] ) ) {
				$author = $theme['Author'];
				if ( !empty( $theme['Author URI'] ) )
					$author = '<a href="' . $theme['Author URI'] . '" title="' . __( 'Visit author homepage' ) . '">' . $theme['Author'] . '</a>';
				$theme_meta[] = sprintf( __( 'By %s' ), $author );
			}
			if ( !empty( $theme['Theme URI'] ) )
				$theme_meta[] = '<a href="' . $theme['Theme URI'] . '" title="' . __( 'Visit theme homepage' ) . '">' . __( 'Visit Theme Site' ) . '</a>';

			$theme_meta = apply_filters( 'theme_row_meta', $theme_meta, $theme_key, $theme, $status );
			echo implode( ' | ', $theme_meta );
			echo "</td>
		</tr>\n";

			do_action( 'after_theme_row', $theme_key, $theme, $status );
			do_action( "after_theme_row_$theme_key", $theme_key, $theme, $status );
		}
	}
}
?>