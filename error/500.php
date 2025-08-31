<?php
// Set the HTTP response code to 500
http_response_code(500);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Error - MedSync</title>

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

    <header class="header" id="header">
        <nav class="container navbar">
            <a href="index.php" class="logo">
                <img src="images/logo.png" alt="MedSync Logo" class="logo-img">
                <span>MedSync</span>
            </a>
        </nav>
    </header>

    <main class="error-page">
        <div class="error-container">
            <div class="error-icon">
                <i class="fas fa-server"></i>
            </div>
            <h1>500 - Internal Server Error</h1>
            <p>We're sorry, but something went wrong on our end. Our technical team has been automatically notified and is working to resolve the issue.</p>
            <a href="index.php" class="btn btn-primary">Go Back Home</a>
        </div>
    </main>

</body>
</html>