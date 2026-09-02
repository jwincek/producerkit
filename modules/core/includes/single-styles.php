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
		if ( ! is_singular( [ 'pkit_product', 'pkit_source', 'pkit_location', 'pkit_event' ] ) ) {
			return;
		}
		?>
	<style>
		.pkit-single-details {
			margin-top: 2rem;
			padding-top: 1.5rem;
			border-top: 2px solid #e5e7eb;
		}

		.pkit-single-details__row {
			display: flex;
			gap: 1rem;
			padding: 0.5rem 0;
			border-bottom: 1px solid #f3f4f6;
			font-size: 0.95rem;
		}

		.pkit-single-details__row:last-child {
			border-bottom: none;
		}

		.pkit-single-details__label {
			flex: 0 0 120px;
			font-weight: 600;
			color: #6b7280;
			font-size: 0.85rem;
			text-transform: uppercase;
			letter-spacing: 0.04em;
			padding-top: 0.1rem;
		}

		.pkit-single-details__value {
			flex: 1;
		}

		.pkit-single-details__value a {
			color: inherit;
			font-weight: 600;
			text-decoration: underline;
			text-decoration-color: #d1d5db;
			text-underline-offset: 2px;
		}

		.pkit-single-details__value a:hover {
			text-decoration-color: currentColor;
		}

		.pkit-single-details__value a:focus-visible {
			outline: 2px solid currentColor;
			outline-offset: 2px;
		}

		.pkit-single-details__unit {
			font-weight: 400;
			opacity: 0.7;
			font-size: 0.85rem;
		}

		.pkit-single-details__note {
			font-size: 0.85rem;
			color: #6b7280;
		}

		.pkit-single-details__links {
			display: flex;
			flex-wrap: wrap;
			gap: 0.5rem;
		}

		.pkit-single-details__alert {
			background: #fee2e2;
			color: #991b1b;
			padding: 0.75rem 1rem;
			border-radius: 0.375rem;
			font-weight: 600;
			margin-bottom: 1rem;
		}

		/* Badge styles (in case they're not loaded from block CSS) */
		.pkit-single-details .pkit-availability-badge {
			display: inline-block;
			font-size: 0.7rem;
			font-weight: 600;
			text-transform: uppercase;
			letter-spacing: 0.04em;
			padding: 0.2rem: 0.5rem;
			border-radius: 0.25rem;
		}

		.pkit-single-details .pkit-availability-badge--abundant   { background: #d1fae5; color: #065f46; }
		.pkit-single-details .pkit-availability-badge--available  { background: #dbeafe; color: #1e40af; }
		.pkit-single-details .pkit-availability-badge--limited    { background: #fef3c7; color: #92400e; }
		.pkit-single-details .pkit-availability-badge--sold_out   { background: #fee2e2; color: #991b1b; }
		.pkit-single-details .pkit-availability-badge--unavailable { background: #f3f4f6; color: #6b7280; }

		.pkit-single-details .pkit-location-info__status {
			display: inline-block;
			font-size: 0.7rem;
			font-weight: 700;
			text-transform: uppercase;
			letter-spacing: 0.05em;
			padding: 0.2rem 0.6rem;
			border-radius: 9999px;
		}

		.pkit-single-details .pkit-location-info__status--open  { background: #d1fae5; color: #065f46; }
		.pkit-single-details .pkit-location-info__status--closed { background: #fee2e2; color: #991b1b; }

		.pkit-single-details .screen-reader-text {
			border: 0; clip: rect(1px, 1px, 1px, 1px); clip-path: inset(50%);
			height: 1px; margin: -1px; overflow: hidden; padding: 0;
			position: absolute; width: 1px; word-wrap: normal !important;
		}

		@media (max-width: 480px) {
			.pkit-single-details__row {
				flex-direction: column;
				gap: 0.15rem;
			}
			.pkit-single-details__label {
				flex: none;
			}
		}

		@media (forced-colors: active) {
			.pkit-single-details__row { border-bottom-color: CanvasText; }
			.pkit-single-details { border-top-color: CanvasText; }
			.pkit-single-details__value a { color: LinkText; }
			.pkit-single-details .pkit-availability-badge,
			.pkit-single-details .pkit-location-info__status {
				forced-color-adjust: none;
				border: 1px solid CanvasText;
			}
		}
	</style>
		<?php
	}
);