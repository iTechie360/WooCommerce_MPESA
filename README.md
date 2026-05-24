# WooCommerce M-PESA Gateway

A custom **M-Pesa STK Push payment gateway plugin** for WooCommerce by iTechie 360.

Allows customers to make secure payments via Safaricom M-Pesa directly from WooCommerce checkout using STK Push.

---

# Features

- WooCommerce payment gateway integration
- M-Pesa STK Push checkout
- Customer phone number collection
- Sandbox and Production support
- Automatic payment callback handling
- Automatic order status updates
- Secure Daraja API authentication
- Admin configuration dashboard
- Stock reduction after successful payment

---

# Requirements

Before installation, ensure your server has:

- WordPress 6.0+
- WooCommerce installed and activated
- PHP 7.4+
- SSL certificate (required for production)
- Safaricom Daraja API credentials

You must have:

- Consumer Key
- Consumer Secret
- Business Shortcode
- Passkey

Get credentials from:

https://developer.safaricom.co.ke

---

# Installation

## Method 1: Upload ZIP

1. Compress plugin folder:

```bash
WooCommerce_MPESA.zip
```

2. Login to WordPress Admin

3. Go to:

Plugins → Add New → Upload Plugin

4. Upload ZIP

5. Activate plugin

---

## Method 2: Manual Upload

Upload plugin folder to:

```bash
/wp-content/plugins/WooCommerce_MPESA/
```

Then activate from:

WordPress Admin → Plugins

---

# IMPORTANT INSTALL ORDER

Install in this exact order:

## Step 1

Install WordPress

## Step 2

Install and activate WooCommerce

## Step 3

Complete WooCommerce setup wizard

## Step 4

Install and activate Pesa360

---

# If Website Crashes After Activation

This usually means WooCommerce is missing or inactive.

Symptoms:

- White screen
- Admin dashboard inaccessible
- Frontend broken
- Fatal PHP errors

Fix:

Login to cPanel or File Manager

Delete:

```bash
/wp-content/plugins/pesa360/
```

Then:

1. Install WooCommerce
2. Activate WooCommerce
3. Reinstall Pesa360

---

# Configuration

Go to:

WooCommerce → Settings → Payments → Pesa360

Enable:

- Enable Gateway

Enter:

- Consumer Key
- Consumer Secret
- Business Shortcode
- Passkey

Choose environment:

- Sandbox
- Production

Save changes

---

# Sandbox Testing

Use Safaricom sandbox credentials.

Test number format:

```bash
2547XXXXXXXX
```

Example:

```bash
254712345678
```

---

# Production Setup

Before going live:

- Switch environment to Production
- Replace sandbox credentials
- Set valid callback URL
- Enable HTTPS

Callback URL:

```bash
https://yourdomain.com/wc-api/mpesa_callback/
```

Register callback with Safaricom.

---

# How Payments Work

1. Customer selects Pesa360
2. Enters M-Pesa phone number
3. Clicks Place Order
4. STK Push sent to phone
5. Customer enters PIN
6. Safaricom confirms payment
7. WooCommerce marks order complete

---

# File Structure

```bash
WooCommerce_MPESA/
│
├── woocommerce_mpesa.php
├── README.md
└── assets/
    └── mpesa-logo.png
```

---

# Security Notes

This plugin:

- Sanitizes phone inputs
- Uses OAuth access tokens
- Uses secure API requests
- Stores checkout request IDs for verification

Never expose:

- Consumer Secret
- Passkey

---

# Troubleshooting

## "Could not connect to M-Pesa"

Possible causes:

- Invalid credentials
- Safaricom API downtime
- Wrong environment selected

---

## STK Push not received

Check:

- Phone format is 2547XXXXXXXX
- Daraja app is approved
- Shortcode is active

---

## Payment succeeds but order not updated

Check callback URL accessibility.

Test:

```bash
https://yourdomain.com/wc-api/mpesa_callback/
```

Should return:

```json
{"ResultCode":0,"ResultDesc":"Accepted"}
```

---

# Developer Fix (Critical)

Add this to avoid WooCommerce dependency crash:

```php
if (!class_exists('WC_Payment_Gateway')) {
    return;
}
```

Inside:

```php
init_my_mpesa_gateway()
```

This prevents fatal errors when WooCommerce is missing.

---

# Author

**iTechie 360**

Website:

https://www.itechie360.com

---

# License

MIT License

---

# Version

1.0.0

---

# Support

For support:

itechie360@gmail.com