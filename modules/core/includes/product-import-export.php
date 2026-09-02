<?php
/**
 * Product CSV Import / Export.
 *
 * Export: downloads a CSV of all published products with all fields.
 * Import: uploads a CSV, previews the data, and creates/updates products.
 *
 * CSV columns:
 *   title, excerpt, price, unit, growing_notes, product_types, seasons, sources, featured_image_url
 *
 * - product_types and seasons are pipe-separated: "Produce|Bread"
 * - sources are pipe-separated source post titles (matched by name)
 * - featured_image_url is optional; if provided, the image is sideloaded
 * - If a product with the same title already exists, it is updated
 */

declare(strict_types=1);

namespace ProducerKit\Core\ProductIO;

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', __NAMESPACE__ . '\\register_page' );
add_action( 'admin_init', __NAMESPACE__ . '\\handle_export' );
add_action( 'admin_init', __NAMESPACE__ . '\\handle_import' );

function register_page(): void {
	add_submenu_page(
		'producerkit',
		__( 'Import / Export Products', 'producerkit' ),
		__( 'Product Import', 'producerkit' ),
		'edit_posts',
		'producerkit-product-io',
		__NAMESPACE__ . '\\render_page',
	);
}

/* ───────────────────────────────────────────────
 * Export
 * ─────────────────────────────────────────────── */

function handle_export(): void {
	$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

	if (
		! isset( $_GET['pkit_export_products'] )
		|| ! wp_verify_nonce( $nonce, 'pkit_export_products' )
		|| ! current_user_can( 'edit_posts' )
	) {
		return;
	}

	$products = get_posts(
		[
			'post_type'      => 'pkit_product',
			'post_status'    => [ 'publish', 'draft' ],
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		]
	);

	$filename = 'producerkit-products-' . gmdate( 'Y-m-d' ) . '.csv';

	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	header( 'Pragma: no-cache' );

	$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streaming CSV to the response; WP_Filesystem cannot write to php://output.

	// BOM for Excel UTF-8 compatibility.
	fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- see fopen above.

	// Header row.
	fputcsv(
		$out,
		[
			'title',
			'status',
			'excerpt',
			'price',
			'unit',
			'growing_notes',
			'product_types',
			'seasons',
			'sources',
			'featured_image_url',
		]
	);

	foreach ( $products as $product ) {
		$pid = $product->ID;

		// Taxonomies.
		$types      = get_the_terms( $pid, 'pkit_product_type' );
		$seasons    = get_the_terms( $pid, 'pkit_season' );
		$type_str   = ( $types && ! is_wp_error( $types ) )
			? implode( '|', wp_list_pluck( $types, 'name' ) )
			: '';
		$season_str = ( $seasons && ! is_wp_error( $seasons ) )
			? implode( '|', wp_list_pluck( $seasons, 'name' ) )
			: '';

		// Sources.
		$source_ids   = get_post_meta( $pid, '_pkit_source_ids', true );
		$source_names = [];
		if ( is_array( $source_ids ) ) {
			foreach ( $source_ids as $sid ) {
				$source = get_post( $sid );
				if ( $source ) {
					$source_names[] = $source->post_title;
				}
			}
		}

		// Featured image.
		$thumb_id  = get_post_thumbnail_id( $pid );
		$thumb_url = $thumb_id ? wp_get_attachment_url( $thumb_id ) : '';

		fputcsv(
			$out,
			[
				$product->post_title,
				$product->post_status,
				$product->post_excerpt,
				get_post_meta( $pid, '_pkit_price', true ),
				get_post_meta( $pid, '_pkit_unit', true ),
				get_post_meta( $pid, '_pkit_growing_notes', true ),
				$type_str,
				$season_str,
				implode( '|', $source_names ),
				$thumb_url,
			]
		);
	}

	fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- see fopen above.
	exit;
}

/* ───────────────────────────────────────────────
 * Import
 * ─────────────────────────────────────────────── */

function handle_import(): void {
	$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

	if (
		! isset( $_POST['pkit_import_products'] )
		|| ! wp_verify_nonce( $nonce, 'pkit_import_products' )
		|| ! current_user_can( 'edit_posts' )
	) {
		return;
	}

	// Read every index defensively: a request can omit the file part
	// altogether, in which case PHP populates none of these keys.
	$upload = isset( $_FILES['pkit_csv'] )
		? array_map( 'sanitize_text_field', wp_unslash( (array) $_FILES['pkit_csv'] ) )
		: [];
	$tmp    = (string) ( $upload['tmp_name'] ?? '' );
	$error  = isset( $upload['error'] ) ? (int) $upload['error'] : UPLOAD_ERR_NO_FILE;

	// is_uploaded_file() is what stops a crafted tmp_name from pointing the
	// parser at an arbitrary path on disk.
	if ( '' === $tmp || UPLOAD_ERR_OK !== $error || ! is_uploaded_file( $tmp ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'No file uploaded or upload error.', 'producerkit' ) . '</p></div>';
			}
		);
		return;
	}

	$rows = parse_csv( $tmp );

	if ( empty( $rows ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'CSV file is empty or could not be parsed.', 'producerkit' ) . '</p></div>';
			}
		);
		return;
	}

	$results = import_rows( $rows );

	// Store results in a transient for display.
	set_transient( 'pkit_import_results', $results, 60 );
}

/**
 * Parse a CSV file into an array of associative arrays.
 *
 * @return array<array<string,string>>
 */
function parse_csv( string $filepath ): array {
	$handle = fopen( $filepath, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streaming row-by-row CSV parse of an uploaded file via fgetcsv(); WP_Filesystem has no CSV reader.
	if ( ! $handle ) {
		return [];
	}

	// Skip BOM if present.
	$bom = fread( $handle, 3 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- see fopen above.
	if ( $bom !== "\xEF\xBB\xBF" ) {
		rewind( $handle );
	}

	$headers = fgetcsv( $handle );
	if ( ! $headers ) {
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- see fopen above.
		return [];
	}

	// Normalize headers.
	$headers = array_map( fn ( $h ) => strtolower( trim( $h ) ), $headers );

	$rows = [];
	while ( ( $data = fgetcsv( $handle ) ) !== false ) {
		if ( count( $data ) !== count( $headers ) ) {
			continue; // Skip malformed rows.
		}
		$row = array_combine( $headers, $data );
		if ( $row && ! empty( trim( $row['title'] ?? '' ) ) ) {
			$rows[] = $row;
		}
	}

	fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- see fopen above.
	return $rows;
}

/**
 * Import parsed CSV rows as products.
 *
 * @param array<array<string,string>> $rows
 * @return array{created:int,updated:int,errors:string[]}
 */
function import_rows( array $rows ): array {
	$created = 0;
	$updated = 0;
	$errors  = [];

	// Pre-fetch source posts for matching by title.
	$sources    = get_posts(
		[
			'post_type'      => 'pkit_source',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		]
	);
	$source_map = [];
	foreach ( $sources as $s ) {
		$source_map[ strtolower( trim( $s->post_title ) ) ] = $s->ID;
	}

	foreach ( $rows as $i => $row ) {
		$line  = $i + 2; // 1-indexed, +1 for header row.
		$title = sanitize_text_field( trim( $row['title'] ?? '' ) );

		if ( empty( $title ) ) {
			/* translators: %d: CSV row number. */
			$errors[] = sprintf( __( 'Row %d: missing title, skipped.', 'producerkit' ), $line );
			continue;
		}

		// Check if product already exists by title.
		// (get_page_by_title() is deprecated since WP 6.2.)
		$title_query = new \WP_Query(
			[
				'post_type'              => 'pkit_product',
				'title'                  => $title,
				'post_status'            => 'all',
				'posts_per_page'         => 1,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
				'orderby'                => 'post_date ID',
				'order'                  => 'ASC',
			]
		);
		$existing    = $title_query->posts[0] ?? null;
		$post_status = sanitize_text_field( $row['status'] ?? 'publish' );
		if ( ! in_array( $post_status, [ 'publish', 'draft', 'pending' ], true ) ) {
			$post_status = 'publish';
		}

		$post_data = [
			'post_title'   => $title,
			'post_type'    => 'pkit_product',
			'post_status'  => $post_status,
			'post_excerpt' => sanitize_textarea_field( $row['excerpt'] ?? '' ),
		];

		if ( $existing ) {
			$post_data['ID'] = $existing->ID;
			$pid             = wp_update_post( $post_data, true );
			if ( is_wp_error( $pid ) ) {
				/* translators: %1$d: CSV row number, %2$s: product title, %3$s: error message. */
				$errors[] = sprintf( __( 'Row %1$d: failed to update "%2$s" — %3$s', 'producerkit' ), $line, $title, $pid->get_error_message() );
				continue;
			}
			++$updated;
		} else {
			$pid = wp_insert_post( $post_data, true );
			if ( is_wp_error( $pid ) ) {
				/* translators: %1$d: CSV row number, %2$s: product title, %3$s: error message. */
				$errors[] = sprintf( __( 'Row %1$d: failed to create "%2$s" — %3$s', 'producerkit' ), $line, $title, $pid->get_error_message() );
				continue;
			}
			++$created;
		}

		// Meta fields.
		if ( isset( $row['price'] ) ) {
			update_post_meta( $pid, '_pkit_price', sanitize_text_field( $row['price'] ) );
		}
		if ( isset( $row['unit'] ) ) {
			update_post_meta( $pid, '_pkit_unit', sanitize_text_field( $row['unit'] ) );
		}
		if ( isset( $row['growing_notes'] ) ) {
			update_post_meta( $pid, '_pkit_growing_notes', sanitize_text_field( $row['growing_notes'] ) );
		}

		// Taxonomies (pipe-separated).
		if ( ! empty( $row['product_types'] ) ) {
			$terms = array_map( 'trim', explode( '|', $row['product_types'] ) );
			wp_set_object_terms( $pid, $terms, 'pkit_product_type' );
		}
		if ( ! empty( $row['seasons'] ) ) {
			$terms = array_map( 'trim', explode( '|', $row['seasons'] ) );
			wp_set_object_terms( $pid, $terms, 'pkit_season' );
		}

		// Source links (pipe-separated titles).
		if ( ! empty( $row['sources'] ) ) {
			$names = array_map( 'trim', explode( '|', $row['sources'] ) );
			$ids   = [];
			foreach ( $names as $name ) {
				$key = strtolower( $name );
				if ( isset( $source_map[ $key ] ) ) {
					$ids[] = $source_map[ $key ];
				}
			}
			update_post_meta( $pid, '_pkit_source_ids', $ids );
		}

		// Featured image (sideload from URL).
		if ( ! empty( $row['featured_image_url'] ) ) {
			$image_url = esc_url_raw( trim( $row['featured_image_url'] ) );
			if ( filter_var( $image_url, FILTER_VALIDATE_URL ) ) {
				$thumb_id = get_post_thumbnail_id( $pid );
				// Only sideload if no thumbnail or URL changed.
				$current_url = $thumb_id ? wp_get_attachment_url( $thumb_id ) : '';
				if ( $image_url !== $current_url ) {
					require_once ABSPATH . 'wp-admin/includes/media.php';
					require_once ABSPATH . 'wp-admin/includes/file.php';
					require_once ABSPATH . 'wp-admin/includes/image.php';
					$new_id = media_sideload_image( $image_url, $pid, $title, 'id' );
					if ( ! is_wp_error( $new_id ) ) {
						set_post_thumbnail( $pid, $new_id );
					}
				}
			}
		}
	}

	return compact( 'created', 'updated', 'errors' );
}

/* ───────────────────────────────────────────────
 * Render
 * ─────────────────────────────────────────────── */

function render_page(): void {
	$results = get_transient( 'pkit_import_results' );
	if ( $results ) {
		delete_transient( 'pkit_import_results' );
	}

	$product_count = (int) wp_count_posts( 'pkit_product' )->publish;
	?>
	<div class="wrap pkit-product-io">
		<h1><?php esc_html_e( 'Product Import / Export', 'producerkit' ); ?></h1>

		<?php if ( $results ) : ?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					$parts = [];
					if ( $results['created'] > 0 ) {
						/* translators: %d: number of products created. */
						$parts[] = sprintf( _n( '%d product created', '%d products created', $results['created'], 'producerkit' ), $results['created'] );
					}
					if ( $results['updated'] > 0 ) {
						/* translators: %d: number of products updated. */
						$parts[] = sprintf( _n( '%d product updated', '%d products updated', $results['updated'], 'producerkit' ), $results['updated'] );
					}
					echo esc_html( implode( ', ', $parts ) ?: __( 'No changes made.', 'producerkit' ) );
					?>
				</p>
			</div>
			<?php if ( ! empty( $results['errors'] ) ) : ?>
				<div class="notice notice-warning">
					<p><strong><?php esc_html_e( 'Some rows had issues:', 'producerkit' ); ?></strong></p>
					<ul style="list-style:disc;padding-left:20px;">
						<?php foreach ( $results['errors'] as $error ) : ?>
							<li><?php echo esc_html( $error ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
		<?php endif; ?>

		<div class="pkit-product-io__panels">
			<!-- ── Export ── -->
			<div class="pkit-product-io__panel">
				<h2><?php esc_html_e( 'Export', 'producerkit' ); ?></h2>
				<p>
				<?php
				printf(
					/* translators: %d: number of published products. */
					esc_html__( 'Download all %d products as a CSV file. Use this as a backup or as a template for bulk edits.', 'producerkit' ),
					(int) $product_count,
				);
				?>
				</p>
				<a
					href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=producerkit-product-io&pkit_export_products=1' ), 'pkit_export_products' ) ); ?>"
					class="button button-primary"
					<?php disabled( $product_count, 0 ); ?>
				>
					<?php esc_html_e( 'Download CSV', 'producerkit' ); ?>
				</a>
			</div>

			<!-- ── Import ── -->
			<div class="pkit-product-io__panel">
				<h2><?php esc_html_e( 'Import', 'producerkit' ); ?></h2>
				<p><?php esc_html_e( 'Upload a CSV to create or update products in bulk. Products are matched by title — if a product with the same name exists, it will be updated.', 'producerkit' ); ?></p>

				<form method="post" enctype="multipart/form-data">
					<?php wp_nonce_field( 'pkit_import_products' ); ?>
					<input type="hidden" name="pkit_import_products" value="1">

					<p>
						<input type="file" name="pkit_csv" accept=".csv,text/csv" required>
					</p>

					<p>
						<button type="submit" class="button button-primary">
							<?php esc_html_e( 'Upload & Import', 'producerkit' ); ?>
						</button>
					</p>
				</form>

				<details style="margin-top:16px;">
					<summary style="cursor:pointer;font-weight:600;font-size:13px;">
						<?php esc_html_e( 'CSV format reference', 'producerkit' ); ?>
					</summary>
					<div style="margin-top:8px;padding:12px;background:#f9fafb;border-radius:4px;font-size:13px;">
						<p><?php esc_html_e( 'The CSV should have these columns (in any order):', 'producerkit' ); ?></p>
						<table class="widefat" style="max-width:600px;">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Column', 'producerkit' ); ?></th>
									<th><?php esc_html_e( 'Required', 'producerkit' ); ?></th>
									<th><?php esc_html_e( 'Example', 'producerkit' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<tr><td><code>title</code></td><td><?php esc_html_e( 'Yes', 'producerkit' ); ?></td><td>Arugula</td></tr>
								<tr><td><code>status</code></td><td><?php esc_html_e( 'No', 'producerkit' ); ?></td><td>publish</td></tr>
								<tr><td><code>excerpt</code></td><td><?php esc_html_e( 'No', 'producerkit' ); ?></td><td>Peppery and fresh</td></tr>
								<tr><td><code>price</code></td><td><?php esc_html_e( 'No', 'producerkit' ); ?></td><td>$4</td></tr>
								<tr><td><code>unit</code></td><td><?php esc_html_e( 'No', 'producerkit' ); ?></td><td>bunch</td></tr>
								<tr><td><code>growing_notes</code></td><td><?php esc_html_e( 'No', 'producerkit' ); ?></td><td>No-till, heirloom variety</td></tr>
								<tr><td><code>product_types</code></td><td><?php esc_html_e( 'No', 'producerkit' ); ?></td><td>Produce</td></tr>
								<tr><td><code>seasons</code></td><td><?php esc_html_e( 'No', 'producerkit' ); ?></td><td>Spring|Fall</td></tr>
								<tr><td><code>sources</code></td><td><?php esc_html_e( 'No', 'producerkit' ); ?></td><td>Anson Mills|Boulted Bread</td></tr>
								<tr><td><code>featured_image_url</code></td><td><?php esc_html_e( 'No', 'producerkit' ); ?></td><td>https://example.com/arugula.jpg</td></tr>
							</tbody>
						</table>
						<p style="margin-top:8px;">
							<?php esc_html_e( 'Use pipes (|) to separate multiple values in product_types, seasons, and sources.', 'producerkit' ); ?>
						</p>
						<p><?php esc_html_e( 'Tip: export your existing products first to see the format, then edit the CSV and re-import.', 'producerkit' ); ?></p>
					</div>
				</details>
			</div>
		</div>
	</div>

	<style>
		.pkit-product-io__panels {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
			gap: 1.5rem;
			margin-top: 1rem;
		}
		.pkit-product-io__panel {
			background: #fff;
			border: 1px solid #dcdcde;
			border-radius: 0.375rem;
			padding: 1.25rem 1.5rem;
		}
		.pkit-product-io__panel h2 {
			margin-top: 0;
			font-size: 1.1rem;
		}
	</style>
	<?php
}