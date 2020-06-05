<?php
 # based on links, code & comments from http://www.hackerfactor.com/blog/?/archives/529-Kind-of-Like-That.html 
 # requires php7
 # requires php GD library, install:  sudo apt install php-gd;sudo phpenmod gd;
 # requires php-gmp, to install php-gmp in ubuntu 20: sudo apt install php-gmp;sudo phpenmod gmp;
 # Status: dHash1 is working
 # TODO: make test-able methods public & a test program
class NS_pHash
{
	private static $_dctConst = null;  
    private static function getDctConst():array
    {  # pre-cache DCT coefficients
       if(self::$_dctConst){ return self::$_dctConst;}  
       
       self::$_dctConst = array();  
       for ($dctP=0; $dctP<8; $dctP++) 
       {  
           for ($p=0;$p<32;$p++) 
           {  
               self::$_dctConst[$dctP][$p] = cos( ((2*$p + 1)/64) * $dctP * M_PI );  
           }  
       }  
      return self::$_dctConst;  
   }
   
     
    # returns 64-bit dHash
    public static function dHash64($imagePath)
    {  
       $img = self::get_resampled_gray($imagePath, 9, 8);
       if(!$img){ return NULL; }  
  
       $grays = array();  
       for ($y=0; $y<8; $y++)
       {  
           for ($x=0; $x<9; $x++)
           {  
               $grays[$y][$x] = imagecolorat($img, $x, $y) & 0xFF;  
           }  
       }  
       imagedestroy($img);
       
       # diffs:
       $bits = array();
       for ($y=0; $y<8; $y++)
       {  
           for ($x=0; $x<8; $x++)
           {  
               $bits[] = ($grays[$y][$x] < $grays[$y][$x+1]) ? '1' : '0';
           }  
       }  

       return gmp_strval( gmp_init(implode($bits),2) ); # 64-bit unsigned number as string, ready for database insert query
    }  
   
   
   
    # returns 16-bit dHash 
    public static function dHash16($imagePath)
    {  
       $img = self::get_resampled_gray($imagePath, 6, 4); # 6x4 = 24
       if(!$img){ return NULL; }  
  
       $grays = array();  
       for ($y=0; $y<4; $y++)
       {  
           for ($x=0; $x<6; $x++)
           {  
               $grays[$y][$x] = imagecolorat($img, $x, $y) & 0xFF;  
           }  
       }  
       imagedestroy($img);
       
       # calc diffs in x-direction
       $bits = [];
       for ($y=0; $y<4; $y++)
       {  
           for ($x=0; $x<5; $x++)
           { 
               $bits[] = ($grays[$y][$x] < $grays[$y][$x+1]) ? '1' : '0';
           } 
       }
       # remove corner positions [0][0], [0][4], [3][0], [3][4]
       unset($bits[0]); unset($bits[4]);
       unset($bits[15]);unset($bits[19]); 
       
       return gmp_strval( gmp_init(implode($bits),2) ); # 64-bit unsigned number as string, ready for database insert query
    }  
    
    
   
  private static function get_resampled_gray($ffname, $twid, $thei, $onlyJPG=TRUE)
  {
    try
    {
  		$thumb = exif_thumbnail($ffname, $swid, $shei, $stype);
  		if (!$thumb) 
		{
			$src = ($onlyJPG) ? imagecreatefromjpeg($ffname) : imagecreatefromstring(file_get_contents($ffname));
			list($swid, $shei) = array(imagesx($src), imagesy($src));
		} else 
		{
			$src = imagecreatefromstring($thumb);
			unset($thumb);
		}
		// resize to 32x32
		$img = imagecreatetruecolor($twid, $thei);
		imagecopyresampled($img, $src, 0, 0, 0, 0, $twid, $thei, $swid, $shei);
		imagedestroy($src);
		imagefilter($img, IMG_FILTER_GRAYSCALE);
	}
	catch(Exception $e) 
          		{ echo(__FILE__.'~72: Caught exception: '.$e->getMessage());
          		  return NULL;
          		}
	return $img;
  }
  
  # perceptual hash1, slow DCT
  public static function pHash1($ffname):string
    {
     // Resize the image.
     $img = self::get_resampled_gray($ffname,32,32);

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
            $matrix[$x] = self::calculateDCT1($col);
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
        $compare = self::median($pixels);
        //} else 
       // {
       # $compare = self::average($pixels);
       // }
       
        // Calculate hash.
        $bits = [];
        foreach ($pixels as $pixel) 
        {
            $bits[] = ($pixel > $compare) ? '1' : '0';
        }

        // have array of chars '0' or '1'
        return gmp_strval( gmp_init(implode($bits),2) ); # 64-bit unsigned number as string, ready for database insert query
    }

    
    # Perform a 1 dimension Discrete Cosine Transformation.
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

    
    # Get the median of the values.
    # The median compared to the mean (average) is that it is reflects the "typical value" of the set 
    # based on the count of all values
    protected static function median(array $pixels): float
    {
        sort($pixels, SORT_NUMERIC);

        if (count($pixels) % 2 === 0) 
        {
            return ($pixels[count($pixels) / 2 - 1] + $pixels[count($pixels) / 2]) / 2;
        }

        return $pixels[(int) floor(count($pixels) / 2)];
    }

    # Get the average of the values without the first one.
    protected static function average(array $pixels): float
    {
        // Calculate the average value from top 8x8 pixels, except for the first one.
        $n = count($pixels) - 1;
        return array_sum( array_slice($pixels, 1, $n) ) / $n;
    }
    
    
    # Average Hash
    public static function aHash($ffname)
    {  
       $img = self::get_resampled_gray($ffname,8,8);
       if(!$img){ return null; }  
  
       $graySum = 0;  
       $grays = array();  
       for ($y=0; $y<8; $y++)
       {  
           for ($x=0; $x<8; $x++)
           {  
               $gray = imagecolorat($img, $x, $y) & 0xFF;
               $grays[] = $gray;  
               $graySum +=  $gray;  
           }  
       }  
       imagedestroy($img);  
  
       $average = $graySum/64;  
  
       foreach ($grays as $i => $gray)
       {
         $grays[$i] = ($gray>=$average) ? '1' : '0';  
       }
  
       return gmp_strval( gmp_init(implode($grays),2) ); # 64-bit unsigned number as string, ready for database insert query 
     }  
     
     
     
     public static function pHash($ffname)
     {  
       $img = self::get_resampled_gray($ffname,32,32);
       if(!$img){ return null; }  
       
       $grays = array();  
       for ($y=0; $y<32; $y++)
       {  
          for ($x=0; $x<32; $x++)
          {  
               $grays[$y][$x] = imagecolorat($img, $x, $y) & 0xFF;  
          }  
       }  
       imagedestroy($img);  
  
  
       // DCT 8*8  
       $dctConst = self::getDctConst();
       $dctSum = 0;  
   
       $dcts = array();  
       for ($dctY=0; $dctY<8; $dctY++) 
       {  
          for ($dctX=0; $dctX<8; $dctX++) 
           {  
            $sum = 1;  
            for ($y=0;$y<32;$y++) 
            {  
               for ($x=0;$x<32;$x++) 
                 {  
                   $sum += $dctConst[$dctY][$y] * $dctConst[$dctX][$x] * $grays[$y][$x];  
                 }  
            }  
  
            // apply coefficients
            $sum = $sum * 0.25;
            if ($dctY == 0 || $dctX == 0) 
            { # exclude term[0][0]
              $sum = $sum * M_SQRT1_2; # M_SQRT1_2 == 1/sqrt(2);
            }
  
            $dcts[] = $sum;
            $dctSum +=  $sum;  
           }  
       }
  
      
      $average = $dctSum/64;  
        
      foreach ($dcts as $i => $dct)
      {  
           $dcts[$i] = ($dct>=$average) ? '1' : '0';  
      }  
  
     return gmp_strval( gmp_init(implode($dcts),2) ); # 64-bit unsigned number as string, ready for database insert query
   }  
    
    } # end class
