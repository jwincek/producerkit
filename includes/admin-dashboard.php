<?php
/**
 * Farm Stand Manager admin dashboard.
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

namespace Leftfield\Admin;

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', __NAMESPACE__ . '\\register_dashboard_page' );
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_dashboard_styles' );

function register_dashboard_page(): void {
	add_menu_page(
		__( 'Farm Stand Manager', 'farm-stand-manager' ),
		__( 'Farm Stand', 'farm-stand-manager' ),
		'edit_posts',
		'farm-stand-dashboard',
		__NAMESPACE__ . '\\render_dashboard',
		'dashicons-carrot',
		3,
	);
}

function enqueue_dashboard_styles( string $hook ): void {
	if ( $hook !== 'toplevel_page_farm-stand-dashboard' ) {
		return;
	}

	wp_add_inline_style( 'wp-admin', get_dashboard_css() );
}

function render_dashboard(): void {
	$registered = \Leftfield\get_registered_modules();
	$labels     = \Leftfield\get_module_labels();
	$active     = \Leftfield\get_active_modules();

	// Content counts.
	$cpt_counts = [];
	$cpt_map    = [
		'lfuf_product'  => [
			'label' => __( 'Products', 'farm-stand-manager' ),
			'icon'  => '🥬',
		],
		'lfuf_source'   => [
			'label' => __( 'Sources', 'farm-stand-manager' ),
			'icon'  => '🌾',
		],
		'lfuf_location' => [
			'label' => __( 'Locations', 'farm-stand-manager' ),
			'icon'  => '📍',
		],
		'lfuf_event'    => [
			'label' => __( 'Events', 'farm-stand-manager' ),
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
	if ( \Leftfield\is_module_active( 'stand-status' ) ) {
		$stands = get_posts(
			[
				'post_type'   => 'lfuf_location',
				'post_status' => 'publish',
				'numberposts' => 1,
				'meta_query'  => [
					[
						'key'   => '_lfuf_location_type',
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
				'is_open'        => (bool) get_post_meta( $stand->ID, '_lfuf_is_open', true ),
				'status_message' => get_post_meta( $stand->ID, '_lfuf_ss_status_message', true ),
				'last_toggled'   => get_post_meta( $stand->ID, '_lfuf_ss_last_toggled', true ),
				'season_start'   => get_post_meta( $stand->ID, '_lfuf_ss_season_start', true ),
				'season_end'     => get_post_meta( $stand->ID, '_lfuf_ss_season_end', true ),
				'edit_url'       => get_edit_post_link( $stand->ID, 'raw' ),
			];
		}
	}

	// Availability counts (if table exists).
	$availability_summary = get_availability_summary();

	// Abilities check.
	$abilities_available = function_exists( 'wp_register_ability' );

	?>
	<div class="wrap lfuf-dashboard">
		<h1 class="lfuf-dashboard__title">
			<?php esc_html_e( 'Farm Stand Manager', 'farm-stand-manager' ); ?>
			<span class="lfuf-dashboard__version">v<?php echo esc_html( \Leftfield\VERSION ); ?></span>
		</h1>

		<!-- ── Stand Status (prominent if module active) ── -->
		<?php if ( $stand_data ) : ?>
			<div class="lfuf-dashboard__stand-card lfuf-dashboard__stand-card--<?php echo $stand_data['is_open'] ? 'open' : 'closed'; ?>">
				<div class="lfuf-dashboard__stand-header">
					<span class="lfuf-dashboard__stand-dot"></span>
					<strong>
						<?php echo esc_html( $stand_data['title'] ); ?>
						— 
						<?php
						echo $stand_data['is_open']
							? esc_html__( 'Open', 'farm-stand-manager' )
							: esc_html__( 'Closed', 'farm-stand-manager' );
						?>
					</strong>
				</div>
				<?php if ( $stand_data['status_message'] ) : ?>
					<p class="lfuf-dashboard__stand-message">
						<?php echo esc_html( $stand_data['status_message'] ); ?>
					</p>
				<?php endif; ?>
				<div class="lfuf-dashboard__stand-meta">
					<?php
					if ( $stand_data['last_toggled'] ) :
						$ago = human_time_diff( strtotime( $stand_data['last_toggled'] ), current_time( 'timestamp' ) );
						?>
						<span>
						<?php
							/* translators: %s: human-readable time since the last update (e.g. "5 mins"). */
							printf( esc_html__( 'Updated %s ago', 'farm-stand-manager' ), esc_html( $ago ) );
						?>
						</span>
					<?php endif; ?>
					<?php if ( $stand_data['season_start'] && $stand_data['season_end'] ) : ?>
						<span>
							<?php
							printf(
								/* translators: %1$s: season start date, %2$s: season end date. */
								esc_html__( 'Season: %1$s – %2$s', 'farm-stand-manager' ),
								esc_html( date_i18n( 'M j', strtotime( $stand_data['season_start'] ) ) ),
								esc_html( date_i18n( 'M j', strtotime( $stand_data['season_end'] ) ) ),
							);
							?>
						</span>
					<?php endif; ?>
				</div>
				<a href="<?php echo esc_url( $stand_data['edit_url'] ); ?>" class="button">
					<?php esc_html_e( 'Edit Stand', 'farm-stand-manager' ); ?>
				</a>
			</div>
		<?php endif; ?>

		<!-- ── Content Overview ── -->
		<div class="lfuf-dashboard__section">
			<h2><?php esc_html_e( 'Content', 'farm-stand-manager' ); ?></h2>
			<div class="lfuf-dashboard__cards">
				<?php foreach ( $cpt_counts as $post_type => $data ) : ?>
					<div class="lfuf-dashboard__card">
						<span class="lfuf-dashboard__card-icon"><?php echo esc_html( $data['icon'] ); ?></span>
						<div class="lfuf-dashboard__card-body">
							<span class="lfuf-dashboard__card-count"><?php echo (int) $data['published']; ?></span>
							<span class="lfuf-dashboard__card-label"><?php echo esc_html( $data['label'] ); ?></span>
							<?php if ( $data['draft'] > 0 ) : ?>
								<span class="lfuf-dashboard__card-draft">
									+<?php echo (int) $data['draft']; ?> <?php esc_html_e( 'drafts', 'farm-stand-manager' ); ?>
								</span>
							<?php endif; ?>
						</div>
						<div class="lfuf-dashboard__card-actions">
							<a href="<?php echo esc_url( $data['edit_url'] ); ?>"><?php esc_html_e( 'View', 'farm-stand-manager' ); ?></a>
							<a href="<?php echo esc_url( $data['add_url'] ); ?>"><?php esc_html_e( 'Add New', 'farm-stand-manager' ); ?></a>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- ── Availability Summary ── -->
		<?php if ( $availability_summary ) : ?>
			<div class="lfuf-dashboard__section">
				<h2><?php esc_html_e( 'Availability', 'farm-stand-manager' ); ?></h2>
				<div class="lfuf-dashboard__availability-bar">
					<?php foreach ( $availability_summary as $status => $count ) : ?>
						<span class="lfuf-availability-badge lfuf-availability-badge--<?php echo esc_attr( $status ); ?>">
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
			<div class="lfuf-dashboard__section">
				<h2><?php esc_html_e( 'Needs Attention', 'farm-stand-manager' ); ?></h2>
				<div class="lfuf-dashboard__gaps">
					<?php foreach ( $gaps as $gap ) : ?>
						<div class="lfuf-dashboard__gap lfuf-dashboard__gap--<?php echo esc_attr( $gap['severity'] ); ?>">
							<span class="lfuf-dashboard__gap-icon"><?php echo esc_html( $gap['icon'] ); ?></span>
							<div class="lfuf-dashboard__gap-body">
								<strong><?php echo esc_html( $gap['label'] ); ?></strong>
								<span class="lfuf-dashboard__gap-detail"><?php echo esc_html( $gap['detail'] ); ?></span>
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
		<div class="lfuf-dashboard__section">
			<h2><?php esc_html_e( 'Modules', 'farm-stand-manager' ); ?></h2>
			<table class="widefat lfuf-dashboard__modules-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Module', 'farm-stand-manager' ); ?></th>
						<th><?php esc_html_e( 'Status', 'farm-stand-manager' ); ?></th>
						<th><?php esc_html_e( 'Type', 'farm-stand-manager' ); ?></th>
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
								<code class="lfuf-dashboard__module-slug"><?php echo esc_html( $slug ); ?></code>
							</td>
							<td>
								<span class="lfuf-dashboard__module-status lfuf-dashboard__module-status--<?php echo $is_active ? 'active' : 'inactive'; ?>">
									<?php
									echo $is_active
										? esc_html__( 'Active', 'farm-stand-manager' )
										: esc_html__( 'Inactive', 'farm-stand-manager' );
									?>
								</span>
							</td>
							<td>
								<?php
								echo $config['required']
									? esc_html__( 'Required', 'farm-stand-manager' )
									: esc_html__( 'Optional', 'farm-stand-manager' );
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<!-- ── Platform Info ── -->
		<div class="lfuf-dashboard__section">
			<h2><?php esc_html_e( 'Platform', 'farm-stand-manager' ); ?></h2>
			<ul class="lfuf-dashboard__platform-list">
				<li>
					<?php
					/* translators: %s: WordPress version number. */
					printf( esc_html__( 'WordPress %s', 'farm-stand-manager' ), esc_html( get_bloginfo( 'version' ) ) );
					?>
				</li>
				<li>
					<?php
					/* translators: %s: PHP version number. */
					printf( esc_html__( 'PHP %s', 'farm-stand-manager' ), esc_html( PHP_VERSION ) );
					?>
				</li>
				<li>
					<?php esc_html_e( 'Interactivity API:', 'farm-stand-manager' ); ?>
					<span class="lfuf-dashboard__check lfuf-dashboard__check--yes">
						<?php esc_html_e( 'Available', 'farm-stand-manager' ); ?>
					</span>
				</li>
				<li>
					<?php esc_html_e( 'Abilities API:', 'farm-stand-manager' ); ?>
					<span class="lfuf-dashboard__check lfuf-dashboard__check--<?php echo $abilities_available ? 'yes' : 'no'; ?>">
						<?php
						echo $abilities_available
							? esc_html__( 'Available', 'farm-stand-manager' )
							: esc_html__( 'Not available (requires WP 6.9+)', 'farm-stand-manager' );
						?>
					</span>
				</li>
			</ul>
		</div>

		<!-- ── Sample Data ── -->
		<?php
		if ( function_exists( 'Leftfield\\SampleData\\get_dashboard_html' ) ) {
			echo \Leftfield\SampleData\get_dashboard_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_dashboard_html() escapes all output internally.
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
			'post_type'      => 'lfuf_product',
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
			'label'    => sprintf( _n( '%d product without a photo', '%d products without photos', $no_thumb_count, 'farm-stand-manager' ), $no_thumb_count ),
			'detail'   => __( 'Products look better on the availability board with images.', 'farm-stand-manager' ),
			'severity' => 'info',
			'url'      => admin_url( 'edit.php?post_type=lfuf_product' ),
			'action'   => __( 'View Products', 'farm-stand-manager' ),
		];
	}

	// Products without a price.
	$no_price_count = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->posts} p
         LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_lfuf_price'
         WHERE p.post_type = 'lfuf_product' AND p.post_status = 'publish'
           AND (pm.meta_value IS NULL OR pm.meta_value = '')"
	);
	if ( $no_price_count > 0 ) {
		$gaps[] = [
			'icon'     => '💲',
			/* translators: %d: number of products without a price. */
			'label'    => sprintf( _n( '%d product without a price', '%d products without prices', $no_price_count, 'farm-stand-manager' ), $no_price_count ),
			'detail'   => __( 'Visitors see the price on the availability board and product pages.', 'farm-stand-manager' ),
			'severity' => 'warning',
			'url'      => admin_url( 'edit.php?post_type=lfuf_product' ),
			'action'   => __( 'View Products', 'farm-stand-manager' ),
		];
	}

	// Events without a start date.
	$no_date_count = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->posts} p
         LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_lfuf_start_datetime'
         WHERE p.post_type = 'lfuf_event' AND p.post_status = 'publish'
           AND (pm.meta_value IS NULL OR pm.meta_value = '')"
	);
	if ( $no_date_count > 0 ) {
		$gaps[] = [
			'icon'     => '📅',
			/* translators: %d: number of events without a start date. */
			'label'    => sprintf( _n( '%d event without a start date', '%d events without start dates', $no_date_count, 'farm-stand-manager' ), $no_date_count ),
			'detail'   => __( 'Events need a start date to appear in the event list.', 'farm-stand-manager' ),
			'severity' => 'warning',
			'url'      => admin_url( 'edit.php?post_type=lfuf_event' ),
			'action'   => __( 'View Events', 'farm-stand-manager' ),
		];
	}

	// Locations without an address.
	$no_addr_count = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->posts} p
         LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_lfuf_address'
         WHERE p.post_type = 'lfuf_location' AND p.post_status = 'publish'
           AND (pm.meta_value IS NULL OR pm.meta_value = '')"
	);
	if ( $no_addr_count > 0 ) {
		$gaps[] = [
			'icon'     => '📍',
			/* translators: %d: number of locations without an address. */
			'label'    => sprintf( _n( '%d location without an address', '%d locations without addresses', $no_addr_count, 'farm-stand-manager' ), $no_addr_count ),
			'detail'   => __( 'The address shows on location cards and the stand banner.', 'farm-stand-manager' ),
			'severity' => 'warning',
			'url'      => admin_url( 'edit.php?post_type=lfuf_location' ),
			'action'   => __( 'View Locations', 'farm-stand-manager' ),
		];
	}

	// Stale availability and unlisted products.
	$avail_table = $wpdb->prefix . 'lfuf_availability';
	if ( $wpdb->get_var( "SHOW TABLES LIKE '{$avail_table}'" ) === $avail_table ) {
		$today    = current_time( 'Y-m-d' );
		$week_ago = gmdate( 'Y-m-d', strtotime( '-7 days', current_time( 'timestamp' ) ) );

		// Products with availability older than 7 days.
		$stale_count = (int) $wpdb->get_var(
			$wpdb->prepare(
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
				'label'    => sprintf( _n( '%d product with availability over a week old', '%d products with availability over a week old', $stale_count, 'farm-stand-manager' ), $stale_count ),
				'detail'   => __( 'Consider refreshing the availability board.', 'farm-stand-manager' ),
				'severity' => 'info',
				'url'      => admin_url( 'admin.php?page=farm-stand-availability' ),
				'action'   => __( 'Update Availability', 'farm-stand-manager' ),
			];
		}

		// Products not on the board at all.
		$total_products = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'lfuf_product' AND post_status = 'publish'"
		);
		$listed_count   = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT product_id) FROM {$avail_table}
             WHERE effective_date <= %s AND (expires_date IS NULL OR expires_date >= %s)",
				$today,
				$today,
			)
		);
		$unlisted       = $total_products - $listed_count;
		if ( $unlisted > 0 && $total_products > 0 ) {
			$gaps[] = [
				'icon'     => '🫥',
				/* translators: %d: number of products not on the availability board. */
				'label'    => sprintf( _n( '%d product not on the availability board', '%d products not on the availability board', $unlisted, 'farm-stand-manager' ), $unlisted ),
				'detail'   => __( 'These products won\'t show up for visitors until you set their status.', 'farm-stand-manager' ),
				'severity' => 'info',
				'url'      => admin_url( 'admin.php?page=farm-stand-availability' ),
				'action'   => __( 'Update Availability', 'farm-stand-manager' ),
			];
		}
	}

	return $gaps;
}

function get_availability_summary(): array {
	global $wpdb;

	$table = $wpdb->prefix . 'lfuf_availability';

	// Check if table exists.
	if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
		return [];
	}

	$today = current_time( 'Y-m-d' );

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

	$summary = [];
	foreach ( $rows as $row ) {
		$summary[ $row->status ] = (int) $row->cnt;
	}

	return $summary;
}

function get_dashboard_css(): string {
	return <<<'CSS'
    .lfuf-dashboard__title {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    .lfuf-dashboard__version {
        font-size: 0.7rem;
        font-weight: 400;
        background: #f0f0f1;
        padding: 0.15rem 0.5rem;
        border-radius: 9999px;
        color: #50575e;
    }
    .lfuf-dashboard__section {
        margin-top: 1.5rem;
    }
    .lfuf-dashboard__section h2 {
        font-size: 1.1rem;
        margin-bottom: 0.75rem;
    }

    /* Stand card */
    .lfuf-dashboard__stand-card {
        margin-top: 1rem;
        padding: 1rem 1.25rem;
        border-left: 4px solid;
        border-radius: 0.25rem;
        background: #fff;
        box-shadow: 0 1px 1px rgba(0,0,0,0.04);
    }
    .lfuf-dashboard__stand-card--open {
        border-left-color: #16a34a;
        background: #f0fdf4;
    }
    .lfuf-dashboard__stand-card--closed {
        border-left-color: #dc2626;
        background: #fef2f2;
    }
    .lfuf-dashboard__stand-header {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 1rem;
    }
    .lfuf-dashboard__stand-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        flex-shrink: 0;
    }
    .lfuf-dashboard__stand-card--open .lfuf-dashboard__stand-dot {
        background: #16a34a;
    }
    .lfuf-dashboard__stand-card--closed .lfuf-dashboard__stand-dot {
        background: #dc2626;
    }
    .lfuf-dashboard__stand-message {
        margin: 0.35rem 0 0;
        font-style: italic;
        font-size: 0.9rem;
    }
    .lfuf-dashboard__stand-meta {
        display: flex;
        gap: 1rem;
        margin-top: 0.35rem;
        font-size: 0.8rem;
        color: #6b7280;
    }
    .lfuf-dashboard__stand-card .button {
        margin-top: 0.75rem;
    }

    /* Content cards */
    .lfuf-dashboard__cards {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 0.75rem;
    }
    .lfuf-dashboard__card {
        background: #fff;
        border: 1px solid #dcdcde;
        border-radius: 0.375rem;
        padding: 1rem;
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
    }
    .lfuf-dashboard__card-icon {
        font-size: 1.5rem;
        line-height: 1;
    }
    .lfuf-dashboard__card-count {
        font-size: 1.75rem;
        font-weight: 700;
        line-height: 1;
    }
    .lfuf-dashboard__card-label {
        font-size: 0.85rem;
        color: #50575e;
    }
    .lfuf-dashboard__card-draft {
        font-size: 0.75rem;
        color: #9ca3af;
    }
    .lfuf-dashboard__card-actions {
        margin-top: 0.5rem;
        display: flex;
        gap: 0.75rem;
        font-size: 0.8rem;
    }

    /* Availability bar */
    .lfuf-dashboard__availability-bar {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    /* Needs Attention gaps */
    .lfuf-dashboard__gaps {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    .lfuf-dashboard__gap {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.65rem 1rem;
        border-radius: 0.375rem;
        border-left: 3px solid;
        background: #fff;
    }
    .lfuf-dashboard__gap--warning {
        border-left-color: #f59e0b;
        background: #fffbeb;
    }
    .lfuf-dashboard__gap--info {
        border-left-color: #3b82f6;
        background: #eff6ff;
    }
    .lfuf-dashboard__gap-icon {
        font-size: 1.25rem;
        flex-shrink: 0;
    }
    .lfuf-dashboard__gap-body {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 0.1rem;
    }
    .lfuf-dashboard__gap-body strong {
        font-size: 0.85rem;
    }
    .lfuf-dashboard__gap-detail {
        font-size: 0.8rem;
        color: #6b7280;
    }
    .lfuf-dashboard__gap .button {
        flex-shrink: 0;
    }

    /* Modules table */
    .lfuf-dashboard__modules-table {
        max-width: 600px;
    }
    .lfuf-dashboard__module-slug {
        display: block;
        font-size: 0.75rem;
        color: #9ca3af;
        margin-top: 0.15rem;
    }
    .lfuf-dashboard__module-status {
        font-size: 0.8rem;
        font-weight: 600;
        padding: 0.15rem 0.5rem;
        border-radius: 9999px;
    }
    .lfuf-dashboard__module-status--active {
        background: #d1fae5;
        color: #065f46;
    }
    .lfuf-dashboard__module-status--inactive {
        background: #f3f4f6;
        color: #6b7280;
    }

    /* Platform list */
    .lfuf-dashboard__platform-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .lfuf-dashboard__platform-list li {
        padding: 0.35rem 0;
        font-size: 0.9rem;
        border-bottom: 1px solid #f3f4f6;
    }
    .lfuf-dashboard__check {
        font-weight: 600;
        font-size: 0.8rem;
    }
    .lfuf-dashboard__check--yes { color: #16a34a; }
    .lfuf-dashboard__check--no  { color: #9ca3af; }

    /* Reuse availability badge styles from blocks */
    .lfuf-availability-badge {
        display: inline-block;
        font-size: 0.75rem;
        font-weight: 600;
        padding: 0.2rem 0.6rem;
        border-radius: 0.25rem;
    }
    .lfuf-availability-badge--abundant   { background: #d1fae5; color: #065f46; }
    .lfuf-availability-badge--available  { background: #dbeafe; color: #1e40af; }
    .lfuf-availability-badge--limited    { background: #fef3c7; color: #92400e; }
    .lfuf-availability-badge--sold_out   { background: #fee2e2; color: #991b1b; }
    .lfuf-availability-badge--unavailable { background: #f3f4f6; color: #6b7280; }
CSS;
}