# SMTP / email setup
Admin → **Email Settings**: sender name/email, host, port, encryption, username, password → Save → **Send test**.
| Provider | Host | Port | Encryption |
|---|---|---|---|
| Gmail (App Password) | smtp.gmail.com | 587 | TLS |
| Brevo | smtp-relay.brevo.com | 587 | TLS |
| Zoho | smtp.zoho.in | 465 | SSL |
Notes: the password is stored encrypted with `APP_KEY`; leave the field blank to keep it. If nothing is saved in Admin, `.env` `SMTP_*` / `MAIL_FROM_*` are used. The test button shows the real error from the SMTP server. Order confirmation emails are sent only when SMTP is configured and the customer entered an email; failures never block an order and are logged to `storage/logs/`.
