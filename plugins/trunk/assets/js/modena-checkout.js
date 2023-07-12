function selectModenaBanklink(id, value) {
    unselectAllModenaBanklinks();
    document.getElementById('mdn_bl_option_' + id).classList.add('mdn_checked');
    document.getElementById('modena_selected_payment_method').value = value;
}

function unselectAllModenaBanklinks() {
    var allBanklinks = document.querySelectorAll('[id^="mdn_bl_option_"]');
    for (i = 0; i < allBanklinks.length; i++) {
        unselectModenaBanklink(allBanklinks[i]);
    }
}

function unselectModenaBanklink(banklink) {
    banklink.classList.remove('mdn_checked');
}