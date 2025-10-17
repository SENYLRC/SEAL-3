
<?php require_once 'includes/template.php'; // WordPress Dashboard Functions ?>

<?php
if ( isset( $_GET['user'] ) && isset( $_GET['status'] ) ) {
	echo wp_kses_post('<div id="message" class="updated fade"><p>' . __( 'User successfully updated.', 'new-user-approve' ) . '</p></div>');
}
?>

<div class='nua-dashboard-wrap'>
	<?php echo '<div id="nua_dashboard_layout"> </div>'; ?>
</div>

