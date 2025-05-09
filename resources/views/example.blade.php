<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Example Email</title>
</head>
<body>
    <h1>Hello {{ $name ?? 'User' }}!</h1>

    <p>This is an example email template provided by the Advanced Email package.</p>

    @isset($customMessage)
        <p><strong>Your custom message:</strong> {{ $customMessage }}</p>
    @endisset

    <p>Thank you for using our package.</p>
</body>
</html>