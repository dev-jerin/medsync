<?php
// Include the configuration file to initialize session and CSRF token
require_once './config.php';

// If a user is already logged in, redirect them to their dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: " . $_SESSION['role'] . "_dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MedSync</title>
    
    <!-- Google Fonts: Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Favicon Links -->
    <link rel="apple-touch-icon" sizes="180x180" href="images/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="images/favicon/favicon-16x16.png">
    <link rel="manifest" href="images/favicon/site.webmanifest">

    <style>
        /* --- Base Styles & Variables (Consistent with main site) --- */
        :root {
            --primary-color: #007BFF;
            --secondary-color: #17a2b8;
            --text-dark: #343a40;
            --background-grey: #f1f5f9;
            --background-light: #ffffff;
            --shadow-md: 0 10px 15px rgba(0, 0, 0, 0.1);
            --border-radius: 12px;
            --error-color: #dc3545;
            --success-color: #28a745;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background-grey);
            color: var(--text-dark);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 2rem;
        }

        .container {
            width: 100%;
            max-width: 450px;
        }
        
        /* --- Login Card --- */
        .login-card {
            background: var(--background-light);
            padding: 2.5rem 3rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            width: 100%;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-header .logo {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            text-decoration: none;
            margin-bottom: 0.5rem;
            display: inline-flex;
            align-items: center;
        }
        
        .login-header .logo-img {
            height: 40px;
            margin-right: 8px;
        }

        .login-header h1 {
            font-size: 1.8rem;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .form-group input {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 1px solid #ced4da;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.2);
        }
        
        .btn-primary {
            padding: 0.8rem 1.5rem;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            width: 100%;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            color: #fff;
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.2);
        }

        .extra-links {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.9rem;
        }

        .extra-links a {
            color: var(--primary-color);
            font-weight: 500;
            text-decoration: none;
        }
        
        /* --- Message Boxes --- */
        .message-box {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            text-align: center;
            border: 1px solid transparent;
            font-size: 0.95rem;
        }
        .error-message {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--error-color);
            border-color: rgba(220, 53, 69, 0.2);
        }
        .success-message {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
            border-color: rgba(40, 167, 69, 0.2);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-card">
            <div class="login-header">
                <a href="index.php" class="logo">
                    <img src="images/logo.png" alt="MedSync Logo" class="logo-img">
                    MedSync
                </a>
                <h1>Member Login</h1>
            </div>

            <?php
            // Display registration success message if it exists
            if (isset($_SESSION['register_success'])) {
                echo '<div class="message-box success-message">' . htmlspecialchars($_SESSION['register_success']) . '</div>';
                unset($_SESSION['register_success']);
            }
            // Display login error message if it exists
            if (isset($_SESSION['login_error'])) {
                echo '<div class="message-box error-message">' . htmlspecialchars($_SESSION['login_error']) . '</div>';
                unset($_SESSION['login_error']);
            }
            ?>

            <form action="login_process.php" method="POST">
                <!-- CSRF Token for security -->
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <div class="form-group">
                    <label for="username">Username, Email, or User ID</label>
                    <input type="text" id="username" name="username" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <button type="submit" class="btn-primary">Login</button>
            </form>

            <div class="extra-links">
                <a href="forgot_password.php">Forgot Password?</a>
                <p style="margin-top: 0.5rem;">Don't have an account? <a href="register.php">Register Now</a></p>
            </div>
        </div>
    </div>
</body>
</html>
