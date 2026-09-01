<?php
/**
 * Enqueue styles for single CPT detail views.
 */

declare(strict_types=1);

namespace ProducerKit\Core\SingleStyles;

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_head',
	function (): void {
		if ( ! is_singular( [ 'lfuf_product', 'lfuf_source', 'lfuf_location', 'lfuf_event' ] ) ) {
			return;
		}
		?>
	<style>
		.lfuf-single-details {
			margin-top: 2rem;
			padding-top: 1.5rem;
			border-top: 2px solid #e5e7eb;
		}

		.lfuf-single-details__row {
			display: flex;
			gap: 1rem;
			padding: 0.5rem 0;
			border-bottom: 1px solid #f3f4f6;
			font-size: 0.95rem;
		}

		.lfuf-single-details__row:last-child {
			border-bottom: none;
		}

		.lfuf-single-details__label {
			flex: 0 0 120px;
			font-weight: 600;
			color: #6b7280;
			font-size: 0.85rem;
			text-transform: uppercase;
			letter-spacing: 0.04em;
			padding-top: 0.1rem;
		}

		.lfuf-single-details__value {
			flex: 1;
		}

		.lfuf-single-details__value a {
			color: inherit;
			font-weight: 600;
			text-decoration: underline;
			text-decoration-color: #d1d5db;
			text-underline-offset: 2px;
		}

		.lfuf-single-details__value a:hover {
			text-decoration-color: currentColor;
		}

		.lfuf-single-details__value a:focus-visible {
			outline: 2px solid currentColor;
			outline-offset: 2px;
		}

		.lfuf-single-details__unit {
			font-weight: 400;
			opacity: 0.7;
			font-size: 0.85rem;
		}

		.lfuf-single-details__note {
			font-size: 0.85rem;
			color: #6b7280;
		}

		.lfuf-single-details__links {
			display: flex;
			flex-wrap: wrap;
			gap: 0.5rem;
		}

		.lfuf-single-details__alert {
			background: #fee2e2;
			color: #991b1b;
			padding: 0.75rem 1rem;
			border-radius: 0.375rem;
			font-weight: 600;
			margin-bottom: 1rem;
		}

		/* Badge styles (in case they're not loaded from block CSS) */
		.lfuf-single-details .lfuf-availability-badge {
			display: inline-block;
			font-size: 0.7rem;
			font-weight: 600;
			text-transform: uppercase;
			letter-spacing: 0.04em;
			padding: 0.2rem: 0.5rem;
			border-radius: 0.25rem;
		}

		.lfuf-single-details .lfuf-availability-badge--abundant   { background: #d1fae5; color: #065f46; }
		.lfuf-single-details .lfuf-availability-badge--available  { background: #dbeafe; color: #1e40af; }
		.lfuf-single-details .lfuf-availability-badge--limited    { background: #fef3c7; color: #92400e; }
		.lfuf-single-details .lfuf-availability-badge--sold_out   { background: #fee2e2; color: #991b1b; }
		.lfuf-single-details .lfuf-availability-badge--unavailable { background: #f3f4f6; color: #6b7280; }

		.lfuf-single-details .lfuf-location-info__status {
			display: inline-block;
			font-size: 0.7rem;
			font-weight: 700;
			text-transform: uppercase;
			letter-spacing: 0.05em;
			padding: 0.2rem 0.6rem;
			border-radius: 9999px;
		}

		.lfuf-single-details .lfuf-location-info__status--open  { background: #d1fae5; color: #065f46; }
		.lfuf-single-details .lfuf-location-info__status--closed { background: #fee2e2; color: #991b1b; }

		.lfuf-single-details .screen-reader-text {
			border: 0; clip: rect(1px, 1px, 1px, 1px); clip-path: inset(50%);
			height: 1px; margin: -1px; overflow: hidden; padding: 0;
			position: absolute; width: 1px; word-wrap: normal !important;
		}

		@media (max-width: 480px) {
			.lfuf-single-details__row {
				flex-direction: column;
				gap: 0.15rem;
			}
			.lfuf-single-details__label {
				flex: none;
			}
		}

		@media (forced-colors: active) {
			.lfuf-single-details__row { border-bottom-color: CanvasText; }
			.lfuf-single-details { border-top-color: CanvasText; }
			.lfuf-single-details__value a { color: LinkText; }
			.lfuf-single-details .lfuf-availability-badge,
			.lfuf-single-details .lfuf-location-info__status {
				forced-color-adjust: none;
				border: 1px solid CanvasText;
			}
		}
	</style>
		<?php
	}
);