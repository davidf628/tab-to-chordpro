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

function is_chord_line(string $line): array|false {
    // Remove trailing newline and extra spaces
    $line = rtrim($line);

    // Regex to match a chord, optionally wrapped in []
    // Matches e.g. C, G#m, Bbmaj7, Dsus4, F#, etc.
    $chordPattern = '\[?\s*([A-G](?:#|b)?(?:m|min|maj|dim|aug|sus\d?|add\d?|maj\d?)?\d*)\s*\]?';

    // Find all matches along with their offsets
    preg_match_all('/' . $chordPattern . '/', $line, $matches, PREG_OFFSET_CAPTURE);

    // If no matches, it's definitely not a chord line
    if (empty($matches[1])) {
        return false;
    }

    // Build structured list of chords + positions
    $chords = [];
    foreach ($matches[1] as $match) {
        [$chordName, $offset] = $match;

        // Wrap chords in brackets, unless that has been done already:
        if (!preg_match('/^\[.+\]$/', $chordName)) {
            $chordName = "[".$chordName."]";
        }

        // Ignore empty matches from stray spaces
        if ($chordName !== '') {
            $chords[] = [
                'chord'  => $chordName,
                'offset' => $offset
            ];
        }
    }

    // Remove all matched chords + spaces and see if leftover contains anything non-space
    $lineWithoutChords = preg_replace('/' . $chordPattern . '/', '', $line);
    $lineWithoutChords = trim($lineWithoutChords);

    // If leftover contains text (lyrics), it's NOT purely a chord line
    if ($lineWithoutChords !== '') {
        return false;
    }

    return $chords;
}
function convert_bracketed_section(string $line): string {
    $trimmed = trim($line);

    // Match a line like [Intro], [Verse 1], [Chorus], etc.
    if (preg_match('/^\[(.+)\]$/', $trimmed, $matches)) {
        $section = trim($matches[1]);
        return "{c: $section}";
    }

    // If not bracketed, return the original string
    return $line;
}

// function remove_blank_strings(array $input): array {
//     return array_values(array_filter($input, function($str) {
//         return trim($str) !== '';
//     }));
// }

function normalize_spacing(string $line): string {
    // Trim leading/trailing whitespace
    $trimmed = trim($line);

    // Replace 2+ spaces/tabs with a single space
    $normalized = preg_replace('/\s{2,}/', ' ', $trimmed);

    return $normalized;
}

// function parse_to_chordpro($text, $add_comments = true) {
//     $lines = preg_split("/\r\n|\n|\r/", trim($text));
//     $lines = remove_blank_strings($lines);
//     $output = '';
//     $sectionCount = 1;

//     for ($i = 0; $i < count($lines); $i++) {
//         $line = rtrim($lines[$i]);

//         if (trim($line) === '') {
//             $output .= "\n";
//             continue;
//         }

//         if ($i + 1 < count($lines)) {
//             $chordLine = $line;
//             $lyricLine = $lines[$i + 1];

//             if (preg_match('/[A-G][#b]?m?(aj|sus|dim|aug)?\d*/', $chordLine) && preg_match('/[a-zA-Z]/', $lyricLine)) {
//                 if ($add_comments) {
//                     $output .= "{comment:Section $sectionCount}\n";
//                     $sectionCount++;
//                 }

//                 $combined = '';
//                 $j = 0;

//                 while ($j < strlen($lyricLine)) {
//                     if (isset($chordLine[$j]) && trim($chordLine[$j]) !== '') {
//                         $chord = '';
//                         while (isset($chordLine[$j]) && $chordLine[$j] !== ' ') {
//                             $chord .= $chordLine[$j];
//                             $j++;
//                         }
//                         $combined .= "[$chord]";
//                     }

//                     $combined .= $lyricLine[$j] ?? '';
//                     $j++;
//                 }

//                 $output .= $combined . "\n";
//                 $i++; // Skip next line
//                 continue;
//             }
//         }

//         if ($add_comments && stripos($line, 'verse') !== false) {
//             $output .= "{comment:" . trim($line) . "}\n";
//         } elseif ($add_comments && stripos($line, 'chorus') !== false) {
//             $output .= "{comment:" . trim($line) . "}\n";
//         } else {
//             $output .= $line . "\n";
//         }
//     }

//     return trim($output);
// }

function is_lyric_line(string $line): bool {

    // Empty line is NOT lyrics
    if (trim($line) === '') {
        return false;
    }

    // 1) If entire line is wrapped in brackets [Intro] -> NOT lyrics
    if (preg_match('/^\[.*\]$/', trim($line))) {
        return false;
    }

    // 2) If entire line is wrapped in brackets {c: Intro} -> NOT lyrics
    if (preg_match('/^\{.*\}$/', trim($line))) {
        return false;
    }

    // 3) If line is only chords -> NOT lyrics
    return is_chord_line($line) === false;

}


function parse_to_chordpro(array $lines) {

    $output = '';

    foreach($lines as $line) {
        $line = convert_bracketed_section($line);
        if (is_chord_line($line)) {
            //echo "--> CHORDS: ".$line."\n";
            $output .= "--> CHORDS: ".$line."\n";
            $chords = is_chord_line($line);
            foreach($chords as $chord) {
                $output .= $chord['chord']." ".$chord['offset']."\n";
            }
        } elseif (is_lyric_line($line)) {
            //echo "--> LYRICS: ".normalize_spacing($line)."\n";
            $output .= "--> LYRICS: ".normalize_spacing($line)."\n";
        } else {
            //echo $line."\n";
            $output .= $line."\n";
        }
    }
    return $output;
}

// -- FOR DEBUGGING --
//$lines = file('./pink-pony-club.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
//echo parse_to_chordpro($lines);

if (isset($_POST['convert'])) {
    $rawText = '';

    if (!empty($_POST['pasted'])) {
        $rawText = $_POST['pasted'];
    }

    if (isset($_FILES['upload']) && $_FILES['upload']['error'] === UPLOAD_ERR_OK) {
        $rawText = file($_FILES['upload']['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }

    $add_comments = $_POST['add_comments'] === 'yes';
    $converted = parse_to_chordpro($rawText);

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
