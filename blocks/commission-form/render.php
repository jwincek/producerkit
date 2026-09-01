<?php
/**
 * Commission Request Form — server render.
 *
 * A plain server-rendered form with a small viewScript that POSTs to
 * lfuf/v1/commissions. Deliberately not on the Interactivity API, unlike the
 * pre-order form: that one has to keep a live basket of products and
 * quantities in sync, whereas this is one submission with no intermediate
 * state, and a form element plus fetch says so more clearly.
 *
 * The type and material selects are populated from the taxonomies the active
 * producer profile switched on, so a woodworker's customers pick a Wood
 * Species and a beekeeper's pick a Floral Source without any configuration.
 *
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( ! \ProducerKit\is_module_active( 'commissions' ) ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<p class="lfuf-commission-form__notice">'
			. esc_html__( 'The Commissions module is turned off, so this form is not shown to visitors.', 'producerkit' )
			. '</p>';
	}
	return;
}

$heading = (string) ( $attributes['heading'] ?? '' );
$intro   = (string) ( $attributes['intro'] ?? '' );
$budget  = ! empty( $attributes['showBudget'] );
$dead    = ! empty( $attributes['showDeadline'] );
$uid     = wp_unique_id( 'lfuf-commission-' );

/**
 * Terms for one of the profile-driven selects, or an empty list when the
 * active profile does not use that taxonomy.
 *
 * @return WP_Term[]
 */
$terms_for = static function ( string $taxonomy ): array {
	if ( ! taxonomy_exists( $taxonomy ) ) {
		return [];
	}

	$terms = get_terms(
		[
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'number'     => 200,
		]
	);

	return is_wp_error( $terms ) ? [] : $terms;
};

$types     = $terms_for( 'lfuf_product_type' );
$materials = $terms_for( 'lfuf_material' );

$material_label = $materials && taxonomy_exists( 'lfuf_material' )
	? get_taxonomy( 'lfuf_material' )->labels->singular_name
	: '';

$wrapper = get_block_wrapper_attributes( [ 'class' => 'lfuf-commission-form' ] );
?>
<div <?php echo wp_kses_data( $wrapper ); ?>
	data-lfuf-commission-form
	data-endpoint="<?php echo esc_url( rest_url( 'lfuf/v1/commissions' ) ); ?>">

	<?php if ( '' !== $heading ) : ?>
		<h2 class="lfuf-commission-form__heading"><?php echo esc_html( $heading ); ?></h2>
	<?php endif; ?>

	<?php if ( '' !== $intro ) : ?>
		<p class="lfuf-commission-form__intro"><?php echo esc_html( $intro ); ?></p>
	<?php endif; ?>

	<form class="lfuf-commission-form__form" novalidate>
		<p class="lfuf-commission-form__field">
			<label for="<?php echo esc_attr( $uid ); ?>-name"><?php esc_html_e( 'Your name', 'producerkit' ); ?> <span aria-hidden="true">*</span></label>
			<input type="text" id="<?php echo esc_attr( $uid ); ?>-name" name="name" required autocomplete="name">
		</p>

		<p class="lfuf-commission-form__field">
			<label for="<?php echo esc_attr( $uid ); ?>-email"><?php esc_html_e( 'Email', 'producerkit' ); ?> <span aria-hidden="true">*</span></label>
			<input type="email" id="<?php echo esc_attr( $uid ); ?>-email" name="email" required autocomplete="email">
			<span class="lfuf-commission-form__hint"><?php esc_html_e( 'We send your quote here.', 'producerkit' ); ?></span>
		</p>

		<p class="lfuf-commission-form__field">
			<label for="<?php echo esc_attr( $uid ); ?>-phone"><?php esc_html_e( 'Phone (optional)', 'producerkit' ); ?></label>
			<input type="tel" id="<?php echo esc_attr( $uid ); ?>-phone" name="phone" autocomplete="tel">
		</p>

		<?php if ( $types ) : ?>
			<p class="lfuf-commission-form__field">
				<label for="<?php echo esc_attr( $uid ); ?>-type">
					<?php echo esc_html( get_taxonomy( 'lfuf_product_type' )->labels->singular_name ); ?>
				</label>
				<select id="<?php echo esc_attr( $uid ); ?>-type" name="product_type">
					<option value=""><?php esc_html_e( '— No preference —', 'producerkit' ); ?></option>
					<?php foreach ( $types as $term ) : ?>
						<option value="<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
		<?php endif; ?>

		<?php if ( $materials ) : ?>
			<p class="lfuf-commission-form__field">
				<label for="<?php echo esc_attr( $uid ); ?>-material"><?php echo esc_html( $material_label ); ?></label>
				<select id="<?php echo esc_attr( $uid ); ?>-material" name="material">
					<option value=""><?php esc_html_e( '— No preference —', 'producerkit' ); ?></option>
					<?php foreach ( $materials as $term ) : ?>
						<option value="<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
		<?php endif; ?>

		<p class="lfuf-commission-form__field">
			<label for="<?php echo esc_attr( $uid ); ?>-description"><?php esc_html_e( 'What would you like made?', 'producerkit' ); ?> <span aria-hidden="true">*</span></label>
			<textarea id="<?php echo esc_attr( $uid ); ?>-description" name="description" rows="5" required
				placeholder="<?php esc_attr_e( 'Size, colours, how it will be used, anything it has to match…', 'producerkit' ); ?>"></textarea>
		</p>

		<?php if ( $budget ) : ?>
			<p class="lfuf-commission-form__field">
				<label for="<?php echo esc_attr( $uid ); ?>-budget"><?php esc_html_e( 'Budget', 'producerkit' ); ?></label>
				<select id="<?php echo esc_attr( $uid ); ?>-budget" name="budget_range">
					<option value=""><?php esc_html_e( '— Prefer not to say —', 'producerkit' ); ?></option>
					<?php foreach ( \ProducerKit\Commissions\Store\budget_ranges() as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
		<?php endif; ?>

		<?php if ( $dead ) : ?>
			<p class="lfuf-commission-form__field">
				<label for="<?php echo esc_attr( $uid ); ?>-deadline"><?php esc_html_e( 'Needed by (optional)', 'producerkit' ); ?></label>
				<input type="date" id="<?php echo esc_attr( $uid ); ?>-deadline" name="deadline"
					min="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>">
			</p>
		<?php endif; ?>

		<?php
		/*
		 * Honeypot. Hidden from people with CSS and from screen readers with
		 * aria-hidden + tabindex, so only a bot filling every input trips it.
		 */
		?>
		<p class="lfuf-commission-form__hp" aria-hidden="true">
			<label for="<?php echo esc_attr( $uid ); ?>-website"><?php esc_html_e( 'Website', 'producerkit' ); ?></label>
			<input type="text" id="<?php echo esc_attr( $uid ); ?>-website" name="website" tabindex="-1" autocomplete="off">
		</p>

		<?php
		/*
		 * Onsite Spam Guard adds its own signed token and timing fields when
		 * it is installed. Absent, the honeypot and the server-side rate
		 * limiter still apply.
		 */
		if ( function_exists( 'simple_spam_shield_field_markup' ) ) {
			// Returns markup rather than echoing, and is the plugin's own
			// trusted output.
			echo simple_spam_shield_field_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		?>

		<p class="lfuf-commission-form__actions">
			<button type="submit" class="lfuf-commission-form__submit wp-element-button">
				<?php esc_html_e( 'Send request', 'producerkit' ); ?>
			</button>
		</p>

		<p class="lfuf-commission-form__message" role="status" aria-live="polite"></p>
	</form>
</div>
