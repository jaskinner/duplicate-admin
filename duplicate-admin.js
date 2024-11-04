jQuery(document).ready(function ($) {
    $('.delete-attachment').on('click', function (e) {
        e.preventDefault();

        $attachmentId = $(this).data('attachment-id');

        $.ajax({
            url: my_ajax_object.ajaxurl,
            type: 'POST',
            data: {
                action: 'delete_single_attachment',
                security: my_ajax_object.security,
                attachmentId: $attachmentId
            },
            success: function (response) {
                console.log('AJAX request successful:', response);
            },
            error: function (xhr, status, error) {
                console.log('AJAX request failed:', error);
            }
        });
    });
    $('.delete-all-attachments').on('click', function (e) {
        e.preventDefault();
        const $attachmentIds = [];
        $('tr > td').each(function () {
            const $row = $(this).parent();
            const attachmentId = $row.find('.delete-attachment').data('attachment-id');
            if ($(this).hasClass('org')) {
                console.log('Attachment ID: original');
            } else if ($(this).hasClass('del')) {
                $attachmentIds.push(attachmentId);
            }
        });

        function deleteAttachmentsBatch(ids) {
            if (ids.length === 0) return; // Exit if there are no more attachments

            const batch = ids.splice(0, 10); // Process 20 attachments at a time

            $.ajax({
                url: my_ajax_object.ajaxurl,
                type: 'POST',
                data: {
                    action: 'delete_batch_attachments',
                    security: my_ajax_object.security,
                    attachmentIds: batch
                },
                success: function (response) {
                    console.log('AJAX request successful:', response);

                    // Remove rows of successfully deleted attachments
                    batch.forEach(id => {
                        $('tr').find(`[data-attachment-id="${id}"]`).closest('tr').remove();
                    });

                    deleteAttachmentsBatch(ids); // Process the next batch
                },
                error: function (xhr, status, error) {
                    console.log('AJAX request failed:', error);
                }
            });
        }

        // deleteAttachmentsBatch($attachmentIds); // Start the batch deletion
    });

    $('.delete-all-detached').on('click', function (e) {
        e.preventDefault();

        num = 10;

        while (num) {
            $.ajax({
                url: my_ajax_object.ajaxurl,
                type: 'POST',
                data: {
                    action: 'delete_detached_media',
                    security: my_ajax_object.security,
                },
                success: function (response) {
                    console.log('AJAX request successful:', response);
                },
                error: function (xhr, status, error) {
                    console.log('AJAX request failed:', error);
                }
            });
            num -= 1;
        }
    });

});