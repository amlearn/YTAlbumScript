<?php
/**
 * Handle options (see setOptions)
 */
$youtubeFile = '';
$keepFiles   = false;
$filename    = uniqid();
setOptions();

/**
 * Extract audio and description from youtubeFile
 */
formatOut(
    "Downloading YouTube file and description and converting to mp3..." . PHP_EOL .
    "(This may take a few moments)"
);
exec("youtube-dl --extract-audio --write-description --audio-format mp3 -o \"{$filename}.%(ext)s\" {$youtubeFile}");

/**
 * Get tracklisting from description file
 */
$listingRegex = '/(\d{1,2}\:\d{2}(\:\d{2})?)\s+(.*)\n/';
$description  = file_get_contents($filename . '.description');
$matches      = [];
$times        = [];
$tracks       = [];

preg_match_all($listingRegex, $description, $matches);

if (count($matches) > 1) {
    $times  = $matches[1];
    $tracks = $matches[3];
} elseif (count($matches)) {
    formatOut("Couldn't get track listing from YouTube description");
    formatOut("File Downloaded: {$filename}.mp3", true);
}

formatOut("Retrieved track info from description - splitting...");

/**
 * Format the track time listings and split the mp3 into album
 */
$outdir = 'out/' . $filename;
$timeStr = '';

foreach ($times as $time) {
    $pieces = explode(':', $time);
    if (count($pieces) > 2) {
        $min = intval($pieces[0]) * 60 + intval($pieces[1]);
        $sec = intval($pieces[2]);
    } else {
        $min = intval($pieces[0]);
        $sec = intval($pieces[1]);
    }

    $timeStr .= $min . '.' . $sec . ' ';
}

$timeStr .= 'EOF';

@mkdir($outdir);

exec("mp3splt -d {$outdir} {$filename}.mp3 {$timeStr}");

$dirList    = scandir($outdir);
$trackFiles = [];

foreach ($dirList as $file) {
    if (is_numeric(strpos($file, '.mp3'))) {
        $trackFiles[] = $file;
    }
}

$numTracks = count($trackFiles);
$padTracks = strlen($numTracks);
foreach ($trackFiles as $order => $file) {
    $trackNum = str_pad(($order + 1), $padTracks, "0", STR_PAD_LEFT);
    exec("mv {$outdir}/\"{$file}\" {$outdir}/\"{$trackNum} - " . $tracks[$order] . ".mp3\"");
    formatOut('Created: ' . $tracks[$order]);
}

if (!$keepFiles) {
    exec("rm {$filename}.mp3");
    exec("rm {$filename}.description");
    formatOut(PHP_EOL . 'Removed raw YouTube files...');
}

formatOut(PHP_EOL . "Album created: {$outdir}", true);

/*** UTILITY ***/

/**
 * Options:
 * -k - (optional) keep downloads (default: cleanup)
 * -f - (optional) filename (default: uniqid)
 * -y - youtube link (required)
 */
function setOptions() {
    global $youtubeFile, $keepFiles, $filename;
    $options = getopt('y:f::k::');

    if (!isset($options['y'])) {
        formatOut('youtube link (-y) parameter required', true);
    }

    $youtubeFile = escapeshellarg($options['y']);

    if (isset($options['k'])) {
        if (!is_boolean($options['k'])) {
            formatOut('keepFiles option (-k) must be boolean', true);
        }
        $keepFiles = $options['k'];
    }

    if (isset($options['f'])) {
        if (!is_string($options['f']) || empty($options['f'])) {
            formatOut('Filename option (-f) must be a string', true);
        }
        $filename = escapeshellarg($options['f']);
    }
}

function formatOut($str, $die = false) {
    $str = $str . PHP_EOL;
    if ($die) {
        die($str . '--- END ---' . PHP_EOL);
    }
    echo $str;
}
