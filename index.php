<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>UG to ChordPro Converter (PHP)</title>
  <style>
    textarea { width: 100%; height: 200px; font-family: monospace; margin: 10px 0; }
    input[type="file"], select, input[type='submit'] { margin: 10px 0; }
  </style>
</head>
<body>

<h1>Ultimate Guitar Tab to ChordPro Converter</h1>

<form method="POST" enctype="multipart/form-data">
  <label>Paste your tab:</label><br>
  <textarea name="pasted" placeholder="Paste UG tab here..."></textarea><br>

  <label>Or upload a .txt file:</label><br>
  <input type="file" name="upload"><br>

  <label>Add section comments?</label>
  <select name="add_comments">
    <option value="yes" selected>Yes</option>
    <option value="no">No</option>
  </select><br>

  <input type="submit" name="convert" value="Convert">
</form>

<?php
session_start(); // To store conversion result for download

function parse_to_chordpro($text, $add_comments = true) {
    $lines = preg_split("/\r\n|\n|\r/", trim($text));
    $output = '';
    $sectionCount = 1;

    for ($i = 0; $i < count($lines); $i++) {
        $line = rtrim($lines[$i]);

        if (trim($line) === '') {
            $output .= "\n";
            continue;
        }

        if ($i + 1 < count($lines)) {
            $chordLine = $line;
            $lyricLine = $lines[$i + 1];

            if (preg_match('/[A-G][#b]?m?(aj|sus|dim|aug)?\d*/', $chordLine) && preg_match('/[a-zA-Z]/', $lyricLine)) {
                if ($add_comments) {
                    $output .= "{comment:Section $sectionCount}\n";
                    $sectionCount++;
                }

                $combined = '';
                $j = 0;

                while ($j < strlen($lyricLine)) {
                    if (isset($chordLine[$j]) && trim($chordLine[$j]) !== '') {
                        $chord = '';
                        while (isset($chordLine[$j]) && $chordLine[$j] !== ' ') {
                            $chord .= $chordLine[$j];
                            $j++;
                        }
                        $combined .= "[$chord]";
                    }

                    $combined .= $lyricLine[$j] ?? '';
                    $j++;
                }

                $output .= $combined . "\n";
                $i++; // Skip next line
                continue;
            }
        }

        if ($add_comments && stripos($line, 'verse') !== false) {
            $output .= "{comment:" . trim($line) . "}\n";
        } elseif ($add_comments && stripos($line, 'chorus') !== false) {
            $output .= "{comment:" . trim($line) . "}\n";
        } else {
            $output .= $line . "\n";
        }
    }

    return trim($output);
}

if (isset($_POST['convert'])) {
    $rawText = '';

    if (!empty($_POST['pasted'])) {
        $rawText = $_POST['pasted'];
    }

    if (isset($_FILES['upload']) && $_FILES['upload']['error'] === UPLOAD_ERR_OK) {
        $rawText = file_get_contents($_FILES['upload']['tmp_name']);
    }

    $add_comments = $_POST['add_comments'] === 'yes';
    $converted = parse_to_chordpro($rawText, $add_comments);

    $_SESSION['chordpro_data'] = $converted;

    echo "<h2>ChordPro Output:</h2>";
    echo "<textarea readonly>" . htmlspecialchars($converted) . "</textarea><br>";

    echo '<form method="POST" action="?download=1">';
    echo '<input type="submit" value="Download .chordpro file">';
    echo '</form>';
}

// Handle download
if (isset($_GET['download']) && isset($_SESSION['chordpro_data'])) {
    $filename = 'converted.chordpro';
    header('Content-Type: text/plain');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    echo $_SESSION['chordpro_data'];
    unset($_SESSION['chordpro_data']); // Clear after download
    exit;
}
?>

</body>
</html>
