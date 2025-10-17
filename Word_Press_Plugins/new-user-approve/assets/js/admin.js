jQuery( document ).ready( function(){
	jQuery( document ).on( 'click', '#nua-generate-api', function( e ){
		e.preventDefault();
		var apiKey = btoa( nuaAdmin.info );
		jQuery( '#nua-api' ).val( apiKey );
	} );
} );

/**
 * 
 * Invitation code Messages
 */
jQuery(document).ready(function() {
	const errorMessage = jQuery('#errorMessage');
	const successMessage = jQuery('#successMessage');
    const failMessage    = jQuery("#failMessage");
	
	showMessage(errorMessage);
	showMessage(successMessage);
	showMessage(failMessage );

   
	function showMessage(messageElement) {
	  messageElement.fadeIn();
  
	  setTimeout(function() {
		messageElement.fadeOut();
	  }, 3000); 
	}

  });