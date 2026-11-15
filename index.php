<?php

declare(strict_types=1);

require_once __DIR__ . '/config/auth_check.php';

$fullName = htmlspecialchars(
    $_SESSION['full_name'] ?? 'User',
    ENT_QUOTES,
    'UTF-8'
);

$role = $_SESSION['role'] ?? 'Student';

$dashboardContent = [

    // STUDENT DASHBOARD
    'Student' => [
        'title' => 'Find Your Ideal Hostel',
        'description' => 'Discover accommodation, explore available rooms and manage your bookings.',

        'cards' => [

            [
                'title' => 'Explore Hostels',
                'description' => 'Discover hostels around your university.',
                'icon' => '🏠',
                'link' => 'modules/hostels/browse_hostels.php'
            ],

            [
                'title' => 'Available Rooms',
                'description' => 'Explore rooms and compare accommodation prices.',
                'icon' => '🛏️',
                'link' => 'modules/hostels/browse_hostels.php'
            ],

            [
                'title' => 'My Bookings',
                'description' => 'View and manage your hostel reservations.',
                'icon' => '📋',
                'link' => 'modules/bookings/my_bookings.php'
            ],

            [
                'title' => 'Payment History',
                'description' => 'Keep track of your booking payments.',
                'icon' => '💳',
                'link' => 'modules/payments/payment_history.php'
            ]

        ]
    ],

    // HOSTEL OWNER DASHBOARD
    'Owner' => [
        'title' => 'Manage Your Hostel Business',
        'description' => 'Manage your properties, rooms and student reservations.',

        'cards' => [

            [
                'title' => 'My Hostels',
                'description' => 'Add and manage your hostel properties.',
                'icon' => '🏠',
                'link' => 'modules/hostels/view_hostels.php'
            ],

            [
                'title' => 'Manage Rooms',
                'description' => 'Manage room information and availability.',
                'icon' => '🛏️',
                'link' => 'modules/rooms/view_rooms.php'
            ],

            [
                'title' => 'Bookings',
                'description' => 'Review student reservations.',
                'icon' => '📋',
                'link' => 'modules/bookings/owner_bookings.php'
            ],

            [
                'title' => 'Payments',
                'description' => 'Review booking payment records.',
                'icon' => '💳',
                'link' => 'modules/payments/owner_payment_history.php'
            ]

        ]
    ],

    // ADMIN DASHBOARD
    'Admin' => [
        'title' => 'System Administration',
        'description' => 'Oversee users, hostels, bookings and platform activities.',

        'cards' => [

            [
                'title' => 'Users',
                'description' => 'Manage registered platform users.',
                'icon' => '👥',
                'link' => '#'
            ],

            [
                'title' => 'Hostels',
                'description' => 'Oversee registered hostel properties.',
                'icon' => '🏠',
                'link' => '#'
            ],

            [
                'title' => 'Bookings',
                'description' => 'Monitor platform reservations.',
                'icon' => '📋',
                'link' => '#'
            ],

            [
                'title' => 'Payments',
                'description' => 'Monitor payment transactions.',
                'icon' => '💳',
                'link' => '#'
            ]

        ]
    ]

];

$content = $dashboardContent[$role] ?? $dashboardContent['Student'];

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard | HostelConnect</title>

    <link rel="stylesheet" href="public/css/dashboard.css">

    <style>

        .dashboard-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.08);
        }

        .dashboard-card-link {
            display: block;
            color: inherit;
            text-decoration: none;
        }

        .card-action {
            display: inline-block;
            margin-top: 15px;
            color: #2563eb;
            font-size: 13px;
            font-weight: 700;
        }

    </style>

</head>

<body>

<header class="topbar">

    <a href="index.php" class="brand">
        Hostel<span>Connect.</span>
    </a>

    <div class="topbar-right">

        <div class="user-info">

            <span class="user-name">
                <?= $fullName ?>
            </span>

            <span class="user-role">
                <?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>
            </span>

        </div>

        <a href="modules/authentication/logout.php" class="logout-btn">
            Sign Out
        </a>

    </div>

</header>


<main class="dashboard-container">

    <!-- WELCOME SECTION -->

    <section class="welcome-section">

        <div>

            <span class="welcome-label">
                YOUR ACCOMMODATION PARTNER
            </span>

            <h1>
                Welcome, <?= $fullName ?>!
            </h1>

            <p>
                <?= htmlspecialchars(
                    $content['description'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </p>

        </div>

        <div class="welcome-icon">
            🏡
        </div>

    </section>


    <!-- DASHBOARD HEADING -->

    <section class="dashboard-heading">

        <div>

            <h2>
                <?= htmlspecialchars(
                    $content['title'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </h2>

            <p>
                Choose an area to get started.
            </p>

        </div>

    </section>


    <!-- DASHBOARD CARDS -->

    <section class="dashboard-grid">

        <?php foreach ($content['cards'] as $card): ?>

            <article class="dashboard-card">

                <?php if ($card['link'] !== '#'): ?>

                    <a
                        href="<?= htmlspecialchars($card['link'], ENT_QUOTES, 'UTF-8') ?>"
                        class="dashboard-card-link"
                    >

                <?php endif; ?>


                <div class="card-icon">
                    <?= $card['icon'] ?>
                </div>

                <h3>
                    <?= htmlspecialchars(
                        $card['title'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </h3>

                <p>
                    <?= htmlspecialchars(
                        $card['description'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </p>

                <span class="card-action">

                    <?= $card['link'] !== '#'
                        ? 'Open Module →'
                        : 'Coming soon' ?>

                </span>


                <?php if ($card['link'] !== '#'): ?>

                    </a>

                <?php endif; ?>

            </article>

        <?php endforeach; ?>

    </section>


    <!-- FOOTER -->

    <footer class="dashboard-footer">

        <p>
            © <?= date('Y') ?> HostelConnect.
            Connecting Students to Their Ideal Accommodation.
        </p>

    </footer>

</main>

</body>
</html>