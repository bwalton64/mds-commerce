jQuery(document).ready(function ($) {
    $('#mds-network-settings-form').on('submit', function () {
        var data = $(this).serialize();
        $.post(
            mdsWooNetworkSettings.url,
            data + '&_wpnonce=' + mdsWooNetworkSettings.nonce
        );
    });
});
