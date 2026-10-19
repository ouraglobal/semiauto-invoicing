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

            $.post(getAjaxUrl(), {
                action: 'ogpsr_regenerate_file',
                order_id: order_id,
                format: format,
                nonce: nonce
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
        function updateLivePreview() {
            if (!$('#ogpsr-live-preview').length) return;

            clearTimeout(previewTimeout);
            previewTimeout = setTimeout(function () {
                var $loader = $('.ogpsr-preview-loader');
                var $iframe = $('#ogpsr-preview-iframe');

                if (!$iframe.length) return;

                var activeDevice = $('.ogpsr-device-btn.active');
                if (!activeDevice.length) return;

                $loader.addClass('active');

                var containerWidth = $('#ogpsr-preview-wrapper').width();
                var format = activeDevice.data('format');
                var widthAttr = activeDevice.data('width');
                var docWidthPx = 794; // Default A4 (210mm)

                if (format === 'thermal') {
                    docWidthPx = parseInt(widthAttr) * 3.78;
                }

                var scale = Math.min(containerWidth / docWidthPx, 1);

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
                    format: activeDevice.data('format'),
                    width: activeDevice.data('width'),
                    settings: settings,
                    nonce: previewNonce
                }, function (response) {
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
                        $iframe.off('load.ogpsr').on('load.ogpsr', function () {
                            var doc = this.contentWindow.document;
                            var storedScale = parseFloat(this.dataset.ogpsrScale || 1);
                            var activeWidth = activeDevice.data('width') || '80mm';

                            doc.documentElement.setAttribute('data-ogpsr-width', activeWidth);

                            // Apply live merchant custom properties via in-memory CSSOM (Zero inline style="" attributes)
                            var fontColor   = $('#ogpsr_font_color').val() || '#0f172a';
                            var accentColor = $('#ogpsr_accent_color, #ogpsr_thermal_accent_color').filter(':visible').val() || $('#ogpsr_accent_color').val() || '#1e293b';
                            var fontFamily  = $('#ogpsr_font_family, #ogpsr_thermal_font_family').filter(':visible').val() || $('#ogpsr_font_family').val() || 'DejaVu Sans';
                            var fontSize    = $('#ogpsr_font_size').val();
                            var lineHeight  = $('#ogpsr_line_height').val();

                            var previewSheet = doc.getElementById('ogpsr-preview-css') ? doc.getElementById('ogpsr-preview-css').sheet : (doc.styleSheets ? doc.styleSheets[0] : null);
                            if (previewSheet && typeof previewSheet.insertRule === 'function') {
                                var dynamicCss = ':root { ' +
                                    '--ogpsr-scale: ' + storedScale + '; ' +
                                    '--ogpsr-font-family: "' + fontFamily + '", sans-serif; ' +
                                    '--ogpsr-font-color: ' + fontColor + '; ' +
                                    '--ogpsr-accent-color: ' + accentColor + '; ' +
                                    (fontSize ? ('--ogpsr-font-size: ' + fontSize + 'pt; ') : '') +
                                    (lineHeight ? ('--ogpsr-line-height: ' + lineHeight + '; ') : '') +
                                '}';
                                try {
                                    previewSheet.insertRule(dynamicCss, previewSheet.cssRules.length);
                                } catch (err) {}
                            }

                            try {
                                var scrollH = doc.documentElement.scrollHeight || doc.body.scrollHeight;
                                if (scrollH && scrollH > 50) {
                                    var fittedH = Math.ceil(scrollH * storedScale) + 20;
                                    this.height = Math.max(fittedH, 480);
                                }
                            } catch (e) { }
                        });

                        $iframe[0].srcdoc = response.data.html;
                    } else {
                        var errorMsg = response.data && response.data.message ? String(response.data.message) : 'Unknown Preview Error';
                        var safeError = $('<div>').text(errorMsg).html();
                        if ($wrapper.length) {
                            $wrapper.addClass('ogpsr-hide');
                        }
                        if ($errorNotice.length) {
                            $errorNotice.html('<strong>Preview Failed</strong>' + safeError).removeClass('ogpsr-hide');
                        }
                    }
                }).fail(function (xhr, status, error) {
                    console.error('OGPSR Live Preview AJAX Failed:', status, error);
                    var $wrapper = $('#ogpsr-preview-wrapper');
                    var $errorNotice = $('#ogpsr-preview-error-notice');
                    var safeCode = parseInt(xhr.status, 10) || 0;
                    if ($wrapper.length) {
                        $wrapper.addClass('ogpsr-hide');
                    }
                    if ($errorNotice.length) {
                        $errorNotice.html('<strong>Preview Failed</strong>Server or Network Error (Code: ' + safeCode + ')').removeClass('ogpsr-hide');
                    }
                }).always(function () {
                    $loader.removeClass('active');
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
