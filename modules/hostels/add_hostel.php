<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/role_check.php';
requireRole(['Owner']);

require_once __DIR__ . '/../../config/database.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$universities = $pdo->query(
    "SELECT university_id, university_name
     FROM universities
     ORDER BY university_name"
)->fetchAll();

$error = $_SESSION['hostel_error'] ?? '';
unset($_SESSION['hostel_error']);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Add Hostel | HostelConnect</title>

    <link rel="stylesheet" href="../../public/css/auth.css">
    <link rel="stylesheet" href="../../public/css/dashboard.css">

    <style>
        .page-container {
            max-width: 850px;
            margin: 45px auto;
            padding: 0 20px;
        }

        .form-card {
            background: white;
            padding: 35px;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 8px 25px rgba(15, 23, 42, .04);
        }

        .page-heading {
            margin-bottom: 28px;
        }

        .page-heading h1 {
            color: #0f172a;
            font-size: 27px;
            margin-bottom: 9px;
        }

        .page-heading p {
            color: #64748b;
            font-size: 14px;
        }

        .hostel-form .form-group {
            margin-bottom: 22px;
        }

        .hostel-form label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 9px;
            color: #0f172a;
        }

        .hostel-form input,
        .hostel-form select,
        .hostel-form textarea {
            width: 100%;
            padding: 14px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            outline: none;
        }

        .hostel-form input:focus,
        .hostel-form select:focus,
        .hostel-form textarea:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .1);
        }

        .hostel-form textarea {
            min-height: 120px;
            resize: vertical;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-top: 28px;
        }

        .submit-btn {
            border: none;
            background: #2563eb;
            color: white;
            padding: 14px 24px;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
        }

        .back-btn {
            text-decoration: none;
            color: #475569;
            font-size: 14px;
            font-weight: 600;
        }

        .error-message {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            padding: 13px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        @media(max-width: 600px) {
            .form-card {
                padding: 22px;
            }

            .form-actions {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>

<body>

<header class="topbar">

    <a href="../../index.php" class="brand">
        Hostel<span>Connect.</span>
    </a>

    <div class="topbar-right">
        <div class="user-info">
            <span class="user-name">
                <?= htmlspecialchars($_SESSION['full_name'], ENT_QUOTES, 'UTF-8') ?>
            </span>
            <span class="user-role">Hostel Owner</span>
        </div>

        <a href="../authentication/logout.php" class="logout-btn">
            Sign Out
        </a>
    </div>

</header>

<main class="page-container">

    <div class="page-heading">
        <h1>Add a New Hostel</h1>
        <p>Provide accurate information about your accommodation property.</p>
    </div>

    <div class="form-card">

        <?php if ($error): ?>
            <div class="error-message">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form class="hostel-form" action="process_hostel.php" method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
            >

            <div class="form-group">
                <label for="hostel_name">Hostel Name</label>
                <input
                    type="text"
                    id="hostel_name"
                    name="hostel_name"
                    maxlength="150"
                    placeholder="e.g. Royal Heights Hostel"
                    required
                >
            </div>

            <div class="form-group">
                <label for="university_id">Select University</label>

                <select id="university_id" name="university_id" required>
                    <option value="">Choose a university</option>

                    <?php foreach ($universities as $university): ?>
                        <option value="<?= (int) $university['university_id'] ?>">
                            <?= htmlspecialchars($university['university_name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>

            <div class="form-group">
                <label for="location">Hostel Location</label>
                <input
                    type="text"
                    id="location"
                    name="location"
                    maxlength="200"
                    placeholder="Enter the hostel's location"
                    required
                >
            </div>

            <div class="form-group">
                <label for="contact_number">Contact Number</label>
                <input
                    type="tel"
                    id="contact_number"
                    name="contact_number"
                    maxlength="20"
                    placeholder="Enter contact number"
                >
            </div>

            <div class="form-group">
                <label for="description">Hostel Description</label>
                <textarea
                    id="description"
                    name="description"
                    placeholder="Describe the facilities, environment and services available..."
                ></textarea>
            </div>

            <div class="form-actions">

                <button type="submit" class="submit-btn">
                    Save Hostel
                </button>

                <a href="view_hostels.php" class="back-btn">
                    View My Hostels
                </a>

            </div>

        </form>

    </div>

</main>

</body>
</html>