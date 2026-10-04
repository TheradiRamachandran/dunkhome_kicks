DunkHome Kicks - Enhanced Project

UI PRESERVED
- Existing supplied storefront/auth/admin UI was kept as the base.
- Administration pages are organized in Admin/; customer pages are organized in User/.
- Use the Admin/ and User/ paths for page URLs; page implementations live in those folders.
- Shared configuration, sessions, assets, images and order helpers remain shared at the project root.
- Existing green/black visual language is retained.
- Theme toggle is available at the top of pages and persists through localStorage.
- Page transitions are handled by assets/dunkhome-ui.js.
- Browser favicon references image/logo.jpg on all user-facing PHP pages.

ENHANCEMENTS
- AddProduct.php: admin-only product creation with name, category, price, description and exactly 3 product photos. No quantity field is used.
- Products.php: product collection page.
- ProductDetails.php: three-photo product detail page.
- ForgotPassword.php: user email -> OTP -> new password flow.
- AdminForgotPassword.php: admin email -> OTP -> new password flow.
- Logout.php: session logout for user/admin.
- Home.php: storefront home entry using the existing index UI.
- Contact.php: contact/support page using the same visual theme.
- Dashboard.php: authenticated user session page.
- index.php: featured products are loaded from the products table and link to ProductDetails.php.
- AdminDashboard.php: Products and Add Product navigation links added.
- db.php: automatically creates the products table if it does not exist.
- schema.sql: optional complete database schema.
- Existing installations created from the older orders schema must apply database/migrations/002_legacy_orders_checkout.sql once before placing new bookings.

PRODUCT STORAGE
- Uploaded images are stored in uploads/products/.
- Accepted formats: JPG, PNG, WEBP.
- Maximum size: 5 MB per image.

MAIL / OTP
- Mailer.php uses PHPMailer over authenticated SMTP for OTP, contact, and order emails.
- All admin and customer email actions call the shared Mailer.php SMTP transport. Configure settings once for every flow.
- Run `composer install` locally and upload the generated `vendor/` directory with the application.
- On the hosting account, set `DUNKHOME_MAIL_FROM=Dunkhomekicks@gmail.com`, `DUNKHOME_SMTP_HOST=smtp.gmail.com`, `DUNKHOME_SMTP_PORT=587`, `DUNKHOME_SMTP_SECURE=tls`, `DUNKHOME_SMTP_USER=Dunkhomekicks@gmail.com`, and `DUNKHOME_SMTP_PASS` to the Google app password. Set this in the host's PHP/application environment configuration (not in source control), then reload PHP so the application can read it.
- If environment variables are unavailable, create `includes/mail-config.local.php` on the server as a PHP file that returns an array containing the SMTP settings above. This private file is excluded from Git; do not upload it to a public repository or share its contents.
- Admin and customer email flows share this SMTP configuration. First-admin signup is available online while no admin account exists and requires email OTP verification; it closes automatically after the first admin is created. No admin setup key is required.
- Gmail SMTP requires 2-Step Verification and an app password; a normal Gmail password will not work. The hosting provider must allow outbound SMTP connections on port 587.
- OTP expiry is 10 minutes.
- Booking confirmation email is sent to the customer and to DUNKHOME_ORDER_ADMIN_EMAIL (defaults to Dunkhomekicks@gmail.com); mail delivery status is shown on the booking confirmation page.
- Customers receive an email after an admin changes a booking status; no email is sent when the status remains unchanged. Status emails include the tracking link and any store note.
- The customer Contact page lists +91 7012372216 and Dunkhomekicks@gmail.com; contact form messages are sent to that support email through configured SMTP.
- Set DUNKHOME_PUBLIC_URL to the public application base URL so email and WhatsApp tracking links work outside the local machine.
- Automatic WhatsApp order alerts use the WhatsApp Cloud API. Configure the business sender as +91 7012372216 only if this number is registered with WhatsApp Cloud API; the admin recipient defaults to 919003341515. Configure DUNKHOME_WHATSAPP_ACCESS_TOKEN, DUNKHOME_WHATSAPP_PHONE_NUMBER_ID, and DUNKHOME_WHATSAPP_API_VERSION as server environment variables; optionally set DUNKHOME_ORDER_WHATSAPP_TO to change the recipient.
- Create and approve a WhatsApp template named DUNKHOME_WHATSAPP_ORDER_TEMPLATE (defaults to dunkhome_new_order), set DUNKHOME_WHATSAPP_ORDER_TEMPLATE_LANGUAGE (defaults to en_US) to its language, and ensure the recipient has opted in. Its body should be `New DunkHome booking {{1}}. Customer {{2}} ({3}). Total {{4}}. Track: {{5}}.`; the five parameters are booking reference, customer name, mobile, total, and tracking URL.
- Store WhatsApp credentials in server environment configuration, never in source files. If the WhatsApp API is not configured or rejects delivery, the confirmation page reports the issue and provides a manual WhatsApp link.
- A booking is recorded as Pending; the current checkout does not collect payment.

ADMIN BOOTSTRAP
- The first admin account can be created from localhost when no admins exist.
- Remote first-admin setup is blocked; create the initial account locally before deployment.
- Admin registration also requires email OTP verification.

DATABASE
- Existing database name: dunkhome_kicks for local XAMPP. On hosting, use the MySQL hostname, username, database name, and password shown in the hosting control panel. Set `DHK_DB_HOST`, `DHK_DB_USER`, `DHK_DB_PASS`, and `DHK_DB_NAME` as environment variables, or add those keys to the private `includes/mail-config.local.php` array. Never use local XAMPP credentials on hosting.
- Existing users/admin tables are preserved.
- Products table is created automatically by db.php.

LOGO
- image/logo.jpg is included as a fallback file because the supplied file set did not include the original image asset. Replace it with your original image/logo.jpg to use your exact logo without changing any PHP.

RUN
1. Put the project inside your PHP server folder (XAMPP htdocs, WAMP www, etc.).
2. Make sure MySQL database dunkhome_kicks is available.
3. Verify db.php credentials.
4. Make uploads/products writable by PHP.
5. Run `composer install`, upload `vendor/`, and configure SMTP using environment variables or the private server-side file described above.
