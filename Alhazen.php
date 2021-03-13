<?php

/*
To do list:
- Check if the example text is complete
- Find out how to perform the median filter
- Check if using Y in the yCrCb can be better than current ink detection method
*/

class Alhazen {
    public $im;
    public $line;
    public $lineTo;
    public $lineFrom;
    public $lineWordTo;
    public $lineWordFrom;
    
    public $blurFilter     = array(array(array(0,1,0), array(1,1,1), array(0,1,0)), 0.2, 0);
    public $moreBlurFilter = array(array(array(0,0,1,0,0), array(0,1,1,1,0), array(1,1,1,1,1), array(0,1,1,1,0), array(0,0,1,0,0)), 0.077, 0);
    public $sharpenFilter  = array(array(array(-1,-1,-1,-1,-1), array(-1,2,2,2,-1), array(-1,2,8,2,-1), array(-1,2,2,2,-1), array(-1,-1,-1,-1,-1)), 0.125, 0);
    public $hEdgesFilter   = array(array(array(0,0,0,0,0), array(0,0,0,0,0), array(-1,-1,2,0,0), array(0,0,0,0,0), array(0,0,0,0,0)), 1, 0);
    public $vEdgesFilter   = array(array(array(0,0,-1,0,0), array(0,0,-1,0,0), array(0,0,2,0,0), array(0,0,0,0,0), array(0,0,0,0,0)), 1, 0);
    public $dEdgesFilter   = array(array(array(-1,0,0,0,0), array(0,-1,0,0,0), array(0,0,2,0,0), array(0,0,0,0,0), array(0,0,0,0,0)), 1, 0);
    public $edgesFilter    = array(array(array(-1,-1,0), array(-1,3,0), array(0,0,0)), 1, 0);
    public $embossFilter   = array(array(array(-1,-1,0), array(-1,0,1), array(0,1,1)), 1, 128);
    public $meanFilter     = array(array(array(1,1,1), array(1,1,1), array(1,1,1)), 0.111, 0);
    
    protected $h;
    protected $v;
    
    protected $x1;
    protected $x2;
    protected $y1;
    protected $y2;
    
    protected $debug = false;
    protected $max;
    
    public function setDebug($mode=true, $pixel=25) {
        $this->debug = $mode;
        $this->max   = $pixel;
    }
    
    public function load($file) {
        $size = getImageSize($file);
        
        if ($size[2] == 1) {
            $this->im = imageCreateFromGIF($file);
        } else if ($size[2] == 2) {
            $this->im = imageCreateFromJPEG($file);
        } else if ($size[2] == 3) {
            $this->im = imageCreateFromPNG($file);
        }
        
        return $this->im;
    }

    public function getArea() {
    }
    
    public function calcHist($x1=null, $y1=null, $x2=null, $y2=null) {
        if (is_null($x1)) { $x1 = 0; }
        if (is_null($y1)) { $y1 = 0; }
        if (is_null($x2)) { $x2 = imagesx($this->im); }
        if (is_null($y2)) { $y2 = imagesy($this->im); }
        
        $this->x1 = $x1;
        $this->x2 = $x2;
        $this->y1 = $y1;
        $this->y2 = $y2;
    
        $h = array_fill($y1, $y2-$y1, 0);

        for ($i=$x1; $i<$x2; $i++) {
            for ($j=$y1; $j<$y2; $j++) {
                $rgb = imagecolorat($this->im, $i, $j);
                if ($this->isInk($rgb)) {
                    $h[$j]++;
                }
            }
        }

        if ($this->debug === true) {
            $c = ImageColorAllocate($this->im, 255, 0, 0);
            imagerectangle($this->im, $x1, $y1, $x2, $y2, $c);
            
            $maxH   = max($h);
            $scaleH = $this->max / $maxH;
            $lastX  = imagesx($this->im);
            
            foreach ($h as $j=>$d) {
                $d = $d * $scaleH;
                ImageLine($this->im, $lastX, $j, $lastX-$d, $j, $c);
            }
        }
        
        $this->h = $h;
        
        return $h;
    }
    
    public function medianFilter() {
        $this->im;
    }
    
    public function findLines($h=null) {
        if (is_null($h)) { $h = $this->h; }
        
        $from = array();
        $to   = array();
        $diff = array();
        
        $avg    = array_sum($h) / count($h);
        $cut    = 0.2 * $avg;
        $isLine = false;
        
        foreach ($h as $j=>$d) {
            if ($d > $cut && !$isLine) {
                $isLine = true;
                $from[] = $j;
                if (isset($start)) {
                    $diff[] = $j - $start;
                }
            } else if ($d < $cut && $isLine) {
                $isLine = false;
                $to[]   = $j;
                $start  = $j;
            }
        }
        
        if ($isLine) {
            $to[] = $j;
        }
        
        $avg = array_sum($diff) / count($diff);
        $cut = 0.2 * $avg;

        foreach ($diff as $line=>$width) {
            if ($width < $cut) {
                $newTo = $to[$line+1];

                unset($from[$line+1]);
                unset($to[$line+1]);

                while (!isset($from[$line])) {
                    $line--;
                }
                $to[$line] = $newTo;
            }
        }
        
        sort($from);
        sort($to);
        
        $this->lineFrom = $from;
        $this->lineTo   = $to;
        
        return array($from, $to);
    }
    
    public function getLine($line=0, $threshold=0.5, $from=null, $to=null) {
        if (is_null($from)) $from = $this->lineFrom;
        if (is_null($to))   $to   = $this->lineTo;
        
        $y1 = $from[$line];
        $y2 = $to[$line];
        
        $x1 = $this->x1;
        $x2 = $this->x2;

        $v = array_fill($x1, $x2-$x1, 0);

        for ($i=$x1; $i<$x2; $i++) {
            for ($j=$y1; $j<$y2; $j++) {
                $rgb = imagecolorat($this->im, $i, $j);
                if ($this->isInk($rgb)) {
                    $v[$i]++;
                }
            }
        }

        $avg = array_sum($v) / count($v);
        $cut = $threshold * $avg;
        
        $lineStart = false;
        
        foreach ($v as $i=>$d) {
            if ($d > $cut) {
                if (!$lineStart) {
                    $x1 = $i;
                    $lineStart = true;
                } else {
                    $x2 = $i;
                }
            }
        }

        if ($this->debug === true) {
            $c = ImageColorAllocate($this->im, 0, 0, 255);
            $w = ImageColorAllocate($this->im, 255, 255, 255);
            
            $style = array($c, $c, $c, $w, $w, $w);
            imagesetstyle($this->im, $style);

            imageline($this->im, $x1, $y1-1, $x2, $y1-1, IMG_COLOR_STYLED);
            imageline($this->im, $x1, $y2+1, $x2, $y2+1, IMG_COLOR_STYLED);

            $maxV   = max($v);
            $scaleV = $this->max / $maxV;
            
            foreach ($v as $j=>$d) {
                $d = $d * $scaleV;
                imageline($this->im, $j, 0, $j, $d, $c);
            }
        }
        
        $this->v    = $v;
        $this->line = $line;
        
        return array($x1, $y1, $x2, $y2, $v);
    }
    
    public function findLineWords($v=null, $threshold=0.5) {
        if (is_null($v)) $v = $this->v;
        
        $from = array();
        $to   = array();
        $diff = array();
        
        $avg    = array_sum($v) / count($v);
        $cut    = $threshold * $avg;
        $isWord = false;
        
        foreach ($v as $i=>$d) {
            if ($d > $cut && !$isWord) {
                $isWord = true;
                $from[] = $i;
                if (isset($start)) {
                    $diff[] = $i - $start;
                }
            } else if ($d < $cut && $isWord) {
                $isWord = false;
                $to[]   = $i;
                $start  = $i;
            }
        }
        
        if ($isWord) {
            $to[] = $i;
        }
        
        $cut = $this->kMeans($diff);

        foreach ($diff as $word=>$width) {
            if ($width < $cut) {
                $newTo = $to[$word+1];

                unset($from[$word+1]);
                unset($to[$word+1]);

                while (!isset($from[$word])) {
                    $word--;
                }
                $to[$word] = $newTo;
            }
        }
        
        sort($from);
        sort($to);
        
        if ($this->debug === true) {
            $y1 = $this->lineFrom[$this->line];
            $y2 = $this->lineTo[$this->line];
        
            foreach ($from as $word=>$x1) {
                $x2 = $to[$word];
                $c = ImageColorAllocate($this->im, 0, 0, 255);
                imagerectangle($this->im, $x1, $y1, $x2, $y2, $c);
            }
        }
        
        $this->lineWordFrom[$this->line] = $from;
        $this->lineWordTo[$this->line]   = $to;
        
        return array($from, $to);
    }
    
    protected function isInk($rgb) {
        $ink = false;
        
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;

        if ($r<128 && $g<128 && $b<128) {
            $ink = true;
        }
        
        return $ink;
    }
    
    protected function isSkin($rgb) {
        $skin = false;
        
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;

        $diff = max($r, $g, $b) - min($r, $g, $b);
        
        // One method to build a skin classifier is to define explicitly (through
        // a number of rules) the boundaries skin cluster in some colorspace.
        // For example [Peer et al. 2003]
        if ($r>95 && $g>40 && $b>20 && $diff>15 && abs($r-$g)>15  && $r>$g && $r>$b) {
            $skin = true;
        }
        
        return $skin;
    }
    
    protected function yCrCb($rgb) {
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;
        
        $y  = 0.299*$r + 0.587*$g + 0.114*$b;
        $cr = ($r - $y) * 0.713;
        $cb = ($b - $y) * 0.564;
        
        return array($y, $cr, $cb);
    }
    
    protected function kMeans($diff) {
        $mean = array_sum($diff) / count($diff);
        $var  = 0;
        
        foreach ($diff as $value) {
            $var += ($value - $mean) * ($value - $mean);
        }

        $sd    = sqrt($var / (count($diff) - 1));
        $diff2 = array();
        
        foreach ($diff as $value) {
            if ($value < 3*$sd) {
                $diff2[] = $value;
            }
        }
        
        $min   = min($diff2);
        $max   = max($diff2);
        $range = $max - $min;
        
        $a = $min + 0.2 * $range;
        $b = $min + 0.8 * $range;
        do {
            $A = array();
            $B = array();
            
            foreach ($diff2 as $point) {
                if (abs($point - $a) < abs($point - $b)) {
                    $A[] = $point;
                } else {
                    $B[] = $point;
                }
            }
            
            $a2 = array_sum($A) / count($A);
            $b2 = array_sum($B) / count($B);
            
            if ($a == $a2 && $b == $b2) {
                $moved = false;
            } else {
                $moved = true;
                $a = $a2;
                $b = $b2;
            }
        } while ($moved);
        
        $cut = ($a + $b) / 2;
        
        return $cut;
    }

    public function checkSkin($x1=null, $y1=null, $x2=null, $y2=null) {
        if (is_null($x1)) { $x1 = 0; }
        if (is_null($y1)) { $y1 = 0; }
        if (is_null($x2)) { $x2 = imagesx($this->im); }
        if (is_null($y2)) { $y2 = imagesy($this->im); }
        
        $this->x1 = $x1;
        $this->x2 = $x2;
        $this->y1 = $y1;
        $this->y2 = $y2;
    
        $c = ImageColorAllocate($this->im, 0, 128, 0);

        for ($i=$x1; $i<$x2; $i++) {
            for ($j=$y1; $j<$y2; $j++) {
                $rgb = imagecolorat($this->im, $i, $j);
                if ($this->isSkin($rgb)) {
                    imagerectangle($this->im, $i, $j, $i, $j, $c);
                }
            }
        }
    }

    public function applyFilter($filter, $factor=1, $bias=0) {
        $w = imagesx($this->im);
        $h = imagesy($this->im);
        
        $result = imageCreateTrueColor($w, $h);
        
        $filterWidth  = count($filter);
        $filterHeight = count($filter[0]);
        
        // apply the filter 
        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                $red   = 0;
                $green = 0;
                $blue  = 0;
                
                // multiply every value of the filter with corresponding image pixel
                for ($filterX = 0; $filterX < $filterWidth; $filterX++) {
                    for ($filterY = 0; $filterY < $filterHeight; $filterY++) {
                        $imageX = ($x - $filterWidth / 2 + $filterX + $w) % $w; 
                        $imageY = ($y - $filterHeight / 2 + $filterY + $h) % $h;
                        
                        $rgb = imageColorAt($this->im, $imageX, $imageY);

                        $r = ($rgb >> 16) & 0xFF;
                        $g = ($rgb >> 8) & 0xFF;
                        $b = $rgb & 0xFF;
                        
                        $red   += $r * $filter[$filterX][$filterY]; 
                        $green += $g * $filter[$filterX][$filterY]; 
                        $blue  += $b * $filter[$filterX][$filterY]; 
                    }
                }
                
                // truncate values smaller than zero and larger than 255
                $red   = min(abs((int)($factor * $red + $bias)), 255); 
                $green = min(abs((int)($factor * $green + $bias)), 255); 
                $blue  = min(abs((int)($factor * $blue + $bias)), 255);
                
                $c = ImageColorAllocate($result, $red, $green, $blue);
                imageRectangle($result, $x, $y, $x, $y, $c);
            }
        }
        
        return $result;
    }
}