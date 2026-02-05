<?php
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit;
}

// Load school settings
$settings_file = 'school_settings.json';
$default_settings = [
    'school_name' => 'Sample High School',
    'school_id' => 'SHS-2026',
    'principal' => 'Dr. Juan Santos',
    'logo_path' => 'logo.png',
    'school_classification' => 'Small'
];

if (file_exists($settings_file)) {
    $settings = json_decode(file_get_contents($settings_file), true);
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

$db = new SQLite3('election.db');

// Get winners only (candidates with highest votes per position)
$winners = [];
$result = $db->query("SELECT position, MAX(vote_count) as max_votes FROM candidates GROUP BY position");
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $position = $row['position'];
    $max_votes = $row['max_votes'];
    
    // Get all candidates with max votes for this position (handles ties)
    $stmt = $db->prepare("SELECT * FROM candidates WHERE position = ? AND vote_count = ?");
    $stmt->bindValue(1, $position);
    $stmt->bindValue(2, $max_votes);
    $winner_result = $stmt->execute();
    
    while ($winner = $winner_result->fetchArray(SQLITE3_ASSOC)) {
        $winners[] = $winner;
    }
}

// Group by position
$grouped_winners = [];
foreach ($winners as $winner) {
    $position = $winner['position'];
    if (!isset($grouped_winners[$position])) {
        $grouped_winners[$position] = [];
    }
    $grouped_winners[$position][] = $winner;
}

// Determine positions based on school classification
$school_class = $settings['school_classification'] ?? 'Small';
$positions_order = [
    'President',
    'Vice President', 
    'Secretary',
    'Treasurer',
    'Auditor',
    'Public Information Officer',
    'Protocol Officer'
];

if (in_array($school_class, ['Small', 'Medium'])) {
    $positions_order = array_merge($positions_order, [
        'Grade 10 Representative',
        'Grade 9 Representative', 
        'Grade 8 Representative'
    ]);
} else {
    $positions_order = array_merge($positions_order, [
        'Grade 10 Representative 1',
        'Grade 10 Representative 2',
        'Grade 9 Representative 1',
        'Grade 9 Representative 2',
        'Grade 8 Representative 1',
        'Grade 8 Representative 2'
    ]);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Winners Report - <?= htmlspecialchars($settings['school_name']) ?></title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 0; 
            background-color: #f5f5f5; 
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
        }
        
        .controls {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
        }
        
        button {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin: 5px;
            font-size: 14px;
        }
        
        button:hover {
            background: #0056b3;
        }
        
        .report-container {
            width: 210mm; /* A4 width */
            min-height: 297mm; /* A4 height */
            margin: 0 auto;
            padding: 20mm;
            box-sizing: border-box;
            position: relative;
        }
        
        .report-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .school-logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 15px;
            display: block;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #000;
        }
        
        .school-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .report-title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 5px;
            text-decoration: underline;
        }
        
        .report-subtitle {
            font-size: 16px;
            margin-bottom: 20px;
        }
        
        .position-section {
            margin-bottom: 25px;
        }
        
        .position-title {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 10px;
            text-decoration: underline;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        
        th, td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
            font-size: 12px;
        }
        
        th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        
        .candidate-photo {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 5px;
            border: 1px solid #000;
        }
        
        .no-photo {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #ddd;
            display: inline-block;
            text-align: center;
            line-height: 30px;
            font-weight: bold;
            font-size: 14px;
            margin-right: 5px;
            border: 1px solid #000;
        }
        
        .signatories {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
        }
        
        .signature-block {
            width: 30%;
            text-align: center;
        }
        
        .signature-label {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .signature-line {
            border-top: 1px solid #000;
            margin: 20px auto 5px auto;
            width: 200px;
        }
        
        .signature-title {
            font-size: 12px;
            margin-top: 5px;
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
            }
            
            .controls {
                display: none;
            }
            
            .report-container {
                margin: 0;
                padding: 20mm;
                width: 100%;
                min-height: 100%;
                box-shadow: none;
                border: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="controls">
            <button onclick="window.print()">Print Winners Report</button>
            <button onclick="location.href='admin_panel.php'">← Back to Admin Panel</button>
        </div>
        
        <div class="report-container">
            <div class="report-header">
                <?php if (file_exists($settings['logo_path'])): ?>
                    <img src="<?= $settings['logo_path'] ?>" alt="School Logo" class="school-logo">
                <?php else: ?>
                    <div style="width: 80px; height: 80px; background: #f8f9fa; margin: 0 auto 15px; border-radius: 50%; border: 2px solid #000; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 24px;">
                        LOGO
                    </div>
                <?php endif; ?>
                
                <div class="school-name"><?= htmlspecialchars($settings['school_name']) ?></div>
                <div class="report-title">OFFICIAL WINNERS REPORT</div>
                <div class="report-subtitle"><?= htmlspecialchars($system_title) ?></div>
            </div>
            
            <?php foreach ($positions_order as $position): ?>
                <?php if (isset($grouped_winners[$position]) && !empty($grouped_winners[$position])): ?>
                    <div class="position-section">
                        <div class="position-title"><?= htmlspecialchars($position) ?></div>
                        <table>
                            <thead>
                                <tr>
                                    <th width="5%">#</th>
                                    <th width="50%">Candidate Name</th>
                                    <th width="20%">Party</th>
                                    <th width="25%">Votes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $rank = 1;
                                foreach ($grouped_winners[$position] as $winner): ?>
                                    <tr>
                                        <td><?= $rank ?></td>
                                        <td>
                                            <?php if (!empty($winner['photo_path']) && file_exists($winner['photo_path'])): ?>
                                                <img src="<?= $winner['photo_path'] ?>" alt="<?= $winner['name'] ?>" class="candidate-photo">
                                            <?php else: ?>
                                                <span class="no-photo"><?= strtoupper(substr($winner['name'], 0, 1)) ?></span>
                                            <?php endif; ?>
                                            <?= htmlspecialchars($winner['name']) ?>
                                        </td>
                                        <td><?= htmlspecialchars($winner['party'] ?? 'N/A') ?></td>
                                        <td><?= $winner['vote_count'] ?></td>
                                    </tr>
                                    <?php $rank++; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
            
            <div class="signatories">
                <div class="signature-block">
                    <div class="signature-label">Prepared by:</div>
                    <div style="height: 40px;"></div>
                    <div class="signature-line"></div>
                    <div class="signature-title">Commissioner on Screening and Validation</div>
                </div>
                
                <div class="signature-block">
                    <div class="signature-label">Attested by:</div>
                    <div style="height: 40px;"></div>
                    <div class="signature-line"></div>
                    <div class="signature-title">Commissioner on Electoral Board</div>
                </div>
                
                <div class="signature-block">
                    <div class="signature-label">Approved by:</div>
                    <div style="height: 40px;"></div>
                    <div class="signature-line"></div>
                    <div class="signature-title">LG COMEA Chief Commissioner</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>