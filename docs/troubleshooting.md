# Laravel Advanced Email - Troubleshooting Guide

This guide provides solutions to common issues you might encounter when using the Laravel Advanced Email package.

## Table of Contents

- [Email Sending Issues](#email-sending-issues)
- [Template Issues](#template-issues)
- [Scheduling Issues](#scheduling-issues)
- [Tracking Issues](#tracking-issues)
- [Database Issues](#database-issues)
- [Queue Issues](#queue-issues)

## Email Sending Issues

### Emails Not Being Sent

**Symptoms:**
- No emails are being delivered
- No errors are shown in the application

**Possible Causes and Solutions:**

1. **Mail Configuration Issues**
   - Check your `.env` file for correct mail settings
   - Verify SMTP credentials in `config/mail.php`
   - Try using the `mail` driver temporarily to rule out SMTP issues

2. **Provider Restrictions**
   - Some email providers block automated emails
   - Check your email provider's logs or spam policies
   - Consider using a dedicated email service like Mailgun, Postmark, or Amazon SES

3. **Firewall or Network Issues**
   - Ensure your server can connect to the mail server
   - Check if outgoing port 25, 465, or 587 is blocked

### Emails Going to Spam

**Symptoms:**
- Emails are sent but end up in recipients' spam folders

**Possible Causes and Solutions:**

1. **Missing or Invalid SPF/DKIM Records**
   - Set up proper SPF and DKIM records for your domain
   - Use a service like [Mail Tester](https://www.mail-tester.com/) to check your email's spam score

2. **Low Sender Reputation**
   - Gradually increase your sending volume
   - Ensure your content doesn't contain spam trigger words
   - Use a dedicated IP address for sending emails

### Multi-Provider Failover Not Working

**Symptoms:**
- Emails fail to send even with multiple providers configured

**Possible Causes and Solutions:**

1. **Configuration Issues**
   - Check that all providers are correctly configured in `config/mail.php`
   - Verify the provider order in `config/advanced_email.php`
   - Ensure each provider has valid credentials

2. **Debug Mode**
   - Enable more verbose logging to see which providers are being attempted
   ```php
   // In config/advanced_email.php
   'debug' => true,
   ```

## Template Issues

### Template Placeholders Not Working

**Symptoms:**
- Placeholders like `{{name}}` appear as-is in the sent email

**Possible Causes and Solutions:**

1. **Placeholder Format Mismatch**
   - Ensure placeholders in the template match the format expected
   - Check that you're using the correct placeholder syntax (e.g., `{{placeholder}}`)

2. **Missing Data**
   - Verify that all required placeholder values are provided in the `with()` method
   ```php
   Email::template('welcome_email')
       ->with([
           'name' => 'John Doe', // Make sure all required placeholders are here
       ])
       ->send();
   ```

3. **Template Version Issues**
   - Confirm that the active template version contains the expected placeholders
   - Check the `placeholders` array in the `email_template_versions` table

### Template Not Found

**Symptoms:**
- Error: "Template [name] not found"

**Possible Causes and Solutions:**

1. **Database Issues**
   - Verify the template exists in the `email_templates` table
   - Check that the template name is spelled correctly

2. **No Active Version**
   - Ensure at least one version of the template has `is_active` set to `true`
   - If no active version exists, create one or activate an existing version

## Scheduling Issues

### Scheduled Emails Not Sending

**Symptoms:**
- Emails are scheduled but never sent
- Status remains as "pending" in the database

**Possible Causes and Solutions:**

1. **Laravel Scheduler Not Running**
   - Ensure the Laravel scheduler is running via cron job
   - For Linux/Unix systems, check that this entry exists in your crontab:
     ```
     * * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
     ```
   - For Windows, set up a scheduled task to run `php artisan schedule:run` every minute

2. **Incorrect Scheduling Configuration**
   - Check the `scheduling.frequency` setting in `config/advanced_email.php`
   - Verify that the frequency is a valid Laravel scheduler method

3. **Queue Worker Not Running**
   - If you're using queues, ensure a queue worker is running
   - Run `php artisan queue:work` or set up a process monitor like Supervisor

### Recurring Emails Issues

**Symptoms:**
- Recurring emails don't create new occurrences

**Possible Causes and Solutions:**

1. **Incorrect Frequency Options**
   - Check that the frequency options match the expected format
   - For weekly emails, ensure the `days` array contains valid day constants
   - For monthly emails, verify the `day` value is between 1 and 31

2. **Parent Email Status**
   - Verify that the parent email has not been marked as failed or expired
   - Check the `status` column in the `scheduled_emails` table

## Tracking Issues

### Email Opens Not Tracking

**Symptoms:**
- The `opened_at` field remains null even when emails are opened

**Possible Causes and Solutions:**

1. **Email Client Blocking Tracking Pixel**
   - Many email clients block tracking pixels by default
   - This is a limitation of email tracking in general
   - Consider using link tracking as a more reliable alternative

2. **HTML Email Required**
   - Open tracking only works with HTML emails, not plain text
   - Ensure you're using `html()` or `view()` methods, not just `text()`

3. **Tracking Routes Not Accessible**
   - Verify that the tracking routes are publicly accessible
   - Check your application's route middleware

### Link Clicks Not Tracking

**Symptoms:**
- Clicked links don't register in the `email_links` table

**Possible Causes and Solutions:**

1. **Link Rewriting Issues**
   - Ensure links are properly formatted in your HTML
   - Links should be standard `<a href="...">` tags

2. **Route Configuration**
   - Check that the tracking routes are registered and accessible
   - Verify the web middleware group includes the necessary routes

## Database Issues

### Migration Errors

**Symptoms:**
- Errors when running migrations

**Possible Causes and Solutions:**

1. **Table Already Exists**
   - If you're getting "table already exists" errors, you may have run the migrations before
   - Run `php artisan migrate:status` to check the status of migrations
   - If needed, roll back with `php artisan migrate:rollback`

2. **Custom Table Names**
   - If you've customized table names in the configuration, ensure they're consistent
   - Check the `database.tables` section in `config/advanced_email.php`

### Database Connection Issues

**Symptoms:**
- Database-related errors when sending or scheduling emails

**Possible Causes and Solutions:**

1. **Custom Connection Configuration**
   - If you're using a custom database connection, verify it's properly configured
   - Check the `database.connection` setting in `config/advanced_email.php`

2. **Permission Issues**
   - Ensure the database user has sufficient permissions for all operations
   - The user needs SELECT, INSERT, UPDATE, and DELETE permissions

## Queue Issues

### Queued Emails Not Processing

**Symptoms:**
- Emails are queued but never sent

**Possible Causes and Solutions:**

1. **Queue Worker Not Running**
   - Start a queue worker with `php artisan queue:work`
   - For production, use a process monitor like Supervisor

2. **Failed Jobs**
   - Check the `failed_jobs` table for errors
   - Run `php artisan queue:failed` to see failed jobs
   - Retry failed jobs with `php artisan queue:retry all`

3. **Queue Configuration**
   - Verify your queue connection in `.env` (QUEUE_CONNECTION)
   - If using Redis or other drivers, ensure they're properly configured

### Memory Issues with Large Batches

**Symptoms:**
- Queue worker crashes when processing many emails

**Possible Causes and Solutions:**

1. **Insufficient Memory**
   - Increase PHP memory limit in php.ini
   - Reduce batch size in `config/advanced_email.php`

2. **Queue Worker Configuration**
   - Use the `--memory` flag to allocate more memory to the queue worker
   - Example: `php artisan queue:work --memory=1024`

## Getting Additional Help

If you're still experiencing issues after trying the solutions above:

1. **Check the Laravel Logs**
   - Look in `storage/logs/laravel.log` for detailed error messages

2. **Enable Debug Mode**
   - Set `APP_DEBUG=true` in your `.env` file temporarily
   - This will provide more detailed error messages

3. **Community Resources**
   - Search for similar issues on [GitHub Issues](https://github.com/grim-reapper/laravel-advanced-email/issues)
   - Ask for help on [Laravel Forums](https://laracasts.com/discuss) or [Stack Overflow](https://stackoverflow.com/questions/tagged/laravel)

4. **Submit an Issue**
   - If you believe you've found a bug, submit an issue on GitHub with:
     - Detailed description of the problem
     - Steps to reproduce
     - Laravel and package versions
     - Relevant code snippets