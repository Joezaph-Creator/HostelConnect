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
unset($_SESSION['auth_error']);

$old = $_SESSION['old_input'] ?? [];
unset($_SESSION['old_input']);

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Create Account | HostelConnect</title>

    <link rel="stylesheet" href="../../public/css/auth.css">
</head>

<body>

<div class="auth-container">

    <section class="auth-visual">

        <div class="brand">
            Hostel<span>Connect.</span>
        </div>

        <div class="visual-content">

            <h1>Find a place you'll love to call home.</h1>

            <p>
                Discover hostels, explore available rooms and book
                your ideal student accommodation with confidence.
            </p>

        </div>

        <div class="visual-footer">
            Connecting Students to Their Ideal Accommodation.
        </div>

    </section>


    <section class="auth-form-section">

        <div class="auth-card">

            <div class="auth-heading">

                <h2>Create your account</h2>

                <p>
                    Join HostelConnect and discover convenient
                    student accommodation.
                </p>

            </div>


            <?php if ($error): ?>

                <div class="auth-alert error">
                    <?= escape($error) ?>
                </div>

            <?php endif; ?>


            <form action="process_registration.php" method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escape($_SESSION['csrf_token']) ?>"
                >


                <div class="form-group">

                    <label for="full_name">Full Name</label>

                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        class="form-control"
                        placeholder="Enter your full name"
                        value="<?= escape($old['full_name'] ?? '') ?>"
                        maxlength="150"
                        autocomplete="name"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="email">Email Address</label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-control"
                        placeholder="example@email.com"
                        value="<?= escape($old['email'] ?? '') ?>"
                        maxlength="150"
                        autocomplete="email"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="phone_number">Phone Number</label>

                    <input
                        type="tel"
                        id="phone_number"
                        name="phone_number"
                        class="form-control"
                        placeholder="Enter your phone number"
                        value="<?= escape($old['phone_number'] ?? '') ?>"
                        maxlength="20"
                        autocomplete="tel"
                    >

                </div>


                <div class="form-group">

                    <label for="role">Account Type</label>

                    <select
                        id="role"
                        name="role"
                        class="form-control"
                        required
                    >

                        <option value="">Select account type</option>

                        <option value="Student"
                            <?= (($old['role'] ?? '') === 'Student') ? 'selected' : '' ?>>
                            Student
                        </option>

                        <option value="Owner"
                            <?= (($old['role'] ?? '') === 'Owner') ? 'selected' : '' ?>>
                            Hostel Owner
                        </option>

                    </select>

                </div>


                <div class="form-row">

                    <div class="form-group">

                        <label for="password">Password</label>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-control"
                            placeholder="Minimum 8 characters"
                            minlength="8"
                            autocomplete="new-password"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label for="confirm_password">Confirm Password</label>

                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            class="form-control"
                            placeholder="Repeat password"
                            minlength="8"
                            autocomplete="new-password"
                            required
                        >

                    </div>

                </div>


                <button type="submit" class="btn-primary">
                    Create Account
                </button>

            </form>


            <div class="auth-switch">

                Already have an account?

                <a href="login.php">Sign in</a>

            </div>

        </div>

    </section>

</div>

</body>
</html>