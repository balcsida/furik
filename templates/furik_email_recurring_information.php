<?php
$furik_sender_name    = 'Test';
$furik_sender_address = 'test@bzz.hu';
$furik_email_subject  = 'Login information';
?>
<div dir="ltr">
	Hi <?php echo $transaction->first_name; ?>,
	<div><br></div>
	<div>Thank you for your recurring donation. Your can log in with your emailaddress (<?php echo $transaction->email; ?>).
	<?php if ( ! $already_registered ) { ?>
		This is your randomly generated password:<br><?php echo $random_password; ?>
	<?php } ?>
	</div>
	<div><br></div>
	<div>-- </div>
	<div>Furik Donation System</div>
</div>