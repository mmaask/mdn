jQuery(document).ready(function($) {
    const shippingSelectBox = $('#mdn-shipping-select-box');
    const currentShippingMethod = shippingSelectBox.data('method-id');

    function toggleSelectBoxVisibility(isSubmitEvent) {
        const selectedShippingMethod = $('input[name="shipping_method[0]"]:checked').val();
        const shippingSelectWrapper = shippingSelectBox.closest('.mdn-shipping-select-wrapper');

        if (selectedShippingMethod === currentShippingMethod) {
            shippingSelectWrapper.show();
            if (shippingSelectBox.val() === null || shippingSelectBox.val() === '') {
                shippingSelectBox.prop('required', true);
                if (isSubmitEvent) {
                    shippingSelectWrapper.addClass('woocommerce-invalid');
                }
            } else {
                shippingSelectBox.prop('required', false);
                shippingSelectWrapper.removeClass('required-border woocommerce-invalid');
                if (isSubmitEvent) {
                    shippingSelectWrapper.addClass('woocommerce-validated');
                }
            }
        } else {
            shippingSelectWrapper.hide();
            shippingSelectBox.prop('required', false);
            shippingSelectWrapper.removeClass('required-border woocommerce-invalid');
        }
    }

    // Initialize Select2 for the select box
    shippingSelectBox.select2({
        placeholder: 'Palun vali pakiautomaat',
        allowClear: true
    });

    toggleSelectBoxVisibility(false);

    $(document.body).on('change', 'input[name="shipping_method[0]"]', () => toggleSelectBoxVisibility(false));
    shippingSelectBox.on('change', () => toggleSelectBoxVisibility(false));

    $('form.checkout.woocommerce-checkout').on('submit', function (event) {
        // Check if the shipping method is selected and the select box value is empty
        const selectedShippingMethod = $('input[name="shipping_method[0]"]:checked').val();
        if (selectedShippingMethod === currentShippingMethod && (shippingSelectBox.val() === null || shippingSelectBox.val() === '')) {
            event.preventDefault(); // Prevent form submission
            toggleSelectBoxVisibility(true);
        }
    });
});
