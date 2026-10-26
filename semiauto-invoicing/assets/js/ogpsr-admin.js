/**
 * SemiAuto Invoicing — Core Admin Scripts
 *
 * Core administration scripts handling settings management, asynchronous AJAX saves,
 * document regeneration, tab switching, form validation, and asset media uploads.
 *
 */
(function ($) {
    'use strict';

    function getAjaxUrl() {
        return (typeof window.ajaxurl !== 'undefined' && window.ajaxurl)
            ? window.ajaxurl
            : ((typeof window.ogpsr_admin_data !== 'undefined' && window.ogpsr_admin_data.ajax_url)
                ? window.ogpsr_admin_data.ajax_url
                : '/wp-admin/admin-ajax.php');
    }

    var ajaxurl = getAjaxUrl();

    window.ogpsr_admin_data = window.ogpsr_admin_data || {};
    window.ogpsr_admin_data.strings = window.ogpsr_admin_data.strings || {};
    window.ogpsr_admin_data.standards = window.ogpsr_admin_data.standards || {};

    function ogpsrDebug() {
        if (typeof ogpsr_admin_data !== 'undefined' && ogpsr_admin_data.debug) {
            console.log.apply(console, arguments);
        }
    }

    $(document).ready(function () {
        // Prevent action on disabled download links
        $(document).on('click', '.ogpsr-download-link.disabled', function (e) {
            e.preventDefault();
            return false;
        });

        // Regenerate PDF from Order Metabox or Storage Manager
        $(document).on('click', '.ogpsr-regenerate-btn', function (e) {
            e.preventDefault();
            var $btn = $(this);
            var order_id = $btn.data('order-id') || $btn.attr('data-order-id');
            var format = $btn.data('format') || $btn.attr('data-format');
            var nonce = $btn.data('nonce') || $btn.attr('data-nonce') || $('#ogpsr_regenerate_nonce').val();

            // Standard document element scoping (Table row or PDF item)
            var $rowContainer = $btn.closest('tr, .ogpsr-pdf-item');
            var isTable = $rowContainer.is('tr');

            var $status = isTable ? $rowContainer.find('.ogpsr-pdf-status, .ogpsr-regen-status, .ogpsr-status') : $rowContainer.children('.ogpsr-pdf-status');
            var $spinner = isTable ? $rowContainer.find('.spinner, .ogpsr-spinner') : $rowContainer.children('.ogpsr-pdf-title').find('.spinner, .ogpsr-spinner');
            var $dot = isTable ? $rowContainer.find('.ogpsr-dot') : $rowContainer.children('.ogpsr-pdf-title').find('.ogpsr-dot');
            var $downloadLink = isTable ? $rowContainer.find('.ogpsr-download-link') : $rowContainer.children('.ogpsr-pdf-actions').find('.ogpsr-download-link');

            

            if ($btn.hasClass('disabled')) return;

            var strings = (typeof ogpsr_admin_data !== 'undefined' && ogpsr_admin_data.strings) ? ogpsr_admin_data.strings : {};
            $btn.addClass('disabled').text(strings.regenerating || 'Regenerating...');
            $status.text('').removeClass('success error');
            $spinner.addClass('is-active');

            // The row's own "Send email to customer" checkbox (a nested credit note row has its own).
            var $sendEmail = $rowContainer.find('.ogpsr-send-email').first();

            $.post(getAjaxUrl(), {
                action: 'ogpsr_regenerate_file',
                order_id: order_id,
                format: format,
                nonce: nonce,
                send_email: $sendEmail.length && $sendEmail.is(':checked') ? 'true' : 'false'
            }, function (response) {
                if (response.success) {
                    $status.text(response.data.message || ogpsr_admin_data.strings.saved).addClass('success');
                    $btn.removeClass('disabled').text(ogpsr_admin_data.strings.regenerate);
                    $spinner.removeClass('is-active');

                    $dot.removeClass('missing').addClass('success');
                    if (response.data.download_url && $downloadLink.length) {
                        $downloadLink.attr('href', response.data.download_url).removeClass('ogpsr-hide').show();
                    }
                    if (response.data.filename && isTable && $rowContainer && $rowContainer.length) {
                        var newFilename = response.data.filename;
                        var viewUrl = response.data.view_url || '';
                        var downloadUrl = response.data.download_url || (viewUrl ? viewUrl + '&download=1' : '');

                        $rowContainer.attr('id', 'row-' + newFilename);
                        $rowContainer.attr('data-filename', newFilename);
                        $rowContainer.find('.ogpsr-file-checkbox').val(newFilename);

                        var $nameCell = $rowContainer.find('td').first();
                        if ($nameCell.length) {
                            var $badge = $nameCell.find('.ogpsr-badge, .ogpsr-file-badge');
                            $nameCell.empty().append(document.createTextNode(newFilename + ' '));
                            if ($badge.length) {
                                $nameCell.append($badge);
                            }
                        }
                        if (viewUrl) {
                            $rowContainer.find('a[href*="ogpsr_view_receipt"]:not([href*="download=1"])').attr('href', viewUrl);
                            $rowContainer.find('a[href*="download=1"]').attr('href', downloadUrl);
                        }
                        var $delBtn = $rowContainer.find('.ogpsr-delete-btn');
                        if ($delBtn.length) {
                            $delBtn.attr('data-file', newFilename).data('file', newFilename);
                        }
                    }
                    if ($rowContainer && $rowContainer.length) {
                        $rowContainer.find('.ogpsr-store-diff-notice').slideUp(250, function () { $(this).remove(); });
                    }
                    
                } else {
                    var errorMsg = (response.data && response.data.message) ? response.data.message : ogpsr_admin_data.strings.error;
                    $status.text(errorMsg).addClass('error');
                    $btn.removeClass('disabled').text(ogpsr_admin_data.strings.regenerate);
                    $spinner.removeClass('is-active');
                }
            }).fail(function () {
                var errorMsg = ogpsr_admin_data.strings.server_error || 'A server error occurred.';
                $status.text(errorMsg).addClass('error');
                $btn.removeClass('disabled').text(ogpsr_admin_data.strings.regenerate);
                $spinner.removeClass('is-active');
            });
        });

        /**
         * Modern Sync Store Details from WooCommerce (Granular Modal & Core Support)
         */
        $(document).on('click', '#ogpsr_sync_woo_btn, .ogpsr-sync-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();

            var $btn = $(this).closest('button, .ogpsr-sync-btn');
            if (!$btn.length) {
                $btn = $(this);
            }
            if ($btn.prop('disabled') || $btn.hasClass('disabled')) {
                return;
            }

            var $msg = $('#ogpsr_sync_msg');
            var originalNodes = $btn.contents().clone();
            var syncNonce = (typeof ogpsr_admin_data !== 'undefined' && ogpsr_admin_data.sync_nonce)
                ? ogpsr_admin_data.sync_nonce
                : ((window.ogpsr_admin_data && window.ogpsr_admin_data.sync_nonce)
                    ? window.ogpsr_admin_data.sync_nonce
                    : ($('#_wpnonce').val() || $('#woocommerce-settings-nonce').val() || ''));
            var ajaxUrl = (typeof ogpsr_admin_data !== 'undefined' && ogpsr_admin_data.ajax_url)
                ? ogpsr_admin_data.ajax_url
                : ((typeof window.ajaxurl !== 'undefined') ? window.ajaxurl : '/wp-admin/admin-ajax.php');
            var syncingText = (typeof ogpsr_admin_data !== 'undefined' && ogpsr_admin_data.strings && ogpsr_admin_data.strings.syncing)
                ? ogpsr_admin_data.strings.syncing
                : 'Syncing...';

            $btn.prop('disabled', true).addClass('disabled').empty()
                .append($('<span>', { class: 'dashicons dashicons-update spin ogpsr-v-middle' }))
                .append(document.createTextNode(' ' + syncingText));
            $msg.empty().removeClass('success error');

            $.post(ajaxUrl, {
                action: 'ogpsr_sync_woo_details',
                nonce: syncNonce
            }).done(function (response) {
                if (typeof response === 'string') {
                    try {
                        response = JSON.parse(response);
                    } catch (err) { }
                }
                $btn.prop('disabled', false).removeClass('disabled').empty().append(originalNodes.clone());

                if (response && response.success && response.data) {
                    var data = response.data;
                    $('#ogpsr-sync-modal-wrap').remove();
                    var $itemsList = $('<div>').addClass('ogpsr-sync-items-list');
                    var itemCount = 0;

                    var addSyncItemCard = function (target, labelText, valText, extraAttrs) {
                        itemCount++;
                        var $card = $('<label>').addClass('ogpsr-sync-item-card');
                        var $chk = $('<input>').attr({
                            type: 'checkbox',
                            'class': 'ogpsr-sync-chk',
                            'data-target': target,
                            'data-val': valText
                        }).prop('checked', true);
                        if (extraAttrs) {
                            $chk.attr(extraAttrs);
                        }
                        var $info = $('<div>').addClass('ogpsr-sync-item-info');
                        var $lbl = $('<span>').addClass('ogpsr-sync-item-label').text(labelText);
                        var $val = $('<span>').addClass('ogpsr-sync-item-val').text(valText);
                        $info.append($lbl, $val);
                        $card.append($chk, $info);
                        $itemsList.append($card);
                    };

                    // 1. Business Name
                    if (data.business_name) {
                        addSyncItemCard('business_name', 'Business Name', data.business_name);
                    }

                    // 2. Business Address (Street Only)
                    if (data.business_address) {
                        addSyncItemCard('business_address', 'Business Address', data.business_address);
                    }

                    // 3. City & Postcode
                    if (data.business_city || data.business_postcode) {
                        var cityPostcodeDisplay = [data.business_city, data.business_postcode].filter(Boolean).join(', ');
                        addSyncItemCard('city_postcode', 'City & Postal Code', cityPostcodeDisplay, {
                            'data-city': data.business_city || '',
                            'data-postcode': data.business_postcode || ''
                        });
                    }

                    // 4. Contact Details
                    if (data.business_contact) {
                        addSyncItemCard('business_contact', 'Store Contact Details', data.business_contact);
                    }

                    // 5. Store Country
                    if (data.supplier_country) {
                        addSyncItemCard('supplier_country', 'Store Base Country', data.supplier_country);
                    }

                    if (itemCount === 0) {
                        var $empty = $('<div>').addClass('ogpsr-sync-empty-state')
                            .append($('<p>').append($('<span>').addClass('dashicons dashicons-info')))
                            .append($('<p>').append($('<strong>').text('No store details found in WooCommerce settings.')))
                            .append($('<p>').addClass('ogpsr-sync-empty-desc').text('Please configure your store address under WooCommerce > Settings > General, then try syncing again.'));
                        $itemsList.append($empty);
                    }

                    var $modal = $('<div>').attr({ id: 'ogpsr-sync-modal-wrap', 'class': 'ogpsr-modal-backdrop' });
                    var $dialog = $('<div>').addClass('ogpsr-modal-dialog ogpsr-sync-modal').attr({ role: 'dialog', 'aria-modal': 'true' });

                    var $header = $('<div>').addClass('ogpsr-modal-header')
                        .append($('<div>').addClass('ogpsr-modal-header-icon').append($('<span>').addClass('dashicons dashicons-store')))
                        .append($('<div>').addClass('ogpsr-modal-header-text')
                            .append($('<h3>').text('Sync Store Details from WooCommerce'))
                            .append($('<p>').addClass('ogpsr-modal-subtitle').text('Select which business details to import from your store settings into your receipts:'))
                        )
                        .append($('<button>', { type: 'button', 'class': 'ogpsr-modal-close', 'aria-label': 'Close', text: '×' }));

                    var $body = $('<div>').addClass('ogpsr-modal-body')
                        .append($('<div>').addClass('ogpsr-sync-select-all-row')
                            .append($('<label>').append($('<input>').attr({ type: 'checkbox', id: 'ogpsr-sync-select-all' }).prop('checked', true), ' ', $('<strong>').text('Select All')))
                        )
                        .append($itemsList);

                    var $footer = $('<div>').addClass('ogpsr-modal-footer')
                        .append($('<button>').attr({ type: 'button', 'class': 'button button-secondary ogpsr-modal-cancel' }).text('Cancel'))
                        .append($('<button>').attr({ type: 'button', 'class': 'button button-primary ogpsr-sync-confirm-btn' })
                            .append($('<span>').addClass('dashicons dashicons-yes-alt ogpsr-v-middle'), ' ' + 'Apply Sync')
                        );

                    $dialog.append($header, $body, $footer);
                    $modal.append($dialog);
                    $('body').append($modal);
                } else {
                    var errorMsg = (response && response.data && response.data.message) ? response.data.message : 'Error fetching WooCommerce details.';
                    $msg.empty().append($('<span>').addClass('ogpsr-sync-badge error').text('✖ ' + errorMsg)).show();
                    setTimeout(function () { $msg.fadeOut(500); }, 5000);
                }
            }).fail(function (xhr, status, error) {
                $btn.prop('disabled', false).removeClass('disabled').empty().append(originalNodes.clone());
                var errText = (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message)
                    ? xhr.responseJSON.data.message
                    : 'Server communication error.';
                $msg.empty().append($('<span>').addClass('ogpsr-sync-badge error').text('✖ ' + errText)).show();
                setTimeout(function () { $msg.fadeOut(500); }, 5000);
            });
        });

        // Toggle Select All inside Sync Modal
        $(document).on('change', '#ogpsr-sync-select-all', function () {
            var isChecked = $(this).is(':checked');
            $('.ogpsr-sync-chk').prop('checked', isChecked);
        });

        // Close Sync Modal
        $(document).on('click', '.ogpsr-modal-close, .ogpsr-modal-cancel', function () {
            $('#ogpsr-sync-modal-wrap').fadeOut(150, function () { $(this).remove(); });
        });
        $(document).on('click', '#ogpsr-sync-modal-wrap', function (e) {
            if ($(e.target).is('#ogpsr-sync-modal-wrap')) {
                $(this).fadeOut(150, function () { $(this).remove(); });
            }
        });

        // Open Privacy Policy & Retention Disclosures Modal
        $(document).on('click', '.ogpsr-read-privacy-policy-btn', function (e) {
            e.preventDefault();
            $('#ogpsr-privacy-policy-modal').removeClass('ogpsr-hidden').fadeIn(150);
        });

        // Close Privacy Policy Modal
        $(document).on('click', '.ogpsr-close-privacy-modal-btn', function (e) {
            e.preventDefault();
            $('#ogpsr-privacy-policy-modal').fadeOut(150, function () {
                $(this).addClass('ogpsr-hidden');
            });
        });
        $(document).on('click', '#ogpsr-privacy-policy-modal', function (e) {
            if ($(e.target).is('#ogpsr-privacy-policy-modal')) {
                $(this).fadeOut(150, function () {
                    $(this).addClass('ogpsr-hidden');
                });
            }
        });

        // Apply Granular Sync Selection
        $(document).on('click', '.ogpsr-sync-confirm-btn', function () {
            var syncedCount = 0;

            $('.ogpsr-sync-chk:checked').each(function () {
                var target = $(this).data('target');
                if (target === 'business_name') {
                    $('#ogpsr_business_name').val($(this).data('val')).trigger('input').trigger('change');
                    syncedCount++;
                } else if (target === 'business_address') {
                    $('#ogpsr_business_address').val($(this).data('val')).trigger('input').trigger('change');
                    syncedCount++;
                } else if (target === 'city_postcode') {
                    $('#ogpsr_business_city').val($(this).data('city')).trigger('input').trigger('change');
                    $('#ogpsr_business_postcode').val($(this).data('postcode')).trigger('input').trigger('change');
                    syncedCount++;
                } else if (target === 'business_contact') {
                    $('#ogpsr_business_contact').val($(this).data('val')).trigger('input').trigger('change');
                    syncedCount++;
                } else if (target === 'supplier_country') {
                    if ($('#ogpsr_xml_supplier_country').length) {
                        $('#ogpsr_xml_supplier_country').val($(this).data('val')).trigger('input').trigger('change');
                    }
                    if ($('#ogpsr_business_country').length) {
                        $('#ogpsr_business_country').val($(this).data('val')).trigger('input').trigger('change');
                    }
                    syncedCount++;
                }
            });

            $('#ogpsr-sync-modal-wrap').fadeOut(150, function () { $(this).remove(); });

            if (syncedCount > 0) {
                var $msg = $('#ogpsr_sync_msg');
                var syncedSuccessText = (typeof ogpsr_admin_data !== 'undefined' && ogpsr_admin_data.strings && ogpsr_admin_data.strings.synced)
                    ? ogpsr_admin_data.strings.synced
                    : 'Settings synchronized with WooCommerce!';
                $msg.empty().append($('<span>').addClass('ogpsr-sync-badge success').text('✓ ' + syncedSuccessText)).show();
                setTimeout(function () {
                    $msg.fadeOut(500);
                }, 4000);
            }
        });

        /**
         * Global AJAX Save Engine
         */
        function saveSettings($form) {
            var $saveBtn = $form.find('.woocommerce-save-button');
            var urlParams = new URLSearchParams(window.location.search);
            var section = urlParams.get('section') || 'general';

            // Validation: Check for mandatory fields that are VISIBLE
            var $requiredFields = $form.find('.ogpsr-required:visible');
            var validationErrors = false;

            $requiredFields.each(function () {
                var $field = $(this);
                // Handle both standard inputs and selects
                var val = $field.val();
                if (!val || (typeof val === 'string' && val.trim() === '')) {
                    $field.addClass('ogpsr-required-error');
                    validationErrors = true;
                } else {
                    $field.removeClass('ogpsr-required-error');
                }
            });

            if (validationErrors) {
                if (!$('.ogpsr-ajax-save-status').length) {
                    $('body').append('<div class="ogpsr-ajax-save-status"></div>');
                }
                var $statusMsg = $('.ogpsr-ajax-save-status');
                var errorTxt = (typeof ogpsr_admin_data !== 'undefined' && ogpsr_admin_data.strings.mandatory_fields_missing)
                    ? ogpsr_admin_data.strings.mandatory_fields_missing
                    : 'Missing mandatory compliance fields. Please check highlighted inputs.';

                $statusMsg.removeClass('success').addClass('error').text(errorTxt).fadeIn();
                setTimeout(function () { $statusMsg.fadeOut(); }, 5000);

                // Scroll to the first error for better UX
                $('html, body').animate({
                    scrollTop: ($('.ogpsr-required-error').first().offset().top - 100)
                }, 500);

                return false; // STOP THE SAVE
            }

            if (!$('.ogpsr-ajax-save-status').length) {
                $('body').append('<div class="ogpsr-ajax-save-status"></div>');
            }
            var $status = $('.ogpsr-ajax-save-status');

            var ajaxUrl = (typeof ogpsr_admin_data !== 'undefined' && ogpsr_admin_data.ajax_url) ? ogpsr_admin_data.ajax_url : (typeof ajaxurl !== 'undefined' ? ajaxurl : '');
            var savingText = (typeof ogpsr_admin_data !== 'undefined' && ogpsr_admin_data.strings) ? ogpsr_admin_data.strings.saving : 'Saving...';
            var saveText = (typeof ogpsr_admin_data !== 'undefined' && ogpsr_admin_data.strings) ? ogpsr_admin_data.strings.save : 'Save';

            $saveBtn.prop('disabled', true).text(savingText);
            $status.removeClass('success error').text(savingText).fadeIn();

            var formData = $form.serializeArray();
            formData.push({ name: 'action', value: 'ogpsr_ajax_save_settings' });
            formData.push({ name: 'nonce', value: (typeof ogpsr_admin_data !== 'undefined') ? ogpsr_admin_data.ajax_save_nonce : '' });
            formData.push({ name: 'section', value: section });
            

            ogpsrDebug('OGPSR: Dispatching AJAX Save for section:', section);

            $.post(ajaxUrl, formData, function (response) {
                ogpsrDebug('OGPSR: Save Response:', response);
                $saveBtn.prop('disabled', false).text(saveText);

                if (response.success) {
                    var successMsg = (response.data && response.data.message) ? response.data.message : ogpsr_admin_data.strings.saved;
                    $status.addClass('success').text(successMsg);
                    if (typeof updateLivePreview === 'function' && $('#ogpsr-live-preview').length) updateLivePreview();
                } else {
                    var errorMsg = (response.data && response.data.message) ? response.data.message : ogpsr_admin_data.strings.error;
                    $status.addClass('error').text(errorMsg);
                }
                setTimeout(function () {
                    $status.fadeOut(function () { $(this).removeClass('success error'); });
                }, 3000);
            }).fail(function () {
                $saveBtn.prop('disabled', false).text(saveText);
                $status.addClass('error').text(ogpsr_admin_data.strings.server_error).fadeOut(3000);
            });
        }

        // Catch the submit event on any settings form in our plugin
        $(document).on('submit', '#mainform, .wc-settings-form', function (e) {
            var urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('tab') === 'pdf_smart_receipts' || $('#ogpsr-section-nav').length || $('.ogpsr-preview-canvas').length) {
                e.preventDefault();
                saveSettings($(this));
                return false;
            }
        });

        // Forced fallback for the save button click
        $(document).on('click', '.woocommerce-save-button', function (e) {
            var urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('tab') === 'pdf_smart_receipts' || $('#ogpsr-section-nav').length || $('.ogpsr-preview-canvas').length) {
                e.preventDefault();
                var $form = $(this).closest('form');
                if ($form.length) saveSettings($form);
                return false;
            }
        });

        // Image Upload Logic
        var mediaUploader;
        $(document).on('click', '.ogpsr-image-upload-button', function (e) {
            e.preventDefault();
            var button = $(this);
            var targetId = button.data('target');
            var attachmentTarget = button.data('attachment-target') || 'ogpsr_logo_attachment_id';

            if (mediaUploader) {
                mediaUploader.open();
                return;
            }

            mediaUploader = wp.media.frames.file_frame = wp.media({
                title: ogpsr_admin_data.strings.choose_image,
                button: {
                    text: ogpsr_admin_data.strings.choose_image
                },
                library: {
                    type: ['image/png', 'image/jpeg', 'image/webp', 'image/gif']
                },
                multiple: false
            });

            mediaUploader.on('select', function () {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                $('#' + targetId).val(attachment.url).trigger('change');
                if (attachmentTarget && $('#' + attachmentTarget).length) {
                    $('#' + attachmentTarget).val(attachment.id).trigger('change');
                }
                if ($('#ogpsr_logo_preview_img').length) {
                    var previewUrl = (attachment.sizes && attachment.sizes.medium) ? attachment.sizes.medium.url : attachment.url;
                    $('#ogpsr_logo_preview_img').attr('src', previewUrl);
                    $('.ogpsr-logo-preview-wrapper').removeClass('ogpsr-hide');
                    $('#ogpsr_remove_logo_btn').removeClass('ogpsr-hide');
                }
            });

            mediaUploader.open();
        });

        $(document).on('click', '#ogpsr_remove_logo_btn', function (e) {
            e.preventDefault();
            $('#ogpsr_logo_attachment_id').val('').trigger('change');
            $('.ogpsr-logo-preview-wrapper').addClass('ogpsr-hide');
            $('#ogpsr_logo_preview_img').attr('src', '');
            $(this).addClass('ogpsr-hide');
        });


        // Purge "Turbo-Burst" Cache
        $(document).on('click', '.ogpsr-purge-cache-btn', function (e) {
            e.preventDefault();
            var $btn = $(this);
            var type = $btn.data('type');

            if (!confirm(ogpsr_admin_data.strings.confirm_purge || ogpsr_admin_data.strings.confirm_purge_short)) {
                return;
            }

            if (!$('.ogpsr-ajax-save-status').length) {
                $('body').append('<div class="ogpsr-ajax-save-status"></div>');
            }
            var $status = $('.ogpsr-ajax-save-status');

            $btn.prop('disabled', true).addClass('updating-message');
            $status.removeClass('success error').text(ogpsr_admin_data.strings.saving).fadeIn();

            $.post(ajaxurl, {
                action: 'ogpsr_purge_cache_ajax',
                nonce: ogpsr_admin_data.purge_nonce,
                type: type
            }, function (response) {
                if (response.success) {
                    $status.addClass('success').text(response.data.message);
                    setTimeout(function () {
                        window.location.reload();
                    }, 1000);
                } else {
                    $status.addClass('error').text(response.data.message || 'Error purging cache.');
                    $btn.prop('disabled', false).removeClass('updating-message');
                    setTimeout(function () { $status.fadeOut(); }, 3000);
                }
            });
        });

        // ============================================================
        // Live Preview Synchronization Engine (Free Core)
        // ============================================================
        var previewTimeout;
        var previewRequestSequence = 0;
        function updateLivePreview() {
            if (!$('#ogpsr-live-preview').length) return;

            // Invalidate in-flight requests immediately so an older tab/settings
            // response cannot overwrite the preview requested most recently.
            var requestId = ++previewRequestSequence;
            clearTimeout(previewTimeout);
            previewTimeout = setTimeout(function () {
                var $loader = $('.ogpsr-preview-loader');
                var $iframe = $('#ogpsr-preview-iframe');

                if (!$iframe.length) return;

                var activeDevice = $('.ogpsr-device-btn.active');
                if (!activeDevice.length) return;

                $loader.addClass('active');

                // Measure the stable canvas width (not the wrapper, which may be mid-transition).
                var containerWidth = $('#ogpsr-preview-wrapper').parent().width() || $('#ogpsr-preview-wrapper').width() || 380;
                var format = activeDevice.data('format');
                var widthAttr = activeDevice.data('width');
                var requestedFormat = format;
                var requestedWidth = String(widthAttr || '');
                var docWidthPx = 794; // Default A4 (210mm)
                var scale = 1;

                if (format === 'thermal') {
                    var rollWidthMm = parseInt(widthAttr, 10) || 80;
                    docWidthPx = rollWidthMm * 3.78;
                    // The wrapper width is proportional to the roll (80mm = 100% of the canvas, see
                    // ogpsr-admin.css), so scale against the final target width for a stable fit.
                    var targetWrapperWidth = containerWidth * (rollWidthMm / 80);
                    var thermalFitScale = (targetWrapperWidth - 24) / docWidthPx;
                    scale = Math.max(0.95, Math.min(thermalFitScale, 1.45));
                } else {
                    scale = Math.min(containerWidth / docWidthPx, 1);
                }

                $iframe[0].dataset.ogpsrScale = scale;
                $iframe[0].dataset.ogpsrDocWidth = docWidthPx + 'px';

                // Gather all settings from the page
                var settings = {};
                $('input[name^="ogpsr_"], select[name^="ogpsr_"], textarea[name^="ogpsr_"]').each(function () {
                    var $el = $(this);
                    var name = $el.attr('name');
                    if (!name) return;

                    var isArray = name.indexOf('[]') !== -1;
                    var cleanName = name.replace('[]', '');

                    if ($el.is(':checkbox')) {
                        if (isArray) {
                            if (!settings[cleanName]) settings[cleanName] = [];
                            if ($el.is(':checked')) {
                                settings[cleanName].push($el.val());
                            }
                        } else {
                            settings[name] = $el.is(':checked') ? 'yes' : 'no';
                        }
                    } else if ($el.is(':radio')) {
                        if ($el.is(':checked')) settings[name] = $el.val();
                    } else if ($el.is('select') && $el.prop('multiple')) {
                        var val = $el.val();
                        settings[cleanName] = (Array.isArray(val) && val.length > 0) ? val : ['__ogpsr_empty__'];
                    } else {
                        var val = $el.val();
                        if (isArray) {
                            if (!settings[cleanName]) settings[cleanName] = [];
                            if (val !== '' && val !== null) settings[cleanName].push(val);
                        } else {
                            settings[name] = val;
                        }
                    }
                });

                var previewNonce = (typeof ogpsr_admin_data !== 'undefined' && ogpsr_admin_data.preview_nonce)
                    ? ogpsr_admin_data.preview_nonce
                    : '';

                $.post(getAjaxUrl(), {
                    action: 'ogpsr_get_live_preview',
                    format: requestedFormat,
                    width: requestedWidth,
                    settings: settings,
                    nonce: previewNonce
                }, function (response) {
                    // Ignore responses for older tabs or settings states.
                    var $currentDevice = $('.ogpsr-device-btn.active');
                    if (requestId !== previewRequestSequence || !$currentDevice.length ||
                        $currentDevice.data('format') !== requestedFormat ||
                        String($currentDevice.data('width') || '') !== requestedWidth) {
                        return;
                    }

                    var $iframe = $('#ogpsr-preview-iframe');
                    var $wrapper = $('#ogpsr-preview-wrapper');
                    var $errorNotice = $('#ogpsr-preview-error-notice');
                    if (!$iframe.length) return;

                    if (response.success) {
                        if ($errorNotice.length) {
                            $errorNotice.addClass('ogpsr-hide').empty();
                        }
                        if ($wrapper.length) {
                            $wrapper.removeClass('ogpsr-hide');
                        }
                        $iframe.removeClass('ogpsr-hide');

                        // Keep the reserved iframe height while the next document loads, hiding the
                        // unfinished paint to prevent logo and layout flicker between paper sizes.
                        if ($wrapper.length) {
                            $wrapper.addClass('ogpsr-preview-swapping');
                        }

                        // All post-load work runs from the parent window. Event listeners, animation
                        // frames and observers inside the script-disabled sandboxed iframe are not
                        // dispatched by every browser (e.g. Safari/WebKit), so none are relied upon.
                        $iframe.off('load.ogpsr').on('load.ogpsr', function () {
                            var iframeEl = this;
                            var doc = iframeEl.contentDocument || (iframeEl.contentWindow ? iframeEl.contentWindow.document : null);
                            if (!doc) return;

                            var storedScale = parseFloat(iframeEl.dataset.ogpsrScale || 1);
                            var activeWidth = activeDevice.data('width') || '80mm';
                            var reduceMotion = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);

                            // Preview scoping attributes are printed server-side for documents; this keeps
                            // other preview types (rendered without them) consistent.
                            if (doc.documentElement) {
                                if (!doc.documentElement.getAttribute('data-ogpsr-width')) {
                                    doc.documentElement.setAttribute('data-ogpsr-width', activeWidth);
                                }
                                doc.documentElement.classList.add('ogpsr-preview-html');
                            }
                            if (doc.body) {
                                doc.body.classList.add('ogpsr-preview-body');
                            }

                            var revealed = false;
                            var revealPreview = function () {
                                if (revealed) return;
                                revealed = true;
                                window.requestAnimationFrame(function () {
                                    $('#ogpsr-preview-wrapper').removeClass('ogpsr-preview-swapping').addClass('ogpsr-preview-animated');
                                });
                            };

                            // Fit the iframe height to the scaled document using the parent window's
                            // animation frames. A new target always restarts the animation, so the
                            // height can never stay stuck on a previously recorded target.
                            var fitPreviewHeight = function (safeScale) {
                                var bodyHeight = doc.body ? Math.max(doc.body.scrollHeight, doc.body.offsetHeight) : 0;
                                var documentHeight = doc.documentElement ? Math.max(doc.documentElement.scrollHeight, doc.documentElement.offsetHeight) : 0;
                                var scrollH = Math.max(bodyHeight, documentHeight);
                                if (scrollH <= 50) return;

                                var targetHeight = Math.max(Math.ceil(scrollH * safeScale) + 35, 480);
                                var currentHeight = parseInt(iframeEl.height, 10) || Math.ceil(iframeEl.getBoundingClientRect().height) || 480;

                                if (iframeEl._ogpsrHeightAnimation) {
                                    if (iframeEl._ogpsrTargetHeight === targetHeight) return;
                                    window.cancelAnimationFrame(iframeEl._ogpsrHeightAnimation);
                                    iframeEl._ogpsrHeightAnimation = 0;
                                }
                                iframeEl._ogpsrTargetHeight = targetHeight;

                                if (reduceMotion || Math.abs(currentHeight - targetHeight) < 2) {
                                    iframeEl.height = targetHeight;
                                    return;
                                }

                                var startHeight = currentHeight;
                                var startTime = null;
                                var animateHeight = function (timestamp) {
                                    if (startTime === null) startTime = timestamp;
                                    var progress = Math.min((timestamp - startTime) / 180, 1);
                                    var easedProgress = 1 - Math.pow(1 - progress, 3);
                                    iframeEl.height = Math.round(startHeight + ((targetHeight - startHeight) * easedProgress));

                                    if (progress < 1) {
                                        iframeEl._ogpsrHeightAnimation = window.requestAnimationFrame(animateHeight);
                                    } else {
                                        iframeEl.height = targetHeight;
                                        iframeEl._ogpsrHeightAnimation = 0;
                                    }
                                };

                                iframeEl._ogpsrHeightAnimation = window.requestAnimationFrame(animateHeight);
                            };

                            // Apply live merchant custom properties through CSSOM on the loaded preview stylesheet.
                            var applyDynamicCSSOM = function () {
                                // Sanitize and validate inputs before interpolating them into CSS.
                                var rawFontColor   = $('#ogpsr_font_color').val() || '#0f172a';
                                var rawAccentColor = $('#ogpsr_accent_color, #ogpsr_thermal_accent_color').filter(':visible').val() || $('#ogpsr_accent_color').val() || '#1e293b';
                                var rawFontFamily  = $('#ogpsr_font_family, #ogpsr_thermal_font_family').filter(':visible').val() || $('#ogpsr_font_family').val() || 'DejaVu Sans';
                                var rawFontSize    = $('#ogpsr_font_size').val();
                                var rawLineHeight  = $('#ogpsr_line_height').val();

                                // Strict font-family allowlist.
                                var allowedFonts = ['DejaVu Sans', 'Courier', 'Helvetica', 'Times', 'Verdana', 'Arial'];
                                var safeFontFamily = (typeof rawFontFamily === 'string' && allowedFonts.indexOf(rawFontFamily) !== -1)
                                    ? rawFontFamily
                                    : 'DejaVu Sans';

                                // Strict hexadecimal color validation.
                                var hexColorRegex = /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/;
                                var safeFontColor = (typeof rawFontColor === 'string' && hexColorRegex.test(rawFontColor.trim()))
                                    ? rawFontColor.trim()
                                    : '#0f172a';
                                var safeAccentColor = (typeof rawAccentColor === 'string' && hexColorRegex.test(rawAccentColor.trim()))
                                    ? rawAccentColor.trim()
                                    : '#1e293b';

                                // Strict numerical bounds: font size 6–24pt, line height 1–3, scale 0.1–5.
                                var parsedFontSize = parseFloat(rawFontSize);
                                var safeFontSize = (!isNaN(parsedFontSize) && parsedFontSize >= 6 && parsedFontSize <= 24)
                                    ? parsedFontSize
                                    : null;

                                var parsedLineHeight = parseFloat(rawLineHeight);
                                var safeLineHeight = (!isNaN(parsedLineHeight) && parsedLineHeight >= 1 && parsedLineHeight <= 3)
                                    ? parsedLineHeight
                                    : null;

                                var safeScale = (!isNaN(storedScale) && isFinite(storedScale) && storedScale >= 0.1 && storedScale <= 5)
                                    ? storedScale
                                    : 1;

                                var previewLink = doc.getElementById('ogpsr-preview-css');
                                var previewSheet = (previewLink && previewLink.sheet)
                                    ? previewLink.sheet
                                    : (doc.styleSheets && doc.styleSheets.length ? doc.styleSheets[0] : null);

                                if (previewSheet && typeof previewSheet.insertRule === 'function') {
                                    var dynamicCss = ':root { ' +
                                        '--ogpsr-scale: ' + safeScale + '; ' +
                                        '--ogpsr-font-family: "' + safeFontFamily + '", sans-serif; ' +
                                        '--ogpsr-font-color: ' + safeFontColor + '; ' +
                                        '--ogpsr-accent-color: ' + safeAccentColor + '; ' +
                                        (safeFontSize !== null ? ('--ogpsr-font-size: ' + safeFontSize + 'pt; ') : '') +
                                        (safeLineHeight !== null ? ('--ogpsr-line-height: ' + safeLineHeight + '; ') : '') +
                                    '}';

                                    try {
                                        previewSheet.insertRule(dynamicCss, previewSheet.cssRules.length);
                                    } catch (err) {
                                        // Keep the preview usable if the browser rejects a CSS rule.
                                    }
                                }

                                // Height fitting is best-effort and must never block the preview reveal.
                                try {
                                    fitPreviewHeight(safeScale);
                                    window.requestAnimationFrame(function () {
                                        fitPreviewHeight(safeScale);
                                    });
                                } catch (e) {
                                    // Keep the preview visible even if measurement fails.
                                }
                                revealPreview();
                            };

                            // Stylesheets are printed into the document head server-side, so they are already
                            // loaded when this load event fires. Adopt the enqueued stylesheets only for preview
                            // documents rendered without them, then wait for them from the parent window.
                            var stylesheetIds = ['ogpsr-document-template-css', 'ogpsr-preview-css'];
                            var adoptedLinks = [];

                            stylesheetIds.forEach(function (id) {
                                var parentLink = document.getElementById(id);

                                if (!parentLink || doc.getElementById(id) || !doc.head) {
                                    return;
                                }

                                var link = doc.createElement('link');
                                link.id = id;
                                link.rel = 'stylesheet';
                                link.href = parentLink.href;
                                doc.head.appendChild(link);
                                adoptedLinks.push(link);
                            });

                            if (!adoptedLinks.length) {
                                applyDynamicCSSOM();
                                return;
                            }

                            var sheetChecks = 0;
                            var waitForAdoptedSheets = function () {
                                sheetChecks++;
                                var sheetsReady = adoptedLinks.every(function (link) {
                                    return !!link.sheet;
                                });
                                if (sheetsReady || sheetChecks >= 40) {
                                    applyDynamicCSSOM();
                                    return;
                                }
                                window.setTimeout(waitForAdoptedSheets, 25);
                            };
                            waitForAdoptedSheets();
                        });

                        $iframe[0].srcdoc = response.data.html;
                    } else {
                        var errorMsg = response.data && response.data.message ? String(response.data.message) : (ogpsr_admin_data.strings.preview_unknown_error || 'Unknown Preview Error');
                        var safeError = $('<div>').text(errorMsg).html();
                        var safeTitle = $('<div>').text(ogpsr_admin_data.strings.preview_failed || 'Preview Failed').html();
                        if ($wrapper.length) {
                            $wrapper.addClass('ogpsr-hide');
                        }
                        if ($errorNotice.length) {
                            $errorNotice.html('<strong>' + safeTitle + '</strong>' + safeError).removeClass('ogpsr-hide');
                        }
                    }
                }).fail(function (xhr, status, error) {
                    if (requestId !== previewRequestSequence) return;
                    console.error('OGPSR Live Preview AJAX Failed:', status, error);
                    var $wrapper = $('#ogpsr-preview-wrapper');
                    var $errorNotice = $('#ogpsr-preview-error-notice');
                    var safeCode = parseInt(xhr.status, 10) || 0;
                    if ($wrapper.length) {
                        $wrapper.addClass('ogpsr-hide');
                    }
                    if ($errorNotice.length) {
                        var safeTitle = $('<div>').text(ogpsr_admin_data.strings.preview_failed || 'Preview Failed').html();
                        var networkMsg = (ogpsr_admin_data.strings.preview_network_error || 'Server or Network Error (Code: %s)').replace('%s', String(safeCode));
                        $errorNotice.html('<strong>' + safeTitle + '</strong>' + $('<div>').text(networkMsg).html()).removeClass('ogpsr-hide');
                    }
                }).always(function () {
                    if (requestId === previewRequestSequence) {
                        $loader.removeClass('active');
                    }
                });
            }, 250);
        }

        // Device Switcher Logic
        $(document).on('click', '.ogpsr-device-btn', function () {
            var $btn = $(this);
            var width = $btn.data('width');
            var format = $btn.data('format');

            $('.ogpsr-device-btn').removeClass('active');
            $btn.addClass('active');

            // Visual Resizing of the Canvas via stylesheet data attribute (Zero inline .css('width'))
            var containerWidthAttr = (format === 'thermal') ? width : '100%';
            $('#ogpsr-preview-wrapper').attr('data-ogpsr-preview-width', containerWidthAttr);
            updateLivePreview();
        });

        // Attach listeners to all settings fields (including Select2 multiselects)
        $(document).on('change select2:select select2:unselect keyup input', '#mainform :input, .ogpsr-admin-settings-column :input, [name^="ogpsr_"]', function () {
            updateLivePreview();
        });

        // Initialize Preview on load
        if ($('#ogpsr-live-preview').length) {
            var $activeBtn = $('.ogpsr-device-btn.active');
            if ($activeBtn.length) {
                var initFormat = $activeBtn.data('format');
                var initWidth = $activeBtn.data('width');
                var initWidthAttr = (initFormat === 'thermal') ? initWidth : '100%';
                $('#ogpsr-preview-wrapper').attr('data-ogpsr-preview-width', initWidthAttr);
            }
            updateLivePreview();
        }
    });
})(jQuery);
