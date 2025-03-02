<?php
/**
 * Furik Pages Admin Menu
 *
 * Adds a submenu to manage default pages
 */

/**
 * Add Pages submenu to Furik menu
 */
function furik_pages_admin_menu() {
	add_submenu_page(
		'furik-dashboard',
		__( 'Manage Pages', 'furik' ),
		__( 'Manage Pages', 'furik' ),
		'manage_options',
		'furik-manage-pages',
		'furik_pages_admin_page'
	);
}
add_action( 'admin_menu', 'furik_pages_admin_menu', 40 );

/**
 * Admin page callback
 */
function furik_pages_admin_page() {
	// Handle form submission
	if ( isset( $_POST['furik_recreate_pages'] ) && check_admin_referer( 'furik_recreate_pages_nonce' ) ) {
		// Reset the option so pages will be recreated
		delete_option( 'furik_pages_created' );
		// Create the pages
		furik_create_default_pages();
		// Update config
		furik_update_page_config();
		// Show success message
		echo '<div class="notice notice-success is-dismissible"><p>' . __( 'Default pages have been created or updated.', 'furik' ) . '</p></div>';
	}

	if ( isset( $_POST['furik_update_placeholders'] ) && check_admin_referer( 'furik_update_placeholders_nonce' ) ) {
		$foundation_name         = sanitize_text_field( $_POST['foundation_name'] );
		$foundation_address      = sanitize_text_field( $_POST['foundation_address'] );
		$foundation_website      = sanitize_text_field( $_POST['foundation_website'] );
		$foundation_bank_account = sanitize_text_field( $_POST['foundation_bank_account'] );

		// Update placeholders in pages
		$updated = furik_update_page_placeholders( $foundation_name, $foundation_address, $foundation_website, $foundation_bank_account );

		if ( $updated ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . __( 'Placeholders have been updated in all pages.', 'furik' ) . '</p></div>';
		} else {
			echo '<div class="notice notice-error is-dismissible"><p>' . __( 'Error updating placeholders. Please try again.', 'furik' ) . '</p></div>';
		}
	}

	// Get page status
	$pages_created = get_option( 'furik_pages_created', false );
	$page_list     = get_furik_pages_status();

	?>
	<div class="wrap">
		<h1><?php _e( 'Furik Pages Management', 'furik' ); ?></h1>
		
		<div class="card">
			<h2><?php _e( 'Default Pages Status', 'furik' ); ?></h2>
			<p>
				<?php
				if ( $pages_created ) {
					_e( 'Default pages have been created. You can check their status below or recreate them if needed.', 'furik' );
				} else {
					_e( 'Default pages have not been created yet. Click the button below to create them.', 'furik' );
				}
				?>
			</p>
			
			<form method="post" action="">
				<?php wp_nonce_field( 'furik_recreate_pages_nonce' ); ?>
				<p class="submit">
					<input type="submit" name="furik_recreate_pages" id="furik_recreate_pages" class="button button-primary" value="<?php _e( 'Create/Recreate Default Pages', 'furik' ); ?>">
				</p>
			</form>
		</div>
		
		<div class="card">
			<h2><?php _e( 'Update Placeholders', 'furik' ); ?></h2>
			<p><?php _e( 'Replace CHANGEME placeholders in all pages with your organization details:', 'furik' ); ?></p>
			
			<form method="post" action="">
				<?php wp_nonce_field( 'furik_update_placeholders_nonce' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="foundation_name"><?php _e( 'Foundation Name', 'furik' ); ?></label></th>
						<td><input type="text" id="foundation_name" name="foundation_name" class="regular-text" placeholder="Example Foundation"></td>
					</tr>
					<tr>
						<th scope="row"><label for="foundation_address"><?php _e( 'Foundation Address', 'furik' ); ?></label></th>
						<td><input type="text" id="foundation_address" name="foundation_address" class="regular-text" placeholder="1234 Example Street, City"></td>
					</tr>
					<tr>
						<th scope="row"><label for="foundation_website"><?php _e( 'Foundation Website', 'furik' ); ?></label></th>
						<td><input type="text" id="foundation_website" name="foundation_website" class="regular-text" placeholder="example.org"></td>
					</tr>
					<tr>
						<th scope="row"><label for="foundation_bank_account"><?php _e( 'Bank Account Number', 'furik' ); ?></label></th>
						<td><input type="text" id="foundation_bank_account" name="foundation_bank_account" class="regular-text" placeholder="12345678-12345678-12345678"></td>
					</tr>
				</table>
				
				<p class="submit">
					<input type="submit" name="furik_update_placeholders" id="furik_update_placeholders" class="button button-primary" value="<?php _e( 'Update Placeholders', 'furik' ); ?>">
				</p>
			</form>
		</div>
		
		<h2><?php _e( 'Pages Overview', 'furik' ); ?></h2>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php _e( 'Page Name', 'furik' ); ?></th>
					<th><?php _e( 'Slug', 'furik' ); ?></th>
					<th><?php _e( 'Status', 'furik' ); ?></th>
					<th><?php _e( 'Actions', 'furik' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $page_list as $key => $page ) : ?>
				<tr>
					<td><?php echo esc_html( $page['title'] ); ?></td>
					<td><?php echo esc_html( $page['slug'] ); ?></td>
					<td>
						<?php if ( $page['exists'] ) : ?>
							<span class="dashicons dashicons-yes" style="color: green;"></span> <?php _e( 'Created', 'furik' ); ?>
						<?php else : ?>
							<span class="dashicons dashicons-no" style="color: red;"></span> <?php _e( 'Not Created', 'furik' ); ?>
						<?php endif; ?>
					</td>
					<td>
						<?php if ( $page['exists'] ) : ?>
							<a href="<?php echo get_edit_post_link( $page['id'] ); ?>" class="button button-small"><?php _e( 'Edit', 'furik' ); ?></a>
							<a href="<?php echo get_permalink( $page['id'] ); ?>" class="button button-small" target="_blank"><?php _e( 'View', 'furik' ); ?></a>
						<?php else : ?>
							<?php _e( 'Page will be created when you click "Create Default Pages"', 'furik' ); ?>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

/**
 * Get the status of all Furik pages
 *
 * @return array List of pages with their status
 */
function get_furik_pages_status() {
	$pages = array(
		'adomanyozas'        => array(
			'title' => 'Adományozás',
			'slug'  => 'tamogatas',
		),
		'adattovabbitas'     => array(
			'title' => 'Adattovábbítási nyilatkozat',
			'slug'  => 'data-transmission-declaration',
		),
		'atutalas'           => array(
			'title' => 'Átutalásos támogatás',
			'slug'  => 'bank-transfer-donation',
		),
		'kartyaregisztracio' => array(
			'title' => 'Kártya regisztrációs nyilatkozat',
			'slug'  => 'card-registration-statement',
		),
		'koszonjuk'          => array(
			'title' => 'Köszönjük támogatásod!',
			'slug'  => 'payment-successful',
		),
		'megszakitott'       => array(
			'title' => 'Megszakított tranzakció',
			'slug'  => 'payment-unsuccessful',
		),
		'rendszeres'         => array(
			'title' => 'Rendszeres támogatás',
			'slug'  => 'monthly-donation',
		),
		'sikertelen'         => array(
			'title' => 'Sikertelen kártyás tranzakció',
			'slug'  => 'card-payment-failed',
		),
		'keszpenz'           => array(
			'title' => 'Készpénzes támogatás',
			'slug'  => 'cash-donation',
		),
	);

	// Check each page's status
	foreach ( $pages as $key => &$page ) {
		$existing_page = get_page_by_path( $page['slug'] );
		if ( $existing_page ) {
			$page['exists'] = true;
			$page['id']     = $existing_page->ID;
		} else {
			$page['exists'] = false;
			$page['id']     = 0;
		}
	}

	return $pages;
}

/**
 * Update placeholders in all Furik pages
 *
 * @param string $foundation_name The foundation name
 * @param string $foundation_address The foundation address
 * @param string $foundation_website The foundation website
 * @param string $foundation_bank_account The bank account number
 * @return bool Success status
 */
function furik_update_page_placeholders( $foundation_name, $foundation_address, $foundation_website, $foundation_bank_account ) {
	$page_list = get_furik_pages_status();
	$success   = true;

	foreach ( $page_list as $page ) {
		if ( $page['exists'] ) {
			$post = get_post( $page['id'] );
			if ( $post ) {
				$content = $post->post_content;

				// Replace placeholders
				$new_content = str_replace( 'CHANGEME Alapítvány', $foundation_name, $content );
				$new_content = str_replace( 'CHANGEME address', $foundation_address, $new_content );
				$new_content = str_replace( 'CHANGEME.hu', $foundation_website, $new_content );
				$new_content = str_replace( 'CHANGEME bankszámlaszámára', $foundation_bank_account, $new_content );

				// Only update if content changed
				if ( $new_content !== $content ) {
					$updated_post = array(
						'ID'           => $page['id'],
						'post_content' => $new_content,
					);

					$result = wp_update_post( $updated_post );
					if ( ! $result ) {
						$success = false;
					}
				}
			}
		}
	}

	return $success;
}
