<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inventory Alert</title>
</head>
<body>


<h2>Inventory Alert</h2>

<p><strong>Type:</strong> {{ $alert->type }}</p>
<p><strong>Severity:</strong> {{ $alert->severity }}</p>
<p><strong>SKU:</strong> {{ optional($alert->variant)->sku }}</p>
<p><strong>Message:</strong> {{ $alert->message }}</p>
<p><strong>Detected:</strong> {{ $alert->first_detected_at }}</p>


</body>
</html>
