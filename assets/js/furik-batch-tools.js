/**
 * Furik Batch Tools JavaScript
 */
jQuery(document).ready(function($) {
    
    // Add confirmation for non-dry-run submissions
    $('.furik-batch-form').on('submit', function(e) {
        // If dry run is unchecked, show confirmation
        if (!$(this).find('input[name="dry_run"]').is(':checked')) {
            if (!confirm(furikBatchTools.confirmMessage)) {
                e.preventDefault();
                return false;
            }
        }
    });
    
    // Toggle input field visibility based on option selection
    $('.toggle-field').on('change', function() {
        var targetField = $(this).data('target');
        if ($(this).is(':checked')) {
            $(targetField).show();
        } else {
            $(targetField).hide();
        }
    });
    
    // Initialize any toggle fields
    $('.toggle-field').trigger('change');
    
    // Format date fields to local format
    $('.date-display').each(function() {
        var dateStr = $(this).text();
        if (dateStr && dateStr !== 'N/A') {
            var date = new Date(dateStr);
            $(this).text(date.toLocaleDateString());
        }
    });
    
    // Filter tables
    $('#filter-status').on('change', function() {
        var status = $(this).val();
        if (status === 'all') {
            $('.results-table tr').show();
        } else {
            $('.results-table tr').hide();
            $('.results-table tr:first-child').show(); // Keep header
            $('.results-table tr[data-status="' + status + '"]').show();
        }
    });
});
