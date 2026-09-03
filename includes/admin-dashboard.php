<?php
/**
 * ProducerKit admin dashboard.
 *
 * A single admin page that shows:
 *  - Active/registered modules with status indicators
 *  - Content counts for each CPT
 *  - Stand status quick view (if stand-status module is active)
 *  - Quick links to relevant admin screens
 *
 * Hooked at the plugin root level, not inside a module,
 * because it needs visibility across all modules.
 */

declare(strict_types=1);

namespace ProducerKit\Admin;

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', __NAMESPACE__ . '\\register_dashboard_page' );
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_dashboard_styles' );

function register_dashboard_page(): void {
	add_menu_page(
		__( 'ProducerKit', 'producerkit' ),
		__( 'ProducerKit', 'producerkit' ),
		'edit_posts',
		// Page slug stays as-is: it is in admin URLs and is the parent slug
		// for five submenus. Only the display label and icon change.
		'producerkit',
		__NAMESPACE__ . '\\render_dashboard',
		'dashicons-store',
		// Sits with its own content types (26, 27) rather than above Posts.
		// add_menu_page() nudges by a fraction on collision, so landing on
		// Comments' 25 puts this directly beneath it rather than replacing it.
		25,
	);
}

function enqueue_dashboard_styles( string $hook ): void {
	if ( $hook !== 'toplevel_page_producerkit' ) {
		return;
	}

	wp_add_inline_style( 'wp-admin', get_dashboard_css() );
}

function render_dashboard(): void {
	$registered = \ProducerKit\get_registered_modules();
	$labels     = \ProducerKit\get_module_labels();
	$active     = \ProducerKit\get_active_modules();

	// Content counts.
	$cpt_counts = [];
	$cpt_map    = [
		'pkit_product'  => [
			'label' => __( 'Products', 'producerkit' ),
			'icon'  => '🏷️',
		],
		'pkit_source'   => [
			'label' => __( 'Sources', 'producerkit' ),
			'icon'  => '📇',
		],
		'pkit_location' => [
			'label' => __( 'Locations', 'producerkit' ),
			'icon'  => '📍',
		],
		'pkit_event'    => [
			'label' => __( 'Events', 'producerkit' ),
			'icon'  => '📅',
		],
	];

	foreach ( $cpt_map as $post_type => $meta ) {
		$counts                   = wp_count_posts( $post_type );
		$cpt_counts[ $post_type ] = [
			'label'     => $meta['label'],
			'icon'      => $meta['icon'],
			'published' => (int) ( $counts->publish ?? 0 ),
			'draft'     => (int) ( $counts->draft ?? 0 ),
			'edit_url'  => admin_url( 'edit.php?post_type=' . $post_type ),
			'add_url'   => admin_url( 'post-new.php?post_type=' . $post_type ),
		];
	}

	// Stand status (if module active).
	$stand_data = null;
	if ( \ProducerKit\is_module_active( 'stand-status' ) ) {
		$stands = get_posts(
			[
				'post_type'   => 'pkit_location',
				'post_status' => 'publish',
				'numberposts' => 1,
				'meta_query'  => [
					[
						'key'   => '_pkit_location_type',
						'value' => 'stand',
					],
				],
			]
		);
		if ( $stands ) {
			$stand      = $stands[0];
			$stand_data = [
				'id'             => $stand->ID,
				'title'          => $stand->post_title,
				'is_open'        => (bool) get_post_meta( $stand->ID, '_pkit_is_open', true ),
				'status_message' => get_post_meta( $stand->ID, '_pkit_ss_status_message', true ),
				'last_toggled'   => get_post_meta( $stand->ID, '_pkit_ss_last_toggled', true ),
				'season_start'   => get_post_meta( $stand->ID, '_pkit_ss_season_start', true ),
				'season_end'     => get_post_meta( $stand->ID, '_pkit_ss_season_end', true ),
				'edit_url'       => get_edit_post_link( $stand->ID, 'raw' ),
			];
		}
	}

	// Availability counts (if table exists).
	$availability_summary = get_availability_summary();

	// Abilities check.
	$abilities_available = function_exists( 'wp_register_ability' );

	?>
	<div class="wrap pkit-dashboard">
		<h1 class="pkit-dashboard__title">
			<?php esc_html_e( 'ProducerKit', 'producerkit' ); ?>
			<span class="pkit-dashboard__version">v<?php echo esc_html( \ProducerKit\VERSION ); ?></span>
		</h1>

		<!-- ── Stand Status (prominent if module active) ── -->
		<?php if ( $stand_data ) : ?>
			<div class="pkit-dashboard__stand-card pkit-dashboard__stand-card--<?php echo $stand_data['is_open'] ? 'open' : 'closed'; ?>">
				<div class="pkit-dashboard__stand-header">
					<span class="pkit-dashboard__stand-dot"></span>
					<strong>
						<?php echo esc_html( $stand_data['title'] ); ?>
						— 
						<?php
						echo $stand_data['is_open']
							? esc_html__( 'Open', 'producerkit' )
							: esc_html__( 'Closed', 'producerkit' );
						?>
					</strong>
				</div>
				<?php if ( $stand_data['status_message'] ) : ?>
					<p class="pkit-dashboard__stand-message">
						<?php echo esc_html( $stand_data['status_message'] ); ?>
					</p>
				<?php endif; ?>
				<div class="pkit-dashboard__stand-meta">
					<?php
					if ( $stand_data['last_toggled'] ) :
						// time(), not current_time( 'timestamp' ) — see the note in
						// blocks/stand-status-banner/render.php. strtotime() returns a
						// true epoch; current_time( 'timestamp' ) returns epoch plus
						// gmt_offset, so mixing them skews by exactly that offset.
						$ago = human_time_diff( strtotime( $stand_data['last_toggled'] ), time() );
						?>
						<span>
						<?php
							/* translators: %s: human-readable time since the last update (e.g. "5 mins"). */
							printf( esc_html__( 'Updated %s ago', 'producerkit' ), esc_html( $ago ) );
						?>
						</span>
					<?php endif; ?>
					<?php if ( $stand_data['season_start'] && $stand_data['season_end'] ) : ?>
						<span>
							<?php
							printf(
								/* translators: %1$s: season start date, %2$s: season end date. */
								esc_html__( 'Season: %1$s – %2$s', 'producerkit' ),
								esc_html( date_i18n( 'M j', strtotime( $stand_data['season_start'] ) ) ),
								esc_html( date_i18n( 'M j', strtotime( $stand_data['season_end'] ) ) ),
							);
							?>
						</span>
					<?php endif; ?>
				</div>
				<a href="<?php echo esc_url( $stand_data['edit_url'] ); ?>" class="button">
					<?php esc_html_e( 'Edit Stand', 'producerkit' ); ?>
				</a>
			</div>
		<?php endif; ?>

		<!-- ── Content Overview ── -->
		<div class="pkit-dashboard__section">
			<h2><?php esc_html_e( 'Content', 'producerkit' ); ?></h2>
			<div class="pkit-dashboard__cards">
				<?php foreach ( $cpt_counts as $post_type => $data ) : ?>
					<div class="pkit-dashboard__card">
						<span class="pkit-dashboard__card-icon"><?php echo esc_html( $data['icon'] ); ?></span>
						<div class="pkit-dashboard__card-body">
							<span class="pkit-dashboard__card-count"><?php echo (int) $data['published']; ?></span>
							<span class="pkit-dashboard__card-label"><?php echo esc_html( $data['label'] ); ?></span>
							<?php if ( $data['draft'] > 0 ) : ?>
								<span class="pkit-dashboard__card-draft">
									+<?php echo (int) $data['draft']; ?> <?php esc_html_e( 'drafts', 'producerkit' ); ?>
								</span>
							<?php endif; ?>
						</div>
						<div class="pkit-dashboard__card-actions">
							<a href="<?php echo esc_url( $data['edit_url'] ); ?>"><?php esc_html_e( 'View', 'producerkit' ); ?></a>
							<a href="<?php echo esc_url( $data['add_url'] ); ?>"><?php esc_html_e( 'Add New', 'producerkit' ); ?></a>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- ── Availability Summary ── -->
		<?php if ( $availability_summary ) : ?>
			<div class="pkit-dashboard__section">
				<h2><?php esc_html_e( 'Availability', 'producerkit' ); ?></h2>
				<div class="pkit-dashboard__availability-bar">
					<?php foreach ( $availability_summary as $status => $count ) : ?>
						<span class="pkit-availability-badge pkit-availability-badge--<?php echo esc_attr( $status ); ?>">
							<?php echo esc_html( ucfirst( str_replace( '_', ' ', $status ) ) ); ?>:
							<?php echo (int) $count; ?>
						</span>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>

		<!-- ── What's Missing ── -->
		<?php
		$gaps = get_content_gaps();
		if ( ! empty( $gaps ) ) :
			?>
			<div class="pkit-dashboard__section">
				<h2><?php esc_html_e( 'Needs Attention', 'producerkit' ); ?></h2>
				<div class="pkit-dashboard__gaps">
					<?php foreach ( $gaps as $gap ) : ?>
						<div class="pkit-dashboard__gap pkit-dashboard__gap--<?php echo esc_attr( $gap['severity'] ); ?>">
							<span class="pkit-dashboard__gap-icon"><?php echo esc_html( $gap['icon'] ); ?></span>
							<div class="pkit-dashboard__gap-body">
								<strong><?php echo esc_html( $gap['label'] ); ?></strong>
								<span class="pkit-dashboard__gap-detail"><?php echo esc_html( $gap['detail'] ); ?></span>
							</div>
							<?php if ( $gap['url'] ) : ?>
								<a href="<?php echo esc_url( $gap['url'] ); ?>" class="button button-small">
									<?php echo esc_html( $gap['action'] ); ?>
								</a>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>

		<!-- ── Modules ── -->
		<div class="pkit-dashboard__section">
			<h2><?php esc_html_e( 'Modules', 'producerkit' ); ?></h2>
			<table class="widefat pkit-dashboard__modules-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Module', 'producerkit' ); ?></th>
						<th><?php esc_html_e( 'Status', 'producerkit' ); ?></th>
						<th><?php esc_html_e( 'Type', 'producerkit' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $registered as $slug => $config ) :
						$is_active = in_array( $slug, $active, true );
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( $labels[ $slug ] ?? $slug ); ?></strong>
								<code class="pkit-dashboard__module-slug"><?php echo esc_html( $slug ); ?></code>
							</td>
							<td>
								<span class="pkit-dashboard__module-status pkit-dashboard__module-status--<?php echo $is_active ? 'active' : 'inactive'; ?>">
									<?php
									echo $is_active
										? esc_html__( 'Active', 'producerkit' )
										: esc_html__( 'Inactive', 'producerkit' );
									?>
								</span>
							</td>
							<td>
								<?php
								echo $config['required']
									? esc_html__( 'Required', 'producerkit' )
									: esc_html__( 'Optional', 'producerkit' );
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<!-- ── Platform Info ── -->
		<div class="pkit-dashboard__section">
			<h2><?php esc_html_e( 'Platform', 'producerkit' ); ?></h2>
			<ul class="pkit-dashboard__platform-list">
				<li>
					<?php
					/* translators: %s: WordPress version number. */
					printf( esc_html__( 'WordPress %s', 'producerkit' ), esc_html( get_bloginfo( 'version' ) ) );
					?>
				</li>
				<li>
					<?php
					/* translators: %s: PHP version number. */
					printf( esc_html__( 'PHP %s', 'producerkit' ), esc_html( PHP_VERSION ) );
					?>
				</li>
				<li>
					<?php esc_html_e( 'Interactivity API:', 'producerkit' ); ?>
					<span class="pkit-dashboard__check pkit-dashboard__check--yes">
						<?php esc_html_e( 'Available', 'producerkit' ); ?>
					</span>
				</li>
				<li>
					<?php esc_html_e( 'Abilities API:', 'producerkit' ); ?>
					<span class="pkit-dashboard__check pkit-dashboard__check--<?php echo $abilities_available ? 'yes' : 'no'; ?>">
						<?php
						echo $abilities_available
							? esc_html__( 'Available', 'producerkit' )
							: esc_html__( 'Not available (requires WP 6.9+)', 'producerkit' );
						?>
					</span>
				</li>
			</ul>
		</div>

		<!-- ── Sample Data ── -->
		<?php
		if ( function_exists( 'ProducerKit\\SampleData\\get_dashboard_html' ) ) {
			echo \ProducerKit\SampleData\get_dashboard_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_dashboard_html() escapes all output internally.
		}
		?>
	</div>
	<?php
}

/* ───────────────────────────────────────────────
 * Helpers
 * ─────────────────────────────────────────────── */

/**
 * Check for content gaps that need attention.
 *
 * @return array<array{icon:string,label:string,detail:string,severity:string,url:string,action:string}>
 */
function get_content_gaps(): array {
	global $wpdb;
	$gaps = [];

	// Products without featured images.
	$no_thumb       = new \WP_Query(
		[
			'post_type'      => 'pkit_product',
			'post_status'    => 'publish',
			'meta_query'     => [
				[
					'key'     => '_thumbnail_id',
					'compare' => 'NOT EXISTS',
				],
			],
			'fields'         => 'ids',
			'posts_per_page' => -1,
		]
	);
	$no_thumb_count = $no_thumb->found_posts;
	if ( $no_thumb_count > 0 ) {
		$gaps[] = [
			'icon'     => '📷',
			/* translators: %d: number of products without a featured photo. */
			'label'    => sprintf( _n( '%d product without a photo', '%d products without photos', $no_thumb_count, 'producerkit' ), $no_thumb_count ),
			'detail'   => __( 'Products look better on the availability board with images.', 'producerkit' ),
			'severity' => 'info',
			'url'      => admin_url( 'edit.php?post_type=pkit_product' ),
			'action'   => __( 'View Products', 'producerkit' ),
		];
	}

	// Products without a price.
	$no_price_count = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->posts} p
         LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_pkit_price'
         WHERE p.post_type = 'pkit_product' AND p.post_status = 'publish'
           AND (pm.meta_value IS NULL OR pm.meta_value = '')"
	);
	if ( $no_price_count > 0 ) {
		$gaps[] = [
			'icon'     => '💲',
			/* translators: %d: number of products without a price. */
			'label'    => sprintf( _n( '%d product without a price', '%d products without prices', $no_price_count, 'producerkit' ), $no_price_count ),
			'detail'   => __( 'Visitors see the price on the availability board and product pages.', 'producerkit' ),
			'severity' => 'warning',
			'url'      => admin_url( 'edit.php?post_type=pkit_product' ),
			'action'   => __( 'View Products', 'producerkit' ),
		];
	}

	// Events without a start date.
	$no_date_count = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->posts} p
         LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_pkit_start_datetime'
         WHERE p.post_type = 'pkit_event' AND p.post_status = 'publish'
           AND (pm.meta_value IS NULL OR pm.meta_value = '')"
	);
	if ( $no_date_count > 0 ) {
		$gaps[] = [
			'icon'     => '📅',
			/* translators: %d: number of events without a start date. */
			'label'    => sprintf( _n( '%d event without a start date', '%d events without start dates', $no_date_count, 'producerkit' ), $no_date_count ),
			'detail'   => __( 'Events need a start date to appear in the event list.', 'producerkit' ),
			'severity' => 'warning',
			'url'      => admin_url( 'edit.php?post_type=pkit_event' ),
			'action'   => __( 'View Events', 'producerkit' ),
		];
	}

	// Locations without an address.
	$no_addr_count = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->posts} p
         LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_pkit_address'
         WHERE p.post_type = 'pkit_location' AND p.post_status = 'publish'
           AND (pm.meta_value IS NULL OR pm.meta_value = '')"
	);
	if ( $no_addr_count > 0 ) {
		$gaps[] = [
			'icon'     => '📍',
			/* translators: %d: number of locations without an address. */
			'label'    => sprintf( _n( '%d location without an address', '%d locations without addresses', $no_addr_count, 'producerkit' ), $no_addr_count ),
			'detail'   => __( 'The address shows on location cards and the stand banner.', 'producerkit' ),
			'severity' => 'warning',
			'url'      => admin_url( 'edit.php?post_type=pkit_location' ),
			'action'   => __( 'View Locations', 'producerkit' ),
		];
	}

	// Stale availability and unlisted products.
	$avail_table = $wpdb->prefix . 'pkit_availability';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a $wpdb->prefix identifier, not user input; identifiers cannot be parameterized.
	if ( $wpdb->get_var( "SHOW TABLES LIKE '{$avail_table}'" ) === $avail_table ) {
		$today    = current_time( 'Y-m-d' );
		$week_ago = gmdate( 'Y-m-d', strtotime( '-7 days', current_time( 'timestamp' ) ) );

		// Products with availability older than 7 days.
		$stale_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a $wpdb->prefix identifier, not user input; identifiers cannot be parameterized.
				"SELECT COUNT(DISTINCT a.product_id) FROM {$avail_table} a
             INNER JOIN {$wpdb->posts} p ON p.ID = a.product_id AND p.post_status = 'publish'
             WHERE a.effective_date <= %s
               AND (a.expires_date IS NULL OR a.expires_date >= %s)
               AND a.updated_at < %s",
				$today,
				$today,
				$week_ago . ' 00:00:00',
			)
		);
		if ( $stale_count > 0 ) {
			$gaps[] = [
				'icon'     => '🕐',
				/* translators: %d: number of products with availability over a week old. */
				'label'    => sprintf( _n( '%d product with availability over a week old', '%d products with availability over a week old', $stale_count, 'producerkit' ), $stale_count ),
				'detail'   => __( 'Consider refreshing the availability board.', 'producerkit' ),
				'severity' => 'info',
				'url'      => admin_url( 'admin.php?page=producerkit-availability' ),
				'action'   => __( 'Update Availability', 'producerkit' ),
			];
		}

		// Products not on the board at all.
		$total_products = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'pkit_product' AND post_status = 'publish'"
		);
		$listed_count   = (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a $wpdb->prefix identifier, not user input; identifiers cannot be parameterized.
				"SELECT COUNT(DISTINCT product_id) FROM {$avail_table}
             WHERE effective_date <= %s AND (expires_date IS NULL OR expires_date >= %s)",
				$today,
				$today,
			)
		);
		$unlisted = $total_products - $listed_count;
		if ( $unlisted > 0 && $total_products > 0 ) {
			$gaps[] = [
				'icon'     => '🫥',
				/* translators: %d: number of products not on the availability board. */
				'label'    => sprintf( _n( '%d product not on the availability board', '%d products not on the availability board', $unlisted, 'producerkit' ), $unlisted ),
				'detail'   => __( 'These products won\'t show up for visitors until you set their status.', 'producerkit' ),
				'severity' => 'info',
				'url'      => admin_url( 'admin.php?page=producerkit-availability' ),
				'action'   => __( 'Update Availability', 'producerkit' ),
			];
		}
	}

	return $gaps;
}

function get_availability_summary(): array {
	global $wpdb;

	$table = $wpdb->prefix . 'pkit_availability';

	// Check if table exists.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a $wpdb->prefix identifier, not user input; identifiers cannot be parameterized.
	if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
		return [];
	}

	$today = current_time( 'Y-m-d' );

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a $wpdb->prefix identifier, not user input; identifiers cannot be parameterized. Disabled rather than ignored because the interpolation sits inside a multi-line string.
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT status, COUNT(*) as cnt
         FROM {$table}
         WHERE effective_date <= %s
           AND (expires_date IS NULL OR expires_date >= %s)
         GROUP BY status
         ORDER BY FIELD(status, 'abundant', 'available', 'limited', 'sold_out', 'unavailable')",
			$today,
			$today,
		)
	);
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	$summary = [];
	foreach ( $rows as $row ) {
		$summary[ $row->status ] = (int) $row->cnt;
	}

	return $summary;
}

function get_dashboard_css(): string {
	return <<<'CSS'
    .pkit-dashboard__title {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    .pkit-dashboard__version {
        font-size: 0.7rem;
        font-weight: 400;
        background: #f0f0f1;
        padding: 0.15rem 0.5rem;
        border-radius: 9999px;
        color: #50575e;
    }
    .pkit-dashboard__section {
        margin-top: 1.5rem;
    }
    .pkit-dashboard__section h2 {
        font-size: 1.1rem;
        margin-bottom: 0.75rem;
    }

    /* Stand card */
    .pkit-dashboard__stand-card {
        margin-top: 1rem;
        padding: 1rem 1.25rem;
        border-left: 4px solid;
        border-radius: 0.25rem;
        background: #fff;
        box-shadow: 0 1px 1px rgba(0,0,0,0.04);
    }
    .pkit-dashboard__stand-card--open {
        border-left-color: #16a34a;
        background: #f0fdf4;
    }
    .pkit-dashboard__stand-card--closed {
        border-left-color: #dc2626;
        background: #fef2f2;
    }
    .pkit-dashboard__stand-header {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 1rem;
    }
    .pkit-dashboard__stand-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        flex-shrink: 0;
    }
    .pkit-dashboard__stand-card--open .pkit-dashboard__stand-dot {
        background: #16a34a;
    }
    .pkit-dashboard__stand-card--closed .pkit-dashboard__stand-dot {
        background: #dc2626;
    }
    .pkit-dashboard__stand-message {
        margin: 0.35rem 0 0;
        font-style: italic;
        font-size: 0.9rem;
    }
    .pkit-dashboard__stand-meta {
        display: flex;
        gap: 1rem;
        margin-top: 0.35rem;
        font-size: 0.8rem;
        color: #6b7280;
    }
    .pkit-dashboard__stand-card .button {
        margin-top: 0.75rem;
    }

    /* Content cards */
    .pkit-dashboard__cards {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 0.75rem;
    }
    .pkit-dashboard__card {
        background: #fff;
        border: 1px solid #dcdcde;
        border-radius: 0.375rem;
        padding: 1rem;
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
    }
    .pkit-dashboard__card-icon {
        font-size: 1.5rem;
        line-height: 1;
    }
    .pkit-dashboard__card-count {
        font-size: 1.75rem;
        font-weight: 700;
        line-height: 1;
    }
    .pkit-dashboard__card-label {
        font-size: 0.85rem;
        color: #50575e;
    }
    .pkit-dashboard__card-draft {
        font-size: 0.75rem;
        color: #9ca3af;
    }
    .pkit-dashboard__card-actions {
        margin-top: 0.5rem;
        display: flex;
        gap: 0.75rem;
        font-size: 0.8rem;
    }

    /* Availability bar */
    .pkit-dashboard__availability-bar {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    /* Needs Attention gaps */
    .pkit-dashboard__gaps {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    .pkit-dashboard__gap {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.65rem 1rem;
        border-radius: 0.375rem;
        border-left: 3px solid;
        background: #fff;
    }
    .pkit-dashboard__gap--warning {
        border-left-color: #f59e0b;
        background: #fffbeb;
    }
    .pkit-dashboard__gap--info {
        border-left-color: #3b82f6;
        background: #eff6ff;
    }
    .pkit-dashboard__gap-icon {
        font-size: 1.25rem;
        flex-shrink: 0;
    }
    .pkit-dashboard__gap-body {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 0.1rem;
    }
    .pkit-dashboard__gap-body strong {
        font-size: 0.85rem;
    }
    .pkit-dashboard__gap-detail {
        font-size: 0.8rem;
        color: #6b7280;
    }
    .pkit-dashboard__gap .button {
        flex-shrink: 0;
    }

    /* Modules table */
    .pkit-dashboard__modules-table {
        max-width: 600px;
    }
    .pkit-dashboard__module-slug {
        display: block;
        font-size: 0.75rem;
        color: #9ca3af;
        margin-top: 0.15rem;
    }
    .pkit-dashboard__module-status {
        font-size: 0.8rem;
        font-weight: 600;
        padding: 0.15rem 0.5rem;
        border-radius: 9999px;
    }
    .pkit-dashboard__module-status--active {
        background: #d1fae5;
        color: #065f46;
    }
    .pkit-dashboard__module-status--inactive {
        background: #f3f4f6;
        color: #6b7280;
    }

    /* Platform list */
    .pkit-dashboard__platform-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .pkit-dashboard__platform-list li {
        padding: 0.35rem 0;
        font-size: 0.9rem;
        border-bottom: 1px solid #f3f4f6;
    }
    .pkit-dashboard__check {
        font-weight: 600;
        font-size: 0.8rem;
    }
    .pkit-dashboard__check--yes { color: #16a34a; }
    .pkit-dashboard__check--no  { color: #9ca3af; }

    /* Reuse availability badge styles from blocks */
    .pkit-availability-badge {
        display: inline-block;
        font-size: 0.75rem;
        font-weight: 600;
        padding: 0.2rem 0.6rem;
        border-radius: 0.25rem;
    }
    .pkit-availability-badge--abundant   { background: #d1fae5; color: #065f46; }
    .pkit-availability-badge--available  { background: #dbeafe; color: #1e40af; }
    .pkit-availability-badge--limited    { background: #fef3c7; color: #92400e; }
    .pkit-availability-badge--sold_out   { background: #fee2e2; color: #991b1b; }
    .pkit-availability-badge--unavailable { background: #f3f4f6; color: #6b7280; }
CSS;
}