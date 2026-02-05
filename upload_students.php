<?php
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    $password = $_POST['password'];
    if (empty($password)) $password = '';
    if ($password !== 'admin123') {
        header("Location: admin_login.php");
        exit;
    }
    $_SESSION['admin_logged_in'] = true;
}

// Check if security is verified for this action
$action_key = 'upload_students';
$verified = isset($_SESSION["security_verified_$action_key"]) && 
           (time() - $_SESSION["security_verified_time"] < 300); // 5 minutes timeout

if (!$verified) {
    header("Location: token_auth.php?action=$action_key&redirect=" . urlencode($_SERVER['PHP_SELF']));
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $file = $_FILES['csv_file']['tmp_name'];

    if (empty($file)) {
        $message = "Error: No file selected.";
    } else {
        $handle = fopen($file, "r");
        if (!$handle) {
            $message = "Error opening file.";
        } else {
            $db = new SQLite3('election.db');

            $success_count = 0;
            $error_count = 0;
            $invalid_sex_count = 0;
            $duplicate_lrns = []; // Track duplicate LRNs found in CSV
            $existing_duplicate_lrns = []; // Track LRNs that already exist in DB
            $valid_rows = []; // Store valid rows temporarily
            $duplicate_details = []; // Track duplicate details
            $existing_student_details = []; // Track existing student details

            // Skip header row
            fgetcsv($handle);

            // First pass: read all rows and validate
            $all_rows = [];
            while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if (count($row) < 7) {
                    $error_count++;
                    continue;
                }

                // Fix the LRN format - convert from scientific notation to full number
                $lrn_raw = trim($row[0]);
                
                // If LRN is in scientific notation, convert it to full number
                if (strpos($lrn_raw, 'E+') !== false || strpos($lrn_raw, 'e+') !== false) {
                    // Convert scientific notation to full number
                    $lrn = number_format((float)$lrn_raw, 0, '', '');
                } else {
                    // Normal case - just trim
                    $lrn = $lrn_raw;
                }
                
                $last_name = trim($row[1]);
                $middle_name = trim($row[2]);
                $given_name = trim($row[3]);
                $sex = trim($row[4]);
                $grade = (int)$row[5];
                $section = trim($row[6]);

                // Validate sex - must be exactly "Male" or "Female"
                if ($sex !== 'Male' && $sex !== 'Female') {
                    $invalid_sex_count++;
                    continue;
                }

                $all_rows[] = [
                    'lrn' => $lrn,
                    'last_name' => $last_name,
                    'middle_name' => $middle_name,
                    'given_name' => $given_name,
                    'sex' => $sex,
                    'grade' => $grade,
                    'section' => $section,
                    'row_data' => $row
                ];
            }

            fclose($handle);

            // Process rows and check for duplicates
            foreach ($all_rows as $row_data) {
                $lrn = $row_data['lrn'];
                
                // Check if LRN already exists in database
                $check_stmt = $db->prepare("SELECT * FROM students WHERE lrn = ?");
                $check_stmt->bindValue(1, $lrn);
                $check_result = $check_stmt->execute();
                $existing = $check_result->fetchArray();

                if ($existing) {
                    $existing_duplicate_lrns[] = $lrn;
                    $existing_student_details[$lrn] = $existing;
                }

                // Check if LRN is duplicate within the CSV file
                $duplicate_count = 0;
                foreach ($all_rows as $other_row) {
                    if ($other_row['lrn'] === $lrn) {
                        $duplicate_count++;
                    }
                }

                if ($duplicate_count > 1) {
                    if (!in_array($lrn, $duplicate_lrns)) {
                        $duplicate_lrns[] = $lrn;
                        // Collect all duplicate entries for this LRN
                        $duplicates_for_this_lrn = [];
                        foreach ($all_rows as $other_row) {
                            if ($other_row['lrn'] === $lrn) {
                                $duplicates_for_this_lrn[] = $other_row;
                            }
                        }
                        $duplicate_details[$lrn] = $duplicates_for_this_lrn;
                    }
                } else {
                    // Only add to valid rows if no duplicates and not existing in DB
                    if (!in_array($lrn, $existing_duplicate_lrns) && !in_array($lrn, $duplicate_lrns)) {
                        $valid_rows[] = $row_data['row_data'];
                    }
                }
            }

            // Show duplicate LRNs if any
            if (!empty($duplicate_lrns) || !empty($existing_duplicate_lrns)) {
                $message = "Upload stopped - duplicate LRNs found!";
            } else {
                // All validations passed, now insert the students
                foreach ($valid_rows as $row) {
                    // Process LRN to ensure it's a full number
                    $lrn_raw = trim($row[0]);
                    
                    // Convert scientific notation to full number
                    if (strpos($lrn_raw, 'E+') !== false || strpos($lrn_raw, 'e+') !== false) {
                        $lrn = number_format((float)$lrn_raw, 0, '', '');
                    } else {
                        $lrn = $lrn_raw;
                    }
                    
                    $last_name = trim($row[1]);
                    $middle_name = trim($row[2]);
                    $given_name = trim($row[3]);
                    $sex = trim($row[4]);
                    $grade = (int)$row[5];
                    $section = trim($row[6]);

                    // Generate unique token
                    $token = bin2hex(random_bytes(8));

                    // Insert student
                    $stmt = $db->prepare("
                        INSERT INTO students (lrn, last_name, middle_name, given_name, sex, grade_level, section, login_token)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->bindValue(1, $lrn);
                    $stmt->bindValue(2, $last_name);
                    $stmt->bindValue(3, $middle_name);
                    $stmt->bindValue(4, $given_name);
                    $stmt->bindValue(5, $sex);
                    $stmt->bindValue(6, $grade);
                    $stmt->bindValue(7, $section);
                    $stmt->bindValue(8, $token);

                    try {
                        if ($stmt->execute()) {
                            $success_count++;
                        } else {
                            $error_count++;
                        }
                    } catch (Exception $e) {
                        $error_count++;
                    }
                }

                $message = "Upload Complete!\nSuccess: $success_count students added\nErrors: $error_count failed";
                if ($invalid_sex_count > 0) {
                    $message .= "\nInvalid Sex Values: $invalid_sex_count records had invalid sex values (must be 'Male' or 'Female')";
                }
                
                // Clear verification after successful operation
                unset($_SESSION["security_verified_$action_key"]);
                unset($_SESSION['security_verified_time']);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>School Learner Government Election System - Upload Students</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
        .container { background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        input, button { padding: 8px 12px; margin: 5px; }
        button { background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .instructions { background-color: #e7f3ff; padding: 10px; border-left: 4px solid #007bff; margin: 10px 0; }
        .result { margin-top: 20px; }
        .back-link { display: inline-block; margin-top: 10px; }
        .download-csv { display: inline-block; background-color: #007bff; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; margin: 10px 0; }
        .error { color: red; }
        .duplicate-lrn { background-color: #f8d7da; padding: 10px; border-radius: 4px; margin: 5px 0; border: 1px solid #f5c6cb; }
        .duplicate-details { background-color: #fff3cd; padding: 10px; border-radius: 4px; margin: 10px 0; border: 1px solid #ffeaa7; }
        .student-info { margin: 5px 0; }
        .student-field { font-weight: bold; }
        .existing-student { background-color: #f1f3f4; padding: 5px; margin: 5px 0; border-radius: 4px; }
        .uploaded-student { background-color: #e3f2fd; padding: 5px; margin: 5px 0; border-radius: 4px; }
        .message { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error-message { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Upload Students (CSV)</h2>
        
        <div class="instructions">
            <p><strong>Format:</strong> LRN,Last Name,Middle Name,Given Name,Sex,Grade Level,Section</p>
            <p><strong>Example:</strong> 123456789012,Dela Cruz,Juan,Johnny,<span class="error">Male</span>,9,9-A</p>
            <p><strong>Requirements:</strong></p>
            <ul>
                <li>LRN must be unique</li>
                <li>Sex must be exactly "<span class="error">Male</span>" or "<span class="error">Female</span>" (case-sensitive)</li>
                <li>Grade Level must be a number (7, 8, 9, 10)</li>
                <li>Section can be any text</li>
            </ul>
        </div>

        <a href="sample_students.csv" class="download-csv">Download Sample CSV</a>

        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="csv_file" accept=".csv" required>
            <button type="submit">Upload Students</button>
        </form>

        <?php if ($message): ?>
            <?php if (strpos($message, 'Upload stopped') !== false): ?>
                <div class="message error-message">
                    <h3>Upload Stopped - Duplicate LRNs Found!</h3>
                    
                    <?php if (!empty($duplicate_lrns)): ?>
                        <div class="duplicate-lrn">
                            <p class="error"><strong>Duplicate LRNs in CSV file:</strong></p>
                            <?php foreach (array_unique($duplicate_lrns) as $duplicate_lrn): ?>
                                <p>• <?= htmlspecialchars($duplicate_lrn) ?></p>
                                
                                <!-- Show details of duplicate entries -->
                                <?php if (isset($duplicate_details[$duplicate_lrn])): ?>
                                    <div class="duplicate-details">
                                        <p><strong>Duplicate Entries Found:</strong></p>
                                        <?php foreach ($duplicate_details[$duplicate_lrn] as $index => $entry): ?>
                                            <div class="uploaded-student">
                                                <div class="student-info"><span class="student-field">Entry <?= ($index + 1) ?>:</span></div>
                                                <div class="student-info"><span class="student-field">Name:</span> <?= htmlspecialchars($entry['last_name']) ?>, <?= htmlspecialchars($entry['given_name']) ?> <?= htmlspecialchars($entry['middle_name']) ?></div>
                                                <div class="student-info"><span class="student-field">Sex:</span> <?= htmlspecialchars($entry['sex']) ?></div>
                                                <div class="student-info"><span class="student-field">Grade:</span> <?= htmlspecialchars($entry['grade']) ?></div>
                                                <div class="student-info"><span class="student-field">Section:</span> <?= htmlspecialchars($entry['section']) ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($existing_duplicate_lrns)): ?>
                        <div class="duplicate-lrn">
                            <p class="error"><strong>LRNs already exist in database:</strong></p>
                            <?php foreach (array_unique($existing_duplicate_lrns) as $existing_duplicate): ?>
                                <p>• <?= htmlspecialchars($existing_duplicate) ?></p>
                                
                                <!-- Show details of existing student -->
                                <?php if (isset($existing_student_details[$existing_duplicate])): ?>
                                    <?php $existing = $existing_student_details[$existing_duplicate]; ?>
                                    <div class="duplicate-details">
                                        <p><strong>Existing Student in Database:</strong></p>
                                        <div class="existing-student">
                                            <div class="student-info"><span class="student-field">Name:</span> <?= htmlspecialchars($existing['last_name']) ?>, <?= htmlspecialchars($existing['given_name']) ?> <?= htmlspecialchars($existing['middle_name']) ?></div>
                                            <div class="student-info"><span class="student-field">Sex:</span> <?= htmlspecialchars($existing['sex']) ?></div>
                                            <div class="student-info"><span class="student-field">Grade:</span> <?= htmlspecialchars($existing['grade_level']) ?></div>
                                            <div class="student-info"><span class="student-field">Section:</span> <?= htmlspecialchars($existing['section']) ?></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <p class="error">No students were added to the database. Please fix the duplicate LRNs and try again.</p>
                </div>
            <?php else: ?>
                <div class="message success">
                    <h3>Upload Complete!</h3>
                    <pre><?= htmlspecialchars($message) ?></pre>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="back-link">
            <a href="admin_panel.php">← Back to Admin Panel</a>
        </div>
    </div>
</body>
</html>