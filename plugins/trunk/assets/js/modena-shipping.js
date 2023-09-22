jQuery(document).ready(function($) {
    console.log('Document is ready.');
    console.log('Is Select2 defined:', typeof $.fn.select2 !== 'undefined');

    if (typeof $.fn.select2 !== 'undefined') {
        try {
            $('.select2-enabled-mdn').select2();
            console.log('Select2 initialized.');
        } catch (e) {
            console.error('Could not initialize Select2:', e);
        }
    } else {
        console.error('Select2 is not loaded.');
    }
});