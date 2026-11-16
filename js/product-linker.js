jQuery(document).ready(function ($) {
    var wc_search_url = mdsWooProductLinker.searchUrl;
    var nonce = mdsWooProductLinker.nonce;

    if ($.fn.selectWoo || $.fn.select2) {
        var selectMethod = $.fn.selectWoo ? 'selectWoo' : 'select2';
        $('.mds-wc-select2').each(function () {
            var $el = $(this);
            var storeId = $el.data('store-id');

            $el[selectMethod]({
                ajax: {
                    url: wc_search_url,
                    dataType: 'json',
                    delay: 250,
                    beforeSend: function (xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', nonce);
                    },
                    data: function (params) {
                        return { q: params.term, store_id: storeId };
                    },
                    processResults: function (data) {
                        return { results: data.results };
                    }
                },
                minimumInputLength: 3
            });
        });
    }

    $('.mds-wc-generate-btn').on('click', function (e) {
        e.preventDefault();
        var $btn = $(this);
        $btn.text('Generating...').prop('disabled', true);

        $.ajax({
            url: mdsWooProductLinker.generateUrl,
            method: 'POST',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', nonce);
            },
            data: {
                store_id: $btn.data('store-id'),
                release_id: $btn.data('post-id')
            },
            success: function (res) {
                if (res.success) {
                    $btn.hide();
                    var opt = new Option(res.product_title, res.product_id, true, true);
                    $(opt).attr('data-permalink', res.permalink);
                    $btn.siblings('select').append(opt).trigger('change');
                } else {
                    alert('Error: ' + res.message);
                    $btn.text('Generate New').prop('disabled', false);
                }
            }
        });
    });
});
