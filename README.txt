DunkHome Kicks - Enhanced Project

UI PRESERVED
- Existing supplied storefront/auth/admin UI was kept as the base.
- Existing green/black visual language is retained.
- Theme toggle is available at the top of pages and persists through localStorage.
- Page transitions are handled by assets/dunkhome-ui.js.
- Browser favicon references image/logo.jpeg on all user-facing PHP pages.

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

PRODUCT STORAGE
- Uploaded images are stored in uploads/products/.
- Accepted formats: JPG, PNG, WEBP.
- Maximum size: 5 MB per image.

MAIL / OTP
- Mailer.php uses PHP mail(). Configure your server SMTP/mail transport for production.
- OTP expiry is 10 minutes.

DATABASE
- Existing database name: dunkhome_kicks.
- Existing users/admin tables are preserved.
- Products table is created automatically by db.php.

LOGO
- image/logo.jpeg is included as a fallback file because the supplied file set did not include the original image asset. Replace it with your original image/logo.jpeg to use your exact logo without changing any PHP.

RUN
1. Put the project inside your PHP server folder (XAMPP htdocs, WAMP www, etc.).
2. Make sure MySQL database dunkhome_kicks is available.
3. Verify db.php credentials.
4. Make uploads/products writable by PHP.
5. Configure PHP mail/SMTP for OTP delivery.
