# Contributing to MedSync

First off, thank you for considering contributing to MedSync! Your help is greatly appreciated. Following these guidelines helps us keep the project organized and maintain a high standard of quality.

## How Can I Contribute?

There are many ways to contribute, from writing code and improving documentation to reporting bugs.

### Reporting Bugs

If you find a bug, please first check the existing [Issues](https://github.com/dev-jerin/medsync/issues) to see if it has already been reported. If not, please [open a new issue](https://github.com/dev-jerin/medsync/issues/new).

When reporting a bug, please include:
- A clear and descriptive title.
- A step-by-step description of how to reproduce the bug.
- The expected behavior and what actually happened.
- Any relevant error messages or screenshots.

### Suggesting Enhancements

If you have an idea for a new feature or an improvement to an existing one, please open an issue to discuss it. This allows us to coordinate efforts and ensure your idea aligns with the project's goals.

### Submitting Code Changes (Pull Requests)

1.  **Fork the repository** to your own GitHub account.
2.  **Clone your fork** to your local machine.
3.  **Create a new branch** for your feature or bug fix:
    ```bash
    git checkout -b feature/your-amazing-feature
    ```
4.  **Make your changes**. Please follow the style guides below.
5.  **Commit your changes** with a clear and descriptive commit message:
    ```bash
    git commit -m "feat: Add patient feedback module"
    ```
6.  **Push your branch** to your fork on GitHub:
    ```bash
    git push origin feature/your-amazing-feature
    ```
7.  **Open a Pull Request** from your branch to the `main` branch of the `dev-jerin/medsync` repository. Provide a clear description of the changes you've made.

---

## Project Setup

To work on MedSync locally, please follow the setup instructions in the main [README.md](./README.md#️-installation-and-configuration) file. This will guide you through setting up XAMPP, the database, and installing Composer dependencies.

---

## Styleguides & Conventions

To maintain consistency throughout the project, please adhere to the following styleguides.

### PHP Styleguide

-   **Coding Style**: All PHP code must adhere to the [PSR-12](https://www.php-fig.org/psr/psr-12/) standard.
-   **Security**:
    -   Always use prepared statements (`$conn->prepare()`) for database queries to prevent SQL injection.
    -   Sanitize all user output with `htmlspecialchars()` to prevent XSS attacks.
    -   Validate and sanitize all incoming data from `$_POST`, `$_GET`, etc.
-   **File Structure**:
    -   Place UI-related PHP files in their respective feature folders (e.g., `login/`, `register/`).
    -   Backend logic for AJAX calls should be handled in the `api.php` file within each role's directory (e.g., `admin/api.php`).
    -   Reusable functions should be placed in appropriate files (e.g., `config.php` for global functions).

### JavaScript Styleguide

-   **Modularity**: Encapsulate page-specific logic within functions and use a `DOMContentLoaded` listener to initialize scripts. See `register/script.js` for an example.
-   **AJAX**: Use the `fetch` API for all asynchronous requests to the backend.
-   **Consistency**: Follow the existing coding style for variable naming and function structure. Avoid using jQuery as it is not a project dependency.

### Database Styleguide

-   **Naming**: Table names should be plural and lowercase (e.g., `users`, `appointments`). Column names should use `snake_case` (e.g., `display_user_id`).
-   **Foreign Keys**: Ensure all foreign key relationships are properly defined with constraints (`ON DELETE`, `ON UPDATE`).

Thank you for your contribution!