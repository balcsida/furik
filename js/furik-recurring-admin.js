/**
 * JavaScript for the improved recurring donations admin page
 */
jQuery(document).ready(function($) {
    // Handle view details clicks
    $(document).on('click', '.view-details', function(e) {
        e.preventDefault();
        
        var donationId = $(this).data('id');
        var detailsHtml = $('.donation-details-' + donationId).html();
        
        // Open the details in a modal
        tb_show(
            'Donation Details', 
            '#TB_inline?width=800&height=600&inlineId=donation-details-modal', 
            false
        );
        
        $('#donation-details-container').html(detailsHtml);
        
        // Adjust ThickBox size
        $('#TB_window').css({
            'width': '800px',
            'height': '80%',
            'max-height': '600px',
            'margin-left': '-400px'
        });
        
        $('#TB_ajaxContent').css({
            'width': '96%',
            'height': '92%',
            'overflow': 'auto'
        });
    });
    
    // Fix ThickBox iframe height for transaction history
    $(document).on('click', 'a.thickbox', function() {
        setTimeout(function() {
            $('#TB_iframeContent').css('height', '90%');
        }, 100);
    });
    
    // Handle bulk action confirmation
    $('#doaction, #doaction2').on('click', function(e) {
        var selectedAction = $(this).prev('select').val();
        
        if (selectedAction === 'delete') {
            if (!confirm('WARNING: You are about to completely delete the selected recurring donations. This will remove ALL records from the database. This action cannot be undone. Are you sure?')) {
                e.preventDefault();
                return false;
            }
        } else if (selectedAction === 'cancel') {
            if (!confirm('Are you sure you want to cancel the selected recurring donations? Future payments will be removed, but past transactions will be preserved. This action cannot be undone.')) {
                e.preventDefault();
                return false;
            }
        }
    });
});
