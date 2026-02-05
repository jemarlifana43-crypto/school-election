<?php
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    $password = $_POST['password'] ?? '';
    if ($password !== 'admin123') {
        header("Location: admin_login.php");
        exit;
    }
    $_SESSION['admin_logged_in'] = true;
}

// Check if settings file exists, if not create default
$settings_file = 'school_settings.json';
$default_settings = [
    'school_name' => 'Sample High School',
    'school_id' => 'SHS-2026',
    'principal' => 'Dr. Juan Santos',
    'logo_path' => 'logo.png',
    'school_classification' => 'Small',
    'school_level' => 'Junior High School'
];

if (file_exists($settings_file)) {
    $settings = json_decode(file_get_contents($settings_file), true);
    // Ensure all keys exist
    $settings = array_merge($default_settings, $settings);
} else {
    $settings = $default_settings;
}

// Determine system title based on school level
$school_level = $settings['school_level'] ?? 'Junior High School';
if ($school_level === 'Elementary') {
    $system_title = "Supreme Elementary Learner Government Election System";
} else {
    $system_title = "Supreme Secondary Learner Government Election System";
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $settings['school_name'] = $_POST['school_name'];
    $settings['school_id'] = $_POST['school_id'];
    $settings['principal'] = $_POST['principal'];
    $settings['school_classification'] = $_POST['school_classification'];
    $settings['school_level'] = $_POST['school_level'];
    
    // Handle logo upload
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "";
        $target_file = $target_dir . basename($_FILES["logo"]["name"]);
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        
        // Check if image file is actual image
        $check = getimagesize($_FILES["logo"]["tmp_name"]);
        if ($check !== false) {
            $new_filename = 'logo.' . $imageFileType;
            if (move_uploaded_file($_FILES["logo"]["tmp_name"], $new_filename)) {
                $settings['logo_path'] = $new_filename;
                $message = "Settings and logo updated successfully!";
            } else {
                $message = "Error uploading logo.";
            }
        } else {
            $message = "File is not an image.";
        }
    } else {
        $message = "Settings updated successfully!";
    }

    file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT));
}
?>

<!DOCTYPE html>
<html>
<head>
    <title><?= htmlspecialchars($system_title) ?> - School Settings</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .form-group { margin: 10px 0; }
        label { display: inline-block; width: 150px; }
        input, select, textarea { padding: 5px; width: 300px; }
        button { padding: 8px 15px; background-color: #007bff; color: white; border: none; border-radius: 4px; }
        .preview { margin-top: 20px; }
        .current-logo { max-width: 150px; max-height: 150px; border: 1px solid #ddd; padding: 5px; }
        .info-box {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .instructions {
            background-color: #e7f3ff;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 4px solid #007bff;
        }
        .instructions h3 {
            margin-top: 0;
        }
        .instructions ul {
            margin: 5px 0;
            padding-left: 20px;
        }
        .password-section {
            background-color: #fff3cd;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #ffc107;
        }
        .password-section h3 {
            margin-top: 0;
            color: #856404;
        }
        .password-btn {
            background-color: #ffc107;
            color: #212529;
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            display: inline-block;
            margin-top: 10px;
        }
        .password-btn:hover {
            background-color: #e0a800;
        }
        .success-message {
            color: green;
        }
        .error-message {
            color: red;
        }
    </style>
</head>
<body>
    <h2><?= htmlspecialchars($system_title) ?> - School Settings</h2>
    <?php if ($message) echo "<p class='success-message'>$message</p>"; ?>

    <div class="password-section">
        <h3>Admin Password Management</h3>
        <p>Click the button below to change your admin password.</p>
        <a href="change_password.php" class="password-btn">Change Admin Password</a>
    </div>

    <div class="instructions">
        <h3>Instructions:</h3>
        <ul>
            <li><strong>School Level</strong> determines which grades can participate</li>
            <li><strong>School Classification</strong> affects number of representatives</li>
        </ul>
    </div>

    <div class="info-box">
        <strong>School Level Details:</strong>
        <ul>
            <li><strong>Elementary (Grade 3-6):</strong> President, VP (Grade 4-5), Secretary/Treasurer/Auditor/PIO/PO (Grade 2-5), Reps for Grades 2-6</li>
            <li><strong>Junior High School (Grade 7-10):</strong> Standard positions, Reps for Grades 7-10</li>
            <li><strong>Integrated School (Grade 7-12):</strong> President, VP (Grade 11-12), other positions (Grade 7-11), Reps for Grades 7-12</li>
            <li><strong>Senior High School (Grade 11-12):</strong> All positions for Grade 11, Rep for Grade 12</li>
        </ul>
    </div>

    <div class="info-box">
        <strong>Classification Details:</strong>
        <ul>
            <li><strong>Small/Medium:</strong> 1 representative per grade level</li>
            <li><strong>Large/Mega:</strong> 2 representatives per grade level</li>
        </ul>
    </div>

    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="update_settings" value="1">
        <div class="form-group">
            <label>School Name:</label>
            <input type="text" name="school_name" value="<?= htmlspecialchars($settings['school_name']) ?>" required>
        </div>

        <div class="form-group">
            <label>School ID:</label>
            <input type="text" name="school_id" value="<?= htmlspecialchars($settings['school_id']) ?>" required>
        </div>

        <div class="form-group">
            <label>Principal:</label>
            <input type="text" name="principal" value="<?= htmlspecialchars($settings['principal']) ?>" required>
        </div>

        <div class="form-group">
            <label>School Level:</label>
            <select name="school_level" required>
                <option value="Elementary" <?= $settings['school_level'] === 'Elementary' ? 'selected' : '' ?>>Elementary (Grade 3-6)</option>
                <option value="Junior High School" <?= $settings['school_level'] === 'Junior High School' ? 'selected' : '' ?>>Junior High School (Grade 7-10)</option>
                <option value="Integrated School" <?= $settings['school_level'] === 'Integrated School' ? 'selected' : '' ?>>Integrated School (Grade 7-12)</option>
                <option value="Senior High School" <?= $settings['school_level'] === 'Senior High School' ? 'selected' : '' ?>>Senior High School (Grade 11-12)</option>
            </select>
        </div>

        <div class="form-group">
            <label>School Classification:</label>
            <select name="school_classification" required>
                <option value="Small" <?= $settings['school_classification'] === 'Small' ? 'selected' : '' ?>>Small</option>
                <option value="Medium" <?= $settings['school_classification'] === 'Medium' ? 'selected' : '' ?>>Medium</option>
                <option value="Large" <?= $settings['school_classification'] === 'Large' ? 'selected' : '' ?>>Large</option>
                <option value="Mega" <?= $settings['school_classification'] === 'Mega' ? 'selected' : '' ?>>Mega</option>
            </select>
        </div>

        <div class="form-group">
            <label>New Logo:</label>
            <input type="file" name="logo" accept="image/*">
            <small>(Leave blank to keep current logo)</small>
        </div>

        <button type="submit">Update Settings</button>
    </form>

    <div class="preview">
        <h3>Current Settings Preview:</h3>
        <p><strong>School Name:</strong> <?= htmlspecialchars($settings['school_name']) ?></p>
        <p><strong>School ID:</strong> <?= htmlspecialchars($settings['school_id']) ?></p>
        <p><strong>Principal:</strong> <?= htmlspecialchars($settings['principal']) ?></p>
        <p><strong>School Level:</strong> <?= htmlspecialchars($settings['school_level']) ?></p>
        <p><strong>School Classification:</strong> <?= htmlspecialchars($settings['school_classification']) ?></p>
        
        <?php if (file_exists($settings['logo_path'])): ?>
            <p><strong>Current Logo:</strong></p>
            <img src="<?= $settings['logo_path'] ?>" alt="Current Logo" class="current-logo">
        <?php else: ?>
            <p><strong>No logo uploaded yet.</strong></p>
        <?php endif; ?>
    </div>

    <br><a href="admin_panel.php">Back to Admin Panel</a>
</body>
</html>