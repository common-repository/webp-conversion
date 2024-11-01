jQuery(document).ready(function ($) {

    $(document).on('click', '.webpc_convert_single', function (e) {
        e.preventDefault();
        const button = $(this);
        const spinner = button.next('.webpc-single-attach-spinner');
        const imageId = button.data('id');

        spinner.show();

        $.ajax({
            type: 'POST',
            url: webp_conversion.ajax_url,
            data: {
                action: 'convert_single',
                image_id: imageId,
                current_page: window.location.pathname,
                nonce: webp_conversion.nonce
            },
            success: function (response) {
                if (response.success) {
                    if (response.data.url === 'reload') {
                        location.reload();
                    } else {
                        window.location.href = response.data.url;
                    }
                } else {
                    spinner.hide();
                    alert('Failed to convert image.');
                }
            },
            error: function () {
                spinner.hide();
                alert('Error occurred while converting image.');
            }
        });
    });

    $(document).on('click', '.webpc_convert_selected', function (e) {
        e.preventDefault();
        const counterSpinner = $('.webpc-counter-and-spinner');

        let imageIds = [];
        $('.attachments .attachment.selected').each(function () {
            imageIds.push($(this).data('id'));
        });

        if (imageIds.length === 0) {
            alert('No images selected.');
            return;
        }

        counterSpinner.show();

        let counter = 0;
        let redirectUrl = '';
        let finalRedirectUrl = '';
        let converted = 0;

        function processBatch() {
            const batch = imageIds.slice(counter, counter + 5);

            if (batch.length === 0) {
                finalRedirectUrl = redirectUrl + converted;
                window.location.href = finalRedirectUrl;
                return;
            }

            $.ajax({
                type: 'POST',
                url: webp_conversion.ajax_url,
                data: {
                    action: 'convert_selected',
                    image_ids: batch,
                    nonce: webp_conversion.nonce
                },
                success: function (response) {
                    if (response.data.url) {
                        redirectUrl = response.data.url;
                        converted += response.data.converted;
                        counter += 5;

                        $('#webpc-converted-count').text(converted);

                        processBatch();
                    } else {
                        counterSpinner.hide();
                        alert('Failed to convert selected images.');
                    }
                },
                error: function () {
                    counterSpinner.hide();
                    alert('Error occurred while converting images.');
                }
            });
        }

        processBatch();
    });

    $('#webpc-settings-form').on('submit', function (event) {
        event.preventDefault();

        const formData = $(this).serialize();

        $.post(ajaxurl, formData, function (response) {
            if (response.success) {
                $('#webpc-notice').show().delay(3000).fadeOut();
            }
        });

    });

    const webpcNotice = $('#message.webpc-notice');
    if (webpcNotice.length) {
        const url = new URL(window.location.href);
        url.searchParams.delete('conversion_done');
        window.history.replaceState({}, document.title, url.toString());
    }
});
