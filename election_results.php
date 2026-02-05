<?php
session_start();

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
    
    // Determine system title based on school level
    $school_level = $settings['school_level'] ?? 'Junior High School';
    if ($school_level === 'Elementary') {
        $system_title = "Supreme Elementary Learner Government Election System";
    } else {
        $system_title = "Supreme Secondary Learner Government Election System";
    }
} else {
    $settings = $default_settings;
    $system_title = "Supreme Secondary Learner Government Election System";
}

// Load results from database
$db = new SQLite3('election.db');

// Determine positions based on school classification
$schoolClass = $settings['school_classification'];
$order = [
    'President',
    'Vice President',
    'Secretary',
    'Treasurer',
    'Auditor',
    'Public Information Officer',
    'Protocol Officer'
];

if (in_array($schoolClass, ['Small', 'Medium'])) {
    $order = array_merge($order, [
        'Grade 10 Representative',
        'Grade 9 Representative',
        'Grade 8 Representative'
    ]);
} else {
    $order = array_merge($order, [
        'Grade 10 Representative 1',
        'Grade 10 Representative 2',
        'Grade 9 Representative 1',
        'Grade 9 Representative 2',
        'Grade 8 Representative 1',
        'Grade 8 Representative 2'
    ]);
}

// Get all results
$results = [];
$stmt = $db->prepare("SELECT c.position, c.name, c.party, c.vote_count, c.photo_path FROM candidates c ORDER BY c.position, c.vote_count DESC");
$result = $stmt->execute();

while ($row = $result->fetchArray()) {
    if (!isset($results[$row['position']])) {
        $results[$row['position']] = [];
    }
    $results[$row['position']][] = $row;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Election Results - <?= htmlspecialchars($system_title) ?></title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: white;
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
        }
        
        .header {
            text-align: center;
            padding: 40px 20px;
            border-bottom: 1px solid #e0e0e0;
            background: #f8f9fa;
        }
        
        .logo {
            width: 100px;
            height: 100px;
            margin: 0 auto 20px;
            display: block;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #dfe1e5;
        }
        
        .system-title {
            font-size: 2.5em;
            color: #202124;
            margin-bottom: 10px;
            font-weight: bold;
        }
        
        .school-info {
            font-size: 1.2em;
            color: #5f6368;
            margin-bottom: 10px;
        }
        
        .school-details {
            font-size: 1em;
            color: #5f6368;
        }
        
        .results-section {
            padding: 30px;
        }
        
        .position-group {
            margin-bottom: 40px;
            border: 1px solid #dfe1e5;
            border-radius: 8px;
            padding: 20px;
        }
        
        .position-title {
            font-size: 1.5em;
            color: #202124;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #4285f4;
        }
        
        .candidate-row {
            display: flex;
            align-items: center;
            padding: 15px;
            border: 1px solid #dfe1e5;
            border-radius: 8px;
            margin-bottom: 10px;
            background: white;
        }
        
        .candidate-rank {
            font-size: 1.2em;
            font-weight: bold;
            color: #4285f4;
            margin-right: 15px;
            min-width: 30px;
            text-align: center;
        }
        
        .candidate-photo {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 15px;
            border: 2px solid #dfe1e5;
        }
        
        .candidate-info {
            flex: 1;
        }
        
        .candidate-name {
            font-size: 1.1em;
            font-weight: bold;
            color: #202124;
        }
        
        .candidate-party {
            font-size: 0.9em;
            color: #5f6368;
            margin-top: 2px;
        }
        
        .candidate-votes {
            font-size: 1.2em;
            font-weight: bold;
            color: #28a745;
            margin-left: 20px;
        }
        
        .winner-badge {
            background: #28a745;
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            margin-left: 10px;
        }
        
        .stats-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #dfe1e5;
        }
        
        .stat-value {
            font-size: 2em;
            font-weight: bold;
            color: #4285f4;
            display: block;
        }
        
        .stat-label {
            font-size: 0.9em;
            color: #5f6368;
        }
        
        .footer {
            text-align: center;
            padding: 20px;
            margin-top: 30px;
            color: #70757a;
            font-size: 0.9em;
            border-top: 1px solid #e0e0e0;
        }
        
        .back-link {
            margin-top: 20px;
            text-align: center;
        }
        
        .back-link a {
            color: #4285f4;
            text-decoration: none;
            font-size: 14px;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .candidate-row {
                flex-direction: column;
                text-align: center;
            }
            
            .candidate-rank {
                margin-bottom: 10px;
            }
            
            .candidate-votes {
                margin-left: 0;
                margin-top: 10px;
            }
            
            .system-title {
                font-size: 2em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <?php if (file_exists($settings['logo_path'])): ?>
                <img src="<?= $settings['logo_path'] ?>" alt="School Logo" class="logo">
            <?php else: ?>
                <div class="logo" style="background-color: #f8f9fa; display: flex; align-items: center; justify-content: center; color: #5f6368; font-weight: bold; font-size: 1.5em;">LOGO</div>
            <?php endif; ?>
            
            <h1 class="system-title"><?= htmlspecialchars($system_title) ?></h1>
            <div class="school-info">
                <h2><?= htmlspecialchars($settings['school_name']) ?></h2>
            </div>
            <div class="school-details">
                School ID: <?= htmlspecialchars($settings['school_id']) ?> | 
                Principal: <?= htmlspecialchars($settings['principal']) ?> | 
                Classification: <?= htmlspecialchars($settings['school_classification']) ?>
            </div>
        </div>

        <div class="stats-section">
            <div class="stats-grid">
                <?php
                $total_students = $db->querySingle("SELECT COUNT(*) FROM students");
                $voted_students = $db->querySingle("SELECT COUNT(*) FROM students WHERE has_voted = 1");
                $turnout = $total_students > 0 ? round(($voted_students / $total_students) * 100, 2) : 0;
                $total_candidates = $db->querySingle("SELECT COUNT(*) FROM candidates");
                $total_votes = $db->querySingle("SELECT COUNT(*) FROM votes");
                ?>
                
                <div class="stat-card">
                    <span class="stat-value"><?= $total_students ?></span>
                    <span class="stat-label">Total Students</span>
                </div>
                
                <div class="stat-card">
                    <span class="stat-value"><?= $voted_students ?></span>
                    <span class="stat-label">Voted Students</span>
                </div>
                
                <div class="stat-card">
                    <span class="stat-value"><?= $turnout ?>%</span>
                    <span class="stat-label">Voter Turnout</span>
                </div>
                
                <div class="stat-card">
                    <span class="stat-value"><?= $total_votes ?></span>
                    <span class="stat-label">Total Votes</span>
                </div>
            </div>
        </div>

        <div class="results-section">
            <?php foreach ($order as $position): ?>
                <?php if (isset($results[$position]) && !empty($results[$position])): ?>
                    <div class="position-group">
                        <h3 class="position-title"><?= htmlspecialchars($position) ?></h3>
                        <?php foreach ($results[$position] as $rank => $candidate): ?>
                            <div class="candidate-row">
                                <div class="candidate-rank">#<?= $rank + 1 ?></div>
                                <?php if (!empty($candidate['photo_path']) && file_exists($candidate['photo_path'])): ?>
                                    <img src="<?= $candidate['photo_path'] ?>" alt="<?= $candidate['name'] ?>" class="candidate-photo">
                                <?php else: ?>
                                    <div class="candidate-photo" style="background-color: #f1f3f4; display: flex; align-items: center; justify-content: center; color: #6c757d; font-weight: bold; font-size: 1.5em;">
                                        <?= strtoupper(substr($candidate['name'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="candidate-info">
                                    <div class="candidate-name"><?= htmlspecialchars($candidate['name']) ?></div>
                                    <div class="candidate-party"><?= htmlspecialchars($candidate['party'] ?? 'N/A') ?></div>
                                </div>
                                <div class="candidate-votes"><?= $candidate['vote_count'] ?> votes</div>
                                <?php if ($rank === 0): ?>
                                    <div class="winner-badge">WINNER</div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        
        <div class="back-link">
            <a href="index.php">← Back to Home</a>
        </div>
        
        <div class="footer">
            <p>Final Election Results - <?= date('Y-m-d H:i:s') ?></p>
            <p>Powered by <?= htmlspecialchars($system_title) ?></p>
            <p>Developed by: <a href="https://www.facebook.com/sirtopet" target="_blank">Cristopher Duro</a></p>
        </div>
    </div>
</body>
</html>