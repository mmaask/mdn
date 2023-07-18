jQuery(document).ready(function ($) {
    const shippingSelectBox1 = $('#mdn-shipping-select-box-itella');
    const shippingSelectBox2 = $('#mdn-shipping-select-box-omniva');

    const currentShippingMethod1 = shippingSelectBox1.data('method-id');
    const currentShippingMethod2 = shippingSelectBox2.data('method-id');

    function toggleSelectBoxVisibility() {
        const selectedShippingMethod = $('input[name="shipping_method[0]"]:checked').val();
        const shippingSelectWrapper1 = shippingSelectBox1.closest('.mdn-shipping-select-wrapper-modena-shipping-itella-terminals');
        const shippingSelectWrapper2 = shippingSelectBox2.closest('.mdn-shipping-select-wrapper-modena-shipping-omniva-terminals');

        if (selectedShippingMethod === currentShippingMethod1) {
            shippingSelectWrapper1.show();
            shippingSelectWrapper2.hide();
        } else if (selectedShippingMethod === currentShippingMethod2) {
            shippingSelectWrapper1.hide();
            shippingSelectWrapper2.show();
        } else {
            shippingSelectWrapper1.hide();
            shippingSelectWrapper2.hide();
        }
    }

    shippingSelectBox1.select2({
        placeholder: mdnTranslations.please_choose_parcel_terminal,
        allowClear: true
    });

    shippingSelectBox2.select2({
        placeholder: mdnTranslations.please_choose_parcel_terminal,
        allowClear: true
    });

    toggleSelectBoxVisibility();

    $(document.body).on('change', 'input[name="shipping_method[0]"]', () => {
        toggleSelectBoxVisibility();
    });

    shippingSelectBox1.on('change', () => {
        toggleSelectBoxVisibility();
    });

    shippingSelectBox2.on('change', () => {
        toggleSelectBoxVisibility();
    });
});