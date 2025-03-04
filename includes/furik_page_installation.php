<?php
/**
 * Furik Page Installation
 *
 * This file handles the creation of default pages when the plugin is activated.
 */

/**
 * Create default pages on plugin activation
 */
function furik_create_default_pages() {
	// Check if pages have already been created
	$pages_created = get_option( 'furik_pages_created', false );

	if ( $pages_created ) {
		return;
	}

	// Array of pages to create with their details
	$pages = array(
		'adomanyozas'        => array(
			'title'   => 'Adományozás',
			'content' => '[furik_donate_form amount=5000 enable_monthly=true]',
			'slug'    => 'tamogatas',
		),
		'adattovabbitas'     => array(
			'title'   => 'Adattovábbítási nyilatkozat',
			'content' => 'Tudomásul veszem, hogy a CHANGEME Alapítvány (CHANGEME address) adatkezelő által a CHANGEME.hu felhasználói adatbázisában tárolt alábbi személyes adataim átadásra kerülnek az OTP Mobil Kft., mint adatfeldolgozó részére. Az adatkezelő által továbbított adatok köre az alábbi: név, e-mail cím, telefonszám, számlázási adatok.

Az adatfeldolgozó által végzett adatfeldolgozási tevékenység jellege és célja a SimplePay Adatkezelési tájékoztatóban, az alábbi linken tekinthető meg: http://simplepay.hu/vasarlo-aff',
			'slug'    => 'data-transmission-declaration',
		),
		'atutalas'           => array(
			'title'   => 'Átutalásos támogatás',
			'content' => '<!-- wp:paragraph -->
<p>Köszönjük, hogy jelezted, hogy támogatod az Alapítványunkat! Az adományodat kérjük a CHANGEME Alapítvány nevére és a CHANGEME bankszámlaszámára utald. A közlemény mezőbe a következő kódot írd: [furik_order_ref]. Köszönjük!</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Fontos: a felületen akkor jelenik meg az adományod, ha elutaltad, és mi jóváhagytuk azt!</p>
<!-- /wp:paragraph -->',
			'slug'    => 'bank-transfer-donation',
		),
		'kartyaregisztracio' => array(
			'title'   => 'Kártya regisztrációs nyilatkozat',
			'content' => 'Az ismétlődő bankkártyás fizetés (továbbiakban: „Ismétlődő fizetés") egy, a SimplePay által biztosított bankkártya elfogadáshoz tartozó funkció, mely azt jelenti, hogy a Vásárló által a regisztrációs tranzakció során megadott bankkártyaadatokkal a jövőben újabb fizetéseket lehet kezdeményezni a bankkártyaadatok újbóli megadása nélkül. Az ismétlődő fizetés ún. „eseti hozzájárulásos" típusa minden tranzakció esetében a Vevő eseti jóváhagyásával történik, tehát, Ön valamennyi jövőbeni fizetésnél jóvá kell, hogy hagyja a tranzakciót. A sikeres fizetés tényéről Ön minden esetben a hagyományos bankkártyás fizetéssel megegyező csatornákon keresztül értesítést kap.
Az Ismétlődő fizetés igénybevételéhez jelen nyilatkozat elfogadásával Ön hozzájárul, hogy a sikeres regisztrációs tranzakciót követően jelen webshopban (CHANGEME.hu) Ön az itt található felhasználói fiókjából kezdeményezett későbbi fizetések a bankkártyaadatok újbóli megadása nélkül menjenek végbe.
Figyelem(!): a bankkártyaadatok kezelése a kártyatársasági szabályoknak megfelelően történik. A bankkártyaadatokhoz sem a Kereskedő, sem a SimplePay nem fér hozzá. A Kereskedő által tévesen vagy jogtalanul kezdeményezett ismétlődő fizetéses tranzakciókért közvetlenül a Kereskedő felel, Kereskedő fizetési szolgáltatójával (SimplePay) szemben bármilyen igényérvényesítés kizárt.
Jelen tájékoztatót átolvastam, annak tartalmát tudomásul veszem és elfogadom.',
			'slug'    => 'card-registration-statement',
		),
		'koszonjuk'          => array(
			'title'   => 'Köszönjük támogatásod!',
			'content' => '<!-- wp:paragraph -->
<p>Sikeres tranzakció.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>[furik_payment_info]</p>
<!-- /wp:paragraph -->',
			'slug'    => 'payment-successful',
		),
		'megszakitott'       => array(
			'title'   => 'Megszakított tranzakció',
			'content' => '<!-- wp:paragraph -->
<p>Ön megszakította a fizetést, vagy lejárt a tranzakció maximális ideje!</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>[furik_payment_info]</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p></p>
<!-- /wp:paragraph -->

<!-- wp:html -->
<a href="[furik_back_to_campaign_url]">Vissza az oldalra</a>
<!-- /wp:html -->',
			'slug'    => 'payment-unsuccessful',
		),
		'rendszeres'         => array(
			'title'   => 'Rendszeres támogatás',
			'content' => 'Amikor a rendszeres támogatás lehetőséget választod, akkor az először megadott összeggel havi rendszerességgel támogatod az Alapítványt. A kártyádról az első alkalommal az adatok megadása után vonjuk le az összeget, a későbbiekben pedig ez automatikusan megy. Minden hónapnak azon a napján, amelyiken regisztráltad a lehetőséget.

A regisztráció alkalmával küldünk egy jelszót, amivel bejelentkezve a havi támogatást le tudod mondani bármikor. A rendszer 2 évig tudja levonni maximum az összegeket.',
			'slug'    => 'monthly-donation',
		),
		'sikertelen'         => array(
			'title'   => 'Sikertelen kártyás tranzakció',
			'content' => '<!-- wp:paragraph -->
<p>Kérjük, ellenőrizze a tranzakció során megadott adatok helyességét. Amennyiben minden adatot helyesen adott meg, a visszautasítás okának kivizsgálása kapcsán kérjük, szíveskedjen kapcsolatba lépni kártyakibocsátó bankjával.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>[furik_payment_info]</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p></p>
<!-- /wp:paragraph -->',
			'slug'    => 'card-payment-failed',
		),
		'keszpenz'           => array(
			'title'   => 'Készpénzes támogatás',
			'content' => '<!-- wp:paragraph -->
<p>Köszönjük, hogy támogatod az Alapítványunkat! Az adományodat a CHANGEME Alapítvány irodájában személyesen is átadhatod.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Tranzakció azonosító: [furik_order_ref]</p>
<!-- /wp:paragraph -->',
			'slug'    => 'cash-donation',
		),
	);

	// Create each page
	foreach ( $pages as $key => $page ) {
		// Check if the page already exists by slug
		$existing_page = get_page_by_path( $page['slug'] );

		if ( ! $existing_page ) {
			// Insert the page
			$page_id = wp_insert_post(
				array(
					'post_title'   => $page['title'],
					'post_content' => $page['content'],
					'post_status'  => 'publish',
					'post_type'    => 'page',
					'post_name'    => $page['slug'],
				)
			);

			if ( $page_id ) {
				// Store page ID in options for later reference
				update_option( 'furik_page_' . $key, $page_id );
			}
		}
	}

	// Mark that pages have been created
	update_option( 'furik_pages_created', true );
}

/**
 * Update config values to point to the created pages
 */
function furik_update_page_config() {
	global $furik_payment_successful_url, $furik_payment_unsuccessful_url,
			$furik_payment_timeout_url, $furik_donations_url,
			$furik_payment_transfer_url, $furik_payment_cash_url,
			$furik_card_registration_statement_url, $furik_data_transmission_declaration_url,
			$furik_monthly_explanation_url;

	// Get current config values to avoid overwriting if already set
	$config_file   = plugin_dir_path( __FILE__ ) . 'config_local.php';
	$config_exists = file_exists( $config_file );

	// Create or append to config_local.php
	$config_content = $config_exists ? file_get_contents( $config_file ) : "<?php\n";

	// Only update if not already set
	if ( ! stristr( $config_content, '$furik_payment_successful_url' ) ) {
		$config_content .= "\n// Pages created by installer\n";
		$config_content .= '$furik_payment_successful_url = "payment-successful";' . "\n";
		$config_content .= '$furik_payment_unsuccessful_url = "payment-unsuccessful";' . "\n";
		$config_content .= '$furik_payment_timeout_url = "payment-unsuccessful";' . "\n";
		$config_content .= '$furik_donations_url = "tamogatas";' . "\n";
		$config_content .= '$furik_payment_transfer_url = "bank-transfer-donation";' . "\n";
		$config_content .= '$furik_payment_cash_url = "cash-donation";' . "\n";
		$config_content .= '$furik_card_registration_statement_url = "card-registration-statement";' . "\n";
		$config_content .= '$furik_data_transmission_declaration_url = "data-transmission-declaration";' . "\n";
		$config_content .= '$furik_monthly_explanation_url = "monthly-donation";' . "\n";
	}

	file_put_contents( $config_file, $config_content );
}

/**
 * Extended install function that creates pages
 */
function furik_extended_install() {
	// Run the original database installation
	furik_install();

	// Create default pages
	furik_create_default_pages();

	// Update configuration to point to the created pages
	furik_update_page_config();
}

// Replace the existing activation hook
remove_action( 'register_activation_hook', 'furik_install' );
register_activation_hook( __FILE__, 'furik_extended_install' );
