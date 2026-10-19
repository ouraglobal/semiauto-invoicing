<?php
/**
 * Customer Document Email
 *
 * WooCommerce email used to send a generated PDF document (receipt, invoice or
 * packaging slip) to the customer on demand from the order screen.
 *
 * @package SemiAutoInvoicing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'OG_PSR_Email_Customer_Document' ) && class_exists( 'WC_Email' ) ) {

	/**
	 * Sends a generated order document to the customer through the WooCommerce mailer.
	 */
	class OG_PSR_Email_Customer_Document extends WC_Email {

		/**
		 * Translated document label (e.g. Receipt, Invoice).
		 *
		 * @var string
		 */
		public $document_label = '';

		/**
		 * Constructor.
		 */
		public function __construct() {
			$this->id             = 'ogpsr_customer_document';
			$this->customer_email = true;
			$this->manual         = true;
			$this->title          = __( 'SemiAuto Invoicing: Customer document', 'semiauto-invoicing' );
			$this->description    = __( 'Sent to the customer when a generated receipt, invoice or packaging slip is emailed from the order screen.', 'semiauto-invoicing' );
			$this->template_html  = 'emails/ogpsr-customer-document.php';
			$this->template_plain = 'emails/plain/ogpsr-customer-document.php';
			$this->template_base  = OG_PSR_PATH . 'templates/';
			$this->placeholders   = array(
				'{order_number}'  => '',
				'{document_type}' => '',
			);

			parent::__construct();
		}

		/**
		 * Default email subject.
		 *
		 * @return string
		 */
		public function get_default_subject() {
			/* translators: Email subject placeholders: {document_type} is the document type (e.g. Receipt), {order_number} is the order number. */
			return __( 'Your {document_type} for Order #{order_number}', 'semiauto-invoicing' );
		}

		/**
		 * Default email heading.
		 *
		 * @return string
		 */
		public function get_default_heading() {
			/* translators: Email heading placeholders: {document_type} is the document type (e.g. Receipt), {order_number} is the order number. */
			return __( 'Your {document_type} for Order #{order_number}', 'semiauto-invoicing' );
		}

		/**
		 * Email subject, stripped of line breaks and control characters (header injection defense).
		 *
		 * @return string
		 */
		public function get_subject() {
			return sanitize_text_field( parent::get_subject() );
		}

		/**
		 * Send the document to the order's billing email address.
		 *
		 * @param WC_Order $order          Order object.
		 * @param string   $file_path      Absolute path of the validated document file.
		 * @param string   $document_label Translated document label.
		 * @return bool Whether the email was sent.
		 */
		public function trigger( $order, $file_path, $document_label ) {
			if ( ! $order instanceof WC_Order || ! $this->is_enabled() ) {
				return false;
			}

			$this->setup_locale();

			$this->object         = $order;
			$this->recipient      = $order->get_billing_email();
			$this->document_label = sanitize_text_field( (string) $document_label );

			$this->placeholders['{order_number}']  = sanitize_text_field( (string) $order->get_order_number() );
			$this->placeholders['{document_type}'] = $this->document_label;

			$sent = false;
			if ( $this->get_recipient() ) {
				$sent = (bool) $this->send(
					$this->get_recipient(),
					$this->get_subject(),
					$this->get_content(),
					$this->get_headers(),
					array_merge( $this->get_attachments(), array( $file_path ) )
				);
			}

			$this->restore_locale();

			return $sent;
		}

		/**
		 * HTML email content.
		 *
		 * @return string
		 */
		public function get_content_html() {
			return wc_get_template_html(
				$this->template_html,
				array(
					'order'              => $this->object,
					'document_label'     => $this->document_label,
					'email_heading'      => $this->get_heading(),
					'additional_content' => $this->get_additional_content(),
					'sent_to_admin'      => false,
					'plain_text'         => false,
					'email'              => $this,
				),
				'',
				$this->template_base
			);
		}

		/**
		 * Plain text email content.
		 *
		 * @return string
		 */
		public function get_content_plain() {
			return wc_get_template_html(
				$this->template_plain,
				array(
					'order'              => $this->object,
					'document_label'     => $this->document_label,
					'email_heading'      => $this->get_heading(),
					'additional_content' => $this->get_additional_content(),
					'sent_to_admin'      => false,
					'plain_text'         => true,
					'email'              => $this,
				),
				'',
				$this->template_base
			);
		}
	}
}
