# Gmail email setup for XAMPP

The order confirmation and password reset emails use Gmail SMTP. The sender
needs a Gmail address and a Google App Password; a normal Gmail password will
not work.

1. Enable 2-Step Verification on the Google account, then create an App
   Password for this application.
2. Open `C:\xampp\apache\conf\httpd.conf` as an administrator and add these
   lines at the end of the file, replacing the example values:

   ```apache
   SetEnv SHOP_SMTP_HOST smtp.gmail.com
   SetEnv SHOP_SMTP_PORT 587
   SetEnv SHOP_SMTP_USERNAME your-address@gmail.com
   SetEnv SHOP_SMTP_PASSWORD your-new-16-character-app-password
   SetEnv SHOP_SMTP_FROM_EMAIL your-address@gmail.com
   ```

3. Save the file and restart Apache from the XAMPP Control Panel.
4. Open Command Prompt, install the project dependencies, set the SMTP
   variables for this Command Prompt session, then send a test message. Replace
   the example values with your own; do not save credentials in project files:

   ```bat
   cd /d C:\xampp\htdocs\project
   set PATH=C:\xampp\php;%PATH%
   composer install
   set SHOP_SMTP_HOST=smtp.gmail.com
   set SHOP_SMTP_PORT=587
   set SHOP_SMTP_USERNAME=your-address@gmail.com
   set SHOP_SMTP_PASSWORD=your-new-16-character-app-password
   set SHOP_SMTP_FROM_EMAIL=your-address@gmail.com
   set SHOP_SMTP_TEST_RECIPIENT=your-test-inbox@example.com
   C:\xampp\php\php.exe phptestmail.php
   ```

   These `set` values only apply to the current Command Prompt. Apache uses
   the `SetEnv` values configured in `httpd.conf`; restart Apache after changing
   those settings. Then place a test order. The order-confirmation email includes
   the order number, item names, quantities, line totals, customer details, and
   order total. If email fails, check the PHP/Apache error log; do not share your
   App Password in a bug report or chat.

Keep the account password and App Password out of this project and its source
files. `SHOP_SMTP_FROM_EMAIL` is optional and defaults to
`SHOP_SMTP_USERNAME`. `SHOP_SMTP_TEST_RECIPIENT` is only used by the CLI test
script and should be set to an inbox you can check.

Do not put real SMTP credentials in project files. If an App Password was
previously committed, shared, or otherwise exposed, revoke it in your Google
Account and generate a new one before configuring Apache.
