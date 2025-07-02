<?php
// Include the configuration file to initialize session and CSRF token
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - MedSync</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        /* --- Base Styles & Variables (Consistent with index.php) --- */
        :root {
            --primary-color: #007BFF;
            --primary-dark: #0056b3;
            --secondary-color: #17a2b8;
            --text-dark: #343a40;
            --text-light: #f8f9fa;
            --background-light: #ffffff;
            --background-grey: #f1f5f9;
            --success-color: #28a745;
            --error-color: #dc3545;
            --shadow-sm: 0 4px 6px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 10px 15px rgba(0, 0, 0, 0.1);
            --border-radius: 12px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background-grey);
            color: var(--text-dark);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 2rem 0;
        }

        .container {
            width: 90%;
            max-width: 500px;
            margin: auto;
        }
        
        /* --- Registration Card --- */
        .register-card {
            background: var(--background-light);
            padding: 2.5rem 3rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            width: 100%;
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .register-header .logo {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            text-decoration: none;
            margin-bottom: 0.5rem;
            display: inline-block;
        }
        
        .register-header .logo i {
            margin-right: 8px;
        }

        .register-header h1 {
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

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 1px solid #ced4da;
            border-radius: 8px;
            font-size: 1rem;
            font-family: 'Poppins', sans-serif;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.2);
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            width: 100%;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            color: var(--text-light);
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 123, 255, 0.3);
        }

        .login-link {
            text-align: center;
            margin-top: 1.5rem;
        }

        .login-link a {
            color: var(--primary-color);
            font-weight: 500;
            text-decoration: none;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        /* --- Password Strength Meter --- */
        #password-strength-meter {
            height: 5px;
            width: 100%;
            background: #e0e0e0;
            border-radius: 5px;
            margin-top: 5px;
        }
        #password-strength-bar {
            height: 100%;
            width: 0;
            background: var(--error-color);
            border-radius: 5px;
            transition: width 0.3s, background-color 0.3s;
        }
        #password-strength-text {
            font-size: 0.85rem;
            margin-top: 5px;
            text-align: right;
        }

        /* --- Message Box --- */
        .message-box {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
            text-align: center;
            border: 1px solid transparent;
        }
        .error-message {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--error-color);
            border-color: rgba(220, 53, 69, 0.2);
        }

        /*logo */
        .logo {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-color);
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .logo-img {
            height: 33px; /* Adjust as needed */
            width: auto;
            margin-right: 8px; /* Match original spacing */
        }
    </style>
        <link rel="apple-touch-icon" sizes="180x180" href="images/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="images/favicon/favicon-16x16.png">
    <link rel="manifest" href="images/favicon/site.webmanifest">
    
</head>
<body>
    <div class="container">
        <div class="register-card">
            <div class="register-header">
            <a href="index.php" class="logo">
                <img src="images/logo.png" alt="MedSync Logo" class="logo-img">MedSync
            </a>
                <h1>Create Your Patient Account</h1>
            </div>

            <?php
            // Display any registration error messages stored in the session
            if (isset($_SESSION['register_error'])) {
                echo '<div class="message-box error-message">' . htmlspecialchars($_SESSION['register_error']) . '</div>';
                unset($_SESSION['register_error']); // Clear the message after displaying it
            }
            ?>

            <form id="registerForm" action="register_process.php" method="POST" onsubmit="return validateForm()">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="username">Username (no spaces, will be converted to lowercase)</label>
                    <input type="text" id="username" name="username" required>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="date_of_birth">Date of Birth</label>
                    <input type="date" id="date_of_birth" name="date_of_birth" required>
                </div>
                
                <div class="form-group">
                    <label for="gender">Gender</label>
                    <select id="gender" name="gender" required>
                        <option value="" disabled selected>Select your gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                    <div id="password-strength-meter">
                        <div id="password-strength-bar"></div>
                    </div>
                    <div id="password-strength-text"></div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <button type="submit" class="btn btn-primary">Register</button>
            </form>
            <div class="login-link">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </div>

    <script>
        /**
         * Client-side form validation before submission.
         */
        function validateForm() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (password !== confirmPassword) {
                alert('Passwords do not match. Please try again.');
                return false; // Prevent form submission
            }
            return true; // Allow form submission
        }
        
        /**
         * Real-time username formatting
         */
        const usernameInput = document.getElementById('username');
        usernameInput.addEventListener('input', function() {
            // Converts to lowercase and removes spaces
            this.value = this.value.toLowerCase().replace(/\s/g, '');
        });

        /**
         * Password strength meter logic.
         */
        const passwordInput = document.getElementById('password');
        const strengthBar = document.getElementById('password-strength-bar');
        const strengthText = document.getElementById('password-strength-text');

        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            let text = 'Weak';
            let color = 'var(--error-color)';

            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;

            switch(strength) {
                case 0:
                case 1:
                case 2:
                    text = 'Weak';
                    color = '#dc3545'; // red
                    break;
                case 3:
                    text = 'Medium';
                    color = '#ffc107'; // yellow
                    break;
                case 4:
                    text = 'Strong';
                    color = '#28a745'; // green
                    break;
                case 5:
                    text = 'Very Strong';
                    color = '#007BFF'; // blue
                    break;
            }

            strengthBar.style.width = (strength * 20) + '%';
            strengthBar.style.backgroundColor = color;
            strengthText.textContent = text;
        });
    </script>
</body>
</html>