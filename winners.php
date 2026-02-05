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

$school_class = $settings['school_classification'];
$db = new SQLite3('election.db');
?>

<!DOCTYPE html>
<html>
<head>
    <title>School Learner Government Election System - Winners</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
        .container { background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h2 { color: #007bff; }
        .classification { background-color: #e7f3ff; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .winner-row { background-color: #d4edda; border: 2px solid #28a745; padding: 10px; margin: 5px 0; border-radius: 5px; }
        .runnerup-row { background-color: #fff3cd; border: 2px solid #ffc107; padding: 10px; margin: 5px 0; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f8f9fa; }
        .back-link { margin-top: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Official Election Winners</h2>
        
        <div class="classification">
            <strong>School:</strong> <?= htmlspecialchars($settings['school_name']) ?><br>
            <strong>Classification:</strong> <?= htmlspecialchars($school_class) ?><br>
            <strong>Winning Rule:</strong> 
            <?php if (in_array($school_class, ['Small', 'Medium'])): ?>
                Top 1 winner per grade level representative
            <?php else: ?>
                Top 2 winners per grade level representative
            <?php endif; ?>
        </div>

        <h3>Election Results Summary</h3>
        
        <?php
        // Define positions by category
        $general_positions = [
            'President', 'Vice President', 'Secretary', 'Treasurer', 
            'Auditor', 'Public Information Officer', 'Protocol Officer'
        ];

        $representative_positions = [];
        if (in_array($school_class, ['Small', 'Medium'])) {
            $representative_positions = [
                'Grade 10 Representative',
                'Grade 9 Representative', 
                'Grade 8 Representative'
            ];
        } else {
            $representative_positions = [
                'Grade 10 Representative 1', 'Grade 10 Representative 2',
                'Grade 9 Representative 1', 'Grade 9 Representative 2',
                'Grade 8 Representative 1', 'Grade 8 Representative 2'
            ];
        }

        // Show general positions (top 1 per position)
        foreach ($general_positions as $position) {
            $stmt = $db->prepare("SELECT name, party, vote_count FROM candidates WHERE position = ? ORDER BY vote_count DESC LIMIT 1");
            $stmt->bindValue(1, $position);
            $result = $stmt->execute();
            $winner = $result->fetchArray();
            
            if ($winner) {
                echo "<div class='winner-row'>";
                echo "<strong>$position:</strong> {$winner['name']} ({$winner['party']}) - {$winner['vote_count']} votes";
                echo "</div>";
            }
        }

        // Show representative positions
        foreach ($representative_positions as $position) {
            $stmt = $db->prepare("SELECT name, party, vote_count FROM candidates WHERE position = ? ORDER BY vote_count DESC LIMIT 1");
            $stmt->bindValue(1, $position);
            $result = $stmt->execute();
            $winner = $result->fetchArray();
            
            if ($winner) {
                echo "<div class='";
                if (strpos($position, ' 1') !== false || strpos($position, ' 2') !== false) {
                    echo "winner-row";
                } else {
                    echo "winner-row";
                }
                echo "'>";
                echo "<strong>$position:</strong> {$winner['name']} ({$winner['party']}) - {$winner['vote_count']} votes";
                echo "</div>";
            }
        }

        // Show runner-ups for Large/Mega schools
        if (in_array($school_class, ['Large', 'Mega'])) {
            echo "<h3>Runner-ups (Top 2 Winners)</h3>";
            
            $runner_up_positions = [
                'Grade 10 Representative', 'Grade 9 Representative', 'Grade 8 Representative'
            ];
            
            foreach ($runner_up_positions as $base_position) {
                $stmt = $db->prepare("SELECT name, party, vote_count FROM candidates WHERE position LIKE ? ORDER BY vote_count DESC LIMIT 2 OFFSET 1");
                $stmt->bindValue(1, "$base_position%");
                $result = $stmt->execute();
                
                $rank = 2;
                while ($runner = $result->fetchArray()) {
                    echo "<div class='runnerup-row'>";
                    echo "<strong>{$base_position} - Rank $rank:</strong> {$runner['name']} ({$runner['party']}) - {$runner['vote_count']} votes";
                    echo "</div>";
                    $rank++;
                    if ($rank > 2) break; // Only show top 2
                }
            }
        }
        ?>

        <h3>Detailed Results</h3>
        <table>
            <thead>
                <tr>
                    <th>Position</th>
                    <th>Name</th>
                    <th>Party</th>
                    <th>Votes</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sql = "SELECT position, name, party, vote_count FROM candidates ORDER BY 
                    CASE position
                        WHEN 'President' THEN 1
                        WHEN 'Vice President' THEN 2
                        WHEN 'Secretary' THEN 3
                        WHEN 'Treasurer' THEN 4
                        WHEN 'Auditor' THEN 5
                        WHEN 'Public Information Officer' THEN 6
                        WHEN 'Protocol Officer' THEN 7
                        WHEN 'Grade 10 Representative' THEN 8
                        WHEN 'Grade 10 Representative 1' THEN 8
                        WHEN 'Grade 10 Representative 2' THEN 9
                        WHEN 'Grade 9 Representative' THEN 10
                        WHEN 'Grade 9 Representative 1' THEN 10
                        WHEN 'Grade 9 Representative 2' THEN 11
                        WHEN 'Grade 8 Representative' THEN 12
                        WHEN 'Grade 8 Representative 1' THEN 12
                        WHEN 'Grade 8 Representative 2' THEN 13
                        ELSE 99
                    END ASC, vote_count DESC";

                $result = $db->query($sql);
                while ($row = $result->fetchArray()) {
                    $status = 'Participant';
                    
                    // Determine if this is a winner
                    if (strpos($row['position'], 'Representative') !== false) {
                        if (in_array($school_class, ['Small', 'Medium'])) {
                            // For Small/Medium, check if this is the top candidate for the position
                            $pos = $row['position'];
                            if ($pos === 'Grade 10 Representative' || $pos === 'Grade 9 Representative' || $pos === 'Grade 8 Representative') {
                                $top_stmt = $db->prepare("SELECT vote_count FROM candidates WHERE position = ? ORDER BY vote_count DESC LIMIT 1");
                                $top_stmt->bindValue(1, $pos);
                                $top_result = $top_stmt->execute();
                                $top = $top_result->fetchArray();
                                
                                if ($top && $row['vote_count'] == $top['vote_count']) {
                                    $status = '<span style="color: green; font-weight: bold;">WINNER</span>';
                                }
                            }
                        } else {
                            // For Large/Mega, check if this is in top 2 for the position
                            $base_pos = str_replace([' 1', ' 2'], '', $row['position']);
                            $top2_stmt = $db->prepare("SELECT vote_count FROM candidates WHERE position LIKE ? ORDER BY vote_count DESC LIMIT 2");
                            $top2_stmt->bindValue(1, "$base_pos%");
                            $top2_result = $top2_stmt->execute();
                            
                            $top_votes = [];
                            while ($top_row = $top2_result->fetchArray()) {
                                $top_votes[] = $top_row['vote_count'];
                            }
                            
                            if (in_array($row['vote_count'], $top_votes)) {
                                $status = '<span style="color: green; font-weight: bold;">WINNER</span>';
                            }
                        }
                    } else {
                        // For general positions, top vote getter wins
                        $pos = $row['position'];
                        if ($pos !== 'Grade 10 Representative' && $pos !== 'Grade 9 Representative' && $pos !== 'Grade 8 Representative') {
                            $top_stmt = $db->prepare("SELECT vote_count FROM candidates WHERE position = ? ORDER BY vote_count DESC LIMIT 1");
                            $top_stmt->bindValue(1, $pos);
                            $top_result = $top_stmt->execute();
                            $top = $top_result->fetchArray();
                            
                            if ($top && $row['vote_count'] == $top['vote_count']) {
                                $status = '<span style="color: green; font-weight: bold;">WINNER</span>';
                            }
                        }
                    }
                    
                    echo "<tr>";
                    echo "<td>".htmlspecialchars($row['position'])."</td>";
                    echo "<td>".htmlspecialchars($row['name'])."</td>";
                    $party_display = !empty($row['party']) ? htmlspecialchars($row['party']) : 'N/A';
                    echo "<td>".$party_display."</td>";
                    echo "<td>".$row['vote_count']."</td>";
                    echo "<td>$status</td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>

        <div class="back-link">
            <a href="admin_panel.php">← Back to Admin Panel</a>
        </div>
    </div>
</body>
</html>