=== SemiAuto Invoicing ===
Contributors: salmansh169
Tags: woocommerce, thermal receipt, packaging, pdf invoice, e-invoicing
Requires at least: 6.0
Tested up to: 7.1
Stable tag: 8.4.2
Requires PHP: 8.0
WC requires at least: 10.0.0
WC tested up to: 11.2.0
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0-standalone.html

Local PDFs & Global E-Invoicing compliance assistant with thermal receipts (44mm to 80mm), PDF invoices, and packaging slips for WooCommerce.

== Description ==

"semiauto-invoicing" operates as a standards-aligned document generation suite and compliance assistant for WooCommerce stores. Designed for flexibility, speed, and reliability, it assists merchants with formatting continuous thermal receipts (44mm - 80mm), professional A4 PDF invoices, and logistics packing slips directly from WooCommerce orders.


### Compliance Disclaimer

No plugin can provide complete legal, tax, or regulatory compliance. SemiAuto Invoicing operates as a software compliance assistant designed to help merchants format and generate, and automate documents aligned with officially published technical e-invoicing specifications.

Use of this plugin does not guarantee legal, regulatory, tax, or accounting compliance. Tax and document regulations vary by jurisdiction, business structure, and individual transaction requirements. Store owners remain solely responsible for ensuring their accounting records and invoicing workflows satisfy applicable local, national, and international laws. This software does not provide legal, tax, or accounting advice.

This plugin uses the [Freemius SDK](https://freemius.com) as a third-party service provider for license management and optional account/usage services. Use of Freemius is subject to the [Freemius Terms of Service](https://freemius.com/terms/) and [Freemius Privacy Policy](https://freemius.com/privacy/). Data sharing with Freemius occurs strictly upon administrative consent via the initial opt-in screen or account configuration within the plugin.

**Key Features (Free Core):**

*   **Continuous Thermal Receipts**: Optimized for 44mm, 57mm, 58mm, 76mm, and 80mm POS printers with automated "cut-line" formatting.
*   **Professional A4 PDF Invoices**: Clean 2-column header layouts with localized "Bill To", store branding, BACS direct bank transfer details, and order breakdown.
*   **Packaging Slips**: High-density logistics slips for picking and packing operations.
*   **Centralized Security Barrier**: Strict authorization checks for document downloads and defense against directory traversal.
*   **Local Rendering Engine**: High-speed, offline-first PDF generation without external API dependencies or remote tracking.
*   **Live Preview Studio**: Real-time canvas rendering with responsive device viewport switcher.
*   **Seamless Email Delivery**: Attach PDF invoices, packaging slips, or receipts directly to WooCommerce transactional customer emails.


== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/semiauto-invoicing` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to **WooCommerce -> Settings -> SemiAuto Invoicing** to configure store branding, template designs, and document options.

== Frequently Asked Questions ==

= How do I customize my store logo on invoices and receipts? =
Navigate to **WooCommerce -> Settings -> SemiAuto Invoicing -> Design Studio**, click **Upload Logo**, and select your store logo from the WordPress Media Library. The logo is resolved locally and embedded securely.

= Where are generated PDF documents stored? =
Documents are generated locally and stored inside your WordPress uploads directory (`wp-content/uploads/semiauto-invoicing/`). The plugin automatically generates defensive `.htaccess` (for Apache / LiteSpeed), `web.config` (for Microsoft IIS), and `index.php` silence files to block direct HTTP directory browsing and serves downloads through authenticated endpoints.

= How do I secure stored documents on Nginx servers? =
Because Nginx does not read runtime `.htaccess` files, add the following location block to your site's Nginx server configuration:

`location ^~ /wp-content/uploads/semiauto-invoicing/ { deny all; return 403; }`

This blocks direct web access to the physical storage folder while authenticated customer and admin downloads continue to function seamlessly through the plugin's secure PHP endpoints.

= Can developers filter the document storage root path? =
Yes, developers can customize the storage directory using the `ogpsr_document_storage_root` filter. The filter includes fail-safe sanitization and validation to ensure compatibility with your host environment.

= Can customers download their invoices from My Account? =
Yes, authenticated customers can download their PDF invoices directly from the WooCommerce My Account orders screen.


= Is this plugin compatible with High-Performance Order Storage (HPOS)? =
Yes, SemiAuto Invoicing is declared and tested for WooCommerce High-Performance Order Storage (HPOS / Custom Order Tables).

= How does the plugin handle GDPR / Erasure and Document Retention? =
SemiAuto integrates natively with the WordPress Personal Data Exporter and Eraser.

Document Retention: SemiAuto may retain generated invoices, receipts, and other financial records for statutory tax, accounting, or record-keeping requirements. Where retention applies, personal data deletion requests do not cause immediate physical deletion of retained financial documents.

During erasure, non-statutory caches and session tokens are wiped immediately. Commercial records are retained by default to ensure tax compliance; customize via the `ogpsr_privacy_retain_financial_records` filter.

= What data does SemiAuto store and what is the data lifecycle? =
SemiAuto processes order data locally from WooCommerce:
1. Generation: PDF documents are generated locally and stored in `wp-content/uploads/semiauto-invoicing/`.
2. Delivery: Served through authenticated endpoints or standard WooCommerce customer emails.
3. Retention: Non-statutory caches purge automatically; statutory invoices are retained until audit windows expire.

Data Inventory:
* Customer name & address: From WooCommerce; formatted inside generated PDF documents.
* Order ID & invoice number: From WooCommerce; stored in order meta.
* Generated documents: Stored locally in protected server directories.
* Diagnostic logs: Stored locally; strictly excludes sensitive passwords or full document dumps.
* Freemius Telemetry (Opt-in only): Admin contact info & technical metadata for licensing/updates; never collects customer transactional data.

= Which image formats are supported for store logos? =
SemiAuto Invoicing supports PNG, WebP, and JPEG images. For optimal print clarity on continuous thermal rolls and high-resolution A4 prints, we recommend a PNG with a transparent background (at least 600px wide). Vector SVGs are excluded by design to prevent document rendering distortion and guarantee strict PDF/A-3 standard compliance and server security.

== Source Code & Build Process ==

* Public Source Repository: https://github.com/ouraglobal/semiauto-invoicing/
* Build Process: All JavaScript and CSS source files in the `assets/` directory are unminified, human-readable Vanilla JavaScript and CSS written directly for native browser execution. No compilation or transpilation build step is required.

== Upgrade Notice ==

= 8.4.2 =
Hardens document stylesheet loading via WP_Filesystem, removes client-side inline .style DOM mutations, and documents preview iframe architecture.

= 8.4.1 =
Refactors PDF document styling to use programmatic Dompdf stylesheet injection, completely eliminates manual <style> and inline style tags across PHP and JavaScript, and implements native WordPress DOM error handling.

== Screenshots ==

1. Plugin Settings Page
2. Thermal Receipt Example
3. A4 Invoice Example

== Changelog ==

= 8.4.2 =
* Core: Refactored document stylesheet loading in compile_document_rules() to read exclusively from local plugin assets via WP_Filesystem, preventing external CSS injection.
* Admin: Added architectural documentation docblocks to preview renderer explaining isolated iframe (srcdoc) stylesheet serialization via wp_print_styles().
* JavaScript: Eliminated client-side .style and .style.setProperty() inline DOM mutations in admin scripts, migrating preview scaling to browser CSSOM rules and container data attributes.
* Architecture: Strengthened rules file architectural boundaries, ensuring all rule specifications remain strictly bounded and modular.

= 8.4.1 =
* Core: Programmatically inject dynamic PDF CSS declarations directly into the Dompdf stylesheet tree via `$dompdf->getCss()->load_css()`, completely removing all `<style>` tags from offline document templates.
* Admin: Converted XML data stream preview containers to pure semantic CSS classes powered by enqueued stylesheets (`wp_enqueue_style()`), eliminating all inline `style="..."` attributes.
* JavaScript: Replaced raw HTML string concatenation in live preview error states with native DOM container toggling (`#ogpsr-preview-error-notice`), eliminating all literal `<style>` tags from client-side scripts.
* Compliance: Documented public source repository link and unminified Vanilla JS/CSS architecture under `== Source Code & Build Process ==` per WordPress.org Guideline #4.

= 8.4.0 =
* Security: Strictly enqueued all admin settings, preview canvas, and diagnostic styles and scripts via standard WordPress APIs, completely eliminating inline <style> and <script> blocks.
* Core: Fortified WP_Filesystem API operations and atomic artifact publishing across all document rendering pipelines.
* Standards: Upgraded PHP and WooCommerce compatibility declarations for streamlined store deployments.
* Architecture: Preserved strict Free vs. Premium pure delegation boundaries with zero trialware residue in Free distribution builds.

= 8.3.0 =
* Compatibility: Declared full compatibility with WooCommerce 11.2.0.
* WordPress: Added the 'Requires Plugins: woocommerce' header in compliance with modern WordPress core plugin dependency standards.
* I18n: Hardened Personal Data Erasure gettext translation strings with explicit format assignments, ensuring complete comment-string pairing integrity across deployment packagers.
* Architecture: Preserved strict Free vs. Premium pure delegation boundaries with zero trialware residue in Free distribution builds.

= 8.2.9 =
* Core: Hardened logo selection in admin settings with native WordPress Media Library modal filtering restricted strictly to supported raster formats (PNG, WebP, JPEG, GIF).
* Documentation: Updated logo setting description and FAQ with explicit raster format guidance and technical rationale for vector SVG exclusion to ensure PDF/A print fidelity and server security.
* Standards: Unified PHPDoc @package docblock declarations across all directory index silence files and handlers to SemiAutoInvoicing in full alignment with WordPress Coding Standards (WPCS).
* Architecture: Preserved Free vs. Premium pure delegation boundaries and clean zero-trialware settings schema across all document generation pipelines.

[See changelog.txt for release history prior to v8.2.9]

== Third-Party Libraries & Copyright ==

This plugin bundles the following third-party software and open-source libraries:

* Dompdf (HTML to PDF rendering engine)
  License: LGPL-2.1-or-later
  Source: https://github.com/dompdf/dompdf

* php-font-lib (TrueType font parsing and subsetting)
  License: LGPL-2.1-or-later
  Source: https://github.com/dompdf/php-font-lib

* php-svg-lib (SVG image parser for PDF generation)
  License: LGPL-3.0-or-later
  Source: https://github.com/dompdf/php-svg-lib

* Masterminds HTML5-PHP (Standards-compliant HTML5 parser)
  License: MIT
  Source: https://github.com/Masterminds/html5-php

* Sabberworm PHP CSS Parser (CSS stylesheet parser)
  License: MIT
  Source: https://github.com/MyIntervals/PHP-CSS-Parser

* Freemius WordPress SDK (Freemius integration and software licensing)
  License: GPL-3.0-or-later
  Source: https://github.com/Freemius/wordpress-sdk


