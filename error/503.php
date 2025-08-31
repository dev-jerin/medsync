<?php
// Set the HTTP response code to 503 and tell browsers to retry after 1 hour
http_response_code(503);
header("Retry-After: 3600"); 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Unavailable - MedSync</title>

    <base href="/medsync/">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="apple-touch-icon" sizes="180x180" href="images/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="images/favicon/favicon-16x16.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <link rel="stylesheet" href="main/styles.css">
    <link rel="stylesheet" href="error/styles.css">
</head>
<body>

    <main class="error-page">
        <div class="error-container">
            <div class="error-icon">
                <i class="fas fa-tools"></i>
            </div>
            <h1>503 - Service Temporarily Unavailable</h1>
            <p>The MedSync platform is currently undergoing scheduled maintenance or is temporarily overloaded. We expect to be back online shortly. Thank you for your patience.</p>
        </div>
    </main>

</body>
</html>