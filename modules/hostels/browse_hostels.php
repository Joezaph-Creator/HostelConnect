<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/role_check.php';
requireRole(['Student']);

require_once __DIR__ . '/../../config/database.php';

// Search and filter values
$keyword = trim($_GET['search'] ?? '');
$universityId = filter_input(INPUT_GET, 'university_id', FILTER_VALIDATE_INT);

// Fetch universities for the filter dropdown
$universityStmt = $pdo->query("
    SELECT university_id, university_name
    FROM universities
    ORDER BY university_name
");

$universities = $universityStmt->fetchAll();

// Build search query
$sql = "
    SELECT
        h.hostel_id,
        h.hostel_name,
        h.location,
        h.description,
        h.contact_number,
        u.university_name,
        COUNT(r.room_id) AS room_count,
        MIN(r.price) AS minimum_price
    FROM hostels h
    INNER JOIN universities u
        ON h.university_id = u.university_id
    LEFT JOIN rooms r
        ON h.hostel_id = r.hostel_id
    WHERE 1 = 1
";

$params = [];

if ($keyword !== '') {
    $sql .= "
        AND (
            h.hostel_name LIKE :hostel_name
            OR h.location LIKE :location
            OR u.university_name LIKE :university_name
        )
    ";

    $searchTerm = '%' . $keyword . '%';

    $params[':hostel_name'] = $searchTerm;
    $params[':location'] = $searchTerm;
    $params[':university_name'] = $searchTerm;
}

if ($universityId) {
    $sql .= " AND h.university_id = :university_id";
    $params[':university_id'] = $universityId;
}

$sql .= "
    GROUP BY
        h.hostel_id,
        h.hostel_name,
        h.location,
        h.description,
        h.contact_number,
        u.university_name
    ORDER BY h.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$hostels = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Explore Hostels | HostelConnect</title>

    <link rel="stylesheet" href="../../public/css/dashboard.css">

    <style>

        .page-container {
            max-width: 1200px;
            margin: 45px auto;
            padding: 0 22px;
        }

        .page-heading {
            margin-bottom: 25px;
        }

        .page-heading h1 {
            font-size: 28px;
            margin-bottom: 8px;
        }

        .page-heading p {
            color: #64748b;
            font-size: 14px;
        }

        .search-panel {
            background: white;
            padding: 22px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            margin-bottom: 30px;
        }

        .search-form {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 12px;
        }

        .search-form input,
        .search-form select {
            width: 100%;
            padding: 13px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            box-sizing: border-box;
        }

        .search-btn {
            background: #2563eb;
            color: white;
            border: none;
            padding: 13px 20px;
            border-radius: 7px;
            font-weight: 700;
            cursor: pointer;
        }

        .hostel-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 22px;
        }

        .hostel-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 13px;
            padding: 24px;
            transition: .25s;
        }

        .hostel-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(15, 23, 42, .07);
        }

        .university-tag {
            display: inline-block;
            background: #eff6ff;
            color: #2563eb;
            padding: 7px 11px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 15px;
        }

        .hostel-card h2 {
            font-size: 19px;
            margin-bottom: 12px;
        }

        .hostel-card p {
            color: #64748b;
            font-size: 13px;
            line-height: 1.7;
            margin-bottom: 9px;
        }

        .hostel-meta {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-top: 18px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }

        .hostel-meta span {
            font-size: 12px;
            color: #64748b;
        }

        .hostel-meta strong {
            color: #2563eb;
        }

        .details-btn {
            display: block;
            text-align: center;
            background: #2563eb;
            color: white;
            text-decoration: none;
            padding: 12px;
            border-radius: 7px;
            margin-top: 18px;
            font-size: 13px;
            font-weight: 700;
        }

        .empty-state {
            background: white;
            text-align: center;
            padding: 55px 20px;
            border-radius: 13px;
            border: 1px solid #e2e8f0;
        }

        @media(max-width: 900px) {
            .hostel-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media(max-width: 650px) {
            .hostel-grid {
                grid-template-columns: 1fr;
            }

            .search-form {
                grid-template-columns: 1fr;
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

            <span class="user-role">Student</span>
        </div>

        <a href="../authentication/logout.php" class="logout-btn">
            Sign Out
        </a>

    </div>

</header>

<main class="page-container">

    <div class="page-heading">

        <h1>Explore Student Hostels</h1>

        <p>
            Discover accommodation options around your university.
        </p>

    </div>

    <section class="search-panel">

        <form method="GET" class="search-form">

            <input
                type="text"
                name="search"
                placeholder="Search hostel name or location..."
                value="<?= htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') ?>"
            >

            <select name="university_id">

                <option value="">All Universities</option>

                <?php foreach ($universities as $university): ?>

                    <option
                        value="<?= (int)$university['university_id'] ?>"
                        <?= $universityId === (int)$university['university_id'] ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($university['university_name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>

                <?php endforeach; ?>

            </select>

            <button type="submit" class="search-btn">
                Search Hostels
            </button>

        </form>

    </section>

    <?php if (count($hostels) > 0): ?>

        <section class="hostel-grid">

            <?php foreach ($hostels as $hostel): ?>

                <article class="hostel-card">

                    <span class="university-tag">
                        <?= htmlspecialchars($hostel['university_name'], ENT_QUOTES, 'UTF-8') ?>
                    </span>

                    <h2>
                        <?= htmlspecialchars($hostel['hostel_name'], ENT_QUOTES, 'UTF-8') ?>
                    </h2>

                    <p>
                        <strong>Location:</strong>
                        <?= htmlspecialchars($hostel['location'], ENT_QUOTES, 'UTF-8') ?>
                    </p>

                    <p>
                        <?= htmlspecialchars(
                            $hostel['description'] ?? 'No description provided.',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </p>

                    <div class="hostel-meta">

                        <span>
                            Listed Rooms:
                            <strong><?= (int)$hostel['room_count'] ?></strong>
                        </span>

                        <span>
                            From:
                            <strong>
                                <?= $hostel['minimum_price'] !== null
                                    ? 'GHS ' . number_format((float)$hostel['minimum_price'], 2)
                                    : 'Not listed' ?>
                            </strong>
                        </span>

                    </div>

                    <a
                        href="hostel_details.php?id=<?= (int)$hostel['hostel_id'] ?>"
                        class="details-btn"
                    >
                        View Hostel Details
                    </a>

                </article>

            <?php endforeach; ?>

        </section>

    <?php else: ?>

        <div class="empty-state">

            <h2>No Hostels Found</h2>

            <p>
                No hostels match your search.
                Try another hostel name, location or university.
            </p>

        </div>

    <?php endif; ?>

</main>

</body>
</html>