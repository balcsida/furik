<?php
function furik_campaign_post_type() {

	$labels = array(
		'name'               => __( 'Campaigns', 'furik' ),
		'singular_name'      => __( 'Campaign', 'furik' ),
		'menu_name'          => __( 'Campaigns', 'furik' ),
		'parent_item_colon'  => __( 'Parent Campaign', 'furik' ),
		'all_items'          => __( 'All Campaigns', 'furik' ),
		'view_item'          => __( 'View Campaign', 'furik' ),
		'add_new_item'       => __( 'Add New Campaign', 'furik' ),
		'add_new'            => __( 'Add New', 'furik' ),
		'edit_item'          => __( 'Edit Campaign', 'furik' ),
		'update_item'        => __( 'Update Campaign', 'furik' ),
		'search_items'       => __( 'Search Campaign', 'furik' ),
		'not_found'          => __( 'Not Found', 'furik' ),
		'not_found_in_trash' => __( 'Not found in Trash', 'furik' ),
	);

	$args = array(
		'label'               => __( 'Campaigns', 'furik' ),
		'description'         => __( 'Donation campaigns', 'furik' ),
		'labels'              => $labels,
		'supports'            => array( 'title', 'editor', 'excerpt', 'author', 'thumbnail', 'revisions', 'custom-fields', 'page-attributes' ),
		'hierarchical'        => true,
		'public'              => true,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'show_in_nav_menus'   => true,
		'show_in_admin_bar'   => true,
		'menu_position'       => 5,
		'can_export'          => true,
		'has_archive'         => true,
		'exclude_from_search' => false,
		'publicly_queryable'  => true,
		'capability_type'     => 'page',
		'capabilities'        => array(
			'edit_post'              => 'edit_page',
			'read_post'              => 'read_page',
			'delete_post'            => 'delete_page',
			'edit_posts'             => 'edit_pages',
			'edit_others_posts'      => 'edit_others_pages',
			'publish_posts'          => 'publish_pages',
			'read_private_posts'     => 'read_private_pages',
			'read'                   => 'read',
			'delete_posts'           => 'delete_pages',
			'delete_private_posts'   => 'delete_private_pages',
			'delete_published_posts' => 'delete_published_campaigns',
			'delete_others_posts'    => 'delete_others_pages',
			'edit_private_posts'     => 'edit_private_pages',
			'edit_published_posts'   => 'edit_published_pages',
			'create_posts'           => 'edit_pages',
		),
		'map_meta_cap'        => 'true',
	);

	register_post_type( 'campaign', $args );
}

add_action( 'init', 'furik_campaign_post_type', 1 );
