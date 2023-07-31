jQuery(document).ready(function($) {
    // Function to show/hide fields based on environment selection
    function toggleFields(environment) {
        if(environment === 'sandbox') {
            $('#modena_sandbox_client_id').closest('tr').show();
            $('#modena_sandbox_client_secret').closest('tr').show();
            $('#modena_live_client_id').closest('tr').hide();
            $('#modena_live_client_secret').closest('tr').hide();
        } else {
            $('#modena_sandbox_client_id').closest('tr').hide();
            $('#modena_sandbox_client_secret').closest('tr').hide();
            $('#modena_live_client_id').closest('tr').show();
            $('#modena_live_client_secret').closest('tr').show();
        }
    }

    // Initial field setup
    toggleFields($('#modena_environment').val());

    // Handle environment selection change
    $('#modena_environment').on('change', function() {
        toggleFields($(this).val());
    });
});