<?php

declare(strict_types=1);

session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = $_SESSION['auth_error'] ?? '';
$success = $_SESSION['auth_success'] ?? '';

unset($_SESSION['auth_error'], $_SESSION['auth_success']);

function escapeLogin(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Sign In | HostelConnect</title>

    <link rel="stylesheet" href="../../public/css/auth.css">

</head>

<body>

<div class="auth-container">

    <section class="auth-visual">

        <div class="brand">
            Hostel<span>Connect.</span>
        </div>

        <div class="visual-content">

            <h1>Your next home is just a few clicks away.</h1>

            <p>
                Sign in to explore available hostels, manage your bookings
                and enjoy a simpler accommodation experience.
            </p>

        </div>

        <div class="visual-footer">
            Connecting Students to Their Ideal Accommodation.
        </div>

    </section>


    <section class="auth-form-section">

        <div class="auth-card">

            <div class="auth-heading">

                <h2>Welcome back!</h2>

                <p>
                    Sign in to your HostelConnect account to continue.
                </p>

            </div>


            <?php if ($error): ?>

                <div class="auth-alert error">
                    <?= escapeLogin($error) ?>
                </div>

            <?php endif; ?>


            <?php if ($success): ?>

                <div class="auth-alert success">
                    <?= escapeLogin($success) ?>
                </div>

            <?php endif; ?>


            <form action="process_login.php" method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escapeLogin($_SESSION['csrf_token']) ?>"
                >


                <div class="form-group">

                    <label for="email">Email Address</label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-control"
                        placeholder="Enter your email address"
                        autocomplete="email"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="password">Password</label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required
                    >

                </div>


                <button type="submit" class="btn-primary">
                    Sign In
                </button>

            </form>


            <div class="auth-switch">

                Don't have an account?

                <a href="register.php">Create Account</a>

            </div>

        </div>

    </section>

</div>

</body>
</html>