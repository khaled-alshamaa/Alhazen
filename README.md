# Alhazen
Alhazen project for Arabic OCR in PHP (still in the pre-Alpha developing stage)

```php
<?php
    // Set debug mode [true|false]
    $debug = true;
    
    include('Alhazen.php');
    $obj = new Alhazen();

    // Load the page scan
    $obj->load('sample-doc.jpg');

    // Get color for (needs only for check/debug)
    if($debug) $c = ImageColorAllocate($obj->im, 0, 0, 255);

    // Analysis the document for ink density in x and y dimentions
    $obj->calcHist();
    
    // Find lines start/end borders at y axis (assuming they are fairly aligned properly)
    $lines = $obj->findLines();
    
    // Buffer in pixels to be used as a crop/border margine 
    $b = 5;
    
    // Get how many lines detected in the scanned page
    $n = count($lines[0]);

    // For each line
    for($i = 0; $i < $n; $i++){
        // Get the extent of each line
        list($x1, $y1, $x2, $y2, $v) = $obj->getLine($i);
        
        // The cropping rectangle as array with keys x, y, width and height
        $rect = ['x' => $x1-$b, 'y' => $y1-$b, 'width' => $x2-$x1+2*$b, 'height' => $y2-$y1+2*$b];
        
        // Crop the line image as GD object
        $lineImage = ImageCrop($obj->im, $rect);
        
        // Save the line image in a separate file
        if(!$debug) ImagePNG($lineImage, 'line_'.$i.'.png');

        // Draw a rectangle arround the detected line (just for check, stop it in production)
        if($debug) ImageRectangle($obj->im, $x1-$b, $y1-$b, $x2+$b, $y2+$b, $c);
    }

    if ($debug) {
        // Show output (just for check/debug)
        header('Content-type: image/png');
        ImagePNG($obj->im);
    } else {
        header('Location: ./');
        exit();
    }
?>
```
