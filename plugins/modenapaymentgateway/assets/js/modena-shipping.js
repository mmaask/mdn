jQuery(document).ready(function($) {
    const shippingSelectBox = $('#mdn-shipping-select-box');
    const currentShippingMethod = shippingSelectBox.data('method-id');

    function toggleSelectBoxVisibility() {
        const selectedShippingMethod = $('input[name="shipping_method[0]"]:checked').val();
        if (selectedShippingMethod === currentShippingMethod) {
            shippingSelectBox.closest('.mdn-shipping-select-wrapper').show();
        } else {
            shippingSelectBox.closest('.mdn-shipping-select-wrapper').hide();
        }
    }

    toggleSelectBoxVisibility();

    $(document.body).on('change', 'input[name="shipping_method[0]"]', toggleSelectBoxVisibility);

});