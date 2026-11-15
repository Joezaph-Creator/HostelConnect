<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/role_check.php';
requireRole(['Owner']);
require_once __DIR__ . '/../../config/database.php';

$hostelId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$hostelId) {
    exit("Invalid hostel ID.");
}

$stmt = $pdo->prepare(
    "SELECT * FROM hostels WHERE hostel_id = ? AND owner_id = ?"
);
$stmt->execute([$hostelId, $_SESSION['user_id']]);
$hostel = $stmt->fetch();

if (!$hostel) {
    http_response_code(404);
    exit("Hostel not found.");
}

$universities = $pdo->query(
    "SELECT university_id, university_name FROM universities ORDER BY university_name"
)->fetchAll();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Hostel | HostelConnect</title>
    <link rel="stylesheet" href="../../public/css/dashboard.css">
</head>
<body>

<div class="dashboard-container">
    <h2>Edit Hostel</h2>

    <form action="process_edit_hostel.php" method="POST">

        <input type="hidden" name="csrf_token"
               value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <input type="hidden" name="hostel_id"
               value="<?= (int)$hostel['hostel_id'] ?>">

        <label>Hostel Name</label>
        <input type="text" name="hostel_name" required
               value="<?= htmlspecialchars($hostel['hostel_name']) ?>">

        <label>University</label>
        <select name="university_id" required>
            <?php foreach ($universities as $university): ?>
                <option value="<?= (int)$university['university_id'] ?>"
                    <?= (int)$hostel['university_id'] === (int)$university['university_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($university['university_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Location</label>
        <input type="text" name="location" required
               value="<?= htmlspecialchars($hostel['location']) ?>">

        <label>Contact Number</label>
        <input type="text" name="contact_number"
               value="<?= htmlspecialchars($hostel['contact_number'] ?? '') ?>">

        <label>Description</label>
        <textarea name="description"><?= htmlspecialchars($hostel['description'] ?? '') ?></textarea>

        <button type="submit">Update Hostel</button>
    </form>

    <p><a href="view_hostels.php">Back to My Hostels</a></p>
</div>

</body>
</html>