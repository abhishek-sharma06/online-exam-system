<?php
/**
 * IMAGE AND AUDIO ANALYSIS FOR CHEATING DETECTION
 * Detects blank screens, blur, wrong tabs, background noise, etc.
 */

class CheatingDetector {
    
    /**
     * Analyze camera image for blank/black screen
     * Returns score 0-100 (100 = definitely blank)
     */
    public static function detectBlankScreen($imagePath) {
        try {
            if (!file_exists($imagePath)) {
                return 0;
            }
            
            $image = imagecreatefromjpeg($imagePath);
            if (!$image) return 0;
            
            $width = imagesx($image);
            $height = imagesy($image);
            
            // Sample 100 random pixels
            $darkPixels = 0;
            $totalSamples = 100;
            
            for ($i = 0; $i < $totalSamples; $i++) {
                $x = rand(0, $width - 1);
                $y = rand(0, $height - 1);
                
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                
                // Calculate brightness (0-255)
                $brightness = (($r * 0.299) + ($g * 0.587) + ($b * 0.114));
                
                // If pixel is very dark (brightness < 50)
                if ($brightness < 50) {
                    $darkPixels++;
                }
            }
            
            imagedestroy($image);
            
            // If 80%+ pixels are dark, it's likely blank/black screen
            $blankScore = ($darkPixels / $totalSamples) * 100;
            
            return min(100, $blankScore);
            
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Detect if image is blurred using edge detection
     * Returns score 0-100 (100 = definitely blurred)
     */
    public static function detectBlur($imagePath) {
        try {
            if (!file_exists($imagePath)) {
                return 0;
            }
            
            $image = imagecreatefromjpeg($imagePath);
            if (!$image) return 0;
            
            $width = imagesx($image);
            $height = imagesy($image);
            
            // Create a grayscale version for edge detection
            $gray = imagecreatetruecolor($width, $height);
            
            for ($y = 0; $y < $height; $y++) {
                for ($x = 0; $x < $width; $x++) {
                    $rgb = imagecolorat($image, $x, $y);
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
                    $b = $rgb & 0xFF;
                    $gray_val = intval(($r * 0.299) + ($g * 0.587) + ($b * 0.114));
                    imagefilledrectangle($gray, $x, $y, $x + 1, $y + 1, imagecolorallocate($gray, $gray_val, $gray_val, $gray_val));
                }
            }
            
            // Simple Laplacian edge detection
            $edges = 0;
            $samples = min(1000, $width * $height);
            
            for ($i = 0; $i < $samples; $i++) {
                $x = rand(1, $width - 2);
                $y = rand(1, $height - 2);
                
                $center = imagecolorat($gray, $x, $y) & 0xFF;
                $top = imagecolorat($gray, $x, $y - 1) & 0xFF;
                $bottom = imagecolorat($gray, $x, $y + 1) & 0xFF;
                $left = imagecolorat($gray, $x - 1, $y) & 0xFF;
                $right = imagecolorat($gray, $x + 1, $y) & 0xFF;
                
                $laplacian = abs(4 * $center - $top - $bottom - $left - $right);
                
                if ($laplacian > 30) {
                    $edges++;
                }
            }
            
            imagedestroy($image);
            imagedestroy($gray);
            
            // High edge count = sharp image, low edge count = blurred
            $edgeRatio = ($edges / $samples);
            $blurScore = max(0, 100 - ($edgeRatio * 200)); // 0-100 scale
            
            return min(100, $blurScore);
            
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Analyze screenshot for wrong tab/application
     * Detects if browser window is minimized or tab is switched
     * Returns indicators
     */
    public static function detectWrongTab($screenshotPath) {
        try {
            if (!file_exists($screenshotPath)) {
                return ['detected' => false, 'confidence' => 0];
            }
            
            $image = imagecreatefromjpeg($screenshotPath);
            if (!$image) return ['detected' => false, 'confidence' => 0];
            
            $width = imagesx($image);
            $height = imagesy($image);
            
            // Check if image appears to be a desktop (very colorful/different patterns)
            // vs a single focused app (more uniform colors)
            
            $colorVariance = self::calculateColorVariance($image, $width, $height);
            
            imagedestroy($image);
            
            // If color variance is very high and specific patterns detected, likely wrong tab
            // This is a heuristic - if exam page, should have relatively consistent colors
            $wrongTabConfidence = 0;
            
            if ($colorVariance > 150) {
                $wrongTabConfidence = 60; // Suspicious
            }
            
            return [
                'detected' => $wrongTabConfidence > 50,
                'confidence' => min(100, $wrongTabConfidence),
                'variance' => $colorVariance
            ];
            
        } catch (Exception $e) {
            return ['detected' => false, 'confidence' => 0];
        }
    }
    
    /**
     * Calculate overall color variance in image
     */
    private static function calculateColorVariance($image, $width, $height) {
        $colors = [];
        $samples = min(1000, $width * $height);
        
        for ($i = 0; $i < $samples; $i++) {
            $x = rand(0, $width - 1);
            $y = rand(0, $height - 1);
            
            $rgb = imagecolorat($image, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            
            $hue = self::rgbToHue($r, $g, $b);
            $colors[] = $hue;
        }
        
        if (empty($colors)) return 0;
        
        $mean = array_sum($colors) / count($colors);
        $variance = array_sum(array_map(function($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $colors)) / count($colors);
        
        return sqrt($variance);
    }
    
    /**
     * Convert RGB to HSV hue (0-360)
     */
    private static function rgbToHue($r, $g, $b) {
        $r /= 255;
        $g /= 255;
        $b /= 255;
        
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $delta = $max - $min;
        
        if ($delta == 0) return 0;
        
        if ($max == $r) {
            $hue = 60 * (fmod((($g - $b) / $delta), 6));
        } elseif ($max == $g) {
            $hue = 60 * ((($b - $r) / $delta) + 2);
        } else {
            $hue = 60 * ((($r - $g) / $delta) + 4);
        }
        
        return ($hue + 360) % 360;
    }
    
    /**
     * Detect face in camera image using simple color-based detection
     * Returns score 0-100 (100 = face likely present)
     */
    public static function detectFace($imagePath) {
        try {
            if (!file_exists($imagePath)) {
                return 0;
            }
            
            $image = imagecreatefromjpeg($imagePath);
            if (!$image) return 0;
            
            $width = imagesx($image);
            $height = imagesy($image);
            
            // Sample skin-tone colored pixels
            // Skin tones typically have R > 95, G > 40, B > 20, R > G > B
            $skinPixels = 0;
            $samples = 500;
            
            for ($i = 0; $i < $samples; $i++) {
                $x = rand(0, $width - 1);
                $y = rand(0, $height - 1);
                
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                
                // Basic skin tone detection
                if ($r > 95 && $g > 40 && $b > 20 && $r > $g && $g > $b && 
                    abs($r - $g) > 15) {
                    $skinPixels++;
                }
            }
            
            imagedestroy($image);
            
            // If 10%+ of sampled pixels are skin tone, likely contains a face
            $faceScore = ($skinPixels / $samples) * 100;
            
            return min(100, $faceScore);
            
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Generate cheating report based on analysis
     */
    public static function generateCheatingReport($cameraPath, $screenshotPath) {
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'indicators' => [],
            'suspicion_level' => 'LOW', // LOW, MEDIUM, HIGH
            'score' => 0
        ];
        
        $totalScore = 0;
        
        // Analyze camera
        if ($cameraPath && file_exists($cameraPath)) {
            $blankScore = self::detectBlankScreen($cameraPath);
            $blurScore = self::detectBlur($cameraPath);
            $faceScore = self::detectFace($cameraPath);
            
            if ($blankScore > 70) {
                $report['indicators'][] = 'Blank/Black screen in camera';
                $totalScore += 25;
            }
            if ($blurScore > 70) {
                $report['indicators'][] = 'Blurred camera image (possible camera cover)';
                $totalScore += 20;
            }
            if ($faceScore < 10) {
                $report['indicators'][] = 'No face detected in camera view';
                $totalScore += 15;
            }
        }
        
        // Analyze screenshot
        if ($screenshotPath && file_exists($screenshotPath)) {
            $tabResult = self::detectWrongTab($screenshotPath);
            if ($tabResult['detected'] && $tabResult['confidence'] > 60) {
                $report['indicators'][] = 'Possible tab switch or minimized window';
                $totalScore += 20;
            }
        }
        
        $report['score'] = $totalScore;
        
        // Determine suspicion level
        if ($totalScore >= 60) {
            $report['suspicion_level'] = 'HIGH';
        } elseif ($totalScore >= 40) {
            $report['suspicion_level'] = 'MEDIUM';
        } else {
            $report['suspicion_level'] = 'LOW';
        }
        
        return $report;
    }
}

?>
