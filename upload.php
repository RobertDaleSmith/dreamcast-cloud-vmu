<?php
// Turn on error reporting for debugging.
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Send content-type header.
header("Content-type: text/html");

// --- Configuration ---
$updir = "./uploads";  // Upload directory (no trailing slash)

// Create the upload directory if it doesn't already exist.
if (!is_dir($updir)) {
    mkdir($updir, 0755, true);
}

// --- Helper: Translation Table ---
// This function should return an associative array mapping characters to their
// "base-shifted" (translated) equivalents. In the original Perl script the table
// was built in table.lib. Here we use a placeholder mapping that simply maps each
// printable ASCII character to itself.
function getTranslationTable() {
    return array(
        '=' => '=',
        '+' => 'y',  // 0x2b => 0x79
        '/' => '/',  // 0x2f => 0x2f
        '0' => '2',  // 0x30 => 0x32
        '1' => '7',  // 0x31 => 0x37
        '2' => '0',  // 0x32 => 0x30
        '3' => 'P',  // 0x33 => 0x50
        '4' => 'l',  // 0x34 => 0x6c
        '5' => 'g',  // 0x35 => 0x67
        '6' => 'M',  // 0x36 => 0x4d
        '7' => 'e',  // 0x37 => 0x65
        '8' => 'r',  // 0x38 => 0x72
        '9' => 'T',  // 0x39 => 0x54
        'A' => 'A',  // 0x41 => 0x41
        'B' => 'X',  // 0x42 => 0x58
        'C' => 's',  // 0x43 => 0x73
        'D' => 'Z',  // 0x44 => 0x5a
        'E' => 'I',  // 0x45 => 0x49
        'F' => 'x',  // 0x46 => 0x78
        'G' => '5',  // 0x47 => 0x35
        'H' => '+',  // 0x48 => 0x2b
        'I' => 'U',  // 0x49 => 0x55
        'J' => 'p',  // 0x4a => 0x70
        'K' => 'o',  // 0x4b => 0x6f
        'L' => 'D',  // 0x4c => 0x44
        'M' => 'k',  // 0x4d => 0x6b
        'N' => 'F',  // 0x4e => 0x46
        'O' => 'C',  // 0x4f => 0x43
        'P' => 'L',  // 0x50 => 0x4c
        'Q' => 'c',  // 0x51 => 0x63
        'R' => 'w',  // 0x52 => 0x77
        'S' => 'Q',  // 0x53 => 0x51
        'T' => 'J',  // 0x54 => 0x4a
        'U' => '4',  // 0x55 => 0x34
        'V' => '1',  // 0x56 => 0x31
        'W' => '9',  // 0x57 => 0x39
        'X' => 'W',  // 0x58 => 0x57
        'Y' => 'E',  // 0x59 => 0x45
        'Z' => 'B',  // 0x5a => 0x42
        'a' => 'i',  // 0x61 => 0x69
        'b' => 'h',  // 0x62 => 0x68
        'c' => 'N',  // 0x63 => 0x4e
        'd' => 'G',  // 0x64 => 0x47
        'e' => 'S',  // 0x65 => 0x53
        'f' => 'b',  // 0x66 => 0x62
        'g' => 'a',  // 0x67 => 0x61
        'h' => 'Y',  // 0x68 => 0x59
        'i' => 'O',  // 0x69 => 0x4f
        'j' => 'q',  // 0x6a => 0x71
        'k' => 'z',  // 0x6b => 0x7a
        'l' => 'f',  // 0x6c => 0x66
        'm' => 'K',  // 0x6d => 0x4b
        'n' => 'H',  // 0x6e => 0x48
        'o' => '6',  // 0x6f => 0x36
        'p' => 'n',  // 0x70 => 0x6e
        'q' => 'd',  // 0x71 => 0x64
        'r' => 'm',  // 0x72 => 0x6d
        's' => 'u',  // 0x73 => 0x75
        't' => 'j',  // 0x74 => 0x6a
        'u' => 't',  // 0x75 => 0x74
        'v' => '8',  // 0x76 => 0x38
        'w' => '3',  // 0x77 => 0x33
        'x' => 'v',  // 0x78 => 0x76
        'y' => 'V',  // 0x79 => 0x56
        'z' => 'R'   // 0x7a => 0x52
    );
}

// --- Input Variables ---
// In the Perl script, cgi-lib.pl was used to populate %in. Here we use $_POST.
$in_upfile = isset($_POST['vmFile']) ? $_POST['vmFile'] : "";

list($queryString, $base64Data) = explode("\n", $in_upfile, 2);
parse_str($queryString, $params);

$filename = isset($params['filename']) ? $params['filename'] : 'default.vmu';

// Convert the entered filename to uppercase and remove non-alphanumeric characters.
$saveas = strtoupper($filename);
$saveas = preg_replace('/\W/', '', $saveas);
$saveas = substr($saveas, 0, 8);

// Ensure the filename is unique within the upload directory
$originalSaveas = $saveas;
$counter = 0;
while (file_exists("$updir/$saveas.VMS")) {
    $counter++;
    $suffix = "_$counter";
    $saveas = substr($originalSaveas, 0, 8 - strlen($suffix)) . $suffix;
}

// Calculate the actual size of the uploaded data
$actualFileSize = strlen($base64Data);

// --- Error Checking ---
$validname    = false;
$doesnotexist = false;
$fileselected = false;
$validFormat = true; // TODO: Check if the file is a valid VMS file
$validSize = false;

// Filename must be 1 to 8 characters long.
if (strlen($saveas) > 0 && strlen($saveas) < 32) {
    $validname = true;
}

// The .VMS file must not already exist.
if (!file_exists("$updir/$saveas.VMS")) {
    $doesnotexist = true;
}

// Check that content was actually uploaded (using CONTENT_LENGTH).
if (isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 500) {
    $fileselected = true;
}

// Check if the uploaded file is a valid VMI or VMS file
// if (isset($_POST['vmFile'])) {
//     $validFormat = in_array(strtolower($fileType), ['vmi', 'vms']);
// }


// Check if the file size is within the VMU capacity
if (isset($_POST['vmFile'])) {
    $validSize = $actualFileSize <= 128 * 1024; // 128 KB
}

// --- Process the Upload if All Checks Pass ---
if ($validname && $doesnotexist && $fileselected && $validFormat && $validSize) {

    // Split the incoming data into three sections.
    // Section 1: header info, Section 2: (blank), Section 3: save/game data.
    // (The Perl code uses split /\n/ with a limit of 3.)
    $vmudata = preg_split("/\n/", $in_upfile, 3);
    $header  = isset($vmudata[0]) ? $vmudata[0] : "";
    $content_str = isset($vmudata[2]) ? $vmudata[2] : "";

    // Break the game data into individual characters.
    $content = preg_split('//', $content_str, -1, PREG_SPLIT_NO_EMPTY);

    // Get the translation table.
    $trantable = getTranslationTable();

    // For each character in the game data, apply the translation table.
    // Newlines and carriage returns are left unchanged.
    $newcontent = array();
    foreach ($content as $char) {
        if ($char !== "\n" && $char !== "\r") {
            $newcontent[] = isset($trantable[$char]) ? $trantable[$char] : $char;
        } else {
            $newcontent[] = $char;
        }
    }
    $newcontent_str = implode("", $newcontent);

    // Decode the translated data from Base64. This is what is saved to the .VMS file.
    $decoded = base64_decode($newcontent_str);
    file_put_contents("$updir/$saveas.VMS", $decoded);

    // --- Parse the VMU Header ---
    // The header is expected to be a query-string–like set of key/value pairs separated by &.
    $vmuhead = array();
    $qd = explode("&", $header);
    foreach ($qd as $pair) {
        $parts = explode("=", $pair, 2);
        if (count($parts) == 2) {
            $vmuhead[$parts[0]] = $parts[1];
        }
    }

    // Debug logging
    // error_log("VMU Header contents:");
    // error_log(print_r($vmuhead, true));
    // error_log("Raw header: " . $header);

    // (Optional) Parse the date information from the header if present.
    if (isset($vmuhead['tm']) && preg_match("/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})(\d)/", $vmuhead['tm'], $matches)) {
        $year  = $matches[1];
        $month = $matches[2];
        $date  = $matches[3];
        $hr    = $matches[4];
        $min   = $matches[5];
        $sec   = $matches[6];
        $day   = $matches[7];
        $days  = array("Sun","Mon","Tue","Wed","Thr","Fri","Sat");
        $day2  = isset($days[$day]) ? $days[$day] : "";
        // (You might use these variables later if needed.)
    }

    // --- Build the .VMI File ---
    $originalname = isset($vmuhead['filename']) ? $vmuhead['filename'] : "";
    $filesize     = isset($vmuhead['fs'])       ? $vmuhead['fs']       : 0;

    // Extract description and copyright from the header
    $description = isset($vmuhead['description']) ? $vmuhead['description'] : "Planetweb Browser";
    $copyright = isset($vmuhead['copyright']) ? $vmuhead['copyright'] : "Planetweb, Inc.";

    // Prepare the VMI file content
    $vmi_data = pack('V', 0); // Checksum placeholder
    $vmi_data .= str_pad($description, 32, "\0"); // Description
    $vmi_data .= str_pad($copyright, 32, "\0"); // Copyright
    $vmi_data .= pack('v', $year); // Creation year
    $vmi_data .= pack('C', $month); // Creation month
    $vmi_data .= pack('C', $date); // Creation day
    $vmi_data .= pack('C', $hr); // Creation hour
    $vmi_data .= pack('C', $min); // Creation minute
    $vmi_data .= pack('C', $sec); // Creation second
    $vmi_data .= pack('C', $day); // Creation weekday
    $vmi_data .= pack('v', 0); // VMI version
    $vmi_data .= pack('v', 1); // File number
    $resource_name = str_pad($saveas, 8, "\0");
    $vmi_data .= $resource_name; // .VMS resource name
    $vmi_data .= str_pad($originalname, 12, "\0"); // Filename on the VMS

    // Extract file type from header or default to data file (0x0001)
    $fileMode = 0x0001; // Default to data file
    if (isset($vmuhead['tp']) && $vmuhead['tp'] === '1') {
        $fileMode = 0x0002; // Set to game file
    }
    
    $vmi_data .= pack('v', $fileMode); // File mode bitfield (0x0001 for data, 0x0002 for game)
    $vmi_data .= pack('v', 0); // Unknown, set to 0
    $vmi_data .= pack('V', $filesize); // File size in bytes

    // Calculate checksum by ANDing the first four bytes of the .VMS resource name with "SEGA"
    $sega_bytes = [0x53, 0x45, 0x47, 0x41]; // "SEGA"
    $checksum = 0;
    $resource_name_bytes = str_split($resource_name);
    for ($i = 0; $i < 4; $i++) {
        $byte_value = isset($resource_name_bytes[$i]) ? ord($resource_name_bytes[$i]) : 0;
        $checksum |= ($byte_value & $sega_bytes[$i]) << (8 * $i);
    }
    $vmi_data = substr_replace($vmi_data, pack('V', $checksum), 0, 4);

    // Save the .VMI file.
    file_put_contents("$updir/$saveas.vmi", $vmi_data);

    // Redirect back to the main page after successful upload
    header("Location: index.php");
    exit;

} else {
    // --- Display Errors ---
    echo "<html><body>";
    if (!$validname) {
        echo "<i>Error: Invalid filename given.</i><br>";
        echo "Filename must be one to eight characters long, and may only contain letters and numbers. Please go back and enter a new name.<br><br>";
    }
    if (!$doesnotexist) {
        echo "<i>Error: File already exists.</i><br>";
        echo "The filename you have given is already in use. Please go back and enter a different name.<br><br>";
    }
    if (!$fileselected) {
        echo "<i>Error: File not selected.</i><br>";
        echo "Before clicking upload, you must first click Browse to select a VMU file to upload. Please go back and select a file.<br><br>";
    }
    if (!$validFormat) {
        echo "<i>Error: Invalid file format.</i><br>";
        echo "Only .vmi and .vms files are allowed. Please go back and select a valid file.<br><br>";
    }
    if (!$validSize) {
        echo "<i>Error: File is too large.</i><br>";
        echo "The file exceeds the maximum size of 128 KB. Please go back and select a smaller file.<br><br>";
    }
    echo "<a href='javascript:history.go(-1)'>Go Back</a>";
    echo "</body></html>";
}
?>
