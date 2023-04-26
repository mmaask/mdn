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

    function checkSelectBoxValue() {
        const selectedShippingMethod = $('input[name="shipping_method[0]"]:checked').val();

        if (selectedShippingMethod === currentShippingMethod && (shippingSelectBox.val() === null || shippingSelectBox.val() === '')) {
            shippingSelectBox.prop('required', true);
        } else {
            shippingSelectBox.prop('required', false);
        }
    }

    shippingSelectBox.select2({
        placeholder: mdnTranslations.please_choose_parcel_terminal,
        allowClear: true
    });

    toggleSelectBoxVisibility(false);

    $(document.body).on('change', 'input[name="shipping_method[0]"]', () => {
        toggleSelectBoxVisibility(false);
        checkSelectBoxValue();
    });
    shippingSelectBox.on('change', () => {
        toggleSelectBoxVisibility(false);
        checkSelectBoxValue();
    });

    $('form.checkout.woocommerce-checkout').on('submit', function (event) {
        const selectedShippingMethod = $('input[name="shipping_method[0]"]:checked').val();
        if (selectedShippingMethod === currentShippingMethod && (shippingSelectBox.val() === null || shippingSelectBox.val() === '')) {
            event.preventDefault();
            toggleSelectBoxVisibility(true);
        }
    });
});