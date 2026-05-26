# Email Link Authentication System - Implementation Guide

## Overview
The exam system has been upgraded from OTP-based email verification to a more secure and user-friendly **email link verification** system.

## What Changed?

### Old System (OTP)
- Users received a 6-digit code via email
- They had to manually enter this code on a verification page
- Code expired after 15 minutes
- Users had to remember/copy the code

### New System (Email Links)
- Users receive a clickable verification link via email
- They click the link to instantly verify and log in
- Link expires after 24 hours for better security
- Seamless one-click experience

---

## Implementation Steps

### 1. Database Migration
Before using the new system, run the database migration script:

```bash
# Open browser and navigate to:
http://your-domain.com/migrate_to_token_auth.php
```

This will add the required columns to your database:
- `verification_token` - Stores the unique verification token
- `verification_token_expires_at` - Token expiration timestamp

### 2. Verify Email Configuration
Ensure your email configuration in `config/email.php` is correct:

```php
// config/email.php
return [
    'from_email' => 'your-email@gmail.com',
    'from_name' => 'ExamSystem Pro',
    'smtp_enabled' => true,
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 465,
    'smtp_secure' => 'ssl',
    'smtp_username' => 'your-email@gmail.com',
    'smtp_password' => 'your-app-password', // Use Gmail App Password
];
```

---

## User Flow

### Registration
1. User fills out registration form
2. Clicks "Register"
3. System generates a secure verification token
4. Email sent with verification link
5. User sees: "✓ Registration successful! Please check your email for the verification link."

### Email Verification
1. User receives email with subject: "Email Verification - ExamSystem Pro"
2. Email contains a clickable button "Verify Email Address"
3. User clicks the link (or copies and pastes it)
4. Link redirects to `verify_email_link.php?token=...&email=...`
5. System validates token and email
6. If valid:
   - Email is marked as verified
   - User is automatically logged in
   - Redirected to dashboard
7. If invalid/expired:
   - User sees error message
   - Prompted to register again

### Login
1. Unverified users cannot log in
2. System shows: "Your email is not verified yet. Please check your email for the verification link..."
3. After verification, users can log in normally

---

## Key Files Modified

### 1. `includes/email.php`
- Added: `sendVerificationLinkEmail()` - Sends email with verification link
- Added: `generateVerificationToken()` - Generates secure 64-character token
- Added: `getVerificationLinkEmailTemplate()` - HTML email template with link
- Kept: `sendOTPEmail()` for backwards compatibility (deprecated)

### 2. `register.php`
- Changed: Uses `generateVerificationToken()` instead of `generateOTP()`
- Changed: Stores token in `verification_token` column
- Changed: Sends verification link email instead of OTP
- Changed: Redirects to login page with message

### 3. `login.php`
- Removed: OTP resend functionality
- Simplified: Error message for unverified users
- Removed: Email verification notice section

### 4. `includes/auth.php`
- Kept: Email verification check in login
- Updated: Comments to reflect new system

### 5. `verify_email.php` (deprecated)
- Updated: Shows information about new email link system
- Redirects users to login page

### 6. `verify_email_link.php` (NEW)
- Handles email link clicks
- Validates token and email
- Marks email as verified
- Auto-logs in user
- Redirects to dashboard

### 7. `migrate_to_token_auth.php` (NEW)
- Database migration script
- Adds verification token columns
- Creates indexes for performance

---

## Security Features

1. **Token Security**
   - 64-character hex string (256-bit random)
   - Much harder to brute force than 6-digit OTP
   - Unique per user

2. **Time-Based Expiration**
   - Links expire after 24 hours (configurable)
   - Prevents long-term token reuse

3. **Email-Bound Verification**
   - Token matched with specific email
   - Can't use token with different email

4. **One-Click Login**
   - Auto-logs in user after verification
   - No separate login step required

---

## Configuration

### Change Token Expiration Time
Edit `register.php` and `verify_email_link.php`:

```php
// Current: 24 hours
$token_expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));

// Change to 48 hours:
$token_expires_at = date('Y-m-d H:i:s', strtotime('+48 hours'));
```

### Change Email Template
Edit `includes/email.php` method `getVerificationLinkEmailTemplate()` to customize:
- Subject line
- Email body text
- Button text
- Link expiration message

---

## Troubleshooting

### Users Not Receiving Emails
1. Check spam/junk folder
2. Verify SMTP credentials in `config/email.php`
3. Check server logs: `logs/verification_link_emails.log`
4. Ensure Gmail App Password is used (not regular password)
5. Enable 2FA on Gmail account

### Link Not Working
1. Check if link is expired (> 24 hours)
2. Verify email address in URL matches database
3. Check if email already verified
4. Try registering again

### Login Still Blocked
1. Verify email verification completed successfully
2. Check database: `users.email_verified` should be 1 (TRUE)
3. Manually update if needed:
   ```sql
   UPDATE users SET email_verified = TRUE WHERE email = 'user@example.com';
   ```

### Database Error During Migration
1. Log in to your database manager
2. Manually run this SQL:
   ```sql
   ALTER TABLE users ADD COLUMN IF NOT EXISTS verification_token VARCHAR(255) NULL;
   ALTER TABLE users ADD COLUMN IF NOT EXISTS verification_token_expires_at TIMESTAMP NULL;
   ALTER TABLE users ADD INDEX IF NOT EXISTS idx_verification_token (verification_token);
   ```

---

## Backwards Compatibility

### Old OTP-Based Users
If you have existing users with OTP columns:
1. Their `otp` and `otp_expires_at` columns remain unchanged
2. Migration doesn't remove old columns
3. Optional: Manually drop old columns after migration
   ```sql
   ALTER TABLE users DROP COLUMN otp, DROP COLUMN otp_expires_at;
   ```

### Existing Unverified Users
- Will need to register again to get new verification link
- Or admin can manually verify them via database:
  ```sql
  UPDATE users SET email_verified = TRUE WHERE role = 'candidate';
  ```

---

## Testing Checklist

- [ ] Run migration script: `migrate_to_token_auth.php`
- [ ] Check database columns added successfully
- [ ] Create new test account
- [ ] Verify registration email received
- [ ] Click email link
- [ ] Confirm auto-login works
- [ ] Verify redirected to candidate dashboard
- [ ] Try logging in with unverified account (should fail)
- [ ] Try expired link (wait 24+ hours or manually update DB)
- [ ] Check admin can still log in (no email verification required)
- [ ] Review email logs: `logs/verification_link_emails.log`

---

## Email Template Customization

The email template is in `includes/email.php` method `getVerificationLinkEmailTemplate()`:

```php
private function getVerificationLinkEmailTemplate($verification_token, $user_name, $user_email) {
    // Customize the HTML email here
    // Change colors, text, button style, etc.
    // URL is auto-generated with token
}
```

---

## Support

For issues or questions:
1. Check logs in `logs/` directory
2. Verify `config/email.php` settings
3. Check database columns with:
   ```sql
   DESCRIBE users;
   ```
4. Enable debugging in `config/email.php`:
   ```php
   'smtp_debug' => true,
   ```

---

## Summary

| Aspect | Old System | New System |
|--------|-----------|-----------|
| **Verification Method** | 6-digit OTP code | Email link |
| **Delivery** | Email + manual entry | Email with clickable link |
| **Expiration** | 15 minutes | 24 hours |
| **Security** | Moderate | High |
| **User Experience** | Manual entry required | One-click verification |
| **Auto-login** | No, requires login after | Yes, after verification |
| **Resend Option** | Yes, via login page | Re-register for new link |

---

**Last Updated:** May 2026  
**Version:** 2.0 (Email Link Authentication)
