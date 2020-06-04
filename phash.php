<?php
 # requires php7
 # Status: non-working yet
 # TODO: make test-able methods public & a test program
class NS_Hasher
{
	private static $_dctConst = null;  
    private static function getDctConst():array
    {  # pre-cache DCT coefficients
       if(self::$_dctConst){ return self::$_dctConst;}  
       $pi = pi();
       self::$_dctConst = array();  
       for ($dctP=0; $dctP<8; $dctP++) 
       {  
           for ($p=0;$p<32;$p++) 
           {  
               self::$_dctConst[$dctP][$p] = cos( ((2*$p + 1)/64) * $dctP * $pi );  
           }  
       }  
      return self::$_dctConst;  
   }  
   
  private static function get_resampled_gray($fname, $wid, $hei)
  {
  	$thumb = exif_thumbnail($fname, $twid, $thei, $onlyJPG=TRUE);
  	if (!$thumb) 
	{
		$src = ($onlyJPG) ? imagecreatefromjpeg($fname) : imagecreatefromstring(file_get_contents($fname));
		list($wid, $hei) = array(imagesx($src), imagesy($src));
	} else 
	{
		$src = imagecreatefromstring($thumb);
		unset($thumb);
	}
	// resize to 32x32
	$img = imagecreatetruecolor($twid, $thei);
	imagecopyresampled($img, $src, 0, 0, 0, 0, $twid, $thei, $wid, $hei);
	imagedestroy($src);
	imagefilter($img, IMG_FILTER_GRAYSCALE);
	return $img;
  }
  
  
  public static function hash1($fname):Hash
    {
     // Resize the image.
     $img = get_resampled_gray($fname,32,32);

     $matrix = [];
     $row = [];
     $rows = [];
     $col = [];

        for ($y = 0; $y < 32; $y++) 
        {
            for ($x = 0; $x < 32; $x++) 
            {
                $row[$x] = imagecolorat($img, $x, $y) & 0xFF;
                # $row[$x] = (int) floor(($rgb[0] * 0.299) + ($rgb[1] * 0.587) + ($rgb[2] * 0.114));
            }
            $rows[$y] = self::calculateDCT1($row);
        }

        for ($x = 0; $x < 32; $x++) 
        {
            for ($y = 0; $y < 32; $y++) 
            {
                $col[$y] = $rows[$y][$x];
            }
            $matrix[$x] = $this->calculateDCT1($col);
        }

        // Extract the top 8x8 pixels.
        $pixels = [];
        for ($y = 0; $y < 8; $y++) 
        {
            for ($x = 0; $x < 8; $x++) 
            {
                $pixels[] = $matrix[$y][$x];
            }
        }

        // Method === MEDIAN) 
        //{
        //  $compare = $this->median($pixels);
        //} else 
       // {
            $compare = self::average($pixels);
       // }

        // Calculate hash.
        $bits = [];
        foreach ($pixels as $pixel) 
        {
            $bits[] = (int) ($pixel > $compare);
        }

        return Hash::fromBits($bits);
    }

    //
    // * Perform a 1 dimension Discrete Cosine Transformation.
    // 
    protected static function calculateDCT1(array $matrix): array
    {
        $transformed = [];
        $size = count($matrix);

        for ($i = 0; $i < $size; $i++) 
        {
            $sum = 0;
            for ($j = 0; $j < $size; $j++) 
            {
                $sum += $matrix[$j] * cos($i * pi() * ($j + 0.5) / $size);
            }
            $sum *= sqrt(2 / $size);
            if ($i === 0) 
            {
                $sum *= 1 / sqrt(2);
            }
            $transformed[$i] = $sum;
        }

        return $transformed;
    }

    /**
     * Get the median of the pixel values.
     TODO: Check this !! looks fake
     */
    protected function median(array $pixels): float
    {
        sort($pixels, SORT_NUMERIC);

        if (count($pixels) % 2 === 0) 
        {
            return ($pixels[count($pixels) / 2 - 1] + $pixels[count($pixels) / 2]) / 2;
        }

        return $pixels[(int) floor(count($pixels) / 2)];
    }

    /**
     * Get the average of the pixel values.
     */
    protected static function average(array $pixels): float
    {
        // Calculate the average value from top 8x8 pixels, except for the first one.
        $n = count($pixels) - 1;
        return array_sum( array_slice($pixels, 1, $n) ) / $n;
    }
    
    
    
    
    }
    
    
    
    
    
    
