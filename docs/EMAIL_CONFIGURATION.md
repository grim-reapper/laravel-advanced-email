# Email Template Version Configuration

This document describes the email configuration feature that allows each email template version to have its own email settings including sender information, recipients, and reply-to configuration.

## Overview

The email template version configuration system allows you to:

- Configure sender information (from_email, from_name) per template version
- Set default recipients (to_email, cc_email, bcc_email) per template version  
- Configure reply-to settings (reply_to_email, reply_to_name) per template version
- Support both simple email addresses and email addresses with names
- Maintain backward compatibility with existing templates

## Database Schema

The following fields have been added to the `email_template_versions` table:

```sql
-- Sender configuration
from_email VARCHAR(255) NULL
from_name VARCHAR(255) NULL

-- Recipient configuration (JSON arrays)
to_email TEXT NULL
cc_email TEXT NULL  
bcc_email TEXT NULL

-- Reply-to configuration
reply_to_email VARCHAR(255) NULL
reply_to_name VARCHAR(255) NULL
```

## Data Format

### Single Email Fields
Single email fields (`from_email`, `reply_to_email`) are stored as plain strings:
```
"sender@example.com"
```

### Multiple Email Fields
Multiple email fields (`to_email`, `cc_email`, `bcc_email`) are stored as JSON arrays of objects:
```json
[
    {"address": "user1@example.com", "name": null},
    {"address": "user2@example.com", "name": "John Doe"},
    {"address": "user3@example.com", "name": "Jane Smith"}
]
```

## Usage Examples

### Setting Email Configuration

```php
use GrimReapper\AdvancedEmail\Models\EmailTemplateVersion;

$templateVersion = EmailTemplateVersion::find(1);

// Set sender information
$templateVersion->from_email = 'noreply@example.com';
$templateVersion->from_name = 'Example App';

// Set recipients with names
$templateVersion->to_email = [
    ['address' => 'user@example.com', 'name' => 'John Doe']
];

$templateVersion->cc_email = [
    ['address' => 'manager@example.com', 'name' => 'Manager'],
    ['address' => 'admin@example.com', 'name' => null]
];

// Set reply-to
$templateVersion->reply_to_email = 'support@example.com';
$templateVersion->reply_to_name = 'Support Team';

$templateVersion->save();
```

### Using Templates with Email Configuration

```php
use GrimReapper\AdvancedEmail\Facades\Email;

// Send email using template configuration
Email::template('welcome-email')
    ->with(['user_name' => 'John Doe'])
    ->send();

// Override template configuration with method calls
Email::template('welcome-email')
    ->to('override@example.com')  // Overrides template to_email
    ->from('custom@example.com')  // Overrides template from_email
    ->send();
```

## Configuration Priority

The system uses a priority hierarchy for email configuration:

1. **Highest Priority**: Method-level configuration
   ```php
   Email::template('example')->to('user@example.com')->send();
   ```

2. **Medium Priority**: Template version configuration (database)
   ```php
   // Configuration stored in email_template_versions table
   ```

3. **Lowest Priority**: Default configuration (config files)
   ```php
   // config/advanced_email.php or config/mail.php
   ```

## Validation

All email addresses are validated using PHP's `filter_var()` function with `FILTER_VALIDATE_EMAIL`. Invalid email addresses are:

- Logged with detailed context
- Excluded from the final configuration
- Replaced with fallback configuration when possible

## Backward Compatibility

The system maintains full backward compatibility:

- All new fields are nullable
- Existing templates without email configuration continue to work
- Legacy email formats are automatically converted to the new format
- Method-level configuration always takes precedence

## API Reference

### EmailTemplateVersion Methods

#### `getEmailConfiguration(): array`
Returns the complete email configuration for the template version.

```php
$config = $templateVersion->getEmailConfiguration();
// Returns:
// [
//     'from_email' => 'sender@example.com',
//     'from_name' => 'Sender Name',
//     'to_email' => [['address' => 'user@example.com', 'name' => 'User']],
//     'cc_email' => [],
//     'bcc_email' => [],
//     'reply_to_email' => null,
//     'reply_to_name' => null
// ]
```

#### `hasEmailConfiguration(): bool`
Checks if the template version has any email configuration.

```php
if ($templateVersion->hasEmailConfiguration()) {
    // Template has email configuration
}
```

#### `getSenderInfo(): ?array`
Returns formatted sender information.

```php
$sender = $templateVersion->getSenderInfo();
// Returns: ['address' => 'sender@example.com', 'name' => 'Sender Name']
```

#### `getReplyToInfo(): ?array`
Returns formatted reply-to information.

```php
$replyTo = $templateVersion->getReplyToInfo();
// Returns: ['address' => 'reply@example.com', 'name' => 'Reply Name']
```

### Validation Methods

#### `EmailTemplateVersion::getEmailConfigValidationRules(): array`
Returns Laravel validation rules for email configuration fields.

#### `EmailTemplateVersion::validateEmailConfig(array $data): Validator`
Validates email configuration data against the defined rules.

## Error Handling

The system includes comprehensive error handling:

- **Invalid email formats**: Logged and excluded from configuration
- **Template loading failures**: Graceful fallback to default configuration
- **JSON parsing errors**: Automatic fallback to comma-separated parsing
- **Missing configuration**: Uses default values from config files

## Performance Considerations

- Email configuration is loaded only when needed
- Database indexes are added for frequently queried fields
- JSON parsing is optimized with fallback mechanisms
- Validation is performed at multiple levels to prevent runtime errors

## Migration Guide

To migrate existing email templates to use the new configuration:

1. **Run the migration**:
   ```bash
   php artisan migrate
   ```

2. **Update existing templates** (optional):
   ```php
   $templateVersion = EmailTemplateVersion::find(1);
   $templateVersion->from_email = 'your-sender@example.com';
   $templateVersion->to_email = [
       ['address' => 'recipient@example.com', 'name' => 'Recipient Name']
   ];
   $templateVersion->save();
   ```

3. **Test email sending**:
   ```php
   Email::template('your-template')->send();
   ```

The system will automatically handle the new configuration while maintaining compatibility with existing templates.