<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start(); // To store conversion result for download

function extract_chords_from_line(string $line): array {
    // Remove trailing newline and extra spaces
    $line = rtrim($line);

    // Regex to match a chord, optionally wrapped in []
    // Matches e.g. C, G#m, Bbmaj7, Dsus4, F#, etc.
    $chordPattern = '/\[?\s*([A-G](?:#|b)?(?:m|min|maj|dim|aug|sus\d?|add\d?|maj\d?)?(?:(?:\\|\/)[A-G](?:#|b)?)?\d*|N\.C\.)\s*\]?/';

    // Find all matches along with their offsets
    preg_match_all($chordPattern, $line, $matches, PREG_OFFSET_CAPTURE);

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

    return $chords;
}

function is_chord_line(string $line): bool {

    if(is_empty_line($line)) {
        return false;
    }

    // regex to match a chord, optionally wrapped in []
    // matches e.g. C, G#m, Bbmaj7, Dsus4, F#, etc.
    $chordPattern = '/\[?\s*([A-G](?:#|b)?(?:m|min|maj|dim|aug|sus\d?|add\d?|maj\d?)?\d*)\s*\]?/';

    // remove all matched chords + spaces and see if leftover contains anything non-space
    $lineWithoutChords = preg_replace($chordPattern, '', $line);
    $lineWithoutChords = trim($lineWithoutChords);

    // if leftover contains text (lyrics), it's not purely a chord line
    return $lineWithoutChords === '';
}

function convert_section_header(string $line): string {
    $trimmed = trim($line);

    // Match a line like [Intro], [Verse 1], [Chorus], etc.
    if (preg_match('/^\[(.+)\]$/', $trimmed, $matches)) {
        $section = trim($matches[1]);
        return "{c: $section}";
    }

    // If not bracketed, return the original string
    return $line;
}

function normalize_spacing(string $line): string {
    // trim whitespace and remove double-spaces
    $trimmed = trim($line);
    $normalized = preg_replace('/\s{2,}/', ' ', $trimmed);

    return $normalized;
}

function is_empty_line(string $line): bool {
    return trim($line) === '';
}

function is_lyric_line(string $line): bool {
    if (is_empty_line($line) || is_section_header($line)) {
        return false;
    }
    return is_chord_line($line) === false;
}

function is_section_header(string $line): bool {
    $trimmed = trim($line);
    $is_bracketed = preg_match('/^\[.*\]$/', $trimmed);
    $is_curlybraced = preg_match('/^\{.*\}$/', $trimmed);
    return $is_bracketed || $is_curlybraced;
}

function get_next_line(array $lines, int $start): string {
    $i = $start + 1;
    $number_of_lines = count($lines)-1;
    while(($i < $number_of_lines) && is_empty_line($lines[$i])) {
        $i++;
    }
    return $i < $number_of_lines ? $lines[$i] : "";
}

function parse_to_chordpro(array $lines) {

    $output = '';
    $number_of_lines = count($lines);

    for ($i = 0; $i < $number_of_lines; $i++) {

        $line = $lines[$i];

        if (is_section_header($line)) {
            $output .= convert_section_header($line)."\n";
        } elseif (is_chord_line($line)) {
            $chords = extract_chords_from_line($line);
            $next_line = get_next_line($lines, $i);
            if (is_lyric_line($next_line)) {
                while($lines[$i] !== $next_line) {
                    $i++; // advance past the current lyric line
                }
                $next_line = normalize_spacing($next_line);
                foreach($chords as $chord) {
                    $next_line = substr_replace($next_line, $chord['chord'], $chord['offset'], 0);
                }
                $output .= $next_line."\n";
            } else {
                for ($k = 0; $k < count($chords)-1; $k++) {
                    $chord = $chords[$k];
                    $output .= $chord['chord']." [-] ";
                }
                $output .= $chords[count($chords)-1]['chord']."\n";
            }
        } else {
            $output .= $line."\n";
        }

    }

    return $output;
}

// -- FOR DEBUGGING --
//$lines = file('./pink-pony-club.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
//echo parse_to_chordpro($lines);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['upload']) && isset($_POST['convert-to-pro'])) {
    $rawText = '';

    //echo "console.log('hi there');";
    //if (!empty($_POST['pasted'])) {
    //    $rawText = $_POST['pasted'];
    //}

    $file = $_FILES['upload'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $rawText = file($_FILES['upload']['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }

    //print_r($rawText);

    $converted = parse_to_chordpro($rawText);

    $_SESSION['chordpro_data'] = $converted;
    $filename = 'converted.pro';
    header('Content-Type: text/plain');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    echo $_SESSION['chordpro_data'];
    unset($_SESSION['chordpro_data']); // Clear after download
    exit;
    //echo "<h2>ChordPro Output:</h2>";
    //echo "<textarea readonly>" . htmlspecialchars($converted) . "</textarea><br>";

    //echo '<form method="POST" action="?download=1">';
    //echo '<input type="submit" value="Download .pro file">';
    //echo '</form>';
}

?>

<?php
if (isset($_POST['convert-to-rtf']) && isset($_FILES['chordpro_file'])) {
    $file = $_FILES['chordpro_file'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $lines = file($file['tmp_name'], FILE_IGNORE_NEW_LINES);

        $rtf = "{\\rtf1\\ansi\\deff0\n";
        $rtf .= "{\\fonttbl{\\f0\\fnil Verdana;}}\n";
        $rtf .= "\\fs24\n"; // Default to 12pt (fs24 = 12pt, fs40 = 20pt, fs16 = 8pt)

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Title
            if (str_starts_with(strtolower($trimmed), '{title:')) {
                $title = trim(substr($trimmed, 7, -1));
                $rtf .= "\\fs40\\b $title\\b0\\fs24\\par\n";
            }
            // Author
            elseif (str_starts_with(strtolower($trimmed), '{artist:') || str_starts_with(strtolower($trimmed), '{author:')) {
                $author = trim(substr($trimmed, strpos($trimmed, ':') + 1, -1));
                $rtf .= "\\fs16 $author\\fs24\\par\n";
            }
            // Capo
            elseif (str_starts_with(strtolower($trimmed), '{capo:')) {
                $capo = trim(substr($trimmed, 6, -1));
                $rtf .= "\\fs24\\b Capo: $capo\\b0\\par\n";
            }
            // Comment line
            elseif (preg_match('/^\{[cC](:|\s)/', $trimmed)) {
                $comment = preg_replace('/^\{[cC](:|\s)|\}$/', '', $trimmed);
                $rtf .= "$comment\\par\n";
            }
            // Blank line
            elseif ($trimmed === '') {
                $rtf .= "\\par\n";
            }
            // Lyric line with chords
            else {

                // Escape backslashes and braces for RTF safety
                $line = str_replace(['\\', '{', '}'], ['\\\\', '\\{', '\\}'], $line);

                // Wrap [Chords] with bold italic
                $line = preg_replace_callback('/(\[.*?\])/', function ($m) {
                    return "\\b\\i " . $m[1] . " \\i0\\b0 ";
                }, $trimmed);

                $rtf .= "$line\\par\n";
            }
        }

        $rtf .= "}\n"; // End RTF

        // Output file
        header('Content-Type: application/rtf');
        header('Content-Disposition: attachment; filename="converted_song.rtf"');
        echo $rtf;
        exit;
    } else {
        echo "<p>Upload error.</p>";
    }
}
?>

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
  <button type="submit" name="convert-to-pro">Convert to Chordpro</button>
</form>

<h1>Convert ChordPro to RTF</h1>
  <form method="POST" enctype="multipart/form-data">
    <label>Select a .chordpro file:</label><br>
    <input type="file" name="chordpro_file" accept=".pro,.chordpro,.txt"><br><br>
    <input type="submit" name="convert-to-rtf" value="Convert to RTF">
  </form>

</body>
</html>
