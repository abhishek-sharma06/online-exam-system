# Email Verification System - Setup & Installation Guide

## 📋 Overview

This is a complete email verification system using:
- **PHP** for backend logic
- **MySQL** for database
- **PHPMailer** for email sending
- **SendGrid SMTP** for reliable email delivery
- **Bootstrap** for UI

## 🔧 Installation Steps

### Step 1: Install PHPMailer via Composer

First, ensure you have Composer installed on your server.

```bash
# On your local machine or server terminal
cd /path/to/exam_system
composer install
```

This will install PHPMailer and create `vendor/` directory with autoloader.

**If Composer is not available on InfinityFree:**
1. Download PHPMailer from: https://github.com/PHPMailer/PHPMailer/releases
2. Extract to `vendor/phpmailer/phpmailer/`
3. Or upload the pre-installed `vendor/` folder

### Step 2: Get SendGrid API Key

1. Sign up for free at: https://sendgrid.com (Free tier: 100 emails/day)
2. Go to: Settings → API Keys
3. Create new API Key
4. Copy the API key

### Step 3: Configure SendGrid Settings

Edit `config/phpmailer.php`:

```php
define('SENDGRID_API_KEY', ''); // Your SendGrid API key
define('SENDER_EMAIL', 'your-email@yourdomain.com'); // Your sender email
define('VERIFICATION_URL', 'https://yourdomain.com/verify.php'); // Your verification URL
define('TOKEN_EXPIRY_MINUTES', 15); // Token expiration time
```

### Step 4: Set Up Database

Run the migration script in your browser:

```
http://localhost/exam_system/setup_email_verification.php
```

Or manually run SQL:

```sql
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_verified TINYINT(1) DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS verification_token VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS token_expiry TIMESTAMP NULL;
```

### Step 5: Update User Registration Flow

Update your `index.php` or main page to link to new registration:

```html
<!-- Old registration link -->
<a href="register.php">Register</a>

<!-- New registration with email verification -->
<a href="register_email.php">Register with Email Verification</a>
```

---

## 📁 File Structure

```
exam_system/
├── config/
│   ├── database.php
│   ├── phpmailer.php (NEW - SendGrid config)
│   └── email.php
├── includes/
│   ├── mailer.php (NEW - PHPMailer helper)
│   ├── auth.php
│   └── functions.php
├── vendor/ (PHPMailer library - from composer install)
│   └── phpmailer/
│       └── phpmailer/
├── register_email.php (NEW - Registration form)
├── login_email.php (NEW - Login with verification check)
├── verify.php (NEW - Email link handler)
├── setup_email_verification.php (NEW - Database migration)
├── composer.json (NEW - Composer dependencies)
└── ...
```

---

## 🔄 User Flow

### Registration
```
User fills form → register_email.php
↓
Password hashed + Token generated
↓
User saved to database (is_verified = 0)
↓
Email sent via SendGrid SMTP
↓
Email contains link: /verify.php?token=ABC123...
↓
User sees: "Check your email for verification link"
```

### Email Verification
```
User receives email
↓
User clicks link: /verify.php?token=ABC123...
↓
verify.php validates token + expiry
↓
If valid: Mark email as verified (is_verified = 1)
↓
Auto-login user
↓
Redirect to dashboard
```

### Login
```
User enters email + password
↓
login_email.php checks credentials
↓
If is_verified = 0 → Show error: "Email not verified"
↓
If is_verified = 1 → Login successful
↓
Redirect to dashboard
```

---

## 🔑 Database Schema

### Users Table Requirements

```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255),
    is_verified TINYINT(1) DEFAULT 0,
    verification_token VARCHAR(255) NULL,
    token_expiry TIMESTAMP NULL,
    role ENUM('candidate', 'admin') DEFAULT 'candidate',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    -- ... other columns
);

-- Create index for token lookup (improves performance)
CREATE INDEX idx_verification_token ON users(verification_token);
```

---

## 🚀 Quick Start Code Examples

### Register New User
```php
// register_email.php handles this
POST /register_email.php
Parameters: email, password, confirm_password, full_name
Response: Success message + Email sent
```

### Verify Email
```php
// User clicks link in email
GET /verify.php?token=ABC123...
Response: Success → Auto-login + Redirect to dashboard
```

### Login
```php
// login_email.php handles this
POST /login_email.php
Parameters: email, password
Response: 
- If not verified: Error message
- If verified: Login + Redirect
```

---

## 🔒 Security Features

1. **Secure Tokens**: 64-character hex strings (256-bit random)
2. **Token Expiry**: Links expire after 15 minutes (configurable)
3. **Password Hashing**: PASSWORD_BCRYPT (industry standard)
4. **Email Validation**: Valid email format required
5. **HTTPS Ready**: Works with SendGrid's secure SMTP
6. **SQL Injection Prevention**: Prepared statements used
7. **One-Time Use**: Token deleted after verification

---

## 📧 Email Configuration

### SendGrid SMTP Details
- **Host**: smtp.sendgrid.net
- **Port**: 587
- **Security**: STARTTLS
- **Username**: apikey
- **Password**: Your SendGrid API Key

### Alternative Email Services

If you prefer not to use SendGrid, modify `includes/mailer.php`:

#### Gmail SMTP
```php
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'your-email@gmail.com');
define('MAIL_PASSWORD', 'your-app-password');
```

#### Mailgun SMTP
```php
define('MAIL_HOST', 'smtp.mailgun.org');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'postmaster@mg.yourdomain.com');
define('MAIL_PASSWORD', 'your-mailgun-password');
```

#### PHP mail() (Local Server)
```php
// Just disable SMTP in config/phpmailer.php
// System will automatically fallback to mail()
```

---

## 🧪 Testing

### Local Testing (XAMPP)
1. Emails won't actually send on local machine
2. Check logs in `logs/` directory for email content
3. Or use SendGrid sandbox mode

### Testing on InfinityFree
1. Configure SendGrid API key
2. Register new account
3. Check email inbox (may take 1-2 minutes)
4. Click verification link
5. Should auto-login and redirect

### Troubleshooting

**Emails not sending:**
- ✓ Check SendGrid API key in config/phpmailer.php
- ✓ Verify SMTP credentials
- ✓ Check email logs in `logs/` directory
- ✓ Ensure 'from_email' is authorized in SendGrid

**Token validation failing:**
- ✓ Check database has `is_verified`, `verification_token`, `token_expiry` columns
- ✓ Run setup_email_verification.php again
- ✓ Check token expiry time hasn't passed

**Login blocked after verification:**
- ✓ Manually verify via SQL:
```sql
UPDATE users SET is_verified = 1 WHERE email = 'user@example.com';
```

---

## 📊 Customization

### Change Token Expiry Time
Edit `config/phpmailer.php`:
```php
define('TOKEN_EXPIRY_MINUTES', 30); // Change from 15 to 30 minutes
```

### Customize Email Template
Edit `includes/mailer.php` method `getVerificationEmailTemplate()`:
- Change subject line
- Modify email body text
- Update button style
- Add/remove links

### Change Verification URL
Edit `config/phpmailer.php`:
```php
define('VERIFICATION_URL', 'https://yourdomain.com/email-verify.php'); // Custom URL
```

---

## 🚢 Deployment to InfinityFree

### Files to Upload
```
config/
├── phpmailer.php ✅
├── database.php
└── email.php

includes/
├── mailer.php ✅
├── auth.php
└── functions.php

vendor/ (entire folder from composer install) ✅

register_email.php ✅
login_email.php ✅
verify.php ✅
setup_email_verification.php ✅
composer.json ✅
```

### Deployment Steps
1. Run `composer install` locally
2. Upload entire `vendor/` folder to InfinityFree
3. Upload all PHP files from above
4. Update `config/phpmailer.php` with your SendGrid API key
5. Run `setup_email_verification.php` in browser
6. Test registration → email → verification

---

## 📝 API Response Format

### Success Responses
```html
<!-- Registration Success -->
✓ Registration successful! Please check your email for the verification link.

<!-- Verification Success -->
✓ Email verified successfully! You can now login to your account.

<!-- Login Success -->
Session created + Redirect to dashboard
```

### Error Responses
```html
❌ Email already registered. Please use a different email or login.
❌ Your email is not verified yet. Please check your email for the verification link.
❌ Invalid email or password
❌ Verification link has expired. Please register again to get a new link.
❌ Invalid verification token. Please check the link in your email.
```

---

## 💾 Database Queries

### Find Unverified Users
```sql
SELECT * FROM users WHERE is_verified = 0;
```

### Find Users with Expired Tokens
```sql
SELECT * FROM users WHERE token_expiry < NOW() AND is_verified = 0;
```

### Manually Verify User
```sql
UPDATE users SET is_verified = 1 WHERE email = 'user@example.com';
```

### Delete Expired Tokens (Cleanup)
```sql
UPDATE users SET verification_token = NULL, token_expiry = NULL 
WHERE token_expiry < NOW();
```

---

## 📞 Support

**Common Issues:**

| Issue | Solution |
|-------|----------|
| Composer not found | Use pre-built vendor/ folder or run composer install locally |
| SendGrid API key error | Check API key in config/phpmailer.php |
| Emails going to spam | Add domain to SendGrid sender authentication |
| Token expired | Token expiry is 15 minutes - adjust in config if needed |
| Database error | Run setup_email_verification.php |

---

## ✅ Final Checklist

- [ ] PHPMailer installed via composer
- [ ] SendGrid account created + API key obtained
- [ ] config/phpmailer.php updated with API key
- [ ] Database migration run (setup_email_verification.php)
- [ ] All files uploaded to server
- [ ] Test registration flow
- [ ] Test email sending
- [ ] Test email verification link
- [ ] Test login with verified account
- [ ] Test login with unverified account (should fail)

---

**Version:** 1.0  
**Last Updated:** May 2026  
**Status:** Production Ready
