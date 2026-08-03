<?php
/**
 * Sample Data seeder for Farm Stand Manager.
 *
 * Provides a "Load Sample Data" / "Remove Sample Data" toggle
 * on the admin dashboard. Creates realistic products, locations,
 * events, availability entries, and RSVP records for testing.
 *
 * All sample posts are tagged with a '_lfuf_sample_data' meta key
 * so they can be cleanly identified and removed.
 */

declare(strict_types=1);

namespace Leftfield\SampleData;

defined( 'ABSPATH' ) || exit;

const SAMPLE_META_KEY = '_lfuf_sample_data';

/**
 * Check if sample data is currently loaded.
 */
function is_loaded(): bool {
	return (bool) get_option( 'lfuf_sample_data_loaded', false );
}

/**
 * Handle the load/remove actions from the dashboard.
 */
add_action(
	'admin_init',
	function (): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( isset( $_GET['lfuf_sample_action'] ) && wp_verify_nonce( $nonce, 'lfuf_sample_action' ) ) {
			$action = sanitize_key( wp_unslash( $_GET['lfuf_sample_action'] ) );

			if ( $action === 'load' && ! is_loaded() ) {
				seed_all();
				update_option( 'lfuf_sample_data_loaded', true );
				wp_safe_redirect( admin_url( 'admin.php?page=farm-stand-dashboard&lfuf_sample=loaded' ) );
				exit;
			}

			if ( $action === 'remove' && is_loaded() ) {
				remove_all();
				delete_option( 'lfuf_sample_data_loaded' );
				wp_safe_redirect( admin_url( 'admin.php?page=farm-stand-dashboard&lfuf_sample=removed' ) );
				exit;
			}
		}
	}
);

/**
 * Get the dashboard button HTML (called from admin-dashboard.php).
 */
function get_dashboard_html(): string {
	$loaded      = is_loaded();
	$action      = $loaded ? 'remove' : 'load';
	$label       = $loaded
		? __( 'Remove Sample Data', 'farm-stand-manager' )
		: __( 'Load Sample Data', 'farm-stand-manager' );
	$description = $loaded
		? __( 'Remove all sample products, locations, events, and availability entries.', 'farm-stand-manager' )
		: __( 'Load example products, a stand location, events, and availability entries so you can see how the blocks look with content.', 'farm-stand-manager' );

	$url = wp_nonce_url(
		admin_url( 'admin.php?page=farm-stand-dashboard&lfuf_sample_action=' . $action ),
		'lfuf_sample_action',
	);

	// Display-only flag, set by our own redirect after the nonce-checked
	// action above already ran. Nothing here changes state.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$result = isset( $_GET['lfuf_sample'] ) ? sanitize_key( wp_unslash( $_GET['lfuf_sample'] ) ) : '';

	$notice = '';
	if ( $result ) {
		$type   = $result === 'loaded' ? 'success' : 'info';
		$msg    = $result === 'loaded'
			? __( 'Sample data loaded! Check your Products, Locations, and Events.', 'farm-stand-manager' )
			: __( 'Sample data removed.', 'farm-stand-manager' );
		$notice = sprintf(
			'<div class="notice notice-%s inline" style="margin-bottom:0.75rem;"><p>%s</p></div>',
			esc_attr( $type ),
			esc_html( $msg ),
		);
	}

	$btn_class = $loaded ? 'button' : 'button button-primary';
	$confirm   = $loaded
		? 'return confirm("Remove all sample data? This cannot be undone.");'
		: '';

	return sprintf(
		'<div class="lfuf-dashboard__section">
            <h2>%s</h2>
            %s
            <p class="description">%s</p>
            <a href="%s" class="%s" onclick=\'%s\'>%s</a>
        </div>',
		esc_html__( 'Sample Data', 'farm-stand-manager' ),
		$notice,
		esc_html( $description ),
		esc_url( $url ),
		esc_attr( $btn_class ),
		esc_attr( $confirm ),
		esc_html( $label ),
	);
}

/* ───────────────────────────────────────────────
 * Seeder
 * ─────────────────────────────────────────────── */

function seed_all(): void {
	$location_id = seed_locations();
	$product_ids = seed_products();
	seed_availability( $product_ids, $location_id );
	seed_events( $location_id, $product_ids );
}

function seed_locations(): int {
	$id = wp_insert_post(
		[
			'post_type'    => 'lfuf_location',
			'post_title'   => 'Farm Stand (Sample)',
			'post_content' => 'Our honor-system roadside stand at 123 Farm Road. Cash and Venmo accepted.',
			'post_status'  => 'publish',
		]
	);

	if ( is_wp_error( $id ) ) {
		return 0;
	}

	update_post_meta( $id, SAMPLE_META_KEY, '1' );
	update_post_meta( $id, '_lfuf_address', '123 Farm Road, Yourtown, ST 00000' );
	update_post_meta( $id, '_lfuf_location_type', 'stand' );
	update_post_meta( $id, '_lfuf_venmo_handle', 'examplefarm' );
	update_post_meta(
		$id,
		'_lfuf_payment_methods',
		(string) wp_json_encode(
			[
				[
					'type'  => 'venmo',
					'value' => 'examplefarm',
					'label' => '',
				],
				[
					'type'  => 'cash',
					'value' => '',
					'label' => '',
				],
				[
					'type'  => 'snap_ebt',
					'value' => '',
					'label' => '',
				],
			]
		)
	);
	update_post_meta( $id, '_lfuf_hours', 'Saturdays 1:00 – 4:00 PM, May – December' );
	update_post_meta( $id, '_lfuf_is_open', false );
	update_post_meta( $id, '_lfuf_lat', 36.3134 );
	update_post_meta( $id, '_lfuf_lng', -82.3535 );
	update_post_meta( $id, '_lfuf_ss_season_start', '2026-05-04' );
	update_post_meta( $id, '_lfuf_ss_season_end', '2026-12-01' );
	update_post_meta(
		$id,
		'_lfuf_ss_schedule',
		wp_json_encode(
			[
				[
					'day'   => 6,
					'open'  => '13:00',
					'close' => '16:00',
				],
			]
		)
	);
	update_post_meta( $id, '_lfuf_ss_auto_toggle', true );

	// Also create a market location.
	$market_id = wp_insert_post(
		[
			'post_type'    => 'lfuf_location',
			'post_title'   => 'Jonesborough Farmers Market (Sample)',
			'post_content' => 'Courthouse Square, downtown Jonesborough. Saturdays 8 AM – 12 PM, May – October.',
			'post_status'  => 'publish',
		]
	);

	if ( ! is_wp_error( $market_id ) ) {
		update_post_meta( $market_id, SAMPLE_META_KEY, '1' );
		update_post_meta( $market_id, '_lfuf_address', 'Courthouse Square, Jonesborough, TN 37659' );
		update_post_meta( $market_id, '_lfuf_location_type', 'market' );
		update_post_meta( $market_id, '_lfuf_hours', 'Saturdays 8:00 AM – 12:00 PM, May – October' );
	}

	return $id;
}

function seed_products(): array {
	$products = [
		[
			'title'   => 'Arugula (Sample)',
			'excerpt' => 'Peppery, tender salad greens grown no-till.',
			'type'    => 'Produce',
			'seasons' => [ 'Spring', 'Fall' ],
			'price'   => '$4',
			'unit'    => 'bunch',
			'notes'   => 'Heirloom variety, cold-hardy.',
		],
		[
			'title'   => 'Salad Mix (Sample)',
			'excerpt' => 'A colorful blend of lettuces, arugula, and herbs.',
			'type'    => 'Produce',
			'seasons' => [ 'Spring', 'Summer', 'Fall' ],
			'price'   => '$6',
			'unit'    => 'bag',
			'notes'   => 'Harvested fresh on market mornings.',
		],
		[
			'title'   => 'Cherry Tomatoes (Sample)',
			'excerpt' => 'Sweet, sun-ripened heirloom cherry tomatoes.',
			'type'    => 'Produce',
			'seasons' => [ 'Summer' ],
			'price'   => '$5',
			'unit'    => 'pint',
			'notes'   => 'Multiple heirloom varieties.',
		],
		[
			'title'   => 'Sugar Snap Peas (Sample)',
			'excerpt' => 'Crisp, sweet peas eaten whole — pod and all.',
			'type'    => 'Produce',
			'seasons' => [ 'Spring' ],
			'price'   => '$5',
			'unit'    => 'half pound',
			'notes'   => 'Hand-picked at peak sweetness.',
		],
		[
			'title'   => 'Country Sourdough (Sample)',
			'excerpt' => 'Naturally leavened with freshly milled local wheat.',
			'type'    => 'Bread',
			'seasons' => [ 'Spring', 'Summer', 'Fall', 'Winter' ],
			'price'   => '$12',
			'unit'    => 'loaf',
			'notes'   => 'Made with grains from East TN farms.',
		],
		[
			'title'   => 'Cornmeal Cookies (Sample)',
			'excerpt' => 'Crunchy, buttery cookies made with freshly ground corn.',
			'type'    => 'Baked Good',
			'seasons' => [ 'Spring', 'Summer', 'Fall', 'Winter' ],
			'price'   => '$8',
			'unit'    => 'half dozen',
			'notes'   => 'A bakery signature.',
		],
		[
			'title'   => 'Garlic Dill Pickles (Sample)',
			'excerpt' => 'Lacto-fermented cucumbers with garlic and fresh dill.',
			'type'    => 'Pantry Good',
			'seasons' => [ 'Summer', 'Fall' ],
			'price'   => '$7',
			'unit'    => 'pint jar',
			'notes'   => 'Small-batch, naturally fermented.',
		],
		[
			'title'   => 'Tomato Seedlings (Sample)',
			'excerpt' => 'Heirloom tomato starts ready for your garden.',
			'type'    => 'Seedling',
			'seasons' => [ 'Spring' ],
			'price'   => '$4',
			'unit'    => 'plant',
			'notes'   => 'Cherokee Purple, Brandywine, and more.',
		],
	];

	$ids = [];

	foreach ( $products as $p ) {
		$id = wp_insert_post(
			[
				'post_type'    => 'lfuf_product',
				'post_title'   => $p['title'],
				'post_excerpt' => $p['excerpt'],
				'post_status'  => 'publish',
			]
		);

		if ( is_wp_error( $id ) ) {
			continue;
		}

		update_post_meta( $id, SAMPLE_META_KEY, '1' );
		update_post_meta( $id, '_lfuf_price', $p['price'] );
		update_post_meta( $id, '_lfuf_unit', $p['unit'] );
		update_post_meta( $id, '_lfuf_growing_notes', $p['notes'] );

		// Assign taxonomy terms.
		wp_set_object_terms( $id, $p['type'], 'lfuf_product_type' );
		wp_set_object_terms( $id, $p['seasons'], 'lfuf_season' );

		$ids[] = $id;
	}

	return $ids;
}

function seed_availability( array $product_ids, int $location_id ): void {
	if ( empty( $product_ids ) || $location_id < 1 ) {
		return;
	}

	$statuses = [ 'abundant', 'available', 'available', 'limited', 'available', 'available', 'limited', 'sold_out' ];
	$notes    = [ '', '', 'Nice big heads', '~4 bunches left', '', '', '~3 jars remaining', '' ];
	$today    = current_time( 'Y-m-d' );

	foreach ( $product_ids as $i => $pid ) {
		$status = $statuses[ $i ] ?? 'available';
		$note   = $notes[ $i ] ?? '';

		\Leftfield\Core\Availability\upsert(
			[
				'product_id'     => $pid,
				'location_id'    => $location_id,
				'status'         => $status,
				'quantity_note'  => $note,
				'effective_date' => $today,
			]
		);
	}
}

function seed_events( int $location_id, array $product_ids ): void {
	// Pizza night — 3 weeks from now.
	$pizza_date = gmdate( 'Y-m-d', strtotime( '+3 weeks' ) );
	$pizza_id   = wp_insert_post(
		[
			'post_type'    => 'lfuf_event',
			'post_title'   => 'Pizza Night (Sample)',
			'post_excerpt' => 'Wood-fired pizza in the field. Bring a dish to share!',
			'post_content' => 'Join us for a laid-back evening of wood-fired pizza made with our own sourdough and farm-fresh toppings. This is a donation-based event — pay what you can. Bring a side dish, a dessert, or just your appetite.',
			'post_status'  => 'publish',
		]
	);

	if ( ! is_wp_error( $pizza_id ) ) {
		update_post_meta( $pizza_id, SAMPLE_META_KEY, '1' );
		update_post_meta( $pizza_id, '_lfuf_start_datetime', $pizza_date . 'T18:00:00' );
		update_post_meta( $pizza_id, '_lfuf_end_datetime', $pizza_date . 'T21:00:00' );
		update_post_meta( $pizza_id, '_lfuf_event_location_id', $location_id );
		update_post_meta( $pizza_id, '_lfuf_donation_link', 'https://venmo.com/examplefarm' );
		update_post_meta( $pizza_id, '_lfuf_rsvp_cap', 30 );
		update_post_meta( $pizza_id, '_lfuf_em_rsvp_enabled', true );
		update_post_meta( $pizza_id, '_lfuf_em_rsvp_label', 'Count me in!' );
		update_post_meta( $pizza_id, '_lfuf_em_cost_note', 'Donation-based — suggested $10/person' );
		update_post_meta( $pizza_id, '_lfuf_em_what_to_bring', 'A side dish or dessert to share' );
		wp_set_object_terms( $pizza_id, 'Pizza Night', 'lfuf_event_type' );
		if ( ! empty( $product_ids ) ) {
			update_post_meta( $pizza_id, '_lfuf_featured_product_ids', array_slice( $product_ids, 0, 3 ) );
		}
	}

	// Seed exchange — 5 weeks from now.
	$seed_date = gmdate( 'Y-m-d', strtotime( '+5 weeks' ) );
	$seed_id   = wp_insert_post(
		[
			'post_type'    => 'lfuf_event',
			'post_title'   => 'Seed Exchange + Potluck (Sample)',
			'post_excerpt' => 'Swap seeds, share stories, and enjoy a community potluck.',
			'post_content' => 'Bring your saved seeds, extra seedlings, or gardening knowledge to share. We\'ll have tables set up for swapping and a potluck lunch. Everyone is welcome — you don\'t need to bring seeds to attend.',
			'post_status'  => 'publish',
		]
	);

	if ( ! is_wp_error( $seed_id ) ) {
		update_post_meta( $seed_id, SAMPLE_META_KEY, '1' );
		update_post_meta( $seed_id, '_lfuf_start_datetime', $seed_date . 'T11:00:00' );
		update_post_meta( $seed_id, '_lfuf_end_datetime', $seed_date . 'T14:00:00' );
		update_post_meta( $seed_id, '_lfuf_event_location_id', $location_id );
		update_post_meta( $seed_id, '_lfuf_rsvp_cap', 0 );
		update_post_meta( $seed_id, '_lfuf_em_rsvp_enabled', true );
		update_post_meta( $seed_id, '_lfuf_em_rsvp_label', "I'll be there!" );
		update_post_meta( $seed_id, '_lfuf_em_cost_note', 'Free!' );
		update_post_meta( $seed_id, '_lfuf_em_what_to_bring', 'Seeds to swap, a dish for the potluck, and your curiosity' );
		wp_set_object_terms( $seed_id, [ 'Seed Exchange', 'Potluck' ], 'lfuf_event_type' );
	}

	// Farm tour — 6 weeks from now.
	$tour_date = gmdate( 'Y-m-d', strtotime( '+6 weeks' ) );
	$tour_id   = wp_insert_post(
		[
			'post_type'    => 'lfuf_event',
			'post_title'   => 'Farm Tour + Workshop: No-Till Growing (Sample)',
			'post_excerpt' => 'Learn how we grow without tilling and see the farm up close.',
			'post_content' => 'A guided tour of the farm followed by a hands-on workshop on no-till growing methods. We\'ll cover bed preparation, mulching, composting, and succession planting. Great for beginning and experienced gardeners alike.',
			'post_status'  => 'publish',
		]
	);

	if ( ! is_wp_error( $tour_id ) ) {
		update_post_meta( $tour_id, SAMPLE_META_KEY, '1' );
		update_post_meta( $tour_id, '_lfuf_start_datetime', $tour_date . 'T10:00:00' );
		update_post_meta( $tour_id, '_lfuf_end_datetime', $tour_date . 'T12:00:00' );
		update_post_meta( $tour_id, '_lfuf_event_location_id', $location_id );
		update_post_meta( $tour_id, '_lfuf_rsvp_cap', 15 );
		update_post_meta( $tour_id, '_lfuf_em_rsvp_enabled', true );
		update_post_meta( $tour_id, '_lfuf_em_rsvp_label', 'Reserve my spot' );
		update_post_meta( $tour_id, '_lfuf_em_cost_note', '$15/person — supports the farm' );
		update_post_meta( $tour_id, '_lfuf_em_what_to_bring', 'Comfortable shoes and water' );
		update_post_meta( $tour_id, '_lfuf_donation_link', 'https://venmo.com/examplefarm' );
		wp_set_object_terms( $tour_id, [ 'Farm Tour', 'Workshop' ], 'lfuf_event_type' );
	}
}

/* ───────────────────────────────────────────────
 * Remover
 * ─────────────────────────────────────────────── */

function remove_all(): void {
	// Remove sample posts (products, locations, events).
	$post_types = [ 'lfuf_product', 'lfuf_location', 'lfuf_event', 'lfuf_source' ];

	foreach ( $post_types as $pt ) {
		$posts = get_posts(
			[
				'post_type'   => $pt,
				'post_status' => 'any',
				'numberposts' => 200,
				'meta_query'  => [
					[
						'key'   => SAMPLE_META_KEY,
						'value' => '1',
					],
				],
			]
		);

		foreach ( $posts as $post ) {
			wp_delete_post( $post->ID, true );
		}
	}

	// Remove sample availability rows.
	// (These reference sample product IDs which are now deleted,
	//  but we clean up orphans explicitly.)
	global $wpdb;
	$avail_table = $wpdb->prefix . 'lfuf_availability';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a $wpdb->prefix identifier, not user input; identifiers cannot be parameterized.
	if ( $wpdb->get_var( "SHOW TABLES LIKE '{$avail_table}'" ) === $avail_table ) {
		$wpdb->query(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a $wpdb->prefix identifier, not user input; identifiers cannot be parameterized.
			"DELETE a FROM {$avail_table} a
             LEFT JOIN {$wpdb->posts} p ON p.ID = a.product_id
             WHERE p.ID IS NULL"
		);
	}

	// Remove sample RSVPs (orphaned by deleted events).
	$rsvp_table = $wpdb->prefix . 'lfuf_rsvps';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a $wpdb->prefix identifier, not user input; identifiers cannot be parameterized.
	if ( $wpdb->get_var( "SHOW TABLES LIKE '{$rsvp_table}'" ) === $rsvp_table ) {
		$wpdb->query(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a $wpdb->prefix identifier, not user input; identifiers cannot be parameterized.
			"DELETE r FROM {$rsvp_table} r
             LEFT JOIN {$wpdb->posts} p ON p.ID = r.event_id
             WHERE p.ID IS NULL"
		);
	}
}
