jQuery(document).ready(function($) {
	$('#mailboxvalidator-email-validator-feedback-modal').dialog({
		// title: 'Quick Feedback',
		title: object_name.some_string,
		dialogClass: 'wp-dialog',
		autoOpen: false,
		draggable: false,
		width: 'auto',
		modal: true,
		resizable: false,
		closeOnEscape: false,
		position: {
			my: 'center',
			at: 'center',
			of: window
		},
				
		open: function() {
			$('.ui-widget-overlay').bind('click', function() {
				$('#mailboxvalidator-email-validator-feedback-modal').dialog('close');
			});
		},
			
		create: function() {
			$('.ui-dialog-titlebar-close').addClass('ui-button');
		},
	});

	$('.deactivate a').each(function(i, ele) {
		if ($(ele).attr('href').indexOf('mailboxvalidator-email-validator') > -1) {
			$('#mailboxvalidator-email-validator-feedback-modal').find('a').attr('href', $(ele).attr('href'));

			$(ele).on('click', function(e) {
				e.preventDefault();

				$('#mailboxvalidator-email-validator-feedback-response').html('');
				$('#mailboxvalidator-email-validator-feedback-modal').dialog('open');
			});

			$('input[name="mailboxvalidator-email-validator-feedback"]').on('change', function(e) {
				if($(this).val() == 4) {
					$('#mailboxvalidator-email-validator-feedback-other').show();
				} else {
					$('#mailboxvalidator-email-validator-feedback-other').hide();
				}
			});

			$('#mailboxvalidator-email-validator-submit-feedback-button').on('click', function(e) {
				e.preventDefault();

				$('#mailboxvalidator-email-validator-feedback-response').html('');

				if (!$('input[name="mailboxvalidator-email-validator-feedback"]:checked').length) {
					// $('#mailboxvalidator-email-validator-feedback-response').html('<div style="color:#cc0033;font-weight:800">Please select your feedback.</div>');
					$('#mailboxvalidator-email-validator-feedback-response').html('<div style="color:#cc0033;font-weight:800">' + object_name.some_string1 + '.</div>');
				} else {
					$(this).val('Loading...');
					$.post(ajaxurl, {
						action: 'mailboxvalidator_email_validator_submit_feedback',
						feedback: $('input[name="mailboxvalidator-email-validator-feedback"]:checked').val(),
						others: $('#mailboxvalidator-email-validator-feedback-other').val(),
					}, function(response) {
						window.location = $(ele).attr('href');
					}).always(function() {
						window.location = $(ele).attr('href');
					});
				}
			});
		}
	});
});