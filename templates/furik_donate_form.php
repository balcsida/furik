<?php

/**
 * Furik Donate Form template
 *
 * This template can be overriden by copying this file to your-theme/furik/furik_donate_form.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $furik_recaptcha_enabled, $furik_recaptcha_site_key;

?><script type="text/javascript">
	function toggle_data_transmission() {
		var monthly = document.getElementById("furik_form_recurring_1");
		var method = document.getElementById("furik_form_type_0");
		if (monthly.checked && method.checked) {
			document.getElementById("furik_form_accept_reg_div").style.display="block";
			document.getElementById("furik_form_submit_button").value="<?php echo __( 'Donation with card registration', 'furik' ); ?>";
			document.getElementById("furik_form_accept_reg").required=true
		}
		else {
			document.getElementById("furik_form_accept_reg_div").style.display="none";
			document.getElementById("furik_form_submit_button").value="<?php echo __( 'Send', 'furik' ); ?>";
			document.getElementById("furik_form_accept_reg").required=false
		}
	}
</script>

<?php if ( $furik_recaptcha_enabled && $furik_recaptcha_site_key ) : ?>
<script src="https://www.google.com/recaptcha/api.js?render=<?php echo esc_attr( $furik_recaptcha_site_key ); ?>"></script>
<script>
	// Execute reCAPTCHA when form is submitted
	document.addEventListener('DOMContentLoaded', function() {
		var form = document.querySelector('form[action="<?php echo $_SERVER['REQUEST_URI']; ?>"]');
		if (form) {
			form.addEventListener('submit', function(e) {
				<?php if ( $furik_recaptcha_enabled && $furik_recaptcha_site_key ) : ?>
				e.preventDefault();
				
				// Show loading state on submit button
				var submitBtn = document.getElementById('furik_form_submit_button');
				var originalText = submitBtn.value;
				submitBtn.value = '<?php echo esc_js( __( 'Verifying...', 'furik' ) ); ?>';
				submitBtn.disabled = true;
				
				grecaptcha.ready(function() {
					grecaptcha.execute('<?php echo esc_js( $furik_recaptcha_site_key ); ?>', {action: 'submit_donation'}).then(function(token) {
						// Add token to form
						var tokenInput = document.getElementById('furik_recaptcha_token');
						if (!tokenInput) {
							tokenInput = document.createElement('input');
							tokenInput.type = 'hidden';
							tokenInput.id = 'furik_recaptcha_token';
							tokenInput.name = 'furik_recaptcha_token';
							form.appendChild(tokenInput);
						}
						tokenInput.value = token;
						
						// Submit the form
						submitBtn.value = originalText;
						submitBtn.disabled = false;
						form.submit();
					}).catch(function(error) {
						// Handle error
						submitBtn.value = originalText;
						submitBtn.disabled = false;
						alert('<?php echo esc_js( __( 'reCAPTCHA verification failed. Please try again.', 'furik' ) ); ?>');
					});
				});
				<?php endif; ?>
			});
		}
	});
</script>
<?php endif; ?>

<form method="POST" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
	<input type="hidden" name="furik_action" value="process_payment_form"/>
	<input type="hidden" name="furik_campaign" value="<?php echo $args['campaign_id']; ?>"/>

	<?php if ( ! furik_extra_field_enabled( 'name_separation' ) ) : ?>
		<div class="form-field form-group form-required">
			<label for="furik_form_name"><?php echo __( 'Your name', 'furik' ); ?>:</label>
			<input type="text" name="furik_form_name" id="furik_form_name" class="form-control" required="1"/>
		</div>
	<?php else : ?>
		<?php if ( ! $args['furik_name_order_eastern'] ) : ?>
			<div class="form-field form-group form-required">
				<label for="furik_form_name"><?php echo __( 'Your first name', 'furik' ); ?>:</label>
				<input type="text" name="furik_form_first_name" id="furik_form_first_name" class="form-control" required="1"/>
			</div>
			<div class="form-field form-group form-required">
				<label for="furik_form_name"><?php echo __( 'Your last name', 'furik' ); ?>:</label>
				<input type="text" name="furik_form_last_name" id="furik_form_last_name" class="form-control" required="1"/>
			</div>
		<?php else : ?>
			<div class="form-field form-group form-required">
				<label for="furik_form_name"><?php echo __( 'Your last name', 'furik' ); ?>:</label>
				<input type="text" name="furik_form_last_name" id="furik_form_last_name" class="form-control" required="1"/>
			</div>
			<div class="form-field form-group form-required">
				<label for="furik_form_name"><?php echo __( 'Your first name', 'furik' ); ?>:</label>
				<input type="text" name="furik_form_first_name" id="furik_form_first_name" class="form-control" required="1"/>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( ! isset( $args['meta']['ANON_DISABLED'] ) ) : ?>
		<div class="form-field form-check">
			<label for="furik_form_anon" class="form-check-label">
				<input type="checkbox" class="form-check-input" name="furik_form_anon" id="furik_form_anon"><?php echo __( 'Anonymous donation', 'furik' ); ?>
			</label>
		</div>
	<?php endif; ?>

	<div class="form-field form-group form-required">
		<label for="furik_form_email"><?php echo __( 'E-mail address', 'furik' ); ?>:</label>
		<input type="email" class="form-control" name="furik_form_email" id="furik_form_email" required="1"/>
	</div>

	<?php if ( furik_extra_field_enabled( 'phone_number' ) ) : ?>
		<div class="form-field form-group">
			<label for="furik_form_phone_number"><?php echo __( 'Phone number', 'furik' ); ?>:</label>
			<input type="tel" class="form-control" name="furik_form_phone_number" id="furik_form_phone_number" />
		</div>
	<?php endif; ?>

	<?php if ( ! $args['a']['skip_message'] ) : ?>
		<div class="form-field form-group">
			<label for="furik_form_message"><?php echo __( 'Message', 'furik' ); ?>:</label>
			<textarea class="form-control" name="furik_form_message" id="furik_form_message"></textarea>
		</div>
	<?php endif; ?>

	<hr/>

	<?php if ( isset( $args['amount_content'] ) && $args['amount_content'] ) : ?>
		<?php echo $args['amount_content']; ?>
	<?php else : ?>
		<div class="form-field form-group form-required">
			<label for="furik_form_amount"><?php echo __( 'Donation amount', 'furik' ); ?> (Forint):</label>
			<input type="number" class="form-control" name="furik_form_amount" id="furik_form_amount" value="<?php echo $args['amount']; ?>" required="1"/>
		</div>
	<?php endif; ?>


	<?php if ( $args['a']['enable_monthly'] ) : ?>
		<hr/>
		<div class="form-field form-group furik-payment-recurring">
			<div>
				<div class="form-check form-check-inline">
					<input type="radio" id="furik_form_recurring_0" class="form-check-input" name="furik_form_recurring" value="0" checked="1" onChange="toggle_data_transmission()" />
					<label for="furik_form_recurring_0" class="form-check-label"><?php echo __( 'One time donation', 'furik' ); ?></label>
				</div>

				<div class="form-check form-check-inline">
					<input type="radio" id="furik_form_recurring_1" class="form-check-input" name="furik_form_recurring" value="1" onChange="toggle_data_transmission()" />
					<label for="furik_form_recurring_1" class="form-check-label">
						<?php echo __( 'Recurring donation', 'furik' ); ?> <a href="<?php echo furik_url( $args['furik_monthly_explanation_url'] ); ?>" target="_blank"><?php echo __( "What's this?", 'furik' ); ?></a>
					</label>
				</div>

			</div>
		</div>
	<?php endif; ?>

	<hr/>
	<div class="form-field form-group furik-payment-method">
		<div>
			<div class="form-check form-check-inline">
				<input type="radio" id="furik_form_type_0" class="form-check-input" name="furik_form_type" value="0" checked="1" onChange="toggle_data_transmission()" />
				<label for="furik_form_type_0" class="form-check-label"><?php echo __( 'Online payment', 'furik' ); ?></label>
			</div>

			<div class="form-check form-check-inline">
				<input type="radio" id="furik_form_type_1" class="form-check-input" name="furik_form_type" value="1" onChange="toggle_data_transmission()" />
				<label for="furik_form_type_1" class="form-check-label"><?php echo __( 'Bank transfer', 'furik' ); ?></label>
			</div>

			<?php if ( $args['a']['enable_cash'] ) : ?>
				<div class="form-check form-check-inline">
					<input type="radio" id="furik_form_type_2" class="form-check-input" name="furik_form_type" value="2" onChange="toggle_data_transmission()" />
					<label for="furik_form_type_2" class="form-check-label"><?php echo __( 'Cash donation', 'furik' ); ?></label>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<hr/>

	<div class="form-field form-check furik-form-accept">
		<label for="furik_form_accept" class="form-check-label">
			<input type="checkbox" name="furik_form_accept" id="furik_form_accept" class="form-check-input" required="1">
			<a href="<?php echo furik_url( $args['furik_data_transmission_declaration_url'] ); ?>" target="_blank"><?php echo __( 'I accept the data transmission declaration', 'furik' ); ?></a>
		</label>
	</div>

	<div class="form-field form-check furik-form-accept-reg" id="furik_form_accept_reg_div" style="display: none">
		<label for="furik_form_accept_reg" class="form-check-label">
			<input type="checkbox" name="furik_form_accept-reg" id="furik_form_accept_reg" class="form-check-input">
			<a href="<?php echo furik_url( $args['furik_card_registration_statement_url'] ); ?>" target="_blank"><?php echo __( 'I accept the card registration statement', 'furik' ); ?></a>
		</label>
	</div>

	<?php if ( $args['a']['enable_newsletter'] ) : ?>
		<div class="form-field form-check furik-form-newsletter">
			<label for="furik_form_newsletter" class="form-check-label">
				<input type="checkbox" name="furik_form_newsletter" id="furik_form_newsletter" class="form-check-input">
				<?php echo __( 'Subscribe to our newsletter', 'furik' ); ?>
			</label>
		</div>
	<?php endif; ?>

	<?php if ( $furik_recaptcha_enabled && $furik_recaptcha_site_key ) : ?>
		<div class="furik-recaptcha-notice" style="margin-top: 10px; font-size: 0.9em; color: #666;">
			<?php echo __( 'This site is protected by reCAPTCHA and the Google', 'furik' ); ?>
			<a href="https://policies.google.com/privacy" target="_blank"><?php echo __( 'Privacy Policy', 'furik' ); ?></a> <?php echo __( 'and', 'furik' ); ?>
			<a href="https://policies.google.com/terms" target="_blank"><?php echo __( 'Terms of Service', 'furik' ); ?></a> <?php echo __( 'apply.', 'furik' ); ?>
		</div>
	<?php endif; ?>

	<div class="py-4 footer-btns">
		<div class="submit-btn">
			<p class="submit">
				<input type="submit" class="button button-primary rounded-xl btn btn-primary" id="furik_form_submit_button" value="<?php echo __( 'Send', 'furik' ); ?>" />
			</p>
		</div>
		<div class="simple-logo">
			<a href="http://simplepartner.hu/PaymentService/Fizetesi_tajekoztato.pdf" target="_blank">
				<img src="<?php echo furik_url( '/wp-content/plugins/furik/images/simplepay.png' ); ?>" title="SimplePay - Online bankkártyás fizetés" alt="SimplePay vásárlói tájékoztató">
			</a>
		</div>
	</div>
</form>