# Laravel Advanced Email - API Reference

This document provides a comprehensive reference of all classes, methods, and properties available in the Laravel Advanced Email package.

## Table of Contents

- [Email Facade](#email-facade)
- [Models](#models)
  - [EmailTemplate](#emailtemplate)
  - [EmailTemplateVersion](#emailtemplateversion)
  - [EmailLog](#emaillog)
  - [EmailLink](#emaillink)
  - [ScheduledEmail](#scheduledemail)
- [Services](#services)
  - [EmailService](#emailservice)
- [Events](#events)
- [Jobs](#jobs)

## Email Facade

The `Email` facade is the primary interface for sending emails with the package.

### Basic Methods

| Method | Description | Parameters | Return |
|--------|-------------|------------|--------|
| `to($address, $name = null)` | Add a recipient | `$address` (string\|array), `$name` (string\|null) | `$this` |
| `cc($address, $name = null)` | Add a CC recipient | `$address` (string\|array), `$name` (string\|null) | `$this` |
| `bcc($address, $name = null)` | Add a BCC recipient | `$address` (string\|array), `$name` (string\|null) | `$this` |
| `from($address, $name = null)` | Set the sender | `$address` (string), `$name` (string\|null) | `$this` |
| `replyTo($address, $name = null)` | Set reply-to address | `$address` (string), `$name` (string\|null) | `$this` |
| `subject($subject)` | Set the email subject | `$subject` (string) | `$this` |
| `view($view, $data = [])` | Use a Blade view for content | `$view` (string), `$data` (array) | `$this` |
| `html($content, $placeholders = [])` | Use raw HTML for content | `$content` (string), `$placeholders` (array) | `$this` |
| `text($content, $placeholders = [])` | Use plain text for content | `$content` (string), `$placeholders` (array) | `$this` |
| `attach($file, $options = [])` | Attach a file | `$file` (string), `$options` (array) | `$this` |
| `attachData($data, $name, $options = [])` | Attach raw data | `$data` (string), `$name` (string), `$options` (array) | `$this` |
| `attachFromStorage($disk, $path, $name = null, $options = [])` | Attach from Storage | `$disk` (string), `$path` (string), `$name` (string\|null), `$options` (array) | `$this` |
| `mailer($mailer)` | Use a specific mailer | `$mailer` (string) | `$this` |
| `send()` | Send immediately | - | `bool` |
| `queue($connection = null, $queue = null)` | Queue for sending | `$connection` (string\|null), `$queue` (string\|null) | `bool` |

### Template Methods

| Method | Description | Parameters | Return |
|--------|-------------|------------|--------|
| `template($templateName)` | Use a database template | `$templateName` (string) | `$this` |
| `with($data)` | Set placeholder values | `$data` (array) | `$this` |

### Scheduling Methods

| Method | Description | Parameters | Return |
|--------|-------------|------------|--------|
| `schedule($datetime)` | Schedule for future sending | `$datetime` (Carbon\|string\|DateTime) | `$this` |
| `expires($datetime)` | Set expiration time | `$datetime` (Carbon\|string\|DateTime) | `$this` |
| `recurring($frequency, $options = [])` | Set up recurring emails | `$frequency` (string), `$options` (array) | `$this` |
| `when($callback)` | Add a condition for sending | `$callback` (Closure) | `$this` |
| `retryAttempts($attempts)` | Set retry attempts | `$attempts` (int) | `$this` |
| `saveScheduled()` | Save as a scheduled email | - | `ScheduledEmail` |

## Models

### EmailTemplate

Represents an email template in the database.

#### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | int | Primary key |
| `name` | string | Unique template identifier |
| `description` | string | Template description |
| `created_at` | Carbon | Creation timestamp |
| `updated_at` | Carbon | Update timestamp |

#### Relationships

| Method | Return Type | Description |
|--------|------------|-------------|
| `versions()` | HasMany | Get all versions of this template |

#### Methods

| Method | Description | Parameters | Return |
|--------|-------------|------------|--------|
| `activeVersion()` | Get active version | - | EmailTemplateVersion |

### EmailTemplateVersion

Represents a specific version of an email template.

#### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | int | Primary key |
| `email_template_id` | int | Foreign key to EmailTemplate |
| `version` | int | Version number |
| `subject` | string | Email subject |
| `html_content` | string | HTML content |
| `text_content` | string | Plain text content |
| `placeholders` | array | Available placeholders |
| `is_active` | bool | Whether version is active |
| `created_at` | Carbon | Creation timestamp |
| `updated_at` | Carbon | Update timestamp |

#### Relationships

| Method | Return Type | Description |
|--------|------------|-------------|
| `template()` | BelongsTo | Get the parent template |

### EmailLog

Represents a log entry for a sent email.

#### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | int | Primary key |
| `uuid` | string | Unique identifier |
| `to` | array | Recipients |
| `cc` | array | CC recipients |
| `bcc` | array | BCC recipients |
| `from` | array | Sender information |
| `reply_to` | array | Reply-to information |
| `subject` | string | Email subject |
| `html_body` | string | HTML content |
| `text_body` | string | Plain text content |
| `template_name` | string | Template name (if used) |
| `template_version` | int | Template version (if used) |
| `mailer` | string | Mailer used |
| `status` | string | Status (sending, sent, failed) |
| `error` | string | Error message (if failed) |
| `sent_at` | Carbon | When email was sent |
| `opened_at` | Carbon | When email was opened |
| `created_at` | Carbon | Creation timestamp |
| `updated_at` | Carbon | Update timestamp |

#### Relationships

| Method | Return Type | Description |
|--------|------------|-------------|
| `links()` | HasMany | Get tracked links |

#### Scopes

| Method | Description | Parameters |
|--------|-------------|------------|
| `scopeOpened($query)` | Query opened emails | `$query` (Builder) |
| `scopeToAddress($query, $email)` | Query by recipient | `$query` (Builder), `$email` (string) |
| `scopeWithSubject($query, $subject)` | Query by subject | `$query` (Builder), `$subject` (string) |

### EmailLink

Represents a tracked link in an email.

#### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | int | Primary key |
| `email_log_id` | int | Foreign key to EmailLog |
| `uuid` | string | Unique identifier |
| `original_url` | string | Original URL |
| `click_count` | int | Number of clicks |
| `last_clicked_at` | Carbon | When link was last clicked |
| `created_at` | Carbon | Creation timestamp |
| `updated_at` | Carbon | Update timestamp |

#### Relationships

| Method | Return Type | Description |
|--------|------------|-------------|
| `emailLog()` | BelongsTo | Get the parent email log |

### ScheduledEmail

Represents an email scheduled for future delivery.

#### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | int | Primary key |
| `uuid` | string | Unique identifier |
| `status` | string | Status (pending, processing, sent, failed, expired) |
| `mailable_class` | string | Serialized mailable class |
| `mailable_data` | array | Serialized mailable data |
| `scheduled_at` | Carbon | When to send |
| `expires_at` | Carbon | When to expire |
| `frequency` | string | Recurrence frequency |
| `frequency_options` | array | Recurrence options |
| `conditions` | array | Sending conditions |
| `retry_attempts` | int | Number of retry attempts |
| `parent_id` | int | Parent email for recurring |
| `occurrence_number` | int | Occurrence number |
| `created_at` | Carbon | Creation timestamp |
| `updated_at` | Carbon | Update timestamp |

#### Relationships

| Method | Return Type | Description |
|--------|------------|-------------|
| `parent()` | BelongsTo | Get the parent scheduled email |
| `children()` | HasMany | Get child scheduled emails |

#### Methods

| Method | Description | Parameters | Return |
|--------|-------------|------------|--------|
| `shouldSend()` | Check if email should be sent | - | bool |
| `markAsSent()` | Mark as sent | - | bool |
| `markAsFailed($error = null)` | Mark as failed | `$error` (string\|null) | bool |
| `markAsExpired()` | Mark as expired | - | bool |
| `createNextOccurrence()` | Create next occurrence | - | ScheduledEmail\|null |

## Services

### EmailService

The core service that handles email sending, scheduling, and tracking.

#### Methods

| Method | Description | Parameters | Return |
|--------|-------------|------------|--------|
| `to($address, $name = null)` | Add a recipient | `$address` (string\|array), `$name` (string\|null) | EmailService |
| `cc($address, $name = null)` | Add a CC recipient | `$address` (string\|array), `$name` (string\|null) | EmailService |
| `bcc($address, $name = null)` | Add a BCC recipient | `$address` (string\|array), `$name` (string\|null) | EmailService |
| `from($address, $name = null)` | Set the sender | `$address` (string), `$name` (string\|null) | EmailService |
| `replyTo($address, $name = null)` | Set reply-to address | `$address` (string), `$name` (string\|null) | EmailService |
| `subject($subject)` | Set the email subject | `$subject` (string) | EmailService |
| `view($view, $data = [])` | Use a Blade view for content | `$view` (string), `$data` (array) | EmailService |
| `html($content, $placeholders = [])` | Use raw HTML for content | `$content` (string), `$placeholders` (array) | EmailService |
| `text($content, $placeholders = [])` | Use plain text for content | `$content` (string), `$placeholders` (array) | EmailService |
| `template($templateName)` | Use a database template | `$templateName` (string) | EmailService |
| `with($data)` | Set placeholder values | `$data` (array) | EmailService |
| `attach($file, $options = [])` | Attach a file | `$file` (string), `$options` (array) | EmailService |
| `attachData($data, $name, $options = [])` | Attach raw data | `$data` (string), `$name` (string), `$options` (array) | EmailService |
| `attachFromStorage($disk, $path, $name = null, $options = [])` | Attach from Storage | `$disk` (string), `$path` (string), `$name` (string\|null), `$options` (array) | EmailService |
| `mailer($mailer)` | Use a specific mailer | `$mailer` (string) | EmailService |
| `send()` | Send immediately | - | bool |
| `queue($connection = null, $queue = null)` | Queue for sending | `$connection` (string\|null), `$queue` (string\|null) | bool |
| `schedule($datetime)` | Schedule for future sending | `$datetime` (Carbon\|string\|DateTime) | EmailService |
| `expires($datetime)` | Set expiration time | `$datetime` (Carbon\|string\|DateTime) | EmailService |
| `recurring($frequency, $options = [])` | Set up recurring emails | `$frequency` (string), `$options` (array) | EmailService |
| `when($callback)` | Add a condition for sending | `$callback` (Closure) | EmailService |
| `retryAttempts($attempts)` | Set retry attempts | `$attempts` (int) | EmailService |
| `saveScheduled()` | Save as a scheduled email | - | ScheduledEmail |

## Events

### EmailSending

Dispatched before an email is sent.

#### Properties

| Property | Type | Description |
|----------|------|-------------|
| `mailable` | Mailable | The mailable being sent |
| `mailer` | string | The mailer being used |

### EmailSent

Dispatched after an email is successfully sent.

#### Properties

| Property | Type | Description |
|----------|------|-------------|
| `mailable` | Mailable | The mailable that was sent |
| `mailer` | string | The mailer that was used |
| `logData` | array | Log data for the sent email |

### EmailFailed

Dispatched when an email fails to send.

#### Properties

| Property | Type | Description |
|----------|------|-------------|
| `mailable` | Mailable | The mailable that failed |
| `mailer` | string | The mailer that was used |
| `exception` | Exception | The exception that caused the failure |

## Jobs

### SendEmailJob

Handles sending an email through the queue.

#### Properties

| Property | Type | Description |
|----------|------|-------------|
| `mailable` | Mailable | The mailable to send |
| `mailerName` | string\|null | The mailer to use |
| `logUuid` | string\|null | The UUID for the email log entry |

### ProcessScheduledEmailsJob

Processes scheduled emails that are due to be sent.

#### Methods

| Method | Description | Parameters | Return |
|--------|-------------|------------|--------|
| `handle()` | Process scheduled emails | - | void |