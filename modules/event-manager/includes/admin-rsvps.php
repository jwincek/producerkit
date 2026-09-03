<?php
/**
 * RSVPs admin screen.
 *
 * The event list has shown an RSVP count since the module was written, and
 * there has never been a way to see who those people are. The names, emails,
 * party sizes and notes went into the table and stayed there: no screen, no
 * export, only a REST route somebody would have to call by hand.
 *
 * So the plugin collected personal data and offered no way to read it — which
 * also meant no way to work the door at a capped event, which is the entire
 * reason to take RSVPs.
 *
 * Modelled on the Pre-Orders screen: server-rendered, actions through the
 * plugin's own REST route with a wp_rest nonce, so the rules live in one place.
 */

declare(strict_types=1);

namespace ProducerKit\EventManager\Admin;

use ProducerKit\EventManager\RSVP;

defined( 'ABSPATH' ) || exit;

const PAGE_SLUG = 'producerkit-rsvps';

add_action( 'admin_menu', __NAMESPACE__ . '\\register_page' );
add_action( 'admin_post_pkit_export_rsvps', __NAMESPACE__ . '\\export_csv' );

function register_page(): void {
	add_submenu_page(
		'producerkit',
		__( 'RSVPs', 'producerkit' ),
		__( 'RSVPs', 'producerkit' ),
		RSVP\manage_cap(),
		PAGE_SLUG,
		__NAMESPACE__ . '\\render_page'
	);
}

/**
 * Events that have RSVPs enabled, newest first, for the picker.
 *
 * @return \WP_Post[]
 */
function rsvp_events(): array {
	return get_posts(
		[
			'post_type'      => 'pkit_event',
			'post_status'    => [ 'publish', 'draft' ],
			'posts_per_page' => 100,
			'meta_key'       => '_pkit_em_rsvp_enabled',
			'meta_value'     => '1',
			'orderby'        => 'meta_value',
			'meta_query'     => [
				[
					'key'     => '_pkit_start_datetime',
					'compare' => 'EXISTS',
				],
			],
			'order'          => 'DESC',
		]
	);
}

function render_page(): void {
	if ( ! current_user_can( RSVP\manage_cap() ) ) {
		wp_die( esc_html__( 'You do not have permission to view RSVPs.', 'producerkit' ), 403 );
	}

	$events = rsvp_events();

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only event selector; nothing changes on this request.
	$event_id = isset( $_GET['event'] ) ? (int) $_GET['event'] : ( $events ? (int) $events[0]->ID : 0 );

	$rsvps   = $event_id > 0 ? RSVP\get_event_rsvps( $event_id ) : [];
	$summary = $event_id > 0 ? RSVP\get_event_rsvp_summary( $event_id ) : [];
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'RSVPs', 'producerkit' ); ?></h1>

		<?php if ( ! $events ) : ?>
			<div class="notice notice-info inline"><p>
				<?php esc_html_e( 'No events have RSVPs turned on yet. Edit an event and enable RSVPs to start taking bookings.', 'producerkit' ); ?>
			</p></div>
			</div>
			<?php
			return;
		endif;
		?>

		<form method="get" style="margin:1em 0;">
			<input type="hidden" name="page" value="<?php echo esc_attr( PAGE_SLUG ); ?>">
			<label for="pkit-event" class="screen-reader-text"><?php esc_html_e( 'Event', 'producerkit' ); ?></label>
			<select name="event" id="pkit-event">
				<?php foreach ( $events as $event ) : ?>
					<option value="<?php echo esc_attr( (string) $event->ID ); ?>" <?php selected( $event->ID, $event_id ); ?>>
						<?php echo esc_html( get_the_title( $event ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( __( 'Show', 'producerkit' ), 'secondary', '', false ); ?>

			<?php if ( $rsvps ) : ?>
				<a class="button"
					href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=pkit_export_rsvps&event=' . $event_id ), 'pkit_export_rsvps_' . $event_id ) ); ?>">
					<?php esc_html_e( 'Download CSV', 'producerkit' ); ?>
				</a>
			<?php endif; ?>
		</form>

		<?php if ( $summary ) : ?>
			<p>
				<strong><?php echo esc_html( (string) ( $summary['headcount'] ?? 0 ) ); ?></strong>
				<?php esc_html_e( 'people coming', 'producerkit' ); ?>
				<?php if ( null !== ( $summary['spots_left'] ?? null ) ) : ?>
					· <?php echo esc_html( (string) $summary['spots_left'] ); ?> <?php esc_html_e( 'spots left', 'producerkit' ); ?>
				<?php endif; ?>
				<?php if ( ! empty( $summary['is_full'] ) ) : ?>
					· <strong style="color:#d63638;"><?php esc_html_e( 'Full', 'producerkit' ); ?></strong>
				<?php endif; ?>
			</p>
		<?php endif; ?>

		<p id="pkit-rsvp-status" style="min-height:1.5em;color:#2271b1;"></p>

		<?php if ( ! $rsvps ) : ?>
			<div class="notice notice-info inline"><p>
				<?php esc_html_e( 'Nobody has RSVP\'d to this event yet.', 'producerkit' ); ?>
			</p></div>
		<?php else : ?>
			<table class="wp-list-table widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Name', 'producerkit' ); ?></th>
						<th scope="col" style="width:5em"><?php esc_html_e( 'Party', 'producerkit' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Email', 'producerkit' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Note', 'producerkit' ); ?></th>
						<th scope="col" style="width:11em"><?php esc_html_e( 'Booked', 'producerkit' ); ?></th>
						<th scope="col" style="width:7em"><?php esc_html_e( 'Actions', 'producerkit' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $rsvps as $rsvp ) : ?>
					<?php $row = (array) $rsvp; ?>
					<tr data-token="<?php echo esc_attr( (string) $row['token'] ); ?>">
						<td><strong><?php echo esc_html( (string) $row['name'] ); ?></strong></td>
						<td><?php echo esc_html( (string) (int) $row['party_size'] ); ?></td>
						<td>
							<?php if ( ! empty( $row['email'] ) ) : ?>
								<a href="mailto:<?php echo esc_attr( (string) $row['email'] ); ?>"><?php echo esc_html( (string) $row['email'] ); ?></a>
							<?php else : ?>
								<span style="color:#646970;">—</span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( (string) ( $row['note'] ?? '' ) ); ?></td>
						<td>
							<?php
							echo esc_html(
								wp_date( (string) get_option( 'date_format' ), (int) strtotime( (string) $row['created_at'] ) )
							);
							?>
						</td>
						<td>
							<button type="button" class="button-link pkit-cancel-rsvp" style="color:#b32d2e;">
								<?php esc_html_e( 'Cancel', 'producerkit' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
	render_script();
}

/**
 * Cancelling goes through the same REST route the guest's own link uses, so
 * the cap arithmetic and the pkit_rsvp_cancelled action fire once, in one
 * place, however the cancellation was made.
 */
function render_script(): void {
	$config = [
		'root'  => esc_url_raw( rest_url( 'producerkit/v1/rsvp' ) ),
		'nonce' => wp_create_nonce( 'wp_rest' ),
		'i18n'  => [
			'confirm' => __( 'Cancel this RSVP? Their place goes back into the count.', 'producerkit' ),
			'working' => __( 'Cancelling…', 'producerkit' ),
			'failed'  => __( 'Could not cancel that.', 'producerkit' ),
		],
	];
	?>
	<script>
	( function () {
		var cfg = <?php echo wp_json_encode( $config ); ?>;
		var out = document.getElementById( 'pkit-rsvp-status' );

		document.addEventListener( 'click', function ( e ) {
			if ( ! e.target.classList.contains( 'pkit-cancel-rsvp' ) ) { return; }

			var row = e.target.closest( '[data-token]' );
			if ( ! row || ! window.confirm( cfg.i18n.confirm ) ) { return; }

			out.textContent = cfg.i18n.working;

			fetch( cfg.root + '/' + encodeURIComponent( row.getAttribute( 'data-token' ) ), {
				method: 'DELETE',
				credentials: 'same-origin',
				headers: { 'X-WP-Nonce': cfg.nonce }
			} )
				.then( function ( r ) {
					if ( ! r.ok ) { throw new Error( 'failed' ); }
					window.location.reload();
				} )
				.catch( function () {
					out.textContent = cfg.i18n.failed;
					out.style.color = '#d63638';
				} );
		} );
	} )();
	</script>
	<?php
}

/**
 * The guest list as a CSV, because a headcount is for planning and a list of
 * names is for the door.
 */
function export_csv(): void {
	if ( ! current_user_can( RSVP\manage_cap() ) ) {
		wp_die( esc_html__( 'You do not have permission to export RSVPs.', 'producerkit' ), 403 );
	}

	$event_id = isset( $_GET['event'] ) ? (int) $_GET['event'] : 0;
	check_admin_referer( 'pkit_export_rsvps_' . $event_id );

	$rsvps = RSVP\get_event_rsvps( $event_id );
	$slug  = sanitize_title( get_the_title( $event_id ) ?: 'event' );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="rsvps-' . $slug . '-' . gmdate( 'Y-m-d' ) . '.csv"' );

	$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streaming CSV to the response; WP_Filesystem cannot write to php://output.
	fputcsv( $out, [ 'Name', 'Party size', 'Email', 'Note', 'Booked' ] );

	foreach ( $rsvps as $rsvp ) {
		$row = (array) $rsvp;

		fputcsv(
			$out,
			[
				RSVP\esc_csv_field( (string) $row['name'] ),
				(int) $row['party_size'],
				RSVP\esc_csv_field( (string) ( $row['email'] ?? '' ) ),
				RSVP\esc_csv_field( (string) ( $row['note'] ?? '' ) ),
				(string) $row['created_at'],
			]
		);
	}

	fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- see fopen above.
	exit;
}
