<?php
header('Content-Type: text/html; charset=Shift-JIS');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Cloud VMU</title>
    <meta http-equiv="Content-Type" content="text/html; charset=Shift-JIS">
</head>
<body cellpadding="0" cellspacing="0" bgcolor="#f0ffff">
    <table width="598" border="0" cellspacing="0" cellpadding="4">
        <tr>
            <td valign="top">
                <h1><font size="7">Cloud VMU</font></h1>
            </td>
            <td valign="top" width="100">
                <table border="0" cellspacing="4" cellpadding="0" bgcolor="#dcdcdc">
                    <form action="upload.php" enctype="multipart/form-data" method="post">
                        <tr>
                            <td height="38"><input type="vmfile" name="vmFile" id="vmFile" placeholder="Select VMU file" required></td>
                            <td><input type="submit" name="submit" value="Upload"></td>
                        </tr>
                    </form>
                </table>
            </td>
        </tr>
    </table>
    
    <?php
    // Function to create a GIF from VMS icon data
    function createGifFromVmsFile($vmsFile, $outputGifPath, $typeText) {
        $vmsContent = file_get_contents("uploads/$vmsFile");
        
        // Add safety check for file content
        if ($vmsContent === false || strlen($vmsContent) < 512) {
            error_log("Invalid or incomplete VMS file: $vmsFile");
            return false;
        }
        
        // Debug: Check file content
        // error_log("VMS file size: " . strlen($vmsContent));
        
        if ($typeText == "ICON") {
            // Get monochrome and color icon offsets
            $monoIconOffset = unpack('V', substr($vmsContent, 16, 4))[1];
            $colorIconOffset = unpack('V', substr($vmsContent, 20, 4))[1];
            // error_log("Mono icon offset: " . $monoIconOffset);
            // error_log("Color icon offset: " . $colorIconOffset);
            
            if ($colorIconOffset === 0) {
                // Create monochrome image
                $image = imagecreatetruecolor(32, 32);
                
                // Allocate black and white colors
                $black = imagecolorallocate($image, 0, 0, 0);
                $white = imagecolorallocate($image, 255, 255, 255);
                
                // Fill background with white
                imagefill($image, 0, 0, $white);
                
                // Get monochrome bitmap data (128 bytes = 1024 bits for 32x32 pixels)
                $monoBitmapData = substr($vmsContent, $monoIconOffset, 128);
                
                // Process each bit for the 32x32 image
                for ($y = 0; $y < 32; $y++) {
                    for ($x = 0; $x < 32; $x++) {
                        $byteIndex = ($y * 32 + $x) >> 3; // Divide by 8 to get byte index
                        $bitIndex = 7 - ($x & 7); // Get bit position (7 to 0)
                        $byte = ord($monoBitmapData[$byteIndex]);
                        $pixel = ($byte >> $bitIndex) & 1; // Extract the bit
                        
                        // Set pixel (1 = black, 0 = white)
                        imagesetpixel($image, $x, $y, $pixel ? $black : $white);
                    }
                }
                
                // Save the monochrome image as GIF
                $result = imagegif($image, $outputGifPath);
                imagedestroy($image);
                return $result;
            }

            // Extract the palette from the color icon offset
            $paletteData = substr($vmsContent, $colorIconOffset, 32);
            // Extract the bitmap from the color icon offset
            $bitmapData = substr($vmsContent, $colorIconOffset + 32, 512);
        } else {
            // Get the header offset
            $headerOffset = 0;
            if ($typeText == "GAME") $headerOffset = 512;

            // Get the icon offset
            $iconOffset = $headerOffset + 0x60;

            // Get number of icons from offset 0x40
            $numIcons = ord($vmsContent[$headerOffset + 0x40]);
            // error_log("Number of icons: " . $numIcons);

            // If there is more than one icon, it is an animated icon
            if ($numIcons > 1) {
                // Get animation speed from offset 0x42 (16-bit integer)
                $animSpeed = unpack('v', substr($vmsContent, $headerOffset + 0x42, 2))[1];

                // Convert to delay in centiseconds (1/100th of a second)
                // Multiply by 4 to slow down the animation
                $delay = max(2, min(150, $animSpeed * 3)); // Clamp between 2-200 centiseconds
                // error_log("Animation speed: " . $animSpeed . ", delay: " . $delay);
                
                // Create temporary directory
                $tempDir = sys_get_temp_dir() . '/vmu_' . uniqid();
                mkdir($tempDir);
                
                // Extract the shared palette (32 bytes at 0x60)
                $paletteData = substr($vmsContent, $iconOffset, 32);
                $palette = [];
                for ($i = 0; $i < 16; $i++) {
                    $color = unpack('v', substr($paletteData, $i * 2, 2))[1];
                    // VMU uses 0x8000 bit for transparency
                    $isTransparent = (($color >> 12) & 0xF) << 4 == 0;
                    $r = (($color >> 8) & 0xF) << 4;
                    $g = (($color >> 4) & 0xF) << 4;
                    $b = ($color & 0xF) << 4;
                    $palette[] = [
                        'r' => $r,
                        'g' => $g,
                        'b' => $b,
                        'transparent' => $isTransparent
                    ];
                }
                
                // Process each icon
                for ($iconIndex = 0; $iconIndex < $numIcons; $iconIndex++) {
                    // Create new image for this frame with alpha channel
                    $frame = imagecreatetruecolor(32, 32);
                    imagealphablending($frame, false);
                    imagesavealpha($frame, true);
                    
                    // Create transparent background
                    $transparent = imagecolorallocatealpha($frame, 0, 0, 0, 127);
                    imagefilledrectangle($frame, 0, 0, 31, 31, $transparent);
                    
                    // Pre-allocate colors from shared palette
                    $colorIndices = [];
                    foreach ($palette as $i => $color) {
                        if ($color['transparent']) {
                            $colorIndices[] = $transparent;
                        } else {
                            $colorIndices[] = imagecolorallocate($frame, $color['r'], $color['g'], $color['b']);
                        }
                    }
                    
                    // Calculate bitmap offset for this icon
                    $bitmapOffset = $iconOffset + 0x20 + ($iconIndex * 512);
                    
                    // Extract and process bitmap data for this icon
                    $bitmapData = substr($vmsContent, $bitmapOffset, 512);
                    
                    // Draw this frame
                    for ($y = 0; $y < 32; $y++) {
                        for ($x = 0; $x < 32; $x++) {
                            $byteIndex = ($y * 32 + $x) >> 1;
                            $byteValue = ord($bitmapData[$byteIndex]);
                            $nybble = ($x & 1) ? $byteValue & 0xF : ($byteValue >> 4);
                            imagesetpixel($frame, $x, $y, $colorIndices[$nybble]);
                        }
                    }
                    
                    // Save frame as PNG with transparency
                    $tempFile = $tempDir . '/frame_' . sprintf('%03d', $iconIndex) . '.png';
                    imagepng($frame, $tempFile);
                    imagedestroy($frame);
                }
                
                // Use ImageMagick to create animated GIF with transparency
                $cmd = sprintf('convert -dispose background -delay %d -loop 0 -transparent white %s/frame_*.png %s', 
                    $delay,
                    escapeshellarg($tempDir),
                    escapeshellarg($outputGifPath)
                );
                exec($cmd);
                
                // Clean up temporary files
                array_map('unlink', glob("$tempDir/*.*"));
                rmdir($tempDir);
                
                return true;
            } else {
                // Get non-animated icon data

                $paletteData = substr($vmsContent, $iconOffset, 32);
                $bitmapData = substr($vmsContent, $iconOffset + 0x20, 512);
            }
        }
        
        // error_log("Palette data length: " . strlen($paletteData));
        
        $palette = [];
        for ($i = 0; $i < 16; $i++) {
            $color = unpack('v', substr($paletteData, $i * 2, 2))[1];
            $transparent = (($color >> 12) & 0xF) << 4 == 0;
            $r = (($color >> 8) & 0xF) << 4;
            $g = (($color >> 4) & 0xF) << 4;
            $b = ($color & 0xF) << 4;
            $palette[] = [$r, $g, $b, $transparent];
            // error_log("Color $i: R=$r G=$g B=$b (raw=$color)");
        }

        // error_log("Bitmap data length: " . strlen($bitmapData));
        
        $image = imagecreatetruecolor(32, 32);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        // Create transparent background
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefilledrectangle($image, 0, 0, 31, 31, $transparent);

        // Pre-allocate all palette colors
        $colorIndices = [];
        for ($i = 0; $i < 16; $i++) {
            $color = unpack('v', substr($paletteData, $i * 2, 2))[1];
            // VMU uses 0x8000 bit for transparency
            $isTransparent = ($color & 0x8000) == 0;
            $r = (($color >> 8) & 0xF) << 4;
            $g = (($color >> 4) & 0xF) << 4;
            $b = ($color & 0xF) << 4;
            
            if ($isTransparent) {
                $colorIndices[] = $transparent;
            } else {
                $colorIndices[] = imagecolorallocate($image, $r, $g, $b);
            }
        }

        // Process pixels
        for ($y = 0; $y < 32; $y++) {
            for ($x = 0; $x < 32; $x++) {
                $byteIndex = ($y * 32 + $x) >> 1;
                $byteValue = ord($bitmapData[$byteIndex]);
                $nybble = ($x & 1) ? $byteValue & 0xF : ($byteValue >> 4);
                imagesetpixel($image, $x, $y, $colorIndices[$nybble]);
            }
        }

        // Save the image as a PNG first (to preserve transparency)
        $tempFile = tempnam(sys_get_temp_dir(), 'vmu');
        imagepng($image, $tempFile);
        imagedestroy($image);

        // Convert PNG to GIF with transparency
        $cmd = sprintf('convert -transparent white %s %s',
            escapeshellarg($tempFile),
            escapeshellarg($outputGifPath)
        );
        exec($cmd);
        unlink($tempFile);

        return true;
    }

    function getVmsBlockSize($vmsContent, $isIconData) {
        // Calculate blocks by dividing total file size by 512 bytes (1 block)
        return ceil(strlen($vmsContent) / 512);
    }

    // Scan the uploads directory for .vmi files
    $files = scandir('uploads/');
    $vmiFiles = [];
    
    // Collect VMI files with their creation times
    foreach ($files as $file) {
        if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) == 'vmi') {
            $vmiFiles[] = [
                'name' => $file,
                'ctime' => filectime("uploads/$file")
            ];
        }
    }
    
    // Sort files by creation time (newest first)
    usort($vmiFiles, function($a, $b) {
        return $b['ctime'] - $a['ctime'];
    });
    
    // Process the sorted files
    $tableCounter = 0; // Add counter before the loop
    foreach ($vmiFiles as $vmiFile) {
        $file = $vmiFile['name'];
        // Read the .vmi file content
        $vmiContent = file_get_contents("uploads/$file");

        // Extract descriptions without converting to UTF-8, keep as Shift-JIS
        $shortDescription = trim(substr($vmiContent, 0x58, 12));
        $originalname = trim(substr($vmiContent, 0x58, 12));

        // Check if originalname is not ICONDATA_VMS before extracting the second description
        $isIconData = strcasecmp($originalname, 'ICONDATA_VMS') === 0;

        // Get file type (offset 0x64)
        $fileMode = ord($vmiContent[0x64]);
        $copyProtect = ($fileMode) & 1; // Extract first bit
        $fileType = ($fileMode >> 1) & 1; // Extract second bit
        $typeText = ($isIconData ? "ICON" : ($fileType == 1 ? "GAME" : "DATA"));

        // Parse the date and time from the .vmi file
        $year = unpack('v', substr($vmiContent, 68, 2))[1]; // Little-endian 2-byte integer
        $month = sprintf('%02d', ord($vmiContent[70]));
        $day = sprintf('%02d', ord($vmiContent[71]));
        $hour = sprintf('%02d', ord($vmiContent[72]));
        $minute = sprintf('%02d', ord($vmiContent[73]));
        $second = sprintf('%02d', ord($vmiContent[74]));
        $weekday = ord($vmiContent[75]);
        $days = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
        $dayName = isset($days[$weekday]) ? $days[$weekday] : "";

        // Check for the corresponding .vms file in a case-insensitive manner
        $vmsFile = str_ireplace('.vmi', '.vms', $file);
        $vmsFileFound = false;
        foreach ($files as $potentialVmsFile) {
            if (strcasecmp($vmsFile, $potentialVmsFile) == 0) {
                $vmsFile = $potentialVmsFile;
                $vmsFileFound = true;
                break;
            }
        }

        if ($vmsFileFound) {
            // Create the GIF file if it doesn't exist
            $gifPath = "uploads/" . pathinfo($file, PATHINFO_FILENAME) . ".gif";
            if (!file_exists($gifPath)) {
                createGifFromVmsFile($vmsFile, $gifPath, $typeText);
            }

            // Read the .vms file content
            $vmsContent = file_get_contents("uploads/$vmsFile");

            // Get the header offset
            $headerOffset = 0;
            if ($typeText == "GAME") $headerOffset = 512;

            // Extract descriptions from the .vms file
            $shortDescription = trim(substr($vmsContent, $headerOffset + 0, 16));
            if (!$isIconData) {
                $longDescription = trim(substr($vmsContent, $headerOffset + 16, 32));
            }

            // Calculate blocks
            $blockSize = getVmsBlockSize($vmsContent, $isIconData);

            // Render the table with the info
            $bgColor = ($tableCounter % 2 != 1) ? " bgcolor='#eeeeee'" : ""; // Alternate background color
            echo "<table width='598' cellspacing='0' cellpadding='2' border='0'$bgColor><tr>";
            // First column - Image
            echo "<td width='64' valign='top'>";
            echo "<a href='uploads/$file'><img src='$gifPath' width='64' height='64' alt='Icon' border='0' style='image-rendering: pixelated;'></a>";
            echo "</td>";
            // Second column - Nested table with info
            echo "<td><table width='100%' cellspacing='0' cellpadding='0'>";
            // Row 1 - Long Description or Short Description for icons, plus type
            echo "<tr><td width='100%'><font size='2'>" . ($isIconData ? $shortDescription : $longDescription) . "</font></td>";
            echo "<td width='40' align='right' valign='top'><font size='2'>$typeText</font></td></tr>";
            // Row 2 - Filename (and Short Description for non-icons)
            echo "<tr><td><font size='2'><strong>$originalname</strong>" . ($isIconData ? "" : "&nbsp; $shortDescription") . "</font></td></tr>";
            // Row 3 - Date/Time
            echo "<tr><td><font size='2'>$month/$day/$year $hour:$minute ($dayName) $blockSize block(s)</font></td></tr>";
            echo "</table></td>";
            // Add download link and delete button column
            echo "<td width='60' valign='top'>";
            // Create table for buttons
            echo "<table border='0' cellspacing='0' cellpadding='2'>";
            // Save button row
            echo "<tr><td align='center'>";
            echo "<input type='button' value='Save' size='50' onclick='window.location.href=\"uploads/$file\"'>";
            echo "</td></tr>";
            // Delete button row
            echo "<tr><td>";
            echo "<form action='delete.php' method='post' style='margin:0;' onsubmit='return confirmDelete()'>";
            echo "<input type='hidden' name='file' value='$file'>";
            echo "<input type='submit' value='Delete'>";
            echo "</form>";
            echo "</td></tr>";
            echo "</table>";
            echo "</td>";
            echo "</tr></table>";
            $tableCounter++; // Increment counter after each table
        } else {
            // Display the link with the originalname only if no .vms file is found
            echo "<li><a href='uploads/$file'>$originalname</a></li>";
        }
    }
    ?>
    <br><br>&nbsp;
    <script>
        function confirmDelete() {
            if (confirm("Are you sure you want to delete this file?")) {
                return true;
            }
            return false;
        }
    </script>
</body>
</html> 