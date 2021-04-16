# paymentnepal-WP-woo-commerce
Copy "woocommerce_paymentnepal" to your-domain/wp-content/plugins

Activate plugin

After plugin activation fill secret key and payment key. You can obtain them in your paymentnepal.com merchant area

In paymentnepal.com service settings fill in:

Notification URL: http://your_domain/?wc-api=wc_paymentnepal&paymentnepal=result

Success URL: http://your_domain/?wc-api=wc_paymentnepal&paymentnepal=success

Fail Url: http://your_domain/?wc-api=wc_paymentnepal&paymentnepal=fail
